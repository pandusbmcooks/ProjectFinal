<?php require_once 'includes/auth.php';
$pdo = db();
$storage = $_GET['storage'] ?? '';
$sql = "SELECT u.*, m.nama_model, m.harga_sewa_per_hari,
               p.id_sewa AS sewa_aktif, p.tgl_kembali_rencana
        FROM tb_unit_iphone u
        JOIN tb_iphone_model m ON m.id_model = u.id_model
        LEFT JOIN tb_penyewaan p ON p.id_sewa = (
            SELECT p2.id_sewa FROM tb_penyewaan p2
            WHERE p2.id_unit = u.id_unit AND p2.status_transaksi IN ('berjalan', 'pending')
            ORDER BY p2.tgl_kembali_rencana DESC LIMIT 1
        )
        WHERE u.status IN ('ready', 'booked', 'disewa')";
$args = [];
if ($storage) {
    $sql .= ' AND u.penyimpanan=?';
    $args[] = $storage;
}
$sql .= ' ORDER BY m.harga_sewa_per_hari';
$st = $pdo->prepare($sql);
$st->execute($args);
$units = $st->fetchAll();

// Group units by id_model so each model appears only once
$groupedCatalog = [];
foreach ($units as $u) {
    $mid = $u['id_model'];
    if (!isset($groupedCatalog[$mid])) {
        $groupedCatalog[$mid] = [
            'id_model' => $mid,
            'nama_model' => $u['nama_model'],
            'harga_sewa_per_hari' => $u['harga_sewa_per_hari'],
            'foto' => $u['foto'],
            'units' => [],
            'ready_units' => [],
            'booked_units' => [],
            'disewa_units' => [],
            'colors' => [],
            'storages' => []
        ];
    }
    if (empty($groupedCatalog[$mid]['foto']) && !empty($u['foto'])) {
        $groupedCatalog[$mid]['foto'] = $u['foto'];
    }
    $groupedCatalog[$mid]['units'][] = $u;
    if (!empty($u['warna']) && !in_array($u['warna'], $groupedCatalog[$mid]['colors'], true)) {
        $groupedCatalog[$mid]['colors'][] = $u['warna'];
    }
    if (!empty($u['penyimpanan']) && !in_array($u['penyimpanan'], $groupedCatalog[$mid]['storages'], true)) {
        $groupedCatalog[$mid]['storages'][] = $u['penyimpanan'];
    }

    $isReady = $u['status'] === 'ready' && !$u['sewa_aktif'];
    $isBooked = $u['status'] === 'booked';
    $isRented = $u['status'] === 'disewa' || $u['sewa_aktif'];
    if ($isReady) {
        $groupedCatalog[$mid]['ready_units'][] = $u;
    } elseif ($isBooked) {
        $groupedCatalog[$mid]['booked_units'][] = $u;
    } elseif ($isRented) {
        $groupedCatalog[$mid]['disewa_units'][] = $u;
    }
}

$storages = $pdo->query('SELECT DISTINCT penyimpanan FROM tb_unit_iphone WHERE penyimpanan IS NOT NULL AND penyimpanan != "" ORDER BY LENGTH(penyimpanan), penyimpanan')->fetchAll(PDO::FETCH_COLUMN);
require 'includes/layout.php';
page_start('Katalog iPhone'); ?>
<section class="hero">
    <div>
        <p class="eyebrow">PREMIUM IPHONE RENTAL</p>
        <h1>iPhone terbaik,<br>untuk momen terbaikmu.</h1>
        <p>Pilih unit terawat, siap pakai, dan nikmati pengalaman sewa yang sederhana serta aman.</p>
    </div><?php if (!is_logged_in()): ?><a class="primary-btn" href="register.php">Mulai sewa sekarang →</a><?php endif ?>
</section>
<form class="panel filter-bar" method="get"><label>Ketersediaan<select disabled>
            <option>Semua unit</option>
        </select></label><label>Kapasitas penyimpanan<select name="storage" onchange="this.form.submit()">
            <option value="">Semua kapasitas</option><?php foreach ($storages as $s): ?><option value="<?= e($s) ?>" <?= $storage === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach ?>
        </select></label><a class="outline-btn" href="catalog.php">Reset filter</a></form>
<div class="section-head">
    <h2><?= count($groupedCatalog) ?> model dalam katalog</h2><span class="muted">Status diperbarui secara real-time</span>
