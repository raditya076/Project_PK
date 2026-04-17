<?php
/**
 * FILE: pages/admin/index.php
 * FUNGSI: Dashboard utama admin — statistik platform Kosta'
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('admin');

// ── Ambil Statistik ──────────────────────────────────────────
$stats = [];

// Total user per role
$r = mysqli_query($koneksi,
    "SELECT role, COUNT(*) AS total, SUM(status='aktif') AS aktif
     FROM users GROUP BY role");
$user_stat = ['pencari'=>['total'=>0,'aktif'=>0],'pemilik'=>['total'=>0,'aktif'=>0],'admin'=>['total'=>0,'aktif'=>0]];
while ($row = mysqli_fetch_assoc($r)) {
    $user_stat[$row['role']] = ['total'=>(int)$row['total'],'aktif'=>(int)$row['aktif']];
}

// Total kos
$r = mysqli_query($koneksi, "SELECT COUNT(*) AS total, SUM(status='aktif') AS aktif, SUM(status='pending') AS pending FROM kos");
$kos_stat = mysqli_fetch_assoc($r);

// Total booking & revenue
$r = mysqli_query($koneksi,
    "SELECT COUNT(*) AS total,
            SUM(status='menunggu_pembayaran') AS menunggu,
            SUM(status='dibayar') AS dibayar,
            SUM(status='aktif') AS aktif,
            SUM(status='selesai') AS selesai,
            SUM(CASE WHEN status IN ('aktif','selesai') THEN total_harga ELSE 0 END) AS revenue
     FROM bookings");
$booking_stat = mysqli_fetch_assoc($r);

// Total reviews
$r = mysqli_query($koneksi, "SELECT COUNT(*) AS total, ROUND(AVG(rating),1) AS avg_rating FROM reviews");
$rev_stat = mysqli_fetch_assoc($r);

// ── Data Terbaru ─────────────────────────────────────────────
// 5 user terbaru
$q_users = mysqli_query($koneksi,
    "SELECT id, nama, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 5");

// 5 booking terbaru
$q_bookings = mysqli_query($koneksi,
    "SELECT b.id, b.status, b.total_harga, b.created_at,
            u.nama AS penyewa, k.nama_kos
     FROM bookings b
     JOIN users u ON b.penyewa_id = u.id
     JOIN kos k ON b.kos_id = k.id
     ORDER BY b.created_at DESC LIMIT 5");

$judul_halaman = "Dashboard Admin";
$css_tambahan  = "admin.css";
$body_class    = "admin-page";
require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';

// Helper format rupiah
function rp(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>

<div class="admin-wrapper">

    <!-- ═══ SIDEBAR ═══ -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- ═══ MAIN ═══ -->
    <main class="admin-main">
        <div class="admin-page-header">
            <h1 class="admin-page-title">⚙️ Dashboard Admin</h1>
            <p class="admin-page-subtitle">Selamat datang! Berikut ringkasan platform Kosta' hari ini.</p>
        </div>

        <?= get_flash() ?>

        <!-- ── Statistik Utama ── -->
        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-icon blue">👥</div>
                <div>
                    <div class="admin-stat-value"><?= $user_stat['pencari']['total'] + $user_stat['pemilik']['total'] ?></div>
                    <div class="admin-stat-label">Total Pengguna</div>
                    <div class="admin-stat-change up">🟢 <?= $user_stat['pencari']['aktif'] + $user_stat['pemilik']['aktif'] ?> aktif</div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon green">🏘️</div>
                <div>
                    <div class="admin-stat-value"><?= $kos_stat['total'] ?></div>
                    <div class="admin-stat-label">Total Listing Kos</div>
                    <div class="admin-stat-change <?= ($kos_stat['pending'] > 0) ? 'down' : 'up' ?>">
                        <?= $kos_stat['pending'] > 0 ? "⚠️ {$kos_stat['pending']} pending" : "✅ Semua aktif" ?>
                    </div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon orange">📅</div>
                <div>
                    <div class="admin-stat-value"><?= $booking_stat['total'] ?></div>
                    <div class="admin-stat-label">Total Booking</div>
                    <div class="admin-stat-change up">🟢 <?= $booking_stat['aktif'] ?> aktif sekarang</div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon red">💰</div>
                <div>
                    <div class="admin-stat-value" style="font-size:18px;"><?= rp((int)$booking_stat['revenue']) ?></div>
                    <div class="admin-stat-label">Total Transaksi</div>
                    <div class="admin-stat-change up">📊 <?= $booking_stat['selesai'] ?> booking selesai</div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon purple">⭐</div>
                <div>
                    <div class="admin-stat-value"><?= $rev_stat['avg_rating'] ?: '-' ?></div>
                    <div class="admin-stat-label">Rating Rata-rata</div>
                    <div class="admin-stat-change up">dari <?= $rev_stat['total'] ?> ulasan</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- ── User Terbaru ── -->
            <div class="col-lg-6">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div class="admin-card-title">👥 Pengguna Terbaru</div>
                        <a href="<?= BASE_URL ?>/pages/admin/users.php" class="btn-admin neutral">Lihat Semua</a>
                    </div>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($u = mysqli_fetch_assoc($q_users)): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($u['nama']) ?></div>
                                    <div style="font-size:11px;color:var(--color-text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td><span class="role-badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                                <td><span class="user-status <?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Booking Terbaru ── -->
            <div class="col-lg-6">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div class="admin-card-title">📅 Booking Terbaru</div>
                        <a href="<?= BASE_URL ?>/pages/admin/bookings.php" class="btn-admin neutral">Lihat Semua</a>
                    </div>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Penyewa</th>
                                <th>Kos</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($b = mysqli_fetch_assoc($q_bookings)): ?>
                            <tr>
                                <td style="font-weight:600;"><?= htmlspecialchars($b['penyewa']) ?></td>
                                <td style="font-size:12px;color:var(--color-text-muted);"><?= htmlspecialchars($b['nama_kos']) ?></td>
                                <td>
                                    <span class="status-badge <?= $b['status'] ?>">
                                        <?= ucwords(str_replace('_', ' ', $b['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Status Booking Summary ── -->
        <div class="admin-card" style="margin-top:8px;">
            <div class="admin-card-header">
                <div class="admin-card-title">📊 Ringkasan Status Booking</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:0;">
                <?php
                $statuses = [
                    'menunggu_pembayaran' => ['label'=>'Menunggu Bayar', 'count'=>$booking_stat['menunggu'], 'color'=>'#92660a', 'bg'=>'#FFF9E6'],
                    'dibayar'             => ['label'=>'Menunggu Konfirmasi', 'count'=>$booking_stat['dibayar'], 'color'=>'#1d4ed8', 'bg'=>'#EFF6FF'],
                    'aktif'               => ['label'=>'Aktif', 'count'=>$booking_stat['aktif'], 'color'=>'#15803d', 'bg'=>'#F0FFF4'],
                    'selesai'             => ['label'=>'Selesai', 'count'=>$booking_stat['selesai'], 'color'=>'#525252', 'bg'=>'#F5F5F5'],
                ];
                foreach ($statuses as $s): ?>
                <div style="padding:20px;text-align:center;border-right:1px solid var(--color-border);background:<?= $s['bg'] ?>;">
                    <div style="font-size:24px;font-weight:900;color:<?= $s['color'] ?>;"><?= $s['count'] ?></div>
                    <div style="font-size:11px;font-weight:600;color:<?= $s['color'] ?>;margin-top:4px;"><?= $s['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main><!-- /admin-main -->
</div>

<?php
mysqli_close($koneksi);
require_once __DIR__ . '/../../components/footer.php';
require_once __DIR__ . '/../../components/scripts.php';
?>
