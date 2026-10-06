// Dashboard ringkasan (gaya liquid glass). Data: rit, pengeluaran, SIM kru — semua Slice 1.
import { sb, must, e, tgl, badgeClass, state } from '../lib.js';
import { shell } from '../layout.js';
import { icon } from '../icons.js';

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

  const nama = (state.profile?.nama || '').split(' ')[0] || 'Pengguna';

  // Daftar "perlu tindakan" (actionable)
  const actions = [];
  if (menunggu) actions.push({
    ico: 'checkCircle', color: 'var(--warn)', bg: 'var(--warnbg)',
    title: `${menunggu} pengeluaran menunggu approval`,
    sub: anomali ? `${anomali} di antaranya ditandai anomali` : 'Perlu ditinjau',
    href: '#/approval',
  });
  simExpiring.slice(0, 3).forEach((s) => {
    const expired = new Date(s.sim_berlaku_sampai) < new Date();
    actions.push({
      ico: 'idcard', color: expired ? 'var(--bad)' : 'var(--warn)', bg: expired ? 'var(--badbg)' : 'var(--warnbg)',
      title: `SIM ${e(s.nama)} ${expired ? 'sudah kedaluwarsa' : 'segera habis'}`,
      sub: `Berlaku sampai ${tgl(s.sim_berlaku_sampai)}`,
      href: '#/kru',
    });
  });
  if (ritBerjalan) actions.push({
    ico: 'compass', color: 'var(--brand)', bg: 'var(--mutedbg)',
    title: `${ritBerjalan} rit sedang berjalan`,
    sub: 'Pantau pengeluaran & tutup saat tiba',
    href: '#/rit?status=berjalan',
  });

  content.innerHTML = `
    <div class="hero">
      <div>
        <h1>${sapaan()}, ${e(nama)} ${icon('wave', { size: '22px' })}</h1>
        <div class="sub">${hariIni()} · Ringkasan operasional armada</div>
      </div>
      <a class="btn" href="#/rit">${icon('plus', { size: '15px' })} Kelola Rit</a>
    </div>

    <div class="grid grid-4">
      ${statCard('brand', 'compass', 'Rit Berjalan', ritBerjalan)}
      ${statCard('', 'folder', 'Rit Rencana', ritRencana)}
      ${statCard(menunggu ? 'warn' : '', 'checkCircle', 'Menunggu Approval',
        `${menunggu}${anomali ? ` <span class="badge bad" style="font-size:10px;vertical-align:middle">${anomali} anomali</span>` : ''}`)}
      ${statCard(simExpiring.length ? 'bad' : '', 'idcard', 'SIM Segera Habis', simExpiring.length)}
    </div>

    <div class="grid grid-2">
      <div class="card"><div class="card-head">Perlu Tindakan</div><div class="card-body">
        ${actions.length ? actions.map((a) => `
          <a class="action-item" href="${a.href}" style="color:inherit">
            <div class="ai-ico" style="background:${a.bg};color:${a.color}">${icon(a.ico, { size: '18px' })}</div>
            <div class="ai-main"><b>${a.title}</b><div>${a.sub}</div></div>
            <span class="muted">${icon('chevronRight', { size: '16px' })}</span>
          </a>`).join('') : `<div class="empty" style="padding:28px">${icon('party', { size: '18px' })} Tidak ada yang perlu ditindak.</div>`}
      </div></div>

      <div class="card"><div class="card-head">SIM Kru Mendekati Kedaluwarsa</div><div class="card-body">
        ${simExpiring.length ? `<div class="table-wrap"><table><thead><tr><th>Kru</th><th>No. SIM</th><th>Berlaku Sampai</th></tr></thead><tbody>
          ${simExpiring.map((s) => `<tr><td>${e(s.nama)}</td><td>${e(s.no_sim)}</td>
            <td><span class="badge ${new Date(s.sim_berlaku_sampai) < new Date() ? 'bad' : 'warn'}">${tgl(s.sim_berlaku_sampai)}</span></td></tr>`).join('')}
        </tbody></table></div>` : '<div class="empty" style="padding:28px">Semua SIM aman (60 hari ke depan).</div>'}
      </div></div>
    </div>

    <div class="card"><div class="card-head">Rit Terbaru <a class="btn sm" href="#/rit">Lihat semua</a></div><div class="card-body">
      ${ritTerbaru.length ? `<div class="table-wrap"><table><thead><tr><th>Kode</th><th>Bus</th><th>Rute</th><th>Tanggal</th><th>Status</th></tr></thead><tbody>
        ${ritTerbaru.map((r) => `<tr>
          <td><a href="#/rit-detail/${r.id}"><b>${e(r.kode)}</b></a></td>
          <td>${e(r.bus?.nopol)}</td>
          <td>${e(r.rute?.asal)} → ${e(r.rute?.tujuan)}</td>
          <td>${tgl(r.tanggal)}</td>
          <td><span class="badge ${badgeClass(r.status)}">${e(r.status)}</span></td></tr>`).join('')}
      </tbody></table></div>` : '<div class="empty">Belum ada rit.</div>'}
    </div>`;
}

function statCard(variant, iconName, label, value) {
  return `<div class="stat ${variant}">
    <div class="stat-icon">${icon(iconName, { size: '20px' })}</div>
    <div class="label">${label}</div>
    <div class="value">${value}</div>
  </div>`;
}

function sapaan() {
  const h = new Date().getHours();
  if (h < 11) return 'Selamat pagi';
  if (h < 15) return 'Selamat siang';
  if (h < 18) return 'Selamat sore';
  return 'Selamat malam';
}
function hariIni() {
  return new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
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
