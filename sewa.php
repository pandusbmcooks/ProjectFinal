<?php
require_once 'includes/auth.php';
require_role('admin');
$pdo = db();

$rows = $pdo->query("SELECT p.*, c.nama_lengkap, c.nomor_wa, m.nama_model, m.penyimpanan, u.warna, u.nomor_seri
    FROM tb_penyewaan p 
    JOIN tb_pelanggan c ON c.id_pelanggan = p.id_pelanggan
    JOIN tb_unit_iphone u ON u.id_unit = p.id_unit 
    JOIN tb_iphone_model m ON m.id_model = u.id_model
    ORDER BY p.id_sewa DESC")->fetchAll();

$pendingRows = array_values(array_filter($rows, fn($r) => $r['status_transaksi'] === 'pending'));

require 'includes/layout.php';
page_start('Riwayat & Pengajuan Sewa', true); ?>

<div class="section-head" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <h2>Daftar Transaksi Penyewaan</h2>
    <a class="primary-btn compact" href="buat_transaksi.php">+ Buat transaksi langsung</a>
</div>

<!-- PANEL PENGAJUAN SEWA MASUK (PENDING) -->
<?php if (!empty($pendingRows)): ?>
    <section class="panel" style="border:1px solid rgba(245,158,11,0.4);background:rgba(245,158,11,0.03);margin-bottom:28px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;">🔔</span>
                <div>
                    <h2 class="panel-title" style="margin:0;color:#fbbf24;font-size:16px;">Pengajuan Sewa Menunggu Persetujuan (<?= count($pendingRows) ?>)</h2>
                    <small class="muted">Pelanggan telah mengajukan sewa. Transaksi akan <strong>mulai berjalan saat Anda menyetujui</strong> pengajuan saat serah terima unit.</small>
                </div>
            </div>
            <span class="badge" style="background:rgba(245,158,11,0.2);color:#fbbf24;border:1px solid rgba(245,158,11,0.4);font-weight:700;">
                Perlu Konfirmasi
            </span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Unit yang Diajukan</th>
                        <th>Durasi Diajukan</th>
                        <th>Jaminan Profil</th>
                        <th>Waktu Mengajukan</th>
                        <th>Aksi Persetujuan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRows as $r):
                        $durasiHari = max(1, round((strtotime($r['tgl_kembali_rencana']) - strtotime($r['tgl_sewa'])) / 86400));
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($r['nama_lengkap']) ?></strong><br>
                            <small class="muted">WA: <?= e($r['nomor_wa'] ?: '-') ?></small>
                        </td>
                        <td>
                            <strong><?= e($r['nama_model'] . ' ' . $r['penyimpanan']) ?></strong><br>
                            <small class="muted"><?= e($r['warna']) ?> [SN: <?= e($r['nomor_seri']) ?>]</small>
                        </td>
                        <td>
                            <strong style="color:var(--text);"><?= $durasiHari ?> Hari</strong><br>
                            <small class="muted">(Mulai saat disetujui)</small>
                        </td>
                        <td>
                            <strong><?= e($r['jenis_jaminan']) ?></strong>
                            <?php if (!empty($r['foto_jaminan']) && file_exists(__DIR__ . '/' . $r['foto_jaminan'])): ?>
                                <a href="<?= e($r['foto_jaminan']) ?>" target="_blank" class="outline-btn compact" style="font-size:10px;padding:2px 6px;margin-left:4px;">Foto ↗</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= date('d M Y H:i', strtotime($r['tgl_sewa'])) ?>
                        </td>
                        <td class="actions" style="white-space:nowrap;">
                            <button type="button" class="primary-btn compact" style="background:#10b981;color:#fff;" onclick="openApprovalModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>, <?= $durasiHari ?>)">
                                 Setujui & Serahkan
                            </button>
                            <form method="post" action="rentals.php" style="display:inline;margin-left:4px;">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= $r['id_sewa'] ?>">
                                <button class="danger-btn compact" data-confirm="Tolak pengajuan sewa dari <?= e($r['nama_lengkap']) ?>?">✕ Tolak</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<!-- TABEL SEMUA TRANSAKSI SEWA -->
