// Rit: daftar (filter+cari) + buat (validasi bus tidak dobel + estimasi UJ) + detail (mulai/tutup/batal, pengeluaran inline).
import { sb, must, e, state, tgl, rupiah, badgeClass, toast, isManager } from '../lib.js';
import { shell, pageHead } from '../layout.js';
import { openModal } from '../modal.js';
import { pengeluaranForm } from './pengeluaran.js';

export async function renderRitList() {
  const content = shell('#/rit');
  content.innerHTML = '<div class="empty">Memuat…</div>';

  const params = new URLSearchParams((location.hash.split('?')[1]) || '');
  const status = params.get('status') || '';
  const q = params.get('q') || '';

  let query = sb.from('rit')
    .select('id,kode,tanggal,status,bus:bus_id(nopol),rute:rute_id(asal,tujuan)')
    .order('tanggal', { ascending: false }).limit(100);
  if (status) query = query.eq('status', status);
  const rows = await must(query);
  const filtered = q ? rows.filter((r) => (r.kode + ' ' + (r.bus?.nopol || '')).toLowerCase().includes(q.toLowerCase())) : rows;

  content.innerHTML = `
    ${pageHead('Rit', `${filtered.length} rit`, '<button class="btn primary" id="btnAdd">+ Buat Rit</button>')}
    <div class="card"><div class="card-body">
      <form class="filters" id="filterForm">
        <div class="form-group"><label>Cari</label><input name="q" value="${e(q)}" placeholder="Kode / nopol"></div>
        <div class="form-group"><label>Status</label><select name="status">
          <option value="">Semua</option>
          ${['rencana', 'berjalan', 'selesai', 'batal'].map((s) => `<option value="${s}" ${status === s ? 'selected' : ''}>${s}</option>`).join('')}
        </select></div>
        <button class="btn">Filter</button>
      </form>
      <div class="table-wrap"><table>
        <thead><tr><th>Kode</th><th>Bus</th><th>Rute</th><th>Tanggal</th><th>Status</th></tr></thead>
        <tbody>
          ${filtered.map((r) => `<tr>
            <td><a href="#/rit-detail/${r.id}"><b>${e(r.kode)}</b></a></td>
            <td>${e(r.bus?.nopol)}</td><td>${e(r.rute?.asal)}→${e(r.rute?.tujuan)}</td>
            <td>${tgl(r.tanggal)}</td><td><span class="badge ${badgeClass(r.status)}">${e(r.status)}</span></td></tr>`).join('')
            || '<tr><td colspan="5" class="empty">Tidak ada rit.</td></tr>'}
        </tbody>
      </table></div>
    </div></div>`;

  content.querySelector('#filterForm').addEventListener('submit', (ev) => {
    ev.preventDefault();
    const fd = new FormData(ev.target);
    location.hash = '#/rit?' + new URLSearchParams({ q: fd.get('q') || '', status: fd.get('status') || '' });
    renderRitList();
  });
  content.querySelector('#btnAdd').addEventListener('click', () => ritForm());
}

