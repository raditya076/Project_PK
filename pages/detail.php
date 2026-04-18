<?php
// Halaman detail kos: galeri foto, peta, review, perbandingan, kontak WA
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

$id_kos = (int)($_GET['id'] ?? 0);
if ($id_kos <= 0) redirect(BASE_URL . '/pages/cari.php');

// Ambil data kos + pemilik
$stmt = mysqli_prepare($koneksi,
    "SELECT k.*, u.nama AS nama_pemilik, u.no_hp AS hp_pemilik, u.email AS email_pemilik
     FROM kos k LEFT JOIN users u ON k.pemilik_id = u.id
     WHERE k.id = ? AND k.status = 'aktif' LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_kos);
mysqli_stmt_execute($stmt);
$kos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$kos) {
    http_response_code(404);
    $judul_halaman = "Kos Tidak Ditemukan";
    require_once __DIR__ . '/../components/head.php';
    require_once __DIR__ . '/../components/navbar.php'; ?>
    <div class="container" style="padding:80px 0;text-align:center;">
        <div style="font-size:64px;margin-bottom:16px;">🏚️</div>
        <h1 style="font-size:24px;font-weight:800;">Kos Tidak Ditemukan</h1>
        <p style="color:var(--color-text-muted);margin-top:8px;">Kos ini sudah tidak aktif atau dihapus.</p>
        <a href="<?= BASE_URL ?>/pages/cari.php" class="btn-kosta btn mt-4">← Cari Kos Lain</a>
    </div>
    <?php require_once '../components/footer.php';
    require_once '../components/scripts.php'; exit;
}

// Status favorit & perbandingan
$sudah_favorit     = false;
$kos_dibandingkan  = $_SESSION['bandingkan'] ?? [];
$sudah_dibandingkan = in_array($id_kos, $kos_dibandingkan);
$penuh_bandingkan   = count($kos_dibandingkan) >= 3;

// URL halaman ini sendiri — dipakai untuk:
//   1. Redirect kembali setelah user login (dari tombol "Login untuk Booking")
//   2. Redirect kembali setelah user kirim review
// http_build_query() memastikan karakter spesial di-encode dengan benar
$url_sekarang = BASE_URL . '/pages/detail.php?id=' . $id_kos;


if (sudah_login()) {
    $uid = $_SESSION['user_id'];
    $q   = mysqli_prepare($koneksi, "SELECT id FROM favorites WHERE user_id=? AND kos_id=? LIMIT 1");
    mysqli_stmt_bind_param($q, 'ii', $uid, $id_kos);
    mysqli_stmt_execute($q);
    mysqli_stmt_store_result($q);
    $sudah_favorit = mysqli_stmt_num_rows($q) > 0;
}



// Review & rating
// Ambil statistik rating (rata-rata dan distribusi per bintang)
$stmt_stat = mysqli_prepare($koneksi,
    "SELECT
        COUNT(*) AS total,
        ROUND(AVG(rating), 1) AS rata_rata,
        SUM(rating = 5) AS bintang5,
        SUM(rating = 4) AS bintang4,
        SUM(rating = 3) AS bintang3,
        SUM(rating = 2) AS bintang2,
        SUM(rating = 1) AS bintang1
     FROM reviews WHERE kos_id = ?"
);
mysqli_stmt_bind_param($stmt_stat, 'i', $id_kos);
mysqli_stmt_execute($stmt_stat);
$stat_review = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stat));

// Ambil semua review untuk ditampilkan
$stmt_rev = mysqli_prepare($koneksi,
    "SELECT r.*, u.nama AS nama_reviewer
     FROM reviews r
     JOIN users u ON r.user_id = u.id
     WHERE r.kos_id = ?
     ORDER BY r.created_at DESC"
);
mysqli_stmt_bind_param($stmt_rev, 'i', $id_kos);
mysqli_stmt_execute($stmt_rev);
$result_review = mysqli_stmt_get_result($stmt_rev);

// Cek apakah user login sudah pernah review kos ini
$sudah_review = false;
if (sudah_login()) {
    $cek_rev = mysqli_prepare($koneksi,
        "SELECT id FROM reviews WHERE user_id = ? AND kos_id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($cek_rev, 'ii', $_SESSION['user_id'], $id_kos);
    mysqli_stmt_execute($cek_rev);
    mysqli_stmt_store_result($cek_rev);
    $sudah_review = mysqli_stmt_num_rows($cek_rev) > 0;
}

// Status booking user di kos ini (menentukan tombol yang tampil di sidebar)
$status_booking_user = null;  // null = belum pernah booking
$id_booking_user     = null;
if (sudah_login() && user_login()['role'] === 'pencari') {
    // Cek apakah tabel bookings sudah ada
    $tbl_cek = mysqli_query($koneksi, "SHOW TABLES LIKE 'bookings'");
    if (mysqli_num_rows($tbl_cek) > 0) {
        $cek_bk = mysqli_prepare($koneksi,
            "SELECT id, status FROM bookings
             WHERE kos_id = ? AND penyewa_id = ?
             ORDER BY created_at DESC LIMIT 1"
        );
        mysqli_stmt_bind_param($cek_bk, 'ii', $id_kos, $_SESSION['user_id']);
        mysqli_stmt_execute($cek_bk);
        $bk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($cek_bk));
        if ($bk_row) {
            $status_booking_user = $bk_row['status'];
            $id_booking_user     = $bk_row['id'];
        }
    }
}

// Foto galeri dari tabel kos_foto
$foto_galeri = [];
// Cek apakah tabel kos_foto sudah ada
$cek_tbl = mysqli_query($koneksi, "SHOW TABLES LIKE 'kos_foto'");
if (mysqli_num_rows($cek_tbl) > 0) {
    $stmt_foto = mysqli_prepare($koneksi,
        "SELECT nama_file FROM kos_foto WHERE kos_id = ? ORDER BY urutan ASC, id ASC"
    );
    mysqli_stmt_bind_param($stmt_foto, 'i', $id_kos);
    mysqli_stmt_execute($stmt_foto);
    $res_foto = mysqli_stmt_get_result($stmt_foto);
    while ($f = mysqli_fetch_assoc($res_foto)) {
        $foto_galeri[] = $f['nama_file'];
    }
}
// Fallback: jika kos_foto kosong tapi foto_utama ada, gunakan itu
if (empty($foto_galeri) && !empty($kos['foto_utama'])) {
    $foto_galeri[] = $kos['foto_utama'];
}

