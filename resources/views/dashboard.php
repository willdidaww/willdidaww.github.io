<div class="page-head">
    <div><h1>Dashboard</h1><div class="sub">Ringkasan operasional & keuangan</div></div>
    <a class="btn primary" href="/rit/create">+ Buat Rit</a>
</div>

<div class="grid grid-4">
    <div class="stat brand"><div class="label">Rit Berjalan</div><div class="value"><?= $ritBerjalan ?></div></div>
    <div class="stat"><div class="label">Rit Rencana</div><div class="value"><?= $ritRencana ?></div></div>
    <div class="stat <?= $menungguApproval ? 'warn' : '' ?>"><div class="label">Menunggu Approval</div><div class="value"><?= $menungguApproval ?><?= $anomali ? ' <span class="badge bad" style="font-size:11px">'.$anomali.' anomali</span>' : '' ?></div></div>
    <div class="stat <?= $kasbonBelumSetor > 0 ? 'warn' : '' ?>"><div class="label">Kasbon Belum Setor</div><div class="value sm"><?= rupiah($kasbonBelumSetor) ?></div></div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="card-head">Pendapatan vs Biaya (6 bulan)</div>
        <div class="card-body chart-box">
            <canvas id="chartPB" height="220"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-head">Bulan Ini</div>
        <div class="card-body">
            <div class="split"><span>Pendapatan</span><b class="mono"><?= rupiah($pendapatanBulan) ?></b></div>
            <div class="split"><span>Biaya (disetujui)</span><b class="mono"><?= rupiah($biayaBulan) ?></b></div>
            <div class="split"><span>Margin</span><b class="mono" style="color:<?= ($pendapatanBulan-$biayaBulan)>=0?'var(--ok)':'var(--bad)' ?>"><?= rupiah($pendapatanBulan - $biayaBulan) ?></b></div>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head">Dokumen Mendekati Jatuh Tempo</div>
        <div class="card-body">
            <?php if (!$dokJatuhTempo): ?><div class="muted">Tidak ada dalam 30 hari ke depan.</div><?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Bus</th><th>Jenis</th><th>Jatuh Tempo</th></tr></thead><tbody>
            <?php foreach ($dokJatuhTempo as $d): ?>
                <tr><td><?= e($d['nopol']) ?></td><td><?= e(strtoupper(str_replace('_',' ',$d['jenis']))) ?></td>
                <td><span class="badge <?= strtotime($d['jatuh_tempo'])<time()?'bad':'warn' ?>"><?= tgl($d['jatuh_tempo']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head">SIM Kru Mendekati Kedaluwarsa</div>
        <div class="card-body">
            <?php if (!$simExpiring): ?><div class="muted">Semua SIM aman (60 hari ke depan).</div><?php else: ?>
            <div class="table-wrap"><table><thead><tr><th>Kru</th><th>No. SIM</th><th>Berlaku Sampai</th></tr></thead><tbody>
            <?php foreach ($simExpiring as $s): ?>
                <tr><td><?= e($s['nama']) ?></td><td><?= e($s['no_sim']) ?></td>
                <td><span class="badge <?= strtotime($s['sim_berlaku_sampai'])<time()?'bad':'warn' ?>"><?= tgl($s['sim_berlaku_sampai']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">Rit Terbaru <a class="btn sm" href="/rit">Lihat semua</a></div>
    <div class="card-body">
        <div class="table-wrap"><table>
            <thead><tr><th>Kode</th><th>Bus</th><th>Rute</th><th>Tanggal</th><th>Status</th><th class="num">Biaya</th></tr></thead>
            <tbody>
            <?php foreach ($ritTerbaru as $r): ?>
                <tr>
                    <td><a href="/rit/<?= $r['id'] ?>"><?= e($r['kode']) ?></a></td>
                    <td><?= e($r['nopol']) ?></td>
                    <td><?= e($r['asal']) ?> → <?= e($r['tujuan']) ?></td>
                    <td><?= tgl($r['tanggal']) ?></td>
                    <td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td class="num mono"><?= rupiah($r['biaya']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ritTerbaru): ?><tr><td colspan="6" class="muted">Belum ada rit.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>

<script>
window.CHART_DATA = <?= json_encode($chart) ?>;
</script>
<script src="/assets/chart.js"></script>
