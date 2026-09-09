<?php require_once 'includes/auth.php';
require_role('admin');
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'delete') {
        // Cek apakah model masih dipakai unit yang berstatus ready (katalog) atau disewa
        $chk = $pdo->prepare("SELECT COUNT(*) FROM tb_unit_iphone WHERE id_model=? AND status IN('ready','disewa')");
        $chk->execute([$_POST['id']]);
        if ($chk->fetchColumn() > 0) {
            flash('error', 'Model tidak dapat dihapus karena masih ada unit yang berstatus Ready (tampil di katalog) atau sedang Disewa.');
            redirect('models.php');
            exit;
        }
        $pdo->prepare('DELETE FROM tb_iphone_model WHERE id_model=?')->execute([$_POST['id']]);
        flash('success', 'Model dihapus.');
    } else {
        $id = $_POST['id'] ?? '';
        $data = [trim($_POST['nama_model']), trim($_POST['penyimpanan']), $_POST['harga']];
        if ($id) {
            $pdo->prepare('UPDATE tb_iphone_model SET nama_model=?,penyimpanan=?,harga_sewa_per_hari=? WHERE id_model=?')->execute([...$data, $id]);
            flash('success', 'Model diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO tb_iphone_model (nama_model,penyimpanan,harga_sewa_per_hari) VALUES (?,?,?)')->execute($data);
            flash('success', 'Model ditambahkan.');
        }
    }
    redirect('models.php');
}
$edit = isset($_GET['edit']) ? $pdo->prepare('SELECT * FROM tb_iphone_model WHERE id_model=?') : null;
if ($edit) {
    $edit->execute([$_GET['edit']]);
    $edit = $edit->fetch();
}
$rows = $pdo->query("
    SELECT m.*,
           COUNT(u.id_unit) AS units,
           SUM(u.status = 'ready')  AS ready_units,
           SUM(u.status = 'disewa') AS disewa_units
    FROM tb_iphone_model m
    LEFT JOIN tb_unit_iphone u ON u.id_model = m.id_model
    GROUP BY m.id_model
    ORDER BY m.id_model DESC
")->fetchAll();
require 'includes/layout.php';
page_start('Model iPhone', true); ?>
<div class="content-grid">
    <section class="panel">
        <h2 class="panel-title"><?= $edit ? 'Ubah model' : 'Tambah model baru' ?></h2>
        <form method="post"><input type="hidden" name="id" value="<?= e($edit['id_model'] ?? '') ?>"><label>Nama model<input name="nama_model" value="<?= e($edit['nama_model'] ?? '') ?>" placeholder="Contoh: iPhone 15 Pro" required></label>
            <div class="form-grid"><label>Penyimpanan<input name="penyimpanan" value="<?= e($edit['penyimpanan'] ?? '') ?>" placeholder="256GB" required></label><label>Harga / hari<input name="harga" type="number" min="0" value="<?= e($edit['harga_sewa_per_hari'] ?? '') ?>" required></label></div><button class="primary-btn"><?= $edit ? 'Simpan perubahan' : 'Tambah model' ?></button>
        </form>
    </section>
    <section class="panel">
        <h2 class="panel-title">Harga sewa</h2>
        <p class="muted">Harga per hari digunakan untuk laporan pendapatan setelah transaksi selesai.</p>
    </section>
</div>
<div class="section-head">
    <h2>Daftar model</h2>
</div>
<section class="panel table-wrap">
    <table>
        <thead>
            <tr>
                <th>Model</th>
                <th>Penyimpanan</th>
                <th>Harga/hari</th>
                <th>Unit</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody><?php foreach ($rows as $r): ?><tr>
                    <td><strong><?= e($r['nama_model']) ?></strong></td>
                    <td><?= e($r['penyimpanan']) ?></td>
                    <td>Rp<?= number_format($r['harga_sewa_per_hari'], 0, ',', '.') ?></td>
                    <td><?= $r['units'] ?></td>
                    <td class="actions"><a class="outline-btn compact" href="models.php?edit=<?= $r['id_model'] ?>">Ubah</a>
                        <?php
                            $blocked  = ($r['ready_units'] > 0 || $r['disewa_units'] > 0);
                            $reasons  = [];
                            if ($r['ready_units']  > 0) $reasons[] = (int)$r['ready_units']  . ' unit Ready (katalog)';
                            if ($r['disewa_units'] > 0) $reasons[] = (int)$r['disewa_units'] . ' unit sedang Disewa';
                            $tipText  = $blocked ? 'Tidak bisa dihapus: masih ada ' . implode(' & ', $reasons) : '';
                        ?>
                        <?php if ($blocked): ?>
                            <button class="danger-btn compact" disabled title="<?= e($tipText) ?>" style="opacity:.4;cursor:not-allowed">Hapus</button>
                        <?php else: ?>
                            <form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id_model'] ?>"><button class="danger-btn compact" data-confirm="Hapus model ini?">Hapus</button></form>
                        <?php endif ?>
                    </td>
                </tr><?php endforeach ?></tbody>
    </table>
</section><?php page_end(); ?>