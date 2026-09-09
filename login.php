<?php require_once 'includes/auth.php';
if (is_logged_in()) redirect(user()['role'] === 'admin' ? 'dashboard_admin.php' : 'catalog.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $st = db()->prepare('SELECT * FROM tb_users WHERE LOWER(username) = LOWER(?)');
    $st->execute([$username]);
    $u = $st->fetch();

    $validPassword = $u && (password_verify($password, $u['password']) || password_verify(trim($password), $u['password']));

    if ($validPassword) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id_user' => $u['id_user'], 'username' => $u['username'], 'role' => $u['role']];
        redirect($u['role'] === 'admin' ? 'dashboard_admin.php' : 'catalog.php');
    }
    flash('error', 'Username atau password tidak sesuai.');
    redirect('login.php');
}
require 'includes/layout.php';
page_start('Masuk'); ?>
<div class="auth-shell">
    <div class="auth-brand"><a class="brand" href="catalog.php"><i>◉</i> iRent</a></div>
    <section class="panel auth-panel">
        <h1>Selamat datang kembali</h1>
        <p>Masuk untuk mengelola atau menyewa iPhone pilihanmu.</p>
        <form method="post"><label>Username<input name="username" required autofocus></label><label>Password<input type="password" name="password" required></label><button class="primary-btn">Masuk ke akun →</button></form>
        <p class="auth-switch">Belum punya akun? <a href="register.php">Daftar sebagai penyewa</a></p>
    </section>
</div><?php page_end(); ?>
