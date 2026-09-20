<?php $pages = (int) ceil($total / $per); ?>
<div class="page-head"><div><h1>Rit</h1><div class="sub"><?= $total ?> rit</div></div><a class="btn primary" href="/rit/create">+ Buat Rit</a></div>
<div class="card"><div class="card-body">
    <form class="filters" method="get">
        <div class="form-group"><label>Cari</label><input name="q" value="<?= e($q) ?>" placeholder="Kode / nopol"></div>
        <div class="form-group"><label>Status</label>
            <select name="status"><option value="">Semua</option>
                <?php foreach (['rencana','berjalan','selesai','batal'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-group"><label>Dari</label><input type="date" name="dari" value="<?= e($dari) ?>"></div>
        <div class="form-group"><label>Sampai</label><input type="date" name="sampai" value="<?= e($sampai) ?>"></div>
        <button class="btn">Filter</button>
        <a class="btn ghost" href="/rit">Reset</a>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Kode</th><th>Bus</th><th>Rute</th><th>Tanggal</th><th>Status</th><th class="num">Biaya</th><th class="num">Margin</th></tr></thead>
        <tbody>
        <?php foreach ($rits as $r): $margin = (float)$r['pendapatan'] - (float)$r['biaya']; ?>
            <tr>
                <td><a href="/rit/<?= $r['id'] ?>"><b><?= e($r['kode']) ?></b></a></td>
                <td><?= e($r['nopol']) ?></td>
                <td><?= e($r['asal']) ?>→<?= e($r['tujuan']) ?></td>
                <td><?= tgl($r['tanggal']) ?></td>
                <td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                <td class="num mono"><?= rupiah($r['biaya']) ?></td>
                <td class="num mono" style="color:<?= $margin>=0?'var(--ok)':'var(--bad)' ?>"><?= rupiah($margin) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rits): ?><tr><td colspan="7" class="empty">Tidak ada rit sesuai filter.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php $qs = fn($p) => '?' . http_build_query(array_filter(['q'=>$q,'status'=>$status,'dari'=>$dari,'sampai'=>$sampai,'page'=>$p])); ?>
        <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $page>1?$qs($page-1):'#' ?>">‹ Sebelumnya</a>
        <span class="current"><?= $page ?> / <?= $pages ?></span>
        <a class="<?= $page>=$pages?'disabled':'' ?>" href="<?= $page<$pages?$qs($page+1):'#' ?>">Berikutnya ›</a>
    </div>
    <?php endif; ?>
</div></div>
