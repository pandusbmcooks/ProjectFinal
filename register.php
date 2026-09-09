<?php require_once 'includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare("INSERT INTO tb_users (username,password,role) VALUES (?,?,'user')");
        $st->execute([trim($_POST['username']), password_hash($_POST['password'], PASSWORD_DEFAULT)]);
        $pdo->prepare('INSERT INTO tb_pelanggan (id_user,nama_lengkap,nomor_nik,nomor_wa,alamat) VALUES (?,?,?,?,?)')->execute([$pdo->lastInsertId(), trim($_POST['nama_lengkap']), trim($_POST['nomor_nik']), trim($_POST['nomor_wa']), trim($_POST['alamat'])]);
        $pdo->commit();
        flash('success', 'Akun berhasil dibuat. Silakan masuk dan lengkapi foto jaminan di profil Anda.');
        redirect('login.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('error', 'Registrasi gagal. Username atau NIK mungkin sudah digunakan.');
        redirect('register.php');
    }
}
require 'includes/layout.php';
page_start('Daftar'); ?>
<div class="auth-shell">
    <div class="auth-brand"><a class="brand" href="catalog.php"><i>◉</i> iRent</a></div>
    <section class="panel auth-panel">
        <h1>Buat akun penyewa</h1>
        <p>Data identitas digunakan untuk proses sewa yang aman.</p>
        <form method="post">
            <div class="form-grid"><label>Nama lengkap<input name="nama_lengkap" required></label><label>Nomor WhatsApp<input name="nomor_wa" required></label></div><label>NIK<input name="nomor_nik" inputmode="numeric" required></label><label>Alamat<textarea name="alamat" rows="2" required></textarea></label>
            <div class="form-grid"><label>Username<input name="username" required></label><label>Password<input type="password" name="password" minlength="6" required></label></div><button class="primary-btn">Buat akun →</button>
        </form>
        <p class="auth-switch">Sudah punya akun? <a href="login.php">Masuk</a></p>
    </section>
</div><?php page_end(); ?>