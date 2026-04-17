<?php
/**
 * ====================================================
 * FILE: pages/detail.php  (FASE 4 — Peta + Compare + Pesan)
 * FUNGSI: Halaman detail kos lengkap dengan:
 *   - Peta Leaflet.js (OpenStreetMap, gratis, tanpa API Key)
 *   - Review & Rating
 *   - Tombol Bandingkan (mode perbandingan session)
 *   - Form Tanya Pemilik (pesan internal)
 *   - Tautan WhatsApp
 * ====================================================
 */
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

// ============================================================
// STATUS FAVORIT & PERBANDINGAN
// ============================================================
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



// ============================================================
// REVIEW & RATING
// ============================================================
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

// ============================================================
// STATUS BOOKING USER DI KOS INI
// Digunakan untuk menampilkan tombol yang tepat di sidebar:
//   - Belum booking     → tampilkan "Pesan Sekarang"
//   - Sudah booking     → tampilkan status + link riwayat
//   - Booking selesai   → sudah tidak bisa booking lagi
// ============================================================
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

// ============================================================
// KOS TERKAIT
// ============================================================
$stmt_terkait = mysqli_prepare($koneksi,
    "SELECT id, nama_kos, tipe, harga_per_bulan, kota, foto_utama
     FROM kos WHERE kota=? AND id!=? AND status='aktif' ORDER BY RAND() LIMIT 3"
);
mysqli_stmt_bind_param($stmt_terkait, 'si', $kos['kota'], $id_kos);
mysqli_stmt_execute($stmt_terkait);
$result_terkait = mysqli_stmt_get_result($stmt_terkait);

// ============================================================
// PERSIAPAN VARIABEL
// ============================================================
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

// ============================================================
// Leaflet CSS + extra_head support (slot di components/head.php)
// Leaflet adalah library JavaScript untuk peta interaktif.
// Ia gratis, open-source, dan TIDAK butuh API key apapun.
// ============================================================
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