// Kos terkait di kota yang sama
$stmt_terkait = mysqli_prepare($koneksi,
    "SELECT id, nama_kos, tipe, harga_per_bulan, kota, foto_utama
     FROM kos WHERE kota=? AND id!=? AND status='aktif' ORDER BY RAND() LIMIT 3"
);
mysqli_stmt_bind_param($stmt_terkait, 'si', $kos['kota'], $id_kos);
mysqli_stmt_execute($stmt_terkait);
$result_terkait = mysqli_stmt_get_result($stmt_terkait);

// Persiapan variabel tampilan
$kamar_tersedia = $kos['jumlah_kamar'] - $kos['kamar_terisi'];
$harga_format   = 'Rp ' . number_format($kos['harga_per_bulan'], 0, ',', '.');
$url_sekarang   = BASE_URL . '/pages/detail.php?id=' . $id_kos;
$has_coords     = !empty($kos['lat']) && !empty($kos['lng']);
$lat_kos        = $kos['lat'] ?? -6.2088;   // Default: Jakarta pusat
$lng_kos        = $kos['lng'] ?? 106.8456;



$fasilitas_list = [
    'wifi'              => ['📶', 'WiFi / Internet'],
    'ac'                => ['❄️', 'Air Conditioner'],
    'kamar_mandi_dalam' => ['🚿', 'Kamar Mandi Dalam'],
    'parkir'            => ['🅿️', 'Parkir Kendaraan'],
    'dapur'             => ['🍳', 'Dapur Bersama'],
    'laundry'           => ['👕', 'Layanan Laundry'],
    'security'          => ['👮', 'Petugas Keamanan'],
    'cctv'              => ['📹', 'CCTV 24 Jam'],
];

// WA link
$wa_pesan = urlencode("Halo, saya tertarik dengan kos *{$kos['nama_kos']}* di Kosta'. Apakah masih tersedia?");
$wa_link  = !empty($kos['hp_pemilik'])
    ? "https://wa.me/62" . ltrim($kos['hp_pemilik'], '0') . "?text={$wa_pesan}"
    : '';

// CSS tambahan: peta, perbandingan, review, transaksi
$extra_head = '
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/maps.css">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/compare.css">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/review.css">
    <link rel="stylesheet" href="' . BASE_URL . '/assets/css/transaction.css">
';

$judul_halaman = $kos['nama_kos'];
$css_tambahan  = "detail.css";

require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<!-- Flash -->
<div class="container" style="padding-top:12px;"><?= get_flash() ?></div>

<!-- Breadcrumb -->
<div class="breadcrumb-bar">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/cari.php">Cari Kos</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars(mb_strimwidth($kos['nama_kos'],0,40,'...')) ?></li>
            </ol>
        </nav>
    </div>
</div>


