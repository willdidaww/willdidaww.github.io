<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Audit;
use App\Support\Validator;

/**
 * Rit — inti operasional (bagian 3).
 */
final class RitController extends Controller
{
    private function scope(): string
    {
        return $this->poolId() ? ' AND r.pool_id = ' . (int) $this->poolId() : '';
    }

    public function index(Request $req): Response
    {
        $status = $req->input('status', '');
        $q = trim((string) $req->input('q', ''));
        $dari = $req->input('dari', '');
        $sampai = $req->input('sampai', '');
        $page = max(1, (int) $req->input('page', 1));
        $per = 15;

        $where = 'WHERE 1=1' . $this->scope();
        $params = [];
        if ($status !== '' && in_array($status, ['rencana','berjalan','selesai','batal'], true)) {
            $where .= ' AND r.status = ?'; $params[] = $status;
        }
        if ($q !== '') {
            $where .= ' AND (r.kode LIKE ? OR b.nopol LIKE ?)';
            $params[] = "%$q%"; $params[] = "%$q%";
        }
        if ($dari !== '') { $where .= ' AND r.tanggal >= ?'; $params[] = $dari; }
        if ($sampai !== '') { $where .= ' AND r.tanggal <= ?'; $params[] = $sampai; }

        $total = (int) $this->db()->scalar("SELECT COUNT(*) FROM rit r JOIN bus b ON b.id=r.bus_id $where", $params);
        $offset = ($page - 1) * $per;
        $rits = $this->db()->all("
            SELECT r.*, b.nopol, ru.asal, ru.tujuan,
                (SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE rit_id=r.id AND status='disetujui') AS biaya,
                (SELECT COALESCE(SUM(nominal),0) FROM pendapatan WHERE rit_id=r.id) AS pendapatan
            FROM rit r JOIN bus b ON b.id=r.bus_id JOIN rute ru ON ru.id=r.rute_id
            $where ORDER BY r.tanggal DESC, r.id DESC LIMIT $per OFFSET $offset", $params);

        return $this->view('rit/index', [
            'title' => 'Rit', 'rits' => $rits, 'total' => $total, 'page' => $page, 'per' => $per,
            'status' => $status, 'q' => $q, 'dari' => $dari, 'sampai' => $sampai,
        ]);
    }

    public function create(Request $req): Response
    {
        return $this->view('rit/form', [
            'title' => 'Buat Rit', 'rit' => null,
            'buses' => $this->availableBuses(),
            'rute' => $this->db()->all('SELECT * FROM rute WHERE aktif=1 ORDER BY asal'),
            'kru' => $this->db()->all('SELECT * FROM kru WHERE aktif=1 ORDER BY nama'),
        ]);
    }

    /** Bus aktif & tidak sedang punya rit aktif. */
    private function availableBuses(?int $exceptRit = null): array
    {
        $scope = $this->poolId() ? ' AND pool_id = ' . (int) $this->poolId() : '';
        return $this->db()->all("
            SELECT * FROM bus WHERE status = 'aktif' $scope
            AND id NOT IN (SELECT bus_id FROM rit WHERE status IN ('rencana','berjalan')" .
            ($exceptRit ? " AND id <> " . (int) $exceptRit : '') . ")
            ORDER BY nopol");
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'bus_id' => 'required|int', 'rute_id' => 'required|int', 'tanggal' => 'required|date',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();

        // Validasi: bus aktif & tidak sedang jalan
        $bus = $this->db()->first("SELECT * FROM bus WHERE id = ? AND status = 'aktif'", [$d['bus_id']]);
        if (!$bus) {
            return $this->backWithErrors($req, ['Bus tidak ditemukan atau tidak berstatus aktif.']);
        }
        $busSibuk = (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM rit WHERE bus_id = ? AND status IN ('rencana','berjalan')", [$d['bus_id']]);
        if ($busSibuk > 0) {
            return $this->backWithErrors($req, ['Bus ini sudah punya rit aktif. Satu bus tidak boleh dua rit aktif sekaligus.']);
        }

        // Auto-hitung estimasi uang jalan dari standar biaya rute (kelas cocok / umum)
        $estimasi = (float) $this->db()->scalar("
            SELECT COALESCE(SUM(nominal_standar),0) FROM standar_biaya_rute
            WHERE rute_id = ? AND berlaku_sampai IS NULL
              AND (kelas_bus IS NULL OR kelas_bus = ?)", [$d['rute_id'], $bus['kelas']]);

        $kode = 'RIT-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $kruIds = array_filter(array_map('intval', (array) $req->input('kru_ids', [])));

        try {
            $ritId = $this->db()->transaction(function ($db) use ($d, $kode, $estimasi, $kruIds, $bus, $req) {
                $id = $db->insert('rit', [
                    'pool_id' => $bus['pool_id'], 'kode' => $kode, 'bus_id' => $d['bus_id'],
                    'rute_id' => $d['rute_id'], 'tanggal' => $d['tanggal'], 'status' => 'rencana',
                    'estimasi_uang_jalan' => $estimasi, 'dibuat_oleh' => $this->userId(),
                    'catatan' => Validator::clean($req->input('catatan')),
                ]);
                foreach ($kruIds as $kid) {
                    $pos = $db->scalar('SELECT posisi FROM kru WHERE id = ?', [$kid]) ?: 'sopir';
                    $db->insert('rit_kru', ['rit_id' => $id, 'kru_id' => $kid, 'peran' => $pos]);
                }
                return $id;
            });
        } catch (\PDOException $e) {
            // Kena unique index idx_bus_rit_aktif (race condition)
            return $this->backWithErrors($req, ['Bus sudah dipakai rit aktif lain (deteksi di database).']);
        }

        Audit::log('buat_rit', 'rit', $ritId);
        Session::flash('success', "Rit $kode dibuat. Estimasi uang jalan: " . rupiah($estimasi));
        return $this->redirect('/rit/' . $ritId);
    }

    public function show(Request $req): Response
    {
        $id = (int) $req->param('id');
        $rit = $this->db()->first("
            SELECT r.*, b.nopol, b.kelas, ru.asal, ru.tujuan, ru.jarak_km
            FROM rit r JOIN bus b ON b.id=r.bus_id JOIN rute ru ON ru.id=r.rute_id
            WHERE r.id = ?", [$id]);
        if (!$rit) { return $this->redirect('/rit'); }

        $kru = $this->db()->all('SELECT k.nama, rk.peran FROM rit_kru rk JOIN kru k ON k.id=rk.kru_id WHERE rk.rit_id = ?', [$id]);
        $pengeluaran = $this->db()->all("
            SELECT e.*, k.nama AS kategori, k.wajib_bukti, v.nama AS vendor
            FROM pengeluaran e JOIN kategori_biaya k ON k.id=e.kategori_id
            LEFT JOIN vendor v ON v.id=e.vendor_id
            WHERE e.rit_id = ? ORDER BY e.dibuat_pada DESC", [$id]);
        $pendapatan = $this->db()->all('SELECT * FROM pendapatan WHERE rit_id = ? ORDER BY id', [$id]);
        $uangJalan = $this->db()->all('SELECT uj.*, k.nama AS kru FROM uang_jalan uj JOIN kru k ON k.id=uj.kru_id WHERE uj.rit_id = ?', [$id]);

        $totalBiaya = array_sum(array_map(fn($e) => $e['status']==='disetujui' ? (float)$e['nominal'] : 0, $pengeluaran));
        $totalPendapatan = array_sum(array_map(fn($p) => (float)$p['nominal'], $pendapatan));

        // untuk form input pengeluaran/pendapatan/uang jalan
        $kategori = $this->db()->all('SELECT * FROM kategori_biaya WHERE aktif=1 ORDER BY tipe, nama');
        $vendor = $this->db()->all('SELECT * FROM vendor WHERE aktif=1 ORDER BY nama');
        $kruList = $this->db()->all("SELECT k.* FROM kru k JOIN rit_kru rk ON rk.kru_id=k.id WHERE rk.rit_id = ?", [$id]);

        return $this->view('rit/show', [
            'title' => 'Rit ' . $rit['kode'], 'rit' => $rit, 'kru' => $kru,
            'pengeluaran' => $pengeluaran, 'pendapatan' => $pendapatan, 'uangJalan' => $uangJalan,
            'totalBiaya' => $totalBiaya, 'totalPendapatan' => $totalPendapatan,
            'kategori' => $kategori, 'vendor' => $vendor, 'kruList' => $kruList,
            'locked' => in_array($rit['status'], ['selesai','batal'], true),
        ]);
    }

    public function edit(Request $req): Response
    {
        $id = (int) $req->param('id');
        $rit = $this->db()->first('SELECT * FROM rit WHERE id = ?', [$id]);
        if (!$rit || $rit['status'] !== 'rencana') {
            Session::flash('error', 'Rit hanya bisa diubah sebelum berangkat.');
            return $this->redirect('/rit/' . $id);
        }
        return $this->view('rit/form', [
            'title' => 'Edit Rit', 'rit' => $rit,
            'buses' => $this->availableBuses($id),
            'rute' => $this->db()->all('SELECT * FROM rute WHERE aktif=1 ORDER BY asal'),
            'kru' => $this->db()->all('SELECT * FROM kru WHERE aktif=1 ORDER BY nama'),
            'kruTerpilih' => array_column($this->db()->all('SELECT kru_id FROM rit_kru WHERE rit_id=?', [$id]), 'kru_id'),
        ]);
    }

    public function update(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $rit = $this->db()->first('SELECT * FROM rit WHERE id = ?', [$id]);
        if (!$rit) { return $this->redirect('/rit'); }
        if ($rit['status'] !== 'rencana') {
            Session::flash('error', 'Rit hanya bisa diubah sebelum berangkat.');
            return $this->redirect('/rit/' . $id);
        }
        $this->db()->update('rit', [
            'rute_id' => (int) $req->input('rute_id', $rit['rute_id']),
            'tanggal' => $req->input('tanggal', $rit['tanggal']),
            'catatan' => Validator::clean($req->input('catatan')),
        ], ['id' => $id]);
        Session::flash('success', 'Rit diperbarui.');
        return $this->redirect('/rit/' . $id);
    }

    public function mulai(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $rit = $this->db()->first('SELECT * FROM rit WHERE id = ?', [$id]);
        if (!$rit || $rit['status'] !== 'rencana') {
            Session::flash('error', 'Rit tidak dalam status rencana.');
            return $this->redirect('/rit/' . $id);
        }
        $kmAwal = (int) $req->input('km_awal', 0);
        $this->db()->update('rit', ['status' => 'berjalan', 'km_awal' => $kmAwal], ['id' => $id]);
        Audit::log('mulai_rit', 'rit', $id, "km_awal=$kmAwal");
        Session::flash('success', 'Rit dimulai (berjalan).');
        return $this->redirect('/rit/' . $id);
    }

    public function tutup(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $rit = $this->db()->first('SELECT * FROM rit WHERE id = ?', [$id]);
        if (!$rit || $rit['status'] !== 'berjalan') {
            Session::flash('error', 'Hanya rit berjalan yang bisa ditutup.');
            return $this->redirect('/rit/' . $id);
        }
        // Syarat: uang jalan sudah direkonsiliasi (semua disetujui/lunas) & tidak ada pengeluaran menunggu
        $ujBelum = (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM uang_jalan WHERE rit_id = ? AND status NOT IN ('disetujui','lunas')", [$id]);
        $pengBelum = (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM pengeluaran WHERE rit_id = ? AND status = 'menunggu'", [$id]);
        if ($pengBelum > 0) {
            Session::flash('error', "Masih ada $pengBelum pengeluaran menunggu approval. Selesaikan dulu.");
            return $this->redirect('/rit/' . $id);
        }
        if ($ujBelum > 0) {
            Session::flash('error', 'Uang jalan belum direkonsiliasi. Rekonsiliasi dulu sebelum tutup rit.');
            return $this->redirect('/rit/' . $id);
        }

        $kmAkhir = $req->input('km_akhir') !== null ? (int) $req->input('km_akhir') : $rit['km_akhir'];
        $this->db()->transaction(function ($db) use ($id, $kmAkhir, $rit) {
            $db->update('rit', [
                'status' => 'selesai', 'km_akhir' => $kmAkhir,
                'ditutup_oleh' => $this->userId(), 'ditutup_pada' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);
            // Update odometer bus bila km_akhir lebih besar
            if ($kmAkhir && $kmAkhir > 0) {
                $db->run('UPDATE bus SET odometer = ? WHERE id = ? AND ? > odometer', [$kmAkhir, $rit['bus_id'], $kmAkhir]);
            }
        });
        Audit::log('tutup_rit', 'rit', $id, "km_akhir=$kmAkhir");
        Session::flash('success', 'Rit ditutup. Semua transaksi terkunci.');
        return $this->redirect('/rit/' . $id);
    }

    public function batal(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $rit = $this->db()->first('SELECT * FROM rit WHERE id = ?', [$id]);
        if (!$rit || $rit['status'] !== 'rencana') {
            Session::flash('error', 'Hanya rit rencana yang bisa dibatalkan.');
            return $this->redirect('/rit/' . $id);
        }
        $this->db()->update('rit', ['status' => 'batal'], ['id' => $id]);
        Audit::log('batal_rit', 'rit', $id);
        Session::flash('success', 'Rit dibatalkan.');
        return $this->redirect('/rit');
    }
}
