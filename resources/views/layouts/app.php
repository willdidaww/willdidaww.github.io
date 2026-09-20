<?php
/** @var string $slot */
/** @var array|null $_user */
/** @var string $title */
$user = $_user ?? \App\Core\Session::get('user');
$role = $user['role'] ?? '';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$active = fn(string $p) => (($p === '/' ? $path === '/' : str_starts_with($path, $p)) ? 'active' : '');
$staff = in_array($role, ['owner','manajer','keuangan','admin_pool'], true);
$notif = $user ? \App\Support\Notify::unreadCount((int)$user['id']) : 0;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Dashboard') ?> — Biaya Bus AKAP</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">🚌 <span>Biaya Bus AKAP</span><small><?= e($user['nama'] ?? '') ?></small></div>
        <nav>
            <?php if ($staff): ?>
                <a class="<?= $active('/') ?>" href="/"><span>📊 Dashboard</span></a>
                <div class="group">Operasional</div>
                <a class="<?= $active('/rit') ?>" href="/rit"><span>🧭 Rit</span></a>
                <a class="<?= $active('/uang-jalan') ?>" href="/uang-jalan"><span>💵 Uang Jalan</span></a>
                <a class="<?= $active('/pengeluaran') ?>" href="/pengeluaran"><span>🧾 Pengeluaran</span></a>
                <a class="<?= $active('/pendapatan') ?>" href="/pendapatan"><span>💰 Pendapatan</span></a>
                <a class="<?= $active('/approval') ?>" href="/approval"><span>✅ Persetujuan</span></a>
                <div class="group">Aset & Master</div>
                <a class="<?= $active('/bus') ?>" href="/bus"><span>🚍 Bus</span></a>
                <a class="<?= $active('/rute') ?>" href="/rute"><span>🗺️ Rute</span></a>
                <a class="<?= $active('/kru') ?>" href="/kru"><span>👷 Kru</span></a>
                <a class="<?= $active('/vendor') ?>" href="/vendor"><span>🏪 Vendor</span></a>
                <a class="<?= $active('/kategori') ?>" href="/kategori"><span>🏷️ Kategori Biaya</span></a>
                <a class="<?= $active('/standar-biaya') ?>" href="/standar-biaya"><span>📐 Standar Biaya</span></a>
                <a class="<?= $active('/perawatan') ?>" href="/perawatan"><span>🔧 Perawatan</span></a>
                <a class="<?= $active('/ban') ?>" href="/ban"><span>🛞 Ban</span></a>
                <a class="<?= $active('/dokumen') ?>" href="/dokumen"><span>📄 Dokumen</span></a>
                <a class="<?= $active('/kas') ?>" href="/kas"><span>🏦 Kas & Kasbon</span></a>
                <div class="group">Analisis</div>
                <a class="<?= $active('/laporan') ?>" href="/laporan"><span>📈 Laporan</span></a>
                <?php if ($role === 'owner'): ?>
                    <div class="group">Admin</div>
                    <a class="<?= $active('/pengguna') ?>" href="/pengguna"><span>👤 Pengguna</span></a>
                    <a class="<?= $active('/audit') ?>" href="/audit"><span>🔍 Audit Log</span></a>
                <?php endif; ?>
            <?php else: ?>
                <a class="<?= $active('/kru-app') ?>" href="/kru-app"><span>🧭 Rit Saya</span></a>
            <?php endif; ?>
            <div class="group">Akun</div>
            <a class="<?= $active('/notifikasi') ?>" href="/notifikasi"><span>🔔 Notifikasi<?= $notif ? '<span class="notif-dot">'.$notif.'</span>' : '' ?></span></a>
        </nav>
    </aside>
    <div class="main">
        <div class="topbar">
            <div class="who">Masuk sebagai <b><?= e($user['nama'] ?? '') ?></b> <span class="tag"><?= e($role) ?></span></div>
            <form method="post" action="/logout" style="margin:0">
                <?= csrf_field() ?>
                <button class="btn sm" type="submit">Keluar</button>
            </form>
        </div>
        <div class="content">
            <?= \App\Core\View::render('partials/flash', ['_flash' => $_flash ?? []]) ?>
            <?= $slot ?>
        </div>
    </div>
</div>
</body>
</html>
