<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Audit;
use App\Support\Notify;
use App\Support\Validator;

/**
 * Uang jalan (bagian 4): pencairan (kasbon kru), realisasi real-time dari
 * pengeluaran disetujui, rekonsiliasi selisih + approval manajer bila lewat toleransi.
 */
final class UangJalanController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->poolId() ? ' AND uj.pool_id = ' . (int) $this->poolId() : '';
        $rows = $this->db()->all("
            SELECT uj.*, k.nama AS kru, r.kode AS rit_kode, r.status AS rit_status,
                (SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE rit_id=uj.rit_id AND status='disetujui') AS realisasi
            FROM uang_jalan uj
            JOIN kru k ON k.id=uj.kru_id
            JOIN rit r ON r.id=uj.rit_id
            WHERE 1=1 $scope ORDER BY uj.dibuat_pada DESC");
        // Rit yang butuh pencairan (rencana/berjalan tanpa uang jalan)
        $ritTanpaUJ = $this->db()->all("
            SELECT r.id, r.kode, b.nopol FROM rit r JOIN bus b ON b.id=r.bus_id
            WHERE r.status IN ('rencana','berjalan')
            " . ($this->poolId() ? 'AND r.pool_id='.(int)$this->poolId() : '') . "
            ORDER BY r.tanggal DESC");
        $kru = $this->db()->all('SELECT id, nama FROM kru WHERE aktif=1 ORDER BY nama');
        return $this->view('uang_jalan/index', [
            'title' => 'Uang Jalan', 'rows' => $rows, 'ritTanpaUJ' => $ritTanpaUJ, 'kru' => $kru,
        ]);
    }

    public function cair(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'rit_id' => 'required|int', 'kru_id' => 'required|int', 'nominal_diberikan' => 'required|numeric|gt:0',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $rit = $this->db()->first('SELECT * FROM rit WHERE id = ?', [$d['rit_id']]);
        if (!$rit) { return $this->backWithErrors($req, ['Rit tidak ditemukan.']); }

        $akun = $this->db()->first('SELECT id FROM akun_kas WHERE aktif=1' . ($this->poolId() ? ' AND pool_id='.(int)$this->poolId() : '') . ' ORDER BY id LIMIT 1');

        $this->db()->transaction(function ($db) use ($d, $rit, $akun) {
            $ujId = $db->insert('uang_jalan', [
                'pool_id' => $rit['pool_id'], 'rit_id' => $d['rit_id'], 'kru_id' => $d['kru_id'],
                'nominal_diberikan' => $d['nominal_diberikan'], 'status' => 'dicairkan',
                'dicairkan_oleh' => $this->userId(), 'dicairkan_pada' => date('Y-m-d H:i:s'),
            ]);
            // Kasbon kru (debit = kru berutang)
            $db->insert('kasbon', [
                'pool_id' => $rit['pool_id'], 'kru_id' => $d['kru_id'], 'arah' => 'debit',
                'nominal' => $d['nominal_diberikan'], 'keterangan' => 'Pencairan uang jalan rit ' . $rit['kode'],
                'ref_tipe' => 'uang_jalan', 'ref_id' => $ujId,
            ]);
            // Mutasi kas keluar
            if ($akun) {
                $db->insert('mutasi_kas', [
                    'pool_id' => $rit['pool_id'], 'akun_id' => $akun['id'], 'arah' => 'keluar',
                    'nominal' => $d['nominal_diberikan'], 'keterangan' => 'Uang jalan rit ' . $rit['kode'],
                    'ref_tipe' => 'uang_jalan', 'ref_id' => $ujId, 'dibuat_oleh' => $this->userId(),
                ]);
            }
        });
        Audit::log('cair_uang_jalan', 'rit', (int) $d['rit_id'], rupiah($d['nominal_diberikan']));
        Session::flash('success', 'Uang jalan dicairkan & tercatat sebagai kasbon kru.');
        return $this->redirect('/uang-jalan');
    }

    public function rekonsiliasi(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $uj = $this->db()->first('SELECT uj.*, r.rute_id, r.kode FROM uang_jalan uj JOIN rit r ON r.id=uj.rit_id WHERE uj.id = ?', [$id]);
        if (!$uj) { return $this->redirect('/uang-jalan'); }

        // Realisasi real-time (bukan cache)
        $realisasi = (float) $this->db()->scalar(
            "SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE rit_id = ? AND status='disetujui'", [$uj['rit_id']]);
        $selisih = (float) $uj['nominal_diberikan'] - $realisasi; // + = sisa dikembalikan, - = kurang bayar

        // Toleransi: pakai toleransi terbesar dari standar rute; default 10%
        $tolPct = (float) ($this->db()->scalar(
            "SELECT MAX(toleransi_pct) FROM standar_biaya_rute WHERE rute_id = ? AND berlaku_sampai IS NULL", [$uj['rute_id']]) ?? 10);
        $batasToleransi = (float) $uj['nominal_diberikan'] * ($tolPct / 100);
        $alasan = Validator::clean($req->input('alasan', ''));

        if (abs($selisih) > $batasToleransi && $alasan === '') {
            Session::flash('error', "Selisih (" . rupiah(abs($selisih)) . ") melebihi toleransi. Wajib isi alasan tertulis.");
            return $this->redirect('/uang-jalan');
        }

        $melebihi = abs($selisih) > $batasToleransi;
        // Bila melebihi toleransi, butuh approval manajer -> status 'dilaporkan' dulu
        $canApprove = $this->can(['owner','manajer']);
        $status = ($melebihi && !$canApprove) ? 'dilaporkan' : 'disetujui';

        $this->db()->transaction(function ($db) use ($id, $uj, $realisasi, $selisih, $alasan, $status) {
            $db->update('uang_jalan', [
                'status' => $status, 'selisih' => $selisih, 'alasan_selisih' => $alasan ?: null,
                'rekonsiliasi_oleh' => $this->userId(), 'rekonsiliasi_pada' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            // Bila kru mengembalikan sisa (selisih>0) catat kredit kasbon (setoran)
            if ($status === 'disetujui' && $selisih > 0) {
                $db->insert('kasbon', [
                    'pool_id' => $uj['pool_id'], 'kru_id' => $uj['kru_id'], 'arah' => 'kredit',
                    'nominal' => $selisih, 'keterangan' => 'Setoran sisa uang jalan rit ' . $uj['kode'],
                    'ref_tipe' => 'uang_jalan', 'ref_id' => $id,
                ]);
            } elseif ($status === 'disetujui' && $selisih < 0) {
                // Kurang bayar: perusahaan menambah pembayaran ke kru (kredit kasbon menutup kelebihan pakai)
                $db->insert('kasbon', [
                    'pool_id' => $uj['pool_id'], 'kru_id' => $uj['kru_id'], 'arah' => 'kredit',
                    'nominal' => abs($selisih), 'keterangan' => 'Pembayaran kurang uang jalan rit ' . $uj['kode'],
                    'ref_tipe' => 'uang_jalan', 'ref_id' => $id,
                ]);
            }
        });

        if ($status === 'dilaporkan') {
            Notify::toRole($uj['pool_id'], 'manajer', 'approval',
                'Rekonsiliasi uang jalan melebihi toleransi', 'Rit ' . $uj['kode'] . ' — selisih ' . rupiah(abs($selisih)), '/uang-jalan');
            Session::flash('warn', 'Selisih melebihi toleransi — dilaporkan & menunggu approval manajer.');
        } else {
            Session::flash('success', 'Rekonsiliasi selesai. Selisih: ' . rupiah($selisih) .
                ($selisih >= 0 ? ' (sisa dikembalikan kru)' : ' (kurang bayar dilunasi)'));
        }
        Audit::log('rekonsiliasi_uj', 'uang_jalan', $id, 'selisih=' . $selisih);
        return $this->redirect('/uang-jalan');
    }

    public function riwayatKru(Request $req): Response
    {
        $kruId = (int) $req->param('kruId');
        $kru = $this->db()->first('SELECT * FROM kru WHERE id = ?', [$kruId]);
        if (!$kru) { return $this->redirect('/uang-jalan'); }
        $rows = $this->db()->all("
            SELECT uj.*, r.kode AS rit_kode,
                (SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE rit_id=uj.rit_id AND status='disetujui') AS realisasi
            FROM uang_jalan uj JOIN rit r ON r.id=uj.rit_id
            WHERE uj.kru_id = ? ORDER BY uj.dibuat_pada DESC", [$kruId]);
        return $this->view('uang_jalan/riwayat_kru', ['title' => 'Riwayat UJ — ' . $kru['nama'], 'kru' => $kru, 'rows' => $rows]);
    }
}
