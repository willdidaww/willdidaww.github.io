<div class="page-head"><div><h1>Kasbon — <?= e($kru['nama']) ?></h1><div class="sub">Buku besar append-only · saldo berjalan: <b class="mono"><?= rupiah($saldo) ?></b></div></div><a class="btn" href="/kas">← Kas</a></div>
<div class="card"><div class="card-body">
    <details style="margin-bottom:12px"><summary class="btn sm" style="display:inline-block">+ Setor Kasbon</summary>
        <form method="post" action="/kas/kasbon/setor" style="margin-top:10px"><?= csrf_field() ?>
            <input type="hidden" name="kru_id" value="<?= $kru['id'] ?>">
            <div class="form-group"><label>Nominal Setoran</label><input type="number" name="nominal" min="1" required></div>
            <div class="form-group"><label>Keterangan</label><input name="keterangan" value="Setoran kasbon"></div>
            <button class="btn sm primary">Catat Setoran</button>
        </form>
    </details>
    <div class="table-wrap"><table>
        <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= tgl($r['tanggal']) ?></td>
                <td><?= e($r['keterangan']) ?></td>
                <td class="num mono"><?= $r['arah']==='debit'?rupiah($r['nominal']):'' ?></td>
                <td class="num mono"><?= $r['arah']==='kredit'?rupiah($r['nominal']):'' ?></td>
                <td class="num mono"><?= rupiah($r['saldo_berjalan']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">Belum ada transaksi kasbon.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
