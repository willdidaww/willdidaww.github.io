<div class="page-head">
    <div><h1>Bus</h1><div class="sub">Master armada — nopol unik, tidak bisa dihapus jika ada rit aktif</div></div>
    <a class="btn primary" href="/bus/create">+ Tambah Bus</a>
</div>

<div class="card"><div class="card-body">
    <form class="filters" method="get">
        <div class="form-group"><label>Cari</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="Nopol / karoseri"></div>
        <button class="btn">Cari</button>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Nopol</th><th>Kelas</th><th>Karoseri</th><th>Tahun</th><th class="num">Odometer</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($buses as $b): ?>
            <tr>
                <td><b><?= e($b['nopol']) ?></b></td>
                <td><?= e($b['kelas']) ?></td>
                <td><?= e($b['karoseri']) ?></td>
                <td><?= e($b['tahun']) ?></td>
                <td class="num mono"><?= number_format((float)$b['odometer'],0,',','.') ?> km</td>
                <td><span class="badge <?= badge_class($b['status']) ?>"><?= e($b['status']) ?></span></td>
                <td class="right">
                    <div class="btn-row" style="justify-content:flex-end">
                        <a class="btn sm" href="/bus/<?= $b['id'] ?>/edit">Edit</a>
                        <form method="post" action="/bus/<?= $b['id'] ?>" onsubmit="return confirm('Hapus / nonaktifkan bus ini?')" style="margin:0">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button class="btn sm bad">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$buses): ?><tr><td colspan="7" class="empty">Belum ada bus.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
