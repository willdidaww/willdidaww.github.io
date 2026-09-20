<div class="page-head"><div><h1>Kru</h1><div class="sub">Peringatan otomatis SIM mendekati kedaluwarsa</div></div><a class="btn primary" href="/kru/create">+ Tambah Kru</a></div>
<div class="card"><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Nama</th><th>Posisi</th><th>No. HP</th><th>SIM</th><th>Berlaku Sampai</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($kru as $k): ?>
        <tr>
            <td><b><?= e($k['nama']) ?></b></td>
            <td><span class="tag"><?= e(str_replace('_',' ',$k['posisi'])) ?></span></td>
            <td><?= e($k['no_hp']) ?></td>
            <td><?= e($k['no_sim'] ?: '-') ?></td>
            <td>
                <?php if ($k['sim_berlaku_sampai']): ?>
                    <span class="badge <?= $k['sim_expired']?'bad':($k['sim_warning']?'warn':'ok') ?>"><?= tgl($k['sim_berlaku_sampai']) ?></span>
                    <?php if ($k['sim_expired']): ?><span class="muted"> kedaluwarsa</span><?php elseif ($k['sim_warning']): ?><span class="muted"> segera habis</span><?php endif; ?>
                <?php else: ?>-<?php endif; ?>
            </td>
            <td><span class="badge <?= $k['aktif']?'ok':'muted' ?>"><?= $k['aktif']?'aktif':'nonaktif' ?></span></td>
            <td class="right"><div class="btn-row" style="justify-content:flex-end">
                <a class="btn sm" href="/kru/<?= $k['id'] ?>/edit">Edit</a>
                <a class="btn sm" href="/uang-jalan/kru/<?= $k['id'] ?>">Uang Jalan</a>
                <a class="btn sm" href="/kas/kasbon/<?= $k['id'] ?>">Kasbon</a>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$kru): ?><tr><td colspan="7" class="empty">Belum ada kru.</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
