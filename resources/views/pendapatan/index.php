<div class="page-head"><div><h1>Pendapatan</h1><div class="sub">Tiket, kargo, paket, carter, lain — per rit</div></div></div>
<div class="grid grid-2">
<div class="card"><div class="card-head">Input Pendapatan</div><div class="card-body">
    <form method="post" action="/pendapatan">
        <?= csrf_field() ?>
        <div class="form-group"><label>Rit <span class="req">*</span></label>
            <select name="rit_id" required><option value="">—</option><?php foreach ($rit as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['kode']) ?></option><?php endforeach; ?></select></div>
        <div class="form-row">
            <div class="form-group"><label>Jenis</label><select name="jenis"><?php foreach (['tiket','kargo','paket','carter','lain'] as $j): ?><option value="<?= $j ?>"><?= ucfirst($j) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Nominal <span class="req">*</span></label><input type="number" name="nominal" min="0" required></div>
        </div>
        <div class="form-group"><label>Jumlah Penumpang</label><input type="number" name="jumlah_penumpang"></div>
        <button class="btn primary">Simpan</button>
    </form>
</div></div>
<div class="card"><div class="card-head">Daftar Pendapatan</div><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Rit</th><th>Jenis</th><th class="num">Nominal</th><th class="num">Pnp</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="/rit/<?= $r['rit_id'] ?>"><?= e($r['rit_kode']) ?></a></td>
            <td><?= ucfirst($r['jenis']) ?></td>
            <td class="num mono"><?= rupiah($r['nominal']) ?></td>
            <td class="num"><?= e($r['jumlah_penumpang'] ?: '-') ?></td>
            <td class="right"><form method="post" action="/pendapatan/<?= $r['id'] ?>" onsubmit="return confirm('Hapus?')" style="margin:0"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn sm bad">✕</button></form></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty">Belum ada pendapatan.</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
</div>
