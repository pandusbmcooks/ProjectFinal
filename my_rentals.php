<?php require_once 'includes/auth.php';
require_role('user');
$pdo = db();
$c = $pdo->prepare('SELECT * FROM tb_pelanggan WHERE id_user=?');
$c->execute([user()['id_user']]);
$customer = $c->fetch();
$rows = [];
if ($customer) {
    $st = $pdo->prepare('SELECT p.*,m.nama_model,m.penyimpanan,m.harga_sewa_per_hari,u.warna FROM tb_penyewaan p JOIN tb_unit_iphone u ON u.id_unit=p.id_unit JOIN tb_iphone_model m ON m.id_model=u.id_model WHERE p.id_pelanggan=? ORDER BY p.id_sewa DESC');
    $st->execute([$customer['id_pelanggan']]);
    $rows = $st->fetchAll();
}
require 'includes/layout.php';
page_start('Riwayat Sewa Saya'); ?>
<section class="hero">
    <div>
        <p class="eyebrow">AKUN PENYEWA</p>
        <h1>Riwayat sewa<br>Anda.</h1>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a class="primary-btn" href="form_sewa_user.php">+ Ajukan Sewa Baru</a>
        <a class="outline-btn" href="profile.php">Profil & Jaminan</a>
        <a class="outline-btn" href="catalog.php">Lihat katalog →</a>
    </div>
</section>
<section class="panel table-wrap">
    <table>
        <thead>
            <tr>
                <th>Unit</th>
                <th>Waktu sewa</th>
                <th>Batas kembali</th>
                <th>Status</th>
                <th>Denda</th>
            </tr>
        </thead>
        <tbody><?php foreach ($rows as $r): 
                    $isPending = $r['status_transaksi'] === 'pending';
                    $isBerjalan = $r['status_transaksi'] === 'berjalan';
                    $late = $isBerjalan ? max(0, (time() - strtotime($r['tgl_kembali_rencana'])) / 60) : 0;
                    $estimate = ceil($late / 60) * $r['tarif_denda_per_jam'];
                    $durasiHari = max(1, round((strtotime($r['tgl_kembali_rencana']) - strtotime($r['tgl_sewa'])) / 86400));
                    ?><tr>
                    <td>
                        <strong><?= e($r['nama_model']) ?></strong><br>
                        <small class="muted"><?= e($r['penyimpanan'] . ' · ' . $r['warna']) ?></small>
                        <?php if (!empty($r['foto_bukti_ambil']) && file_exists(__DIR__ . '/' . $r['foto_bukti_ambil'])): ?>
                            <br><a href="<?= e($r['foto_bukti_ambil']) ?>" target="_blank" class="outline-btn compact" style="font-size:10px;padding:2px 6px;margin-top:4px;display:inline-block;">Bukti Serah Terima ↗</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isPending): ?>
                            <span class="muted" style="font-size:12px;">Menunggu persetujuan</span><br>
                            <small class="muted">Diajukan: <?= date('d M Y H:i', strtotime($r['tgl_sewa'])) ?></small>
                        <?php else: ?>
                            <?= date('d M Y H:i', strtotime($r['tgl_sewa'])) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isPending): ?>
                            <span class="muted" style="font-size:12px;">Durasi <?= $durasiHari ?> hari</span><br>
                            <small class="muted">(Mulai saat disetujui)</small>
                        <?php else: ?>
                            <?= date('d M Y H:i', strtotime($r['tgl_kembali_rencana'])) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isPending): ?>
                            <span class="badge pending" style="background:#f59e0b;color:#000;font-weight:700;">Menunggu Persetujuan</span>
                        <?php else: ?>
                            <span class="badge <?= $r['status_transaksi'] ?>"><?= e($r['status_transaksi']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isBerjalan): ?>
                            <strong class="<?= $estimate ? 'hilang' : '' ?>">Rp<?= number_format($estimate, 0, ',', '.') ?></strong><br><small class="muted">estimasi saat ini</small>
                        <?php elseif ($isPending): ?>
                            <span class="muted">-</span>
                        <?php else: ?>
                            Rp<?= number_format($r['total_denda'], 0, ',', '.') ?>
                        <?php endif; ?>
                    </td>
                </tr><?php endforeach;
                    if (!$rows): ?><tr>
                    <td colspan="5" class="empty">Belum ada riwayat sewa. <a href="catalog.php">Jelajahi katalog</a></td>
                </tr><?php endif ?></tbody>
    </table>
</section><?php page_end(); ?>
