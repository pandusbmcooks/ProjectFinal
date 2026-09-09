<?php require_once 'includes/auth.php';
require_role('admin');
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'delete') {
            $st = $pdo->prepare('SELECT status, foto FROM tb_unit_iphone WHERE id_unit=?');
            $st->execute([$_POST['id']]);
            $u = $st->fetch();
            if (!$u) {
                flash('error', 'Unit tidak ditemukan.');
            } else if ($u['status'] !== 'ready') {
                flash('error', 'Unit tidak dapat dihapus karena statusnya sedang "' . $u['status'] . '". Hanya unit berstatus ready yang dapat dihapus.');
            } else {
                if (!empty($u['foto']) && file_exists(__DIR__ . '/' . $u['foto'])) {
                    @unlink(__DIR__ . '/' . $u['foto']);
                }
                $pdo->prepare('DELETE FROM tb_unit_iphone WHERE id_unit=?')->execute([$_POST['id']]);
                flash('success', 'Unit dihapus.');
            }
        } else {
            $id = $_POST['id'] ?? '';
            $idModel = $_POST['id_model'];
            $nomorSeri = trim($_POST['nomor_seri']);
            $warna = trim($_POST['warna']);
            $status = $_POST['status'];

            $fotoPath = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $filename = 'unit_' . time() . '_' . uniqid() . '.' . $ext;
                    $targetDir = __DIR__ . '/uploads/units/';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetDir . $filename)) {
                        $fotoPath = 'uploads/units/' . $filename;
                    }
                }
            }

            if (!empty($id)) {
                if ($fotoPath) {
                    $st = $pdo->prepare('SELECT foto FROM tb_unit_iphone WHERE id_unit=?');
                    $st->execute([$id]);
                    $old = $st->fetch();
                    if ($old && !empty($old['foto']) && file_exists(__DIR__ . '/' . $old['foto'])) {
                        @unlink(__DIR__ . '/' . $old['foto']);
                    }
                    $pdo->prepare('UPDATE tb_unit_iphone SET id_model=?,nomor_seri=?,warna=?,status=?,foto=? WHERE id_unit=?')->execute([$idModel, $nomorSeri, $warna, $status, $fotoPath, $id]);
                } else {
                    $pdo->prepare('UPDATE tb_unit_iphone SET id_model=?,nomor_seri=?,warna=?,status=? WHERE id_unit=?')->execute([$idModel, $nomorSeri, $warna, $status, $id]);
                }
                flash('success', 'Unit diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO tb_unit_iphone (id_model,nomor_seri,warna,status,foto) VALUES (?,?,?,?,?)')->execute([$idModel, $nomorSeri, $warna, $status, $fotoPath]);
                flash('success', 'Unit ditambahkan.');
            }
        }
    } catch (Throwable $e) {
        flash('error', 'Gagal menyimpan unit. Nomor seri harus unik.');
    }
    redirect('units.php');
}
$edit = isset($_GET['edit']) ? $pdo->prepare('SELECT * FROM tb_unit_iphone WHERE id_unit=?') : null;
if ($edit) {
    $edit->execute([$_GET['edit']]);
    $edit = $edit->fetch();
}
$models = $pdo->query('SELECT * FROM tb_iphone_model ORDER BY nama_model')->fetchAll();
$rows = $pdo->query('SELECT u.*,m.nama_model,m.penyimpanan FROM tb_unit_iphone u JOIN tb_iphone_model m ON m.id_model=u.id_model ORDER BY m.nama_model ASC, u.nomor_seri ASC')->fetchAll();

$groupedUnits = [];
foreach ($rows as $r) {
    $mid = $r['id_model'];
    if (!isset($groupedUnits[$mid])) {
        $groupedUnits[$mid] = [
            'id_model' => $mid,
            'nama_model' => $r['nama_model'],
            'penyimpanan' => $r['penyimpanan'],
            'foto' => $r['foto'],
            'units' => []
        ];
    }
    if (empty($groupedUnits[$mid]['foto']) && !empty($r['foto'])) {
        $groupedUnits[$mid]['foto'] = $r['foto'];
    }
    $groupedUnits[$mid]['units'][] = $r;
}

