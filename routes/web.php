<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\{
    AuthController, DashboardController, BusController, RuteController, KruController,
    VendorController, KategoriController, StandarBiayaController, RitController,
    PengeluaranController, UangJalanController, ApprovalController, PendapatanController,
    PerawatanController, BanController, DokumenController, KasController,
    LaporanController, NotifikasiController, PenggunaController, AuditController, KruAppController
};
use App\Middleware\{AuthMiddleware, StaffOnly, ManagerOnly, OwnerOnly};

/**
 * Definisi rute web. Dikembalikan sebagai closure yang menerima Router.
 */
return static function (Router $r): void {

    $auth  = [AuthMiddleware::class];
    $staff = [AuthMiddleware::class, StaffOnly::class];
    $mgr   = [AuthMiddleware::class, ManagerOnly::class];
    $owner = [AuthMiddleware::class, OwnerOnly::class];

    // --- Auth (publik) ---
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login']);
    $r->post('/logout', [AuthController::class, 'logout'], $auth);
    $r->get('/forgot-password', [AuthController::class, 'showForgot']);
    $r->post('/forgot-password', [AuthController::class, 'sendOtp']);
    $r->get('/reset-password', [AuthController::class, 'showReset']);
    $r->post('/reset-password', [AuthController::class, 'reset']);

    // --- Dashboard ---
    $r->get('/', [DashboardController::class, 'index'], $staff);

    // --- Master: Bus ---
    $r->get('/bus', [BusController::class, 'index'], $staff);
    $r->get('/bus/create', [BusController::class, 'create'], $staff);
    $r->post('/bus', [BusController::class, 'store'], $staff);
    $r->get('/bus/{id}/edit', [BusController::class, 'edit'], $staff);
    $r->put('/bus/{id}', [BusController::class, 'update'], $staff);
    $r->delete('/bus/{id}', [BusController::class, 'destroy'], $mgr);

    // --- Master: Rute ---
    $r->get('/rute', [RuteController::class, 'index'], $staff);
    $r->get('/rute/create', [RuteController::class, 'create'], $staff);
    $r->post('/rute', [RuteController::class, 'store'], $staff);
    $r->get('/rute/{id}/edit', [RuteController::class, 'edit'], $staff);
    $r->put('/rute/{id}', [RuteController::class, 'update'], $staff);
    $r->post('/rute/{id}/toggle', [RuteController::class, 'toggle'], $staff);

    // --- Master: Kru ---
    $r->get('/kru', [KruController::class, 'index'], $staff);
    $r->get('/kru/create', [KruController::class, 'create'], $staff);
    $r->post('/kru', [KruController::class, 'store'], $staff);
    $r->get('/kru/{id}/edit', [KruController::class, 'edit'], $staff);
    $r->put('/kru/{id}', [KruController::class, 'update'], $staff);

    // --- Master: Vendor ---
    $r->get('/vendor', [VendorController::class, 'index'], $staff);
    $r->get('/vendor/create', [VendorController::class, 'create'], $staff);
    $r->post('/vendor', [VendorController::class, 'store'], $staff);
    $r->get('/vendor/{id}/edit', [VendorController::class, 'edit'], $staff);
    $r->put('/vendor/{id}', [VendorController::class, 'update'], $staff);

    // --- Master: Kategori biaya ---
    $r->get('/kategori', [KategoriController::class, 'index'], $staff);
    $r->post('/kategori', [KategoriController::class, 'store'], $staff);
    $r->put('/kategori/{id}', [KategoriController::class, 'update'], $staff);

    // --- Master: Standar biaya ---
    $r->get('/standar-biaya', [StandarBiayaController::class, 'index'], $staff);
    $r->post('/standar-biaya', [StandarBiayaController::class, 'store'], $staff);

    // --- Rit ---
    $r->get('/rit', [RitController::class, 'index'], $staff);
    $r->get('/rit/create', [RitController::class, 'create'], $staff);
    $r->post('/rit', [RitController::class, 'store'], $staff);
    $r->get('/rit/{id}', [RitController::class, 'show'], $staff);
    $r->get('/rit/{id}/edit', [RitController::class, 'edit'], $staff);
    $r->put('/rit/{id}', [RitController::class, 'update'], $staff);
    $r->post('/rit/{id}/mulai', [RitController::class, 'mulai'], $staff);
    $r->post('/rit/{id}/tutup', [RitController::class, 'tutup'], $staff);
    $r->post('/rit/{id}/batal', [RitController::class, 'batal'], $staff);

    // --- Pengeluaran ---
    $r->get('/pengeluaran', [PengeluaranController::class, 'index'], $staff);
    $r->post('/pengeluaran', [PengeluaranController::class, 'store'], $staff);
    $r->put('/pengeluaran/{id}', [PengeluaranController::class, 'update'], $staff);
    $r->delete('/pengeluaran/{id}', [PengeluaranController::class, 'destroy'], $staff);

    // --- Uang jalan ---
    $r->get('/uang-jalan', [UangJalanController::class, 'index'], $staff);
    $r->post('/uang-jalan/cair', [UangJalanController::class, 'cair'], $staff);
    $r->post('/uang-jalan/{id}/rekonsiliasi', [UangJalanController::class, 'rekonsiliasi'], $staff);
    $r->get('/uang-jalan/kru/{kruId}', [UangJalanController::class, 'riwayatKru'], $staff);

    // --- Approval (manajer/owner untuk keputusan) ---
    $r->get('/approval', [ApprovalController::class, 'index'], $staff);
    $r->post('/approval/{id}/approve', [ApprovalController::class, 'approve'], $staff);
    $r->post('/approval/{id}/reject', [ApprovalController::class, 'reject'], $staff);
    $r->post('/approval/batch', [ApprovalController::class, 'batch'], $staff);

    // --- Pendapatan ---
    $r->get('/pendapatan', [PendapatanController::class, 'index'], $staff);
    $r->post('/pendapatan', [PendapatanController::class, 'store'], $staff);
    $r->delete('/pendapatan/{id}', [PendapatanController::class, 'destroy'], $staff);

    // --- Perawatan ---
    $r->get('/perawatan', [PerawatanController::class, 'index'], $staff);
    $r->get('/perawatan/create', [PerawatanController::class, 'create'], $staff);
    $r->post('/perawatan', [PerawatanController::class, 'store'], $staff);
    $r->get('/perawatan/{id}', [PerawatanController::class, 'show'], $staff);

    // --- Ban ---
    $r->get('/ban', [BanController::class, 'index'], $staff);
    $r->post('/ban', [BanController::class, 'store'], $staff);
    $r->post('/ban/pasang', [BanController::class, 'pasang'], $staff);
    $r->post('/ban/{id}/lepas', [BanController::class, 'lepas'], $staff);

    // --- Dokumen ---
    $r->get('/dokumen', [DokumenController::class, 'index'], $staff);
    $r->post('/dokumen', [DokumenController::class, 'store'], $staff);
    $r->delete('/dokumen/{id}', [DokumenController::class, 'destroy'], $staff);

    // --- Kas & kasbon ---
    $r->get('/kas', [KasController::class, 'index'], $staff);
    $r->post('/kas/akun', [KasController::class, 'storeAkun'], $staff);
    $r->post('/kas/mutasi', [KasController::class, 'storeMutasi'], $staff);
    $r->get('/kas/kasbon/{kruId}', [KasController::class, 'kasbonKru'], $staff);
    $r->post('/kas/kasbon/setor', [KasController::class, 'setorKasbon'], $staff);

    // --- Laporan ---
    $r->get('/laporan', [LaporanController::class, 'index'], $staff);
    $r->get('/laporan/biaya-per-km', [LaporanController::class, 'biayaPerKm'], $staff);
    $r->get('/laporan/konsumsi-bbm', [LaporanController::class, 'konsumsiBbm'], $staff);
    $r->get('/laporan/margin', [LaporanController::class, 'margin'], $staff);
    $r->get('/laporan/export', [LaporanController::class, 'export'], $staff);

    // --- Notifikasi ---
    $r->get('/notifikasi', [NotifikasiController::class, 'index'], $auth);
    $r->post('/notifikasi/{id}/baca', [NotifikasiController::class, 'baca'], $auth);
    $r->post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'], $auth);

    // --- Pengguna & audit (owner) ---
    $r->get('/pengguna', [PenggunaController::class, 'index'], $owner);
    $r->post('/pengguna', [PenggunaController::class, 'store'], $owner);
    $r->put('/pengguna/{id}', [PenggunaController::class, 'update'], $owner);
    $r->post('/pengguna/{id}/toggle', [PenggunaController::class, 'toggle'], $owner);
    $r->get('/audit', [AuditController::class, 'index'], $owner);

    // --- PWA Kru + API ---
    $r->get('/kru-app', [KruAppController::class, 'home'], $auth);
    $r->get('/api/kru/rit', [KruAppController::class, 'ritSaya'], $auth);
    $r->post('/api/kru/pengeluaran', [KruAppController::class, 'simpanPengeluaran'], $auth);
    // route /kru untuk role kru sudah dipetakan; controller cek role
};
