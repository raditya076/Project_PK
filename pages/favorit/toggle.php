<?php
/**
 * ====================================================
 * FILE: pages/favorit/toggle.php
 * FUNGSI: Toggle kos masuk/keluar dari daftar favorit.
 *
 * LOGIKA TOGGLE:
 * 1. Cek apakah kos sudah ada di tabel favorites
 * 2. Jika sudah ada  → HAPUS (un-favorite)
 * 3. Jika belum ada  → TAMBAH (favorite)
 *
 * Teknik: INSERT IGNORE + DELETE
 * Atau: cek dulu dengan SELECT, baru INSERT atau DELETE
 * ====================================================
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

// Hanya menerima POST (tolak akses langsung via browser/GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/index.php');
}

// Wajib login untuk menggunakan fitur favorit
wajib_login();

$user  = user_login();
$kos_id  = (int)($_POST['kos_id']  ?? 0);
$kembali = $_POST['kembali'] ?? BASE_URL . '/index.php';

// Validasi ID kos
if ($kos_id <= 0) {
    set_flash('error', 'ID kos tidak valid.');
    redirect($kembali);
}

// Pastikan kos ini benar-benar ada di database
$cek_kos = mysqli_prepare($koneksi, "SELECT id FROM kos WHERE id = ? AND status = 'aktif' LIMIT 1");
mysqli_stmt_bind_param($cek_kos, 'i', $kos_id);
mysqli_stmt_execute($cek_kos);
mysqli_stmt_store_result($cek_kos);

if (mysqli_stmt_num_rows($cek_kos) === 0) {
    set_flash('error', 'Kos tidak ditemukan.');
    redirect($kembali);
}

// ============================================================
// LOGIKA TOGGLE:
// Cek apakah sudah ada di tabel favorites
// ============================================================
$cek_fav = mysqli_prepare($koneksi,
    "SELECT id FROM favorites WHERE user_id = ? AND kos_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($cek_fav, 'ii', $user['id'], $kos_id);
mysqli_stmt_execute($cek_fav);
mysqli_stmt_store_result($cek_fav);

$sudah_favorit = mysqli_stmt_num_rows($cek_fav) > 0;

if ($sudah_favorit) {
    // ---- Sudah favorit → HAPUS (un-favorite) ----
    $stmt = mysqli_prepare($koneksi,
        "DELETE FROM favorites WHERE user_id = ? AND kos_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $user['id'], $kos_id);
    mysqli_stmt_execute($stmt);
    // Tidak perlu flash message untuk aksi ini (UX yang baik = silent undo)

} else {
    // ---- Belum favorit → TAMBAH ----
    // INSERT IGNORE: jika terjadi duplikasi (UNIQUE KEY), abaikan error-nya
    // Ini mencegah error jika user klik dua kali bersamaan (race condition)
    $stmt = mysqli_prepare($koneksi,
        "INSERT IGNORE INTO favorites (user_id, kos_id) VALUES (?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $user['id'], $kos_id);
    mysqli_stmt_execute($stmt);
    // Flash message hanya untuk saat menambah (memberi konfirmasi ke user)
    set_flash('sukses', 'Kos berhasil disimpan ke favorit! ❤️');
}

mysqli_close($koneksi);

// Redirect kembali ke halaman asal (bisa index, cari, atau detail)
redirect($kembali);
