<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('pemilik');
$user = user_login();

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/pemilik/index.php');
}

$foto_id = (int)($_POST['foto_id'] ?? 0);
$kos_id  = (int)($_POST['kos_id']  ?? 0);
$kembali = BASE_URL . '/pages/pemilik/edit_kos.php?id=' . $kos_id;

if ($foto_id <= 0 || $kos_id <= 0) {
    set_flash('error', 'ID foto tidak valid.');
    redirect($kembali);
}

// Ambil data foto — pastikan kos ini milik user yang login
$stmt = mysqli_prepare($koneksi,
    "SELECT f.id, f.nama_file, f.kos_id
     FROM kos_foto f
     JOIN kos k ON f.kos_id = k.id
     WHERE f.id = ? AND k.pemilik_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $foto_id, $user['id']);
mysqli_stmt_execute($stmt);
$foto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$foto) {
    set_flash('error', 'Foto tidak ditemukan atau bukan milikmu.');
    redirect($kembali);
}

// Cek apakah ini adalah satu-satunya foto kos ini
$cek_jumlah = mysqli_prepare($koneksi,
    "SELECT COUNT(*) AS total FROM kos_foto WHERE kos_id = ?"
);
mysqli_stmt_bind_param($cek_jumlah, 'i', $kos_id);
mysqli_stmt_execute($cek_jumlah);
$jumlah_foto = mysqli_fetch_assoc(mysqli_stmt_get_result($cek_jumlah))['total'];

// Hapus dari database
$del = mysqli_prepare($koneksi, "DELETE FROM kos_foto WHERE id = ?");
mysqli_stmt_bind_param($del, 'i', $foto_id);
mysqli_stmt_execute($del);

// Hapus file dari filesystem
$path_file = __DIR__ . '/../../assets/images/kos/' . $foto['nama_file'];
if (file_exists($path_file)) {
    unlink($path_file);
}

// Update foto_utama di tabel kos (ambil foto pertama yang tersisa)
$ambil_pertama = mysqli_prepare($koneksi,
    "SELECT nama_file FROM kos_foto WHERE kos_id = ? ORDER BY urutan ASC, id ASC LIMIT 1"
);
mysqli_stmt_bind_param($ambil_pertama, 'i', $kos_id);
mysqli_stmt_execute($ambil_pertama);
$foto_pertama = mysqli_fetch_assoc(mysqli_stmt_get_result($ambil_pertama));

$foto_utama_baru = $foto_pertama ? $foto_pertama['nama_file'] : '';
$upd = mysqli_prepare($koneksi, "UPDATE kos SET foto_utama = ? WHERE id = ?");
mysqli_stmt_bind_param($upd, 'si', $foto_utama_baru, $kos_id);
mysqli_stmt_execute($upd);

set_flash('sukses', 'Foto berhasil dihapus.');
redirect($kembali);
