<div class="auth-card">
    <h1>Lupa Password</h1>
    <p class="sub">Kami akan kirim kode OTP ke email/No. HP Anda</p>
    <?= \App\Core\View::render('partials/flash', ['_flash' => $_flash ?? []]) ?>
    <form method="post" action="/forgot-password">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Email atau No. HP</label>
            <input type="text" name="identifier" required autofocus>
        </div>
        <button class="btn primary" type="submit">Kirim OTP</button>
    </form>
    <div class="auth-links"><a href="/login">Kembali ke login</a></div>
</div>
