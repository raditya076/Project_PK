<?php

require_once '../config/koneksi.php';
require_once '../config/session.php';

// Ambil parameter filter dari URL
$keyword  = trim($_GET['q']     ?? '');
$tipe     = trim($_GET['tipe']  ?? '');
$kota     = trim($_GET['kota']  ?? '');
$harga_max = (int)($_GET['harga_max'] ?? 0);
$order    = trim($_GET['order'] ?? 'terbaru');

// --- Bangun Query SQL secara dinamis ---
$sql = "SELECT k.*, u.nama AS nama_pemilik
        FROM kos k
        LEFT JOIN users u ON k.pemilik_id = u.id
        WHERE k.status = 'aktif'";

// Array kondisi filter (lebih rapi dari string concatenation)
$kondisi = [];

if (!empty($keyword)) {
    $kw = mysqli_real_escape_string($koneksi, $keyword);
    $kondisi[] = "(k.nama_kos LIKE '%$kw%' OR k.alamat LIKE '%$kw%' OR k.kota LIKE '%$kw%')";
}
if (!empty($tipe) && in_array($tipe, ['putra', 'putri', 'campur'])) {
    $tipe_aman = mysqli_real_escape_string($koneksi, $tipe);
    $kondisi[] = "k.tipe = '$tipe_aman'";
}
if (!empty($kota)) {
    $kota_aman = mysqli_real_escape_string($koneksi, $kota);
    $kondisi[] = "k.kota LIKE '%$kota_aman%'";
}
if ($harga_max > 0) {
    $kondisi[] = "k.harga_per_bulan <= $harga_max";
}

// Tambahkan kondisi ke query jika ada
if (!empty($kondisi)) {
    $sql .= " AND " . implode(" AND ", $kondisi);
}

// Urutkan hasil
$urutan_map = [
    'terbaru'    => 'k.created_at DESC',
    'termurah'   => 'k.harga_per_bulan ASC',
    'termahal'   => 'k.harga_per_bulan DESC',
];
$order_sql = $urutan_map[$order] ?? 'k.created_at DESC';
$sql .= " ORDER BY $order_sql";

$result      = mysqli_query($koneksi, $sql);
$jumlah_kos  = mysqli_num_rows($result);

// Ambil daftar kota untuk filter dropdown
$result_kota = mysqli_query($koneksi, "SELECT DISTINCT kota FROM kos WHERE status='aktif' ORDER BY kota");

$judul_halaman = "Cari Kos";
$css_tambahan  = "home.css";

require_once '../components/head.php';
require_once '../components/navbar.php';
?>

<div style="background:var(--color-surface); border-bottom:1px solid var(--color-border); padding:20px 0;">
    <div class="container">
        <h1 style="font-size:18px; font-weight:800; margin:0; color:var(--color-text);">
            Cari Kos
            <span style="font-size:14px; font-weight:500; color:var(--color-text-muted);">
                — <?= $jumlah_kos ?> kos ditemukan
            </span>
        </h1>
    </div>
</div>

