<?php
declare(strict_types=1);

namespace App\Middleware;

/** Semua peran internal kantor (bukan kru). */
final class StaffOnly extends RoleMiddleware
{
    protected array $allowed = ['owner', 'manajer', 'keuangan', 'admin_pool'];
}
