<div class="page-head"><div><h1>Detail Perawatan — <?= e($p['nopol']) ?></h1><div class="sub"><?= e(str_replace('_',' ',$p['jenis'])) ?> · <?= tgl($p['tanggal_masuk']) ?></div></div><a class="btn" href="/perawatan">← Kembali</a></div>
<div class="grid grid-2">
    <div class="card"><div class="card-head">Informasi</div><div class="card-body">
        <div class="split"><span>Vendor</span><b><?= e($p['vendor'] ?: '-') ?></b></div>
        <div class="split"><span>Odometer</span><b><?= $p['odometer']?number_format((float)$p['odometer'],0,',','.').' km':'-' ?></b></div>
        <div class="split"><span>Keluhan</span><b><?= e($p['keluhan'] ?: '-') ?></b></div>
        <div class="split"><span>Tindakan</span><b><?= e($p['tindakan'] ?: '-') ?></b></div>
        <div class="split"><span>Servis berikutnya</span><b><?= $p['odometer_servis_berikutnya']?number_format((float)$p['odometer_servis_berikutnya'],0,',','.').' km':'-' ?></b></div>
        <div class="split"><span>Total</span><b class="mono"><?= rupiah($p['total']) ?></b></div>
    </div></div>
    <div class="card"><div class="card-head">Item</div><div class="card-body"><div class="table-wrap"><table>
        <thead><tr><th>Jenis</th><th>Nama</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr><td><span class="tag"><?= e($it['jenis']) ?></span></td><td><?= e($it['nama']) ?></td><td class="num"><?= (float)$it['qty'] ?></td><td class="num mono"><?= rupiah($it['harga']) ?></td><td class="num mono"><?= rupiah($it['subtotal']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5" class="muted">Tidak ada item.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div></div>
</div>
