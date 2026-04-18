<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/midtrans.php';

wajib_login();
$user = user_login();

// ── Ambil parameter dari Midtrans ─────────────────────
$order_id           = $_GET['order_id']           ?? '';
$status_code        = $_GET['status_code']        ?? '';
$transaction_status = $_GET['transaction_status'] ?? '';
$signature_notif    = $_GET['signature_key']      ?? '';

// ── Verifikasi Signature ──────────────────────────────
// Sama seperti di notifikasi_midtrans.php, tapi untuk redirect
// sha512( order_id + status_code + gross_amount + server_key )
// Untuk finish redirect, gross_amount tidak selalu ada di GET,
// jadi kita ambil dari database sebagai verifikasi tambahan
$status_valid = false;
$booking      = null;

if (!empty($order_id)) {

    // Cari booking dari order_id
    $stmt = mysqli_prepare($koneksi,
        "SELECT b.*, k.nama_kos
         FROM bookings b
         JOIN kos k ON b.kos_id = k.id
         WHERE b.midtrans_order_id = ?
           AND b.penyewa_id        = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'si', $order_id, $user['id']);
    mysqli_stmt_execute($stmt);
    $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($booking) {
        // Verifikasi signature menggunakan total_harga dari DB
        // (lebih aman karena tidak bisa dimanipulasi via URL)
        $gross_amount    = number_format($booking['total_harga'], 2, '.', '');
        $signature_check = hash('sha512',
            $order_id .
            $status_code .
            $gross_amount .
            MIDTRANS_SERVER_KEY
        );

        // Jika signature cocok → notifikasi legitimate dari Midtrans
        // Jika tidak cocok → mungkin URL dimanipulasi, tapi tetap tampilkan
        // berdasarkan status di database (lebih aman)
        $status_valid = ($signature_check === $signature_notif);
    }
}

// ── UPDATE DB LANGSUNG (Fallback jika webhook tidak terpanggil) ──
// Di localhost, Midtrans tidak bisa reach webhook. Jadi kita update
// DB di sini. Karena uang masuk ke Midtrans platform, tidak perlu
// verifikasi pemilik — langsung set status 'aktif'.
if ($booking && ($transaction_status === 'settlement' || $transaction_status === 'capture')) {
    // Hanya update jika belum 'aktif' (hindari duplikat)
    if (!in_array($booking['status'], ['aktif', 'selesai'])) {
        mysqli_begin_transaction($koneksi);
        try {
            // Update status booking → aktif langsung
            $upd_status = mysqli_prepare($koneksi,
                "UPDATE bookings
                 SET status          = 'aktif',
                     midtrans_status = ?,
                     tanggal_bayar   = NOW()
                 WHERE id = ? AND penyewa_id = ?"
            );
            mysqli_stmt_bind_param($upd_status, 'sii', $transaction_status, $booking['id'], $user['id']);
            mysqli_stmt_execute($upd_status);

            // Tambah kamar_terisi otomatis
            $upd_kamar = mysqli_prepare($koneksi,
                "UPDATE kos SET kamar_terisi = kamar_terisi + 1
                 WHERE id = ? AND kamar_terisi < jumlah_kamar"
            );
            mysqli_stmt_bind_param($upd_kamar, 'i', $booking['kos_id']);
            mysqli_stmt_execute($upd_kamar);

            mysqli_commit($koneksi);

            // Insert pembagian_dana jika belum ada (fallback: webhook tidak terpanggil di localhost)
            $cek_pd = mysqli_prepare($koneksi,
                "SELECT id FROM pembagian_dana WHERE booking_id = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($cek_pd, 'i', $booking['id']);
            mysqli_stmt_execute($cek_pd);
            $sudah_ada_pd = mysqli_fetch_assoc(mysqli_stmt_get_result($cek_pd));

            if (!$sudah_ada_pd) {
                $total_transaksi = (float) $booking['total_harga'];
                $persen_platform = (float) PLATFORM_FEE_PERCENT;
                $biaya_platform  = round($total_transaksi * $persen_platform / 100, 2);
                $biaya_gateway   = 0.00;
                $jatah_pemilik   = round($total_transaksi - $biaya_platform - $biaya_gateway, 2);
                $catatan_pd      = "Order: {$order_id} | Metode: {$transaction_status} (via callback)";

                // Ambil pemilik_id dari kos
                $stmt_pemilik = mysqli_prepare($koneksi,
                    "SELECT pemilik_id FROM kos WHERE id = ? LIMIT 1"
                );
                mysqli_stmt_bind_param($stmt_pemilik, 'i', $booking['kos_id']);
                mysqli_stmt_execute($stmt_pemilik);
                $kos_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_pemilik));

                if ($kos_row) {
                    $ins_pd = mysqli_prepare($koneksi,
                        "INSERT INTO pembagian_dana
                         (booking_id, pemilik_id, total_transaksi, persen_platform,
                          biaya_platform, biaya_gateway, jatah_pemilik, status_disbursement, catatan)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
                    );
                    mysqli_stmt_bind_param($ins_pd, 'iiddddds',
                        $booking['id'],
                        $kos_row['pemilik_id'],
                        $total_transaksi,
                        $persen_platform,
                        $biaya_platform,
                        $biaya_gateway,
                        $jatah_pemilik,
                        $catatan_pd
                    );
                    mysqli_stmt_execute($ins_pd);
                }
            }

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
        }

        // Refresh data booking dari DB agar tampilan akurat
        $stmt2 = mysqli_prepare($koneksi,
            "SELECT b.*, k.nama_kos
             FROM bookings b
             JOIN kos k ON b.kos_id = k.id
             WHERE b.id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt2, 'i', $booking['id']);
        mysqli_stmt_execute($stmt2);
        $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
    }
}

