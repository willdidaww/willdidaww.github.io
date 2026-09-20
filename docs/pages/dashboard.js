// Dashboard ringkasan (slice 1: rit berjalan, menunggu approval, SIM kru, rit terbaru).
import { sb, must, rupiah, e, tgl, badgeClass } from '../lib.js';
import { shell, pageHead } from '../layout.js';

export async function renderDashboard() {
  const content = shell('#/');
  content.innerHTML = '<div class="empty">Memuat…</div>';

  // Hitung metrik. RLS otomatis membatasi ke pool user.
  const [ritBerjalan, ritRencana, menunggu, anomali] = await Promise.all([
    countRit('berjalan'), countRit('rencana'),
    countPeng('menunggu', false), countPeng('menunggu', true),
  ]);

  const simExpiring = await must(
    sb.from('kru').select('nama,no_sim,sim_berlaku_sampai')
      .eq('aktif', true).not('sim_berlaku_sampai', 'is', null)
      .lte('sim_berlaku_sampai', addDays(60)).order('sim_berlaku_sampai').limit(8)
  );

  const ritTerbaru = await must(
    sb.from('rit').select('id,kode,tanggal,status,bus:bus_id(nopol),rute:rute_id(asal,tujuan)')
      .order('dibuat_pada', { ascending: false }).limit(6)
  );

  content.innerHTML = `
    ${pageHead('Dashboard', 'Ringkasan operasional', '<a class="btn primary" href="#/rit">+ Kelola Rit</a>')}
    <div class="grid grid-4">
      <div class="stat brand"><div class="label">Rit Berjalan</div><div class="value">${ritBerjalan}</div></div>
      <div class="stat"><div class="label">Rit Rencana</div><div class="value">${ritRencana}</div></div>
      <div class="stat ${menunggu ? 'warn' : ''}"><div class="label">Menunggu Approval</div><div class="value">${menunggu}${anomali ? ` <span class="badge bad" style="font-size:11px">${anomali} anomali</span>` : ''}</div></div>
      <div class="stat ${simExpiring.length ? 'warn' : ''}"><div class="label">SIM Segera Habis</div><div class="value">${simExpiring.length}</div></div>
    </div>
    <div class="grid grid-2">
      <div class="card"><div class="card-head">SIM Kru Mendekati Kedaluwarsa</div><div class="card-body">
        ${simExpiring.length ? `<div class="table-wrap"><table><thead><tr><th>Kru</th><th>No. SIM</th><th>Berlaku Sampai</th></tr></thead><tbody>
          ${simExpiring.map((s) => `<tr><td>${e(s.nama)}</td><td>${e(s.no_sim)}</td>
            <td><span class="badge ${new Date(s.sim_berlaku_sampai) < new Date() ? 'bad' : 'warn'}">${tgl(s.sim_berlaku_sampai)}</span></td></tr>`).join('')}
        </tbody></table></div>` : '<div class="muted">Semua SIM aman (60 hari ke depan).</div>'}
      </div></div>
      <div class="card"><div class="card-head">Rit Terbaru</div><div class="card-body">
        ${ritTerbaru.length ? `<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Bus</th><th>Rute</th><th>Tanggal</th><th>Status</th></tr></thead><tbody>
          ${ritTerbaru.map((r) => `<tr>
            <td><a href="#/rit-detail/${r.id}">${e(r.kode)}</a></td>
            <td>${e(r.bus?.nopol)}</td>
            <td>${e(r.rute?.asal)} → ${e(r.rute?.tujuan)}</td>
            <td>${tgl(r.tanggal)}</td>
            <td><span class="badge ${badgeClass(r.status)}">${e(r.status)}</span></td></tr>`).join('')}
        </tbody></table></div>` : '<div class="muted">Belum ada rit.</div>'}
      </div></div>
    </div>`;
}

async function countRit(status) {
  const { count } = await sb.from('rit').select('id', { count: 'exact', head: true }).eq('status', status);
  return count || 0;
}
async function countPeng(status, anomaliOnly) {
  let q = sb.from('pengeluaran').select('id', { count: 'exact', head: true }).eq('status', status);
  if (anomaliOnly) q = q.eq('flag_anomali', true);
  const { count } = await q;
  return count || 0;
}
function addDays(n) {
  const d = new Date(); d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
}
