<?php
declare(strict_types=1);

namespace App\Middleware;

/** Hanya manajer & owner: approval, edit setelah rit tutup, hapus master sensitif. */
final class ManagerOnly extends RoleMiddleware
{
    protected array $allowed = ['owner', 'manajer'];
}
