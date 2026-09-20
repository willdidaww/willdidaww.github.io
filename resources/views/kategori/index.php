<div class="page-head"><div><h1>Kategori Biaya</h1><div class="sub">Hierarki induk-anak, tandai wajib bukti foto</div></div></div>
<div class="grid grid-2">
    <div class="card"><div class="card-head">Daftar Kategori</div><div class="card-body">
        <div class="table-wrap"><table>
            <thead><tr><th>Nama</th><th>Tipe</th><th>Wajib Bukti</th><th></th></tr></thead>
            <tbody>
            <?php foreach (($byParent[0] ?? []) as $parent): ?>
                <tr>
                    <td><b><?= e($parent['nama']) ?></b></td>
                    <td><span class="tag"><?= e($parent['tipe']) ?></span></td>
                    <td><?= $parent['wajib_bukti']?'<span class="badge ok">wajib</span>':'<span class="muted">-</span>' ?></td>
                    <td></td>
                </tr>
                <?php foreach (($byParent[$parent['id']] ?? []) as $anak): ?>
                    <tr>
                        <td style="padding-left:28px">↳ <?= e($anak['nama']) ?></td>
                        <td><span class="tag"><?= e($anak['tipe']) ?></span></td>
                        <td><?= $anak['wajib_bukti']?'<span class="badge ok">wajib</span>':'<span class="muted">-</span>' ?></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div></div>
    <div class="card"><div class="card-head">Tambah Kategori</div><div class="card-body">
        <form method="post" action="/kategori">
            <?= csrf_field() ?>
            <div class="form-group"><label>Nama <span class="req">*</span></label><input name="nama" required></div>
            <div class="form-group"><label>Induk (opsional)</label>
                <select name="induk_id"><option value="">— kategori utama —</option>
                    <?php foreach ($induk as $i): ?><option value="<?= $i['id'] ?>"><?= e($i['nama']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Tipe</label>
                <select name="tipe"><option value="variabel">Variabel</option><option value="tetap">Tetap</option><option value="bbm">BBM</option></select></div>
            <div class="form-group"><label><input type="checkbox" name="wajib_bukti" value="1" style="width:auto"> Wajib bukti foto nota</label></div>
            <button class="btn primary">Tambah</button>
        </form>
    </div></div>
</div>