async function ritForm() {
  // Bus tersedia: aktif & tidak sedang rit aktif
  const aktifRit = await must(sb.from('rit').select('bus_id').in('status', ['rencana', 'berjalan']));
  const busy = new Set(aktifRit.map((r) => r.bus_id));
  const buses = (await must(sb.from('bus').select('id,nopol,kelas').eq('status', 'aktif').order('nopol'))).filter((b) => !busy.has(b.id));
  const rute = await must(sb.from('rute').select('id,asal,tujuan').eq('aktif', true).order('asal'));
  const kru = await must(sb.from('kru').select('id,nama,posisi').eq('aktif', true).order('nama'));

  openModal('Buat Rit', `
    <div class="form-group"><label>Bus <span class="req">*</span> <span class="hint">(aktif & tidak sedang jalan)</span></label>
      <select name="bus_id" required><option value="">— pilih —</option>
      ${buses.map((b) => `<option value="${b.id}" data-kelas="${e(b.kelas)}">${e(b.nopol)} (${e(b.kelas)})</option>`).join('')}</select>
      ${buses.length ? '' : '<p class="hint" style="color:var(--bad)">Tidak ada bus tersedia.</p>'}</div>
    <div class="form-row">
      <div class="form-group"><label>Rute <span class="req">*</span></label><select name="rute_id" required>
        ${rute.map((r) => `<option value="${r.id}">${e(r.asal)}→${e(r.tujuan)}</option>`).join('')}</select></div>
      <div class="form-group"><label>Tanggal <span class="req">*</span></label><input type="date" name="tanggal" required value="${new Date().toISOString().slice(0, 10)}"></div>
    </div>
    <div class="form-group"><label>Kru</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
      ${kru.map((k) => `<label style="font-weight:400"><input type="checkbox" name="kru" value="${k.id}" data-posisi="${e(k.posisi)}" style="width:auto"> ${e(k.nama)} <span class="tag">${e(k.posisi)}</span></label>`).join('')}
    </div></div>
    <div class="form-group"><label>Catatan</label><input name="catatan"></div>
  `, async (data, close) => {
    const form = document.getElementById('modalForm');
    const busSel = form.querySelector('[name=bus_id]');
    const kelas = busSel.options[busSel.selectedIndex]?.dataset.kelas || null;

    // Estimasi UJ dari standar rute (kelas cocok / umum)
    const std = await must(sb.from('standar_biaya_rute').select('nominal_standar,kelas_bus')
      .eq('rute_id', Number(data.rute_id)).is('berlaku_sampai', null));
    const estimasi = std.filter((s) => !s.kelas_bus || s.kelas_bus === kelas)
      .reduce((sum, s) => sum + Number(s.nominal_standar), 0);

    const kode = 'RIT-' + new Date().toISOString().slice(2, 10).replace(/-/g, '') + '-' +
      Math.random().toString(36).slice(2, 6).toUpperCase();

    const rit = await must(sb.from('rit').insert({
      pool_id: state.profile.pool_id, kode, bus_id: Number(data.bus_id), rute_id: Number(data.rute_id),
      tanggal: data.tanggal, status: 'rencana', estimasi_uang_jalan: estimasi,
      dibuat_oleh: state.user.id, catatan: data.catatan || null,
    }).select().single());

    const chosen = Array.from(form.querySelectorAll('[name=kru]:checked'));
    if (chosen.length) {
      await must(sb.from('rit_kru').insert(chosen.map((c) => ({
        rit_id: rit.id, kru_id: Number(c.value), peran: c.dataset.posisi || 'sopir',
      }))));
    }
    toast('Rit ' + kode + ' dibuat. Estimasi UJ: ' + rupiah(estimasi));
    close();
    location.hash = '#/rit-detail/' + rit.id;
  });
}

