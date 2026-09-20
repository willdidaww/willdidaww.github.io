<?php $margin = $totalPendapatan - $totalBiaya; ?>
<div class="page-head">
    <div>
        <h1>Rit <?= e($rit['kode']) ?> <span class="badge <?= badge_class($rit['status']) ?>"><?= e($rit['status']) ?></span></h1>
        <div class="sub"><?= e($rit['nopol']) ?> (<?= e($rit['kelas']) ?>) · <?= e($rit['asal']) ?> → <?= e($rit['tujuan']) ?> · <?= tgl($rit['tanggal']) ?></div>
    </div>
    <a class="btn" href="/rit">← Daftar Rit</a>
</div>

<div class="grid grid-4">
    <div class="stat"><div class="label">Estimasi Uang Jalan</div><div class="value sm"><?= rupiah($rit['estimasi_uang_jalan']) ?></div></div>
    <div class="stat"><div class="label">Biaya Disetujui</div><div class="value sm"><?= rupiah($totalBiaya) ?></div></div>
    <div class="stat"><div class="label">Pendapatan</div><div class="value sm"><?= rupiah($totalPendapatan) ?></div></div>
    <div class="stat <?= $margin>=0?'':'bad' ?>"><div class="label">Margin</div><div class="value sm"><?= rupiah($margin) ?></div></div>
</div>

<!-- Aksi status rit -->
<div class="card"><div class="card-body">
    <div class="btn-row">
        <?php if ($rit['status']==='rencana'): ?>
            <form method="post" action="/rit/<?= $rit['id'] ?>/mulai" style="display:flex;gap:6px;align-items:center;margin:0">
                <?= csrf_field() ?>
                <input type="number" name="km_awal" placeholder="Km awal" style="width:140px">
                <button class="btn ok">Mulai (Berangkat)</button>
            </form>
            <a class="btn" href="/rit/<?= $rit['id'] ?>/edit">Ubah</a>
            <form method="post" action="/rit/<?= $rit['id'] ?>/batal" onsubmit="return confirm('Batalkan rit?')" style="margin:0"><?= csrf_field() ?><button class="btn bad">Batalkan</button></form>
        <?php elseif ($rit['status']==='berjalan'): ?>
            <form method="post" action="/rit/<?= $rit['id'] ?>/tutup" style="display:flex;gap:6px;align-items:center;margin:0">
                <?= csrf_field() ?>
                <input type="number" name="km_akhir" placeholder="Km akhir" value="<?= e($rit['km_akhir']) ?>" style="width:140px">
                <button class="btn primary">Tutup Rit</button>
            </form>
            <span class="hint">Tutup rit hanya bisa jika uang jalan direkonsiliasi & tidak ada pengeluaran menunggu.</span>
        <?php else: ?>
            <span class="badge muted">Rit terkunci — semua transaksi tidak dapat diubah</span>
            <span class="hint">Km: <?= e($rit['km_awal']) ?> → <?= e($rit['km_akhir']) ?> (<?= $rit['km_akhir']&&$rit['km_awal']?($rit['km_akhir']-$rit['km_awal']).' km':'-' ?>)</span>
        <?php endif; ?>
    </div>
    <?php if ($kru): ?><div style="margin-top:10px" class="muted">Kru: <?php foreach ($kru as $k) echo e($k['nama']).' ('.$k['peran'].') '; ?></div><?php endif; ?>
</div></div>

