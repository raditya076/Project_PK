<?php
/**
 * ====================================================
 * FILE: pages/booking.php
 * FUNGSI: Form booking kos oleh pencari / penyewa.
 *
 * ALUR:
 *   GET  → Tampilkan form (pilih durasi, tanggal masuk)
 *   POST → Simpan booking ke DB, redirect ke pembayaran
 *
 * HANYA user dengan role 'pencari' yang bisa booking.
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

// Wajib login sebagai pencari
wajib_login();
if (user_login()['role'] === 'pemilik') {
    set_flash('error', 'Pemilik kos tidak bisa melakukan booking. Login sebagai pencari.');
    redirect(BASE_URL . '/index.php');
}

$user   = user_login();
$kos_id = (int)($_GET['id'] ?? $_POST['kos_id'] ?? 0);
if ($kos_id <= 0) redirect(BASE_URL . '/pages/cari.php');

// Ambil data kos
$stmt = mysqli_prepare($koneksi,
    "SELECT k.*, u.nama AS nama_pemilik
     FROM kos k LEFT JOIN users u ON k.pemilik_id = u.id
     WHERE k.id = ? AND k.status = 'aktif' LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $kos_id);
mysqli_stmt_execute($stmt);
$kos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$kos) {
    set_flash('error', 'Kos tidak ditemukan atau sudah tidak aktif.');
    redirect(BASE_URL . '/pages/cari.php');
}

// Cek apakah masih ada kamar
$kamar_sisa = $kos['jumlah_kamar'] - $kos['kamar_terisi'];
if ($kamar_sisa <= 0) {
    set_flash('error', 'Maaf, semua kamar di kos ini sudah terisi.');
    redirect(BASE_URL . '/pages/detail.php?id=' . $kos_id);
}

// Cek apakah user sudah punya booking aktif di kos ini
$cek_aktif = mysqli_prepare($koneksi,
    "SELECT id FROM bookings
     WHERE kos_id = ? AND penyewa_id = ?
     AND status IN ('menunggu_pembayaran','dibayar','aktif')
     LIMIT 1"
);
mysqli_stmt_bind_param($cek_aktif, 'ii', $kos_id, $user['id']);
mysqli_stmt_execute($cek_aktif);
mysqli_stmt_store_result($cek_aktif);
if (mysqli_stmt_num_rows($cek_aktif) > 0) {
    set_flash('error', 'Kamu sudah memiliki booking aktif di kos ini.');
    redirect(BASE_URL . '/pages/riwayat.php');
}

$errors = [];
$input  = [
    'durasi_bulan'  => 1,
    'tanggal_masuk' => date('Y-m-d', strtotime('+3 days')),
    'nomor_kamar'   => '',
    'catatan'       => '',
];

// ============================================================
// PROSES POST: Simpan booking baru
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input['durasi_bulan']  = (int)($_POST['durasi_bulan']  ?? 1);
    $input['tanggal_masuk'] = trim($_POST['tanggal_masuk']  ?? '');
    $input['nomor_kamar']   = trim($_POST['nomor_kamar']    ?? '');
    $input['catatan']       = trim($_POST['catatan_penyewa'] ?? '');

    // Validasi
    if ($input['durasi_bulan'] < 1 || $input['durasi_bulan'] > 24) {
        $errors[] = 'Durasi sewa tidak valid (1–24 bulan).';
    }
    if (empty($input['tanggal_masuk'])) {
        $errors[] = 'Tanggal masuk wajib diisi.';
    } elseif (strtotime($input['tanggal_masuk']) < strtotime('today')) {
        $errors[] = 'Tanggal masuk tidak boleh di masa lalu.';
    }

    if (empty($errors)) {
        // Hitung tanggal keluar: tanggal_masuk + durasi_bulan
        // date_modify() menambahkan bulan ke objek DateTime
        $dt_masuk  = new DateTime($input['tanggal_masuk']);
        $dt_keluar = clone $dt_masuk;
        $dt_keluar->modify('+' . $input['durasi_bulan'] . ' months');

        $tanggal_keluar = $dt_keluar->format('Y-m-d');
        $total_harga    = $kos['harga_per_bulan'] * $input['durasi_bulan'];

        // Simpan ke database
        $stmt_insert = mysqli_prepare($koneksi,
            "INSERT INTO bookings
                (kos_id, penyewa_id, nomor_kamar, tanggal_masuk, durasi_bulan,
                 tanggal_keluar, harga_per_bulan, total_harga, catatan_penyewa, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu_pembayaran')"
        );
        mysqli_stmt_bind_param($stmt_insert, 'iissisiis',
            $kos['id'],
            $user['id'],
            $input['nomor_kamar'],
            $input['tanggal_masuk'],
            $input['durasi_bulan'],
            $tanggal_keluar,
            $kos['harga_per_bulan'],
            $total_harga,
            $input['catatan']
        );

        if (mysqli_stmt_execute($stmt_insert)) {
            $booking_id = mysqli_insert_id($koneksi);
            // Setelah booking berhasil, arahkan ke halaman pembayaran
            set_flash('sukses', 'Booking berhasil dibuat! Selesaikan pembayaran di bawah ini. 🎉');
            redirect(BASE_URL . '/pages/pembayaran.php?booking_id=' . $booking_id);
        } else {
            $errors[] = 'Gagal menyimpan booking. Silakan coba lagi.';
        }
    }
}

// Harga yang ditampilkan di form
$harga_format = 'Rp ' . number_format($kos['harga_per_bulan'], 0, ',', '.');

$extra_head    = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/transaction.css">';
$judul_halaman = "Booking: " . $kos['nama_kos'];
$css_tambahan  = "detail.css";

require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<div class="container" style="padding-top:12px;"><?= get_flash() ?></div>

<!-- Breadcrumb -->
<div class="breadcrumb-bar">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos_id ?>">Detail Kos</a></li>
                <li class="breadcrumb-item active">Booking</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container">
<div class="booking-layout">

    <!-- ===== KOLOM KIRI: Form Booking ===== -->
    <div>
        <div class="booking-form-card">
            <h1 class="booking-form-title">📋 Formulir Booking Kos</h1>
            <p class="booking-form-subtitle">
                Isi detail sewa kamu. Setelah booking, kamu perlu melakukan pembayaran
                untuk mengkonfirmasi tempat.
            </p>

            <!-- Error -->
            <?php if (!empty($errors)): ?>
                <div class="alert-kosta error" style="margin-bottom:20px;">
                    <ul style="margin:0;padding-left:20px;">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/pages/booking.php?id=<?= $kos_id ?>" id="form-booking">
                <input type="hidden" name="kos_id" value="<?= $kos_id ?>">

                <!-- NOMOR KAMAR (hanya jika kos pakai sistem nomor) -->
                <?php if (!empty($kos['ada_nomor_kamar'])): ?>
                <div class="form-group-kosta" style="margin-bottom:20px;">
                    <label for="nomor_kamar" class="form-label-kosta">
                        Nomor Kamar yang Diinginkan
                    </label>
                    <input type="text" id="nomor_kamar" name="nomor_kamar"
                           class="form-input-kosta" placeholder="Contoh: A1, 101"
                           value="<?= htmlspecialchars($input['nomor_kamar']) ?>">
                    <p style="font-size:12px;color:var(--color-text-muted);margin-top:4px;">
                        Hubungi pemilik terlebih dahulu untuk memastikan kamar tersedia.
                    </p>
                </div>
                <?php endif; ?>

                <!-- TANGGAL MASUK -->
                <div class="form-group-kosta" style="margin-bottom:20px;">
                    <label for="tanggal_masuk" class="form-label-kosta">
                        Tanggal Mulai Masuk *
                    </label>
                    <input type="date" id="tanggal_masuk" name="tanggal_masuk"
                           class="form-input-kosta" required
                           min="<?= date('Y-m-d') ?>"
                           value="<?= htmlspecialchars($input['tanggal_masuk']) ?>">
                </div>

                <!-- DURASI SEWA -->
                <div class="form-group-kosta" style="margin-bottom:20px;">
                    <label class="form-label-kosta">Durasi Sewa *</label>
                    <div class="durasi-grid">
                        <?php
                        $durasi_opts = [1, 2, 3, 6, 12, 24];
                        foreach ($durasi_opts as $d):
                        ?>
                        <div class="durasi-option">
                            <input type="radio" name="durasi_bulan" id="durasi_<?= $d ?>"
                                   value="<?= $d ?>" <?= $input['durasi_bulan'] == $d ? 'checked' : '' ?>
                                   onchange="hitungHarga(this.value)">
                            <label for="durasi_<?= $d ?>">
                                <span class="durasi-num"><?= $d ?></span>
                                <span class="durasi-txt">
                                    <?= $d === 1 ? 'bulan' : ($d < 12 ? 'bulan' : ($d === 12 ? 'tahun' : '2 tahun')) ?>
                                </span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- CATATAN -->
                <div class="form-group-kosta" style="margin-bottom:20px;">
                    <label for="catatan_penyewa" class="form-label-kosta">
                        Catatan untuk Pemilik (opsional)
                    </label>
                    <textarea id="catatan_penyewa" name="catatan_penyewa"
                              class="form-textarea-kosta" rows="3"
                              placeholder="Contoh: Saya akan datang siang hari, tolong siapkan kunci..."
                    ><?= htmlspecialchars($input['catatan']) ?></textarea>
                </div>

                <!-- RINGKASAN HARGA (dihitung JS) -->
                <div class="price-summary-box">
                    <div class="price-summary-row">
                        <span class="price-summary-label">Harga per bulan</span>
                        <span><?= $harga_format ?></span>
                    </div>
                    <div class="price-summary-row">
                        <span class="price-summary-label">Durasi sewa</span>
                        <span id="display-durasi">1 bulan</span>
                    </div>
                    <div class="price-summary-row">
                        <span class="price-summary-label">Estimasi keluar</span>
                        <span id="display-keluar">—</span>
                    </div>
                    <div class="price-summary-row total">
                        <span>Total Pembayaran</span>
                        <span id="display-total"><?= $harga_format ?></span>
                    </div>
                </div>

                <button type="submit" class="btn-kosta btn"
                        style="width:100%;margin-top:20px;padding:13px;font-size:15px;">
                    Lanjutkan ke Pembayaran →
                </button>
            </form>
        </div>
    </div>

    <!-- ===== KOLOM KANAN: Info Kos ===== -->
    <div>
        <div class="booking-kos-sidebar">
            <?php if (!empty($kos['foto_utama'])): ?>
                <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($kos['foto_utama']) ?>"
                     alt="<?= htmlspecialchars($kos['nama_kos']) ?>"
                     class="booking-kos-img">
            <?php else: ?>
                <div class="booking-kos-img-placeholder">🏠</div>
            <?php endif; ?>
            <div class="booking-kos-info">
                <div class="booking-kos-name"><?= htmlspecialchars($kos['nama_kos']) ?></div>
                <div class="booking-kos-loc">📍 <?= htmlspecialchars($kos['kota']) ?></div>
                <div style="margin-bottom:10px;">
                    <span class="badge-kos <?= $kos['tipe'] ?>"><?= ucfirst($kos['tipe']) ?></span>
                    <span style="font-size:12px;color:#15803d;font-weight:700;margin-left:8px;">
                        ✅ <?= $kamar_sisa ?> kamar tersedia
                    </span>
                </div>
                <div class="booking-kos-price-row">
                    <div>
                        <div class="booking-kos-price"><?= $harga_format ?></div>
                        <div class="booking-kos-period">per bulan</div>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos_id ?>"
                       style="font-size:12px; color:var(--color-accent); font-weight:600;">
                        Lihat detail →
                    </a>
                </div>
                <hr style="border-color:var(--color-border); margin:12px 0;">
                <div style="font-size:12px; color:var(--color-text-muted); line-height:1.7;">
                    <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                        <span>👤</span>
                        <span>Pemilik: <strong><?= htmlspecialchars($kos['nama_pemilik']) ?></strong></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span>ℹ️</span>
                        <span>Booking tidak mengurangi jumlah kamar sampai pemilik konfirmasi.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /booking-layout -->
<?php mysqli_close($koneksi); ?>
</div><!-- /container -->

<?php require_once __DIR__ . '/../components/footer.php'; ?>

<script>
// ============================================================
// HITUNG HARGA & TANGGAL KELUAR SECARA REAL-TIME
// Ini adalah JavaScript yang berjalan di browser (sisi client).
// Ia menghitung dan menampilkan estimasi SEBELUM form di-submit.
// ============================================================
var HARGA_PER_BULAN = <?= $kos['harga_per_bulan'] ?>;   // dari PHP ke JS

function hitungHarga(durasi) {
    // Konversi string ke angka
    durasi = parseInt(durasi);

    // Hitung total
    var total = HARGA_PER_BULAN * durasi;

    // Format angka ke Rupiah (Indonesian locale)
    var fmt = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('display-total').textContent = fmt;

    // Tampilkan durasi
    var label = durasi + ' bulan';
    if (durasi === 12) label = '1 tahun';
    if (durasi === 24) label = '2 tahun';
    document.getElementById('display-durasi').textContent = label;

    // Hitung tanggal keluar dari tanggal masuk
    var tglInput = document.getElementById('tanggal_masuk').value;
    if (tglInput) {
        // new Date() membuat objek tanggal dari string
        var tglMasuk  = new Date(tglInput);
        var tglKeluar = new Date(tglMasuk);

        // setMonth() menambah bulan ke tanggal
        tglKeluar.setMonth(tglKeluar.getMonth() + durasi);

        // Tampilkan dalam format DD/MM/YYYY
        document.getElementById('display-keluar').textContent =
            tglKeluar.toLocaleDateString('id-ID', {day:'2-digit', month:'long', year:'numeric'});
    }
}

// Hitung saat tanggal masuk berubah
document.getElementById('tanggal_masuk').addEventListener('change', function() {
    var durasi = document.querySelector('input[name="durasi_bulan"]:checked')?.value || 1;
    hitungHarga(durasi);
});

// Hitung saat halaman pertama dimuat
window.addEventListener('DOMContentLoaded', function() {
    hitungHarga(<?= (int)$input['durasi_bulan'] ?>);
});
</script>

<?php require_once __DIR__ . '/../components/scripts.php'; ?>
