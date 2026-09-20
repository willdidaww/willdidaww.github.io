<div class="page-head"><div><h1>Catat Perawatan</h1><div class="sub">Item sparepart/jasa dihitung subtotal otomatis</div></div><a class="btn" href="/perawatan">← Kembali</a></div>
<div class="card" style="max-width:820px"><div class="card-body">
    <form method="post" action="/perawatan">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group"><label>Bus <span class="req">*</span></label>
                <select name="bus_id" required><option value="">—</option><?php foreach ($buses as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['nopol']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Jenis</label>
                <select name="jenis"><?php foreach (['servis'=>'Servis','perbaikan'=>'Perbaikan','ganti_ban'=>'Ganti Ban','lain'=>'Lain'] as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Tanggal Masuk <span class="req">*</span></label><input type="date" name="tanggal_masuk" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label>Tanggal Keluar</label><input type="date" name="tanggal_keluar"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Vendor/Bengkel</label><select name="vendor_id"><option value="">—</option><?php foreach ($vendor as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['nama']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Odometer</label><input type="number" name="odometer"></div>
        </div>
        <div class="form-group"><label>Keluhan</label><input name="keluhan"></div>
        <div class="form-group"><label>Tindakan</label><input name="tindakan"></div>
        <div class="form-group"><label>Odometer Servis Berikutnya (reminder)</label><input type="number" name="odometer_servis_berikutnya"></div>

        <label>Item (sparepart / jasa)</label>
        <table id="itemTable"><thead><tr><th>Jenis</th><th>Nama</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
            <tbody></tbody></table>
        <button type="button" class="btn sm" onclick="addRow()">+ Baris Item</button>
        <div style="text-align:right;margin:12px 0;font-size:16px">Total: <b id="total">Rp 0</b></div>
        <button class="btn primary">Simpan Perawatan</button>
    </form>
</div></div>
<script>
function fmt(n){return 'Rp '+Number(n||0).toLocaleString('id-ID');}
function addRow(){
  const tb = document.querySelector('#itemTable tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="item_jenis[]"><option value="sparepart">Sparepart</option><option value="jasa">Jasa</option></select></td>
    <td><input name="item_nama[]"></td>
    <td><input type="number" name="item_qty[]" value="1" style="width:70px" oninput="calc()"></td>
    <td><input type="number" name="item_harga[]" value="0" oninput="calc()"></td>
    <td class="sub mono">Rp 0</td>`;
  tb.appendChild(tr); calc();
}
function calc(){
  let total=0;
  document.querySelectorAll('#itemTable tbody tr').forEach(tr=>{
    const q=+tr.querySelector('[name="item_qty[]"]').value||0;
    const h=+tr.querySelector('[name="item_harga[]"]').value||0;
    const s=q*h; total+=s;
    tr.querySelector('.sub').textContent=fmt(s);
  });
  document.getElementById('total').textContent=fmt(total);
}
addRow();
</script>
