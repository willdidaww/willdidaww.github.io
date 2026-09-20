<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Base controller: helper umum (view, redirect, json, validasi CSRF, auth).
 */
abstract class Controller
{
    protected function db(): Database
    {
        return App::db();
    }

    protected function view(string $name, array $data = [], int $status = 200): Response
    {
        $data['_flash'] = Session::takeFlash();
        $data['_user'] = $this->user();
        $data['_csrf'] = Session::csrfToken();
        return Response::html(View::page($name, $data), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(Request $req): Response
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        return Response::redirect($ref);
    }

    /** Simpan input lama + error, lalu redirect balik. */
    protected function backWithErrors(Request $req, array $errors, ?string $to = null): Response
    {
        Session::set('_old', $req->body);
        foreach ($errors as $e) {
            Session::flash('error', $e);
        }
        return Response::redirect($to ?? ($_SERVER['HTTP_REFERER'] ?? '/'));
    }

    protected function user(): ?array
    {
        return Session::get('user');
    }

    protected function userId(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    protected function poolId(): ?int
    {
        return $this->user()['pool_id'] ?? null;
    }

    protected function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    protected function requireCsrf(Request $req): ?Response
    {
        if (in_array($req->method, ['POST', 'PUT', 'DELETE'], true)) {
            $token = $req->input('_csrf') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::verifyCsrf($token)) {
                return $req->wantsJson()
                    ? Response::json(['error' => 'Token CSRF tidak valid'], 419)
                    : Response::html('<h1>419 — Sesi kedaluwarsa</h1><p>Muat ulang halaman.</p>', 419);
            }
        }
        return null;
    }

    /** Cek apakah peran user termasuk salah satu yang diizinkan. */
    protected function can(array $roles): bool
    {
        return in_array($this->role(), $roles, true);
    }
}
