<?php
/**
 * ====================================================
 * FILE: index.php  (FASE 3 — Filter Lengkap & Favorit)
 * FUNGSI: Landing page dengan pencarian multi-filter,
 *         grid kos dari database, tombol favorit.
 * ====================================================
 */
require_once 'config/koneksi.php';
require_once 'config/session.php';

// ============================================================
// LANGKAH 1: Ambil semua parameter filter dari URL ($_GET)
// Menggunakan GET agar URL bisa di-bookmark dan di-bagikan
// Contoh URL: ?q=margonda&tipe=putri&harga_max=1500000&wifi=1
// ============================================================
$keyword    = trim($_GET['q']         ?? '');
$tipe       = trim($_GET['tipe']      ?? '');
$kota       = trim($_GET['kota']      ?? '');
$harga_min  = (int)($_GET['harga_min'] ?? 0);
$harga_max  = (int)($_GET['harga_max'] ?? 0);
$order      = trim($_GET['order']     ?? 'terbaru');

// Filter fasilitas: setiap fasilitas adalah checkbox terpisah
// isset($_GET['wifi']) = checkbox dicentang
$filter_wifi    = isset($_GET['wifi']);
$filter_ac      = isset($_GET['ac']);
$filter_km_dlm  = isset($_GET['kamar_mandi_dalam']);
$filter_parkir  = isset($_GET['parkir']);

$ada_filter = !empty($keyword) || !empty($tipe) || !empty($kota)
           || $harga_min > 0 || $harga_max > 0
           || $filter_wifi || $filter_ac || $filter_km_dlm || $filter_parkir;

// ============================================================
// LANGKAH 2: Bangun Query SQL secara Dinamis
// ============================================================
// Mulai dengan query dasar — ambil kos + info pemilik (JOIN)
$sql = "SELECT k.*, u.nama AS nama_pemilik
        FROM kos k
        LEFT JOIN users u ON k.pemilik_id = u.id
        WHERE k.status = 'aktif'";

// Array kondisi WHERE tambahan
// Dikumpulkan dulu, baru digabung dengan implode(" AND ", ...)
// Ini cara yang lebih rapi daripada string concatenation
$kondisi = [];

// --- Kondisi keyword: cari di nama_kos, alamat, dan kota ---
// LIKE '%kata%' = cari substring di manapun dalam nilai kolom
if (!empty($keyword)) {
    $kw = mysqli_real_escape_string($koneksi, $keyword);
    // Gunakan tanda kurung agar OR tidak merusak logika AND lainnya
    $kondisi[] = "(k.nama_kos LIKE '%$kw%'
                   OR k.alamat   LIKE '%$kw%'
                   OR k.kota     LIKE '%$kw%'
                   OR k.kecamatan LIKE '%$kw%')";
}

// --- Kondisi tipe: harus cocok persis (bukan LIKE) ---
// in_array() untuk validasi agar tidak bisa dimanipulasi
if (!empty($tipe) && in_array($tipe, ['putra', 'putri', 'campur'])) {
    $tipe_aman = mysqli_real_escape_string($koneksi, $tipe);
    $kondisi[] = "k.tipe = '$tipe_aman'";
}

// --- Kondisi kota ---
if (!empty($kota)) {
    $kota_aman = mysqli_real_escape_string($koneksi, $kota);
    $kondisi[] = "k.kota LIKE '%$kota_aman%'";
}

// --- Kondisi rentang harga ---
// >= harga_min: harga harus lebih besar atau sama dengan minimum
if ($harga_min > 0) {
    $kondisi[] = "k.harga_per_bulan >= $harga_min";
}
// <= harga_max: harga harus lebih kecil atau sama dengan maksimum
if ($harga_max > 0) {
    $kondisi[] = "k.harga_per_bulan <= $harga_max";
}

// --- Kondisi fasilitas: setiap yang dicentang harus = 1 ---
// Kolom fasilitas di database: 1 = ada, 0 = tidak ada
if ($filter_wifi)   $kondisi[] = "k.wifi = 1";
if ($filter_ac)     $kondisi[] = "k.ac = 1";
if ($filter_km_dlm) $kondisi[] = "k.kamar_mandi_dalam = 1";
if ($filter_parkir) $kondisi[] = "k.parkir = 1";

