<?php
require_once 'includes/auth.php';
require_role('admin');
$pdo = db();
$jenisJaminanValid = ['KTP', 'Kartu Pelajar', 'SIM', 'Paspor', 'Kartu Identitas Lainnya'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('sewa.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $pdo->beginTransaction();

        if ($action === 'return') {
            $sewa = $pdo->prepare('SELECT * FROM tb_penyewaan WHERE id_sewa=? AND status_transaksi="berjalan" FOR UPDATE');
            $sewa->execute([$_POST['id']]);
            $s = $sewa->fetch();
            if (!$s) throw new Exception('Transaksi tidak ditemukan.');
            $now = device_transaction_time();
            $minutes = max(0, (strtotime($now) - strtotime($s['tgl_kembali_rencana'])) / 60);
            $fine = ceil($minutes / 60) * $s['tarif_denda_per_jam'];
            $pdo->prepare("UPDATE tb_penyewaan SET tgl_kembali_aktual=?,total_denda=?,status_transaksi='selesai' WHERE id_sewa=?")->execute([$now, $fine, $s['id_sewa']]);
            $pdo->prepare("UPDATE tb_unit_iphone SET status='ready' WHERE id_unit=?")->execute([$s['id_unit']]);
            flash('success', 'Pengembalian selesai. Denda: Rp' . number_format($fine, 0, ',', '.'));

        } elseif ($action === 'accept') {
            // Accept a pending rental application
            $sewa = $pdo->prepare('SELECT p.*, c.nama_lengkap FROM tb_penyewaan p JOIN tb_pelanggan c ON c.id_pelanggan=p.id_pelanggan WHERE p.id_sewa=? AND p.status_transaksi="pending" FOR UPDATE');
            $sewa->execute([$_POST['id']]);
            $s = $sewa->fetch();
            if (!$s) throw new Exception('Pengajuan tidak ditemukan atau sudah diproses.');

            // Check if unit is still available
            $unitCheck = $pdo->prepare("SELECT id_unit FROM tb_unit_iphone WHERE id_unit=? AND status='ready' FOR UPDATE");
            $unitCheck->execute([$s['id_unit']]);
            if (!$unitCheck->fetch()) {
                throw new Exception('Unit sudah tidak tersedia. Pengajuan tidak dapat disetujui.');
            }

            // Recalculate rental period from NOW (approval time)
            $durasiDetik = max(86400, strtotime($s['tgl_kembali_rencana']) - strtotime($s['tgl_sewa']));
            $durasiHari = max(1, round($durasiDetik / 86400));
            $now = device_transaction_time();
            $newKembali = date('Y-m-d H:i:s', strtotime("+$durasiHari days", strtotime($now)));

            // Check if admin captured proof photo with open cam upon handover
            $fotoBuktiPath = $s['foto_bukti_ambil'] ?? null;
            if (!empty($_POST['foto_bukti_ambil']) && strpos($_POST['foto_bukti_ambil'], 'data:image/') === 0) {
                $base64Str = $_POST['foto_bukti_ambil'];
                if (preg_match('/^data:image\/(\w+);base64,/', $base64Str, $match)) {
                    $type = strtolower($match[1]);
                    if ($type === 'jpeg') $type = 'jpg';
                    $data = base64_decode(substr($base64Str, strpos($base64Str, ',') + 1));
                    if ($data !== false) {
                        $targetDir = __DIR__ . '/uploads/bukti_ambil/';
                        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                        $filename = 'bukti_' . $s['id_pelanggan'] . '_' . time() . '_' . uniqid() . '.' . $type;
                        if (file_put_contents($targetDir . $filename, $data)) {
                            $fotoBuktiPath = 'uploads/bukti_ambil/' . $filename;
                        }
                    }
                }
            }

            $pdo->prepare("UPDATE tb_penyewaan SET status_transaksi='berjalan', tgl_sewa=?, tgl_kembali_rencana=?, foto_bukti_ambil=COALESCE(?, foto_bukti_ambil) WHERE id_sewa=?")
                ->execute([$now, $newKembali, $fotoBuktiPath, $s['id_sewa']]);
            $pdo->prepare("UPDATE tb_unit_iphone SET status='disewa' WHERE id_unit=?")->execute([$s['id_unit']]);
            flash('success', "Pengajuan dari {$s['nama_lengkap']} berhasil disetujui! Transaksi mulai berjalan sekarang (" . date('d M Y H:i', strtotime($now)) . " s/d " . date('d M Y H:i', strtotime($newKembali)) . ").");

        } elseif ($action === 'reject') {
            // Reject a pending rental application
            $sewa = $pdo->prepare('SELECT p.*, c.nama_lengkap FROM tb_penyewaan p JOIN tb_pelanggan c ON c.id_pelanggan=p.id_pelanggan WHERE p.id_sewa=? AND p.status_transaksi="pending" FOR UPDATE');
            $sewa->execute([$_POST['id']]);
            $s = $sewa->fetch();
            if (!$s) throw new Exception('Pengajuan tidak ditemukan atau sudah diproses.');

            // Delete or mark as rejected — we'll delete pending entries
            $pdo->prepare("DELETE FROM tb_penyewaan WHERE id_sewa=? AND status_transaksi='pending'")->execute([$s['id_sewa']]);
            flash('success', "Pengajuan dari {$s['nama_lengkap']} telah ditolak.");

        } else {
            // Admin creates a direct transaction (status = 'berjalan')
            $idPelanggan = (int)($_POST['id_pelanggan'] ?? 0);
            $cSt = $pdo->prepare('SELECT * FROM tb_pelanggan WHERE id_pelanggan=? FOR UPDATE');
            $cSt->execute([$idPelanggan]);
            $cust = $cSt->fetch();
            if (!$cust) throw new Exception('Data pelanggan tidak ditemukan.');

            $st = $pdo->prepare("SELECT u.id_unit, m.harga_sewa_per_hari FROM tb_unit_iphone u JOIN tb_iphone_model m ON m.id_model=u.id_model WHERE u.id_unit=? AND u.status='ready' FOR UPDATE");
            $st->execute([$_POST['id_unit']]);
            $unitInfo = $st->fetch();
            if (!$unitInfo) throw new Exception('Unit tidak tersedia atau sedang disewa.');

            $durasiHari = max(1, (int)($_POST['lama_sewa'] ?? 1));
            $jenisJaminan = trim($_POST['jenis_jaminan'] ?? ($cust['jenis_jaminan'] ?? ''));
            if (!in_array($jenisJaminan, $jenisJaminanValid, true)) {
                throw new Exception('Data jenis jaminan tidak valid.');
            }

            // Collateral photo is strictly verified from customer profile
            $fotoJaminanPath = $cust['foto_jaminan'] ?? null;
            if (empty($fotoJaminanPath) || !file_exists(__DIR__ . '/' . $fotoJaminanPath)) {
                throw new Exception("Pelanggan '{$cust['nama_lengkap']}' belum mengunggah foto identitas diri untuk jaminan. Transaksi tidak dapat dibuat.");
            }

            // Process camera snapshot / file proof of unit pickup (Bukti Serah Terima)
            $fotoBuktiPath = null;
            if (!empty($_POST['foto_bukti_ambil']) && strpos($_POST['foto_bukti_ambil'], 'data:image/') === 0) {
                $base64Str = $_POST['foto_bukti_ambil'];
                if (preg_match('/^data:image\/(\w+);base64,/', $base64Str, $match)) {
                    $type = strtolower($match[1]);
                    if ($type === 'jpeg') $type = 'jpg';
                    $data = base64_decode(substr($base64Str, strpos($base64Str, ',') + 1));
                    if ($data !== false) {
                        $targetDir = __DIR__ . '/uploads/bukti_ambil/';
                        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                        $filename = 'bukti_' . $idPelanggan . '_' . time() . '_' . uniqid() . '.' . $type;
                        if (file_put_contents($targetDir . $filename, $data)) {
                            $fotoBuktiPath = 'uploads/bukti_ambil/' . $filename;
                        }
                    }
                }
            } elseif (isset($_FILES['foto_bukti_ambil_file']) && $_FILES['foto_bukti_ambil_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['foto_bukti_ambil_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed, true)) {
                    $targetDir = __DIR__ . '/uploads/bukti_ambil/';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                    $filename = 'bukti_' . $idPelanggan . '_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['foto_bukti_ambil_file']['tmp_name'], $targetDir . $filename)) {
                        $fotoBuktiPath = 'uploads/bukti_ambil/' . $filename;
                    }
                }
            }

            $tarifDendaPerJam = $unitInfo['harga_sewa_per_hari'] * 0.1;
            $tglSewa = device_transaction_time();
            $tglKembaliRencana = date('Y-m-d H:i:s', strtotime("+$durasiHari days", strtotime($tglSewa)));
            $pdo->prepare("INSERT INTO tb_penyewaan (id_pelanggan,id_unit,jenis_jaminan,foto_jaminan,foto_bukti_ambil,tgl_sewa,tgl_kembali_rencana,tarif_denda_per_jam,status_transaksi) VALUES (?,?,?,?,?,?,?,?,'berjalan')")
                ->execute([$idPelanggan, $_POST['id_unit'], $jenisJaminan, $fotoJaminanPath, $fotoBuktiPath, $tglSewa, $tglKembaliRencana, $tarifDendaPerJam]);
            $pdo->prepare("UPDATE tb_unit_iphone SET status='disewa' WHERE id_unit=?")->execute([$_POST['id_unit']]);
            flash('success', 'Transaksi sewa berhasil dibuat.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
    }
    redirect('sewa.php');
}
