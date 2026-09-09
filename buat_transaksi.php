<?php
require_once 'includes/auth.php';
require_role('admin');

$pdo = db();
$jenisJaminanValid = ['KTP', 'Kartu Pelajar', 'SIM', 'Paspor', 'Kartu Identitas Lainnya'];

$customers = $pdo->query('SELECT * FROM tb_pelanggan ORDER BY nama_lengkap')->fetchAll();
$ready = $pdo->query("SELECT u.*, m.nama_model, m.penyimpanan FROM tb_unit_iphone u JOIN tb_iphone_model m ON m.id_model=u.id_model WHERE u.status='ready' ORDER BY m.nama_model")->fetchAll();

// Add jaminan status helper to each customer
foreach ($customers as &$c) {
    $c['has_valid_jaminan'] = !empty($c['foto_jaminan']) && file_exists(__DIR__ . '/' . $c['foto_jaminan']);
}
unset($c);

require 'includes/layout.php';
page_start('Buat Transaksi Sewa', true);
?>

<div class="content-grid">
    <section class="panel">
        <h2 class="panel-title">Buat Transaksi Baru (Admin)</h2>
        
        <form method="post" action="rentals.php" enctype="multipart/form-data" id="adminRentForm">
            <input type="hidden" name="device_datetime" value="">

            <label>
                Pilih Pelanggan
                <select name="id_pelanggan" id="selectPelanggan" required onchange="updateCustomerJaminanStatus()">
                    <option value="" disabled selected>-- Pilih Pelanggan --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id_pelanggan'] ?>" 
                                data-has-jaminan="<?= $c['has_valid_jaminan'] ? '1' : '0' ?>"
                                data-jenis-jaminan="<?= e($c['jenis_jaminan'] ?? '') ?>"
                                data-foto-jaminan="<?= e($c['foto_jaminan'] ?? '') ?>"
                                data-nama="<?= e($c['nama_lengkap']) ?>"
                                data-nik="<?= e($c['nomor_nik']) ?>">
                            <?= e($c['nama_lengkap']) ?> - <?= e($c['nomor_wa']) ?> <?= $c['has_valid_jaminan'] ? ' [✓ Jaminan: ' . e($c['jenis_jaminan']) . ']' : ' [⚠️ Belum Ada Jaminan]' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <!-- Box Status Jaminan Pelanggan Dinamis -->
            <div id="statusJaminanBox" style="display:none;margin-top:4px;margin-bottom:8px;"></div>

            <label>
                Unit Tersedia
                <select name="id_unit" required>
                    <option value="" disabled selected>-- Pilih Unit iPhone --</option>
                    <?php foreach ($ready as $u): ?>
                        <option value="<?= $u['id_unit'] ?>">
                            <?= e($u['nama_model'] . ' ' . $u['penyimpanan'] . ' - ' . $u['warna']) ?> [SN: <?= e($u['nomor_seri']) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-grid">
                <label>
                    Jenis Jaminan
                    <select name="jenis_jaminan" id="inputJenisJaminan" required>
                        <option value="" selected disabled>Pilih identitas jaminan</option>
                        <?php foreach ($jenisJaminanValid as $jenis): ?>
                            <option value="<?= e($jenis) ?>"><?= e($jenis) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Lama Sewa (Hari)
                    <input type="number" name="lama_sewa" min="1" value="1" required>
                </label>
            </div>
            <!-- Bukti Serah Terima Unit (Open Cam) -->
            <div style="background:rgba(5,9,19,0.5);border:1px solid var(--line);border-radius:12px;padding:16px;margin-top:14px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div>
                        <strong style="font-size:13px;color:var(--text);display:flex;align-items:center;gap:6px;">
                            📸 Bukti Pengambilan Unit (Open Cam)
                        </strong>
                        <small class="muted" style="display:block;margin-top:2px;font-size:11px;">
                            Foto penyewa saat mengambil unit iPhone sebagai bukti fisik serah terima.
                        </small>
                    </div>
                    <span id="camStatusBadge" class="badge" style="font-size:10px;background:rgba(255,255,255,0.06);border:1px solid var(--line);">Belum Diambil</span>
                </div>

                <!-- Hidden inputs -->
                <input type="hidden" name="foto_bukti_ambil" id="inputFotoBuktiAmbil" value="">
                <input type="file" name="foto_bukti_ambil_file" id="inputFotoBuktiFile" accept="image/*" style="display:none;" onchange="handleFileBukti(this)">
                <canvas id="canvasCapture" style="display:none;"></canvas>

                <!-- Initial State: Tombol Buka Kamera -->
                <div id="camInitialBox" style="text-align:center;padding:18px 12px;border:2px dashed var(--line);border-radius:10px;background:rgba(0,0,0,0.2);">
                    <div style="font-size:30px;margin-bottom:6px;opacity:0.75;">📷</div>
                    <p class="muted" style="font-size:12px;margin:0 0 12px;">Gunakan kamera perangkat untuk memotret penyewa saat serah terima:</p>
                    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                        <button type="button" class="primary-btn compact" onclick="startCamera()">
                            Buka Kamera (Open Cam)
                        </button>
                    </div>
                </div>

                <!-- Live Stream State -->
                <div id="camStreamBox" style="display:none;text-align:center;">
                    <div style="position:relative;border-radius:10px;overflow:hidden;background:#000;border:1px solid var(--line);max-height:280px;display:flex;align-items:center;justify-content:center;">
                        <video id="webcamVideo" autoplay playsinline style="width:100%;max-height:280px;object-fit:cover;"></video>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:center;margin-top:10px;">
                        <button type="button" class="primary-btn compact" style="background:#10b981;color:#fff;" onclick="snapPhoto()">
                            Ambil Foto
                        </button>
                        <button type="button" class="outline-btn compact" onclick="stopCamera()">
                             Tutup Kamera
                        </button>
                    </div>
                </div>

                <!-- Snapshot Preview State -->
                <div id="camPreviewBox" style="display:none;text-align:center;">
                    <div style="position:relative;border-radius:10px;overflow:hidden;background:#000;border:1px solid rgba(54,214,154,0.3);max-height:280px;display:inline-block;width:100%;">
                        <img id="snapshotImg" src="#" alt="Bukti Serah Terima" style="max-height:260px;width:100%;object-fit:contain;display:block;margin:auto;">
                    </div>
                    <div style="display:flex;gap:8px;justify-content:center;margin-top:10px;align-items:center;">
                        <span class="badge ready" style="font-size:11px;">Foto Bukti Siap</span>
                        <button type="button" class="outline-btn compact" onclick="retakePhoto()">
                            Foto Ulang
                        </button>
                    </div>
                </div>
            </div>

            <p class="muted" style="margin:12px 0 6px;font-size:12px;">
                Pastikan identitas penyewa terverifikasi dan foto serah terima unit telah diambil sebelum menyimpan transaksi.
            </p>

            <button class="primary-btn" id="btnSubmitAdmin" <?= !$ready || !$customers ? 'disabled' : '' ?>>
                Buat Transaksi Sewa
            </button>
        </form>
    </section>

    <section class="panel">
        <h2 class="panel-title">Aturan & Validasi Jaminan</h2>
        <ul style="margin:0;padding-left:18px;color:var(--muted);display:grid;gap:12px;font-size:13px;line-height:1.5;">
            <li><strong>Verifikasi Profil:</strong> Pelanggan wajib telah mengunggah foto jaminan identitas pada akun/profil mereka sebelum transaksi disetujui.</li>
            <li><strong>Foto Serah Terima:</strong> Gunakan fitur Open Cam untuk mengambil foto fisik penyewa saat unit diserahkan sebagai bukti pengambilan.</li>
            <li><strong>Denda Keterlambatan:</strong> Denda dihitung 10% dari tarif sewa per hari untuk setiap jam keterlambatan pengembalian unit.</li>
        </ul>
    </section>
</div>

<script>
let mediaStream = null;

async function startCamera() {
    const initialBox = document.getElementById('camInitialBox');
    const streamBox = document.getElementById('camStreamBox');
    const previewBox = document.getElementById('camPreviewBox');
    const video = document.getElementById('webcamVideo');

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'environment'
            },
            audio: false
        });
        video.srcObject = mediaStream;
        await video.play();

        initialBox.style.display = 'none';
        previewBox.style.display = 'none';
        streamBox.style.display = 'block';
    } catch (err) {
        console.error('Error opening camera:', err);
        alert('Kamera tidak dapat diakses (' + err.message + '). Pastikan izin akses kamera diberikan pada browser, atau silakan gunakan opsi "Unggah dari File".');
    }
}

