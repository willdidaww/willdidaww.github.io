<?php $edit = $kru !== null; ?>
<div class="page-head"><div><h1><?= $edit ? 'Edit' : 'Tambah' ?> Kru</h1></div><a class="btn" href="/kru">← Kembali</a></div>
<div class="card" style="max-width:640px"><div class="card-body">
    <form method="post" action="<?= $edit ? '/kru/'.$kru['id'] : '/kru' ?>">
        <?= csrf_field() ?><?php if ($edit) echo method_field('PUT'); ?>
        <div class="form-group"><label>Nama <span class="req">*</span></label><input name="nama" required value="<?= e($edit ? $kru['nama'] : old('nama')) ?>"></div>
        <div class="form-row">
            <div class="form-group"><label>Posisi</label>
                <select name="posisi">
                    <?php foreach (['sopir'=>'Sopir','sopir_2'=>'Sopir 2','kernet'=>'Kernet','kondektur'=>'Kondektur'] as $val=>$lbl): ?>
                        <option value="<?= $val ?>" <?= ($edit && $kru['posisi']===$val)?'selected':'' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>No. HP</label><input name="no_hp" value="<?= e($edit ? $kru['no_hp'] : '') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>No. SIM</label><input name="no_sim" value="<?= e($edit ? $kru['no_sim'] : '') ?>"></div>
            <div class="form-group"><label>SIM Berlaku Sampai</label><input type="date" name="sim_berlaku_sampai" value="<?= e($edit ? $kru['sim_berlaku_sampai'] : '') ?>"></div>
        </div>
        <?php if ($edit): ?>
        <div class="form-group"><label>Status</label>
            <select name="aktif"><option value="1" <?= $kru['aktif']?'selected':'' ?>>Aktif</option><option value="0" <?= !$kru['aktif']?'selected':'' ?>>Nonaktif</option></select></div>
        <?php endif; ?>
        <button class="btn primary">Simpan</button>
    </form>
</div></div>