export async function renderRitDetail(id) {
  const content = shell('#/rit');
  content.innerHTML = '<div class="empty">Memuat…</div>';
  id = Number(id);

  const rit = await must(sb.from('rit')
    .select('*,bus:bus_id(nopol,kelas),rute:rute_id(asal,tujuan,jarak_km)').eq('id', id).single());
  const kru = await must(sb.from('rit_kru').select('peran,kru:kru_id(nama)').eq('rit_id', id));
  const peng = await must(sb.from('pengeluaran')
    .select('*,kategori:kategori_id(nama,wajib_bukti)').eq('rit_id', id).order('dibuat_pada', { ascending: false }));
  const kategori = await must(sb.from('kategori_biaya').select('*').eq('aktif', true).order('tipe').order('nama'));

  const totalBiaya = peng.filter((p) => p.status === 'disetujui').reduce((s, p) => s + Number(p.nominal), 0);
  const locked = ['selesai', 'batal'].includes(rit.status);

  content.innerHTML = `
    ${pageHead('Rit ' + rit.kode, `${e(rit.bus?.nopol)} · ${e(rit.rute?.asal)} → ${e(rit.rute?.tujuan)} · ${tgl(rit.tanggal)}`, '<a class="btn" href="#/rit">← Daftar</a>')}
    <div class="grid grid-4">
      <div class="stat"><div class="label">Status</div><div class="value sm"><span class="badge ${badgeClass(rit.status)}">${e(rit.status)}</span></div></div>
      <div class="stat"><div class="label">Estimasi UJ</div><div class="value sm">${rupiah(rit.estimasi_uang_jalan)}</div></div>
      <div class="stat"><div class="label">Biaya Disetujui</div><div class="value sm">${rupiah(totalBiaya)}</div></div>
      <div class="stat"><div class="label">Km</div><div class="value sm">${rit.km_awal ?? '-'} → ${rit.km_akhir ?? '-'}</div></div>
    </div>
    <div class="card"><div class="card-body"><div class="btn-row" id="ritActions"></div>
      ${kru.length ? `<div class="muted" style="margin-top:10px">Kru: ${kru.map((k) => e(k.kru?.nama) + ' (' + e(k.peran) + ')').join(', ')}</div>` : ''}
    </div></div>
    <div class="card">
      <div class="card-head">Pengeluaran (${peng.length}) ${locked ? '' : '<button class="btn sm primary" id="btnPeng">+ Input Pengeluaran</button>'}</div>
      <div class="card-body"><div class="timeline">
        ${peng.map((p) => `<div class="item">
          <div><b>${e(p.kategori?.nama)}</b> — <span class="mono">${rupiah(p.nominal)}</span>
            ${p.flag_anomali ? '<span class="badge bad">anomali</span>' : ''}
            <span class="badge ${badgeClass(p.status)}">${e(p.status)}</span>
            <span class="tag">${e(p.sumber)}</span></div>
          <div class="muted" style="font-size:12px">${tgl(p.tanggal)} · ${e(p.keterangan || '')}
            ${p.liter ? ` · ${p.liter} L @ ${rupiah(p.harga_liter)}` : ''}
            ${p.lampiran ? ` · <a href="${e(p.lampiran)}" target="_blank">📎 nota</a>` : ''}
            ${p.alasan_reject ? ` · <span style="color:var(--bad)">ditolak: ${e(p.alasan_reject)}</span>` : ''}</div>
        </div>`).join('') || '<div class="muted">Belum ada pengeluaran.</div>'}
      </div></div>
    </div>`;

  // Aksi status
  const actions = content.querySelector('#ritActions');
  if (rit.status === 'rencana') {
    actions.innerHTML = `
      <button class="btn ok" id="btnMulai">Mulai (Berangkat)</button>
      <button class="btn bad" id="btnBatal">Batalkan</button>`;
    actions.querySelector('#btnMulai').addEventListener('click', async () => {
      const km = prompt('Km awal saat berangkat:'); if (km === null) return;
      await must(sb.from('rit').update({ status: 'berjalan', km_awal: Number(km) || null }).eq('id', id));
      toast('Rit dimulai.'); renderRitDetail(id);
    });
    actions.querySelector('#btnBatal').addEventListener('click', async () => {
      if (!confirm('Batalkan rit?')) return;
      await must(sb.from('rit').update({ status: 'batal' }).eq('id', id));
      toast('Rit dibatalkan.'); location.hash = '#/rit';
    });
  } else if (rit.status === 'berjalan') {
    actions.innerHTML = `<button class="btn primary" id="btnTutup">Tutup Rit</button>
      <span class="hint">Tutup ditolak bila masih ada pengeluaran menunggu approval.</span>`;
    actions.querySelector('#btnTutup').addEventListener('click', async () => {
      const menunggu = peng.filter((p) => p.status === 'menunggu').length;
      if (menunggu) { toast(`Masih ada ${menunggu} pengeluaran menunggu approval.`, 'error'); return; }
      const km = prompt('Km akhir saat tiba:', rit.km_akhir || ''); if (km === null) return;
      const kmAkhir = Number(km) || rit.km_akhir;
      await must(sb.from('rit').update({
        status: 'selesai', km_akhir: kmAkhir, ditutup_oleh: state.user.id, ditutup_pada: new Date().toISOString(),
      }).eq('id', id));
      if (kmAkhir && kmAkhir > (rit.bus?.odometer || 0)) {
        await sb.from('bus').update({ odometer: kmAkhir }).eq('id', rit.bus_id).gt('odometer', -1);
      }
      toast('Rit ditutup.'); renderRitDetail(id);
    });
  } else {
    actions.innerHTML = '<span class="badge muted">Rit terkunci — transaksi tidak dapat diubah</span>';
  }

  const btnPeng = content.querySelector('#btnPeng');
  if (btnPeng) btnPeng.addEventListener('click', () => pengeluaranForm({ rit, kategori, onDone: () => renderRitDetail(id) }));
}
