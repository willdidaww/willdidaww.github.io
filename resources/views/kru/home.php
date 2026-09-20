<div id="queueBanner" class="alert warn" style="display:none"></div>

<?php foreach ($rits as $r): $locked = in_array($r['status'], ['selesai','batal'], true); ?>
    <div class="rit-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <h3><?= e($r['kode']) ?> <span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></h3>
                <div class="muted" style="font-size:13px"><?= e($r['nopol']) ?> · <?= e($r['asal']) ?> → <?= e($r['tujuan']) ?></div>
                <div class="muted" style="font-size:12px"><?= tgl($r['tanggal']) ?></div>
            </div>
            <div class="right">
                <div class="muted" style="font-size:11px">Total pengeluaran</div>
                <b class="mono"><?= rupiah($r['total']) ?></b>
            </div>
        </div>
        <?php if (!$locked): ?>
        <details style="margin-top:10px">
            <summary class="btn sm primary" style="display:inline-block">+ Input Pengeluaran</summary>
            <form class="pengForm" data-rit="<?= $r['id'] ?>" style="margin-top:12px">
                <div class="form-group"><label>Kategori</label>
                    <select name="kategori_id" required>
                        <?php foreach ($kategori as $k): ?><option value="<?= $k['id'] ?>" data-tipe="<?= e($k['tipe']) ?>" data-bukti="<?= $k['wajib_bukti'] ?>"><?= e($k['nama']) ?><?= $k['wajib_bukti']?' *':'' ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Nominal</label><input type="number" name="nominal" min="1" required></div>
                <div class="bbmFields" style="display:none">
                    <div class="form-row">
                        <div class="form-group"><label>Odometer</label><input type="number" name="odometer"></div>
                        <div class="form-group"><label>Liter</label><input type="number" step="0.01" name="liter"></div>
                    </div>
                </div>
                <div class="form-group"><label>Keterangan</label><input name="keterangan"></div>
                <div class="form-group"><label>Foto Nota (dikompres otomatis)</label><input type="file" name="foto" accept="image/*" capture="environment"></div>
                <button class="btn primary" type="submit">Kirim</button>
                <div class="status muted" style="font-size:12px;margin-top:6px"></div>
            </form>
        </details>
        <?php else: ?>
            <div class="muted" style="font-size:12px;margin-top:8px">Rit terkunci.</div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (!$rits): ?><div class="empty">Belum ada rit untuk Anda.</div><?php endif; ?>

<script>window.CSRF = <?= json_encode($_csrf) ?>;</script>
<script src="/assets/kru.js"></script>
