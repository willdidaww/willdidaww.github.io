<div class="page-head"><div><h1>Dokumen Kendaraan</h1><div class="sub">STNK, KIR, izin trayek, asuransi, kartu pengawasan — reminder jatuh tempo</div></div></div>
<div class="grid grid-2">
    <div class="card"><div class="card-head">Tambah Dokumen</div><div class="card-body">
        <form method="post" action="/dokumen" enctype="multipart/form-data"><?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group"><label>Bus <span class="req">*</span></label><select name="bus_id" required><option value="">—</option><?php foreach ($buses as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['nopol']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Jenis <span class="req">*</span></label>
                    <select name="jenis" required><?php foreach (['stnk'=>'STNK','kir'=>'KIR','izin_trayek'=>'Izin Trayek','asuransi'=>'Asuransi','kartu_pengawasan'=>'Kartu Pengawasan','lain'=>'Lain'] as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-group"><label>Nomor</label><input name="nomor"></div>
            <div class="form-row">
                <div class="form-group"><label>Berlaku Dari</label><input type="date" name="berlaku_dari"></div>
                <div class="form-group"><label>Jatuh Tempo</label><input type="date" name="jatuh_tempo"></div>
            </div>
            <div class="form-group"><label>Scan Dokumen</label><input type="file" name="lampiran" accept="image/*,application/pdf"></div>
            <button class="btn primary">Simpan</button>
        </form>
    </div></div>
    <div class="card"><div class="card-head">Daftar Dokumen</div><div class="card-body"><div class="table-wrap"><table>
        <thead><tr><th>Bus</th><th>Jenis</th><th>Nomor</th><th>Jatuh Tempo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $d): ?>
            <tr>
                <td><?= e($d['nopol']) ?></td>
                <td><?= e(strtoupper(str_replace('_',' ',$d['jenis']))) ?></td>
                <td><?= e($d['nomor']) ?><?php if ($d['lampiran']): ?> <a href="<?= e($d['lampiran']) ?>" target="_blank">📎</a><?php endif; ?></td>
                <td><?php if ($d['jatuh_tempo']): ?><span class="badge <?= $d['expired']?'bad':($d['warning']?'warn':'ok') ?>"><?= tgl($d['jatuh_tempo']) ?></span><?php else: ?>-<?php endif; ?></td>
                <td class="right"><form method="post" action="/dokumen/<?= $d['id'] ?>" onsubmit="return confirm('Hapus?')" style="margin:0"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn sm bad">✕</button></form></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">Belum ada dokumen.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div></div>
</div>