<div class="grid grid-2">
    <!-- Timeline pengeluaran -->
    <div class="card">
        <div class="card-head">Timeline Pengeluaran (<?= count($pengeluaran) ?>)</div>
        <div class="card-body">
            <?php if (!$locked): ?>
            <details style="margin-bottom:14px"><summary class="btn sm" style="display:inline-block">+ Input Pengeluaran</summary>
                <form method="post" action="/pengeluaran" enctype="multipart/form-data" style="margin-top:12px" id="formPeng">
                    <?= csrf_field() ?>
                    <input type="hidden" name="rit_id" value="<?= $rit['id'] ?>">
                    <div class="form-row">
                        <div class="form-group"><label>Kategori <span class="req">*</span></label>
                            <select name="kategori_id" required id="katSelect">
                                <option value="">— pilih —</option>
                                <?php foreach ($kategori as $k): ?>
                                    <option value="<?= $k['id'] ?>" data-tipe="<?= e($k['tipe']) ?>" data-bukti="<?= $k['wajib_bukti'] ?>"><?= e($k['nama']) ?><?= $k['wajib_bukti']?' *':'' ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="form-group"><label>Nominal <span class="req">*</span></label><input type="number" name="nominal" min="1" required></div>
                    </div>
                    <!-- Form khusus BBM -->
                    <div id="bbmFields" style="display:none">
                        <div class="form-row">
                            <div class="form-group"><label>Odometer</label><input type="number" name="odometer"></div>
                            <div class="form-group"><label>Liter</label><input type="number" step="0.01" name="liter"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Harga/Liter</label><input type="number" name="harga_liter"></div>
                            <div class="form-group"><label style="margin-top:26px"><input type="checkbox" name="tangki_penuh" value="1" style="width:auto"> Tangki penuh</label></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="form-group"><label>Vendor</label>
                            <select name="vendor_id"><option value="">—</option><?php foreach ($vendor as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['nama']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="form-group"><label>Keterangan</label><input name="keterangan"></div>
                    <div class="form-group"><label>Foto Nota <span id="buktiReq" style="display:none;color:var(--bad)">(wajib)</span></label><input type="file" name="lampiran" accept="image/*,application/pdf"></div>
                    <button class="btn primary">Simpan Pengeluaran</button>
                </form>
            </details>
            <?php endif; ?>
            <div class="timeline">
                <?php foreach ($pengeluaran as $e): ?>
                    <div class="item">
                        <div class="split" style="border:none;padding-bottom:2px">
                            <div>
                                <b><?= e($e['kategori']) ?></b> — <span class="mono"><?= rupiah($e['nominal']) ?></span>
                                <?php if ($e['flag_anomali']): ?><span class="badge bad">anomali</span><?php endif; ?>
                                <span class="badge <?= badge_class($e['status']) ?>"><?= e($e['status']) ?></span>
                                <span class="tag"><?= e($e['sumber']) ?></span>
                            </div>
                        </div>
                        <div class="muted" style="font-size:12px">
                            <?= tgl($e['tanggal']) ?> · <?= e($e['keterangan']) ?>
                            <?php if ($e['liter']): ?> · <?= $e['liter'] ?> L @ <?= rupiah($e['harga_liter']) ?><?php endif; ?>
                            <?php if ($e['lampiran']): ?> · <a href="<?= e($e['lampiran']) ?>" target="_blank">📎 nota</a><?php endif; ?>
                            <?php if ($e['alasan_reject']): ?> · <span style="color:var(--bad)">ditolak: <?= e($e['alasan_reject']) ?></span><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$pengeluaran): ?><div class="muted">Belum ada pengeluaran.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pendapatan + Uang jalan -->
    <div>
        <div class="card">
            <div class="card-head">Pendapatan</div>
            <div class="card-body">
                <?php if (!$locked): ?>
                <form method="post" action="/pendapatan" style="margin-bottom:12px">
                    <?= csrf_field() ?><input type="hidden" name="rit_id" value="<?= $rit['id'] ?>">
                    <div class="form-row">
                        <div class="form-group"><label>Jenis</label>
                            <select name="jenis"><?php foreach (['tiket','kargo','paket','carter','lain'] as $j): ?><option value="<?= $j ?>"><?= ucfirst($j) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Nominal</label><input type="number" name="nominal" min="0" required></div>
                    </div>
                    <div class="form-group"><label>Jumlah Penumpang (okupansi)</label><input type="number" name="jumlah_penumpang"></div>
                    <button class="btn sm primary">+ Tambah Pendapatan</button>
                </form>
                <?php endif; ?>
                <?php foreach ($pendapatan as $p): ?>
                    <div class="split"><span><?= ucfirst($p['jenis']) ?><?= $p['jumlah_penumpang']?' · '.$p['jumlah_penumpang'].' pnp':'' ?></span><b class="mono"><?= rupiah($p['nominal']) ?></b></div>
                <?php endforeach; ?>
                <?php if (!$pendapatan): ?><div class="muted">Belum ada pendapatan.</div><?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head">Uang Jalan</div>
            <div class="card-body">
                <?php foreach ($uangJalan as $uj): ?>
                    <div class="split"><span><?= e($uj['kru']) ?> <span class="badge <?= badge_class($uj['status']) ?>"><?= e($uj['status']) ?></span></span><b class="mono"><?= rupiah($uj['nominal_diberikan']) ?></b></div>
                <?php endforeach; ?>
                <?php if (!$uangJalan): ?><div class="muted">Belum ada pencairan uang jalan. <a href="/uang-jalan">Cairkan →</a></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Tampilkan field BBM & indikator wajib bukti sesuai kategori
(function(){
  const sel = document.getElementById('katSelect');
  if (!sel) return;
  const bbm = document.getElementById('bbmFields');
  const req = document.getElementById('buktiReq');
  sel.addEventListener('change', function(){
    const opt = sel.options[sel.selectedIndex];
    bbm.style.display = opt.dataset.tipe === 'bbm' ? 'block' : 'none';
    req.style.display = opt.dataset.bukti === '1' ? 'inline' : 'none';
  });
})();
</script>
