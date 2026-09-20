<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Audit;
use App\Support\Validator;

/**
 * Master Bus (bagian 2): CRUD, nopol unik, tidak bisa dihapus jika punya rit aktif.
 */
final class BusController extends Controller
{
    public function index(Request $req): Response
    {
        $q = trim((string) $req->input('q', ''));
        $where = $this->poolId() ? 'WHERE pool_id = ' . (int) $this->poolId() : 'WHERE 1=1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (nopol LIKE ? OR karoseri LIKE ?)';
            $params[] = "%$q%"; $params[] = "%$q%";
        }
        $buses = $this->db()->all("SELECT * FROM bus $where ORDER BY nopol", $params);
        return $this->view('bus/index', ['title' => 'Bus', 'buses' => $buses, 'q' => $q]);
    }

    public function create(Request $req): Response
    {
        return $this->view('bus/form', ['title' => 'Tambah Bus', 'bus' => null]);
    }

    public function edit(Request $req): Response
    {
        $bus = $this->db()->first('SELECT * FROM bus WHERE id = ?', [$req->param('id')]);
        if (!$bus) { return $this->redirect('/bus'); }
        return $this->view('bus/form', ['title' => 'Edit Bus', 'bus' => $bus]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'nopol' => 'required|string|max:20', 'kelas' => 'string|max:30',
            'karoseri' => 'string|max:50', 'tahun' => 'int|min:1980|max:2100',
            'odometer' => 'int|min:0', 'status' => 'in:aktif,nonaktif,perawatan',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        // nopol unik
        if ($this->db()->first('SELECT id FROM bus WHERE nopol = ?', [$d['nopol']])) {
            return $this->backWithErrors($req, ['Nopol sudah terdaftar.']);
        }
        $id = $this->db()->insert('bus', [
            'pool_id' => $this->poolId(), 'nopol' => $d['nopol'], 'kelas' => $d['kelas'],
            'karoseri' => $d['karoseri'], 'tahun' => $d['tahun'], 'odometer' => $d['odometer'] ?? 0,
            'status' => $d['status'] ?? 'aktif',
        ]);
        Session::flash('success', 'Bus berhasil ditambahkan.');
        return $this->redirect('/bus');
    }

    public function update(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $bus = $this->db()->first('SELECT * FROM bus WHERE id = ?', [$id]);
        if (!$bus) { return $this->redirect('/bus'); }

        $v = Validator::make($req->body);
        if (!$v->validate([
            'nopol' => 'required|string|max:20', 'kelas' => 'string|max:30',
            'karoseri' => 'string|max:50', 'tahun' => 'int', 'odometer' => 'int|min:0',
            'status' => 'in:aktif,nonaktif,perawatan',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $dup = $this->db()->first('SELECT id FROM bus WHERE nopol = ? AND id <> ?', [$d['nopol'], $id]);
        if ($dup) { return $this->backWithErrors($req, ['Nopol sudah dipakai bus lain.']); }

        $this->db()->update('bus', [
            'nopol' => $d['nopol'], 'kelas' => $d['kelas'], 'karoseri' => $d['karoseri'],
            'tahun' => $d['tahun'], 'odometer' => $d['odometer'] ?? $bus['odometer'], 'status' => $d['status'] ?? $bus['status'],
        ], ['id' => $id]);
        Session::flash('success', 'Bus diperbarui.');
        return $this->redirect('/bus');
    }

    public function destroy(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $aktif = (int) $this->db()->scalar("SELECT COUNT(*) FROM rit WHERE bus_id = ? AND status IN ('rencana','berjalan')", [$id]);
        if ($aktif > 0) {
            Session::flash('error', 'Bus tidak bisa dihapus karena masih punya rit aktif. Nonaktifkan saja.');
            return $this->redirect('/bus');
        }
        $punyaRit = (int) $this->db()->scalar('SELECT COUNT(*) FROM rit WHERE bus_id = ?', [$id]);
        if ($punyaRit > 0) {
            // Punya histori -> nonaktifkan, jangan hapus
            $this->db()->update('bus', ['status' => 'nonaktif'], ['id' => $id]);
            Audit::log('nonaktif_bus', 'bus', $id, 'punya histori rit');
            Session::flash('info', 'Bus dinonaktifkan (punya histori rit, tidak dihapus).');
            return $this->redirect('/bus');
        }
        $this->db()->delete('bus', ['id' => $id]);
        Audit::log('hapus_master', 'bus', $id);
        Session::flash('success', 'Bus dihapus.');
        return $this->redirect('/bus');
    }
}
