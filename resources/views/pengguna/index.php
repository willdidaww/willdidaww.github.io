<div class="page-head"><div><h1>Pengguna</h1><div class="sub">Nonaktifkan tanpa hapus (histori terjaga)</div></div></div>
<div class="grid grid-2">
<div class="card"><div class="card-head">Tambah Pengguna</div><div class="card-body">
    <form method="post" action="/pengguna"><?= csrf_field() ?>
        <div class="form-group"><label>Nama <span class="req">*</span></label><input name="nama" required></div>
        <div class="form-row">
            <div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="email" required></div>
            <div class="form-group"><label>No. HP</label><input name="no_hp"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Peran <span class="req">*</span></label>
                <select name="role" required><?php foreach (['owner','manajer','keuangan','admin_pool','kru'] as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Pool</label><select name="pool_id"><option value="">—</option><?php foreach ($pool as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['nama']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Tautan Kru (bila peran kru)</label><select name="kru_id"><option value="">—</option><?php foreach ($kru as $k): ?><option value="<?= $k['id'] ?>"><?= e($k['nama']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Password <span class="req">*</span></label><input type="text" name="password" required minlength="6"></div>
        </div>
        <button class="btn primary">Buat Pengguna</button>
    </form>
</div></div>
<div class="card"><div class="card-head">Daftar Pengguna</div><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
        <tr style="<?= $u['aktif']?'':'opacity:.5' ?>">
            <td><b><?= e($u['nama']) ?></b><?= $u['kru_nama']?'<br><span class="muted">kru: '.e($u['kru_nama']).'</span>':'' ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="tag"><?= e($u['role']) ?></span></td>
            <td><span class="badge <?= $u['aktif']?'ok':'muted' ?>"><?= $u['aktif']?'aktif':'nonaktif' ?></span></td>
            <td class="right"><form method="post" action="/pengguna/<?= $u['id'] ?>/toggle" style="margin:0"><?= csrf_field() ?><button class="btn sm"><?= $u['aktif']?'Nonaktifkan':'Aktifkan' ?></button></form></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>
</div>
