<div class="page-head"><div><h1>Biaya per KM</h1><div class="sub">Klik nopol untuk drill-down ke rit bus tsb</div></div>
    <div class="btn-row"><a class="btn" href="/laporan/export?jenis=biaya_per_km">⬇ Export CSV/Excel</a><a class="btn ghost" href="/laporan">← Laporan</a></div></div>
<div class="card"><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Bus</th><th class="num">Total Biaya</th><th class="num">Total KM</th><th class="num">Biaya / KM</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="/rit?q=<?= e($r['nopol']) ?>"><b><?= e($r['nopol']) ?></b></a></td>
            <td class="num mono"><?= rupiah($r['total_biaya']) ?></td>
            <td class="num mono"><?= number_format((float)$r['total_km'],0,',','.') ?> km</td>
            <td class="num mono"><b><?= $r['biaya_per_km']!==null?rupiah($r['biaya_per_km']):'-' ?></b></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="empty">Belum ada data (perlu rit selesai dengan km & biaya).</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
