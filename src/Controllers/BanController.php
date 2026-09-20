<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/**
 * Manajemen ban (bagian 7): kode seri, pasang/lepas per posisi,
 * cegah dua ban aktif di posisi sama (UI + constraint DB).
 */
final class BanController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->poolId() ? ' AND b.pool_id = ' . (int) $this->poolId() : '';
        $ban = $this->db()->all("
            SELECT b.*,
              (SELECT bp.bus_id FROM ban_pasang bp WHERE bp.ban_id=b.id AND bp.tanggal_lepas IS NULL) AS terpasang_bus,
              (SELECT bp.posisi FROM ban_pasang bp WHERE bp.ban_id=b.id AND bp.tanggal_lepas IS NULL) AS posisi
            FROM ban b WHERE 1=1 $scope ORDER BY b.kode_seri");
        foreach ($ban as &$b) {
            $b['nopol'] = $b['terpasang_bus'] ? $this->db()->scalar('SELECT nopol FROM bus WHERE id=?', [$b['terpasang_bus']]) : null;
        }
        unset($b);
        $pemasangan = $this->db()->all("
            SELECT bp.*, ban.kode_seri, bus.nopol FROM ban_pasang bp
            JOIN ban ON ban.id=bp.ban_id JOIN bus ON bus.id=bp.bus_id
            ORDER BY bp.tanggal_lepas IS NOT NULL, bp.tanggal_pasang DESC LIMIT 50");
        return $this->view('ban/index', [
            'title' => 'Manajemen Ban', 'ban' => $ban, 'pemasangan' => $pemasangan,
            'buses' => $this->db()->all('SELECT id, nopol FROM bus WHERE status<>\'nonaktif\' ORDER BY nopol'),
            'banBebas' => array_filter($ban, fn($b) => !$b['terpasang_bus']),
        ]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $kode = Validator::clean($req->input('kode_seri'));
        if ($kode === '') { return $this->backWithErrors($req, ['Kode seri wajib diisi.']); }
        if ($this->db()->first('SELECT id FROM ban WHERE kode_seri = ?', [$kode])) {
            return $this->backWithErrors($req, ['Kode seri sudah ada.']);
        }
        $this->db()->insert('ban', [
            'pool_id' => $this->poolId(), 'kode_seri' => $kode, 'merk' => Validator::clean($req->input('merk')),
        ]);
        Session::flash('success', 'Ban ditambahkan.');
        return $this->redirect('/ban');
    }

    public function pasang(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $banId = (int) $req->input('ban_id');
        $busId = (int) $req->input('bus_id');
        $posisi = strtoupper(Validator::clean($req->input('posisi')));
        if (!$banId || !$busId || $posisi === '') {
            return $this->backWithErrors($req, ['Ban, bus, dan posisi wajib diisi.']);
        }
        // Cek UI: posisi sudah terisi?
        $terisi = $this->db()->first('SELECT id FROM ban_pasang WHERE bus_id=? AND posisi=? AND tanggal_lepas IS NULL', [$busId, $posisi]);
        if ($terisi) {
            return $this->backWithErrors($req, ["Posisi $posisi pada bus ini sudah ada ban aktif. Lepas dulu."]);
        }
        // Cek: ban sudah terpasang di tempat lain?
        $aktif = $this->db()->first('SELECT id FROM ban_pasang WHERE ban_id=? AND tanggal_lepas IS NULL', [$banId]);
        if ($aktif) {
            return $this->backWithErrors($req, ['Ban ini masih terpasang di posisi lain. Lepas dulu.']);
        }
        try {
            $this->db()->insert('ban_pasang', [
                'ban_id' => $banId, 'bus_id' => $busId, 'posisi' => $posisi,
                'odometer_pasang' => $req->input('odometer_pasang') ? (int) $req->input('odometer_pasang') : null,
                'tanggal_pasang' => $req->input('tanggal_pasang') ?: date('Y-m-d'),
            ]);
        } catch (\PDOException $e) {
            return $this->backWithErrors($req, ['Gagal — bentrok constraint database (posisi/ban sudah aktif).']);
        }
        Session::flash('success', 'Ban dipasang.');
        return $this->redirect('/ban');
    }

    public function lepas(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $id = (int) $req->param('id');
        $this->db()->update('ban_pasang', [
            'tanggal_lepas' => $req->input('tanggal_lepas') ?: date('Y-m-d'),
            'odometer_lepas' => $req->input('odometer_lepas') ? (int) $req->input('odometer_lepas') : null,
        ], ['id' => $id]);
        Session::flash('success', 'Ban dilepas.');
        return $this->redirect('/ban');
    }
}
