<div class="page-head"><div><h1>Manajemen Ban</h1><div class="sub">Cegah dua ban aktif di posisi sama (dicek UI + constraint DB)</div></div></div>
<div class="grid grid-2">
    <div class="card"><div class="card-head">Tambah Ban</div><div class="card-body">
        <form method="post" action="/ban"><?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group"><label>Kode Seri <span class="req">*</span></label><input name="kode_seri" required></div>
                <div class="form-group"><label>Merk</label><input name="merk"></div>
            </div>
            <button class="btn primary">Tambah</button>
        </form>
        <hr style="margin:16px 0;border:none;border-top:1px solid var(--line)">
        <div class="card-head" style="padding:0 0 10px">Pasang Ban</div>
        <form method="post" action="/ban/pasang"><?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group"><label>Ban <span class="req">*</span></label>
                    <select name="ban_id" required><option value="">—</option><?php foreach ($banBebas as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['kode_seri']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Bus <span class="req">*</span></label>
                    <select name="bus_id" required><option value="">—</option><?php foreach ($buses as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['nopol']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Posisi <span class="req">*</span></label><input name="posisi" placeholder="FL, FR, RL1..." required></div>
                <div class="form-group"><label>Odometer Pasang</label><input type="number" name="odometer_pasang"></div>
            </div>
            <button class="btn primary">Pasang</button>
        </form>
    </div></div>
    <div class="card"><div class="card-head">Daftar Ban</div><div class="card-body"><div class="table-wrap"><table>
        <thead><tr><th>Kode</th><th>Merk</th><th>Terpasang</th></tr></thead>
        <tbody>
        <?php foreach ($ban as $b): ?>
            <tr><td><?= e($b['kode_seri']) ?></td><td><?= e($b['merk']) ?></td>
                <td><?= $b['terpasang_bus']?'<span class="badge ok">'.e($b['nopol']).' @ '.e($b['posisi']).'</span>':'<span class="badge muted">bebas</span>' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$ban): ?><tr><td colspan="3" class="empty">Belum ada ban.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div></div>
</div>
<div class="card"><div class="card-head">Riwayat Pemasangan</div><div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Ban</th><th>Bus</th><th>Posisi</th><th>Pasang</th><th>Lepas</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pemasangan as $p): ?>
        <tr>
            <td><?= e($p['kode_seri']) ?></td><td><?= e($p['nopol']) ?></td><td><?= e($p['posisi']) ?></td>
            <td><?= tgl($p['tanggal_pasang']) ?></td>
            <td><?= $p['tanggal_lepas']?tgl($p['tanggal_lepas']):'<span class="badge ok">terpasang</span>' ?></td>
            <td class="right"><?php if (!$p['tanggal_lepas']): ?>
                <form method="post" action="/ban/<?= $p['id'] ?>/lepas" style="margin:0"><?= csrf_field() ?><button class="btn sm">Lepas</button></form>
            <?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$pemasangan): ?><tr><td colspan="6" class="empty">Belum ada riwayat.</td></tr><?php endif; ?>
    </tbody>
</table></div></div></div>
