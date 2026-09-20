<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/** Master Vendor (bagian 2): SPBU/bengkel/sparepart/agen. */
final class VendorController extends Controller
{
    public function index(Request $req): Response
    {
        $where = $this->poolId() ? 'WHERE pool_id = ' . (int) $this->poolId() : '';
        $vendor = $this->db()->all("SELECT * FROM vendor $where ORDER BY jenis, nama");
        return $this->view('vendor/index', ['title' => 'Vendor', 'vendor' => $vendor]);
    }

    public function create(Request $req): Response
    {
        return $this->view('vendor/form', ['title' => 'Tambah Vendor', 'vendor' => null]);
    }

    public function edit(Request $req): Response
    {
        $vendor = $this->db()->first('SELECT * FROM vendor WHERE id = ?', [$req->param('id')]);
        if (!$vendor) { return $this->redirect('/vendor'); }
        return $this->view('vendor/form', ['title' => 'Edit Vendor', 'vendor' => $vendor]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'nama' => 'required|string|max:80', 'jenis' => 'in:spbu,bengkel,sparepart,agen,lain',
            'telepon' => 'string|max:20', 'alamat' => 'string|max:200',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->insert('vendor', [
            'pool_id' => $this->poolId(), 'nama' => $d['nama'], 'jenis' => $d['jenis'] ?? 'lain',
            'telepon' => $d['telepon'], 'alamat' => $d['alamat'], 'aktif' => 1,
        ]);
        Session::flash('success', 'Vendor ditambahkan.');
        return $this->redirect('/vendor');
    }

    public function update(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $v = Validator::make($req->body);
        if (!$v->validate([
            'nama' => 'required|string|max:80', 'jenis' => 'in:spbu,bengkel,sparepart,agen,lain',
            'telepon' => 'string|max:20', 'alamat' => 'string|max:200', 'aktif' => 'in:0,1',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->update('vendor', [
            'nama' => $d['nama'], 'jenis' => $d['jenis'] ?? 'lain', 'telepon' => $d['telepon'],
            'alamat' => $d['alamat'], 'aktif' => (int) $req->input('aktif', 1),
        ], ['id' => $id]);
        Session::flash('success', 'Vendor diperbarui.');
        return $this->redirect('/vendor');
    }
}
