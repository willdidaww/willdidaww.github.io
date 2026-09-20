<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Basis middleware otorisasi peran. Subclass menetapkan $allowed.
 * Kru dibatasi lebih lanjut di controller (hanya rit miliknya).
 */
abstract class RoleMiddleware
{
    /** @var string[] */
    protected array $allowed = [];

    public function handle(Request $req): ?Response
    {
        $user = Session::get('user');
        if (!$user) {
            return Response::redirect('/login');
        }
        if (!in_array($user['role'], $this->allowed, true)) {
            if ($req->wantsJson()) {
                return Response::json(['error' => 'Akses ditolak'], 403);
            }
            return Response::html(View::page('errors/403', ['title' => '403']), 403);
        }
        return null;
    }
}
