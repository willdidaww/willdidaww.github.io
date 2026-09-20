<div class="page-head"><div><h1>Uang Jalan</h1><div class="sub">Pencairan → kasbon kru · realisasi real-time · rekonsiliasi</div></div></div>
<div class="grid grid-2">
<div class="card"><div class="card-head">Cairkan Uang Jalan</div><div class="card-body">
    <form method="post" action="/uang-jalan/cair">
        <?= csrf_field() ?>
        <div class="form-group"><label>Rit <span class="req">*</span></label>
            <select name="rit_id" required><option value="">— pilih rit aktif —</option>
                <?php foreach ($ritTanpaUJ as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['kode']) ?> — <?= e($r['nopol']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label>Kru <span class="req">*</span></label>
            <select name="kru_id" required><option value="">— pilih kru —</option>
                <?php foreach ($kru as $k): ?><option value="<?= $k['id'] ?>"><?= e($k['nama']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label>Nominal Diberikan <span class="req">*</span></label><input type="number" name="nominal_diberikan" min="1" required></div>
        <p class="hint">Dicatat sebagai kasbon kru + mutasi kas keluar otomatis.</p>
        <button class="btn primary">Cairkan</button>
    </form>
</div></div>
<div class="card"><div class="card-head">Daftar Uang Jalan</div><div class="card-body">
    <div class="table-wrap"><table>
        <thead><tr><th>Rit</th><th>Kru</th><th class="num">Diberikan</th><th class="num">Realisasi</th><th class="num">Selisih</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $selisih = (float)$r['nominal_diberikan'] - (float)$r['realisasi']; ?>
            <tr>
                <td><a href="/rit/<?= $r['rit_id'] ?>"><?= e($r['rit_kode']) ?></a></td>
                <td><?= e($r['kru']) ?></td>
                <td class="num mono"><?= rupiah($r['nominal_diberikan']) ?></td>
                <td class="num mono"><?= rupiah($r['realisasi']) ?></td>
                <td class="num mono" style="color:<?= $selisih>=0?'var(--ok)':'var(--bad)' ?>"><?= rupiah($selisih) ?></td>
                <td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                <td class="right">
                    <?php if (in_array($r['status'], ['dicairkan','dilaporkan'], true)): ?>
                    <details><summary class="btn sm">Rekonsiliasi</summary>
                        <form method="post" action="/uang-jalan/<?= $r['id'] ?>/rekonsiliasi" style="margin-top:8px;text-align:left">
                            <?= csrf_field() ?>
                            <div class="form-group"><label style="font-size:12px">Alasan (wajib bila selisih > toleransi)</label><input name="alasan" placeholder="Alasan selisih"></div>
                            <button class="btn sm primary">Proses</button>
                        </form>
                    </details>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada uang jalan.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
</div>