<section class="panel table-wrap">
    <div style="margin-bottom:12px;">
        <h2 class="panel-title" style="margin:0;">Riwayat & Transaksi Berjalan</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Pelanggan / Unit</th>
                <th>Periode sewa</th>
                <th>Jaminan</th>
                <th>Bukti Ambil</th>
                <th>Status</th>
                <th>Denda</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r):
                $isPending = $r['status_transaksi'] === 'pending';
                $isBerjalan = $r['status_transaksi'] === 'berjalan';
                $durasiHari = max(1, round((strtotime($r['tgl_kembali_rencana']) - strtotime($r['tgl_sewa'])) / 86400));
                $minutesLate = $isBerjalan ? max(0, (time() - strtotime($r['tgl_kembali_rencana'])) / 60) : 0;
                $estimatedFine = ceil($minutesLate / 60) * (float)$r['tarif_denda_per_jam'];
            ?>
            <tr>
                <td>
                    <strong><?= e($r['nama_lengkap']) ?></strong><br>
                    <small class="muted"><?= e($r['nama_model'] . ' ' . $r['penyimpanan'] . ' - ' . $r['warna']) ?> [SN: <?= e($r['nomor_seri']) ?>]</small>
                </td>
                <td>
                    <?php if ($isPending): ?>
                        <span class="badge pending" style="background:#f59e0b;color:#000;font-size:10px;">Menunggu Persetujuan</span><br>
                        <small class="muted">Durasi: <?= $durasiHari ?> hari (mulai saat disetujui)</small>
                    <?php else: ?>
                        <?= date('d M Y H:i', strtotime($r['tgl_sewa'])) ?><br>
                        <small class="muted">s/d <?= date('d M Y H:i', strtotime($r['tgl_kembali_rencana'])) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['jenis_jaminan']): ?>
                        <strong><?= e($r['jenis_jaminan']) ?></strong>
                        <?php if ($r['foto_jaminan'] && file_exists(__DIR__ . '/' . $r['foto_jaminan'])): ?>
                            <a href="<?= e($r['foto_jaminan']) ?>" target="_blank" class="outline-btn compact" style="font-size:10px;padding:3px 6px;margin-left:4px;">Foto ↗</a>
                        <?php endif ?>
                    <?php else: ?>
                        <span class="muted">Belum dicatat</span>
                    <?php endif ?>
                </td>
                <td>
                    <?php if (!empty($r['foto_bukti_ambil']) && file_exists(__DIR__ . '/' . $r['foto_bukti_ambil'])): ?>
                        <a href="<?= e($r['foto_bukti_ambil']) ?>" target="_blank" class="outline-btn compact" style="font-size:10px;padding:3px 6px;">Bukti ↗</a>
                    <?php else: ?>
                        <span class="muted">-</span>
                    <?php endif ?>
                </td>
                <td>
                    <?php if ($isPending): ?>
                        <span class="badge pending" style="background:#f59e0b;color:#000;font-weight:700;">pending</span>
                    <?php else: ?>
                        <span class="badge <?= e($r['status_transaksi']) ?>"><?= e($r['status_transaksi']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isBerjalan): ?>
                        <strong class="<?= $estimatedFine > 0 ? 'hilang' : '' ?>">Rp<?= number_format($estimatedFine, 0, ',', '.') ?></strong><br>
                        <small class="muted">estimasi saat ini</small>
                    <?php elseif ($isPending): ?>
                        <span class="muted">-</span>
                    <?php else: ?>
                        Rp<?= number_format($r['total_denda'], 0, ',', '.') ?>
                    <?php endif ?>
                </td>
                <td>
                    <?php if ($isBerjalan): ?>
                        <form method="post" action="rentals.php">
                            <input type="hidden" name="action" value="return">
                            <input type="hidden" name="id" value="<?= $r['id_sewa'] ?>">
                            <input type="hidden" name="device_datetime" value="">
                            <button class="primary-btn compact" data-confirm="Proses pengembalian sekarang?">Proses kembali</button>
                        </form>
                    <?php elseif ($isPending): ?>
                        <div style="display:flex;gap:4px;white-space:nowrap;">
                            <button type="button" class="primary-btn compact" style="background:#10b981;color:#fff;" onclick="openApprovalModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>, <?= $durasiHari ?>)">
                                 Setujui
                            </button>
                            <form method="post" action="rentals.php" style="display:inline;">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= $r['id_sewa'] ?>">
                                <button class="danger-btn compact" data-confirm="Tolak pengajuan sewa ini?">✕</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <?= date('d M Y H:i', strtotime($r['tgl_kembali_aktual'])) ?>
                    <?php endif ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="empty">Belum ada transaksi sewa.</td></tr>
            <?php endif ?>
        </tbody>
    </table>
