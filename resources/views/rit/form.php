<?php $edit = $rit !== null; $kruTerpilih = $kruTerpilih ?? []; ?>
<div class="page-head"><div><h1><?= $edit ? 'Edit Rit' : 'Buat Rit' ?></h1></div><a class="btn" href="/rit">← Kembali</a></div>
<div class="card" style="max-width:720px"><div class="card-body">
    <form method="post" action="<?= $edit ? '/rit/'.$rit['id'] : '/rit' ?>">
        <?= csrf_field() ?><?php if ($edit) echo method_field('PUT'); ?>
        <?php if (!$edit): ?>
        <div class="form-group"><label>Bus <span class="req">*</span> <span class="hint">(hanya aktif & tidak sedang jalan)</span></label>
            <select name="bus_id" required>
                <option value="">— pilih bus —</option>
                <?php foreach ($buses as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['nopol']) ?> (<?= e($b['kelas']) ?>)</option><?php endforeach; ?>
            </select>
            <?php if (!$buses): ?><p class="hint" style="color:var(--bad)">Tidak ada bus tersedia — semua sedang jalan atau nonaktif.</p><?php endif; ?>
        </div>
        <?php else: ?>
            <p class="hint">Bus tidak bisa diubah setelah rit dibuat. Batalkan rit bila perlu ganti bus.</p>
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group"><label>Rute <span class="req">*</span></label>
                <select name="rute_id" required>
                    <?php foreach ($rute as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($edit && $rit['rute_id']==$r['id'])?'selected':'' ?>><?= e($r['asal']) ?>→<?= e($r['tujuan']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Tanggal <span class="req">*</span></label>
                <input type="date" name="tanggal" required value="<?= e($edit ? $rit['tanggal'] : date('Y-m-d')) ?>"></div>
        </div>
        <?php if (!$edit): ?>
        <div class="form-group"><label>Kru</label>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px">
                <?php foreach ($kru as $k): ?>
                    <label style="font-weight:400"><input type="checkbox" name="kru_ids[]" value="<?= $k['id'] ?>" style="width:auto"> <?= e($k['nama']) ?> <span class="tag"><?= e($k['posisi']) ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="form-group"><label>Catatan</label><textarea name="catatan" rows="2"><?= e($edit ? $rit['catatan'] : '') ?></textarea></div>
        <p class="hint">Estimasi uang jalan dihitung otomatis dari standar biaya rute saat rit dibuat.</p>
        <button class="btn primary">Simpan Rit</button>
    </form>
</div></div>
