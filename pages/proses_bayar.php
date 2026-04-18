<?php
ob_start(); // Tangkap semua output tidak diinginkan (PHP notices, warnings, dll)

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/midtrans.php';

ob_end_clean();        // Bersihkan output sebelumnya
ob_start();            // Buffer baru untuk respons bersih
header('Content-Type: application/json');

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method tidak diizinkan.']);
    exit;
}

wajib_login();
$user = user_login();

$booking_id = (int)($_POST['booking_id'] ?? 0);
if ($booking_id <= 0) {
    echo json_encode(['error' => 'Booking ID tidak valid.']);
    exit;
}

// ── Ambil data booking ────────────────────────────────
$stmt = mysqli_prepare($koneksi,
    "SELECT b.*,
            k.nama_kos,
            k.pemilik_id,
            u.nama  AS nama_penyewa,
            u.email AS email_penyewa,
            u.no_hp AS hp_penyewa
     FROM bookings b
     JOIN kos   k ON b.kos_id     = k.id
     JOIN users u ON b.penyewa_id = u.id
     WHERE b.id         = ?
       AND b.penyewa_id = ?
       AND b.status     = 'menunggu_pembayaran'
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user['id']);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$booking) {
    echo json_encode(['error' => 'Booking tidak ditemukan atau sudah dibayar.']);
    exit;
}

// ── Jika snap_token sudah ada di DB, kembalikan langsung ──
// Mencegah request berulang ke Midtrans API
if (!empty($booking['snap_token'])) {
    echo json_encode([
        'snap_token' => $booking['snap_token'],
        'order_id'   => $booking['midtrans_order_id'],
    ]);
    exit;
}

// ── Buat Order ID unik ────────────────────────────────
// Format: KOSTA-{booking_id}-{timestamp}
$order_id = 'KOSTA-' . $booking_id . '-' . time();

// ── Siapkan payload JSON untuk Midtrans Snap API ──────
$payload = [
    'transaction_details' => [
        'order_id'     => $order_id,
        // gross_amount HARUS integer (rupiah, tanpa desimal)
        'gross_amount' => (int) $booking['total_harga'],
    ],
    'customer_details' => [
        'first_name' => $booking['nama_penyewa'],
        'email'      => $booking['email_penyewa'],
        'phone'      => $booking['hp_penyewa'] ?? '',
    ],
    'item_details' => [
        [
            'id'       => 'KOS-' . $booking['kos_id'],
            'price'    => (int) $booking['total_harga'],
            'quantity' => 1,
            'name'     => 'Sewa Kos: ' . mb_substr($booking['nama_kos'], 0, 40),
        ]
    ],
    'callbacks' => [
        // URL tujuan setelah user selesai di Snap popup
        // Midtrans akan redirect browser ke URL ini dengan parameter status
        'finish' => BASE_URL . '/pages/callback_bayar.php',
    ],
    'expiry' => [
        'unit'     => 'hours',
        'duration' => 24,
    ],
];

// ── Kirim request ke Midtrans API via cURL ────────────
//
// Midtrans Snap API menggunakan HTTP Basic Auth:
//   - Username = Server Key
//   - Password  = (kosong)
// Lalu di-encode ke Base64: base64(serverKey + ":")
$auth = base64_encode(MIDTRANS_SERVER_KEY . ':');

$ch = curl_init();

curl_setopt_array($ch, [
    // URL endpoint Snap Midtrans
    CURLOPT_URL            => MIDTRANS_SNAP_API,

    // Kirim sebagai POST
    CURLOPT_POST           => true,

    // Body request: JSON dari payload
    CURLOPT_POSTFIELDS     => json_encode($payload),

    // Header HTTP yang diperlukan Midtrans
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth,
    ],

    // Kembalikan response sebagai string (bukan langsung diprint)
    CURLOPT_RETURNTRANSFER => true,

    // Timeout: max 30 detik menunggu response
    CURLOPT_TIMEOUT        => 30,

    // Verifikasi SSL (aktifkan di production)
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response    = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

// ── Tangani error cURL ────────────────────────────────
if ($curl_error) {
    error_log('[proses_bayar] cURL error: ' . $curl_error);
    echo json_encode(['error' => 'Gagal terhubung ke Midtrans. Coba lagi.']);
    exit;
}

// ── Parse response JSON dari Midtrans ────────────────
$result = json_decode($response, true);

// HTTP 201 = Created (sukses dapat token)
// HTTP lain = error dari Midtrans
if ($http_status !== 201 || empty($result['token'])) {
    $pesan_error = $result['error_messages'][0] ?? $result['message'] ?? 'Unknown error';
    error_log('[proses_bayar] Midtrans error (' . $http_status . '): ' . $response);
    echo json_encode(['error' => 'Midtrans: ' . $pesan_error]);
    exit;
}

$snap_token = $result['token'];

// ── Simpan snap_token & order_id ke database ─────────
$upd = mysqli_prepare($koneksi,
    "UPDATE bookings
     SET snap_token        = ?,
         midtrans_order_id = ?
     WHERE id         = ?
       AND penyewa_id = ?"
);
mysqli_stmt_bind_param($upd, 'ssii', $snap_token, $order_id, $booking_id, $user['id']);
mysqli_stmt_execute($upd);

// ── Kembalikan token ke frontend ──────────────────────
echo json_encode([
    'snap_token' => $snap_token,
    'order_id'   => $order_id,
]);
