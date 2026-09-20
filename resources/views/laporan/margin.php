<div class="page-head"><div><h1>Laporan Margin</h1><div class="sub">Per rute & per bus · rekap kasbon</div></div>
    <div class="btn-row"><a class="btn" href="/laporan/export?jenis=margin_rute">⬇ Export</a><button class="btn" onclick="window.print()">🖨 Cetak / PDF</button><a class="btn ghost" href="/laporan">← Laporan</a></div></div>
<div class="card"><div class="card-body">
    <form class="filters" method="get">
        <div class="form-group"><label>Dari</label><input type="date" name="dari" value="<?= e($dari) ?>"></div>
        <div class="form-group"><label>Sampai</label><input type="date" name="sampai" value="<?= e($sampai) ?>"></div>
        <button class="btn">Filter</button>
    </form>
</div></div>
<div class="grid grid-2">
    <div class="card"><div class="card-head">Margin per Rute</div><div class="card-body"><div class="table-wrap"><table>
        <thead><tr><th>Rute</th><th class="num">Pendapatan</th><th class="num">Biaya</th><th class="num">Margin</th></tr></thead>
        <tbody>
        <?php foreach ($perRute as $r): ?>
            <tr><td><?= e($r['asal']) ?>→<?= e($r['tujuan']) ?></td>
                <td class="num mono"><?= rupiah($r['pendapatan']) ?></td>
                <td class="num mono"><?= rupiah($r['biaya']) ?></td>
                <td class="num mono" style="color:<?= $r['margin']>=0?'var(--ok)':'var(--bad)' ?>"><?= rupiah($r['margin']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$perRute): ?><tr><td colspan="4" class="empty">Belum ada data.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div></div>
    <div class="card"><div class="card-head">Margin per Bus</div><div class="card-body"><div class="table-wrap"><table>
        <thead><tr><th>Bus</th><th class="num">Pendapatan</th><th class="num">Biaya</th><th class="num">Margin</th></tr></thead>
        <tbody>
        <?php foreach ($perBus as $r): ?>
            <tr><td><?= e($r['nopol']) ?></td>
                <td class="num mono"><?= rupiah($r['pendapatan']) ?></td>
                <td class="num mono"><?= rupiah($r['biaya']) ?></td>
                <td class="num mono" style="color:<?= $r['margin']>=0?'var(--ok)':'var(--bad)' ?>"><?= rupiah($r['margin']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$perBus): ?><tr><td colspan="4" class="empty">Belum ada data.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div></div>
</div>
<div class="card"><div class="card-head">Rekap Kasbon Kru Belum Disetor</div><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Kru</th><th class="num">Saldo Kasbon</th></tr></thead>
    <tbody>
    <?php foreach ($kasbon as $k): ?><tr><td><?= e($k['nama']) ?></td><td class="num mono"><?= rupiah($k['saldo']) ?></td></tr><?php endforeach; ?>
    <?php if (!$kasbon): ?><tr><td colspan="2" class="empty">Semua kasbon lunas.</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
