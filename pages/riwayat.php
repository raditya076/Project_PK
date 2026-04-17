<?php
/**
 * ====================================================
 * FILE: pages/riwayat.php
 * FUNGSI: Halaman riwayat booking untuk pencari/penyewa.
 *         Menampilkan semua booking + status + tombol aksi.
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

wajib_login();
$user = user_login();

// Ambil semua booking milik user ini, terbaru dulu
$stmt = mysqli_prepare($koneksi,
    "SELECT b.*, k.nama_kos, k.foto_utama, k.kota, k.tipe
     FROM bookings b
     JOIN kos k ON b.kos_id = k.id
     WHERE b.penyewa_id = ?
     ORDER BY b.created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $user['id']);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$bookings = [];
while ($row = mysqli_fetch_assoc($result)) $bookings[] = $row;

// Label status yang ditampilkan ke user (lebih ramah)
$label_status = [
    'menunggu_pembayaran' => ['⏳', 'Menunggu Pembayaran', 'menunggu_pembayaran'],
    'aktif'               => ['✅', 'Aktif / Terkonfirmasi', 'aktif'],
    'ditolak'             => ['❌', 'Booking Ditolak',      'ditolak'],
    'selesai'             => ['🏁', 'Masa Sewa Selesai',   'selesai'],
    'dibatalkan'          => ['🚫', 'Dibatalkan',           'dibatalkan'],
];

$extra_head    = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/transaction.css">';
$judul_halaman = "Riwayat Booking";
$css_tambahan  = "dashboard.css";

require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<div style="background:var(--color-surface);border-bottom:1px solid var(--color-border);padding:28px 0;">
    <div class="container">
        <?= get_flash() ?>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="font-size:22px;font-weight:800;margin:0;">📋 Riwayat Booking</h1>
                <p style="font-size:14px;color:var(--color-text-muted);margin:4px 0 0;">
                    <?= count($bookings) ?> total booking — pantau status pembayaran dan sewa kamu
                </p>
            </div>
            <a href="<?= BASE_URL ?>/pages/cari.php" class="btn-kosta btn" style="font-size:13px;padding:9px 20px;">
                + Cari Kos Baru
            </a>
        </div>
    </div>
</div>

<section style="padding:32px 0 80px;">
<div class="container">

    <?php if (empty($bookings)): ?>
        <!-- Empty state -->
        <div style="text-align:center;padding:80px 20px;">
            <div style="font-size:56px;margin-bottom:16px;">📭</div>
            <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;">Belum Ada Booking</h3>
            <p style="color:var(--color-text-muted);margin-bottom:24px;">
                Kamu belum pernah memesan kos di Kosta'. Mulai cari kos sekarang!
            </p>
            <a href="<?= BASE_URL ?>/pages/cari.php" class="btn-kosta btn">Jelajahi Kos →</a>
        </div>

    <?php else: ?>

        <!-- Filter tab status (JavaScript toggle) -->
        <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
            <button class="filter-tab aktif" onclick="filterStatus('semua', this)">
                Semua (<?= count($bookings) ?>)
            </button>
            <?php
            $status_count = array_count_values(array_column($bookings, 'status'));
            foreach ($label_status as $k => [$ikon, $label, $cls]):
                if (!isset($status_count[$k])) continue;
            ?>
            <button class="filter-tab" onclick="filterStatus('<?= $k ?>', this)">
                <?= $ikon ?> <?= $label ?> (<?= $status_count[$k] ?>)
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Daftar Booking -->
        <?php foreach ($bookings as $b):
            [$ikon, $label, $cls] = $label_status[$b['status']] ?? ['❓', $b['status'], 'dibatalkan'];
            $tgl_masuk  = date('d M Y', strtotime($b['tanggal_masuk']));
            $tgl_keluar = date('d M Y', strtotime($b['tanggal_keluar']));
            $total_fmt  = 'Rp ' . number_format($b['total_harga'], 0, ',', '.');
            $tgl_buat   = date('d M Y, H:i', strtotime($b['created_at']));
        ?>
        <div class="riwayat-card" data-status="<?= $b['status'] ?>">
            <!-- Header kartu -->
            <div class="riwayat-header">
                <div style="display:flex;align-items:center;gap:14px;">
                    <?php if (!empty($b['foto_utama'])): ?>
                        <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($b['foto_utama']) ?>"
                             alt="" style="width:56px;height:56px;border-radius:8px;object-fit:cover;flex-shrink:0;">
                    <?php else: ?>
                        <div style="width:56px;height:56px;border-radius:8px;background:var(--color-surface-alt);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🏠</div>
                    <?php endif; ?>
                    <div>
                        <div class="riwayat-kos-name"><?= htmlspecialchars($b['nama_kos']) ?></div>
                        <div class="riwayat-kos-loc">📍 <?= htmlspecialchars($b['kota']) ?></div>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">
                            Dipesan: <?= $tgl_buat ?> · ID #<?= $b['id'] ?>
                        </div>
                    </div>
                </div>
                <span class="status-badge <?= $cls ?>"><?= $ikon ?> <?= $label ?></span>
            </div>

            <!-- Detail info -->
            <div class="riwayat-body">
                <div class="riwayat-info-col">
                    <div class="riwayat-info-label">Tanggal Masuk</div>
                    <div class="riwayat-info-value"><?= $tgl_masuk ?></div>
                </div>
                <div class="riwayat-info-col">
                    <div class="riwayat-info-label">Tanggal Keluar</div>
                    <div class="riwayat-info-value"><?= $tgl_keluar ?></div>
                </div>
                <div class="riwayat-info-col">
                    <div class="riwayat-info-label">Durasi</div>
                    <div class="riwayat-info-value"><?= $b['durasi_bulan'] ?> bulan</div>
                </div>
                <div class="riwayat-info-col">
                    <div class="riwayat-info-label">Total Bayar</div>
                    <div class="riwayat-info-value" style="color:var(--color-accent);"><?= $total_fmt ?></div>
                </div>
                <?php if (!empty($b['metode_pembayaran'])): ?>
                <div class="riwayat-info-col">
                    <div class="riwayat-info-label">Metode</div>
                    <div class="riwayat-info-value" style="text-transform:capitalize;">
                        <?= str_replace('_', ' ', $b['metode_pembayaran']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Catatan pemilik (jika ditolak) -->
            <?php if ($b['status'] === 'ditolak' && !empty($b['catatan_pemilik'])): ?>
            <div style="padding:12px 20px;background:rgba(185,28,28,0.04);border-top:1px solid #FCA5A5;">
                <p style="font-size:12px;color:#b91c1c;margin:0;">
                    <strong>Alasan penolakan:</strong> <?= htmlspecialchars($b['catatan_pemilik']) ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- FOOTER: Tombol Aksi -->
            <div class="riwayat-footer">

                <!-- Lihat detail kos -->
                <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $b['kos_id'] ?>"
                   class="btn-action" target="_blank" style="font-size:12px;">
                    👁️ Lihat Kos
                </a>

                <!-- Lanjutkan pembayaran (jika masih menunggu) -->
                <?php if ($b['status'] === 'menunggu_pembayaran'): ?>
                    <a href="<?= BASE_URL ?>/pages/pembayaran.php?booking_id=<?= $b['id'] ?>"
                       class="btn-action edit" style="font-size:12px;border-color:var(--color-accent);color:var(--color-accent);">
                        💳 Bayar Sekarang
                    </a>
                    <form method="POST" action="<?= BASE_URL ?>/pages/booking/batalkan.php"
                          onsubmit="return confirm('Yakin batalkan booking ini?');" style="display:inline;">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn-action hapus" style="font-size:12px;">
                            🗑️ Batalkan
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Booking aktif atau selesai: beri ulasan -->
                <?php if (in_array($b['status'], ['aktif', 'selesai'])): ?>
                    <?php
                    // Cek apakah sudah review
                    $cek_rev = mysqli_prepare($koneksi,
                        "SELECT id FROM reviews WHERE user_id = ? AND kos_id = ? LIMIT 1"
                    );
                    mysqli_stmt_bind_param($cek_rev, 'ii', $user['id'], $b['kos_id']);
                    mysqli_stmt_execute($cek_rev);
                    mysqli_stmt_store_result($cek_rev);
                    $sudah_review = mysqli_stmt_num_rows($cek_rev) > 0;
                    ?>
                    <?php if (!$sudah_review): ?>
                        <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $b['kos_id'] ?>#ulasan"
                           class="btn-action edit" style="font-size:12px;">
                            ⭐ Beri Ulasan
                        </a>
                    <?php else: ?>
                        <span style="font-size:12px;color:#15803d;font-weight:600;">✅ Sudah diulas</span>
                    <?php endif; ?>
                <?php endif; ?>

            </div><!-- /riwayat-footer -->
        </div><!-- /riwayat-card -->
        <?php endforeach; ?>

    <?php endif; ?>

    <?php mysqli_close($koneksi); ?>
</div>
</section>

<!-- CSS filter tab + filter JS -->
<style>
.filter-tab {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 20px;
    border: 1.5px solid var(--color-border);
    background: var(--color-surface);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    color: var(--color-text-muted);
    transition: all 0.2s;
    font-family: var(--font-main);
}
.filter-tab.aktif,
.filter-tab:hover {
    border-color: var(--color-accent);
    color: var(--color-accent);
    background: rgba(197,0,0,0.05);
}
</style>
<script>
function filterStatus(status, btn) {
    // Set tombol aktif
    document.querySelectorAll('.filter-tab').forEach(function(b) { b.classList.remove('aktif'); });
    btn.classList.add('aktif');

    // Filter kartu booking
    document.querySelectorAll('.riwayat-card').forEach(function(card) {
        if (status === 'semua' || card.dataset.status === status) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<?php require_once __DIR__ . '/../components/scripts.php'; ?>
