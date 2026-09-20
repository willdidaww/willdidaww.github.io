<?php $edit = $vendor !== null; ?>
<div class="page-head"><div><h1><?= $edit ? 'Edit' : 'Tambah' ?> Vendor</h1></div><a class="btn" href="/vendor">← Kembali</a></div>
<div class="card" style="max-width:640px"><div class="card-body">
    <form method="post" action="<?= $edit ? '/vendor/'.$vendor['id'] : '/vendor' ?>">
        <?= csrf_field() ?><?php if ($edit) echo method_field('PUT'); ?>
        <div class="form-row">
            <div class="form-group"><label>Nama <span class="req">*</span></label><input name="nama" required value="<?= e($edit ? $vendor['nama'] : old('nama')) ?>"></div>
            <div class="form-group"><label>Jenis</label>
                <select name="jenis">
                    <?php foreach (['spbu'=>'SPBU','bengkel'=>'Bengkel','sparepart'=>'Sparepart','agen'=>'Agen','lain'=>'Lain'] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($edit && $vendor['jenis']===$val)?'selected':'' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-group"><label>Telepon</label><input name="telepon" value="<?= e($edit ? $vendor['telepon'] : '') ?>"></div>
        <div class="form-group"><label>Alamat</label><textarea name="alamat" rows="2"><?= e($edit ? $vendor['alamat'] : '') ?></textarea></div>
        <?php if ($edit): ?>
        <div class="form-group"><label>Status</label><select name="aktif"><option value="1" <?= $vendor['aktif']?'selected':'' ?>>Aktif</option><option value="0" <?= !$vendor['aktif']?'selected':'' ?>>Nonaktif</option></select></div>
        <?php endif; ?>
        <button class="btn primary">Simpan</button>
    </form>
</div></div>