<!-- ====== KONTEN DETAIL UTAMA ====== -->
<section class="detail-section-main">
<div class="container">
<div class="row g-4">

    <!-- ===== KOLOM KIRI ===== -->
    <div class="col-lg-8">

        <!-- FOTO UTAMA -->
        <div class="detail-photo-wrapper">
            <?php if (!empty($kos['foto_utama'])): ?>
                <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($kos['foto_utama']) ?>"
                     alt="Foto <?= htmlspecialchars($kos['nama_kos']) ?>">
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

                <!-- ===== TOMBOL BANDINGKAN ===== -->
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

            <!-- ===================================================
                 PETA LEAFLET.JS
                 ===================================================
                 Leaflet adalah library JavaScript untuk membuat
                 peta interaktif di browser.

                 Cara kerjanya:
                 1. Kita sediakan sebuah <div> kosong dengan ID
                 2. Leaflet akan "mengisi" div itu dengan peta
                 3. Peta diambil dari OpenStreetMap (gratis, tanpa API key)
                 4. Kita pasang marker (pin) di koordinat kos
                 =================================================== -->
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

        <!-- =====================================================
             SECTION REVIEW & RATING
             ===================================================== -->
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


    <!-- ===== KOLOM KANAN (sidebar) ===== -->
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
                    'dibayar'            => ['🔵','Menunggu verifikasi pemilik','#1d4ed8'],
                    'aktif'              => ['✅','Kamu sedang menghuni kos ini','#15803d'],
                    'ditolak'            => ['❌','Pembayaran ditolak, perlu ulang','#b91c1c'],
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

                <?php elseif (in_array($status_booking_user, ['menunggu_pembayaran','dibayar','aktif'])): ?>
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

                <!-- WhatsApp (jika ada nomor) -->
                <?php if (!empty($wa_link)): ?>
                    <a href="<?= $wa_link ?>" target="_blank" rel="noopener" class="contact-btn whatsapp">
                        <span>💬</span> Chat via WhatsApp
                    </a>
                <?php endif; ?>

                <!-- Email -->
                <?php if (!empty($kos['email_pemilik'])): ?>
                    <a href="mailto:<?= htmlspecialchars($kos['email_pemilik']) ?>?subject=Tanya Kos: <?= urlencode($kos['nama_kos']) ?>"
                       class="contact-btn email">
                        <span>✉️</span> Kirim Email
                    </a>
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

            <!-- ====================================================
                 FORM TANYA PEMILIK (Pesan Internal)
                 Ini adalah alternatif jika user tidak pakai WA.
                 Form ini mengirim POST ke pages/pesan/kirim.php
                 ==================================================== -->
            <div class="contact-card" style="margin-top:12px;">
                <h3 class="contact-title" style="font-size:14px;">💬 Tanya Pemilik Langsung</h3>

                <?php if (sudah_login()): $u = user_login(); ?>
                    <!-- User login: nama & email sudah terisi otomatis -->
                    <p style="font-size:12px;color:var(--color-text-muted);margin-bottom:14px;">
                        Kirim sebagai <strong><?= htmlspecialchars($u['nama']) ?></strong>
                    </p>
                    <form action="<?= BASE_URL ?>/pages/pesan/kirim.php" method="POST">
                        <input type="hidden" name="kos_id"       value="<?= $id_kos ?>">
                        <input type="hidden" name="kembali"      value="<?= $url_sekarang ?>">
                        <input type="hidden" name="nama_pengirim"  value="<?= htmlspecialchars($u['nama']) ?>">
                        <input type="hidden" name="email_pengirim" value="<?= htmlspecialchars($u['email']) ?>">
                        <div class="form-group-kosta" style="margin-bottom:10px;">
                            <input type="tel" name="no_hp_pengirim" class="form-input-kosta"
                                   placeholder="No. HP / WA (opsional)" style="font-size:13px;">
                        </div>
                        <div class="form-group-kosta" style="margin-bottom:10px;">
                            <textarea name="isi_pesan" class="form-textarea-kosta"
                                      style="min-height:90px;font-size:13px;"
                                      placeholder="Tulis pertanyaanmu di sini...&#10;Contoh: Apakah masih ada kamar? Berapa DP-nya?" required></textarea>
                        </div>
                        <button type="submit" class="contact-btn whatsapp" style="background:var(--color-accent);border-color:var(--color-accent);">
                            📩 Kirim Pesan
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Belum login: tampilkan form lengkap -->
                    <form action="<?= BASE_URL ?>/pages/pesan/kirim.php" method="POST">
                        <input type="hidden" name="kos_id"  value="<?= $id_kos ?>">
                        <input type="hidden" name="kembali" value="<?= $url_sekarang ?>">
                        <div class="form-group-kosta" style="margin-bottom:10px;">
                            <input type="text" name="nama_pengirim" class="form-input-kosta"
                                   placeholder="Nama kamu" required style="font-size:13px;">
                        </div>
                        <div class="form-group-kosta" style="margin-bottom:10px;">
                            <input type="email" name="email_pengirim" class="form-input-kosta"
                                   placeholder="Email kamu" required style="font-size:13px;">
                        </div>
                        <div class="form-group-kosta" style="margin-bottom:10px;">
                            <input type="tel" name="no_hp_pengirim" class="form-input-kosta"
                                   placeholder="No. HP / WA (opsional)" style="font-size:13px;">
                        </div>
                        <div class="form-group-kosta" style="margin-bottom:10px;">
                            <textarea name="isi_pesan" class="form-textarea-kosta"
                                      style="min-height:90px;font-size:13px;"
                                      placeholder="Pertanyaan untuk pemilik..." required></textarea>
                        </div>
                        <button type="submit" class="contact-btn whatsapp" style="background:var(--color-accent);border-color:var(--color-accent);">
                            📩 Kirim Pesan
                        </button>
                    </form>
                <?php endif; ?>
            </div><!-- /form pesan -->

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


<!-- ====================================================
     FLOATING TOOLBAR PERBANDINGAN
     Muncul dari bawah ketika ada kos di session bandingkan
     ==================================================== -->
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

<script>
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



<?php require_once __DIR__ . '/../components/scripts.php'; ?>
