<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Audit;
use App\Support\Notify;
use App\Support\Upload;
use App\Support\Validator;

/**
 * Pengeluaran (bagian 5): input admin (rit & non-rit), form BBM,
 * upload nota, validasi, deteksi anomali vs standar, kunci saat rit ditutup.
 */
final class PengeluaranController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->poolId() ? ' AND e.pool_id = ' . (int) $this->poolId() : '';
        $status = $req->input('status', '');
        $where = 'WHERE 1=1' . $scope;
        $params = [];
        if (in_array($status, ['menunggu','disetujui','ditolak'], true)) {
            $where .= ' AND e.status = ?'; $params[] = $status;
        }
        $page = max(1, (int) $req->input('page', 1));
        $per = 20; $offset = ($page - 1) * $per;
        $total = (int) $this->db()->scalar("SELECT COUNT(*) FROM pengeluaran e $where", $params);
        $rows = $this->db()->all("
            SELECT e.*, k.nama AS kategori, r.kode AS rit_kode
            FROM pengeluaran e JOIN kategori_biaya k ON k.id=e.kategori_id
            LEFT JOIN rit r ON r.id=e.rit_id
            $where ORDER BY e.dibuat_pada DESC LIMIT $per OFFSET $offset", $params);
        return $this->view('pengeluaran/index', [
            'title' => 'Pengeluaran', 'rows' => $rows, 'total' => $total,
            'page' => $page, 'per' => $per, 'status' => $status,
            'kategori' => $this->db()->all('SELECT * FROM kategori_biaya WHERE aktif=1 ORDER BY nama'),
            'vendor' => $this->db()->all('SELECT * FROM vendor WHERE aktif=1 ORDER BY nama'),
        ]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'kategori_id' => 'required|int', 'nominal' => 'required|numeric|gt:0',
            'tanggal' => 'required|date|not_future',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $ritId = $req->input('rit_id') ? (int) $req->input('rit_id') : null;

        // Rit terkunci?
        if ($ritId) {
            $rit = $this->db()->first('SELECT status FROM rit WHERE id = ?', [$ritId]);
            if ($rit && in_array($rit['status'], ['selesai','batal'], true)) {
                return $this->backWithErrors($req, ['Rit sudah ditutup/batal — pengeluaran terkunci.']);
            }
        }

        $kategori = $this->db()->first('SELECT * FROM kategori_biaya WHERE id = ?', [$d['kategori_id']]);
        if (!$kategori) { return $this->backWithErrors($req, ['Kategori tidak valid.']); }

        // Upload nota (wajib bila kategori mensyaratkan)
        $lampiran = null;
        $file = $req->file('lampiran');
        if ($file) {
            try { $lampiran = Upload::store($file, 'nota'); }
            catch (\RuntimeException $e) { return $this->backWithErrors($req, [$e->getMessage()]); }
        }
        if ((int) $kategori['wajib_bukti'] === 1 && !$lampiran) {
            return $this->backWithErrors($req, ['Kategori ini wajib melampirkan foto nota.']);
        }

        // Deteksi anomali vs standar rute (toleransi)
        $flag = 0;
        if ($ritId) {
            $flag = $this->cekAnomali($ritId, (int) $d['kategori_id'], (float) $d['nominal']) ? 1 : 0;
        }

        $id = $this->db()->insert('pengeluaran', [
            'pool_id' => $this->poolId(), 'rit_id' => $ritId, 'kategori_id' => $d['kategori_id'],
            'vendor_id' => $req->input('vendor_id') ? (int) $req->input('vendor_id') : null,
            'nominal' => $d['nominal'], 'tanggal' => $d['tanggal'],
            'keterangan' => Validator::clean($req->input('keterangan')),
            'odometer' => $req->input('odometer') ? (int) $req->input('odometer') : null,
            'liter' => $req->input('liter') ? (float) $req->input('liter') : null,
            'harga_liter' => $req->input('harga_liter') ? (float) $req->input('harga_liter') : null,
            'tangki_penuh' => $req->input('tangki_penuh') ? 1 : null,
            'lampiran' => $lampiran, 'sumber' => 'admin', 'status' => 'menunggu', 'flag_anomali' => $flag,
            'dibuat_oleh' => $this->userId(),
        ]);

        // Notifikasi ke approver
        Notify::toRole($this->poolId(), 'manajer', 'approval', 'Pengeluaran menunggu approval',
            rupiah($d['nominal']) . ' — ' . $kategori['nama'] . ($flag ? ' (ANOMALI)' : ''), '/approval');

        Session::flash($flag ? 'warn' : 'success',
            $flag ? 'Pengeluaran tersimpan & ditandai ANOMALI (melebihi toleransi standar).' : 'Pengeluaran tersimpan, menunggu approval.');
        return $ritId ? $this->redirect('/rit/' . $ritId) : $this->redirect('/pengeluaran');
    }

    /** True jika nominal melebihi standar rute + toleransi. */
    private function cekAnomali(int $ritId, int $kategoriId, float $nominal): bool
    {
        $std = $this->db()->first("
            SELECT s.nominal_standar, s.toleransi_pct FROM rit r
            JOIN standar_biaya_rute s ON s.rute_id = r.rute_id AND s.kategori_id = ? AND s.berlaku_sampai IS NULL
            WHERE r.id = ? LIMIT 1", [$kategoriId, $ritId]);
        if (!$std) { return false; }
        $batas = (float) $std['nominal_standar'] * (1 + ((float) $std['toleransi_pct'] / 100));
        return $nominal > $batas;
    }

    public function update(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $e = $this->db()->first('SELECT e.*, r.status AS rit_status FROM pengeluaran e LEFT JOIN rit r ON r.id=e.rit_id WHERE e.id = ?', [$id]);
        if (!$e) { return $this->redirect('/pengeluaran'); }
        if ($e['rit_status'] && in_array($e['rit_status'], ['selesai','batal'], true) && !$this->can(['owner','manajer'])) {
            Session::flash('error', 'Rit sudah ditutup. Hanya manajer/owner yang bisa mengubah.');
            return $this->back($req);
        }
        $nominal = (float) $req->input('nominal', $e['nominal']);
        $this->db()->update('pengeluaran', [
            'nominal' => $nominal,
            'keterangan' => Validator::clean($req->input('keterangan', $e['keterangan'])),
        ], ['id' => $id]);
        if ($e['rit_status'] && in_array($e['rit_status'], ['selesai','batal'], true)) {
            Audit::log('edit_setelah_tutup', 'pengeluaran', $id, 'alasan: ' . Validator::clean($req->input('alasan', '-')));
        }
        Session::flash('success', 'Pengeluaran diperbarui.');
        return $this->back($req);
    }

    public function destroy(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $e = $this->db()->first('SELECT e.*, r.status AS rit_status FROM pengeluaran e LEFT JOIN rit r ON r.id=e.rit_id WHERE e.id = ?', [$id]);
        if (!$e) { return $this->redirect('/pengeluaran'); }
        if ($e['rit_status'] && in_array($e['rit_status'], ['selesai','batal'], true)) {
            Session::flash('error', 'Pengeluaran pada rit yang sudah ditutup tidak bisa dihapus.');
            return $this->back($req);
        }
        $this->db()->delete('pengeluaran', ['id' => $id]);
        Audit::log('hapus_pengeluaran', 'pengeluaran', $id);
        Session::flash('success', 'Pengeluaran dihapus.');
        return $this->back($req);
    }
}
