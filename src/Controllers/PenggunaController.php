<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Audit;
use App\Support\Validator;

/**
 * Manajemen pengguna (bagian 1): CRUD akun, nonaktifkan tanpa hapus histori.
 * Hanya owner.
 */
final class PenggunaController extends Controller
{
    public function index(Request $req): Response
    {
        $rows = $this->db()->all("
            SELECT p.*, k.nama AS kru_nama, pl.nama AS pool_nama
            FROM pengguna p LEFT JOIN kru k ON k.id=p.kru_id LEFT JOIN pool pl ON pl.id=p.pool_id
            ORDER BY p.aktif DESC, p.role, p.nama");
        return $this->view('pengguna/index', [
            'title' => 'Pengguna', 'rows' => $rows,
            'pool' => $this->db()->all('SELECT id, nama FROM pool ORDER BY nama'),
            'kru' => $this->db()->all('SELECT id, nama FROM kru WHERE aktif=1 ORDER BY nama'),
        ]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'nama' => 'required|string|max:80', 'email' => 'required|email',
            'role' => 'required|in:owner,manajer,keuangan,admin_pool,kru', 'password' => 'required|string|min:6',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        if ($this->db()->first('SELECT id FROM pengguna WHERE email = ?', [$d['email']])) {
            return $this->backWithErrors($req, ['Email sudah digunakan.']);
        }
        $id = $this->db()->insert('pengguna', [
            'pool_id' => $req->input('pool_id') ? (int) $req->input('pool_id') : null,
            'nama' => $d['nama'], 'email' => $d['email'], 'no_hp' => Validator::clean($req->input('no_hp')) ?: null,
            'password_hash' => password_hash($d['password'], PASSWORD_BCRYPT), 'role' => $d['role'],
            'kru_id' => $req->input('kru_id') ? (int) $req->input('kru_id') : null, 'aktif' => 1,
        ]);
        Audit::log('buat_pengguna', 'pengguna', $id);
        Session::flash('success', 'Pengguna dibuat.');
        return $this->redirect('/pengguna');
    }

    public function update(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $data = [
            'nama' => Validator::clean($req->input('nama')),
            'role' => in_array($req->input('role'), ['owner','manajer','keuangan','admin_pool','kru'], true) ? $req->input('role') : 'kru',
            'pool_id' => $req->input('pool_id') ? (int) $req->input('pool_id') : null,
        ];
        if ($req->input('password')) {
            $data['password_hash'] = password_hash((string) $req->input('password'), PASSWORD_BCRYPT);
        }
        $this->db()->update('pengguna', $data, ['id' => $id]);
        Audit::log('edit_pengguna', 'pengguna', $id);
        Session::flash('success', 'Pengguna diperbarui.');
        return $this->redirect('/pengguna');
    }

    public function toggle(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        if ($id === $this->userId()) {
            Session::flash('error', 'Tidak bisa menonaktifkan akun sendiri.');
            return $this->redirect('/pengguna');
        }
        $u = $this->db()->first('SELECT aktif FROM pengguna WHERE id = ?', [$id]);
        if ($u) {
            // Nonaktifkan tanpa hapus (histori tetap ada)
            $this->db()->update('pengguna', ['aktif' => $u['aktif'] ? 0 : 1], ['id' => $id]);
            Audit::log($u['aktif'] ? 'nonaktif_pengguna' : 'aktif_pengguna', 'pengguna', $id);
            Session::flash('success', 'Status pengguna diubah.');
        }
        return $this->redirect('/pengguna');
    }
}
