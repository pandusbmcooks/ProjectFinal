<?php require_once 'includes/auth.php';
require_role('admin');
$pdo = db();
$counts = [];
foreach (['ready', 'disewa', 'maintenance'] as $s) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM tb_unit_iphone WHERE status=?');
    $q->execute([$s]);
    $counts[$s] = $q->fetchColumn();
}
$pendingCount = $pdo->query("SELECT COUNT(*) FROM tb_penyewaan WHERE status_transaksi='pending'")->fetchColumn();
$income = $pdo->query("SELECT COALESCE(SUM(GREATEST(1,CEIL(TIMESTAMPDIFF(MINUTE,tgl_sewa,tgl_kembali_aktual)/1440))*m.harga_sewa_per_hari+total_denda),0) FROM tb_penyewaan p JOIN tb_unit_iphone u ON u.id_unit=p.id_unit JOIN tb_iphone_model m ON m.id_model=u.id_model WHERE p.status_transaksi='selesai'")->fetchColumn();
$latest = $pdo->query('SELECT p.*,c.nama_lengkap,m.nama_model,u.warna FROM tb_penyewaan p JOIN tb_pelanggan c ON c.id_pelanggan=p.id_pelanggan JOIN tb_unit_iphone u ON u.id_unit=p.id_unit JOIN tb_iphone_model m ON m.id_model=u.id_model ORDER BY p.id_sewa DESC LIMIT 6')->fetchAll();
require 'includes/layout.php';
page_start('Dashboard', true); ?>

<?php if ($pendingCount > 0): ?>
    <div class="alert" style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.35);color:#fef3c7;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:14px 18px;border-radius:14px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:24px;">🔔</span>
            <div>
                <strong style="color:#fbbf24;font-size:14px;"><?= $pendingCount ?> Pengajuan Sewa Menunggu Persetujuan</strong>
                <p style="margin:2px 0 0;font-size:12px;opacity:0.9;">Pelanggan telah mengajukan transaksi. Silakan setujui pengajuan saat serah terima unit agar transaksi resmi berjalan.</p>
            </div>
        </div>
        <a href="sewa.php" class="primary-btn compact" style="background:#f59e0b;color:#000;font-weight:700;">Tinjau & Setujui →</a>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card"><span class="stat-mark">◉</span><span class="stat-label">UNIT READY</span>
        <p class="stat-value"><?= $counts['ready'] ?></p>
    </div>
    <div class="stat-card"><span class="stat-mark">↗</span><span class="stat-label">SEDANG DISEWA</span>
        <p class="stat-value"><?= $counts['disewa'] ?></p>
    </div>
    <div class="stat-card"><span class="stat-mark">◇</span><span class="stat-label">MAINTENANCE</span>
        <p class="stat-value"><?= $counts['maintenance'] ?></p>
    </div>
    <div class="stat-card"><span class="stat-label">PENDAPATAN + DENDA</span>
        <p class="stat-value">Rp<?= number_format($income, 0, ',', '.') ?></p>
    </div>
</div>
<br>
<section class="panel realtime-clock stat-card" aria-live="polite">
    <div>
        <p class="eyebrow">WAKTU SAAT INI</p>
        <h2 id="realtime-clock">--:--:--</h2>
    </div>
    <p id="realtime-date" class="muted">Memuat waktu perangkat...</p>
</section>
<script src="assets/js/app.js"></script>
<div class="content-grid">
    <section class="panel">
        <h2 class="panel-title">Transaksi terbaru</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Rencana kembali</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($latest as $r): ?><tr>
                            <td><?= e($r['nama_lengkap']) ?></td>
                            <td><?= e($r['nama_model'] . ' · ' . $r['warna']) ?></td>
                            <td>
                                <?php if ($r['status_transaksi'] === 'pending'): ?>
                                    <span class="badge pending" style="background:#f59e0b;color:#000;font-weight:700;">Menunggu Persetujuan</span>
                                <?php else: ?>
                                    <span class="badge <?= $r['status_transaksi'] ?>"><?= e($r['status_transaksi']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['status_transaksi'] === 'pending'): ?>
                                    <small class="muted">Dihitung saat disetujui</small>
                                <?php else: ?>
                                    <?= date('d M Y H:i', strtotime($r['tgl_kembali_rencana'])) ?>
                                <?php endif; ?>
                            </td>
                        </tr><?php endforeach;
                            if (!$latest): ?><tr>
                            <td colspan="4" class="empty">Belum ada transaksi.</td>
                        </tr><?php endif ?></tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <h2 class="panel-title">Aksi cepat</h2>
        <p class="muted">Kelola armada dan transaksi penyewaan dari satu tempat.</p>
        <div style="display:grid;gap:10px;margin-top:20px"><a class="primary-btn" href="buat_transaksi.php">+ Buat transaksi sewa</a><a class="outline-btn" href="units.php">Kelola unit iPhone</a></div>
    </section>
</div><?php page_end(); ?>
