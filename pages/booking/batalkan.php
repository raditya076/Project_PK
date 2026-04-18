<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(BASE_URL . '/pages/riwayat.php');

$user       = user_login();
$booking_id = (int)($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    set_flash('error', 'ID booking tidak valid.');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// UPDATE STATUS: menunggu_pembayaran → dibatalkan
// Kondisi WHERE ganda memastikan:
// 1. booking ini milik user yang sedang login
// 2. hanya bisa dibatalkan jika masih 'menunggu_pembayaran'
//    (jika sudah dibayar, batalkan tidak bisa)
$stmt = mysqli_prepare($koneksi,
    "UPDATE bookings
     SET status = 'dibatalkan'
     WHERE id         = ?
       AND penyewa_id = ?
       AND status     = 'menunggu_pembayaran'"
);
mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user['id']);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    set_flash('sukses', 'Booking berhasil dibatalkan.');
} else {
    set_flash('error', 'Tidak dapat membatalkan booking ini. Status mungkin sudah berubah.');
}

mysqli_close($koneksi);
redirect(BASE_URL . '/pages/riwayat.php');
