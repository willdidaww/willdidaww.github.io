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
 * Persetujuan (bagian 10): halaman approval lintas rit, filter kategori/nominal/anomali,
 * approve/reject per item & batch, alasan wajib saat reject.
 * Keputusan disetujui oleh admin_pool/keuangan/manajer/owner (kru tidak).
 */
final class ApprovalController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->poolId() ? ' AND e.pool_id = ' . (int) $this->poolId() : '';
        $kategoriId = $req->input('kategori_id', '');
        $minNominal = (float) $req->input('min_nominal', 0);
        $onlyAnomali = $req->input('anomali', '') === '1';

        $where = "WHERE e.status = 'menunggu'" . $scope;
        $params = [];
        if ($kategoriId !== '') { $where .= ' AND e.kategori_id = ?'; $params[] = (int) $kategoriId; }
        if ($minNominal > 0) { $where .= ' AND e.nominal >= ?'; $params[] = $minNominal; }
        if ($onlyAnomali) { $where .= ' AND e.flag_anomali = 1'; }

        $rows = $this->db()->all("
            SELECT e.*, k.nama AS kategori, r.kode AS rit_kode, b.nopol,
                   s.nominal_standar, s.toleransi_pct
            FROM pengeluaran e
            JOIN kategori_biaya k ON k.id=e.kategori_id
            LEFT JOIN rit r ON r.id=e.rit_id
            LEFT JOIN bus b ON b.id=r.bus_id
            LEFT JOIN standar_biaya_rute s ON s.rute_id=r.rute_id AND s.kategori_id=e.kategori_id AND s.berlaku_sampai IS NULL
            $where ORDER BY e.flag_anomali DESC, e.nominal DESC, e.dibuat_pada ASC", $params);

        return $this->view('approval/index', [
            'title' => 'Persetujuan', 'rows' => $rows,
            'kategori' => $this->db()->all('SELECT id, nama FROM kategori_biaya WHERE aktif=1 ORDER BY nama'),
            'kategoriId' => $kategoriId, 'minNominal' => $minNominal, 'onlyAnomali' => $onlyAnomali,
        ]);
    }

    public function approve(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $this->setStatus((int) $req->param('id'), 'disetujui');
        Session::flash('success', 'Pengeluaran disetujui.');
        return $this->back($req);
    }

    public function reject(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $alasan = Validator::clean($req->input('alasan', ''));
        if ($alasan === '') {
            Session::flash('error', 'Alasan wajib diisi saat menolak.');
            return $this->back($req);
        }
        $this->setStatus((int) $req->param('id'), 'ditolak', $alasan);
        Session::flash('success', 'Pengeluaran ditolak.');
        return $this->back($req);
    }

    public function batch(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $ids = array_filter(array_map('intval', (array) $req->input('ids', [])));
        $aksi = $req->input('aksi');
        if (!$ids) {
            Session::flash('error', 'Pilih minimal satu item.');
            return $this->back($req);
        }
        if ($aksi === 'reject') {
            $alasan = Validator::clean($req->input('alasan', ''));
            if ($alasan === '') {
                Session::flash('error', 'Alasan wajib diisi saat menolak batch.');
                return $this->back($req);
            }
            foreach ($ids as $id) { $this->setStatus($id, 'ditolak', $alasan); }
            Session::flash('success', count($ids) . ' item ditolak.');
        } else {
            foreach ($ids as $id) { $this->setStatus($id, 'disetujui'); }
            Session::flash('success', count($ids) . ' item disetujui.');
        }
        return $this->back($req);
    }

    private function setStatus(int $id, string $status, ?string $alasan = null): void
    {
        $e = $this->db()->first('SELECT * FROM pengeluaran WHERE id = ? AND status = ?', [$id, 'menunggu']);
        if (!$e) { return; }
        $this->db()->update('pengeluaran', [
            'status' => $status, 'alasan_reject' => $alasan,
            'disetujui_oleh' => $this->userId(), 'disetujui_pada' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
        Audit::log($status === 'disetujui' ? 'approve' : 'reject', 'pengeluaran', $id, $alasan);

        // Notifikasi ke pembuat (bila punya akun)
        if ($e['dibuat_oleh']) {
            Notify::toUser((int) $e['dibuat_oleh'], 'approval',
                'Pengeluaran ' . $status, rupiah($e['nominal']) . ($alasan ? ' — ' . $alasan : ''),
                $e['rit_id'] ? '/rit/' . $e['rit_id'] : '/pengeluaran');
        }
    }
}
