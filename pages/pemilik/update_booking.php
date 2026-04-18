<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

// Hanya pemilik yang bisa akses
wajib_role('pemilik');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/pemilik/booking.php');
}

$user       = user_login();
$booking_id = (int)($_POST['booking_id']    ?? 0);
$aksi       = trim($_POST['aksi']           ?? '');
$catatan    = trim($_POST['catatan_pemilik'] ?? '');

if ($booking_id <= 0 || !in_array($aksi, ['selesai'])) {
    set_flash('error', 'Permintaan tidak valid.');
    redirect(BASE_URL . '/pages/pemilik/booking.php');
}

// Verifikasi: booking ini milik kos yang dimiliki pemilik ini
// JOIN dengan tabel kos untuk memastikan kepemilikan
$stmt_cek = mysqli_prepare($koneksi,
    "SELECT b.id, b.status, b.kos_id, b.penyewa_id, b.total_harga
     FROM bookings b
     JOIN kos k ON b.kos_id = k.id
     WHERE b.id = ? AND k.pemilik_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt_cek, 'ii', $booking_id, $user['id']);
mysqli_stmt_execute($stmt_cek);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));

if (!$booking) {
    set_flash('error', 'Booking tidak ditemukan atau bukan kos milikmu.');
    redirect(BASE_URL . '/pages/pemilik/booking.php');
}

// LOGIKA UPDATE STATUS BERDASARKAN AKSI
//
// Setiap aksi hanya valid dari status tertentu.
// Ini disebut "State Machine" — mesin status.
// Kita tidak bisa skip status (misal: langsung ke 'selesai'
// dari 'menunggu_pembayaran'). Ini menjaga integritas data.

$pesan_sukses = '';
$updated      = false;

if ($aksi === 'selesai') {
    // TANDAI SELESAI: aktif → selesai
    //
    // Masa sewa sudah berakhir.
    // Setelah ini, kamar_terisi berkurang 1 (kamar bebas lagi).
    // Penyewa bisa memberi ulasan setelah status ini.
    if ($booking['status'] !== 'aktif') {
        set_flash('error', 'Hanya booking berstatus "Aktif" yang bisa ditandai selesai.');
        redirect(BASE_URL . '/pages/pemilik/booking.php');
    }

    mysqli_begin_transaction($koneksi);
    try {
        // QUERY 1: Tandai booking selesai
        $stmt_selesai = mysqli_prepare($koneksi,
            "UPDATE bookings SET status = 'selesai', catatan_pemilik = ?
             WHERE id = ? AND status = 'aktif'"
        );
        mysqli_stmt_bind_param($stmt_selesai, 'si', $catatan, $booking_id);
        mysqli_stmt_execute($stmt_selesai);

        if (mysqli_stmt_affected_rows($stmt_selesai) === 0) {
            throw new Exception('Status sudah berubah.');
        }

        // QUERY 2: Kurangi kamar_terisi (kamar bebas kembali)
        // GREATEST(0, ...) mencegah nilai negatif
        $stmt_kamar = mysqli_prepare($koneksi,
            "UPDATE kos SET kamar_terisi = GREATEST(0, kamar_terisi - 1)
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt_kamar, 'i', $booking['kos_id']);
        mysqli_stmt_execute($stmt_kamar);

        mysqli_commit($koneksi);
        $updated      = true;
        $pesan_sukses = '🏁 Booking ditandai selesai. Penyewa sekarang bisa memberi ulasan.';

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        set_flash('error', 'Error: ' . $e->getMessage());
        redirect(BASE_URL . '/pages/pemilik/booking.php');
    }
}

if ($updated) {
    set_flash('sukses', $pesan_sukses);
}

mysqli_close($koneksi);
redirect(BASE_URL . '/pages/pemilik/booking.php');
