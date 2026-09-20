<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/**
 * Master Kru (bagian 2): CRUD, SIM + tanggal berlaku, peringatan SIM mendekati kedaluwarsa.
 */
final class KruController extends Controller
{
    public function index(Request $req): Response
    {
        $where = $this->poolId() ? 'WHERE pool_id = ' . (int) $this->poolId() : '';
        $kru = $this->db()->all("SELECT * FROM kru $where ORDER BY aktif DESC, nama");
        // Tandai SIM mendekati kedaluwarsa (<= 60 hari)
        foreach ($kru as &$k) {
            $k['sim_warning'] = $k['sim_berlaku_sampai']
                && strtotime($k['sim_berlaku_sampai']) <= strtotime('+60 day');
            $k['sim_expired'] = $k['sim_berlaku_sampai']
                && strtotime($k['sim_berlaku_sampai']) < time();
        }
        unset($k);
        return $this->view('kru/index', ['title' => 'Kru', 'kru' => $kru]);
    }

    public function create(Request $req): Response
    {
        return $this->view('kru/form', ['title' => 'Tambah Kru', 'kru' => null]);
    }

    public function edit(Request $req): Response
    {
        $kru = $this->db()->first('SELECT * FROM kru WHERE id = ?', [$req->param('id')]);
        if (!$kru) { return $this->redirect('/kru'); }
        return $this->view('kru/form', ['title' => 'Edit Kru', 'kru' => $kru]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'nama' => 'required|string|max:80', 'no_hp' => 'string|max:20',
            'posisi' => 'in:sopir,sopir_2,kernet,kondektur', 'no_sim' => 'string|max:30',
            'sim_berlaku_sampai' => 'date',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->insert('kru', [
            'pool_id' => $this->poolId(), 'nama' => $d['nama'], 'no_hp' => $d['no_hp'],
            'posisi' => $d['posisi'] ?? 'sopir', 'no_sim' => $d['no_sim'],
            'sim_berlaku_sampai' => $d['sim_berlaku_sampai'], 'aktif' => 1,
        ]);
        Session::flash('success', 'Kru ditambahkan.');
        return $this->redirect('/kru');
    }

    public function update(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $v = Validator::make($req->body);
        if (!$v->validate([
            'nama' => 'required|string|max:80', 'no_hp' => 'string|max:20',
            'posisi' => 'in:sopir,sopir_2,kernet,kondektur', 'no_sim' => 'string|max:30',
            'sim_berlaku_sampai' => 'date', 'aktif' => 'in:0,1',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->update('kru', [
            'nama' => $d['nama'], 'no_hp' => $d['no_hp'], 'posisi' => $d['posisi'] ?? 'sopir',
            'no_sim' => $d['no_sim'], 'sim_berlaku_sampai' => $d['sim_berlaku_sampai'],
            'aktif' => (int) ($req->input('aktif', 1)),
        ], ['id' => $id]);
        Session::flash('success', 'Kru diperbarui.');
        return $this->redirect('/kru');
    }
}
