<div class="page-head"><div><h1>Vendor</h1><div class="sub">SPBU, bengkel, toko sparepart, agen</div></div><a class="btn primary" href="/vendor/create">+ Tambah Vendor</a></div>
<div class="card"><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Nama</th><th>Jenis</th><th>Telepon</th><th>Alamat</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($vendor as $v): ?>
        <tr>
            <td><b><?= e($v['nama']) ?></b></td>
            <td><span class="tag"><?= e($v['jenis']) ?></span></td>
            <td><?= e($v['telepon']) ?></td>
            <td><?= e($v['alamat']) ?></td>
            <td><span class="badge <?= $v['aktif']?'ok':'muted' ?>"><?= $v['aktif']?'aktif':'nonaktif' ?></span></td>
            <td class="right"><a class="btn sm" href="/vendor/<?= $v['id'] ?>/edit">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$vendor): ?><tr><td colspan="6" class="empty">Belum ada vendor.</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
