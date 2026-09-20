<?php $user = $_user ?? \App\Core\Session::get('user'); ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="/assets/manifest.webmanifest">
    <title><?= e($title ?? 'Rit Saya') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
    <style>
        body{background:#f1f5f9}
        .kru-top{background:#0f172a;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10}
        .kru-top b{font-size:15px}
        .kru-main{padding:14px;max-width:560px;margin:0 auto}
        .kru-net{font-size:12px;padding:3px 8px;border-radius:999px;background:#16a34a;color:#fff}
        .kru-net.off{background:#dc2626}
        .rit-card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:12px;box-shadow:var(--shadow)}
        .rit-card h3{margin:0 0 4px;font-size:15px}
    </style>
</head>
<body>
<div class="kru-top">
    <div><b>🚌 Rit Saya</b><br><span style="font-size:12px;color:#94a3b8"><?= e($user['nama'] ?? '') ?></span></div>
    <div style="display:flex;gap:8px;align-items:center">
        <span class="kru-net" id="netStatus">online</span>
        <form method="post" action="/logout" style="margin:0"><?= csrf_field() ?><button class="btn sm">Keluar</button></form>
    </div>
</div>
<div class="kru-main">
    <?= \App\Core\View::render('partials/flash', ['_flash' => $_flash ?? []]) ?>
    <?= $slot ?>
</div>
</body>
</html>
