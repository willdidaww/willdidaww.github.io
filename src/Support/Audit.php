<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\App;
use App\Core\Session;

/**
 * Pencatat audit log untuk aksi sensitif (bagian 14).
 */
final class Audit
{
    public static function log(string $aksi, ?string $entitas = null, ?int $entitasId = null, ?string $detail = null): void
    {
        $user = Session::get('user');
        App::db()->insert('audit_log', [
            'pengguna_id' => $user['id'] ?? null,
            'aksi'        => $aksi,
            'entitas'     => $entitas,
            'entitas_id'  => $entitasId,
            'detail'      => $detail,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