function stopCamera() {
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }
    const initialBox = document.getElementById('camInitialBox');
    const streamBox = document.getElementById('camStreamBox');
    const previewBox = document.getElementById('camPreviewBox');
    const inputHidden = document.getElementById('inputFotoBuktiAmbil');

    streamBox.style.display = 'none';
    if (inputHidden.value) {
        previewBox.style.display = 'block';
    } else {
        initialBox.style.display = 'block';
    }
}

function snapPhoto() {
    const video = document.getElementById('webcamVideo');
    const canvas = document.getElementById('canvasCapture');
    const snapshotImg = document.getElementById('snapshotImg');
    const inputHidden = document.getElementById('inputFotoBuktiAmbil');
    const statusBadge = document.getElementById('camStatusBadge');
    const streamBox = document.getElementById('camStreamBox');
    const previewBox = document.getElementById('camPreviewBox');

    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.88);
    inputHidden.value = dataUrl;
    snapshotImg.src = dataUrl;

    stopCamera();

    streamBox.style.display = 'none';
    previewBox.style.display = 'block';
    statusBadge.className = 'badge ready';
    statusBadge.innerText = 'Foto Terambil';
}

function retakePhoto() {
    const inputHidden = document.getElementById('inputFotoBuktiAmbil');
    const inputFile = document.getElementById('inputFotoBuktiFile');
    const statusBadge = document.getElementById('camStatusBadge');
    inputHidden.value = '';
    inputFile.value = '';
    statusBadge.className = 'badge';
    statusBadge.style.background = 'rgba(255,255,255,0.06)';
    statusBadge.innerText = 'Belum Diambil';
    startCamera();
}

