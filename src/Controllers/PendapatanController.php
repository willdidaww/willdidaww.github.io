<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/**
 * Pendapatan (bagian 6): per rit (tiket/kargo/paket/carter/lain) + jumlah penumpang.
 */
final class PendapatanController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->poolId() ? ' AND p.pool_id = ' . (int) $this->poolId() : '';
        $rows = $this->db()->all("
            SELECT p.*, r.kode AS rit_kode, ru.asal, ru.tujuan
            FROM pendapatan p JOIN rit r ON r.id=p.rit_id JOIN rute ru ON ru.id=r.rute_id
            WHERE 1=1 $scope ORDER BY p.dibuat_pada DESC LIMIT 100");
        $rit = $this->db()->all("SELECT r.id, r.kode FROM rit r WHERE r.status IN ('berjalan','selesai')" .
            ($this->poolId() ? ' AND r.pool_id='.(int)$this->poolId() : '') . ' ORDER BY r.tanggal DESC LIMIT 100');
        return $this->view('pendapatan/index', ['title' => 'Pendapatan', 'rows' => $rows, 'rit' => $rit]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'rit_id' => 'required|int', 'jenis' => 'in:tiket,kargo,paket,carter,lain',
            'nominal' => 'required|numeric|min:0',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $rit = $this->db()->first('SELECT pool_id, status FROM rit WHERE id = ?', [$d['rit_id']]);
        if (!$rit) { return $this->backWithErrors($req, ['Rit tidak ditemukan.']); }
        if ($rit['status'] === 'batal') { return $this->backWithErrors($req, ['Rit batal — tidak bisa input pendapatan.']); }

        $this->db()->insert('pendapatan', [
            'pool_id' => $rit['pool_id'], 'rit_id' => $d['rit_id'], 'jenis' => $d['jenis'] ?? 'tiket',
            'nominal' => $d['nominal'], 'jumlah_penumpang' => $req->input('jumlah_penumpang') ? (int) $req->input('jumlah_penumpang') : null,
            'keterangan' => Validator::clean($req->input('keterangan')), 'dibuat_oleh' => $this->userId(),
        ]);
        Session::flash('success', 'Pendapatan dicatat.');
        return $this->back($req);
    }

    public function destroy(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $p = $this->db()->first('SELECT p.*, r.status AS rit_status FROM pendapatan p JOIN rit r ON r.id=p.rit_id WHERE p.id=?', [$id]);
        if ($p && in_array($p['rit_status'], ['selesai','batal'], true)) {
            Session::flash('error', 'Rit sudah ditutup — pendapatan terkunci.');
            return $this->back($req);
        }
        $this->db()->delete('pendapatan', ['id' => $id]);
        Session::flash('success', 'Pendapatan dihapus.');
        return $this->back($req);
    }
}
