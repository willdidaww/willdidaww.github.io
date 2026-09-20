// Entry point SPA: restore sesi, router hash-based, guard peran.
import { sb, state, isStaff, isKru } from './lib.js';
import { renderLogin } from './pages/auth.js';
import { renderDashboard } from './pages/dashboard.js';
import { renderBus } from './pages/bus.js';
import { renderRute } from './pages/rute.js';
import { renderKru } from './pages/kru.js';
import { renderRitList, renderRitDetail } from './pages/rit.js';
import { renderPengeluaran } from './pages/pengeluaran.js';
import { renderApproval } from './pages/approval.js';
import { renderKruApp } from './pages/kru_app.js';

// Rute publik (tanpa login)
const PUBLIC = ['#/login'];

// Muat profil user (role/pool/kru) dari tabel profiles
async function loadProfile() {
  const { data: { user } } = await sb.auth.getUser();
  state.user = user;
  state.profile = null;
  if (user) {
    const { data } = await sb.from('profiles').select('*').eq('id', user.id).single();
    state.profile = data || { id: user.id, role: 'kru', nama: user.email };
  }
}

function parseHash() {
  const raw = location.hash || '#/';
  const [path, ...rest] = raw.split('/').slice(1); // buang '#'
  return { route: '#/' + (path || ''), parts: rest };
}

async function router() {
  const { route, parts } = parseHash();

  // Belum login -> paksa ke login
  if (!state.user) {
    if (!PUBLIC.includes(route)) { location.hash = '#/login'; return; }
    return renderLogin();
  }

  // Sudah login tapi buka /login -> arahkan ke beranda sesuai peran
  if (route === '#/login') {
    location.hash = isStaff() ? '#/' : '#/kru-app';
    return;
  }

  // Kru: batasi hanya ke area kru
  if (isKru() && !['#/kru-app'].includes(route)) {
    location.hash = '#/kru-app';
    return;
  }

  try {
    switch (route) {
      case '#/':            return await renderDashboard();
      case '#/bus':         return await renderBus();
      case '#/rute':        return await renderRute();
      case '#/kru':         return await renderKru();
      case '#/rit':         return await renderRitList();
      case '#/rit-detail':  return await renderRitDetail(parts[0]);
      case '#/pengeluaran': return await renderPengeluaran();
      case '#/approval':    return await renderApproval();
      case '#/kru-app':     return await renderKruApp();
      default:
        document.getElementById('app').innerHTML =
          '<div class="auth-wrap"><div class="auth-card"><h1>404</h1><p class="sub">Halaman tidak ditemukan.</p><a class="btn" href="#/">Beranda</a></div></div>';
    }
  } catch (err) {
    document.getElementById('app').innerHTML =
      `<div class="auth-wrap"><div class="auth-card"><h1>Terjadi kesalahan</h1><p class="sub">${(err && err.message) || err}</p><a class="btn" href="#/">Muat ulang</a></div></div>`;
  }
}

// Reaksi terhadap perubahan auth (login/logout/refresh)
sb.auth.onAuthStateChange(async () => {
  await loadProfile();
  router();
});

window.addEventListener('hashchange', router);

// Boot
(async function init() {
  await loadProfile();
  if (!location.hash) location.hash = state.user ? '#/' : '#/login';
  router();
})();