function handleFileBukti(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const dataUrl = e.target.result;
            document.getElementById('inputFotoBuktiAmbil').value = dataUrl;
            document.getElementById('snapshotImg').src = dataUrl;
            document.getElementById('camInitialBox').style.display = 'none';
            document.getElementById('camStreamBox').style.display = 'none';
            document.getElementById('camPreviewBox').style.display = 'block';
            const statusBadge = document.getElementById('camStatusBadge');
            statusBadge.className = 'badge ready';
            statusBadge.innerText = 'File Terpilih';
        };
        reader.readAsDataURL(file);
    }
}

function updateCustomerJaminanStatus() {
    const select = document.getElementById('selectPelanggan');
    const selectedOpt = select.options[select.selectedIndex];
    const statusBox = document.getElementById('statusJaminanBox');
    const jenisSelect = document.getElementById('inputJenisJaminan');
    const btnSubmit = document.getElementById('btnSubmitAdmin');

    if (!selectedOpt || !selectedOpt.value) {
        statusBox.style.display = 'none';
        return;
    }

    const hasJaminan = selectedOpt.getAttribute('data-has-jaminan') === '1';
    const jenisJaminan = selectedOpt.getAttribute('data-jenis-jaminan') || '';
    const fotoJaminan = selectedOpt.getAttribute('data-foto-jaminan') || '';
    const nama = selectedOpt.getAttribute('data-nama') || '';
    const nik = selectedOpt.getAttribute('data-nik') || '';

    statusBox.style.display = 'block';

    if (hasJaminan) {
        statusBox.innerHTML = `
            <div class="alert success" style="margin:0;padding:12px 14px;font-size:12px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:18px;">✓</span>
                    <div>
                        <strong>Jaminan Profil Terverifikasi: ${jenisJaminan}</strong>
                        <div class="muted" style="font-size:11px;">Pelanggan (${nama}, NIK: ${nik}) telah memiliki foto jaminan aktif.</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <a href="${fotoJaminan}" target="_blank" class="outline-btn compact" style="font-size:11px;padding:4px 8px;">Lihat Jaminan ↗</a>
                </div>
            </div>
        `;
        if (jenisJaminan) {
            jenisSelect.value = jenisJaminan;
        }
        btnSubmit.removeAttribute('disabled');
        btnSubmit.innerText = 'Buat Transaksi Sewa';
    } else {
        statusBox.innerHTML = `
            <div class="alert error" style="margin:0;padding:12px 14px;font-size:12px;">
                <div style="display:flex;align-items:flex-start;gap:10px;">
                    <span style="font-size:18px;line-height:1;">⚠️</span>
                    <div>
                        <strong style="color:#ffabb7;">Pelanggan Belum Memiliki Foto Jaminan</strong>
                        <div style="color:#ffcbd2;font-size:11px;margin-top:2px;">
                            Pelanggan <strong>${nama}</strong> belum mengunggah foto jaminan pada profilnya. Transaksi hanya dapat diproses setelah pelanggan melengkapi jaminan identitas pada akun.
                        </div>
                    </div>
                </div>
            </div>
        `;
        btnSubmit.setAttribute('disabled', 'disabled');
        btnSubmit.innerText = 'Terkunci (Pelanggan Belum Ada Jaminan)';
    }
}
</script>

<?php page_end(); ?>