</section>

<!-- MODAL PERSETUJUAN SEWA & OPEN CAM SERAH TERIMA -->
<div class="modal-overlay" id="modalApprovalOverlay" onclick="if(event.target===this) closeApprovalModal()">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-header">
            <h3> Persetujuan & Bukti Pengambilan Unit</h3>
            <button class="modal-close" onclick="closeApprovalModal()">×</button>
        </div>

        <div style="background:rgba(255,255,255,0.03);border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:13px;line-height:1.5;">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span class="muted">Pelanggan:</span>
                <strong id="modalCustomerName" style="color:var(--text);">-</strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span class="muted">Unit iPhone:</span>
                <strong id="modalUnitName" style="color:var(--text);">-</strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span class="muted">Durasi Sewa:</span>
                <strong id="modalDurasiText" style="color:#80f4c4;">-</strong>
            </div>
            <div style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--line);font-size:12px;color:#fbbf24;">
            Waktu mulai transaksi resmi dihitung <strong>mulai saat persetujuan disimpan</strong>.
            </div>
        </div>

        <!-- Open Cam Box di Modal -->
        <div style="background:rgba(5,9,19,0.5);border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <strong style="font-size:13px;display:flex;align-items:center;gap:6px;">
                     Foto Bukti Pengambilan Unit (Open Cam)
                </strong>
                <span id="modalCamStatus" class="badge" style="font-size:10px;background:rgba(239,68,68,0.2);color:#fca5a5;border:1px solid rgba(239,68,68,0.4);">Wajib Foto Bukti</span>
            </div>
            <p class="muted" style="margin-top:-4px;margin-bottom:12px;font-size:11px;">
                Ambil foto penyewa saat menerima unit sebagai bukti fisik serah terima sebelum transaksi disetujui.
            </p>

            <canvas id="modalCanvas" style="display:none;"></canvas>
            <input type="file" id="modalFileInput" accept="image/*" style="display:none;" onchange="handleModalFile(this)">

            <!-- Initial Box (Jika kamera belum aktif) -->
            <div id="modalCamInitial" style="text-align:center;padding:16px;border:2px dashed var(--line);border-radius:10px;background:rgba(0,0,0,0.2);">
                <div style="font-size:28px;margin-bottom:6px;opacity:0.75;">📷</div>
                <p class="muted" style="font-size:12px;margin:0 0 10px;">Buka kamera untuk memotret penyewa saat serah terima unit:</p>
                <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                    <button type="button" class="primary-btn compact" onclick="startModalCamera()">
                         Buka Kamera (Open Cam)
                    </button>
                    <button type="button" class="outline-btn compact" onclick="document.getElementById('modalFileInput').click()">
                         Unggah File Foto
                    </button>
                </div>
            </div>

            <!-- Video Stream Box -->
            <div id="modalCamStream" style="display:none;text-align:center;">
                <div style="position:relative;border-radius:8px;overflow:hidden;background:#000;border:1px solid var(--line);max-height:240px;display:flex;align-items:center;justify-content:center;">
                    <video id="modalWebcamVideo" autoplay playsinline style="width:100%;max-height:240px;object-fit:cover;"></video>
                </div>
                <div style="display:flex;gap:8px;justify-content:center;margin-top:10px;flex-wrap:wrap;">
                    <button type="button" class="primary-btn compact" style="background:#10b981;color:#fff;font-weight:700;" onclick="snapModalPhoto()">
                         Jepret Foto Pengambilan
                    </button>
                    <button type="button" class="outline-btn compact" onclick="document.getElementById('modalFileInput').click()">
                        📁 File Foto
                    </button>
                    <button type="button" class="outline-btn compact" onclick="stopModalCamera()">
                        ✖ Tutup Kamera
                    </button>
                </div>
            </div>

            <!-- Snapshot Preview Box -->
            <div id="modalCamPreview" style="display:none;text-align:center;">
                <div style="position:relative;border-radius:8px;overflow:hidden;background:#000;border:1px solid rgba(54,214,154,0.3);max-height:240px;display:inline-block;width:100%;">
                    <img id="modalSnapshotImg" src="#" alt="Bukti Serah Terima" style="max-height:220px;width:100%;object-fit:contain;display:block;margin:auto;">
                </div>
                <div style="display:flex;gap:8px;justify-content:center;margin-top:10px;align-items:center;">
                    <span class="badge ready" style="font-size:11px;">✓ Foto Bukti Pengambilan Siap</span>
                    <button type="button" class="outline-btn compact" style="font-size:11px;" onclick="retakeModalPhoto()">
                         Foto Ulang
                    </button>
                </div>
            </div>

            <!-- Fallback Error Alert -->
            <div id="modalCamError" style="display:none;margin-top:10px;padding:10px 12px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:8px;font-size:11px;color:#fca5a5;text-align:center;">
                Kamera tidak terdeteksi atau izin akses kamera ditolak. Silakan gunakan tombol <strong>"Unggah File Foto"</strong> atau <a href="javascript:void(0)" onclick="bypassPhotoRequirement()" style="color:#6ee7b7;text-decoration:underline;">tetap setujui tanpa foto</a>.
            </div>
        </div>

        <form method="post" action="rentals.php" id="formAcceptRental">
            <input type="hidden" name="action" value="accept">
            <input type="hidden" name="id" id="acceptRentalId" value="">
            <input type="hidden" name="foto_bukti_ambil" id="acceptFotoBukti" value="">
            <input type="hidden" name="device_datetime" value="">

            <div style="display:flex;gap:10px;justify-content:flex-end;align-items:center;">
                <button type="button" class="outline-btn" onclick="closeApprovalModal()">Batal</button>
                <button type="submit" id="btnSubmitApproval" class="primary-btn" style="background:#10b981;color:#fff;opacity:0.55;cursor:not-allowed;" disabled>
                     Ambil Foto Bukti Dahulu
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let modalMediaStream = null;

