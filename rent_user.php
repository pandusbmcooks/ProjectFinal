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

    // 2. Validate unit exists and status allows booking
    $idUnit = (int)($_POST['id_unit'] ?? 0);
    $st = $pdo->prepare("SELECT u.id_unit, u.status, m.nama_model, m.harga_sewa_per_hari FROM tb_unit_iphone u JOIN tb_iphone_model m ON m.id_model=u.id_model WHERE u.id_unit=? AND u.status IN ('ready', 'booked', 'disewa') FOR UPDATE");
    $st->execute([$idUnit]);
    $unit = $st->fetch();
    if (!$unit) {
        throw new Exception('Unit tidak ditemukan atau sedang tidak tersedia untuk disewa.');
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

    // Check active rentals for conflict / interval overlap
    $activeSt = $pdo->prepare("
        SELECT id_sewa, tgl_sewa, tgl_kembali_rencana, status_transaksi 
        FROM tb_penyewaan 
        WHERE id_unit = ? AND status_transaksi IN ('pending', 'berjalan')
        ORDER BY tgl_kembali_rencana DESC
    ");
    $activeSt->execute([$idUnit]);
    $activeRentals = $activeSt->fetchAll();

    $inputStartDate = date('Y-m-d', strtotime($tglSewa));
    foreach ($activeRentals as $ar) {
        $arEndDate = date('Y-m-d', strtotime($ar['tgl_kembali_rencana']));
        $statusText = ($ar['status_transaksi'] === 'berjalan') ? 'sedang disewa' : 'sedang dibooking';
        $formattedReturn = date('d M Y', strtotime($ar['tgl_kembali_rencana']));
        $earliestReadyDate = date('Y-m-d', strtotime('+1 day', strtotime($arEndDate)));
        $formattedEarliest = date('d M Y', strtotime($earliestReadyDate));

        // Aturan: Jika unit kembali tanggal 25, baru bisa dipilih atau dibooking orang lain pada tanggal 26 (setelah transaksi selesai)
        if ($inputStartDate <= $arEndDate) {
            throw new Exception("Unit ini {$statusText} hingga tanggal {$formattedReturn} dan baru dapat disewa mulai tanggal {$formattedEarliest} (setelah transaksi sebelumnya selesai). Silakan pilih tanggal mulai sewa {$formattedEarliest} atau setelahnya.");
        }

        $newStart = strtotime($tglSewa);
        $newEnd = strtotime($tglKembaliRencana);
        $existStart = strtotime($ar['tgl_sewa']);
        $existEnd = strtotime($ar['tgl_kembali_rencana']);

        if ($newStart < $existEnd && $newEnd > $existStart) {
            throw new Exception("Jadwal sewa bertabrakan dengan transaksi lain untuk unit ini ({$statusText} s/d {$formattedReturn}). Silakan pilih tanggal lain.");
        }
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

    // 8. Update status unit: jika saat ini 'ready', ubah ke 'booked'. Jika 'disewa', biarkan tetap 'disewa'
    if ($unit['status'] === 'ready') {
        $pdo->prepare("UPDATE tb_unit_iphone SET status='booked' WHERE id_unit=?")->execute([$idUnit]);
    }

    $pdo->commit();
    flash('success', 'Pengajuan sewa berhasil dikirim! Unit telah di-booking untuk Anda dan menunggu persetujuan admin.');
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
