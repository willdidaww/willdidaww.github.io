<div class="auth-card">
    <h1>Reset Password</h1>
    <p class="sub">Masukkan OTP dan password baru</p>
    <?= \App\Core\View::render('partials/flash', ['_flash' => $_flash ?? []]) ?>
    <form method="post" action="/reset-password">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Kode OTP</label>
            <input type="text" name="otp" inputmode="numeric" required autofocus>
        </div>
        <div class="form-group">
            <label>Password Baru</label>
            <input type="password" name="password" minlength="6" required>
        </div>
        <button class="btn primary" type="submit">Simpan Password</button>
    </form>
    <div class="auth-links"><a href="/login">Kembali ke login</a></div>
</div>
