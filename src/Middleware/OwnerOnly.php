<?php
declare(strict_types=1);

namespace App\Middleware;

/** Hanya owner: manajemen pengguna, konfigurasi sensitif. */
final class OwnerOnly extends RoleMiddleware
{
    protected array $allowed = ['owner'];
}
