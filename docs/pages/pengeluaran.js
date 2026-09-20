// Pengeluaran: daftar + input (rit/non-rit), form BBM dinamis, deteksi anomali vs standar.
import { sb, must, e, state, tgl, rupiah, badgeClass, toast } from '../lib.js';
import { shell, pageHead } from '../layout.js';
import { openModal } from '../modal.js';

export async function renderPengeluaran() {
  const content = shell('#/pengeluaran');
  content.innerHTML = '<div class="empty">Memuat…</div>';

  const params = new URLSearchParams((location.hash.split('?')[1]) || '');
  const status = params.get('status') || '';
  let q = sb.from('pengeluaran')
    .select('*,kategori:kategori_id(nama),rit:rit_id(kode)')
    .order('dibuat_pada', { ascending: false }).limit(100);
  if (status) q = q.eq('status', status);
  const rows = await must(q);
  const kategori = await must(sb.from('kategori_biaya').select('*').eq('aktif', true).order('nama'));

  content.innerHTML = `
    ${pageHead('Pengeluaran', 'Termasuk biaya non-rit', '<button class="btn primary" id="btnAdd">+ Input Pengeluaran</button>')}
    <div class="card"><div class="card-body">
      <form class="filters" id="f"><div class="form-group"><label>Status</label>
        <select name="status"><option value="">Semua</option>
        ${['menunggu', 'disetujui', 'ditolak'].map((s) => `<option value="${s}" ${status === s ? 'selected' : ''}>${s}</option>`).join('')}</select></div>
        <button class="btn">Filter</button></form>
      <div class="table-wrap"><table>
        <thead><tr><th>Tanggal</th><th>Kategori</th><th>Rit</th><th class="num">Nominal</th><th>Status</th></tr></thead>
        <tbody>${rows.map((r) => `<tr>
          <td>${tgl(r.tanggal)}</td>
          <td>${e(r.kategori?.nama)}${r.flag_anomali ? ' <span class="badge bad">anomali</span>' : ''}</td>
          <td>${r.rit ? `<a href="#/rit-detail/${r.rit_id}">${e(r.rit.kode)}</a>` : '<span class="tag">non-rit</span>'}</td>
          <td class="num mono">${rupiah(r.nominal)}</td>
          <td><span class="badge ${badgeClass(r.status)}">${e(r.status)}</span></td></tr>`).join('')
          || '<tr><td colspan="5" class="empty">Tidak ada data.</td></tr>'}</tbody>
      </table></div>
    </div></div>`;

  content.querySelector('#f').addEventListener('submit', (ev) => {
    ev.preventDefault();
    location.hash = '#/pengeluaran?' + new URLSearchParams({ status: new FormData(ev.target).get('status') || '' });
    renderPengeluaran();
  });
  content.querySelector('#btnAdd').addEventListener('click', () =>
    pengeluaranForm({ kategori, allowNonRit: true, onDone: () => renderPengeluaran() }));
}

/**
 * Form input pengeluaran. Dipakai dari detail rit (rit terkunci) & halaman pengeluaran (bebas rit).
 * opts: { rit?, kategori, allowNonRit?, onDone }
 */
