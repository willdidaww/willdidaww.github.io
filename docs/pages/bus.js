// Master Bus: list + create/edit + hapus (dibatasi RLS: delete manajer/owner).
import { sb, must, e, state, isManager, toast } from '../lib.js';
import { shell, pageHead } from '../layout.js';
import { openModal } from '../modal.js';

const KELAS = ['ekonomi', 'bisnis', 'eksekutif', 'sleeper'];
const STATUS = ['aktif', 'perawatan', 'nonaktif'];

export async function renderBus() {
  const content = shell('#/bus');
  content.innerHTML = '<div class="empty">Memuat…</div>';
  const rows = await must(sb.from('bus').select('*').order('nopol'));

  content.innerHTML = `
    ${pageHead('Bus', 'Master armada — nopol unik', '<button class="btn primary" id="btnAdd">+ Tambah Bus</button>')}
    <div class="card"><div class="card-body"><div class="table-wrap"><table>
      <thead><tr><th>Nopol</th><th>Kelas</th><th>Karoseri</th><th>Tahun</th><th class="num">Odometer</th><th>Status</th><th></th></tr></thead>
      <tbody>
        ${rows.map((b) => `<tr>
          <td><b>${e(b.nopol)}</b></td><td>${e(b.kelas)}</td><td>${e(b.karoseri)}</td><td>${e(b.tahun)}</td>
          <td class="num mono">${Number(b.odometer || 0).toLocaleString('id-ID')} km</td>
          <td><span class="badge ${b.status === 'aktif' ? 'ok' : (b.status === 'perawatan' ? 'warn' : 'bad')}">${e(b.status)}</span></td>
          <td class="right"><div class="btn-row" style="justify-content:flex-end">
            <button class="btn sm" data-edit="${b.id}">Edit</button>
            ${isManager() ? `<button class="btn sm bad" data-del="${b.id}">Hapus</button>` : ''}
          </div></td></tr>`).join('') || '<tr><td colspan="7" class="empty">Belum ada bus.</td></tr>'}
      </tbody>
    </table></div></div></div>`;

  content.querySelector('#btnAdd').addEventListener('click', () => form());
  content.querySelectorAll('[data-edit]').forEach((btn) =>
    btn.addEventListener('click', () => form(rows.find((r) => r.id == btn.dataset.edit))));
  content.querySelectorAll('[data-del]').forEach((btn) =>
    btn.addEventListener('click', () => del(btn.dataset.del)));
}

function form(bus = null) {
  const b = bus || {};
  openModal(bus ? 'Edit Bus' : 'Tambah Bus', `
    <div class="form-group"><label>Nopol <span class="req">*</span></label><input name="nopol" required value="${e(b.nopol || '')}"></div>
    <div class="form-row">
      <div class="form-group"><label>Kelas</label><select name="kelas">${KELAS.map((k) => `<option ${b.kelas === k ? 'selected' : ''}>${k}</option>`).join('')}</select></div>
      <div class="form-group"><label>Karoseri</label><input name="karoseri" value="${e(b.karoseri || '')}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Tahun</label><input type="number" name="tahun" value="${e(b.tahun || '')}"></div>
      <div class="form-group"><label>Odometer</label><input type="number" name="odometer" value="${e(b.odometer || 0)}"></div>
    </div>
    <div class="form-group"><label>Status</label><select name="status">${STATUS.map((s) => `<option ${b.status === s ? 'selected' : ''}>${s}</option>`).join('')}</select></div>
  `, async (data, close) => {
    const payload = {
      nopol: data.nopol, kelas: data.kelas, karoseri: data.karoseri || null,
      tahun: data.tahun ? Number(data.tahun) : null,
      odometer: data.odometer ? Number(data.odometer) : 0, status: data.status,
    };
    if (bus) {
      await must(sb.from('bus').update(payload).eq('id', bus.id));
      toast('Bus diperbarui.');
    } else {
      payload.pool_id = state.profile.pool_id;
      await must(sb.from('bus').insert(payload));
      toast('Bus ditambahkan.');
    }
    close(); renderBus();
  });
}

async function del(id) {
  if (!confirm('Hapus bus ini? (gagal bila masih punya rit)')) return;
  const { error } = await sb.from('bus').delete().eq('id', id);
  if (error) { toast('Tidak bisa dihapus: ' + error.message, 'error'); return; }
  toast('Bus dihapus.'); renderBus();
}
