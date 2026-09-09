<?php
require_once 'includes/auth.php';
require_role('user');

$pdo = db();
$userId = user()['id_user'];
$selectedUnitId = (int)($_GET['unit_id'] ?? 0);
$jenisJaminanValid = ['KTP', 'Kartu Pelajar', 'SIM', 'Paspor', 'Kartu Identitas Lainnya'];

// Check customer and collateral status
$cSt = $pdo->prepare('SELECT * FROM tb_pelanggan WHERE id_user = ?');
$cSt->execute([$userId]);
$customer = $cSt->fetch();

$hasJaminan = !empty($customer['foto_jaminan']) && file_exists(__DIR__ . '/' . $customer['foto_jaminan']);

$readyUnits = $pdo->query("SELECT u.*, m.nama_model, m.penyimpanan, m.harga_sewa_per_hari FROM tb_unit_iphone u JOIN tb_iphone_model m ON m.id_model=u.id_model WHERE u.status='ready' ORDER BY m.harga_sewa_per_hari")->fetchAll();

require 'includes/layout.php';
page_start('Pengajuan Sewa iPhone');
?>

<section class="hero">
    <div>
        <p class="eyebrow">PENGAJUAN SEWA IPHONE</p>
        <h1>Ajukan sewa iPhone impianmu.</h1>
        <p>Lengkapi formulir pengajuan sewa. Transaksi akan resmi berjalan dan waktu sewa dihitung setelah disetujui oleh admin.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a class="outline-btn" href="profile.php">Lihat Profil Saya</a>
        <a class="outline-btn" href="catalog.php">Kembali ke katalog</a>
    </div>
</section>

<section class="panel" style="max-width:780px;margin:auto">
    <h2 class="panel-title">Formulir Pengajuan Sewa</h2>

    <?php if (!$hasJaminan): ?>
        <!-- Peringatan jika belum memiliki bukti foto jaminan -->
        <div class="alert error" style="margin-bottom:24px;padding:20px;border-radius:16px;">
            <div style="display:flex;align-items:flex-start;gap:14px;">
                <span style="font-size:28px;line-height:1;">🔒</span>
                <div>
                    <strong style="font-size:16px;display:block;margin-bottom:6px;">Pengajuan Sewa Belum Aktif</strong>
                    <p style="margin:0 0 14px;color:#ffcbd2;line-height:1.5;">
                        Akun Anda belum memiliki <strong>bukti foto jaminan</strong> (KTP/SIM/Kartu Pelajar). Sesuai kebijakan rental, setiap akun wajib memiliki foto jaminan yang terdaftar di profil untuk dapat mengajukan sewa unit.
                    </p>
                    <a class="primary-btn compact" href="profile.php#form-jaminan" style="background:#fb7185;color:#fff;">
                        Unggah Foto Jaminan di Profil Sekarang →
                    </a>
                </div>
            </div>
        </div>

        <fieldset disabled style="border:none;padding:0;margin:0;opacity:0.45;cursor:not-allowed;pointer-events:none;">
            <label>Pilih Unit iPhone Ready
                <select name="id_unit" required>
                    <option value="" disabled selected>-- Lengkapi foto jaminan pada profil terlebih dahulu --</option>
                </select>
            </label>
            <div class="form-grid" style="margin-top:14px;">
                <label>Lama Sewa (Hari)
                    <input type="number" name="lama_sewa" value="1" disabled>
                </label>
                <label>Jenis Jaminan
                    <select name="jenis_jaminan" disabled>
                        <option>Belum terverifikasi</option>
                    </select>
                </label>
            </div>
            <button class="primary-btn" style="margin-top:16px;" disabled>Pengajuan Terkunci (Perlu Foto Jaminan)</button>
        </fieldset>

    <?php elseif (!$readyUnits): ?>
        <div class="empty">Saat ini belum ada unit yang siap disewa. <a href="catalog.php">Kembali ke katalog</a></div>
    <?php else: ?>
        <!-- Info Jaminan Terverifikasi -->
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;background:rgba(54,214,154,0.1);border:1px solid rgba(54,214,154,0.25);border-radius:14px;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:10px;">
               
                <div>
                    <strong style="color:#80f4c4;">Jaminan Identitas Aktif: <?= e($customer['jenis_jaminan']) ?></strong>
                    <small class="muted" style="display:block;">Tercatat atas nama: <?= e($customer['nama_lengkap']) ?> (NIK: <?= e($customer['nomor_nik']) ?>)</small>
                </div>
            </div>
            <a href="profile.php" class="outline-btn compact" style="font-size:11px;">Lihat Profil</a>
        </div>

        <form method="post" action="rent_user.php">
            <input type="hidden" name="device_datetime" value="">
            
            <label>Pilih Unit iPhone Ready
                <select name="id_unit" required>
                    <option value="" disabled <?= !$selectedUnitId ? 'selected' : '' ?>>-- Pilih Unit iPhone --</option>
                    <?php foreach ($readyUnits as $u): ?>
                        <option value="<?= $u['id_unit'] ?>" <?= $selectedUnitId === (int)$u['id_unit'] ? 'selected' : '' ?>>
                            <?= e($u['nama_model'] . ' ' . $u['penyimpanan'] . ' (' . $u['warna'] . ')') ?> [SN: <?= e($u['nomor_seri']) ?>] - Rp<?= number_format($u['harga_sewa_per_hari'], 0, ',', '.') ?>/hari
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-grid">
                <label>Lama Sewa (Hari)
                    <input type="number" name="lama_sewa" min="1" value="1" required>
                </label>
                <label>Jenis Jaminan Fisik (Ditinggalkan di Toko)
                    <select name="jenis_jaminan" required>
                        <?php foreach ($jenisJaminanValid as $jenis): ?>
                            <option value="<?= e($jenis) ?>" <?= ($customer['jenis_jaminan'] ?? '') === $jenis ? 'selected' : '' ?>>
                                <?= e($jenis) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div style="margin-top:14px;padding:12px 14px;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);border-radius:10px;font-size:12px;color:var(--muted);line-height:1.5;">
                 <strong>Alur Transaksi:</strong> Setelah formulir ini dikirim, status transaksi adalah <em>Menunggu Persetujuan</em>. Waktu transaksi <strong>resmi mulai berjalan saat admin menyetujui</strong> pengajuan Anda saat serah terima unit.
            </div>

            <button class="primary-btn" style="margin-top:16px">Kirim Pengajuan Transaksi Sewa →</button>
        </form>
    <?php endif; ?>
</section>

<?php page_end(); ?>
