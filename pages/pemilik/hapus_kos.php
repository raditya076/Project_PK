<?php
/**
 * ====================================================
 * FILE: pages/pemilik/hapus_kos.php
 * FUNGSI: Menghapus data kos dari database.
 *         Hanya menerima POST request dari form.
 *         Verifikasi kepemilikan sebelum menghapus.
 * ====================================================
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('pemilik');

// Hanya terima POST request (tolak akses langsung via URL/GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/pemilik/index.php');
}

$user   = user_login();
$id_kos = (int)($_POST['id'] ?? 0);

if ($id_kos <= 0) {
    set_flash('error', 'ID kos tidak valid.');
    redirect(BASE_URL . '/pages/pemilik/index.php');
}

// Cek kepemilikan: pastikan kos ini benar-benar milik user yang login
// Ini penting agar pemilik A tidak bisa hapus kos milik pemilik B!
$stmt_cek = mysqli_prepare($koneksi,
    "SELECT id, nama_kos, foto_utama FROM kos WHERE id = ? AND pemilik_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt_cek, 'ii', $id_kos, $user['id']);
mysqli_stmt_execute($stmt_cek);
$kos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));

if (!$kos) {
    set_flash('error', 'Kos tidak ditemukan atau bukan milik kamu.');
    redirect(BASE_URL . '/pages/pemilik/index.php');
}

// Hapus dari database
$stmt_hapus = mysqli_prepare($koneksi, "DELETE FROM kos WHERE id = ? AND pemilik_id = ?");
mysqli_stmt_bind_param($stmt_hapus, 'ii', $id_kos, $user['id']);

if (mysqli_stmt_execute($stmt_hapus)) {
    // Hapus file foto dari server jika ada
    if (!empty($kos['foto_utama'])) {
        $path_foto = __DIR__ . '/../../assets/images/kos/' . $kos['foto_utama'];
        if (file_exists($path_foto)) {
            unlink($path_foto); // unlink() = hapus file dari sistem
        }
    }
    set_flash('sukses', 'Kos "' . $kos['nama_kos'] . '" berhasil dihapus.');
} else {
    set_flash('error', 'Gagal menghapus kos. Silakan coba lagi.');
}

mysqli_close($koneksi);
redirect(BASE_URL . '/pages/pemilik/index.php');