<!-- Filter Bar -->
<div style="background:var(--color-surface-alt); border-bottom:1px solid var(--color-border); padding:14px 0;">
    <div class="container">
        <form action="<?= BASE_URL ?>/pages/cari.php" method="GET" id="form-filter">
            <div class="row g-2 align-items-end">

                <!-- Input Keyword -->
                <div class="col-lg-4 col-md-12">
                    <input type="text" name="q" class="form-control" style="font-size:14px; font-family:var(--font-main);"
                           placeholder="Nama kos, alamat, kota..."
                           value="<?= htmlspecialchars($keyword) ?>">
                </div>

                <!-- Filter Tipe -->
                <div class="col-lg-2 col-md-4 col-6">
                    <select name="tipe" class="form-select" style="font-size:13px; font-family:var(--font-main);">
                        <option value="">Semua Tipe</option>
                        <option value="putra"  <?= $tipe === 'putra'  ? 'selected' : '' ?>>Putra</option>
                        <option value="putri"  <?= $tipe === 'putri'  ? 'selected' : '' ?>>Putri</option>
                        <option value="campur" <?= $tipe === 'campur' ? 'selected' : '' ?>>Campur</option>
                    </select>
                </div>

                <!-- Filter Kota -->
                <div class="col-lg-2 col-md-4 col-6">
                    <select name="kota" class="form-select" style="font-size:13px; font-family:var(--font-main);">
                        <option value="">Semua Kota</option>
                        <?php while ($row_kota = mysqli_fetch_assoc($result_kota)): ?>
                            <option value="<?= htmlspecialchars($row_kota['kota']) ?>"
                                <?= ($kota === $row_kota['kota']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row_kota['kota']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Urutkan -->
                <div class="col-lg-2 col-md-4 col-6">
                    <select name="order" class="form-select" style="font-size:13px; font-family:var(--font-main);">
                        <option value="terbaru"  <?= $order === 'terbaru'  ? 'selected' : '' ?>>Terbaru</option>
                        <option value="termurah" <?= $order === 'termurah' ? 'selected' : '' ?>>Termurah</option>
                        <option value="termahal" <?= $order === 'termahal' ? 'selected' : '' ?>>Termahal</option>
                    </select>
                </div>

                <!-- Tombol -->
                <div class="col-lg-2 col-md-4 col-6">
                    <div style="display:flex; gap:6px;">
                        <button type="submit" class="btn-kosta btn" style="flex:1; font-size:13px;">Filter</button>
                        <a href="<?= BASE_URL ?>/pages/cari.php" class="btn-kosta-outline btn" style="font-size:13px;">Reset</a>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- Grid Hasil -->
<section class="section-listing">
    <div class="container">
        <?php if ($jumlah_kos > 0): ?>
            <div class="row g-4">
                <?php while ($kos = mysqli_fetch_assoc($result)):
                    $harga_format = 'Rp ' . number_format($kos['harga_per_bulan'], 0, ',', '.');
                    $kamar_sisa   = $kos['jumlah_kamar'] - $kos['kamar_terisi'];
                    $hari_lalu    = (time() - strtotime($kos['created_at'])) / 86400;
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
                                    <div class="placeholder-text">Foto belum tersedia</div>
                                </div>
                            <?php endif; ?>
                            <?php if ($hari_lalu < 7): ?><span class="kos-ribbon">Baru</span><?php endif; ?>
                            <button class="kos-wishlist-btn" title="Simpan">🤍</button>
                        </div>
                        <div class="kos-card-body">
                            <span class="badge-kos <?= $tipe_class ?>"><?= ucfirst($kos['tipe']) ?></span>
                            <h3 class="kos-card-name mt-2"><?= htmlspecialchars($kos['nama_kos']) ?></h3>
                            <p class="kos-card-location">
                                <span class="icon">📍</span>
                                <?= htmlspecialchars($kos['kota']) ?>
                                <?php if (!empty($kos['kecamatan'])): ?>, <?= htmlspecialchars($kos['kecamatan']) ?><?php endif; ?>
                            </p>
                            <div class="kos-facilities">
                                <?php if ($kos['wifi']): ?><span class="facility-item">📶 WiFi</span><?php endif; ?>
                                <?php if ($kos['ac']): ?><span class="facility-item">❄️ AC</span><?php endif; ?>
                                <?php if ($kos['kamar_mandi_dalam']): ?><span class="facility-item">🚿 KM Dalam</span><?php endif; ?>
                                <?php if ($kos['parkir']): ?><span class="facility-item">🅿️ Parkir</span><?php endif; ?>
                            </div>
                            <div class="kos-card-meta">
                                <div class="kos-card-price"><?= $harga_format ?><span>/ bulan</span></div>
                                <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos['id'] ?>"
                                   class="btn-kosta btn" style="font-size:12px; padding:7px 16px;">
                                    <?= $kamar_sisa > 0 ? 'Lihat Detail' : 'Penuh' ?>
                                </a>
                            </div>
                            <?php if ($kamar_sisa <= 2 && $kamar_sisa > 0): ?>
                                <p style="font-size:11px;color:#e67e00;font-weight:600;margin-top:8px;margin-bottom:0;">
                                    ⚠️ Sisa <?= $kamar_sisa ?> kamar!
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🔍</div>
                <h5>Tidak Ada Hasil</h5>
                <p>Coba ubah filter pencarian kamu.</p>
                <a href="<?= BASE_URL ?>/pages/cari.php" class="btn-kosta btn mt-3">Reset Filter</a>
            </div>
        <?php endif; ?>

        <?php mysqli_close($koneksi); ?>
    </div>
</section>

<?php require_once '../components/footer.php'; ?>
<?php require_once '../components/scripts.php'; ?>