require 'includes/layout.php';
page_start('Unit Fisik', true); ?>
<div class="content-grid">
    <section class="panel">
        <h2 class="panel-title"><?= $edit ? 'Ubah unit' : 'Tambah unit fisik' ?></h2>
        <form method="post" enctype="multipart/form-data"><input type="hidden" name="id" value="<?= e($edit['id_unit'] ?? '') ?>"><label>Model<select name="id_model" required><?php foreach ($models as $m): ?><option value="<?= $m['id_model'] ?>" <?= ($edit['id_model'] ?? '') == $m['id_model'] ? 'selected' : '' ?>><?= e($m['nama_model'] . ' · ' . $m['penyimpanan']) ?></option><?php endforeach ?></select></label>
            <div class="form-grid"><label>Nomor seri<input name="nomor_seri" value="<?= e($edit['nomor_seri'] ?? '') ?>" required></label><label>Warna<input name="warna" value="<?= e($edit['warna'] ?? '') ?>" required></label></div>
            <div class="form-grid"><label>Status<select name="status"><?php foreach (['ready', 'maintenance', 'hilang'] as $s): ?><option <?= $s === ($edit['status'] ?? 'ready') ? 'selected' : '' ?>><?= $s ?></option><?php endforeach ?></select></label><label>Foto iPhone <small class="muted">(Opsional)</small><input type="file" name="foto" accept="image/*"></label></div>
            <?php if (!empty($edit['foto'])): ?><div class="file-preview-wrap"><small class="muted">Foto saat ini:</small><br><img src="<?= e($edit['foto']) ?>" alt="Preview" class="unit-thumb"></div><?php endif ?>
            <button class="primary-btn"><?= $edit ? 'Simpan perubahan' : 'Tambah unit' ?></button>
        </form>
    </section>
    <section class="panel">
        <h2 class="panel-title">Status armada</h2>
        <p class="muted">Ubah status manual untuk unit yang sedang diperbaiki atau hilang. Status sewa akan dikelola otomatis saat transaksi dibuat dan dikembalikan.</p>
    </section>
</div>
<div class="section-head">
    <h2>Semua unit (<?= count($groupedUnits) ?> Model)</h2>
</div>
<section class="panel table-wrap">
    <table>
        <thead>
            <tr>
                <th>Foto</th>
                <th>Model</th>
                <th>Nomor seri</th>
                <th>Warna</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($groupedUnits)): ?>
                <tr><td colspan="6" class="empty">Belum ada unit fisik terdaftar.</td></tr>
            <?php else: ?>
                <?php foreach ($groupedUnits as $g):
                    $colors = array_unique(array_filter(array_column($g['units'], 'warna')));
                    $statusCounts = [];
                    foreach ($g['units'] as $u) {
                        $statusCounts[$u['status']] = ($statusCounts[$u['status']] ?? 0) + 1;
                    }
                ?>
                <tr>
                    <td><?php if (!empty($g['foto'])): ?><img src="<?= e($g['foto']) ?>" alt="Foto iPhone" class="unit-thumb"><?php else: ?><div class="unit-thumb-placeholder">⌁</div><?php endif ?></td>
                    <td>
                        <strong><?= e($g['nama_model']) ?></strong><br>
                        <small class="muted"><?= e($g['penyimpanan']) ?></small>
                        <div style="margin-top:4px;">
                            <span class="badge" style="font-size:10px;background:rgba(255,255,255,0.06);border:1px solid var(--line);"><?= count($g['units']) ?> Unit Terdaftar</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                            <?php foreach ($g['units'] as $u): ?>
                                <span style="font-family:monospace;font-size:12px;background:rgba(255,255,255,0.07);border:1px solid var(--line);padding:3px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:5px;">
                                    <?= e($u['nomor_seri']) ?>
                                    <span class="badge <?= e($u['status']) ?>" style="font-size:9px;padding:1px 5px;"><?= e($u['status']) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td><?= e(implode(', ', $colors)) ?></td>
                    <td>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;">
                            <?php foreach ($statusCounts as $st => $cnt): ?>
                                <span class="badge <?= e($st) ?>"><?= $cnt ?> <?= e($st) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <?php if (count($g['units']) === 1): ?>
                            <div class="actions">
                                <a class="outline-btn compact" href="units.php?edit=<?= $g['units'][0]['id_unit'] ?>">Ubah</a>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $g['units'][0]['id_unit'] ?>">
                                    <button class="danger-btn compact" data-confirm="Hapus unit <?= e($g['units'][0]['nomor_seri']) ?>?">Hapus</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;gap:5px;">
                                <?php foreach ($g['units'] as $u): ?>
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;font-size:11px;background:rgba(255,255,255,0.03);padding:2px 6px;border-radius:6px;border:1px solid rgba(255,255,255,0.06);">
                                        <span style="font-family:monospace;color:var(--muted);"><?= e($u['nomor_seri']) ?>:</span>
                                        <div style="display:flex;gap:4px;">
                                            <a class="outline-btn compact" style="font-size:10px;padding:2px 5px;" href="units.php?edit=<?= $u['id_unit'] ?>">Ubah</a>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $u['id_unit'] ?>">
                                                <button class="danger-btn compact" style="font-size:10px;padding:2px 5px;" data-confirm="Hapus unit <?= e($u['nomor_seri']) ?>?">Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach ?>
            <?php endif ?>
        </tbody>
    </table>
</section><?php page_end(); ?>