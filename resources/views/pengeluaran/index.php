<?php $pages = (int) ceil($total / $per); ?>
<div class="page-head"><div><h1>Pengeluaran</h1><div class="sub">Termasuk biaya non-rit / biaya tetap</div></div></div>
<div class="grid grid-2">
<div class="card"><div class="card-head">Input Pengeluaran (rit / non-rit)</div><div class="card-body">
    <form method="post" action="/pengeluaran" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group"><label>Rit (kosongkan untuk biaya tetap/non-rit)</label>
            <input name="rit_id" placeholder="ID rit (opsional)"></div>
        <div class="form-row">
            <div class="form-group"><label>Kategori <span class="req">*</span></label>
                <select name="kategori_id" required><option value="">—</option>
                    <?php foreach ($kategori as $k): ?><option value="<?= $k['id'] ?>"><?= e($k['nama']) ?><?= $k['wajib_bukti']?' *':'' ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Nominal <span class="req">*</span></label><input type="number" name="nominal" min="1" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Tanggal <span class="req">*</span></label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label>Vendor</label><select name="vendor_id"><option value="">—</option><?php foreach ($vendor as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['nama']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-group"><label>Keterangan</label><input name="keterangan"></div>
        <div class="form-group"><label>Foto Nota</label><input type="file" name="lampiran" accept="image/*,application/pdf"></div>
        <button class="btn primary">Simpan</button>
    </form>
</div></div>
<div class="card"><div class="card-head">Daftar Pengeluaran</div><div class="card-body">
    <form class="filters" method="get">
        <div class="form-group"><label>Status</label>
            <select name="status"><option value="">Semua</option>
                <?php foreach (['menunggu','disetujui','ditolak'] as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select></div>
        <button class="btn">Filter</button>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Tanggal</th><th>Kategori</th><th>Rit</th><th class="num">Nominal</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= tgl($r['tanggal']) ?></td>
                <td><?= e($r['kategori']) ?><?= $r['flag_anomali']?' <span class="badge bad">anomali</span>':'' ?></td>
                <td><?= $r['rit_kode']?'<a href="/rit/'.$r['rit_id'].'">'.e($r['rit_kode']).'</a>':'<span class="tag">non-rit</span>' ?></td>
                <td class="num mono"><?= rupiah($r['nominal']) ?></td>
                <td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php $qs = fn($p)=>'?'.http_build_query(array_filter(['status'=>$status,'page'=>$p])); ?>
        <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $page>1?$qs($page-1):'#' ?>">‹</a>
        <span class="current"><?= $page ?>/<?= $pages ?></span>
        <a class="<?= $page>=$pages?'disabled':'' ?>" href="<?= $page<$pages?$qs($page+1):'#' ?>">›</a>
    </div>
    <?php endif; ?>
</div></div>
</div>
