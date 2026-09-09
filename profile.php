<?php
require_once 'includes/auth.php';
require_role('user');

$pdo = db();
$userId = user()['id_user'];
$jenisJaminanValid = ['KTP', 'Kartu Pelajar', 'SIM', 'Paspor', 'Kartu Identitas Lainnya'];

// Fetch customer data
$st = $pdo->prepare('SELECT p.*, u.username FROM tb_pelanggan p JOIN tb_users u ON u.id_user = p.id_user WHERE p.id_user = ?');
$st->execute([$userId]);
$customer = $st->fetch();

// If customer record doesn't exist yet, create a default record
if (!$customer) {
    $pdo->prepare('INSERT INTO tb_pelanggan (id_user, nama_lengkap, nomor_nik, nomor_wa, alamat) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, user()['username'], 'NIK-' . $userId, '-', '-']);
    $st->execute([$userId]);
    $customer = $st->fetch();
}

$hasJaminan = !empty($customer['foto_jaminan']) && file_exists(__DIR__ . '/' . $customer['foto_jaminan']);

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $nomorNik = trim($_POST['nomor_nik'] ?? '');
        $nomorWa = trim($_POST['nomor_wa'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if (!$namaLengkap || !$nomorNik || !$nomorWa || !$alamat) {
            flash('error', 'Semua kolom biodata wajib diisi.');
            redirect('profile.php');
        }

        try {
            // Check if NIK already used by other customer
            $checkNik = $pdo->prepare('SELECT id_pelanggan FROM tb_pelanggan WHERE nomor_nik = ? AND id_user != ?');
            $checkNik->execute([$nomorNik, $userId]);
            if ($checkNik->fetch()) {
                throw new Exception('Nomor NIK sudah digunakan oleh akun lain.');
            }

            $update = $pdo->prepare('UPDATE tb_pelanggan SET nama_lengkap = ?, nomor_nik = ?, nomor_wa = ?, alamat = ? WHERE id_user = ?');
            $update->execute([$namaLengkap, $nomorNik, $nomorWa, $alamat, $userId]);
            flash('success', 'Biodata berhasil diperbarui.');
        } catch (Throwable $e) {
            flash('error', 'Gagal memperbarui biodata: ' . $e->getMessage());
        }
        redirect('profile.php');
    }

    if ($action === 'update_jaminan') {
        if ($hasJaminan) {
            flash('error', 'Jaminan identitas sudah tersimpan dan tidak dapat diubah kembali.');
            redirect('profile.php');
        }

        $jenisJaminan = trim($_POST['jenis_jaminan'] ?? '');

        if (!in_array($jenisJaminan, $jenisJaminanValid, true)) {
            flash('error', 'Jenis jaminan tidak valid.');
            redirect('profile.php');
        }

        $fotoJaminanPath = $customer['foto_jaminan'] ?? null;

        if (isset($_FILES['foto_jaminan']) && $_FILES['foto_jaminan']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto_jaminan']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowed, true)) {
                flash('error', 'Format foto jaminan harus JPG, JPEG, PNG, atau WEBP.');
                redirect('profile.php');
            }

            if ($_FILES['foto_jaminan']['size'] > 5 * 1024 * 1024) {
                flash('error', 'Ukuran file foto jaminan maksimal 5MB.');
                redirect('profile.php');
            }

            $filename = 'jaminan_' . $userId . '_' . time() . '_' . uniqid() . '.' . $ext;
            $targetDir = __DIR__ . '/uploads/jaminan/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            if (move_uploaded_file($_FILES['foto_jaminan']['tmp_name'], $targetDir . $filename)) {
                // Delete previous file if exists
                if ($fotoJaminanPath && file_exists(__DIR__ . '/' . $fotoJaminanPath)) {
                    @unlink(__DIR__ . '/' . $fotoJaminanPath);
                }
                $fotoJaminanPath = 'uploads/jaminan/' . $filename;
            } else {
                flash('error', 'Gagal mengunggah file foto jaminan.');
                redirect('profile.php');
            }
        } elseif (empty($fotoJaminanPath)) {
            flash('error', 'File foto jaminan wajib dipilih.');
            redirect('profile.php');
        }

        try {
            $update = $pdo->prepare('UPDATE tb_pelanggan SET jenis_jaminan = ?, foto_jaminan = ? WHERE id_user = ?');
            $update->execute([$jenisJaminan, $fotoJaminanPath, $userId]);
            flash('success', 'Foto jaminan berhasil disimpan! Akun Anda kini aktif untuk melakukan transaksi sewa secara mandiri.');
        } catch (Throwable $e) {
            flash('error', 'Gagal menyimpan foto jaminan: ' . $e->getMessage());
        }
        redirect('profile.php');
    }
}

