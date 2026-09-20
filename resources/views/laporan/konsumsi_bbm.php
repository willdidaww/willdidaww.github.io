<div class="page-head"><div><h1>Konsumsi BBM</h1><div class="sub">km/liter dari pengisian BBM, ± dari rata-rata bus ditandai anomali</div></div>
    <div class="btn-row"><a class="btn" href="/laporan/export?jenis=konsumsi_bbm">⬇ Export</a><a class="btn ghost" href="/laporan">← Laporan</a></div></div>
<div class="card"><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Tanggal</th><th>Bus</th><th class="num">Odometer</th><th class="num">Jarak</th><th class="num">Liter</th><th class="num">km/L</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr style="<?= $r['anomali']?'background:var(--badbg)':'' ?>">
            <td><?= tgl($r['tanggal']) ?></td>
            <td><?= e($r['nopol']) ?></td>
            <td class="num mono"><?= number_format((float)$r['odometer'],0,',','.') ?></td>
            <td class="num mono"><?= $r['jarak']!==null?number_format((float)$r['jarak'],0,',','.').' km':'-' ?></td>
            <td class="num mono"><?= (float)$r['liter'] ?> L</td>
            <td class="num mono"><b><?= $r['km_per_liter']!==null?round((float)$r['km_per_liter'],2):'-' ?></b></td>
            <td><?= $r['anomali']?'<span class="badge bad">anomali</span>':'<span class="badge ok">normal</span>' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada data BBM (butuh pengeluaran BBM disetujui dengan odometer & liter).</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
