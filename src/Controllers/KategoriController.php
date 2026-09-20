<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/**
 * Master Kategori Biaya (bagian 2): hierarki induk-anak, flag wajib bukti foto.
 */
final class KategoriController extends Controller
{
    public function index(Request $req): Response
    {
        $rows = $this->db()->all('SELECT * FROM kategori_biaya ORDER BY COALESCE(induk_id, id), induk_id IS NOT NULL, nama');
        // Susun hierarki
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[$r['induk_id'] ?? 0][] = $r;
        }
        $induk = $this->db()->all('SELECT id, nama FROM kategori_biaya WHERE induk_id IS NULL ORDER BY nama');
        return $this->view('kategori/index', [
            'title' => 'Kategori Biaya', 'byParent' => $byParent, 'induk' => $induk,
        ]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'nama' => 'required|string|max:80', 'tipe' => 'in:variabel,tetap,bbm',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->insert('kategori_biaya', [
            'induk_id' => $req->input('induk_id') ? (int) $req->input('induk_id') : null,
            'nama' => $d['nama'], 'tipe' => $d['tipe'] ?? 'variabel',
            'wajib_bukti' => $req->input('wajib_bukti') ? 1 : 0, 'aktif' => 1,
        ]);
        Session::flash('success', 'Kategori ditambahkan.');
        return $this->redirect('/kategori');
    }

    public function update(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $this->db()->update('kategori_biaya', [
            'nama' => Validator::clean($req->input('nama')),
            'tipe' => in_array($req->input('tipe'), ['variabel','tetap','bbm'], true) ? $req->input('tipe') : 'variabel',
            'wajib_bukti' => $req->input('wajib_bukti') ? 1 : 0,
            'aktif' => $req->input('aktif') !== null ? (int) $req->input('aktif') : 1,
        ], ['id' => $id]);
        Session::flash('success', 'Kategori diperbarui.');
        return $this->redirect('/kategori');
    }
}