<!-- Konten detail utama -->
<section class="detail-section-main">
<div class="container">
<div class="row g-4">

    <!-- Kolom kiri -->
    <div class="col-lg-8">

        <!-- Galeri foto (slideshow jika lebih dari 1 foto) -->
        <div class="detail-photo-wrapper" style="position:relative;">
            <?php if (!empty($foto_galeri)): ?>

                <!-- Slideshow container -->
                <div id="galeri-wrap" style="position:relative;overflow:hidden;border-radius:12px;">
                    <?php foreach ($foto_galeri as $gi => $gfoto): ?>
                        <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($gfoto) ?>"
                             alt="Foto <?= htmlspecialchars($kos['nama_kos']) ?> <?= $gi+1 ?>"
                             class="galeri-slide"
                             style="width:100%;display:<?= $gi === 0 ? 'block' : 'none' ?>;max-height:420px;object-fit:contain;background:#1a1a1a;border-radius:12px;">
                    <?php endforeach; ?>

                    <?php if (count($foto_galeri) > 1): ?>
                        <!-- Tombol prev/next -->
                        <button onclick="galeriNav(-1)" class="galeri-btn galeri-prev" title="Sebelumnya">
                            &#8249;
                        </button>
                        <button onclick="galeriNav(1)" class="galeri-btn galeri-next" title="Berikutnya">
                            &#8250;
                        </button>

                        <!-- Counter foto: "2 / 5" -->
                        <div class="galeri-counter" id="galeri-counter">
                            1 / <?= count($foto_galeri) ?>
                        </div>

                        <!-- Dot navigasi -->
                        <div class="galeri-dots">
                            <?php foreach ($foto_galeri as $gi => $_): ?>
                                <span class="galeri-dot <?= $gi === 0 ? 'aktif' : '' ?>"
                                      onclick="galeriJump(<?= $gi ?>)"></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="detail-photo-placeholder">
                    <span style="font-size:72px;opacity:.3;">🏠</span>
                    <p style="margin-top:12px;font-size:14px;color:var(--color-text-muted);opacity:.6;">Foto belum tersedia</p>
                </div>
            <?php endif; ?>

            <div class="photo-overlay-badge">
                <span class="badge-kos <?= $kos['tipe'] ?>"><?= ucfirst($kos['tipe']) ?></span>
            </div>
        </div>

        <!-- INFO CARD UTAMA -->
        <div class="detail-info-card">

            <!-- Judul + Harga -->
            <div class="detail-top-row">
                <div class="detail-top-left">
                    <h1 class="detail-title"><?= htmlspecialchars($kos['nama_kos']) ?></h1>
                    <p class="detail-location">
                        📍 <?= htmlspecialchars($kos['alamat']) ?>
                        <?php if (!empty($kos['kecamatan'])): ?>, <?= htmlspecialchars($kos['kecamatan']) ?><?php endif; ?>
                        , <?= htmlspecialchars($kos['kota']) ?>
                        <?php if (!empty($kos['provinsi'])): ?>, <?= htmlspecialchars($kos['provinsi']) ?><?php endif; ?>
                    </p>
                </div>
                <div class="detail-top-right">
                    <div class="detail-price"><?= $harga_format ?></div>
                    <div class="detail-price-sub">per bulan</div>
                </div>
            </div>

            <!-- Badge ketersediaan -->
            <div class="detail-availability <?= $kamar_tersedia > 0 ? 'available' : 'full' ?>">
                <?= $kamar_tersedia > 0
                    ? "✅ <strong>{$kamar_tersedia} kamar tersedia</strong> dari {$kos['jumlah_kamar']} kamar total"
                    : "❌ <strong>Semua kamar terisi</strong> — Tidak ada kamar kosong saat ini" ?>
                <?php if ($kamar_tersedia > 0 && $kamar_tersedia <= 2): ?>
                    <span class="availability-urgent">— Segera pesan!</span>
                <?php endif; ?>
            </div>

            <!-- TOMBOL AKSI CEPAT -->
            <div class="detail-quick-actions">

                <!-- Favorit -->
                <?php if (sudah_login()): ?>
                    <form action="<?= BASE_URL ?>/pages/favorit/toggle.php" method="POST" style="display:inline;">
                        <input type="hidden" name="kos_id"  value="<?= $id_kos ?>">
                        <input type="hidden" name="kembali" value="<?= $url_sekarang ?>">
                        <button type="submit" class="btn-action-detail <?= $sudah_favorit ? 'favorit-aktif' : 'favorit-tidak' ?>">
                            <?= $sudah_favorit ? '❤️ Tersimpan' : '🤍 Simpan Favorit' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/pages/login.php?pesan=login_dulu" class="btn-action-detail favorit-tidak">
                        🤍 Simpan Favorit
                    </a>
                <?php endif; ?>

                <!-- Tombol bandingkan -->
                <?php if ($sudah_dibandingkan): ?>
                    <!-- Sudah ada di perbandingan: tampilkan opsi lihat/hapus -->
                    <a href="<?= BASE_URL ?>/pages/bandingkan/index.php" class="btn-action-detail"
                       style="border-color:#1d4ed8;color:#1d4ed8;background:#EFF6FF;">
                        ⚖️ Lihat Perbandingan
                    </a>
                <?php elseif ($penuh_bandingkan): ?>
                    <!-- Slot penuh -->
                    <span class="btn-action-detail" style="cursor:not-allowed;opacity:0.6;" title="Maksimal 3 kos">
                        ⚖️ Slot Penuh (3/3)
                    </span>
                <?php else: ?>
                    <!-- Tambahkan ke perbandingan -->
                    <form action="<?= BASE_URL ?>/pages/bandingkan/tambah.php" method="POST" style="display:inline;">
                        <input type="hidden" name="kos_id"  value="<?= $id_kos ?>">
                        <input type="hidden" name="kembali" value="<?= $url_sekarang ?>">
                        <button type="submit" class="btn-action-detail">
                            ⚖️ Bandingkan
                            <?php if (!empty($kos_dibandingkan)): ?>
                                <span style="font-size:10px;opacity:.7;">(<?= count($kos_dibandingkan) ?>/3)</span>
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Bagikan -->
                <button class="btn-action-detail" id="btn-share"
                        onclick="shareKos()">
                    🔗 Bagikan
                </button>

            </div><!-- /detail-quick-actions -->

            <!-- DESKRIPSI -->
            <?php if (!empty($kos['deskripsi'])): ?>
            <div class="detail-block">
                <h2 class="detail-block-title">Tentang Kos Ini</h2>
                <p class="detail-desc"><?= nl2br(htmlspecialchars($kos['deskripsi'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- FASILITAS -->
            <div class="detail-block">
                <h2 class="detail-block-title">Fasilitas Lengkap</h2>
                <div class="fasilitas-detail-grid">
                    <?php foreach ($fasilitas_list as $kolom => [$ikon, $label]): ?>
                    <div class="fasilitas-item <?= $kos[$kolom] ? 'ada' : 'tidak' ?>">
                        <span class="fas-icon"><?= $ikon ?></span>
                        <span class="fas-label"><?= $label ?></span>
                        <?= $kos[$kolom] ? '<span class="fas-check">✓</span>' : '<span class="fas-unavail">✕</span>' ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Peta OpenStreetMap via iframe (gratis, tanpa API key) -->
            <div class="detail-block">
                <h2 class="detail-block-title">📍 Lokasi di Peta</h2>

                <?php if ($has_coords): ?>
                    <!-- Embed OpenStreetMap via iframe — tanpa library JS, selalu bekerja -->
                    <div class="osm-map-wrapper">
                        <iframe
                            src="https://www.openstreetmap.org/export/embed.html?bbox=<?= $lng_kos - 0.005 ?>,<?= $lat_kos - 0.003 ?>,<?= $lng_kos + 0.005 ?>,<?= $lat_kos + 0.003 ?>&layer=mapnik&marker=<?= $lat_kos ?>,<?= $lng_kos ?>"
                            class="osm-iframe"
                            allowfullscreen
                            loading="lazy"
                            title="Lokasi <?= htmlspecialchars($kos['nama_kos']) ?>">
                        </iframe>
                    </div>
                <?php else: ?>
                    <!-- Tidak ada koordinat GPS — tampilkan fallback informatif -->
                    <div class="map-unavailable">
                        <div class="map-unav-icon">🗺️</div>
                        <p>Koordinat GPS belum ditambahkan oleh pemilik kos.</p>
                    </div>
                <?php endif; ?>

                <!-- Teks alamat + link Google Maps -->
                <div class="map-address-text">
                    <span>📍</span>
                    <span><?= htmlspecialchars($kos['alamat'])
                        . (!empty($kos['kecamatan']) ? ', ' . $kos['kecamatan'] : '')
                        . ', ' . $kos['kota'] ?></span>
                </div>
                <?php if ($has_coords): ?>
                    <a href="https://www.google.com/maps?q=<?= $lat_kos ?>,<?= $lng_kos ?>"
                       target="_blank" rel="noopener" class="map-open-link">
                        🗺️ Buka di Google Maps →
                    </a>
                <?php endif; ?>
            </div>


            <!-- INFO TAMBAHAN -->
            <div class="detail-block">
                <h2 class="detail-block-title">Informasi Tambahan</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Tipe Penghuni</span>
                        <span class="info-value"><span class="badge-kos <?= $kos['tipe'] ?>"><?= ucfirst($kos['tipe']) ?></span></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Kamar</span>
                        <span class="info-value"><?= $kos['jumlah_kamar'] ?> kamar</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Kamar Tersedia</span>
                        <span class="info-value" style="color:<?= $kamar_tersedia > 0 ? '#15803d' : '#b91c1c' ?>;font-weight:700;"><?= $kamar_tersedia ?> kamar</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Kota</span>
                        <span class="info-value"><?= htmlspecialchars($kos['kota']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Terdaftar Sejak</span>
                        <span class="info-value"><?= date('d F Y', strtotime($kos['created_at'])) ?></span>
                    </div>
                </div>
            </div>

        </div><!-- /detail-info-card -->

        <!-- Section review & rating -->
        <div class="detail-info-card" id="ulasan" style="margin-top:24px;">

            <!-- Header: Judul + Statistik Ringkas -->
            <div class="review-section-header">
                <h2 class="detail-block-title" style="margin:0;">
                    ⭐ Ulasan Penghuni
                    <?php if ($stat_review['total'] > 0): ?>
                        <span style="font-size:13px;font-weight:500;color:var(--color-text-muted);">
                            (<?= $stat_review['total'] ?> ulasan)
                        </span>
                    <?php endif; ?>
                </h2>
            </div>

            <?php if ($stat_review['total'] > 0): ?>
            <!-- Ringkasan rata-rata + distribusi bar -->
            <div class="review-summary" style="margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--color-border);display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
                <div style="text-align:center;min-width:80px;">
                    <div class="review-avg-score"><?= $stat_review['rata_rata'] ?></div>
                    <div class="review-avg-stars">
                        <?php
                        $avg = (float)$stat_review['rata_rata'];
                        for ($s = 1; $s <= 5; $s++) {
                            echo '<span style="font-size:18px;color:' . ($s <= round($avg) ? '#f59e0b' : '#d1d5db') . ';">' . ($s <= round($avg) ? '★' : '☆') . '</span>';
                        }
                        ?>
                    </div>
                    <div class="review-avg-count"><?= $stat_review['total'] ?> ulasan</div>
                </div>

                <!-- Bar distribusi bintang -->
                <div style="flex:1;min-width:180px;">
                    <?php
                    $dist = [5=>'bintang5',4=>'bintang4',3=>'bintang3',2=>'bintang2',1=>'bintang1'];
                    foreach ($dist as $num => $col):
                        $count = (int)$stat_review[$col];
                        $pct   = $stat_review['total'] > 0 ? round($count / $stat_review['total'] * 100) : 0;
                    ?>
                    <div class="star-bar-row">
                        <span class="star-bar-label" style="color:#f59e0b;"><?= $num ?>★</span>
                        <div class="star-bar-track">
                            <div class="star-bar-fill" style="width:<?= $pct ?>%;"></div>
                        </div>
                        <span class="star-bar-count"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Daftar ulasan -->
            <?php while ($rev = mysqli_fetch_assoc($result_review)): ?>
            <div class="review-card">
                <div class="review-header">
                    <div class="reviewer-row">
                        <div class="reviewer-avatar">
                            <?= strtoupper(substr($rev['nama_reviewer'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="reviewer-name"><?= htmlspecialchars($rev['nama_reviewer']) ?></div>
                            <div class="review-date"><?= date('d M Y', strtotime($rev['created_at'])) ?></div>
                        </div>
                    </div>
                    <div class="review-stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <span style="font-size:16px;color:<?= $s <= $rev['rating'] ? '#f59e0b' : '#d1d5db' ?>;">
                                <?= $s <= $rev['rating'] ? '★' : '☆' ?>
                            </span>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php if (!empty($rev['judul'])): ?>
                    <div class="review-judul"><?= htmlspecialchars($rev['judul']) ?></div>
                <?php endif; ?>
                <div class="review-isi"><?= nl2br(htmlspecialchars($rev['isi_ulasan'])) ?></div>
            </div>
            <?php endwhile; ?>

            <?php else: ?>
            <div style="text-align:center;padding:32px 0;color:var(--color-text-muted);">
                <div style="font-size:40px;margin-bottom:12px;opacity:.3;">💬</div>
                <p style="font-size:14px;">Belum ada ulasan untuk kos ini. Jadilah yang pertama!</p>
            </div>
            <?php endif; ?>

            <!-- Form Tulis Ulasan -->
            <div class="write-review-card">
                <h3 class="write-review-title">✍️ Tulis Ulasanmu</h3>

                <?php if (sudah_login() && !$sudah_review): ?>
                    <form action="<?= BASE_URL ?>/pages/review/kirim.php" method="POST" id="form-ulasan">
                        <input type="hidden" name="kos_id"  value="<?= $id_kos ?>">
                        <input type="hidden" name="kembali" value="<?= $url_sekarang ?>#ulasan">

                        <!-- Star Picker interaktif dengan JS -->
                        <label style="font-size:12px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:8px;">Rating *</label>
                        <div class="star-picker-js" id="star-picker" style="display:flex;gap:6px;margin-bottom:16px;cursor:pointer;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star-btn" data-val="<?= $i ?>"
                                      style="font-size:32px;color:#d1d5db;transition:color .15s;line-height:1;">★</span>
                            <?php endfor; ?>
                            <input type="hidden" name="rating" id="rating-value" value="">
                        </div>
                        <p id="rating-hint" style="font-size:11px;color:#b91c1c;display:none;margin-top:-10px;margin-bottom:12px;">⚠️ Pilih rating bintang terlebih dahulu.</p>

                        <div class="form-group-kosta" style="margin-bottom:10px;">
                            <input type="text" name="judul" class="form-input-kosta"
                                   placeholder="Judul ulasan (opsional, maks. 120 karakter)"
                                   maxlength="120">
                        </div>
                        <div class="form-group-kosta" style="margin-bottom:14px;">
                            <textarea name="isi_ulasan" class="form-textarea-kosta"
                                      placeholder="Ceritakan pengalamanmu tinggal di kos ini..."
                                      rows="4" minlength="10" required></textarea>
                        </div>
                        <button type="submit" class="btn-kosta btn" style="font-size:13px;">
                            Kirim Ulasan →
                        </button>
                    </form>

                    <script>
                    (function() {
                        var stars  = document.querySelectorAll('#star-picker .star-btn');
                        var input  = document.getElementById('rating-value');
                        var hint   = document.getElementById('rating-hint');
                        var form   = document.getElementById('form-ulasan');
                        var curVal = 0;

                        // Hover: highlight bintang 1..i
                        stars.forEach(function(star, idx) {
                            star.addEventListener('mouseenter', function() {
                                stars.forEach(function(s, j) {
                                    s.style.color = j <= idx ? '#f59e0b' : '#d1d5db';
                                });
                            });
                            // Klik: set nilai rating
                            star.addEventListener('click', function() {
                                curVal = parseInt(this.dataset.val);
                                input.value = curVal;
                                hint.style.display = 'none';
                                stars.forEach(function(s, j) {
                                    s.style.color = j < curVal ? '#f59e0b' : '#d1d5db';
                                });
                            });
                        });

                        // Reset warna saat mouse keluar dari picker
                        document.getElementById('star-picker').addEventListener('mouseleave', function() {
                            stars.forEach(function(s, j) {
                                s.style.color = j < curVal ? '#f59e0b' : '#d1d5db';
                            });
                        });

                        // Validasi sebelum submit
                        form.addEventListener('submit', function(e) {
                            if (!input.value || input.value < 1) {
                                e.preventDefault();
                                hint.style.display = 'block';
                                document.getElementById('star-picker').scrollIntoView({behavior:'smooth', block:'center'});
                            }
                        });
                    })();
                    </script>

                <?php elseif ($sudah_review): ?>
                    <div class="review-login-prompt">
                        ✅ Kamu sudah memberikan ulasan untuk kos ini. Terima kasih!
                    </div>
                <?php else: ?>
                    <div class="review-login-prompt">
                        Kamu harus <a href="<?= BASE_URL ?>/pages/login.php">login</a> untuk menulis ulasan.
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /review section -->

        <!-- KOS TERKAIT -->
        <?php if (mysqli_num_rows($result_terkait) > 0): ?>
        <div style="margin-top:28px;">
            <h3 style="font-size:16px;font-weight:800;margin-bottom:16px;">
                Kos Lain di <?= htmlspecialchars($kos['kota']) ?>
            </h3>
            <div class="row g-3">
            <?php while ($terkait = mysqli_fetch_assoc($result_terkait)): ?>
                <div class="col-md-4">
                    <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $terkait['id'] ?>" class="kos-card-mini">
                        <div class="kos-card-mini-img">
                            <?php if (!empty($terkait['foto_utama'])): ?>
                                <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($terkait['foto_utama']) ?>"
                                     alt="<?= htmlspecialchars($terkait['nama_kos']) ?>">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:28px;background:#F0EDE8;">🏠</div>
                            <?php endif; ?>
                        </div>
                        <div class="kos-card-mini-body">
                            <div class="kos-card-mini-name"><?= htmlspecialchars($terkait['nama_kos']) ?></div>
                            <div class="kos-card-mini-price">Rp <?= number_format($terkait['harga_per_bulan'],0,',','.') ?>/bln</div>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /col-lg-8 -->


    <!-- Kolom kanan (sidebar) -->
    <div class="col-lg-4">
        <div class="contact-sticky-wrapper">

            <!-- Kartu Harga -->
            <div class="price-card">
                <div class="price-card-value"><?= $harga_format ?></div>
                <div class="price-card-period">per bulan / kamar</div>
                <div class="price-card-availability <?= $kamar_tersedia > 0 ? 'ok' : 'penuh' ?>">
                    <?= $kamar_tersedia > 0 ? "✅ {$kamar_tersedia} kamar tersedia" : "❌ Semua kamar penuh" ?>
                </div>

                <!-- TOMBOL PESAN SEKARANG -->
                <div style="margin-top:14px;">
                <?php
                // Label status booking untuk ditampilkan ke user
                $label_bk = [
                    'menunggu_pembayaran' => ['⏳','Booking dibuat, belum bayar','#92660a'],
                    'aktif'              => ['✅','Kamu sedang menghuni kos ini','#15803d'],
                    'ditolak'            => ['❌','Booking ditolak','#b91c1c'],
                    'selesai'            => ['🏁','Masa sewa telah selesai','#525252'],
                    'dibatalkan'         => ['🚫','Booking dibatalkan','#78716c'],
                ];
                ?>
                <?php if ($status_booking_user === null): ?>
                    <!-- Belum booking — tampilkan tombol Pesan Sekarang -->
                    <?php if ($kamar_tersedia > 0): ?>
                        <?php if (sudah_login() && user_login()['role'] === 'pencari'): ?>
                            <a href="<?= BASE_URL ?>/pages/booking.php?id=<?= $id_kos ?>"
                               class="btn-kosta btn"
                               style="width:100%;display:block;text-align:center;padding:12px;font-size:15px;font-weight:800;">
                                🛏️ Pesan Sekarang
                            </a>
                        <?php elseif (!sudah_login()): ?>
                            <a href="<?= BASE_URL ?>/pages/login.php?kembali=<?= urlencode($url_sekarang) ?>"
                               class="btn-kosta btn"
                               style="width:100%;display:block;text-align:center;padding:12px;font-size:15px;">
                                🔑 Login untuk Booking
                            </a>
                        <?php else: ?>
                            <!-- User adalah pemilik, tidak bisa booking -->
                            <div style="font-size:12px;color:var(--color-text-muted);text-align:center;padding:10px 0;">
                                ℹ️ Pemilik kos tidak bisa melakukan booking.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <button disabled class="btn-kosta btn"
                                style="width:100%;display:block;opacity:.5;cursor:not-allowed;padding:12px;">
                            ❌ Kamar Penuh
                        </button>
                    <?php endif; ?>

                <?php elseif (in_array($status_booking_user, ['menunggu_pembayaran','aktif'])): ?>
                    <!-- Sudah punya booking aktif -->
                    <?php [$bk_ico, $bk_msg, $bk_color] = $label_bk[$status_booking_user]; ?>
                    <div style="background:var(--color-bg);border:1.5px solid var(--color-border);border-radius:8px;padding:12px;text-align:center;margin-bottom:10px;">
                        <div style="font-size:20px;margin-bottom:4px;"><?= $bk_ico ?></div>
                        <div style="font-size:12px;font-weight:700;color:<?= $bk_color ?>;"><?= $bk_msg ?></div>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/riwayat.php"
                       class="btn-kosta-outline btn"
                       style="width:100%;display:block;text-align:center;font-size:13px;">
                        Lihat Riwayat Booking →
                    </a>
                    <?php if ($status_booking_user === 'menunggu_pembayaran'): ?>
                        <a href="<?= BASE_URL ?>/pages/pembayaran.php?booking_id=<?= $id_booking_user ?>"
                           class="btn-kosta btn"
                           style="width:100%;display:block;text-align:center;font-size:13px;margin-top:8px;">
                            💳 Bayar Sekarang
                        </a>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Status selesai / dibatalkan / ditolak — bisa booking lagi jika ada kamar -->
                    <?php [$bk_ico, $bk_msg, $bk_color] = $label_bk[$status_booking_user]; ?>
                    <div style="font-size:12px;color:<?= $bk_color ?>;font-weight:600;text-align:center;padding:6px 0 10px;">
                        <?= $bk_ico ?> <?= $bk_msg ?>
                    </div>
                    <?php if ($kamar_tersedia > 0): ?>
                        <a href="<?= BASE_URL ?>/pages/booking.php?id=<?= $id_kos ?>"
                           class="btn-kosta btn"
                           style="width:100%;display:block;text-align:center;font-size:13px;padding:10px;">
                            🔄 Pesan Lagi
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                </div><!-- /tombol pesan -->
            </div><!-- /price-card -->

            <!-- Kartu Kontak Pemilik -->
            <div class="contact-card">
                <h3 class="contact-title">Hubungi Pemilik</h3>

                <div class="owner-row">
                    <div class="owner-avatar"><?= strtoupper(substr($kos['nama_pemilik'], 0, 1)) ?></div>
                    <div>
                        <div class="owner-name"><?= htmlspecialchars($kos['nama_pemilik']) ?></div>
                        <div class="owner-role">Pemilik Kos</div>
                    </div>
                </div>

                <!-- Tanya Pemilik via WhatsApp (Fonnte) -->
                <?php if (!empty($kos['hp_pemilik'])): ?>
                    <button type="button" class="contact-btn whatsapp" id="btn-tanya-pemilik"
                            onclick="bukaModalTanya()">
                        <span>💬</span> Tanya Pemilik via WA
                    </button>
                <?php else: ?>
                    <div style="font-size:12px;color:var(--color-text-muted);text-align:center;padding:8px 0;">
                        ℹ️ Pemilik belum mendaftarkan nomor WhatsApp.
                    </div>
                <?php endif; ?>


                <!-- Favorit -->
                <?php if (sudah_login()): ?>
                    <form action="<?= BASE_URL ?>/pages/favorit/toggle.php" method="POST" style="margin-top:10px;">
                        <input type="hidden" name="kos_id"  value="<?= $id_kos ?>">
                        <input type="hidden" name="kembali" value="<?= $url_sekarang ?>">
                        <button type="submit" class="contact-btn favorit <?= $sudah_favorit ? 'aktif' : '' ?>">
                            <?= $sudah_favorit ? '❤️ Tersimpan di Favorit' : '🤍 Simpan ke Favorit' ?>
                        </button>
                    </form>
                <?php endif; ?>

                <p class="contact-note">
                    💡 Hubungi pemilik untuk survei langsung sebelum memutuskan.
                </p>
            </div>



            <a href="javascript:history.back()"
               style="display:block;text-align:center;font-size:13px;font-weight:600;color:var(--color-text-muted);margin-top:16px;">
                ← Kembali ke hasil pencarian
            </a>

        </div><!-- /contact-sticky-wrapper -->
    </div><!-- /col-lg-4 -->

</div><!-- /row -->

<?php mysqli_close($koneksi); ?>
</div><!-- /container -->
</section>


<!-- Floating toolbar perbandingan (muncul jika ada kos di sesi bandingkan) -->
<?php if (!empty($kos_dibandingkan)): ?>
<div class="compare-toolbar <?= count($kos_dibandingkan) > 0 ? 'tampil' : '' ?>" id="compare-toolbar">
    <div class="container compare-toolbar-inner">
        <div>
            <div class="compare-toolbar-label">⚖️ Daftar Perbandingan</div>
            <div class="compare-slots" id="compare-slots">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="compare-slot <?= isset($kos_dibandingkan[$i]) ? 'filled' : '' ?>">
                        <?php if (isset($kos_dibandingkan[$i])): ?>
                            <span style="font-size:12px;">Kos #<?= $kos_dibandingkan[$i] ?></span>
                            <form action="<?= BASE_URL ?>/pages/bandingkan/hapus.php" method="POST" style="display:inline;">
                                <input type="hidden" name="kos_id" value="<?= $kos_dibandingkan[$i] ?>">
                                <input type="hidden" name="kembali" value="<?= $url_sekarang ?>">
                                <button type="submit" class="compare-slot-remove">✕</button>
                            </form>
                        <?php else: ?>
                            + Tambah kos
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="compare-toolbar-actions">
            <a href="<?= BASE_URL ?>/pages/bandingkan/index.php" class="btn-compare-go">
                ⚖️ Bandingkan Sekarang
            </a>
            <form action="<?= BASE_URL ?>/pages/bandingkan/hapus.php" method="POST" style="display:inline;">
                <input type="hidden" name="reset" value="1">
                <button type="submit" class="btn-compare-reset">Reset</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<?php require_once __DIR__ . '/../components/footer.php'; ?>

<style>
/* Galeri slideshow */
.galeri-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,.45);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 5;
    transition: background .2s;
}
.galeri-btn:hover { background: rgba(0,0,0,.7); }
.galeri-prev { left: 10px; }
.galeri-next { right: 10px; }

.galeri-counter {
    position: absolute;
    bottom: 44px;
    right: 12px;
    background: rgba(0,0,0,.5);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    z-index: 5;
}

.galeri-dots {
    position: absolute;
    bottom: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 6px;
    z-index: 5;
}
.galeri-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,.5);
    cursor: pointer;
    transition: background .2s, transform .2s;
}
.galeri-dot.aktif {
    background: #fff;
    transform: scale(1.3);
}
</style>

<script>
/* Galeri slideshow */
(function() {
    var slides  = document.querySelectorAll('.galeri-slide');
    var dots    = document.querySelectorAll('.galeri-dot');
    var counter = document.getElementById('galeri-counter');
    var current = 0;
    var total   = slides.length;

    if (total <= 1) return; // Satu foto — tidak perlu navigasi

    function tampilSlide(idx) {
        slides[current].style.display = 'none';
        dots[current].classList.remove('aktif');

        current = (idx + total) % total;

        slides[current].style.display = 'block';
        dots[current].classList.add('aktif');
        if (counter) counter.textContent = (current + 1) + ' / ' + total;
    }

    // Dipanggil dari tombol prev/next (onclick di HTML)
    window.galeriNav  = function(dir)  { tampilSlide(current + dir); };
    window.galeriJump = function(idx)  { tampilSlide(idx); };

    // Keyboard navigasi (← →)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft')  galeriNav(-1);
        if (e.key === 'ArrowRight') galeriNav(1);
    });

    // Swipe touch (mobile)
    var startX = 0;
    var wrap = document.getElementById('galeri-wrap');
    if (wrap) {
        wrap.addEventListener('touchstart', function(e) { startX = e.touches[0].clientX; }, {passive:true});
        wrap.addEventListener('touchend',   function(e) {
            var diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) galeriNav(diff > 0 ? 1 : -1);
        });
    }
})();

