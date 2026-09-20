// Kerangka tampilan: sidebar peran-aware + topbar. Mengembalikan elemen konten.
import { state, isStaff, e, sb } from './lib.js';

const NAV_STAFF = [
  { group: '' },
  { href: '#/', label: '📊 Dashboard' },
  { group: 'Operasional' },
  { href: '#/rit', label: '🧭 Rit' },
  { href: '#/pengeluaran', label: '🧾 Pengeluaran' },
  { href: '#/approval', label: '✅ Persetujuan' },
  { group: 'Master' },
  { href: '#/bus', label: '🚍 Bus' },
  { href: '#/rute', label: '🗺️ Rute' },
  { href: '#/kru', label: '👷 Kru' },
];
const NAV_KRU = [
  { group: '' },
  { href: '#/kru-app', label: '🧭 Rit Saya' },
];

/** Bangun shell untuk halaman terautentikasi; kembalikan elemen .content untuk diisi. */
export function shell(active) {
  const nav = isStaff() ? NAV_STAFF : NAV_KRU;
  const app = document.getElementById('app');
  app.innerHTML = `
    <div class="shell">
      <aside class="sidebar">
        <div class="brand">🚌 <span>Biaya Bus AKAP</span><small>${e(state.profile?.nama || '')}</small></div>
        <nav>
          ${nav.map((n) => n.group !== undefined
            ? (n.group ? `<div class="group">${e(n.group)}</div>` : '')
            : `<a href="${n.href}" class="${active === n.href ? 'active' : ''}"><span>${n.label}</span></a>`
          ).join('')}
        </nav>
      </aside>
      <div class="main">
        <div class="topbar">
          <div class="who">Masuk sebagai <b>${e(state.profile?.nama || '')}</b> <span class="tag">${e(state.profile?.role || '')}</span></div>
          <button class="btn sm" id="btnLogout">Keluar</button>
        </div>
        <div class="content" id="content"></div>
      </div>
    </div>`;
  document.getElementById('btnLogout').addEventListener('click', async () => {
    await sb.auth.signOut();
    location.hash = '#/login';
  });
  return document.getElementById('content');
}

export function pageHead(title, sub = '', actionsHtml = '') {
  return `<div class="page-head"><div><h1>${e(title)}</h1>${sub ? `<div class="sub">${e(sub)}</div>` : ''}</div>${actionsHtml}</div>`;
}