function openApprovalModal(rentalData, durasiHari) {
    document.getElementById('acceptRentalId').value = rentalData.id_sewa;
    document.getElementById('modalCustomerName').innerText = rentalData.nama_lengkap + (rentalData.nomor_wa ? ' (' + rentalData.nomor_wa + ')' : '');
    document.getElementById('modalUnitName').innerText = rentalData.nama_model + ' ' + rentalData.penyimpanan + ' (' + rentalData.warna + ') [SN: ' + (rentalData.nomor_seri || '-') + ']';
    document.getElementById('modalDurasiText').innerText = durasiHari + ' Hari';

    // Reset camera state
    document.getElementById('acceptFotoBukti').value = '';
    document.getElementById('modalCamInitial').style.display = 'none';
    document.getElementById('modalCamStream').style.display = 'none';
    document.getElementById('modalCamPreview').style.display = 'none';
    document.getElementById('modalCamError').style.display = 'none';
    document.getElementById('modalCamStatus').className = 'badge';
    document.getElementById('modalCamStatus').style.background = 'rgba(239,68,68,0.2)';
    document.getElementById('modalCamStatus').style.color = '#fca5a5';
    document.getElementById('modalCamStatus').innerText = 'Wajib Foto Bukti';

    // Reset button to disabled
    const submitBtn = document.getElementById('btnSubmitApproval');
    submitBtn.setAttribute('disabled', 'disabled');
    submitBtn.style.opacity = '0.55';
    submitBtn.style.cursor = 'not-allowed';
    submitBtn.innerText = ' Ambil Foto Bukti Dahulu';

    document.getElementById('modalApprovalOverlay').classList.add('active');

    // Automatically open camera right away!
    startModalCamera();
}

