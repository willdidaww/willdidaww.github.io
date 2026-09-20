<?php $pages = (int) ceil($total / $per); ?>
<div class="page-head"><div><h1>Audit Log</h1><div class="sub">Jejak aksi sensitif: approve, edit setelah tutup, hapus master, login</div></div></div>
<div class="card"><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): ?>
        <tr>
            <td><?= tgl($a['waktu'], true) ?></td>
            <td><?= e($a['pengguna'] ?: '-') ?></td>
            <td><span class="tag"><?= e($a['aksi']) ?></span></td>
            <td><?= e($a['entitas']) ?><?= $a['entitas_id']?' #'.$a['entitas_id']:'' ?></td>
            <td class="muted"><?= e($a['detail']) ?></td>
            <td class="muted"><?= e($a['ip']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty">Belum ada log.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php if ($pages > 1): ?>
<div class="pagination">
    <a class="<?= $page<=1?'disabled':'' ?>" href="?page=<?= max(1,$page-1) ?>">‹</a>
    <span class="current"><?= $page ?>/<?= $pages ?></span>
    <a class="<?= $page>=$pages?'disabled':'' ?>" href="?page=<?= min($pages,$page+1) ?>">›</a>
</div>
<?php endif; ?>
</div></div>
