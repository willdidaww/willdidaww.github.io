// Master Rute: list + create/edit + toggle aktif.
import { sb, must, e, state, toast } from '../lib.js';
import { shell, pageHead } from '../layout.js';
import { openModal } from '../modal.js';

export async function renderRute() {
  const content = shell('#/rute');
  content.innerHTML = '<div class="empty">Memuat…</div>';
  const rows = await must(sb.from('rute').select('*').order('aktif', { ascending: false }).order('asal'));

  content.innerHTML = `
    ${pageHead('Rute', 'Nonaktifkan tanpa hapus (histori tetap ada)', '<button class="btn primary" id="btnAdd">+ Tambah Rute</button>')}
    <div class="card"><div class="card-body"><div class="table-wrap"><table>
      <thead><tr><th>Kode</th><th>Asal → Tujuan</th><th class="num">Jarak</th><th class="num">Estimasi</th><th>Status</th><th></th></tr></thead>
      <tbody>
        ${rows.map((r) => `<tr>
          <td>${e(r.kode)}</td><td><b>${e(r.asal)}</b> → <b>${e(r.tujuan)}</b></td>
          <td class="num mono">${r.jarak_km ? Number(r.jarak_km).toLocaleString('id-ID') + ' km' : '-'}</td>
          <td class="num mono">${r.estimasi_jam ? r.estimasi_jam + ' jam' : '-'}</td>
          <td><span class="badge ${r.aktif ? 'ok' : 'muted'}">${r.aktif ? 'aktif' : 'nonaktif'}</span></td>
          <td class="right"><div class="btn-row" style="justify-content:flex-end">
            <button class="btn sm" data-edit="${r.id}">Edit</button>
            <button class="btn sm" data-toggle="${r.id}" data-aktif="${r.aktif ? 1 : 0}">${r.aktif ? 'Nonaktifkan' : 'Aktifkan'}</button>
          </div></td></tr>`).join('') || '<tr><td colspan="6" class="empty">Belum ada rute.</td></tr>'}
      </tbody>
    </table></div></div></div>`;

  content.querySelector('#btnAdd').addEventListener('click', () => form());
  content.querySelectorAll('[data-edit]').forEach((b) =>
    b.addEventListener('click', () => form(rows.find((r) => r.id == b.dataset.edit))));
  content.querySelectorAll('[data-toggle]').forEach((b) =>
    b.addEventListener('click', async () => {
      await must(sb.from('rute').update({ aktif: b.dataset.aktif !== '1' }).eq('id', b.dataset.toggle));
      toast('Status rute diubah.'); renderRute();
    }));
}

function form(rute = null) {
  const r = rute || {};
  openModal(rute ? 'Edit Rute' : 'Tambah Rute', `
    <div class="form-group"><label>Kode</label><input name="kode" value="${e(r.kode || '')}" placeholder="JKT-SBY"></div>
    <div class="form-row">
      <div class="form-group"><label>Asal <span class="req">*</span></label><input name="asal" required value="${e(r.asal || '')}"></div>
      <div class="form-group"><label>Tujuan <span class="req">*</span></label><input name="tujuan" required value="${e(r.tujuan || '')}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Jarak (km)</label><input type="number" step="0.1" name="jarak_km" value="${e(r.jarak_km || '')}"></div>
      <div class="form-group"><label>Estimasi (jam)</label><input type="number" step="0.1" name="estimasi_jam" value="${e(r.estimasi_jam || '')}"></div>
    </div>
  `, async (data, close) => {
    const payload = {
      kode: data.kode || null, asal: data.asal, tujuan: data.tujuan,
      jarak_km: data.jarak_km ? Number(data.jarak_km) : null,
      estimasi_jam: data.estimasi_jam ? Number(data.estimasi_jam) : null,
    };
    if (rute) { await must(sb.from('rute').update(payload).eq('id', rute.id)); toast('Rute diperbarui.'); }
    else { payload.pool_id = state.profile.pool_id; payload.aktif = true; await must(sb.from('rute').insert(payload)); toast('Rute ditambahkan.'); }
    close(); renderRute();
  });
}
