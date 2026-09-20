<div class="page-head"><div><h1>Persetujuan</h1><div class="sub">Pengeluaran menunggu approval lintas rit</div></div></div>
<div class="card"><div class="card-body">
    <form class="filters" method="get">
        <div class="form-group"><label>Kategori</label>
            <select name="kategori_id"><option value="">Semua</option>
                <?php foreach ($kategori as $k): ?><option value="<?= $k['id'] ?>" <?= (string)$kategoriId===(string)$k['id']?'selected':'' ?>><?= e($k['nama']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label>Nominal Minimum</label><input type="number" name="min_nominal" value="<?= $minNominal?:'' ?>"></div>
        <div class="form-group"><label>&nbsp;</label><label style="font-weight:400"><input type="checkbox" name="anomali" value="1" <?= $onlyAnomali?'checked':'' ?> style="width:auto"> Hanya anomali</label></div>
        <button class="btn">Filter</button>
        <a class="btn ghost" href="/approval">Reset</a>
    </form>

    <form method="post" action="/approval/batch" id="batchForm">
        <?= csrf_field() ?>
        <div class="btn-row" style="margin-bottom:12px">
            <button class="btn ok sm" name="aksi" value="approve" onclick="return confirm('Setujui item terpilih?')">✓ Setujui Terpilih</button>
            <button class="btn bad sm" type="button" onclick="rejectBatch()">✕ Tolak Terpilih</button>
            <input type="hidden" name="alasan" id="batchAlasan">
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.chk').forEach(c=>c.checked=this.checked)"></th>
                <th>Rit / Bus</th><th>Kategori</th><th class="num">Nominal</th><th class="num">Standar</th><th>Flag</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $batas = $r['nominal_standar'] ? (float)$r['nominal_standar']*(1+(float)$r['toleransi_pct']/100) : null; ?>
                <tr>
                    <td><input class="chk" type="checkbox" name="ids[]" value="<?= $r['id'] ?>"></td>
                    <td><?= $r['rit_kode']?'<a href="/rit/'.$r['rit_id'].'">'.e($r['rit_kode']).'</a>':'<span class="tag">non-rit</span>' ?><br><span class="muted"><?= e($r['nopol']) ?></span></td>
                    <td><?= e($r['kategori']) ?><br><span class="muted" style="font-size:11px"><?= e($r['keterangan']) ?></span></td>
                    <td class="num mono"><?= rupiah($r['nominal']) ?><?php if ($r['lampiran']): ?><br><a href="<?= e($r['lampiran']) ?>" target="_blank" style="font-size:11px">📎 nota</a><?php endif; ?></td>
                    <td class="num mono muted"><?= $batas!==null?rupiah($batas).' (batas)':'-' ?></td>
                    <td><?= $r['flag_anomali']?'<span class="badge bad">anomali</span>':'<span class="badge muted">normal</span>' ?></td>
                    <td class="right"><div class="btn-row" style="justify-content:flex-end">
                        <form method="post" action="/approval/<?= $r['id'] ?>/approve" style="margin:0"><?= csrf_field() ?><button class="btn sm ok">✓</button></form>
                        <button type="button" class="btn sm bad" onclick="rejectOne(<?= $r['id'] ?>)">✕</button>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">Tidak ada yang menunggu approval. 🎉</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </form>
</div></div>

<!-- form reject tunggal tersembunyi -->
<form method="post" id="rejectForm" style="display:none"><?= csrf_field() ?><input name="alasan" id="rejectAlasan"></form>
<script>
function rejectOne(id){
  const a = prompt('Alasan penolakan (wajib):');
  if(!a) return;
  const f = document.getElementById('rejectForm');
  f.action = '/approval/'+id+'/reject';
  document.getElementById('rejectAlasan').value = a;
  f.submit();
}
function rejectBatch(){
  const a = prompt('Alasan penolakan batch (wajib):');
  if(!a) return;
  document.getElementById('batchAlasan').value = a;
  const f = document.getElementById('batchForm');
  const hidden = document.createElement('input'); hidden.type='hidden'; hidden.name='aksi'; hidden.value='reject';
  f.appendChild(hidden); f.submit();
}
</script>
