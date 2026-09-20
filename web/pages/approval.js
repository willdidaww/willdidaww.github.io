// Persetujuan: daftar pengeluaran menunggu, filter, approve/reject (per item & batch), alasan wajib saat reject.
import { sb, must, e, state, rupiah, toast } from '../lib.js';
import { shell, pageHead } from '../layout.js';

export async function renderApproval() {
  const content = shell('#/approval');
  content.innerHTML = '<div class="empty">Memuat…</div>';

  const params = new URLSearchParams((location.hash.split('?')[1]) || '');
  const onlyAnomali = params.get('anomali') === '1';

  let q = sb.from('pengeluaran')
    .select('*,kategori:kategori_id(nama),rit:rit_id(kode,bus:bus_id(nopol))')
    .eq('status', 'menunggu')
    .order('flag_anomali', { ascending: false }).order('nominal', { ascending: false });
  if (onlyAnomali) q = q.eq('flag_anomali', true);
  const rows = await must(q);

  content.innerHTML = `
    ${pageHead('Persetujuan', 'Pengeluaran menunggu approval lintas rit')}
    <div class="card"><div class="card-body">
      <div class="filters">
        <label style="font-weight:400"><input type="checkbox" id="fAnomali" ${onlyAnomali ? 'checked' : ''} style="width:auto"> Hanya anomali</label>
        <div class="btn-row" style="margin-left:auto">
          <button class="btn ok sm" id="btnApproveSel">✓ Setujui Terpilih</button>
          <button class="btn bad sm" id="btnRejectSel">✕ Tolak Terpilih</button>
        </div>
      </div>
      <div class="table-wrap"><table>
        <thead><tr><th><input type="checkbox" id="chkAll"></th><th>Rit / Bus</th><th>Kategori</th><th class="num">Nominal</th><th>Flag</th><th></th></tr></thead>
        <tbody>${rows.map((r) => `<tr>
          <td><input type="checkbox" class="chk" value="${r.id}"></td>
          <td>${r.rit ? `<a href="#/rit-detail/${r.rit_id}">${e(r.rit.kode)}</a><br><span class="muted">${e(r.rit.bus?.nopol)}</span>` : '<span class="tag">non-rit</span>'}</td>
          <td>${e(r.kategori?.nama)}<br><span class="muted" style="font-size:11px">${e(r.keterangan || '')}</span></td>
          <td class="num mono">${rupiah(r.nominal)}${r.lampiran ? `<br><a href="${e(r.lampiran)}" target="_blank" style="font-size:11px">📎 nota</a>` : ''}</td>
          <td>${r.flag_anomali ? '<span class="badge bad">anomali</span>' : '<span class="badge muted">normal</span>'}</td>
          <td class="right"><div class="btn-row" style="justify-content:flex-end">
            <button class="btn sm ok" data-approve="${r.id}">✓</button>
            <button class="btn sm bad" data-reject="${r.id}">✕</button>
          </div></td></tr>`).join('') || '<tr><td colspan="6" class="empty">Tidak ada yang menunggu approval. 🎉</td></tr>'}</tbody>
      </table></div>
    </div></div>`;

  const el = (s) => content.querySelector(s);
  el('#fAnomali').addEventListener('change', (ev) => {
    location.hash = '#/approval?' + new URLSearchParams({ anomali: ev.target.checked ? '1' : '' });
    renderApproval();
  });
  el('#chkAll').addEventListener('click', (ev) =>
    content.querySelectorAll('.chk').forEach((c) => (c.checked = ev.target.checked)));

  content.querySelectorAll('[data-approve]').forEach((b) =>
    b.addEventListener('click', () => setStatus([Number(b.dataset.approve)], 'disetujui')));
  content.querySelectorAll('[data-reject]').forEach((b) =>
    b.addEventListener('click', () => rejectFlow([Number(b.dataset.reject)])));

  el('#btnApproveSel').addEventListener('click', () => {
    const ids = selected(content); if (!ids.length) return toast('Pilih minimal satu item.', 'error');
    setStatus(ids, 'disetujui');
  });
  el('#btnRejectSel').addEventListener('click', () => {
    const ids = selected(content); if (!ids.length) return toast('Pilih minimal satu item.', 'error');
    rejectFlow(ids);
  });
}

function selected(root) {
  return Array.from(root.querySelectorAll('.chk:checked')).map((c) => Number(c.value));
}

async function setStatus(ids, status, alasan = null) {
  await must(sb.from('pengeluaran').update({
    status, alasan_reject: alasan, disetujui_oleh: state.user.id, disetujui_pada: new Date().toISOString(),
  }).in('id', ids).eq('status', 'menunggu'));
  toast(`${ids.length} item ${status === 'disetujui' ? 'disetujui' : 'ditolak'}.`);
  renderApproval();
}

function rejectFlow(ids) {
  const alasan = prompt('Alasan penolakan (wajib):');
  if (!alasan || !alasan.trim()) { toast('Alasan wajib diisi saat menolak.', 'error'); return; }
  setStatus(ids, 'ditolak', alasan.trim());
}