/* Fungsi share link */
function shareKos() {
    var btn = document.getElementById('btn-share');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(window.location.href).then(function() {
            var orig = btn.textContent;
            btn.textContent = '✅ Link disalin!';
            setTimeout(function() { btn.textContent = orig; }, 2000);
        });
    } else {
        prompt('Salin link ini:', window.location.href);
    }
}
</script>

<!-- Modal tanya pemilik via WhatsApp (Fonnte) -->
<?php if (!empty($kos['hp_pemilik'])): ?>
<div id="modal-tanya" role="dialog" aria-modal="true" aria-label="Tanya Pemilik"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:var(--color-surface,#1e1e2e);border:1px solid var(--color-border,#2e2e3e);border-radius:16px;width:100%;max-width:480px;box-shadow:0 24px 60px rgba(0,0,0,.45);overflow:hidden;">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--color-border,#2e2e3e);">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#25d366,#128c5e);display:flex;align-items:center;justify-content:center;font-size:18px;">💬</div>
                <div>
                    <div style="font-size:15px;font-weight:800;color:var(--color-text,#f0f0f0);">Tanya Pemilik Kos</div>
                    <div style="font-size:12px;color:var(--color-text-muted,#9999aa);"><?= htmlspecialchars($kos['nama_pemilik']) ?> · via WhatsApp</div>
                </div>
            </div>
            <button onclick="tutupModalTanya()" title="Tutup"
                    style="background:none;border:none;color:var(--color-text-muted,#9999aa);font-size:22px;cursor:pointer;line-height:1;padding:4px;">✕</button>
        </div>

        <!-- Body form -->
        <div style="padding:20px;">

            <!-- Template pesan cepat -->
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;color:var(--color-text-muted,#9999aa);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">
                    💡 Pilih template atau tulis sendiri
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:7px;">
                    <?php
                    $nama_kos_tpl = htmlspecialchars($kos['nama_kos'], ENT_QUOTES);
                    $templates = [
                        ['🏠', 'Ketersediaan Kamar',
                         "Halo, saya tertarik dengan kos *{$nama_kos_tpl}*. Apakah masih ada kamar yang tersedia? Jika ada, kapan bisa saya mulai huni?"],
                        ['💰', 'Harga & Biaya',
                         "Halo, selain harga sewa bulanan, apakah ada biaya tambahan lain seperti listrik, air, atau biaya kebersihan di *{$nama_kos_tpl}*?"],
                        ['🔑', 'Jadwal Survei',
                         "Halo, saya ingin melakukan survei langsung ke *{$nama_kos_tpl}*. Kapan waktu yang bisa kita atur untuk kunjungan?"],
                        ['📋', 'Syarat & Ketentuan',
                         "Halo, apa saja syarat dan ketentuan untuk menyewa kamar di *{$nama_kos_tpl}*? Apakah ada deposit atau kontrak minimum?"],
                        ['🚗', 'Fasilitas & Parkir',
                         "Halo, apakah di *{$nama_kos_tpl}* tersedia parkir kendaraan (motor/mobil)? Dan bagaimana dengan fasilitas lainnya?"],
                    ];
                    foreach ($templates as [$ikon, $judul, $isi]):
                        $isi_escaped = htmlspecialchars($isi, ENT_QUOTES);
                    ?>
                    <button type="button"
                            class="tpl-chip"
                            onclick="pakaiTemplate(this)"
                            data-tpl="<?= $isi_escaped ?>"
                            title="<?= htmlspecialchars($isi, ENT_QUOTES) ?>">
                        <?= $ikon ?> <?= $judul ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <label style="display:block;font-size:12px;font-weight:700;color:var(--color-text-muted,#9999aa);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">✏️ Pesan *</label>
            <textarea id="tanya-pesan"
                      placeholder="Pilih template di atas atau ketik pesanmu sendiri..."
                      rows="5"
                      maxlength="500"
                      style="width:100%;box-sizing:border-box;background:var(--color-bg,#13131f);border:1.5px solid var(--color-border,#2e2e3e);border-radius:10px;padding:12px 14px;font-size:14px;color:var(--color-text,#f0f0f0);resize:vertical;outline:none;transition:border-color .2s;font-family:inherit;"
                      onfocus="this.style.borderColor='#25d366'"
                      onblur="this.style.borderColor=''"
                      oninput="updateCharCount()"></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                <span id="tanya-char-count" style="font-size:11px;color:var(--color-text-muted,#9999aa);">0 / 500</span>
                <span style="font-size:11px;color:var(--color-text-muted,#9999aa);">📲 Dikirim ke WA pemilik</span>
            </div>

            <!-- Alert area -->
            <div id="tanya-alert" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;"></div>

            <!-- Action buttons -->
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button onclick="tutupModalTanya()"
                        style="flex:1;padding:11px;border-radius:10px;border:1.5px solid var(--color-border,#2e2e3e);background:none;color:var(--color-text-muted,#9999aa);font-size:14px;font-weight:600;cursor:pointer;transition:background .2s;"
                        onmouseover="this.style.background='rgba(255,255,255,.05)'"
                        onmouseout="this.style.background='none'">Batal</button>
                <button id="btn-kirim-tanya"
                        onclick="kirimPesanFonnte()"
                        style="flex:2;padding:11px;border-radius:10px;border:none;background:linear-gradient(135deg,#25d366,#128c5e);color:#fff;font-size:14px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .2s;">
                    <span id="kirim-icon">📨</span>
                    <span id="kirim-label">Kirim Pesan</span>
                </button>
            </div>

            <?php if (!sudah_login()): ?>
            <div style="margin-top:12px;text-align:center;font-size:12px;color:var(--color-text-muted,#9999aa);">
                ⚠️ Kamu harus <a href="<?= BASE_URL ?>/pages/login.php" style="color:#25d366;font-weight:700;">login</a> untuk mengirim pesan.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Template chip buttons */
.tpl-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    border: 1.5px solid var(--color-border, #2e2e3e);
    background: var(--color-bg, #13131f);
    color: var(--color-text-muted, #9999aa);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all .18s ease;
    white-space: nowrap;
    font-family: inherit;
}
.tpl-chip:hover {
    border-color: #25d366;
    color: #25d366;
    background: rgba(37, 211, 102, .08);
}
.tpl-chip.aktif {
    border-color: #25d366;
    background: rgba(37, 211, 102, .15);
    color: #25d366;
}
</style>

<script>
(function() {
    var KOS_ID     = <?= $id_kos ?>;
    var HANDLER    = '<?= BASE_URL ?>/pages/kirim_wa_fonnte.php';
    var SUDAH_LOGIN = <?= sudah_login() ? 'true' : 'false' ?>;

    // Template chip: isi textarea dan tandai chip aktif
    window.pakaiTemplate = function(btn) {
        var teks = btn.getAttribute('data-tpl');
        var area = document.getElementById('tanya-pesan');
        area.value = teks;
        updateCharCount();
        area.focus();
        // Highlight chip yang dipilih
        document.querySelectorAll('.tpl-chip').forEach(function(c) {
            c.classList.remove('aktif');
        });
        btn.classList.add('aktif');
        // Scroll ke textarea
        area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    window.bukaModalTanya = function() {
        var modal = document.getElementById('modal-tanya');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        document.getElementById('tanya-pesan').focus();
        resetFormTanya();
    };

    window.tutupModalTanya = function() {
        document.getElementById('modal-tanya').style.display = 'none';
        document.body.style.overflow = '';
    };

    // Tutup saat klik backdrop
    document.getElementById('modal-tanya').addEventListener('click', function(e) {
        if (e.target === this) tutupModalTanya();
    });

    // Tutup dengan Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') tutupModalTanya();
    });

    window.updateCharCount = function() {
        var len = document.getElementById('tanya-pesan').value.length;
        var el  = document.getElementById('tanya-char-count');
        el.textContent = len + ' / 500';
        el.style.color = len > 450 ? '#ef4444' : '';
    };

    function tampilAlert(tipe, pesan) {
        var el = document.getElementById('tanya-alert');
        el.style.display = 'block';
        if (tipe === 'sukses') {
            el.style.background  = 'rgba(37,211,102,.12)';
            el.style.border      = '1px solid rgba(37,211,102,.35)';
            el.style.color       = '#25d366';
        } else {
            el.style.background  = 'rgba(239,68,68,.12)';
            el.style.border      = '1px solid rgba(239,68,68,.35)';
            el.style.color       = '#ef4444';
        }
        el.textContent = pesan;
    }

    function resetFormTanya() {
        document.getElementById('tanya-pesan').value = '';
        document.getElementById('tanya-alert').style.display = 'none';
        document.getElementById('tanya-char-count').textContent = '0 / 500';
        setBtnKirim(false);
    }

    function setBtnKirim(loading) {
        var btn   = document.getElementById('btn-kirim-tanya');
        var icon  = document.getElementById('kirim-icon');
        var label = document.getElementById('kirim-label');
        btn.disabled       = loading;
        btn.style.opacity  = loading ? '.65' : '1';
        icon.textContent   = loading ? '⏳' : '📨';
        label.textContent  = loading ? 'Mengirim...' : 'Kirim Pesan';
    }

    window.kirimPesanFonnte = function() {
        if (!SUDAH_LOGIN) {
            tampilAlert('error', '⚠️ Kamu harus login terlebih dahulu.');
            return;
        }

        var pesan = document.getElementById('tanya-pesan').value.trim();
        if (pesan.length < 5) {
            tampilAlert('error', '⚠️ Pesan terlalu pendek. Minimal 5 karakter.');
            return;
        }

        setBtnKirim(true);
        document.getElementById('tanya-alert').style.display = 'none';

        var formData = new FormData();
        formData.append('kos_id', KOS_ID);
        formData.append('pesan',  pesan);

        fetch(HANDLER, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                setBtnKirim(false);
                if (data.success) {
                    tampilAlert('sukses', '✅ ' + data.message);
                    document.getElementById('tanya-pesan').value = '';
                    document.getElementById('tanya-char-count').textContent = '0 / 500';
                    // Tutup modal otomatis setelah 2.5 detik
                    setTimeout(tutupModalTanya, 2500);
                } else {
                    tampilAlert('error', '❌ ' + data.message);
                }
            })
            .catch(function() {
                setBtnKirim(false);
                tampilAlert('error', '❌ Terjadi kesalahan jaringan. Silakan coba lagi.');
            });
    };
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../components/scripts.php'; ?>


