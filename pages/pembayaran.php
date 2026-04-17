<?php
/**
 * ====================================================
 * FILE: pages/pembayaran.php
 * FUNGSI: Halaman pembayaran — bayar menggunakan Midtrans.
 *
 * ALUR:
 *   Status SEBELUM: 'menunggu_pembayaran'
 *   Status SESUDAH: 'aktif' (dikonfirmasi otomatis oleh Midtrans)
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/midtrans.php';

wajib_login();
$user = user_login();

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) redirect(BASE_URL . '/pages/riwayat.php');

// Ambil data booking + kos, pastikan milik user yang login
$stmt = mysqli_prepare($koneksi,
    "SELECT b.*, k.nama_kos, k.foto_utama, k.kota, k.harga_per_bulan
     FROM bookings b
     JOIN kos k ON b.kos_id = k.id
     WHERE b.id = ? AND b.penyewa_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user['id']);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$booking) {
    set_flash('error', 'Booking tidak ditemukan.');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Hanya bisa bayar jika status masih 'menunggu_pembayaran'
if ($booking['status'] !== 'menunggu_pembayaran') {
    set_flash('info', 'Status booking ini sudah: ' . ucwords(str_replace('_', ' ', $booking['status'])));
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Format harga & tanggal
$harga_format   = 'Rp ' . number_format($booking['total_harga'], 0, ',', '.');
$tgl_masuk_fmt  = date('d F Y', strtotime($booking['tanggal_masuk']));
$tgl_keluar_fmt = date('d F Y', strtotime($booking['tanggal_keluar']));

$extra_head    = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/transaction.css">';
$judul_halaman = "Pembayaran Booking #" . $booking_id;
$css_tambahan  = "detail.css";

require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<div class="container" style="padding-top:12px;"><?= get_flash() ?></div>

<div class="breadcrumb-bar">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/riwayat.php">Riwayat</a></li>
                <li class="breadcrumb-item active">Pembayaran</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container">
<div class="payment-layout">

    <!-- ===== KOLOM KIRI: Pembayaran Midtrans ===== -->
    <div>

        <!-- TOMBOL BAYAR MIDTRANS -->
        <div class="booking-form-card" style="margin-bottom:20px;border:2px solid var(--color-accent);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="font-size:32px;">⚡</div>
                <div>
                    <div style="font-size:16px;font-weight:800;color:var(--color-text);">Bayar dengan Midtrans</div>
                    <div style="font-size:12px;color:var(--color-text-muted);margin-top:3px;">GoPay · OVO · QRIS · Transfer Bank · Kartu Kredit</div>
                </div>
            </div>

            <button type="button"
                    id="btn-bayar-midtrans"
                    onclick="bayarDenganMidtrans()"
                    style="width:100%;padding:16px;font-size:16px;font-weight:700;
                           background:var(--color-accent);color:#fff;border:none;
                           border-radius:10px;cursor:pointer;transition:all .2s;
                           font-family:'Plus Jakarta Sans',sans-serif;
                           box-shadow:0 4px 18px rgba(197,0,0,0.3);">
                💳 Bayar Sekarang — <?= $harga_format ?>
            </button>

            <!-- Div untuk menampilkan error Midtrans -->
            <div id="midtrans-error" style="display:none;margin-top:14px;
                 padding:12px 16px;background:#FFF3F3;color:#b91c1c;
                 border:1px solid #fca5a5;border-radius:8px;
                 font-size:13px;font-weight:500;"></div>

            <p style="font-size:11px;color:var(--color-text-muted);text-align:center;margin-top:14px;margin-bottom:0;">
                🔒 Transaksi aman &amp; terenkripsi oleh Midtrans
            </p>
        </div>

        <!-- Batalkan Booking -->
        <div style="text-align:center;">
            <form method="POST" action="<?= BASE_URL ?>/pages/booking/batalkan.php"
                  onsubmit="return confirm('Yakin ingin membatalkan booking ini?');">
                <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                <button type="submit" class="btn-action"
                        style="font-size:13px; color:var(--color-text-muted);">
                    🗑️ Batalkan Booking Ini
                </button>
            </form>
        </div>

    </div><!-- /kolom kiri -->


    <!-- ===== KOLOM KANAN: Ringkasan Booking ===== -->
    <div>
        <div class="booking-summary-card">
            <div class="booking-summary-title">📋 Ringkasan Booking</div>

            <!-- Foto kos -->
            <?php if (!empty($booking['foto_utama'])): ?>
                <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($booking['foto_utama']) ?>"
                     alt="foto kos"
                     style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:14px;">
            <?php endif; ?>

            <div class="summary-row">
                <span class="label">Kos</span>
                <span class="value"><?= htmlspecialchars($booking['nama_kos']) ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Kota</span>
                <span class="value"><?= htmlspecialchars($booking['kota']) ?></span>
            </div>
            <?php if (!empty($booking['nomor_kamar'])): ?>
            <div class="summary-row">
                <span class="label">No. Kamar</span>
                <span class="value"><?= htmlspecialchars($booking['nomor_kamar']) ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row">
                <span class="label">Tanggal Masuk</span>
                <span class="value"><?= $tgl_masuk_fmt ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Tanggal Keluar</span>
                <span class="value"><?= $tgl_keluar_fmt ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Durasi</span>
                <span class="value"><?= $booking['durasi_bulan'] ?> bulan</span>
            </div>
            <div class="summary-row">
                <span class="label">Harga/bulan</span>
                <span class="value">Rp <?= number_format($booking['harga_per_bulan'], 0, ',', '.') ?></span>
            </div>
            <div class="summary-row summary-total">
                <span class="label">Total</span>
                <span class="value"><?= $harga_format ?></span>
            </div>

            <!-- Status badge -->
            <div style="text-align:center; margin-top:14px;">
                <span class="status-badge menunggu_pembayaran">⏳ Menunggu Pembayaran</span>
            </div>
        </div>
    </div>

</div><!-- /payment-layout -->
<?php mysqli_close($koneksi); ?>
</div><!-- /container -->

<?php require_once __DIR__ . '/../components/footer.php'; ?>

<script>
// ── Tampilkan error di halaman (bukan alert) ───────────────
function tampilkanError(pesan) {
    var el = document.getElementById('midtrans-error');
    if (el) { el.textContent = '⚠️ ' + pesan; el.style.display = 'block'; }
}
function sembunyikanError() {
    var el = document.getElementById('midtrans-error');
    if (el) el.style.display = 'none';
}

// ── Bayar dengan Midtrans Snap ────────────────────────
function bayarDenganMidtrans() {
    console.log('[Kosta] bayarDenganMidtrans() dipanggil');
    sembunyikanError();

    // Cek apakah Snap JS dari Midtrans sudah termuat
    if (typeof snap === 'undefined') {
        tampilkanError('Sistem pembayaran Midtrans belum termuat. Refresh halaman dan coba lagi.');
        console.error('[Kosta] snap object tidak ditemukan — snap.js gagal dimuat');
        return;
    }

    var btn = document.getElementById('btn-bayar-midtrans');
    btn.disabled      = true;
    btn.textContent   = '⏳ Menghubungkan ke Midtrans...';
    btn.style.opacity = '0.7';

    fetch('<?= BASE_URL ?>/pages/proses_bayar.php', {
        method     : 'POST',
        headers    : { 'Content-Type': 'application/x-www-form-urlencoded' },
        body       : 'booking_id=<?= $booking_id ?>',
        credentials: 'same-origin'
    })
    .then(function(response) {
        console.log('[Kosta] proses_bayar response status:', response.status);
        if (!response.ok) {
            throw new Error('Server error: HTTP ' + response.status);
        }
        return response.json();
    })
    .then(function(data) {
        console.log('[Kosta] proses_bayar response data:', data);
        if (data.error) {
            tampilkanError(data.error);
            resetBtn(btn);
            return;
        }
        // Buka Midtrans Snap Popup
        snap.pay(data.snap_token, {
            onSuccess: function(result) {
                console.log('[Midtrans] Success:', result);
                window.location.href = '<?= BASE_URL ?>/pages/callback_bayar.php'
                    + '?order_id='           + encodeURIComponent(result.order_id)
                    + '&status_code='        + encodeURIComponent(result.status_code)
                    + '&transaction_status=' + encodeURIComponent(result.transaction_status)
                    + '&signature_key='      + encodeURIComponent(result.signature_key || '');
            },
            onPending: function(result) {
                console.log('[Midtrans] Pending:', result);
                window.location.href = '<?= BASE_URL ?>/pages/callback_bayar.php'
                    + '?order_id='           + encodeURIComponent(result.order_id)
                    + '&status_code='        + encodeURIComponent(result.status_code)
                    + '&transaction_status=' + encodeURIComponent(result.transaction_status)
                    + '&signature_key='      + encodeURIComponent(result.signature_key || '');
            },
            onError: function(result) {
                console.error('[Midtrans] Error:', result);
                tampilkanError('Pembayaran gagal. Silakan coba lagi.');
                resetBtn(btn);
            },
            onClose: function() {
                console.log('[Midtrans] Popup ditutup user');
                resetBtn(btn);
            }
        });
    })
    .catch(function(err) {
        console.error('[Kosta] Fetch error:', err);
        tampilkanError(err.message || 'Terjadi kesalahan. Buka F12 → Console untuk detail.');
        resetBtn(btn);
    });
}

// Helper: kembalikan tombol ke state semula
function resetBtn(btn) {
    btn.disabled      = false;
    btn.textContent   = '💳 Bayar Sekarang — <?= $harga_format ?>';
    btn.style.opacity = '1';
}
</script>

<script src="<?= MIDTRANS_SNAP_JS ?>" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>" async></script>
<?php require_once __DIR__ . '/../components/scripts.php'; ?>
