<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Pastikan pengguna sudah login & session belum kedaluwarsa.
 */
final class AuthMiddleware
{
    public function handle(Request $req): ?Response
    {
        $user = Session::get('user');
        if (!$user) {
            Session::flash('error', 'Silakan login terlebih dahulu.');
            return Response::redirect('/login');
        }
        // Cek durasi session (pemakaian mobile bergantian)
        $last = Session::get('_last_activity', time());
        $lifetime = (int) \App\Core\App::config('session.lifetime', 43200);
        if (time() - $last > $lifetime) {
            Session::destroy();
            Session::start();
            Session::flash('error', 'Sesi berakhir. Silakan login kembali.');
            return Response::redirect('/login');
        }
        Session::set('_last_activity', time());
        return null;
    }
}