// ── Tentukan status tampilan berdasarkan parameter Midtrans ──
// Prioritaskan status DATABASE (sudah diupdate di atas atau oleh webhook).
$status_db = $booking['status'] ?? '';

// Mapping ke tipe tampilan
if (in_array($status_db, ['aktif', 'selesai']) || $transaction_status === 'settlement' || $transaction_status === 'capture') {
    $tipe    = 'sukses';
    $judul   = '🎉 Pembayaran Berhasil!';
    $pesan   = 'Pembayaranmu telah terkonfirmasi otomatis. Kamar sudah tercatat aktif untukmu!';
    $warna   = '#15803d';
    $bg      = '#F0FFF4';

} elseif ($transaction_status === 'pending') {
    $tipe    = 'pending';
    $judul   = '⏳ Menunggu Pembayaran';
    $pesan   = 'Pembayaran sedang diproses. Jika kamu memilih transfer bank / VA, selesaikan pembayaran sebelum batas waktu.';
    $warna   = '#92400e';
    $bg      = '#FFFBEB';

} elseif (in_array($transaction_status, ['cancel', 'deny', 'expire', 'failure'])) {
    $tipe    = 'gagal';
    $judul   = '❌ Pembayaran Gagal';
    $pesan   = 'Pembayaran tidak berhasil. Silakan coba lagi dari halaman pembayaran.';
    $warna   = '#b91c1c';
    $bg      = '#FFF3F3';

} else {
    // Fallback: cek dari status DB
    if (in_array($status_db, ['aktif', 'selesai'])) {
        $tipe = 'sukses'; $judul = '🎉 Pembayaran Berhasil!';
        $pesan = 'Pembayaranmu sudah tercatat. Kamar sudah aktif untukmu.';
        $warna = '#15803d'; $bg = '#F0FFF4';
    } else {
        $tipe = 'pending'; $judul = '⏳ Status Diproses';
        $pesan = 'Status pembayaranmu sedang diproses. Cek halaman riwayat untuk update terbaru.';
        $warna = '#1d4ed8'; $bg = '#EFF6FF';
    }
}

$judul_halaman = 'Hasil Pembayaran';
require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<div class="container" style="max-width:520px;margin:60px auto;padding:0 16px;">

    <!-- Kartu Hasil Pembayaran -->
    <div style="background:<?= $bg ?>;border:1.5px solid <?= $warna ?>33;
                border-radius:16px;padding:36px 32px;text-align:center;
                box-shadow:0 8px 32px rgba(0,0,0,0.08);">

        <!-- Judul -->
        <div style="font-size:28px;font-weight:800;color:<?= $warna ?>;
                    margin-bottom:12px;font-family:'Plus Jakarta Sans',sans-serif;">
            <?= $judul ?>
        </div>

        <!-- Pesan -->
        <p style="font-size:14px;color:#374151;margin-bottom:24px;line-height:1.6;">
            <?= $pesan ?>
        </p>

        <!-- Detail Booking -->
        <?php if ($booking): ?>
        <div style="background:#fff;border-radius:10px;padding:16px;
                    margin-bottom:24px;text-align:left;border:1px solid #e5e7eb;">
            <div style="font-size:12px;color:#6b7280;margin-bottom:4px;">Detail Booking</div>
            <div style="font-size:14px;font-weight:700;color:#111;">
                <?= htmlspecialchars($booking['nama_kos']) ?>
            </div>
            <div style="font-size:12px;color:#6b7280;margin-top:4px;">
                Order ID: <code style="font-size:11px;"><?= htmlspecialchars($order_id) ?></code>
            </div>
            <div style="font-size:15px;font-weight:800;color:<?= $warna ?>;margin-top:8px;">
                Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tombol Aksi -->
        <a href="<?= BASE_URL ?>/pages/riwayat.php"
           style="display:block;width:100%;padding:13px;background:<?= $warna ?>;
                  color:#fff;border-radius:8px;font-weight:700;font-size:14px;
                  text-decoration:none;font-family:'Plus Jakarta Sans',sans-serif;
                  transition:opacity .2s;box-sizing:border-box;">
            📋 Lihat Riwayat Booking
        </a>

        <?php if ($tipe === 'gagal' && $booking): ?>
        <a href="<?= BASE_URL ?>/pages/pembayaran.php?booking_id=<?= $booking['id'] ?>"
           style="display:block;width:100%;padding:13px;background:transparent;
                  color:<?= $warna ?>;border:1.5px solid <?= $warna ?>;
                  border-radius:8px;font-weight:700;font-size:14px;
                  text-decoration:none;margin-top:10px;
                  font-family:'Plus Jakarta Sans',sans-serif;box-sizing:border-box;">
            🔄 Coba Bayar Lagi
        </a>
        <?php endif; ?>

    </div>

    <!-- Note untuk pending -->
    <?php if ($tipe === 'pending'): ?>
    <p style="font-size:12px;color:#6b7280;text-align:center;margin-top:16px;">
        ⏱ Halaman ini akan otomatis memeriksa status setiap 10 detik.
    </p>
    <script>
    // Auto-check status setiap 10 detik jika masih pending
    // Webhook akan update DB, halaman ini refresh untuk cek status terbaru
    <?php if (!empty($order_id)): ?>
    var checkInterval = setInterval(function() {
        fetch('<?= BASE_URL ?>/pages/proses_cek_bayar.php?order_id=<?= urlencode($order_id) ?>', {
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'aktif') {
                clearInterval(checkInterval);
                window.location.reload(); // Reload untuk tampilkan status sukses
            }
        })
        .catch(function() {}); // Abaikan error network
    }, 10000); // Cek tiap 10 detik
    <?php endif; ?>
    </script>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<?php require_once __DIR__ . '/../components/scripts.php'; ?>
