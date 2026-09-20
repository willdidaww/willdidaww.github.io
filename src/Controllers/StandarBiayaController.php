<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/**
 * Standar biaya per rute (bagian 2): nominal + toleransi per kategori per rute per kelas,
 * dengan riwayat perubahan (berlaku_dari / berlaku_sampai).
 */
final class StandarBiayaController extends Controller
{
    public function index(Request $req): Response
    {
        $rows = $this->db()->all("
            SELECT s.*, r.asal, r.tujuan, k.nama AS kategori
            FROM standar_biaya_rute s
            JOIN rute r ON r.id = s.rute_id
            JOIN kategori_biaya k ON k.id = s.kategori_id
            ORDER BY r.asal, r.tujuan, s.berlaku_sampai IS NOT NULL, s.berlaku_dari DESC");
        $rute = $this->db()->all('SELECT id, asal, tujuan FROM rute WHERE aktif = 1 ORDER BY asal');
        $kategori = $this->db()->all('SELECT id, nama FROM kategori_biaya WHERE aktif = 1 ORDER BY nama');
        return $this->view('standar/index', [
            'title' => 'Standar Biaya per Rute', 'rows' => $rows, 'rute' => $rute, 'kategori' => $kategori,
        ]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'rute_id' => 'required|int', 'kategori_id' => 'required|int',
            'nominal_standar' => 'required|numeric|gt:0', 'toleransi_pct' => 'numeric|min:0|max:100',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $kelas = $req->input('kelas_bus') ?: null;

        $this->db()->transaction(function ($db) use ($d, $kelas) {
            // Tutup standar lama yang masih berlaku (riwayat) untuk kombinasi sama
            $db->run("
                UPDATE standar_biaya_rute SET berlaku_sampai = date('now','-1 day')
                WHERE rute_id = ? AND kategori_id = ? AND berlaku_sampai IS NULL
                  AND (kelas_bus IS ? OR kelas_bus = ?)",
                [$d['rute_id'], $d['kategori_id'], $kelas, $kelas]);
            $db->insert('standar_biaya_rute', [
                'rute_id' => $d['rute_id'], 'kategori_id' => $d['kategori_id'], 'kelas_bus' => $kelas,
                'nominal_standar' => $d['nominal_standar'], 'toleransi_pct' => $d['toleransi_pct'] ?? 10,
                'berlaku_dari' => date('Y-m-d'),
            ]);
        });
        Session::flash('success', 'Standar biaya disimpan (versi lama diarsipkan).');
        return $this->redirect('/standar-biaya');
    }
}
