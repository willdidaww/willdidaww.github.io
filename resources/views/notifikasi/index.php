<div class="page-head"><div><h1>Notifikasi</h1></div>
    <form method="post" action="/notifikasi/baca-semua" style="margin:0"><?= csrf_field() ?><button class="btn sm">Tandai semua dibaca</button></form></div>
<div class="card"><div class="card-body">
    <?php foreach ($rows as $n): ?>
        <div class="split" style="<?= $n['dibaca']?'opacity:.6':'' ?>">
            <div>
                <b><?= e($n['judul']) ?></b> <span class="tag"><?= e($n['tipe']) ?></span>
                <?= $n['dibaca']?'':'<span class="badge warn">baru</span>' ?>
                <div class="muted" style="font-size:12px"><?= e($n['pesan']) ?> · <?= tgl($n['dibuat_pada'], true) ?></div>
            </div>
            <div class="btn-row">
                <?php if ($n['link']): ?><a class="btn sm" href="<?= e($n['link']) ?>">Buka</a><?php endif; ?>
                <?php if (!$n['dibaca']): ?><form method="post" action="/notifikasi/<?= $n['id'] ?>/baca" style="margin:0"><?= csrf_field() ?><button class="btn sm ghost">✓</button></form><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty">Tidak ada notifikasi.</div><?php endif; ?>
</div></div>
