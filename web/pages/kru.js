// Master Kru: list + create/edit, peringatan SIM mendekati kedaluwarsa.
import { sb, must, e, state, tgl, toast } from '../lib.js';
import { shell, pageHead } from '../layout.js';
import { openModal } from '../modal.js';

const POSISI = ['sopir', 'sopir_2', 'kernet', 'kondektur'];

export async function renderKru() {
  const content = shell('#/kru');
  content.innerHTML = '<div class="empty">Memuat…</div>';
  const rows = await must(sb.from('kru').select('*').order('aktif', { ascending: false }).order('nama'));
  const now = Date.now(), soon = now + 60 * 864e5;

  content.innerHTML = `
    ${pageHead('Kru', 'Peringatan otomatis SIM mendekati kedaluwarsa', '<button class="btn primary" id="btnAdd">+ Tambah Kru</button>')}
    <div class="card"><div class="card-body"><div class="table-wrap"><table>
      <thead><tr><th>Nama</th><th>Posisi</th><th>No. HP</th><th>SIM</th><th>Berlaku Sampai</th><th>Status</th><th></th></tr></thead>
      <tbody>
        ${rows.map((k) => {
          const t = k.sim_berlaku_sampai ? new Date(k.sim_berlaku_sampai).getTime() : null;
          const cls = t === null ? '' : (t < now ? 'bad' : (t <= soon ? 'warn' : 'ok'));
          return `<tr>
            <td><b>${e(k.nama)}</b></td>
            <td><span class="tag">${e(String(k.posisi).replace('_', ' '))}</span></td>
            <td>${e(k.no_hp)}</td><td>${e(k.no_sim || '-')}</td>
            <td>${k.sim_berlaku_sampai ? `<span class="badge ${cls}">${tgl(k.sim_berlaku_sampai)}</span>` : '-'}</td>
            <td><span class="badge ${k.aktif ? 'ok' : 'muted'}">${k.aktif ? 'aktif' : 'nonaktif'}</span></td>
            <td class="right"><button class="btn sm" data-edit="${k.id}">Edit</button></td></tr>`;
        }).join('') || '<tr><td colspan="7" class="empty">Belum ada kru.</td></tr>'}
      </tbody>
    </table></div></div></div>`;

  content.querySelector('#btnAdd').addEventListener('click', () => form());
  content.querySelectorAll('[data-edit]').forEach((b) =>
    b.addEventListener('click', () => form(rows.find((r) => r.id == b.dataset.edit))));
}

function form(kru = null) {
  const k = kru || {};
  openModal(kru ? 'Edit Kru' : 'Tambah Kru', `
    <div class="form-group"><label>Nama <span class="req">*</span></label><input name="nama" required value="${e(k.nama || '')}"></div>
    <div class="form-row">
      <div class="form-group"><label>Posisi</label><select name="posisi">${POSISI.map((p) => `<option ${k.posisi === p ? 'selected' : ''}>${p}</option>`).join('')}</select></div>
      <div class="form-group"><label>No. HP</label><input name="no_hp" value="${e(k.no_hp || '')}"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>No. SIM</label><input name="no_sim" value="${e(k.no_sim || '')}"></div>
      <div class="form-group"><label>SIM Berlaku Sampai</label><input type="date" name="sim_berlaku_sampai" value="${e(k.sim_berlaku_sampai || '')}"></div>
    </div>
    ${kru ? `<div class="form-group"><label>Status</label><select name="aktif"><option value="true" ${k.aktif ? 'selected' : ''}>Aktif</option><option value="false" ${!k.aktif ? 'selected' : ''}>Nonaktif</option></select></div>` : ''}
  `, async (data, close) => {
    const payload = {
      nama: data.nama, posisi: data.posisi, no_hp: data.no_hp || null,
      no_sim: data.no_sim || null, sim_berlaku_sampai: data.sim_berlaku_sampai || null,
    };
    if (kru) { payload.aktif = data.aktif === 'true'; await must(sb.from('kru').update(payload).eq('id', kru.id)); toast('Kru diperbarui.'); }
    else { payload.pool_id = state.profile.pool_id; payload.aktif = true; await must(sb.from('kru').insert(payload)); toast('Kru ditambahkan.'); }
    close(); renderKru();
  });
}
