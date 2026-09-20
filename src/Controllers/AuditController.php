<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Audit log viewer (bagian 14). Hanya owner.
 */
final class AuditController extends Controller
{
    public function index(Request $req): Response
    {
        $page = max(1, (int) $req->input('page', 1));
        $per = 40; $offset = ($page - 1) * $per;
        $total = (int) $this->db()->scalar('SELECT COUNT(*) FROM audit_log');
        $rows = $this->db()->all("
            SELECT a.*, p.nama AS pengguna FROM audit_log a LEFT JOIN pengguna p ON p.id=a.pengguna_id
            ORDER BY a.waktu DESC LIMIT $per OFFSET $offset");
        return $this->view('audit/index', ['title' => 'Audit Log', 'rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per]);
    }
}
