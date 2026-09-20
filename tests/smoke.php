<?php
declare(strict_types=1);

/**
 * Smoke test tanpa jaringan: boot aplikasi & simulasikan request lewat Router
 * langsung. Memvalidasi routing, controller, view, DB, dan alur inti.
 */

$root = dirname(__DIR__);
$config = require $root . '/src/bootstrap.php';
require $root . '/src/Support/helpers.php';

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Core\App;

View::setPath($root . '/resources/views');

$router = new Router();
(require $root . '/routes/web.php')($router);

// Mulai session sekali sebelum ada output (CLI: hindari "headers already sent")
@Session::start();

// Di CLI test, jangan biarkan exception handler global menulis header
restore_exception_handler();
restore_error_handler();

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $extra = ''): void {
    global $pass, $fail;
    echo ($ok ? "  ✓ " : "  ✗ ") . $label . ($extra ? " — $extra" : "") . "\n";
    $ok ? $pass++ : $fail++;
}

/** Bangun Request sintetis & dispatch. */
function req(Router $router, string $method, string $path, array $body = [], array $query = []): \App\Core\Response {
    $r = new Request();
    $r->method = $method;
    $r->path = rtrim($path, '/') ?: '/';
    $r->query = $query;
    $r->body = $body;
    $r->files = [];
    return $router->dispatch($r);
}

// Pastikan DB fresh
$db = App::db();

echo "== 1. Auth ==\n";
$_SESSION = [];
$csrf = Session::csrfToken();

// login gagal
$res = req($router, 'POST', '/login', ['identifier' => 'owner@akap.test', 'password' => 'salah', '_csrf' => $csrf]);
check('login password salah -> redirect /login', $res->status === 302);

// login sukses
$res = req($router, 'POST', '/login', ['identifier' => 'owner@akap.test', 'password' => 'password', '_csrf' => $csrf]);
check('login owner sukses -> redirect', $res->status === 302 && ($res->headers['Location'] ?? '') === '/');
check('session berisi user owner', (Session::get('user')['role'] ?? '') === 'owner');

echo "== 2. Dashboard & halaman utama ==\n";
foreach (['/', '/bus', '/rute', '/kru', '/vendor', '/kategori', '/standar-biaya', '/rit',
          '/pengeluaran', '/uang-jalan', '/approval', '/pendapatan', '/perawatan', '/ban',
          '/dokumen', '/kas', '/laporan', '/laporan/biaya-per-km', '/laporan/konsumsi-bbm',
          '/laporan/margin', '/notifikasi', '/pengguna', '/audit'] as $p) {
    $res = req($router, 'GET', $p);
    check("GET $p -> 200", $res->status === 200, 'status=' . $res->status);
}

echo "== 3. Master data: buat bus & rute ==\n";
$csrf = Session::csrfToken();
$res = req($router, 'POST', '/bus', ['nopol' => 'B 9999 TEST', 'kelas' => 'eksekutif', 'tahun' => 2022, 'odometer' => 1000, 'status' => 'aktif', '_csrf' => $csrf]);
check('buat bus baru', $res->status === 302);
$busId = (int) $db->scalar("SELECT id FROM bus WHERE nopol = 'B 9999 TEST'");
check('bus tersimpan', $busId > 0);

// nopol duplikat ditolak
$res = req($router, 'POST', '/bus', ['nopol' => 'B 9999 TEST', 'status' => 'aktif', '_csrf' => $csrf]);
$dup = (int) $db->scalar("SELECT COUNT(*) FROM bus WHERE nopol = 'B 9999 TEST'");
check('nopol duplikat tidak menambah', $dup === 1);

echo "== 4. Rit: buat, validasi bus ganda, pengeluaran, approval, tutup ==\n";
$ruteId = (int) $db->scalar("SELECT id FROM rute LIMIT 1");
$res = req($router, 'POST', '/rit', ['bus_id' => $busId, 'rute_id' => $ruteId, 'tanggal' => date('Y-m-d'), '_csrf' => $csrf]);
check('buat rit', $res->status === 302);
$ritId = (int) $db->scalar("SELECT id FROM rit WHERE bus_id = ? ORDER BY id DESC LIMIT 1", [$busId]);
check('rit tersimpan', $ritId > 0);

// bus sama tidak boleh rit aktif kedua
$res = req($router, 'POST', '/rit', ['bus_id' => $busId, 'rute_id' => $ruteId, 'tanggal' => date('Y-m-d'), '_csrf' => $csrf]);
$countAktif = (int) $db->scalar("SELECT COUNT(*) FROM rit WHERE bus_id = ? AND status IN ('rencana','berjalan')", [$busId]);
check('bus tidak bisa 2 rit aktif', $countAktif === 1, "aktif=$countAktif");

