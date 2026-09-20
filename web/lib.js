// Utilitas bersama: klien Supabase, helper DOM, format, escaping, state sesi.

const cfg = window.AKAP_CONFIG || {};
if (!cfg.SUPABASE_URL || !cfg.SUPABASE_ANON_KEY || cfg.SUPABASE_URL.includes('YOUR-')) {
  document.getElementById('app').innerHTML =
    '<div class="auth-wrap"><div class="auth-card">' +
    '<h1>Konfigurasi belum lengkap</h1>' +
    '<p class="sub">Salin <code>config.example.js</code> menjadi <code>config.js</code> lalu isi URL & anon key Supabase Anda. Lihat <b>SETUP-SUPABASE.md</b>.</p>' +
    '</div></div>';
  throw new Error('AKAP_CONFIG belum diisi');
}

// Klien Supabase (global `supabase` dari UMD bundle)
export const sb = window.supabase.createClient(cfg.SUPABASE_URL, cfg.SUPABASE_ANON_KEY, {
  auth: { persistSession: true, autoRefreshToken: true },
});

// State sesi (diisi setelah login): { user, profile }
export const state = { user: null, profile: null };

// --- Helper DOM ---
export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

/** Escape HTML untuk cegah XSS saat render nilai dari DB. */
export function e(v) {
  if (v === null || v === undefined) return '';
  return String(v).replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

export function rupiah(n) {
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

export function tgl(iso, withTime = false) {
  if (!iso) return '-';
  const d = new Date(iso);
  if (isNaN(d)) return e(iso);
  const opt = withTime
    ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    : { day: '2-digit', month: '2-digit', year: 'numeric' };
  return d.toLocaleDateString('id-ID', opt);
}

export function badgeClass(status) {
  const map = {
    aktif: 'ok', disetujui: 'ok', selesai: 'ok', lunas: 'ok',
    menunggu: 'warn', rencana: 'warn', dilaporkan: 'warn', perawatan: 'warn',
    ditolak: 'bad', nonaktif: 'bad', batal: 'bad', berjalan: 'ok',
  };
  return map[status] || 'muted';
}

export const isStaff = () => ['owner', 'manajer', 'keuangan', 'admin_pool'].includes(state.profile?.role);
export const isManager = () => ['owner', 'manajer'].includes(state.profile?.role);
export const isKru = () => state.profile?.role === 'kru';

// --- Notifikasi toast sederhana ---
let toastTimer;
export function toast(msg, type = 'success') {
  let el = $('#toast');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast';
    el.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:999;max-width:90%';
    document.body.appendChild(el);
  }
  el.innerHTML = `<div class="alert ${type}" style="box-shadow:0 6px 20px rgba(0,0,0,.2)">${e(msg)}</div>`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (el.innerHTML = ''), 4000);
}

/** Bungkus panggilan Supabase; lempar error dengan pesan bersih. */
export async function must(promise) {
  const { data, error } = await promise;
  if (error) throw new Error(error.message || 'Terjadi kesalahan');
  return data;
}

/** Ambil nilai form sebagai objek. */
export function formData(form) {
  const fd = new FormData(form);
  const obj = {};
  for (const [k, v] of fd.entries()) obj[k] = typeof v === 'string' ? v.trim() : v;
  return obj;
}
