<?php /** @var string $slot */ /** @var string $title */ ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Login') ?> — Biaya Bus AKAP</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <div class="auth-wrap">
        <?= $slot ?>
    </div>
</body>
</html>
