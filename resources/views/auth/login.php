<div class="auth-card">
    <h1>Biaya Bus AKAP</h1>
    <p class="sub">Manajemen biaya operasional armada</p>
    <?= \App\Core\View::render('partials/flash', ['_flash' => $_flash ?? []]) ?>
    <form method="post" action="/login">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Email atau No. HP</label>
            <input type="text" name="identifier" value="<?= e(old('identifier')) ?>" autofocus required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <button class="btn primary" type="submit">Masuk</button>
    </form>
    <div class="auth-links"><a href="/forgot-password">Lupa password?</a></div>
    <div class="demo-creds">
        <b>Akun demo</b> (password: <code>password</code>)<br>
        owner@akap.test · manajer@akap.test · keuangan@akap.test<br>
        admin@akap.test · kru@akap.test
    </div>
</div>
