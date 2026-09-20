<?php $edit = $rute !== null; ?>
<div class="page-head"><div><h1><?= $edit ? 'Edit' : 'Tambah' ?> Rute</h1></div><a class="btn" href="/rute">← Kembali</a></div>
<div class="card" style="max-width:640px"><div class="card-body">
    <form method="post" action="<?= $edit ? '/rute/'.$rute['id'] : '/rute' ?>">
        <?= csrf_field() ?><?php if ($edit) echo method_field('PUT'); ?>
        <div class="form-group"><label>Kode</label><input name="kode" value="<?= e($edit ? $rute['kode'] : '') ?>" placeholder="JKT-SBY"></div>
        <div class="form-row">
            <div class="form-group"><label>Asal <span class="req">*</span></label><input name="asal" required value="<?= e($edit ? $rute['asal'] : old('asal')) ?>"></div>
            <div class="form-group"><label>Tujuan <span class="req">*</span></label><input name="tujuan" required value="<?= e($edit ? $rute['tujuan'] : old('tujuan')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Jarak (km)</label><input type="number" step="0.1" name="jarak_km" value="<?= e($edit ? $rute['jarak_km'] : '') ?>"></div>
            <div class="form-group"><label>Estimasi (jam)</label><input type="number" step="0.1" name="estimasi_jam" value="<?= e($edit ? $rute['estimasi_jam'] : '') ?>"></div>
        </div>
        <button class="btn primary">Simpan</button>
    </form>
</div></div>
