<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Notification center in-app (bagian 11).
 */
final class NotifikasiController extends Controller
{
    public function index(Request $req): Response
    {
        $uid = $this->userId();
        $rows = $this->db()->all('SELECT * FROM notifikasi WHERE pengguna_id = ? ORDER BY dibuat_pada DESC LIMIT 100', [$uid]);
        return $this->view('notifikasi/index', ['title' => 'Notifikasi', 'rows' => $rows]);
    }

    public function baca(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $this->db()->update('notifikasi', ['dibaca' => 1], ['id' => (int) $req->param('id'), 'pengguna_id' => $this->userId()]);
        return $this->back($req);
    }

    public function bacaSemua(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $this->db()->run('UPDATE notifikasi SET dibaca = 1 WHERE pengguna_id = ?', [$this->userId()]);
        return $this->back($req);
    }
}
