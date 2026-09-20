<div class="page-head"><div><h1>Rute</h1><div class="sub">Nonaktifkan tanpa hapus — histori tetap muncul</div></div><a class="btn primary" href="/rute/create">+ Tambah Rute</a></div>
<div class="card"><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Kode</th><th>Asal → Tujuan</th><th class="num">Jarak</th><th class="num">Estimasi</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rute as $r): ?>
        <tr>
            <td><?= e($r['kode']) ?></td>
            <td><b><?= e($r['asal']) ?></b> → <b><?= e($r['tujuan']) ?></b></td>
            <td class="num mono"><?= $r['jarak_km'] ? number_format((float)$r['jarak_km'],0,',','.').' km' : '-' ?></td>
            <td class="num mono"><?= $r['estimasi_jam'] ? $r['estimasi_jam'].' jam' : '-' ?></td>
            <td><span class="badge <?= $r['aktif']?'ok':'muted' ?>"><?= $r['aktif']?'aktif':'nonaktif' ?></span></td>
            <td class="right"><div class="btn-row" style="justify-content:flex-end">
                <a class="btn sm" href="/rute/<?= $r['id'] ?>/edit">Edit</a>
                <form method="post" action="/rute/<?= $r['id'] ?>/toggle" style="margin:0"><?= csrf_field() ?>
                    <button class="btn sm"><?= $r['aktif']?'Nonaktifkan':'Aktifkan' ?></button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rute): ?><tr><td colspan="6" class="empty">Belum ada rute.</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
