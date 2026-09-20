<div class="page-head"><div><h1>Riwayat Uang Jalan — <?= e($kru['nama']) ?></h1><div class="sub">Pola pemakaian & setoran</div></div><a class="btn" href="/kru">← Kru</a></div>
<div class="card"><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Rit</th><th>Tanggal</th><th class="num">Diberikan</th><th class="num">Realisasi</th><th class="num">Selisih</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $s = (float)$r['nominal_diberikan']-(float)$r['realisasi']; ?>
        <tr>
            <td><a href="/rit/<?= $r['rit_id'] ?>"><?= e($r['rit_kode']) ?></a></td>
            <td><?= tgl($r['dibuat_pada']) ?></td>
            <td class="num mono"><?= rupiah($r['nominal_diberikan']) ?></td>
            <td class="num mono"><?= rupiah($r['realisasi']) ?></td>
            <td class="num mono"><?= rupiah($s) ?></td>
            <td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty">Belum ada riwayat.</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
