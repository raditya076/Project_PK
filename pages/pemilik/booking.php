<?php
/**
 * ====================================================
 * FILE: pages/pemilik/booking.php
 * FUNGSI: Dashboard pemilik — tampilkan daftar booking
 *         yang masuk ke kos mereka + tombol aksi
 *         (konfirmasi / tolak / tandai selesai).
 * ====================================================
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('pemilik');
$user = user_login();

// Ambil semua booking untuk semua kos milik pemilik ini
$stmt = mysqli_prepare($koneksi,
    "SELECT b.*,
            k.nama_kos, k.foto_utama, k.kota,
            u.nama AS nama_penyewa, u.email AS email_penyewa, u.no_hp AS hp_penyewa
     FROM bookings b
     JOIN kos k      ON b.kos_id    = k.id
     JOIN users u    ON b.penyewa_id = u.id
     WHERE k.pemilik_id = ?
     ORDER BY
        FIELD(b.status, 'dibayar', 'menunggu_pembayaran', 'aktif', 'ditolak', 'selesai', 'dibatalkan'),
        b.created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $user['id']);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$bookings = [];
while ($row = mysqli_fetch_assoc($result)) $bookings[] = $row;

// Hitung booking aktif yang belum di-checkin (perlu perhatian pemilik)
$perlu_aksi = array_filter($bookings, fn($b) => $b['status'] === 'aktif');
$jml_perlu_aksi = count($perlu_aksi);

$label_status = [
    'menunggu_pembayaran' => ['⏳', 'Menunggu Bayar',    'menunggu_pembayaran'],
    'aktif'               => ['✅', 'Aktif',             'aktif'],
    'ditolak'             => ['❌', 'Ditolak',           'ditolak'],
    'selesai'             => ['🏁', 'Selesai',           'selesai'],
    'dibatalkan'          => ['🚫', 'Dibatalkan',        'dibatalkan'],
];

$extra_head    = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/transaction.css">';
$judul_halaman = "Booking Masuk — Dashboard Pemilik";
$css_tambahan  = "dashboard.css";

require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

<!-- Modal Tolak/Catatan (Bootstrap modal) -->
<div class="modal fade" id="modalAksi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;border:1.5px solid var(--color-border);">
            <div class="modal-header" style="border-color:var(--color-border);">
                <h5 class="modal-title" id="modal-judul" style="font-weight:800;">Konfirmasi Aksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-aksi" method="POST" action="<?= BASE_URL ?>/pages/pemilik/update_booking.php">
                    <input type="hidden" name="booking_id" id="input-booking-id">
                    <input type="hidden" name="aksi"       id="input-aksi">

                    <p id="modal-deskripsi" style="font-size:14px;color:var(--color-text-muted);margin-bottom:16px;"></p>

                    <div id="wrap-catatan" style="display:none;">
                        <label style="font-size:12px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">
                            Catatan untuk Penyewa
                        </label>
                        <textarea name="catatan_pemilik" id="input-catatan"
                                  style="width:100%;border:1.5px solid var(--color-border);border-radius:8px;padding:10px 12px;font-size:13px;resize:vertical;min-height:80px;font-family:var(--font-main);"
                                  placeholder="Tulis catatan atau alasan di sini..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-color:var(--color-border);">
                <button type="button" class="btn-action" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="btn-modal-submit" class="btn-kosta btn"
                        style="font-size:13px;padding:8px 20px;"
                        onclick="document.getElementById('form-aksi').submit();">
                    Konfirmasi
                </button>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div class="sidebar-profile">
            <div class="sidebar-avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
            <div class="sidebar-name"><?= htmlspecialchars($user['nama']) ?></div>
            <span class="sidebar-role-badge">Pemilik Kos</span>
        </div>
        <p class="sidebar-menu-label">Menu Utama</p>
        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/pages/pemilik/index.php" class="sidebar-link">
                <span class="link-icon">📊</span> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/pages/pemilik/tambah_kos.php" class="sidebar-link">
                <span class="link-icon">➕</span> Tambah Kos
            </a>
            <a href="<?= BASE_URL ?>/pages/pemilik/booking.php" class="sidebar-link aktif">
                <span class="link-icon">📋</span> Booking Masuk
                <?php if ($jml_perlu_aksi > 0): ?>
                    <span style="background:var(--color-accent);color:white;font-size:10px;font-weight:800;border-radius:20px;padding:1px 7px;margin-left:auto;">
                        <?= $jml_perlu_aksi ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/pages/pemilik/pesan.php" class="sidebar-link">
                <span class="link-icon">📩</span> Pesan Masuk
            </a>

        </nav>
        <p class="sidebar-menu-label">Akun</p>
        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/pages/logout.php" class="sidebar-link sidebar-link-logout">
                <span class="link-icon">🚪</span> Keluar
            </a>
        </nav>
    </aside>

    <main class="dashboard-content">
        <div class="dashboard-header">
            <div>
                <h1 class="dashboard-title">📋 Booking Masuk</h1>
                <p class="dashboard-subtitle">
                    <?= count($bookings) ?> total booking
                    <?php if ($jml_perlu_aksi > 0): ?>
                        — <span style="color:var(--color-accent);font-weight:700;"><?= $jml_perlu_aksi ?> booking aktif menunggu check-in</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty-state" style="padding:60px 20px;">
                <div class="empty-icon">📭</div>
                <h5>Belum Ada Booking</h5>
                <p>Belum ada yang memesan kos kamu. Pastikan kos sudah aktif dan terlihat di pencarian.</p>
            </div>

        <?php else: ?>
            <!-- Filter Status -->
            <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
                <button class="filter-tab aktif" onclick="filterStatus('semua',this)">Semua</button>
                <?php
                $cnt = array_count_values(array_column($bookings, 'status'));
                foreach ($label_status as $k => [$ico, $lbl, $cls]):
                    if (!isset($cnt[$k])) continue;
                ?>
                <button class="filter-tab" onclick="filterStatus('<?= $k ?>',this)">
                    <?= $ico ?> <?= $lbl ?> (<?= $cnt[$k] ?>)
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Daftar Booking -->
            <?php foreach ($bookings as $b):
                [$ico, $lbl, $cls] = $label_status[$b['status']] ?? ['❓', $b['status'], 'dibatalkan'];
                $harga_fmt  = 'Rp ' . number_format($b['total_harga'], 0, ',', '.');
                $tgl_masuk  = date('d M Y', strtotime($b['tanggal_masuk']));
                $tgl_keluar = date('d M Y', strtotime($b['tanggal_keluar']));
                $tgl_buat   = date('d M Y, H:i', strtotime($b['created_at']));
                $wa = !empty($b['hp_penyewa'])
                    ? 'https://wa.me/62' . ltrim($b['hp_penyewa'], '0')
                        . '?text=' . urlencode("Halo {$b['nama_penyewa']}, saya pemilik kos {$b['nama_kos']} di Kosta'. Tentang booking #{$b['id']}.")
                    : '';
            ?>
            <div class="riwayat-card" data-status="<?= $b['status'] ?>" style="margin-bottom:14px;">

                <!-- Header -->
                <div class="riwayat-header">
                    <div>
                        <div style="font-size:13px;font-weight:800;color:var(--color-text);">
                            <?= htmlspecialchars($b['nama_kos']) ?>
                            <span style="font-weight:400;font-size:12px;color:var(--color-text-muted);">
                                · #<?= $b['id'] ?> · <?= $tgl_buat ?>
                            </span>
                        </div>
                        <div style="font-size:13px;color:var(--color-text-muted);margin-top:4px;">
                            👤 <strong><?= htmlspecialchars($b['nama_penyewa']) ?></strong>
                            · <?= htmlspecialchars($b['email_penyewa']) ?>
                        </div>
                    </div>
                    <span class="status-badge <?= $cls ?>"><?= $ico ?> <?= $lbl ?></span>
                </div>

                <!-- Detail -->
                <div class="riwayat-body">
                    <div class="riwayat-info-col">
                        <div class="riwayat-info-label">Tanggal Masuk</div>
                        <div class="riwayat-info-value"><?= $tgl_masuk ?></div>
                    </div>
                    <div class="riwayat-info-col">
                        <div class="riwayat-info-label">Keluar</div>
                        <div class="riwayat-info-value"><?= $tgl_keluar ?></div>
                    </div>
                    <div class="riwayat-info-col">
                        <div class="riwayat-info-label">Durasi</div>
                        <div class="riwayat-info-value"><?= $b['durasi_bulan'] ?> bulan</div>
                    </div>
                    <div class="riwayat-info-col">
                        <div class="riwayat-info-label">Total</div>
                        <div class="riwayat-info-value" style="color:var(--color-accent);"><?= $harga_fmt ?></div>
                    </div>
                    <?php if (!empty($b['metode_pembayaran'])): ?>
                    <div class="riwayat-info-col">
                        <div class="riwayat-info-label">Metode</div>
                        <div class="riwayat-info-value"><?= ucwords(str_replace('_',' ',$b['metode_pembayaran'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Catatan penyewa -->
                <?php if (!empty($b['catatan_penyewa'])): ?>
                <div style="padding:10px 20px;background:var(--color-bg);border-top:1px solid var(--color-border);">
                    <p style="font-size:12px;color:var(--color-text-muted);margin:0;">
                        💬 <em>Catatan penyewa:</em> <?= htmlspecialchars($b['catatan_penyewa']) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Catatan pemilik (jika ada) -->
                <?php if (!empty($b['catatan_pemilik'])): ?>
                <div style="padding:10px 20px;background:rgba(197,0,0,0.03);border-top:1px solid var(--color-border);">
                    <p style="font-size:12px;color:var(--color-accent);margin:0;">
                        📝 <em>Catatanmu:</em> <?= htmlspecialchars($b['catatan_pemilik']) ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- FOOTER: Tombol Aksi Pemilik -->
                <div class="riwayat-footer">

                    <?php if ($b['status'] === 'aktif'): ?>
                        <!-- Tandai Selesai -->
                        <button type="button" class="btn-action"
                                style="font-size:12px;"
                                onclick="bukaModal(<?= $b['id'] ?>, 'selesai',
                                    'Tandai Sewa Selesai',
                                    'Masa sewa telah berakhir. Kamar akan kembali tersedia dan penyewa dapat memberi ulasan.',
                                    false)">
                            🏁 Tandai Selesai
                        </button>
                    <?php endif; ?>

                    <!-- Hubungi penyewa via WA -->
                    <?php if (!empty($wa)): ?>
                        <a href="<?= $wa ?>" target="_blank" class="btn-action" style="font-size:12px;">
                            💬 WA Penyewa
                        </a>
                    <?php endif; ?>

                    <!-- Info: pembayaran dikonfirmasi otomatis -->
                    <?php if ($b['status'] === 'menunggu_pembayaran'): ?>
                        <span style="font-size:11px;color:var(--color-text-muted);font-style:italic;">
                            ⏳ Menunggu penyewa menyelesaikan pembayaran
                        </span>
                    <?php endif; ?>

                </div><!-- /riwayat-footer -->
            </div><!-- /riwayat-card -->
            <?php endforeach; ?>

        <?php endif; ?>

        <?php mysqli_close($koneksi); ?>
    </main>
</div><!-- /dashboard-wrapper -->

<style>
.filter-tab {
    display:inline-block;padding:7px 14px;border-radius:20px;border:1.5px solid var(--color-border);
    background:var(--color-surface);font-size:12px;font-weight:700;cursor:pointer;
    color:var(--color-text-muted);transition:all .2s;font-family:var(--font-main);
}
.filter-tab.aktif,.filter-tab:hover{border-color:var(--color-accent);color:var(--color-accent);background:rgba(197,0,0,.05);}
</style>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>

<script>
// Filter kartu booking berdasarkan status
function filterStatus(status, btn) {
    document.querySelectorAll('.filter-tab').forEach(function(b){b.classList.remove('aktif');});
    btn.classList.add('aktif');
    document.querySelectorAll('.riwayat-card').forEach(function(card){
        card.style.display = (status === 'semua' || card.dataset.status === status) ? '' : 'none';
    });
}

// Buka modal konfirmasi aksi
function bukaModal(bookingId, aksi, judul, deskripsi, tampilCatatan) {
    document.getElementById('input-booking-id').value = bookingId;
    document.getElementById('input-aksi').value       = aksi;
    document.getElementById('modal-judul').textContent = judul;
    document.getElementById('modal-deskripsi').textContent = deskripsi;
    document.getElementById('wrap-catatan').style.display = tampilCatatan ? 'block' : 'none';
    document.getElementById('input-catatan').required = tampilCatatan;

    // Warna tombol submit sesuai aksi
    var btn = document.getElementById('btn-modal-submit');
    if (aksi === 'tolak') {
        btn.style.background = '#b91c1c';
        btn.style.borderColor = '#b91c1c';
    } else if (aksi === 'konfirmasi') {
        btn.style.background = '#15803d';
        btn.style.borderColor = '#15803d';
    } else {
        btn.style.background = '';
        btn.style.borderColor = '';
    }

    new bootstrap.Modal(document.getElementById('modalAksi')).show();
}
</script>

<?php require_once __DIR__ . '/../../components/scripts.php'; ?>