function closeApprovalModal() {
    stopModalCamera();
    document.getElementById('modalApprovalOverlay').classList.remove('active');
}

function unlockApprovalButton() {
    const submitBtn = document.getElementById('btnSubmitApproval');
    submitBtn.removeAttribute('disabled');
    submitBtn.style.opacity = '1';
    submitBtn.style.cursor = 'pointer';
    submitBtn.innerText = '✓ Setujui & Mulai Transaksi Sekarang';

    const statusBadge = document.getElementById('modalCamStatus');
    statusBadge.className = 'badge ready';
    statusBadge.style.background = '';
    statusBadge.style.color = '';
    statusBadge.innerText = 'Foto Terambil';
}

function bypassPhotoRequirement() {
    unlockApprovalButton();
    document.getElementById('btnSubmitApproval').innerText = '✓ Setujui Tanpa Foto Bukti';
    document.getElementById('modalCamStatus').innerText = 'Dilewati';
}

async function startModalCamera() {
    const video = document.getElementById('modalWebcamVideo');
    try {
        modalMediaStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'environment' },
            audio: false
        });
        video.srcObject = modalMediaStream;
        await video.play();

        document.getElementById('modalCamInitial').style.display = 'none';
        document.getElementById('modalCamPreview').style.display = 'none';
        document.getElementById('modalCamError').style.display = 'none';
        document.getElementById('modalCamStream').style.display = 'block';
    } catch (err) {
        console.error('Kamera error:', err);
        document.getElementById('modalCamStream').style.display = 'none';
        document.getElementById('modalCamInitial').style.display = 'block';
        document.getElementById('modalCamError').style.display = 'block';
    }
}

function stopModalCamera() {
    if (modalMediaStream) {
        modalMediaStream.getTracks().forEach(t => t.stop());
        modalMediaStream = null;
    }
    const hasPhoto = document.getElementById('acceptFotoBukti').value;
    document.getElementById('modalCamStream').style.display = 'none';
    if (hasPhoto) {
        document.getElementById('modalCamPreview').style.display = 'block';
    } else {
        document.getElementById('modalCamInitial').style.display = 'block';
    }
}

function snapModalPhoto() {
    const video = document.getElementById('modalWebcamVideo');
    const canvas = document.getElementById('modalCanvas');
    const snapshotImg = document.getElementById('modalSnapshotImg');
    const inputHidden = document.getElementById('acceptFotoBukti');

    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.88);
    inputHidden.value = dataUrl;
    snapshotImg.src = dataUrl;

    stopModalCamera();

    document.getElementById('modalCamStream').style.display = 'none';
    document.getElementById('modalCamPreview').style.display = 'block';

    unlockApprovalButton();
}

function retakeModalPhoto() {
    document.getElementById('acceptFotoBukti').value = '';
    document.getElementById('modalCamStatus').className = 'badge';
    document.getElementById('modalCamStatus').style.background = 'rgba(239,68,68,0.2)';
    document.getElementById('modalCamStatus').style.color = '#fca5a5';
    document.getElementById('modalCamStatus').innerText = 'Wajib Foto Bukti';

    const submitBtn = document.getElementById('btnSubmitApproval');
    submitBtn.setAttribute('disabled', 'disabled');
    submitBtn.style.opacity = '0.55';
    submitBtn.style.cursor = 'not-allowed';
    submitBtn.innerText = '📸 Ambil Foto Bukti Dahulu';

    startModalCamera();
}

function handleModalFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const dataUrl = e.target.result;
            document.getElementById('acceptFotoBukti').value = dataUrl;
            document.getElementById('modalSnapshotImg').src = dataUrl;
            stopModalCamera();
            document.getElementById('modalCamInitial').style.display = 'none';
            document.getElementById('modalCamStream').style.display = 'none';
            document.getElementById('modalCamPreview').style.display = 'block';
            unlockApprovalButton();
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php page_end(); ?>
