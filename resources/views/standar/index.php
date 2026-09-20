<div class="page-head"><div><h1>Standar Biaya per Rute</h1><div class="sub">Nominal + toleransi per kategori per rute/kelas, dengan riwayat perubahan</div></div></div>
<div class="grid grid-2">
    <div class="card"><div class="card-head">Standar Berlaku & Riwayat</div><div class="card-body">
        <div class="table-wrap"><table>
            <thead><tr><th>Rute</th><th>Kategori</th><th>Kelas</th><th class="num">Standar</th><th class="num">Tol.</th><th>Berlaku</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $aktif = $r['berlaku_sampai'] === null; ?>
                <tr style="<?= $aktif?'':'opacity:.55' ?>">
                    <td><?= e($r['asal']) ?>→<?= e($r['tujuan']) ?></td>
                    <td><?= e($r['kategori']) ?></td>
                    <td><?= e($r['kelas_bus'] ?: 'semua') ?></td>
                    <td class="num mono"><?= rupiah($r['nominal_standar']) ?></td>
                    <td class="num"><?= (float)$r['toleransi_pct'] ?>%</td>
                    <td><?= tgl($r['berlaku_dari']) ?><?= $aktif ? ' <span class="badge ok">aktif</span>' : ' — '.tgl($r['berlaku_sampai']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty">Belum ada standar.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div></div>
    <div class="card"><div class="card-head">Tetapkan Standar</div><div class="card-body">
        <form method="post" action="/standar-biaya">
            <?= csrf_field() ?>
            <div class="form-group"><label>Rute <span class="req">*</span></label>
                <select name="rute_id" required><option value="">— pilih —</option>
                    <?php foreach ($rute as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['asal']) ?>→<?= e($r['tujuan']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Kategori <span class="req">*</span></label>
                <select name="kategori_id" required><option value="">— pilih —</option>
                    <?php foreach ($kategori as $k): ?><option value="<?= $k['id'] ?>"><?= e($k['nama']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Kelas Bus (opsional)</label>
                <select name="kelas_bus"><option value="">Semua kelas</option>
                    <?php foreach (['ekonomi','bisnis','eksekutif','sleeper'] as $k): ?><option value="<?= $k ?>"><?= ucfirst($k) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-row">
                <div class="form-group"><label>Nominal Standar <span class="req">*</span></label><input type="number" name="nominal_standar" min="1" required></div>
                <div class="form-group"><label>Toleransi (%)</label><input type="number" name="toleransi_pct" value="10" min="0" max="100"></div>
            </div>
            <p class="hint">Menyimpan akan mengarsipkan standar lama untuk kombinasi yang sama (riwayat terjaga).</p>
            <button class="btn primary">Simpan Standar</button>
        </form>
    </div></div>
</div>
