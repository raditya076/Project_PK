<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/midtrans.php';

// ── Baca body JSON dari Midtrans ──────────────────────
$raw_body = file_get_contents('php://input');

if (empty($raw_body)) {
    http_response_code(400);
    exit('Empty body');
}

// ── Parse JSON ────────────────────────────────────────
$notif = json_decode($raw_body, true);

if (!$notif || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    exit('Invalid JSON');
}

// ── Ekstrak field penting ─────────────────────────────
$order_id         = $notif['order_id']            ?? '';
$status_transaksi = $notif['transaction_status']  ?? '';
$fraud_status     = $notif['fraud_status']         ?? '';
$payment_type     = $notif['payment_type']         ?? '';
$gross_amount     = $notif['gross_amount']         ?? '0'; // String asli (untuk signature)
$status_code      = $notif['status_code']          ?? '';
$signature_notif  = $notif['signature_key']        ?? '';

// ── VERIFIKASI SIGNATURE KEY ─────────────────────────
// KRITIS: Memastikan notifikasi benar-benar dari Midtrans,
// bukan dari pihak yang mencoba memanipulasi status.
//
// Rumus resmi Midtrans:
//   sha512( order_id + status_code + gross_amount + server_key )
//
// Kita hitung ulang signature-nya, lalu bandingkan
// dengan signature yang dikirim Midtrans.
$signature_kita = hash('sha512',
    $order_id .
    $status_code .
    $gross_amount .         // Gunakan string asli, BUKAN float
    MIDTRANS_SERVER_KEY     // Server Key dari config/midtrans.php
);

if ($signature_kita !== $signature_notif) {
    // Signature tidak cocok → TOLAK notifikasi ini
    http_response_code(403);
    error_log('[Midtrans] INVALID SIGNATURE for order: ' . $order_id);
    exit('Invalid signature');
}

// Jika order_id kosong setelah verifikasi, abaikan
if (empty($order_id)) {
    http_response_code(200);
    exit('Ignored');
}

// ── Cari booking di database ──────────────────────────
$stmt = mysqli_prepare($koneksi,
    "SELECT b.*, k.pemilik_id
     FROM bookings b
     JOIN kos k ON b.kos_id = k.id
     WHERE b.midtrans_order_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 's', $order_id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$booking) {
    // Booking tidak ditemukan (mungkin test ping dari Midtrans dashboard)
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'booking not found']);
    exit;
}

// ── Mapping status Midtrans → status internal ─────────
// Karena uang masuk ke akun Midtrans platform (bukan rekening pemilik),
// tidak perlu verifikasi pemilik. Settlement/capture langsung = aktif.
$status_baru = $booking['status']; // Default: tidak berubah
$sudah_bayar = false;

if ($status_transaksi === 'capture') {
    // Khusus kartu kredit — cek fraud_status
    if ($fraud_status === 'accept') {
        $status_baru = 'aktif'; // Langsung aktif tanpa verifikasi pemilik
        $sudah_bayar = true;
    } elseif ($fraud_status === 'challenge') {
        $status_baru = 'menunggu_pembayaran'; // Menunggu review Midtrans
    }

} elseif ($status_transaksi === 'settlement') {
    // Pembayaran final & sukses — langsung aktif
    $status_baru = 'aktif';
    $sudah_bayar = true;

} elseif ($status_transaksi === 'pending') {
    // User membuka Snap tapi belum bayar
    $status_baru = 'menunggu_pembayaran';

} elseif (in_array($status_transaksi, ['cancel', 'deny', 'expire'])) {
    // Dibatalkan / ditolak / kadaluarsa
    $status_baru = 'menunggu_pembayaran';
}

// ── Update status booking ─────────────────────────────
// Skip jika sudah aktif/selesai (duplikat notifikasi Midtrans)
if ($sudah_bayar && in_array($booking['status'], ['aktif', 'selesai'])) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'order_id' => $order_id, 'new_status' => $booking['status']]);
    exit;
}

mysqli_begin_transaction($koneksi);
try {
    $sql = "UPDATE bookings
            SET status            = ?,
                midtrans_status   = ?,
                payment_type      = ?,
                metode_pembayaran = ?";

    if ($sudah_bayar) {
        $sql .= ", tanggal_bayar = NOW()";
    }

    $sql .= " WHERE id = ?";

    $upd = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($upd, 'ssssi',
        $status_baru,
        $status_transaksi,
        $payment_type,
        $payment_type,
        $booking['id']
    );
    mysqli_stmt_execute($upd);

    // Jika aktif (sudah bayar), tambah kamar_terisi otomatis
    if ($sudah_bayar) {
        $upd_kamar = mysqli_prepare($koneksi,
            "UPDATE kos SET kamar_terisi = kamar_terisi + 1
             WHERE id = ? AND kamar_terisi < jumlah_kamar"
        );
        mysqli_stmt_bind_param($upd_kamar, 'i', $booking['kos_id']);
        mysqli_stmt_execute($upd_kamar);
    }

    mysqli_commit($koneksi);
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    error_log('[Midtrans webhook] Transaction failed: ' . $e->getMessage());
}

// ── Catat Pembagian Dana ──────────────────────────────
// Hanya saat settlement PERTAMA KALI (hindari duplikat)
if ($sudah_bayar) {

    $cek = mysqli_prepare($koneksi,
        "SELECT id FROM pembagian_dana WHERE booking_id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($cek, 'i', $booking['id']);
    mysqli_stmt_execute($cek);
    $sudah_ada = mysqli_fetch_assoc(mysqli_stmt_get_result($cek));

    if (!$sudah_ada) {
        // ── LOGIKA PEMBAGIAN DANA ──────────────────────────────
        //
        // Contoh: total_harga = Rp 1.000.000, platform fee = 3%
        //
        //   biaya_platform = 1.000.000 × 3 / 100  = Rp 30.000
        //   biaya_gateway  = 0 (Midtrans potong langsung dari saldo)
        //   jatah_pemilik  = 1.000.000 - 30.000    = Rp 970.000
        //
        // ──────────────────────────────────────────────────────

        $total_transaksi = (float) $booking['total_harga'];
        $persen_platform = (float) PLATFORM_FEE_PERCENT;
        $biaya_platform  = round($total_transaksi * $persen_platform / 100, 2);
        $biaya_gateway   = 0.00;
        $jatah_pemilik   = round($total_transaksi - $biaya_platform - $biaya_gateway, 2);
        $catatan         = "Order: {$order_id} | Metode: {$payment_type}";

        $ins = mysqli_prepare($koneksi,
            "INSERT INTO pembagian_dana
             (booking_id, pemilik_id, total_transaksi, persen_platform,
              biaya_platform, biaya_gateway, jatah_pemilik, status_disbursement, catatan)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
        );
        mysqli_stmt_bind_param($ins, 'iiddddds',
            $booking['id'],
            $booking['pemilik_id'],
            $total_transaksi,
            $persen_platform,
            $biaya_platform,
            $biaya_gateway,
            $jatah_pemilik,
            $catatan
        );
        mysqli_stmt_execute($ins);
    }
}

// ── Balas ke Midtrans dengan HTTP 200 ────────────────
// Wajib 200, jika tidak Midtrans akan retry notifikasi
http_response_code(200);
echo json_encode([
    'status'     => 'ok',
    'order_id'   => $order_id,
    'new_status' => $status_baru,
]);
