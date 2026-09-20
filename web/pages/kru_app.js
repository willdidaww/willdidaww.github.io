// Area Kru: lihat rit miliknya (RLS membatasi) + input pengeluaran + foto nota.
import { sb, must, e, state, tgl, rupiah, badgeClass, toast } from '../lib.js';
import { openModal } from '../modal.js';

export async function renderKruApp() {
  const app = document.getElementById('app');
  // RLS: kru hanya melihat rit yang ditugaskan padanya.
  const rits = await must(sb.from('rit')
    .select('*,bus:bus_id(nopol),rute:rute_id(asal,tujuan)')
    .in('status', ['rencana', 'berjalan', 'selesai'])
    .order('tanggal', { ascending: false }));
  const kategori = await must(sb.from('kategori_biaya').select('*').eq('aktif', true).order('nama'));

  app.innerHTML = `
    <div class="kru-top" style="background:#0f172a;color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10">
      <div><b>🚌 Rit Saya</b><br><span style="font-size:12px;color:#94a3b8">${e(state.profile?.nama || '')}</span></div>
      <button class="btn sm" id="btnLogout">Keluar</button>
    </div>
    <div style="padding:14px;max-width:560px;margin:0 auto">
      ${rits.map((r) => {
        const locked = ['selesai', 'batal'].includes(r.status);
        return `<div class="card"><div class="card-body">
          <div style="display:flex;justify-content:space-between">
            <div><b>${e(r.kode)}</b> <span class="badge ${badgeClass(r.status)}">${e(r.status)}</span>
              <div class="muted" style="font-size:13px">${e(r.bus?.nopol)} · ${e(r.rute?.asal)} → ${e(r.rute?.tujuan)}</div>
              <div class="muted" style="font-size:12px">${tgl(r.tanggal)}</div></div>
          </div>
          ${locked ? '<div class="muted" style="font-size:12px;margin-top:8px">Rit terkunci.</div>'
            : `<button class="btn sm primary" data-peng="${r.id}" style="margin-top:10px">+ Input Pengeluaran</button>`}
        </div></div>`;
      }).join('') || '<div class="empty">Belum ada rit untuk Anda.</div>'}
    </div>`;

  app.querySelector('#btnLogout').addEventListener('click', async () => {
    await sb.auth.signOut(); location.hash = '#/login';
  });
  app.querySelectorAll('[data-peng]').forEach((b) =>
    b.addEventListener('click', () => inputForm(Number(b.dataset.peng), kategori)));
}

function inputForm(ritId, kategori) {
  openModal('Input Pengeluaran', `
    <div class="form-group"><label>Kategori</label>
      <select name="kategori_id" id="katSel" required>
      ${kategori.map((k) => `<option value="${k.id}" data-tipe="${e(k.tipe)}" data-bukti="${k.wajib_bukti ? 1 : 0}">${e(k.nama)}${k.wajib_bukti ? ' *' : ''}</option>`).join('')}</select></div>
    <div class="form-group"><label>Nominal</label><input type="number" name="nominal" min="1" required></div>
    <div id="bbmFields" style="display:none">
      <div class="form-row">
        <div class="form-group"><label>Odometer</label><input type="number" name="odometer"></div>
        <div class="form-group"><label>Liter</label><input type="number" step="0.01" name="liter"></div>
      </div>
    </div>
    <div class="form-group"><label>Keterangan</label><input name="keterangan"></div>
    <div class="form-group"><label>Foto Nota <span id="reqBukti" style="display:none;color:var(--bad)">(wajib)</span></label><input type="file" name="foto" accept="image/*" capture="environment"></div>
  `, async (data, close) => {
    const form = document.getElementById('modalForm');
    const opt = form.querySelector('#katSel').selectedOptions[0];
    const file = form.querySelector('[name=foto]').files[0];
    if (opt?.dataset.bukti === '1' && !file) throw new Error('Kategori ini wajib foto nota');
    if (Number(data.nominal) <= 0) throw new Error('Nominal harus > 0');

    let lampiran = null;
    if (file) {
      const ext = (file.name.split('.').pop() || 'jpg').toLowerCase();
      const path = `${state.user.id}/${Date.now()}-${Math.random().toString(36).slice(2, 8)}.${ext}`;
      const up = await sb.storage.from('nota').upload(path, file);
      if (up.error) throw new Error('Upload gagal: ' + up.error.message);
      lampiran = sb.storage.from('nota').getPublicUrl(path).data.publicUrl;
    }

    // client_uuid untuk idempotency
    const clientUuid = (crypto.randomUUID && crypto.randomUUID()) || String(Date.now()) + Math.random();
    const { error } = await sb.from('pengeluaran').insert({
      pool_id: state.profile.pool_id, rit_id: ritId, kategori_id: Number(data.kategori_id),
      nominal: Number(data.nominal), tanggal: new Date().toISOString().slice(0, 10),
      keterangan: data.keterangan || null,
      odometer: data.odometer ? Number(data.odometer) : null,
      liter: data.liter ? Number(data.liter) : null,
      lampiran, sumber: 'kru', client_uuid: clientUuid, status: 'menunggu', dibuat_oleh: state.user.id,
    });
    if (error) throw new Error(error.message);
    toast('Terkirim, menunggu approval.');
    close();
  });

  const sel = document.getElementById('katSel');
  const bbm = document.getElementById('bbmFields');
  const req = document.getElementById('reqBukti');
  const upd = () => {
    const o = sel.selectedOptions[0];
    bbm.style.display = o?.dataset.tipe === 'bbm' ? 'block' : 'none';
    req.style.display = o?.dataset.bukti === '1' ? 'inline' : 'none';
  };
  sel.addEventListener('change', upd); upd();
}