export async function pengeluaranForm({ rit = null, kategori, allowNonRit = false, onDone }) {
  let ritOptions = '';
  if (!rit && allowNonRit) {
    const rits = await must(sb.from('rit').select('id,kode').in('status', ['rencana', 'berjalan']).order('tanggal', { ascending: false }));
    ritOptions = `<div class="form-group"><label>Rit (kosongkan = biaya non-rit)</label>
      <select name="rit_id"><option value="">— non-rit —</option>
      ${rits.map((r) => `<option value="${r.id}">${e(r.kode)}</option>`).join('')}</select></div>`;
  }

  openModal('Input Pengeluaran', `
    ${rit ? `<p class="hint">Rit <b>${e(rit.kode)}</b></p>` : ritOptions}
    <div class="form-row">
      <div class="form-group"><label>Kategori <span class="req">*</span></label>
        <select name="kategori_id" id="katSel" required><option value="">—</option>
        ${kategori.map((k) => `<option value="${k.id}" data-tipe="${e(k.tipe)}" data-bukti="${k.wajib_bukti ? 1 : 0}">${e(k.nama)}${k.wajib_bukti ? ' *' : ''}</option>`).join('')}</select></div>
      <div class="form-group"><label>Nominal <span class="req">*</span></label><input type="number" name="nominal" min="1" required></div>
    </div>
    <div id="bbmFields" style="display:none">
      <div class="form-row">
        <div class="form-group"><label>Odometer</label><input type="number" name="odometer"></div>
        <div class="form-group"><label>Liter</label><input type="number" step="0.01" name="liter"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Harga/Liter</label><input type="number" name="harga_liter"></div>
        <div class="form-group"><label style="margin-top:26px"><input type="checkbox" name="tangki_penuh" value="1" style="width:auto"> Tangki penuh</label></div>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Tanggal <span class="req">*</span></label><input type="date" name="tanggal" required value="${new Date().toISOString().slice(0, 10)}"></div>
      <div class="form-group"><label>Keterangan</label><input name="keterangan"></div>
    </div>
    <div class="form-group"><label>Foto Nota <span id="reqBukti" style="display:none;color:var(--bad)">(wajib)</span></label><input type="file" name="foto" accept="image/*"></div>
  `, async (data, close) => {
    const form = document.getElementById('modalForm');
    const opt = form.querySelector('#katSel').selectedOptions[0];
    const wajibBukti = opt?.dataset.bukti === '1';
    const fileInput = form.querySelector('[name=foto]');
    const file = fileInput.files[0];

    if (Number(data.nominal) <= 0) throw new Error('Nominal harus > 0');
    if (new Date(data.tanggal) > new Date()) throw new Error('Tanggal tidak boleh di masa depan');
    if (wajibBukti && !file) throw new Error('Kategori ini wajib foto nota');

    const ritId = rit ? rit.id : (data.rit_id ? Number(data.rit_id) : null);
    const kategoriId = Number(data.kategori_id);

    // Upload foto ke Supabase Storage (bucket 'nota') bila ada
    let lampiran = null;
    if (file) lampiran = await uploadNota(file);

    // Deteksi anomali vs standar rute
    let flag = false;
    if (ritId) flag = await cekAnomali(ritId, kategoriId, Number(data.nominal));

    const payload = {
      pool_id: state.profile.pool_id, rit_id: ritId, kategori_id: kategoriId,
      nominal: Number(data.nominal), tanggal: data.tanggal, keterangan: data.keterangan || null,
      odometer: data.odometer ? Number(data.odometer) : null,
      liter: data.liter ? Number(data.liter) : null,
      harga_liter: data.harga_liter ? Number(data.harga_liter) : null,
      tangki_penuh: data.tangki_penuh ? true : null,
      lampiran, sumber: 'admin', status: 'menunggu', flag_anomali: flag,
      dibuat_oleh: state.user.id,
    };
    await must(sb.from('pengeluaran').insert(payload));
    toast(flag ? 'Tersimpan & ditandai ANOMALI (di atas toleransi standar).' : 'Pengeluaran tersimpan, menunggu approval.', flag ? 'warn' : 'success');
    close(); onDone && onDone();
  });

  // Toggle field BBM + indikator wajib bukti
  const sel = document.getElementById('katSel');
  const bbm = document.getElementById('bbmFields');
  const req = document.getElementById('reqBukti');
  sel.addEventListener('change', () => {
    const o = sel.selectedOptions[0];
    bbm.style.display = o?.dataset.tipe === 'bbm' ? 'block' : 'none';
    req.style.display = o?.dataset.bukti === '1' ? 'inline' : 'none';
  });
}

async function cekAnomali(ritId, kategoriId, nominal) {
  const rit = await must(sb.from('rit').select('rute_id').eq('id', ritId).single());
  const std = await must(sb.from('standar_biaya_rute').select('nominal_standar,toleransi_pct')
    .eq('rute_id', rit.rute_id).eq('kategori_id', kategoriId).is('berlaku_sampai', null).limit(1));
  if (!std.length) return false;
  const batas = Number(std[0].nominal_standar) * (1 + Number(std[0].toleransi_pct) / 100);
  return nominal > batas;
}

async function uploadNota(file) {
  const ext = (file.name.split('.').pop() || 'jpg').toLowerCase();
  const path = `${state.user.id}/${Date.now()}-${Math.random().toString(36).slice(2, 8)}.${ext}`;
  const { error } = await sb.storage.from('nota').upload(path, file, { upsert: false });
  if (error) throw new Error('Upload nota gagal: ' + error.message + ' (pastikan bucket "nota" ada — lihat SETUP)');
  const { data } = sb.storage.from('nota').getPublicUrl(path);
  return data.publicUrl;
}
