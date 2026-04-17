<?php
/**
 * FILE: pages/proses_cek_bayar.php
 * FUNGSI: Endpoint AJAX untuk cek status pembayaran dari database.
 * Dipanggil oleh callback_bayar.php setiap 10 detik untuk
 * polling status saat pembayaran masih pending.
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');
wajib_login();
$user = user_login();

$order_id = trim($_GET['order_id'] ?? '');

if (empty($order_id)) {
    echo json_encode(['error' => 'Order ID kosong']);
    exit;
}

$stmt = mysqli_prepare($koneksi,
    "SELECT status FROM bookings
     WHERE midtrans_order_id = ? AND penyewa_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'si', $order_id, $user['id']);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

echo json_encode([
    'status' => $row['status'] ?? 'tidak_ditemukan'
]);