</div>
<section class="iphone-grid"><?php foreach ($groupedCatalog as $item):
    $readyCount = count($item['ready_units']);
    $bookedCount = count($item['booked_units']);
    $disewaCount = count($item['disewa_units']);
    $isReady = $readyCount > 0;
    $isBooked = !$isReady && $bookedCount > 0;
    $isRented = !$isReady && !$isBooked && $disewaCount > 0;

    $estimatedReady = null;
    $nextAvailableUnitId = null;
    $futureUnits = array_merge($item['booked_units'], $item['disewa_units']);
    foreach ($futureUnits as $fu) {
        if (!empty($fu['tgl_kembali_rencana'])) {
            if ($estimatedReady === null || strtotime($fu['tgl_kembali_rencana']) < strtotime($estimatedReady)) {
                $estimatedReady = $fu['tgl_kembali_rencana'];
                $nextAvailableUnitId = $fu['id_unit'];
            }
        }
    }
    if (!$nextAvailableUnitId && !empty($futureUnits)) {
        $nextAvailableUnitId = $futureUnits[0]['id_unit'];
    }

    $estText = $estimatedReady ? date('d M Y, H:i', strtotime($estimatedReady)) : null;
    $firstReadyUnitId = $isReady ? $item['ready_units'][0]['id_unit'] : null;
    $warnaText = implode(', ', $item['colors']);
    $storageText = implode(', ', $item['storages']);
?><article class="panel iphone-card">
            <div class="iphone-visual"><?php if (!empty($item['foto'])): ?><img src="<?= e($item['foto']) ?>" alt="<?= e($item['nama_model']) ?>" class="iphone-card-img"><?php else: ?>⌁<?php endif ?></div>
            <div class="iphone-card-body">
                <span class="badge catalog-status <?= $isReady ? 'ready' : ($isBooked ? 'booked' : ($isRented ? 'disewa' : 'hilang')) ?>">Status: <?= $isReady ? ($readyCount > 1 ? "Ready ({$readyCount} unit)" : 'Ready') : ($isBooked ? 'Sedang Dibooking' : ($isRented ? 'Disewa' : 'Tidak Tersedia')) ?></span>
                <?php if ($isReady): ?>
                    <p class="muted availability-note"><?= $readyCount > 1 ? "{$readyCount} unit siap disewa sekarang." : 'Unit siap disewa sekarang.' ?></p>
                <?php elseif ($isBooked): ?>
                    <p class="muted availability-note">Unit sedang dibooking. Perkiraan ready: <strong><?= e($estText ?: 'menunggu konfirmasi') ?></strong></p>
                <?php elseif ($isRented): ?>
                    <p class="muted availability-note">Semua unit sedang disewa. Perkiraan kembali: <strong><?= e($estText ?: 'menunggu konfirmasi') ?></strong></p>
                <?php else: ?>
                    <p class="muted availability-note">Unit sedang tidak tersedia untuk disewa.</p>
                <?php endif ?>
                <h3><?= e($item['nama_model']) ?></h3>
                <p><?= e(($storageText ?: '-') . ($warnaText ? ' · ' . $warnaText : '')) ?></p>
                <div class="card-foot">
                    <span class="price">Rp<?= number_format($item['harga_sewa_per_hari'], 0, ',', '.') ?><small class="muted"> / hari</small></span>
                    <?php if ($isReady): ?>
                        <?php if (is_logged_in()): ?>
                            <?php if (user()['role'] === 'user'): ?>
                                <a class="primary-btn compact" href="form_sewa_user.php?unit_id=<?= $firstReadyUnitId ?>">Sewa Sekarang</a>
                            <?php else: ?>
                                <a class="outline-btn compact" href="rentals.php">Kelola Sewa</a>
                            <?php endif ?>
                        <?php else: ?>
                            <a class="primary-btn compact" href="login.php">Sewa Sekarang</a>
                        <?php endif ?>
                    <?php elseif ($nextAvailableUnitId): ?>
                        <?php if (is_logged_in()): ?>
                            <?php if (user()['role'] === 'user'): ?>
                                <a class="outline-btn compact" href="form_sewa_user.php?unit_id=<?= $nextAvailableUnitId ?>" style="border-color:#3b82f6;color:#60a5fa;">Ajukan Sewa Nanti →</a>
                            <?php else: ?>
                                <a class="outline-btn compact" href="rentals.php">Kelola Sewa</a>
                            <?php endif ?>
                        <?php else: ?>
                            <a class="outline-btn compact" href="login.php" style="border-color:#3b82f6;color:#60a5fa;">Ajukan Sewa Nanti →</a>
                        <?php endif ?>
                    <?php else: ?>
                        <button class="outline-btn compact" type="button" disabled>Unit Tidak Tersedia</button>
                    <?php endif ?>
                </div>
            </div>
        </article><?php endforeach;
        if (!$groupedCatalog): ?><div class="panel empty">Tidak ada model iPhone yang cocok dengan filter ini.</div><?php endif ?></section>
<?php page_end(); ?>
