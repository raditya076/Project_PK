<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

// Review hanya bisa dikirim oleh user yang login
wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/index.php');
}

$user    = user_login();
$kos_id  = (int)($_POST['kos_id']  ?? 0);
$kembali = $_POST['kembali'] ?? BASE_URL . '/index.php';
$rating  = (int)($_POST['rating']  ?? 0);
$judul   = trim($_POST['judul']    ?? '');
$isi     = trim($_POST['isi_ulasan'] ?? '');

// Validasi
if ($kos_id <= 0) {
    set_flash('error', 'Kos tidak valid.');
    redirect($kembali);
}
if ($rating < 1 || $rating > 5) {
    set_flash('error', 'Pilih rating bintang (1-5).');
    redirect($kembali);
}
if (strlen($isi) < 10) {
    set_flash('error', 'Ulasan minimal 10 karakter.');
    redirect($kembali);
}

// Cek apakah kos aktif
$cek = mysqli_prepare($koneksi, "SELECT id FROM kos WHERE id = ? AND status = 'aktif' LIMIT 1");
mysqli_stmt_bind_param($cek, 'i', $kos_id);
mysqli_stmt_execute($cek);
mysqli_stmt_store_result($cek);
if (mysqli_stmt_num_rows($cek) === 0) {
    set_flash('error', 'Kos tidak ditemukan.');
    redirect($kembali);
}

// GATE: Hanya penyewa dengan booking SELESAI yang bisa review
//
// Ini mencegah ulasan palsu dari orang yang tidak pernah
// tinggal di kos tersebut.
//
// Jika tabel bookings belum ada (sebelum eksekusi SQL),
// cek ini akan dilewati agar tidak merusak fungsionalitas.
$tabel_ada = mysqli_query($koneksi, "SHOW TABLES LIKE 'bookings'");
if (mysqli_num_rows($tabel_ada) > 0) {
    // Izinkan review jika booking berstatus 'aktif' ATAU 'selesai'.
    // 'aktif'   = sudah bayar dan sedang menghuni
    // 'selesai' = masa sewa sudah berakhir
    // Pemilik tidak selalu menandai selesai, jadi aktif sudah cukup.
    $cek_booking = mysqli_prepare($koneksi,
        "SELECT id FROM bookings
         WHERE kos_id = ? AND penyewa_id = ? AND status IN ('aktif', 'selesai')
         LIMIT 1"
    );
    mysqli_stmt_bind_param($cek_booking, 'ii', $kos_id, $user['id']);
    mysqli_stmt_execute($cek_booking);
    mysqli_stmt_store_result($cek_booking);

    if (mysqli_stmt_num_rows($cek_booking) === 0) {
        set_flash('error',
            'Kamu hanya bisa memberi ulasan setelah pembayaran dikonfirmasi (status Aktif atau Selesai). 📋'
        );
        redirect($kembali);
    }
}

// Cek apakah sudah pernah review
$cek_review = mysqli_prepare($koneksi,
    "SELECT id FROM reviews WHERE user_id = ? AND kos_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($cek_review, 'ii', $user['id'], $kos_id);
mysqli_stmt_execute($cek_review);
mysqli_stmt_store_result($cek_review);
if (mysqli_stmt_num_rows($cek_review) > 0) {
    set_flash('error', 'Kamu sudah pernah memberikan ulasan untuk kos ini.');
    redirect($kembali);
}

// Simpan review
// INSERT IGNORE: jika duplikat (race condition), abaikan saja
$stmt = mysqli_prepare($koneksi,
    "INSERT IGNORE INTO reviews (kos_id, user_id, rating, judul, isi_ulasan)
     VALUES (?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'iiiss',
    $kos_id, $user['id'], $rating, $judul, $isi
);

if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    set_flash('sukses', 'Ulasan kamu berhasil dikirim! Terima kasih ⭐');
} else {
    set_flash('error', 'Gagal menyimpan ulasan. Mungkin kamu sudah pernah mengulas kos ini.');
}

mysqli_close($koneksi);
redirect($kembali);
