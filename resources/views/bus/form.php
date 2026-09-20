<?php $edit = $bus !== null; ?>
<div class="page-head"><div><h1><?= $edit ? 'Edit' : 'Tambah' ?> Bus</h1></div><a class="btn" href="/bus">← Kembali</a></div>
<div class="card" style="max-width:640px"><div class="card-body">
    <form method="post" action="<?= $edit ? '/bus/'.$bus['id'] : '/bus' ?>">
        <?= csrf_field() ?><?php if ($edit) echo method_field('PUT'); ?>
        <div class="form-group"><label>Nopol <span class="req">*</span></label>
            <input name="nopol" required value="<?= e($edit ? $bus['nopol'] : old('nopol')) ?>"></div>
        <div class="form-row">
            <div class="form-group"><label>Kelas</label>
                <select name="kelas">
                    <?php foreach (['ekonomi','bisnis','eksekutif','sleeper'] as $k): ?>
                        <option value="<?= $k ?>" <?= ($edit && $bus['kelas']===$k)?'selected':'' ?>><?= ucfirst($k) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Karoseri</label><input name="karoseri" value="<?= e($edit ? $bus['karoseri'] : '') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Tahun</label><input type="number" name="tahun" value="<?= e($edit ? $bus['tahun'] : '') ?>"></div>
            <div class="form-group"><label>Odometer (km)</label><input type="number" name="odometer" value="<?= e($edit ? $bus['odometer'] : 0) ?>"></div>
        </div>
        <div class="form-group"><label>Status</label>
            <select name="status">
                <?php foreach (['aktif','perawatan','nonaktif'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($edit && $bus['status']===$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select></div>
        <button class="btn primary">Simpan</button>
    </form>
</div></div>
