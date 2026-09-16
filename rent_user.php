<?php
require_once 'includes/auth.php';
require_role('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('catalog.php');
}

$pdo = db();
$jenisJaminanValid = ['KTP', 'Kartu Pelajar', 'SIM', 'Paspor', 'Kartu Identitas Lainnya'];

try {
    $pdo->beginTransaction();

    // 1. Get customer record for logged in user
    $c = $pdo->prepare('SELECT * FROM tb_pelanggan WHERE id_user = ?');
    $c->execute([user()['id_user']]);
    $customer = $c->fetch();
    if (!$customer) {
        throw new Exception('Data penyewa tidak ditemukan. Silakan lengkapi profil terlebih dahulu.');
    }
    $idPelanggan = $customer['id_pelanggan'];

    // 2. Validate unit exists and is ready
    $idUnit = (int)($_POST['id_unit'] ?? 0);
    $st = $pdo->prepare("SELECT u.id_unit, m.nama_model, m.harga_sewa_per_hari FROM tb_unit_iphone u JOIN tb_iphone_model m ON m.id_model=u.id_model WHERE u.id_unit=? AND u.status='ready'");
    $st->execute([$idUnit]);
    $unit = $st->fetch();
    if (!$unit) {
        throw new Exception('Unit tidak tersedia atau sudah disewa.');
    }

    // 3. Validate inputs
    $inputTglSewa = trim($_POST['tgl_sewa'] ?? '');
    $inputTglKembali = trim($_POST['tgl_kembali'] ?? '');

    if (!empty($inputTglSewa) && !empty($inputTglKembali)) {
        $startTime = strtotime($inputTglSewa);
        $endTime = strtotime($inputTglKembali);
        if (!$startTime || !$endTime) {
            throw new Exception('Format tanggal sewa tidak valid.');
        }
        if ($endTime < $startTime) {
            throw new Exception('Tanggal selesai sewa tidak boleh sebelum tanggal mulai sewa.');
        }
        $durasiHari = max(1, (int)round(($endTime - $startTime) / 86400));
        $currentTime = date('H:i:s', strtotime(device_transaction_time()));
        $tglSewa = date('Y-m-d', $startTime) . ' ' . $currentTime;
        $tglKembaliRencana = date('Y-m-d', $endTime) . ' ' . $currentTime;
    } else {
        $durasiHari = max(1, (int)($_POST['lama_sewa'] ?? 1));
        $tglSewa = device_transaction_time();
        $tglKembaliRencana = date('Y-m-d H:i:s', strtotime("+$durasiHari days", strtotime($tglSewa)));
    }

    $jenisJaminan = trim($_POST['jenis_jaminan'] ?? ($customer['jenis_jaminan'] ?? ''));
    if (!in_array($jenisJaminan, $jenisJaminanValid, true)) {
        throw new Exception('Pilihan jenis jaminan tidak valid.');
    }

    // 4. Use existing profile foto_jaminan
    $fotoJaminanPath = $customer['foto_jaminan'] ?? null;

    // STRICT CHECK: account MUST have a valid collateral photo
    if (empty($fotoJaminanPath) || !file_exists(__DIR__ . '/' . $fotoJaminanPath)) {
        throw new Exception('Akun Anda belum memiliki bukti foto jaminan. Harap unggah foto jaminan pada profil Anda sebelum mengajukan penyewaan.');
    }

    // 5. Check for duplicate pending request for same unit
    $dupCheck = $pdo->prepare("SELECT id_sewa FROM tb_penyewaan WHERE id_pelanggan=? AND id_unit=? AND status_transaksi='pending'");
    $dupCheck->execute([$idPelanggan, $idUnit]);
    if ($dupCheck->fetch()) {
        throw new Exception('Anda sudah memiliki pengajuan yang sedang menunggu persetujuan untuk unit ini.');
    }

    // 6. Calculate tariff info
    $tarifDendaPerJam = $unit['harga_sewa_per_hari'] * 0.1;

    // 7. Insert transaction with status 'pending'
    $insertSt = $pdo->prepare("INSERT INTO tb_penyewaan (id_pelanggan, id_unit, jenis_jaminan, foto_jaminan, tgl_sewa, tgl_kembali_rencana, tarif_denda_per_jam, status_transaksi) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $insertSt->execute([$idPelanggan, $idUnit, $jenisJaminan, $fotoJaminanPath, $tglSewa, $tglKembaliRencana, $tarifDendaPerJam]);

    // 8. Kunci kondisi unit menjadi 'booked'
    $pdo->prepare("UPDATE tb_unit_iphone SET status='booked' WHERE id_unit=?")->execute([$idUnit]);

    $pdo->commit();
    flash('success', 'Pengajuan sewa berhasil dikirim! Kondisi unit telah di-booking untuk Anda dan menunggu persetujuan admin.');
    redirect('my_rentals.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $e->getMessage());
    if (strpos($e->getMessage(), 'foto jaminan') !== false) {
        redirect('profile.php#form-jaminan');
    }
    redirect('form_sewa_user.php');
}