// Gabungkan semua kondisi jika ada
if (!empty($kondisi)) {
    $sql .= " AND " . implode(" AND ", $kondisi);
}

// --- Pengurutan hasil ---
$urutan_sql = match($order) {
    'termurah'  => 'k.harga_per_bulan ASC',
    'termahal'  => 'k.harga_per_bulan DESC',
    'az'        => 'k.nama_kos ASC',
    default     => 'k.created_at DESC', // 'terbaru'
};
$sql .= " ORDER BY $urutan_sql LIMIT 9";

$result     = mysqli_query($koneksi, $sql);
$jumlah_kos = mysqli_num_rows($result);

// ============================================================
// LANGKAH 3: Ambil ID kos yang sudah difavoritkan user
//            (hanya jika user sudah login)
// ============================================================
// Kita simpan dalam array agar pengecekan O(1) — sangat cepat
$set_favorit = []; // format: [kos_id => true, ...]

if (sudah_login()) {
    $user_id_login = $_SESSION['user_id'];
    $q_fav = mysqli_prepare($koneksi,
        "SELECT kos_id FROM favorites WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($q_fav, 'i', $user_id_login);
    mysqli_stmt_execute($q_fav);
    $r_fav = mysqli_stmt_get_result($q_fav);
    while ($row_fav = mysqli_fetch_assoc($r_fav)) {
        $set_favorit[$row_fav['kos_id']] = true;
    }
}

// ============================================================
// LANGKAH 4: Ambil daftar kota untuk dropdown filter
// ============================================================
$result_kota = mysqli_query($koneksi,
    "SELECT DISTINCT kota FROM kos WHERE status='aktif' ORDER BY kota ASC"
);
$daftar_kota = [];
while ($r = mysqli_fetch_assoc($result_kota)) {
    $daftar_kota[] = $r['kota'];
}

// ============================================================
// LANGKAH 5: Siapkan variabel head & render
// ============================================================
$judul_halaman = "Beranda";
$css_tambahan  = "home.css";

require_once 'components/head.php';
require_once 'components/navbar.php';
?>

<!-- ===== HERO SECTION ===== -->
<section class="hero-section">
    <div class="container" style="position:relative; z-index:2;">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="hero-eyebrow">🏠 Platform Kos Indonesia</div>
                <h1 class="hero-title">
                    Temukan Kos<br>
                    <span class="highlight">Impianmu</span> Sekarang
                </h1>
                <p class="hero-subtitle">
                    Banyak pilihan kos putra, putri, dan campur tersedia
                    di seluruh Indonesia. Mudah, cepat, terpercaya.
                </p>
            </div>
        </div>
    </div>
</section>


<!-- ===== SEARCH BOX MENGAMBANG ===== -->
<div class="search-floating-wrapper">
    <div class="container">
        <form action="<?= BASE_URL ?>/index.php" method="GET" id="form-filter">
            <div class="search-box">

                <p class="search-title">🔍 Cari Kos Favoritmu</p>

                <!-- Baris 1: Input utama + tombol -->
                <div class="search-input-group">
                    <input type="text" name="q" id="input-cari"
                           class="form-control"
                           placeholder="Nama kos, alamat, kampus, atau kota..."
                           value="<?= htmlspecialchars($keyword) ?>"
                           autocomplete="off">

                    <!-- Dropdown Kota -->
                    <select name="kota" class="form-control search-select" style="max-width:180px;">
                        <option value="">Semua Kota</option>
                        <?php foreach ($daftar_kota as $k): ?>
                            <option value="<?= htmlspecialchars($k) ?>"
                                <?= ($kota === $k) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-kosta btn" style="white-space:nowrap; flex-shrink:0;">
                        Cari Kos
                    </button>
                </div>

                <!-- Baris 2: Filter lanjutan (collapsible di mobile) -->
                <div class="filter-advanced" id="filter-advanced">

                    <!-- Filter Tipe -->
                    <div class="filter-group">
                        <span class="filter-label">Tipe:</span>
                        <button type="button" class="filter-chip <?= empty($tipe) ? 'active' : '' ?>"
                                onclick="setFilterVal('tipe', '')">Semua</button>
                        <button type="button" class="filter-chip <?= $tipe === 'putra'  ? 'active' : '' ?>"
                                onclick="setFilterVal('tipe', 'putra')">🚹 Putra</button>
                        <button type="button" class="filter-chip <?= $tipe === 'putri'  ? 'active' : '' ?>"
                                onclick="setFilterVal('tipe', 'putri')">🚺 Putri</button>
                        <button type="button" class="filter-chip <?= $tipe === 'campur' ? 'active' : '' ?>"
                                onclick="setFilterVal('tipe', 'campur')">👥 Campur</button>
                        <input type="hidden" name="tipe" id="filter-tipe" value="<?= htmlspecialchars($tipe) ?>">
                    </div>

                    <!-- Filter Rentang Harga -->
                    <div class="filter-group">
                        <span class="filter-label">Harga:</span>
                        <select name="harga_max" class="filter-select" onchange="this.form.submit()">
                            <option value="">Semua Harga</option>
                            <option value="500000"  <?= $harga_max == 500000  ? 'selected':'' ?>>< Rp 500rb</option>
                            <option value="1000000" <?= $harga_max == 1000000 ? 'selected':'' ?>>< Rp 1 Juta</option>
                            <option value="1500000" <?= $harga_max == 1500000 ? 'selected':'' ?>>< Rp 1,5 Juta</option>
                            <option value="2000000" <?= $harga_max == 2000000 ? 'selected':'' ?>>< Rp 2 Juta</option>
                            <option value="3000000" <?= $harga_max == 3000000 ? 'selected':'' ?>>< Rp 3 Juta</option>
                        </select>
                    </div>

                    <!-- Filter Fasilitas -->
                    <div class="filter-group">
                        <span class="filter-label">Fasilitas:</span>
                        <label class="filter-check <?= $filter_wifi   ? 'active' : '' ?>">
                            <input type="checkbox" name="wifi" value="1" <?= $filter_wifi   ? 'checked' : '' ?> onchange="this.form.submit()">
                            📶 WiFi
                        </label>
                        <label class="filter-check <?= $filter_ac     ? 'active' : '' ?>">
                            <input type="checkbox" name="ac" value="1" <?= $filter_ac     ? 'checked' : '' ?> onchange="this.form.submit()">
                            ❄️ AC
                        </label>
                        <label class="filter-check <?= $filter_km_dlm ? 'active' : '' ?>">
                            <input type="checkbox" name="kamar_mandi_dalam" value="1" <?= $filter_km_dlm ? 'checked' : '' ?> onchange="this.form.submit()">
                            🚿 KM Dalam
                        </label>
                        <label class="filter-check <?= $filter_parkir ? 'active' : '' ?>">
                            <input type="checkbox" name="parkir" value="1" <?= $filter_parkir ? 'checked' : '' ?> onchange="this.form.submit()">
                            🅿️ Parkir
                        </label>
                    </div>

                    <!-- Urutan + Reset -->
                    <div class="filter-group" style="margin-left:auto;">
                        <select name="order" class="filter-select" onchange="this.form.submit()">
                            <option value="terbaru"  <?= $order === 'terbaru'  ? 'selected' : '' ?>>Terbaru</option>
                            <option value="termurah" <?= $order === 'termurah' ? 'selected' : '' ?>>Termurah</option>
                            <option value="termahal" <?= $order === 'termahal' ? 'selected' : '' ?>>Termahal</option>
                            <option value="az"       <?= $order === 'az'       ? 'selected' : '' ?>>A–Z</option>
                        </select>

                        <?php if ($ada_filter): ?>
                            <a href="<?= BASE_URL ?>/index.php" class="filter-reset-btn">
                                ✕ Reset
                            </a>
                        <?php endif; ?>
                    </div>

                </div><!-- /filter-advanced -->

            </div><!-- /search-box -->
        </form>
    </div>
</div>


<!-- ===== SECTION LISTING KOS ===== -->
<section class="section-listing">
    <div class="container">

        <!-- Header section -->
        <div class="section-header">
            <div>
                <h2 class="section-title">
                    <?= $ada_filter ? 'Hasil Pencarian' : 'Kos Terbaru & Populer' ?>
                </h2>
                <p class="section-subtitle">
                    <?php if ($jumlah_kos > 0): ?>
                        <?= $jumlah_kos ?> kos ditemukan<?= $ada_filter ? ' sesuai filtermu' : '' ?>
                    <?php else: ?>
                        Tidak ada kos yang cocok dengan kriteria ini
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/pages/cari.php" class="lihat-semua">
                Lihat Semua →
            </a>
        </div>

        <!-- ========== GRID KOS ========== -->
        <?php if ($jumlah_kos > 0): ?>
            <div class="row g-4">

            <?php while ($kos = mysqli_fetch_assoc($result)):
                $tipe_class     = strtolower($kos['tipe']);
                $harga_format   = 'Rp ' . number_format($kos['harga_per_bulan'], 0, ',', '.');
                $kamar_sisa     = $kos['jumlah_kamar'] - $kos['kamar_terisi'];
                $hari_lalu      = (time() - strtotime($kos['created_at'])) / 86400;
                $sudah_favorit  = isset($set_favorit[$kos['id']]); // Cek dari array yang sudah disiapkan di atas
            ?>
            <div class="col-lg-4 col-md-6">
                <article class="kos-card">

                    <!-- Gambar -->
                    <div class="kos-card-img-wrapper">
                        <?php if (!empty($kos['foto_utama'])): ?>
                            <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($kos['foto_utama']) ?>"
                                 alt="<?= htmlspecialchars($kos['nama_kos']) ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="kos-img-placeholder">
                                <div class="placeholder-icon">🏠</div>
                                <div class="placeholder-text">Foto belum tersedia</div>
                            </div>
                        <?php endif; ?>

                        <!-- Ribbon "Baru" -->
                        <?php if ($hari_lalu < 7): ?>
                            <span class="kos-ribbon">Baru</span>
                        <?php endif; ?>

                        <!-- ===== TOMBOL FAVORIT (WISHLIST HEART) ===== -->
                        <?php if (sudah_login()): ?>
                            <!--
                                Jika sudah login: kirim POST ke toggle.php
                                Form ini menggunakan method POST (bukan GET)
                                karena operasi ini mengubah data di database
                            -->
                            <form action="<?= BASE_URL ?>/pages/favorit/toggle.php"
                                  method="POST"
                                  class="favorit-form">
                                <!-- ID kos yang akan di-toggle -->
                                <input type="hidden" name="kos_id" value="<?= $kos['id'] ?>">
                                <!-- Redirect kembali ke halaman ini setelah toggle -->
                                <input type="hidden" name="kembali" value="<?= BASE_URL ?>/index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>">
                                <button type="submit"
                                        class="kos-wishlist-btn <?= $sudah_favorit ? 'aktif' : '' ?>"
                                        title="<?= $sudah_favorit ? 'Hapus dari favorit' : 'Simpan ke favorit' ?>">
                                    <!-- Merah jika sudah favorit, abu-abu jika belum -->
                                    <?= $sudah_favorit ? '❤️' : '🤍' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <!-- Belum login: arahkan ke halaman login -->
                            <a href="<?= BASE_URL ?>/pages/login.php?pesan=login_dulu"
                               class="kos-wishlist-btn"
                               title="Login untuk menyimpan favorit">
                                🤍
                            </a>
                        <?php endif; ?>
                        <!-- ===== END TOMBOL FAVORIT ===== -->

                    </div><!-- /kos-card-img-wrapper -->

                    <!-- Body kartu -->
                    <div class="kos-card-body">
                        <span class="badge-kos <?= $tipe_class ?>">
                            <?= ucfirst($kos['tipe']) ?>
                        </span>

                        <h3 class="kos-card-name mt-2">
                            <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos['id'] ?>"
                               style="color:inherit; text-decoration:none;">
                                <?= htmlspecialchars($kos['nama_kos']) ?>
                            </a>
                        </h3>

                        <p class="kos-card-location">
                            <span class="icon">📍</span>
                            <?= htmlspecialchars($kos['kota']) ?>
                            <?php if (!empty($kos['kecamatan'])): ?>
                                , <?= htmlspecialchars($kos['kecamatan']) ?>
                            <?php endif; ?>
                        </p>

                        <!-- Fasilitas ikon kecil -->
                        <div class="kos-facilities">
                            <?php if ($kos['wifi']): ?><span class="facility-item">📶 WiFi</span><?php endif; ?>
                            <?php if ($kos['ac']): ?><span class="facility-item">❄️ AC</span><?php endif; ?>
                            <?php if ($kos['kamar_mandi_dalam']): ?><span class="facility-item">🚿 KM Dalam</span><?php endif; ?>
                            <?php if ($kos['parkir']): ?><span class="facility-item">🅿️ Parkir</span><?php endif; ?>
                            <?php if ($kos['dapur']): ?><span class="facility-item">🍳 Dapur</span><?php endif; ?>
                        </div>

                        <!-- Harga & Tombol -->
                        <div class="kos-card-meta">
                            <div>
                                <div class="kos-card-price">
                                    <?= $harga_format ?><span>/ bln</span>
                                </div>
                                <?php if ($kamar_sisa > 0 && $kamar_sisa <= 3): ?>
                                    <p class="kamar-warning">⚠️ Sisa <?= $kamar_sisa ?> kamar!</p>
                                <?php elseif ($kamar_sisa === 0): ?>
                                    <p class="kamar-full">❌ Penuh</p>
                                <?php endif; ?>
                            </div>
                            <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos['id'] ?>"
                               class="btn-kosta btn"
                               style="font-size:12px; padding:7px 16px; white-space:nowrap;">
                                Lihat →
                            </a>
                        </div>

                    </div><!-- /kos-card-body -->
                </article>
            </div><!-- /col -->

            <?php endwhile; ?>
            </div><!-- /row -->

            <!-- Tombol lihat semua di bawah grid -->
            <div style="text-align:center; margin-top:40px;">
                <a href="<?= BASE_URL ?>/pages/cari.php" class="btn-kosta-outline btn">
                    Lihat Semua Kos →
                </a>
            </div>

        <?php else: ?>
            <!-- Empty state -->
            <div class="empty-state">
                <div class="empty-icon"><?= $ada_filter ? '🔍' : '🏚️' ?></div>
                <h5><?= $ada_filter ? 'Kos Tidak Ditemukan' : 'Belum Ada Data Kos' ?></h5>
                <p>
                    <?php if ($ada_filter): ?>
                        Coba ubah atau reset filter pencarianmu.
                    <?php else: ?>
                        Belum ada kos yang terdaftar. Coba tambahkan data kos melalui dashboard pemilik.
                    <?php endif; ?>
                </p>
                <?php if ($ada_filter): ?>
                    <a href="<?= BASE_URL ?>/index.php" class="btn-kosta btn mt-3">Reset Semua Filter</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php mysqli_close($koneksi); ?>

    </div><!-- /container -->
</section>

<?php require_once 'components/footer.php'; ?>

<script>
/**
 * Fungsi untuk set nilai input hidden tipe filter
 * dan otomatis submit form
 */
function setFilterVal(field, value) {
    var el = document.getElementById('filter-' + field);
    if (el) el.value = value;

    // Update visual active state
    var chips = document.querySelectorAll('[onclick*="setFilterVal(\'' + field + '\'"]');
    chips.forEach(function(c) { c.classList.remove('active'); });
    event.currentTarget.classList.add('active');

    // Submit form
    document.getElementById('form-filter').submit();
}
</script>

<?php require_once 'components/scripts.php'; ?>
