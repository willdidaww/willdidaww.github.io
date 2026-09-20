<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Audit;

/**
 * Autentikasi (bagian 1): login email/no HP + password, lupa password via OTP,
 * rate limiting, log login untuk audit.
 */
final class AuthController extends Controller
{
    public function showLogin(Request $req): Response
    {
        if ($this->user()) {
            return $this->redirect('/');
        }
        return Response::html(
            \App\Core\View::page('auth/login', [
                '_flash' => Session::takeFlash(),
                '_csrf'  => Session::csrfToken(),
                'title'  => 'Login',
            ], 'layouts/auth')
        );
    }

    public function login(Request $req): Response
    {
        if ($csrf = $this->requireCsrf($req)) { return $csrf; }

        $identifier = trim((string) $req->input('identifier'));
        $password   = (string) $req->input('password');

        // --- Rate limiting per IP+identifier ---
        if ($this->tooManyAttempts($req->ip(), $identifier)) {
            Session::flash('error', 'Terlalu banyak percobaan gagal. Coba lagi beberapa menit.');
            return $this->redirect('/login');
        }

        $user = $this->db()->first(
            'SELECT * FROM pengguna WHERE (email = ? OR no_hp = ?) AND aktif = 1',
            [$identifier, $identifier]
        );

        $ok = $user && password_verify($password, $user['password_hash']);
        $this->recordLogin($req, $user['id'] ?? null, $identifier, $ok);

        if (!$ok) {
            Session::flash('error', 'Email/No HP atau password salah.');
            return $this->redirect('/login');
        }

        // Rehash bila algoritma berubah
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            $this->db()->update('pengguna', ['password_hash' => password_hash($password, PASSWORD_BCRYPT)], ['id' => $user['id']]);
        }

        Session::regenerate();
        Session::set('user', [
            'id' => (int) $user['id'], 'nama' => $user['nama'], 'email' => $user['email'],
            'role' => $user['role'], 'pool_id' => $user['pool_id'] ? (int) $user['pool_id'] : null,
            'kru_id' => $user['kru_id'] ? (int) $user['kru_id'] : null,
        ]);
        Session::set('_last_activity', time());
        Audit::log('login', 'pengguna', (int) $user['id']);

        // Kru diarahkan ke PWA-nya, staff ke dashboard
        return $this->redirect($user['role'] === 'kru' ? '/kru-app' : '/');
    }

    public function logout(Request $req): Response
    {
        if ($csrf = $this->requireCsrf($req)) { return $csrf; }
        Audit::log('logout', 'pengguna', $this->userId());
        Session::destroy();
        Session::start();
        Session::flash('success', 'Anda telah keluar.');
        return $this->redirect('/login');
    }

    // --- Lupa password via OTP (email/SMS disimulasikan; di produksi kirim via gateway) ---
    public function showForgot(Request $req): Response
    {
        return Response::html(\App\Core\View::page('auth/forgot', [
            '_flash' => Session::takeFlash(), '_csrf' => Session::csrfToken(), 'title' => 'Lupa Password',
        ], 'layouts/auth'));
    }

    public function sendOtp(Request $req): Response
    {
        if ($csrf = $this->requireCsrf($req)) { return $csrf; }
        $identifier = trim((string) $req->input('identifier'));
        $user = $this->db()->first('SELECT id, email, no_hp FROM pengguna WHERE (email = ? OR no_hp = ?) AND aktif = 1', [$identifier, $identifier]);

        // Selalu tampilkan pesan sama (hindari user enumeration)
        if ($user) {
            $otp = (string) random_int(100000, 999999);
            Session::set('_otp', ['user_id' => (int) $user['id'], 'code' => $otp, 'exp' => time() + 300]);
            // Di produksi: kirim OTP via SMS/email gateway. Di sini dicatat ke log.
            App::logger()->info("OTP untuk {$identifier}: {$otp} (berlaku 5 menit)");
            if (App::config('app.debug')) {
                Session::flash('info', "Mode dev — OTP Anda: {$otp} (cek juga storage/logs/app.log).");
            }
        }
        Session::flash('success', 'Jika akun terdaftar, kode OTP telah dikirim.');
        return $this->redirect('/reset-password');
    }

    public function showReset(Request $req): Response
    {
        return Response::html(\App\Core\View::page('auth/reset', [
            '_flash' => Session::takeFlash(), '_csrf' => Session::csrfToken(), 'title' => 'Reset Password',
        ], 'layouts/auth'));
    }

    public function reset(Request $req): Response
    {
        if ($csrf = $this->requireCsrf($req)) { return $csrf; }
        $otp = Session::get('_otp');
        $code = trim((string) $req->input('otp'));
        $pass = (string) $req->input('password');

        if (!$otp || $otp['exp'] < time() || !hash_equals($otp['code'], $code)) {
            Session::flash('error', 'OTP tidak valid atau kedaluwarsa.');
            return $this->redirect('/reset-password');
        }
        if (strlen($pass) < 6) {
            Session::flash('error', 'Password minimal 6 karakter.');
            return $this->redirect('/reset-password');
        }
        $this->db()->update('pengguna', ['password_hash' => password_hash($pass, PASSWORD_BCRYPT)], ['id' => $otp['user_id']]);
        Session::forget('_otp');
        Audit::log('reset_password', 'pengguna', (int) $otp['user_id']);
        Session::flash('success', 'Password berhasil diubah. Silakan login.');
        return $this->redirect('/login');
    }

    // --- helper rate limiting & login log ---
    private function tooManyAttempts(string $ip, string $identifier): bool
    {
        $max = (int) App::config('security.login_max_attempts', 5);
        $window = (int) App::config('security.login_window_sec', 300);
        $since = date('Y-m-d H:i:s', time() - $window);
        $count = (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM login_log WHERE berhasil = 0 AND ip = ? AND email = ? AND waktu >= ?",
            [$ip, $identifier, $since]
        );
        return $count >= $max;
    }

    private function recordLogin(Request $req, ?int $userId, string $identifier, bool $ok): void
    {
        $this->db()->insert('login_log', [
            'pengguna_id' => $userId, 'email' => $identifier,
            'ip' => $req->ip(), 'user_agent' => $req->userAgent(),
            'berhasil' => $ok ? 1 : 0,
        ]);
    }
}
