// Halaman login (email + password via Supabase Auth).
import { sb, $, formData, toast } from '../lib.js';

export function renderLogin() {
  const app = document.getElementById('app');
  app.innerHTML = `
    <div class="auth-wrap">
      <div class="auth-card">
        <h1>Biaya Bus AKAP</h1>
        <p class="sub">Manajemen biaya operasional armada</p>
        <form id="loginForm">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required autofocus autocomplete="username">
          </div>
          <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required autocomplete="current-password">
          </div>
          <button class="btn primary" type="submit" id="btnLogin">Masuk</button>
        </form>
        <div class="demo-creds">
          Akun dibuat di <b>Supabase &gt; Authentication</b>, lalu diberi peran lewat
          <code>seed.sql</code> (blok PROMOSI PERAN). Lihat <b>SETUP-SUPABASE.md</b>.
        </div>
      </div>
    </div>`;

  $('#loginForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const { email, password } = formData(ev.target);
    const btn = $('#btnLogin');
    btn.disabled = true; btn.textContent = 'Memproses…';
    const { error } = await sb.auth.signInWithPassword({ email, password });
    if (error) {
      toast(error.message || 'Login gagal', 'error');
      btn.disabled = false; btn.textContent = 'Masuk';
      return;
    }
    // onAuthStateChange akan memuat profil & mengarahkan otomatis.
  });
}
