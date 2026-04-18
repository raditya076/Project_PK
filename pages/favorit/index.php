<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_login();
$user = user_login();

// Ambil semua kos favorit user ini (JOIN tabel favorites + kos)
$stmt = mysqli_prepare($koneksi,
    "SELECT k.*, u.nama AS nama_pemilik, f.created_at AS tanggal_simpan
     FROM favorites f
     JOIN kos k ON f.kos_id = k.id
     LEFT JOIN users u ON k.pemilik_id = u.id
     WHERE f.user_id = ?
       AND k.status = 'aktif'
     ORDER BY f.created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $user['id']);
mysqli_stmt_execute($stmt);
$result     = mysqli_stmt_get_result($stmt);
$total_fav  = mysqli_num_rows($result);

$judul_halaman = "Kos Favorit Saya";
$css_tambahan  = "home.css";

require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

<div style="background:var(--color-surface); border-bottom:1px solid var(--color-border); padding:20px 0;">
    <div class="container">
        <h1 style="font-size:20px; font-weight:800; margin:0;">
            ❤️ Kos Favorit Saya
            <span style="font-size:14px; font-weight:500; color:var(--color-text-muted);">
                — <?= $total_fav ?> kos tersimpan
            </span>
        </h1>
    </div>
</div>

<section class="section-listing">
    <div class="container">
        <?= get_flash() ?>

        <?php if ($total_fav > 0): ?>
            <div class="row g-4">
            <?php while ($kos = mysqli_fetch_assoc($result)):
                $harga_format = 'Rp ' . number_format($kos['harga_per_bulan'], 0, ',', '.');
                $kamar_sisa   = $kos['jumlah_kamar'] - $kos['kamar_terisi'];
            ?>
                <div class="col-lg-4 col-md-6">
                    <article class="kos-card">
                        <div class="kos-card-img-wrapper">
                            <?php if (!empty($kos['foto_utama'])): ?>
                                <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($kos['foto_utama']) ?>"
                                     alt="<?= htmlspecialchars($kos['nama_kos']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="kos-img-placeholder">
                                    <div class="placeholder-icon">🏠</div>
                                    <div class="placeholder-text">Foto belum ada</div>
                                </div>
                            <?php endif; ?>

                            <!-- Tombol hapus dari favorit -->
                            <form action="<?= BASE_URL ?>/pages/favorit/toggle.php"
                                  method="POST" class="favorit-form">
                                <input type="hidden" name="kos_id"  value="<?= $kos['id'] ?>">
                                <input type="hidden" name="kembali" value="<?= BASE_URL ?>/pages/favorit/index.php">
                                <button type="submit" class="kos-wishlist-btn aktif" title="Hapus dari favorit">
                                    ❤️
                                </button>
                            </form>
                        </div>

                        <div class="kos-card-body">
                            <span class="badge-kos <?= $kos['tipe'] ?>"><?= ucfirst($kos['tipe']) ?></span>
                            <h3 class="kos-card-name mt-2">
                                <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos['id'] ?>"
                                   style="color:inherit; text-decoration:none;">
                                    <?= htmlspecialchars($kos['nama_kos']) ?>
                                </a>
                            </h3>
                            <p class="kos-card-location">
                                <span class="icon">📍</span>
                                <?= htmlspecialchars($kos['kota']) ?>
                            </p>
                            <div class="kos-facilities">
                                <?php if ($kos['wifi']): ?><span class="facility-item">📶 WiFi</span><?php endif; ?>
                                <?php if ($kos['ac']): ?><span class="facility-item">❄️ AC</span><?php endif; ?>
                                <?php if ($kos['kamar_mandi_dalam']): ?><span class="facility-item">🚿 KM Dalam</span><?php endif; ?>
                            </div>
                            <div class="kos-card-meta">
                                <div>
                                    <div class="kos-card-price"><?= $harga_format ?><span>/ bln</span></div>
                                    <p style="font-size:11px; color:var(--color-text-muted); margin:2px 0 0;">
                                        Disimpan <?= date('d M Y', strtotime($kos['tanggal_simpan'])) ?>
                                    </p>
                                </div>
                                <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos['id'] ?>"
                                   class="btn-kosta btn" style="font-size:12px; padding:7px 16px;">
                                    Lihat →
                                </a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endwhile; ?>
            </div>

        <?php else: ?>
            <div class="empty-state" style="padding:80px 20px;">
                <div class="empty-icon">💔</div>
                <h5>Belum Ada Kos Favorit</h5>
                <p>Kamu belum menyimpan kos apapun. Klik ikon ❤️ pada kartu kos untuk menyimpannya.</p>
                <a href="<?= BASE_URL ?>/index.php" class="btn-kosta btn mt-3">
                    Mulai Cari Kos
                </a>
            </div>
        <?php endif; ?>

        <?php mysqli_close($koneksi); ?>
    </div>
</section>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
<?php require_once __DIR__ . '/../../components/scripts.php'; ?>
