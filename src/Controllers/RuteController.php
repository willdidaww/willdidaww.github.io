<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/**
 * Master Rute (bagian 2): CRUD, nonaktifkan tanpa hapus (histori tetap ada).
 */
final class RuteController extends Controller
{
    public function index(Request $req): Response
    {
        $where = $this->poolId() ? 'WHERE pool_id = ' . (int) $this->poolId() : '';
        $rute = $this->db()->all("SELECT * FROM rute $where ORDER BY aktif DESC, asal, tujuan");
        return $this->view('rute/index', ['title' => 'Rute', 'rute' => $rute]);
    }

    public function create(Request $req): Response
    {
        return $this->view('rute/form', ['title' => 'Tambah Rute', 'rute' => null]);
    }

    public function edit(Request $req): Response
    {
        $rute = $this->db()->first('SELECT * FROM rute WHERE id = ?', [$req->param('id')]);
        if (!$rute) { return $this->redirect('/rute'); }
        return $this->view('rute/form', ['title' => 'Edit Rute', 'rute' => $rute]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'asal' => 'required|string|max:60', 'tujuan' => 'required|string|max:60',
            'kode' => 'string|max:20', 'jarak_km' => 'numeric|min:0', 'estimasi_jam' => 'numeric|min:0',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->insert('rute', [
            'pool_id' => $this->poolId(), 'kode' => $d['kode'], 'asal' => $d['asal'], 'tujuan' => $d['tujuan'],
            'jarak_km' => $d['jarak_km'], 'estimasi_jam' => $d['estimasi_jam'], 'aktif' => 1,
        ]);
        Session::flash('success', 'Rute ditambahkan.');
        return $this->redirect('/rute');
    }

    public function update(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $v = Validator::make($req->body);
        if (!$v->validate([
            'asal' => 'required|string|max:60', 'tujuan' => 'required|string|max:60',
            'kode' => 'string|max:20', 'jarak_km' => 'numeric|min:0', 'estimasi_jam' => 'numeric|min:0',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->update('rute', [
            'kode' => $d['kode'], 'asal' => $d['asal'], 'tujuan' => $d['tujuan'],
            'jarak_km' => $d['jarak_km'], 'estimasi_jam' => $d['estimasi_jam'],
        ], ['id' => $id]);
        Session::flash('success', 'Rute diperbarui.');
        return $this->redirect('/rute');
    }

    public function toggle(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $rute = $this->db()->first('SELECT aktif FROM rute WHERE id = ?', [$id]);
        if ($rute) {
            $this->db()->update('rute', ['aktif' => $rute['aktif'] ? 0 : 1], ['id' => $id]);
            Session::flash('success', 'Status rute diubah.');
        }
        return $this->redirect('/rute');
    }
}