// input pengeluaran ke rit
$katId = (int) $db->scalar("SELECT id FROM kategori_biaya WHERE wajib_bukti = 0 LIMIT 1");
$res = req($router, 'POST', '/pengeluaran', ['rit_id' => $ritId, 'kategori_id' => $katId, 'nominal' => 150000, 'tanggal' => date('Y-m-d'), '_csrf' => $csrf]);
$pengId = (int) $db->scalar("SELECT id FROM pengeluaran WHERE rit_id = ? ORDER BY id DESC LIMIT 1", [$ritId]);
check('pengeluaran tercatat menunggu', $pengId > 0 && $db->scalar("SELECT status FROM pengeluaran WHERE id=?", [$pengId]) === 'menunggu');

// approve
$res = req($router, 'POST', "/approval/$pengId/approve", ['_csrf' => $csrf]);
check('approve pengeluaran', $db->scalar("SELECT status FROM pengeluaran WHERE id=?", [$pengId]) === 'disetujui');

// reject tanpa alasan -> tetap menunggu (buat satu lagi)
req($router, 'POST', '/pengeluaran', ['rit_id' => $ritId, 'kategori_id' => $katId, 'nominal' => 50000, 'tanggal' => date('Y-m-d'), '_csrf' => $csrf]);
$peng2 = (int) $db->scalar("SELECT id FROM pengeluaran WHERE rit_id=? ORDER BY id DESC LIMIT 1", [$ritId]);
req($router, 'POST', "/approval/$peng2/reject", ['_csrf' => $csrf]); // tanpa alasan
check('reject tanpa alasan ditolak (masih menunggu)', $db->scalar("SELECT status FROM pengeluaran WHERE id=?", [$peng2]) === 'menunggu');
req($router, 'POST', "/approval/$peng2/reject", ['_csrf' => $csrf, 'alasan' => 'nota tidak jelas']);
check('reject dengan alasan berhasil', $db->scalar("SELECT status FROM pengeluaran WHERE id=?", [$peng2]) === 'ditolak');

echo "== 5. Uang jalan + rekonsiliasi ==\n";
$kruId = (int) $db->scalar("SELECT id FROM kru LIMIT 1");
req($router, 'POST', '/uang-jalan/cair', ['rit_id' => $ritId, 'kru_id' => $kruId, 'nominal_diberikan' => 200000, '_csrf' => $csrf]);
$ujId = (int) $db->scalar("SELECT id FROM uang_jalan WHERE rit_id=? ORDER BY id DESC LIMIT 1", [$ritId]);
check('uang jalan dicairkan', $ujId > 0);
check('kasbon debit tercatat', (int) $db->scalar("SELECT COUNT(*) FROM kasbon WHERE kru_id=? AND arah='debit'", [$kruId]) >= 1);
check('mutasi kas keluar tercatat', (int) $db->scalar("SELECT COUNT(*) FROM mutasi_kas WHERE ref_tipe='uang_jalan' AND arah='keluar'") >= 1);

// mulai & rekonsiliasi & tutup
req($router, 'POST', "/rit/$ritId/mulai", ['km_awal' => 1000, '_csrf' => $csrf]);
check('rit berjalan', $db->scalar("SELECT status FROM rit WHERE id=?", [$ritId]) === 'berjalan');
req($router, 'POST', "/uang-jalan/$ujId/rekonsiliasi", ['_csrf' => $csrf, 'alasan' => 'ok']);
check('uang jalan disetujui/dilaporkan', in_array($db->scalar("SELECT status FROM uang_jalan WHERE id=?", [$ujId]), ['disetujui','dilaporkan'], true));
$res = req($router, 'POST', "/rit/$ritId/tutup", ['km_akhir' => 1500, '_csrf' => $csrf]);
$ritStatus = $db->scalar("SELECT status FROM rit WHERE id=?", [$ritId]);
check('rit ditutup (uj disetujui & tak ada pengeluaran menunggu)', $ritStatus === 'selesai', "status=$ritStatus");

echo "== 6. View report (v_rekap_rit / v_biaya_per_km) ==\n";
check('v_rekap_rit terbaca', is_array($db->all("SELECT * FROM v_rekap_rit")));
check('v_biaya_per_km terbaca', is_array($db->all("SELECT * FROM v_biaya_per_km")));

echo "== 7. Role scoping: kru tidak bisa akses staff page ==\n";
$_SESSION = [];
$csrf = Session::csrfToken();
req($router, 'POST', '/login', ['identifier' => 'kru@akap.test', 'password' => 'password', '_csrf' => $csrf]);
check('login kru', (Session::get('user')['role'] ?? '') === 'kru');
$res = req($router, 'GET', '/pengguna'); // owner-only
check('kru akses /pengguna -> 403', $res->status === 403, 'status=' . $res->status);
$res = req($router, 'GET', '/bus'); // staff-only
check('kru akses /bus -> 403', $res->status === 403, 'status=' . $res->status);
$res = req($router, 'GET', '/kru-app');
check('kru akses /kru-app -> 200', $res->status === 200, 'status=' . $res->status);

echo "\n==== HASIL: $pass lulus, $fail gagal ====\n";
exit($fail > 0 ? 1 : 0);