require 'includes/layout.php';
page_start('Profil Saya');
?>

<section class="hero">
    <div>
        <p class="eyebrow">AKUN PENYEWA</p>
        <h1>Profil & Jaminan Identitas</h1>
        <p>Lengkapi biodata dan foto jaminan identitas asli Anda agar dapat mengajukan sewa iPhone.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <?php if ($hasJaminan): ?>
            <a class="primary-btn" href="form_sewa_user.php">+ Ajukan Sewa iPhone</a>
        <?php else: ?>
            <a class="outline-btn" href="#form-jaminan">Unggah Jaminan ↓</a>
        <?php endif; ?>
        <a class="outline-btn" href="my_rentals.php">Riwayat Sewa →</a>
    </div>
</section>

<!-- Status Jaminan Banner -->
<?php if ($hasJaminan): ?>
    <div class="alert success" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:24px;">✓</span>
            <div>
                <strong>Jaminan Terverifikasi: <?= e($customer['jenis_jaminan']) ?></strong>
                <p style="margin:2px 0 0;font-size:12px;opacity:0.9;">Akun Anda memenuhi syarat untuk mengajukan sewa iPhone.</p>
            </div>
        </div>
        <span class="badge ready">Siap Ajukan Sewa</span>
    </div>
<?php else: ?>
    <div class="alert error" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:24px;">!</span>
            <div>
                <strong>Bukti Foto Jaminan Belum Ada</strong>
                <p style="margin:2px 0 0;font-size:12px;opacity:0.9;">Sesuai regulasi keamanan, akun yang belum memiliki bukti foto jaminan tidak dapat mengajukan sewa.</p>
            </div>
        </div>
        <span class="badge hilang">Belum Bisa Mengajukan</span>
    </div>
<?php endif; ?>

