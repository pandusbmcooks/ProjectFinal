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

// Fetch units with ready, booked, and disewa status along with their expected ready datetime
$unitsQuery = $pdo->query("
    SELECT u.*, m.nama_model, m.harga_sewa_per_hari,
           (
               SELECT MAX(p.tgl_kembali_rencana) 
               FROM tb_penyewaan p 
               WHERE p.id_unit = u.id_unit AND p.status_transaksi IN ('pending', 'berjalan')
           ) AS tgl_ready_perkiraan
    FROM tb_unit_iphone u 
    JOIN tb_iphone_model m ON m.id_model = u.id_model 
    WHERE u.status IN ('ready', 'booked', 'disewa')
    ORDER BY m.harga_sewa_per_hari, u.id_unit
");
$allUnits = $unitsQuery->fetchAll();

// Date defaults and auto-adjust if unit_id is specified from catalog
$today = date('Y-m-d');
$defaultTglSewa = $today;
$defaultTglKembali = date('Y-m-d', strtotime('+1 day'));

$selectedUnit = null;
if ($selectedUnitId > 0) {
    foreach ($allUnits as $u) {
        if ((int)$u['id_unit'] === $selectedUnitId) {
            $selectedUnit = $u;
            break;
        }
    }
}

// If pre-selected unit is currently booked/rented with a future ready date, auto-adjust default dates
if ($selectedUnit && in_array($selectedUnit['status'], ['booked', 'disewa'], true) && !empty($selectedUnit['tgl_ready_perkiraan'])) {
    // Unit kembali pada tanggal rencana, baru bisa dipilih/dibooking pada H+1 (setelah transaksi selesai)
    $readyDate = date('Y-m-d', strtotime('+1 day', strtotime($selectedUnit['tgl_ready_perkiraan'])));
    if ($readyDate >= $today) {
        $defaultTglSewa = $readyDate;
        $defaultTglKembali = date('Y-m-d', strtotime('+1 day', strtotime($readyDate)));
    }
}

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
            <label>Pilih Unit iPhone
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

    <?php elseif (!$allUnits): ?>
        <div class="empty">Saat ini belum ada unit yang terdaftar untuk disewa. <a href="catalog.php">Kembali ke katalog</a></div>
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

        <form method="post" action="rent_user.php" id="formSewaUser">
            <input type="hidden" name="device_datetime" value="">
            
            <label>Pilih Unit iPhone
                <select name="id_unit" id="selectUnit" required onchange="onUnitChange()">
                    <option value="" disabled <?= !$selectedUnitId ? 'selected' : '' ?>>-- Pilih Unit iPhone --</option>
                    <?php foreach ($allUnits as $u):
                        $hasActiveRental = in_array($u['status'], ['booked', 'disewa'], true) && !empty($u['tgl_ready_perkiraan']);
                        $isReadyNow = ($u['status'] === 'ready' && (empty($u['tgl_ready_perkiraan']) || strtotime($u['tgl_ready_perkiraan']) <= time()));

                        if ($hasActiveRental) {
                            // Label select option menampilkan tanggal kembali unit (misal: 25 Sep 2026, tanpa jam)
                            $readyFormatted = date('d M Y', strtotime($u['tgl_ready_perkiraan']));
                            // Namun sistem baru mengizinkan booking pada next day / H+1 (misal: 26 Sep 2026)
                            $readyDay = date('Y-m-d', strtotime('+1 day', strtotime($u['tgl_ready_perkiraan'])));
                        } else {
                            $readyDay = $today;
                            $readyFormatted = 'Sekarang';
                        }
                        $baseLabel = $u['nama_model'] . ' ' . $u['penyimpanan'] . ' (' . $u['warna'] . ') [SN: ' . $u['nomor_seri'] . '] - Rp' . number_format($u['harga_sewa_per_hari'], 0, ',', '.') . '/hari';

                        $isAvailableForDate = $isReadyNow || ($defaultTglSewa >= $readyDay);
                        $optionLabel = $baseLabel;
                        if ($isReadyNow) {
                            $optionLabel .= ' (Ready Sekarang)';
                        } elseif ($defaultTglSewa >= $readyDay) {
                            $optionLabel .= ' (Akan ready pada ' . $readyFormatted . ')';
                        } else {
                            $stText = ($u['status'] === 'disewa') ? 'Sedang disewa' : 'Sedang dibooking';
                            $optionLabel .= ' (' . $stText . ' — baru ready pada ' . $readyFormatted . ')';
                        }
                        $isSelected = ($selectedUnitId === (int)$u['id_unit'] && $isAvailableForDate);
                    ?>
                        <option value="<?= $u['id_unit'] ?>"
                            data-harga="<?= (float)$u['harga_sewa_per_hari'] ?>"
                            data-is-ready="<?= $isReadyNow ? '1' : '0' ?>"
                            data-ready-day="<?= e($readyDay) ?>"
                            data-ready-formatted="<?= e($readyFormatted) ?>"
                            data-status="<?= e($u['status']) ?>"
                            data-base-label="<?= e($baseLabel) ?>"
                            <?= !$isAvailableForDate ? 'disabled' : '' ?>
                            <?= $isSelected ? 'selected' : '' ?>>
                            <?= e($optionLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <!-- Banner Ketersediaan Unit Interaktif -->
            <div id="unitAvailabilityBanner" style="display:none;margin-top:-6px;margin-bottom:6px;padding:12px 14px;border-radius:10px;font-size:12.5px;line-height:1.5;"></div>

            <!-- Peringatan jika pilihan unit otomatis direset -->
            <div id="unitResetAlert" class="alert error" style="display:none;margin-top:-4px;margin-bottom:10px;padding:10px 14px;font-size:12px;border-radius:8px;"></div>

            <div class="form-grid">
                <label>Tanggal Mulai Sewa
                    <input type="date" name="tgl_sewa" id="inputTglSewa" value="<?= e($defaultTglSewa) ?>" min="<?= e($today) ?>" required onchange="onTglSewaChange()">
                </label>
                <label>Tanggal Selesai Sewa (Pengembalian)
                    <input type="date" name="tgl_kembali" id="inputTglKembali" value="<?= e($defaultTglKembali) ?>" min="<?= e($defaultTglSewa) ?>" required onchange="calculateRentalDuration()">
                </label>
            </div>

            <!-- Estimasi Durasi Sewa Dinamis -->
            <div id="durasiInfoBox" style="padding:10px 14px;background:rgba(255,255,255,0.04);border:1px solid var(--line);border-radius:10px;font-size:13px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <span>📅 Durasi Sewa: <strong id="durasiDaysText" style="color:#80f4c4;">1 Hari</strong></span>
                <span id="estimasiBiayaText" style="color:var(--muted);font-size:12px;">Pilih unit untuk melihat estimasi total</span>
            </div>

            <label>Jenis Jaminan Fisik (Ditinggalkan di Toko)
                <select name="jenis_jaminan" required>
                    <?php foreach ($jenisJaminanValid as $jenis): ?>
                        <option value="<?= e($jenis) ?>" <?= ($customer['jenis_jaminan'] ?? '') === $jenis ? 'selected' : '' ?>>
                            <?= e($jenis) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div style="margin-top:4px;padding:12px 14px;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);border-radius:10px;font-size:12px;color:var(--muted);line-height:1.5;">
                <strong>Alur Transaksi:</strong> Setelah formulir ini dikirim, pengajuan sewa unit akan tercatat dalam antrean booking. Waktu sewa akan <strong>resmi mulai berjalan</strong> setelah disetujui admin saat serah terima unit di toko.
            </div>

            <button class="primary-btn" style="margin-top:16px">Kirim Pengajuan Transaksi Sewa →</button>
        </form>

        <script>
        function onUnitChange() {
            document.getElementById('unitResetAlert').style.display = 'none';
            updateAvailabilityBanner();
            calculateRentalDuration();
        }

        function onTglSewaChange() {
            const tglSewa = document.getElementById('inputTglSewa');
            const tglKembali = document.getElementById('inputTglKembali');
            const unitSelect = document.getElementById('selectUnit');
            const resetAlert = document.getElementById('unitResetAlert');

            if (!tglSewa || !unitSelect) return;

            // Pastikan tanggal kembali minimal sama dengan tanggal mulai sewa
            tglKembali.min = tglSewa.value;
            if (tglKembali.value < tglSewa.value) {
                tglKembali.value = tglSewa.value;
            }

            const chosenDate = tglSewa.value;
            let currentSelectedWasReset = false;
            let resetReason = '';

            // Update status dan keterangan setiap opsi unit
            Array.from(unitSelect.options).forEach((opt, idx) => {
                if (idx === 0) return; // Lewati placeholder

                const isReadyNow = opt.dataset.isReady === '1';
                const readyDay = opt.dataset.readyDay;
                const readyFormatted = opt.dataset.readyFormatted;
                const baseLabel = opt.dataset.baseLabel;
                const status = opt.dataset.status;

                if (isReadyNow) {
                    opt.disabled = false;
                    opt.textContent = baseLabel + ' (Ready Sekarang)';
                } else {
                    if (chosenDate >= readyDay) {
                        opt.disabled = false;
                        opt.textContent = baseLabel + ' (Akan ready pada ' + readyFormatted + ')';
                    } else {
                        opt.disabled = true;
                        const stText = (status === 'disewa') ? 'Sedang disewa' : 'Sedang dibooking';
                        opt.textContent = baseLabel + ' (' + stText + ' — baru ready pada ' + readyFormatted + ')';

                        // Jika opsi yang sedang terpilih menjadi disabled
                        if (unitSelect.selectedIndex === idx) {
                            currentSelectedWasReset = true;
                            resetReason = 'Unit yang dipilih ' + stText.toLowerCase() + ' dan baru akan ready pada ' + readyFormatted + '. Pilihan unit telah di-reset.';
                        }
                    }
                }
            });

            if (currentSelectedWasReset) {
                unitSelect.value = '';
                resetAlert.textContent = resetReason;
                resetAlert.style.display = 'block';
            } else {
                resetAlert.style.display = 'none';
            }

            updateAvailabilityBanner();
            calculateRentalDuration();
        }

        function updateAvailabilityBanner() {
            const unitSelect = document.getElementById('selectUnit');
            const banner = document.getElementById('unitAvailabilityBanner');
            const tglSewa = document.getElementById('inputTglSewa');

            if (!unitSelect || !banner) return;

            if (unitSelect.selectedIndex <= 0) {
                banner.style.display = 'none';
                return;
            }

            const opt = unitSelect.options[unitSelect.selectedIndex];
            const isReadyNow = opt.dataset.isReady === '1';
            const readyFormatted = opt.dataset.readyFormatted;
            const status = opt.dataset.status;
            const chosenDate = tglSewa ? tglSewa.value : '';

            if (isReadyNow) {
                banner.style.display = 'block';
                banner.style.background = 'rgba(54, 214, 154, 0.12)';
                banner.style.border = '1px solid rgba(54, 214, 154, 0.3)';
                banner.style.color = '#80f4c4';
                banner.innerHTML = '<strong>✓ Unit Ready Sekarang:</strong> Unit tersedia dan siap diserahkan setelah pengajuan disetujui admin di toko.';
            } else {
                const stText = (status === 'disewa') ? 'sedang disewa' : 'sedang dibooking';
                banner.style.display = 'block';
                banner.style.background = 'rgba(59, 130, 246, 0.12)';
                banner.style.border = '1px solid rgba(59, 130, 246, 0.3)';
                banner.style.color = '#93c5fd';
                banner.innerHTML = '<strong>📅 Jadwal Ketersediaan Unit:</strong> Unit saat ini ' + stText + ' dan dijadwalkan ready kembali pada <strong>' + readyFormatted + '</strong>. Pengajuan sewa Anda untuk tanggal <strong>' + chosenDate + '</strong> dapat diproses.';
            }
        }

        function calculateRentalDuration() {
            const tglSewa = document.getElementById('inputTglSewa');
            const tglKembali = document.getElementById('inputTglKembali');
            const durasiDaysText = document.getElementById('durasiDaysText');
            const estimasiBiayaText = document.getElementById('estimasiBiayaText');
            const unitSelect = document.getElementById('selectUnit');

            if (!tglSewa || !tglKembali) return;

            // Minimal tanggal kembali tidak boleh sebelum tanggal sewa
            tglKembali.min = tglSewa.value;

            const start = new Date(tglSewa.value);
            const end = new Date(tglKembali.value);

            let diffDays = 1;
            if (!isNaN(start) && !isNaN(end)) {
                const diffTime = end - start;
                diffDays = Math.max(1, Math.round(diffTime / (1000 * 60 * 60 * 24)));
            }

            if (durasiDaysText) {
                durasiDaysText.textContent = diffDays + ' Hari';
            }

            if (unitSelect && unitSelect.selectedIndex > 0) {
                const selectedOpt = unitSelect.options[unitSelect.selectedIndex];
                const harga = parseFloat(selectedOpt.getAttribute('data-harga') || 0);
                if (harga > 0 && estimasiBiayaText) {
                    const total = diffDays * harga;
                    estimasiBiayaText.innerHTML = 'Estimasi: <strong style="color:#80f4c4;">Rp' + total.toLocaleString('id-ID') + '</strong>';
                }
            } else if (estimasiBiayaText) {
                estimasiBiayaText.textContent = 'Pilih unit untuk melihat estimasi total';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateAvailabilityBanner();
            calculateRentalDuration();
        });
        </script>
    <?php endif; ?>
</section>

<?php page_end(); ?>

