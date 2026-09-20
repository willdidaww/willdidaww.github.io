<div class="page-head"><div><h1>Perawatan</h1><div class="sub">Servis, perbaikan, ban — dengan reminder odometer</div></div><a class="btn primary" href="/perawatan/create">+ Catat Perawatan</a></div>
<?php if ($reminder): ?>
<div class="alert warn"><b>Reminder servis:</b>
    <?php foreach ($reminder as $rm): ?>
        <?= e($rm['nopol']) ?> (odo <?= number_format((float)$rm['odometer'],0,',','.') ?> / servis @ <?= number_format((float)$rm['odometer_servis_berikutnya'],0,',','.') ?>) ·
    <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card"><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Tanggal</th><th>Bus</th><th>Jenis</th><th>Vendor</th><th class="num">Total</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= tgl($r['tanggal_masuk']) ?></td>
            <td><?= e($r['nopol']) ?></td>
            <td><span class="tag"><?= e(str_replace('_',' ',$r['jenis'])) ?></span></td>
            <td><?= e($r['vendor'] ?: '-') ?></td>
            <td class="num mono"><?= rupiah($r['total']) ?></td>
            <td class="right"><a class="btn sm" href="/perawatan/<?= $r['id'] ?>">Detail</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty">Belum ada catatan perawatan.</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
