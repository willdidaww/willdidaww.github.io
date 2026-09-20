<div class="page-head"><div><h1>Kas & Kasbon</h1><div class="sub">Saldo dihitung dari mutasi (buku besar), bukan kolom statis</div></div></div>
<div class="grid grid-3">
    <?php foreach ($akun as $a): ?>
        <div class="stat"><div class="label"><?= e($a['nama']) ?> <span class="tag"><?= e($a['jenis']) ?></span></div><div class="value sm"><?= rupiah($a['saldo']) ?></div></div>
    <?php endforeach; ?>
</div>
<div class="grid grid-2">
    <div class="card"><div class="card-head">Mutasi Kas Terbaru</div><div class="card-body">
        <details style="margin-bottom:12px"><summary class="btn sm" style="display:inline-block">+ Mutasi Manual</summary>
            <form method="post" action="/kas/mutasi" style="margin-top:10px"><?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group"><label>Akun</label><select name="akun_id" required><?php foreach ($akun as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['nama']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Arah</label><select name="arah"><option value="masuk">Masuk</option><option value="keluar">Keluar</option></select></div>
                </div>
                <div class="form-group"><label>Nominal</label><input type="number" name="nominal" min="1" required></div>
                <div class="form-group"><label>Keterangan</label><input name="keterangan"></div>
                <button class="btn sm primary">Simpan</button>
            </form>
        </details>
        <div class="table-wrap"><table>
            <thead><tr><th>Tgl</th><th>Akun</th><th>Ket</th><th class="num">Nominal</th></tr></thead>
            <tbody>
            <?php foreach ($mutasi as $m): ?>
                <tr><td><?= tgl($m['tanggal']) ?></td><td><?= e($m['akun']) ?></td>
                    <td><?= e($m['keterangan']) ?> <span class="tag"><?= e($m['ref_tipe']) ?></span></td>
                    <td class="num mono" style="color:<?= $m['arah']==='masuk'?'var(--ok)':'var(--bad)' ?>"><?= ($m['arah']==='masuk'?'+':'−') ?><?= rupiah($m['nominal']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$mutasi): ?><tr><td colspan="4" class="empty">Belum ada mutasi.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <details style="margin-top:12px"><summary class="btn sm" style="display:inline-block">+ Akun Kas</summary>
            <form method="post" action="/kas/akun" style="margin-top:10px"><?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group"><label>Nama</label><input name="nama" required></div>
                    <div class="form-group"><label>Jenis</label><select name="jenis"><option value="kas">Kas</option><option value="bank">Bank</option></select></div>
                </div>
                <div class="form-group"><label>Saldo Awal</label><input type="number" name="saldo_awal" value="0"></div>
                <button class="btn sm primary">Tambah Akun</button>
            </form>
        </details>
    </div></div>
    <div class="card"><div class="card-head">Saldo Kasbon per Kru</div><div class="card-body">
        <div class="table-wrap"><table>
            <thead><tr><th>Kru</th><th class="num">Saldo Kasbon</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($kasbon as $k): ?>
                <tr>
                    <td><?= e($k['nama']) ?> <?= $k['alert']?'<span class="badge bad">> batas</span>':'' ?></td>
                    <td class="num mono"><?= rupiah($k['saldo']) ?></td>
                    <td class="right"><a class="btn sm" href="/kas/kasbon/<?= $k['id'] ?>">Buku Besar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$kasbon): ?><tr><td colspan="3" class="empty">Semua kasbon lunas.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
        <p class="hint">Alert bila saldo kasbon kru melebihi <?= rupiah($batasKasbon) ?>.</p>
    </div></div>
</div>
