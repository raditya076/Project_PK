<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/index.php');
}

$kos_id  = (int)($_POST['kos_id']  ?? 0);
$kembali = $_POST['kembali'] ?? BASE_URL . '/index.php';

if ($kos_id <= 0) {
    redirect($kembali);
}

// Inisialisasi array jika belum ada di session
if (!isset($_SESSION['bandingkan']) || !is_array($_SESSION['bandingkan'])) {
    $_SESSION['bandingkan'] = [];
}

// Cek apakah sudah ada di daftar
if (in_array($kos_id, $_SESSION['bandingkan'])) {
    set_flash('info', 'Kos ini sudah ada di daftar perbandingan.');
    redirect($kembali);
}

// Maksimal 3 kos untuk dibandingkan
if (count($_SESSION['bandingkan']) >= 3) {
    set_flash('warning', 'Maksimal 3 kos untuk dibandingkan. Hapus salah satu terlebih dahulu.');
    redirect($kembali);
}

// Verifikasi kos ada di database
$stmt = mysqli_prepare($koneksi, "SELECT id, nama_kos FROM kos WHERE id = ? AND status = 'aktif' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $kos_id);
mysqli_stmt_execute($stmt);
$kos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$kos) {
    set_flash('error', 'Kos tidak ditemukan.');
    redirect($kembali);
}

// Tambahkan ke array session
$_SESSION['bandingkan'][] = $kos_id;

set_flash('sukses', "\"" . $kos['nama_kos'] . "\" ditambahkan ke perbandingan! ⚖️");
mysqli_close($koneksi);
redirect($kembali);