<div class="content-grid" style="margin-top:24px;">
    <!-- Kolom Kiri: Foto Jaminan & Upload -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        <section class="panel" id="form-jaminan">
            <h2 class="panel-title">Dokumen Jaminan Identitas</h2>
            <p class="muted" style="margin-top:-10px;margin-bottom:18px;font-size:13px;">
                Foto identitas asli (KTP, SIM, Kartu Pelajar, atau Paspor) wajib diunggah sebagai jaminan sebelum mengajukan sewa.
            </p>

            <?php if ($hasJaminan): ?>
                <div style="margin-bottom:16px;padding:16px;background:rgba(5,9,19,0.5);border:1px solid var(--line);border-radius:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <span class="muted" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Foto Jaminan Terdaftar</span>
                        <span class="badge ready"><?= e($customer['jenis_jaminan']) ?></span>
                    </div>
                    <div style="position:relative;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);background:#000;text-align:center;">
                        <img src="<?= e($customer['foto_jaminan']) ?>" alt="Foto Jaminan <?= e($customer['nama_lengkap']) ?>" style="max-height:260px;width:100%;object-fit:contain;display:block;margin:auto;">
                    </div>
                    <p class="muted" style="font-size:11px;margin:10px 0 0;text-align:center;">
                        Foto jaminan ini digunakan secara otomatis untuk verifikasi setiap pengajuan sewa Anda.
                    </p>
                </div>

                <div style="padding:16px;background:rgba(54,214,154,0.06);border:1px solid rgba(54,214,154,0.25);border-radius:14px;display:flex;flex-direction:column;gap:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span class="muted" style="font-size:12px;">Jenis Jaminan</span>
                        <strong style="color:var(--text);font-size:13px;"><?= e($customer['jenis_jaminan']) ?></strong>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span class="muted" style="font-size:12px;">Status Dokumen</span>
                        <span class="badge ready" style="font-size:11px;"> Terkunci & Aktif</span>
                    </div>
                    <div style="padding-top:10px;border-top:1px solid rgba(255,255,255,0.08);font-size:12px;color:var(--muted);line-height:1.5;">
                         <strong>Perhatian:</strong> Foto dan jenis jaminan hanya dapat diunggah satu kali demi keamanan verifikasi transaksi. Data jaminan tidak dapat diganti secara mandiri. Apabila terdapat kesalahan dokumen atau butuh perubahan, silakan hubungi admin.
                    </div>
                </div>
            <?php else: ?>
                <div style="padding:28px 16px;text-align:center;border:2px dashed var(--line);border-radius:14px;margin-bottom:20px;background:rgba(5,9,19,0.3);">
                    <div style="font-size:42px;margin-bottom:8px;opacity:0.6;">🪪</div>
                    <strong style="display:block;margin-bottom:4px;color:var(--text);">Belum Ada Foto Jaminan</strong>
                    <p class="muted" style="font-size:12px;margin:0 auto;max-width:340px;line-height:1.5;">
                        Silakan pilih jenis jaminan dan unggah foto kartu identitas Anda. <br><span style="color:#fb7185;font-weight:600;">Penting: Pengunggahan hanya dapat dilakukan sekali dan tidak dapat diganti setelah tersimpan.</span>
                    </p>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_jaminan">
                    
                    <label>
                        Jenis Jaminan
                        <select name="jenis_jaminan" required>
                            <option value="" disabled selected>-- Pilih Jenis Kartu Identitas --</option>
                            <?php foreach ($jenisJaminanValid as $j): ?>
                                <option value="<?= e($j) ?>"><?= e($j) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Unggah Foto Jaminan
                        <input type="file" name="foto_jaminan" id="input_foto_jaminan" accept="image/jpeg,image/png,image/webp" required onchange="previewJaminan(this)">
                        <small class="muted">Format: JPG, PNG, atau WEBP. Maksimal 5MB. Pastikan foto terbaca jelas (hanya dapat diunggah 1 kali).</small>
                    </label>

                    <!-- Live Preview Client-side -->
                    <div id="live_preview_wrap" style="display:none;margin-top:10px;padding:12px;background:rgba(5,9,19,0.6);border:1px solid var(--line);border-radius:12px;">
                        <span class="muted" style="font-size:11px;display:block;margin-bottom:6px;font-weight:700;">PREVIEW FOTO YANG DIPILIH:</span>
                        <img id="live_preview_img" src="#" alt="Preview" style="max-height:180px;width:100%;object-fit:contain;border-radius:8px;">
                    </div>

                    <button class="primary-btn" style="margin-top:10px;">
                        Unggah & Aktifkan Transaksi
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <!-- Kolom Kanan: Biodata Pelanggan -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        <section class="panel">
            <h2 class="panel-title">Biodata Pelanggan</h2>
            <p class="muted" style="margin-top:-10px;margin-bottom:18px;font-size:13px;">
                Data identitas resmi sesuai dengan data penyewa terdaftar di sistem.
            </p>

            <form method="post">
                <input type="hidden" name="action" value="update_profile">

                <label>
                    Username Akun
                    <input type="text" value="<?= e($customer['username']) ?>" disabled style="opacity:0.7;cursor:not-allowed;">
                    <small class="muted">Username akun tidak dapat diubah.</small>
                </label>

                <div class="form-grid">
                    <label>
                        Nama Lengkap
                        <input type="text" name="nama_lengkap" value="<?= e($customer['nama_lengkap']) ?>" required placeholder="Nama sesuai KTP">
                    </label>
                    <label>
                        Nomor WhatsApp
                        <input type="tel" name="nomor_wa" value="<?= e($customer['nomor_wa']) ?>" required placeholder="Contoh: 08123456789">
                    </label>
                </div>

                <label>
                    Nomor NIK (KTP / Identitas)
                    <input type="text" name="nomor_nik" inputmode="numeric" value="<?= e($customer['nomor_nik']) ?>" required placeholder="16 digit nomor NIK">
                </label>

                <label>
                    Alamat Lengkap Domisili
                    <textarea name="alamat" rows="3" required placeholder="Alamat lengkap tempat tinggal saat ini"><?= e($customer['alamat']) ?></textarea>
                </label>

                <button class="primary-btn" style="margin-top:10px;">Simpan Perubahan Biodata</button>
            </form>
        </section>

        <!-- Panduan & Keamanan -->
        <section class="panel">
            <h2 class="panel-title">Ketentuan Transaksi Mandiri</h2>
            <ul style="margin:0;padding-left:18px;color:var(--muted);display:grid;gap:10px;font-size:13px;line-height:1.5;">
                <li>Identitas jaminan asli wajib dibawa saat pengambilan/penerimaan unit iPhone.</li>
                <li>Data identitas dan foto jaminan disimpan secara aman untuk keperluan proteksi sewa.</li>
                <li>Jika belum mengunggah foto jaminan, transaksi penyewaan tidak dapat diproses.</li>
            </ul>
        </section>
    </div>
</div>

<script>
function previewJaminan(input) {
    const wrap = document.getElementById('live_preview_wrap');
    const img = document.getElementById('live_preview_img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            wrap.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        wrap.style.display = 'none';
    }
}
</script>

<?php page_end(); ?>
