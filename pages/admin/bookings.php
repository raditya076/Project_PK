<?php
/**
 * FILE: pages/admin/bookings.php
 * FUNGSI: Lihat semua booking di seluruh platform
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('admin');

// ── Filter & Query ────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$cari          = trim($_GET['cari'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$where  = ['1=1']; $params = []; $types = '';

$valid_status = ['menunggu_pembayaran','aktif','ditolak','selesai','dibatalkan'];
if ($filter_status && in_array($filter_status, $valid_status)) {
    $where[] = 'b.status = ?'; $params[] = $filter_status; $types .= 's';
}
if ($cari) {
    $where[] = '(u.nama LIKE ? OR k.nama_kos LIKE ?)';
    $like = "%$cari%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}
$where_sql = implode(' AND ', $where);

// Total
$stmt_cnt = mysqli_prepare($koneksi,
    "SELECT COUNT(*) FROM bookings b
     JOIN users u ON b.penyewa_id = u.id
     JOIN kos k ON b.kos_id = k.id
     WHERE $where_sql");
if ($types) mysqli_stmt_bind_param($stmt_cnt, $types, ...$params);
mysqli_stmt_execute($stmt_cnt);
$total = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt_cnt))[0];
$total_pages = max(1, ceil($total / $per_page));

// Data
$stmt = mysqli_prepare($koneksi,
    "SELECT b.id, b.status, b.total_harga, b.durasi_bulan,
            b.tanggal_masuk, b.tanggal_keluar, b.tanggal_bayar,
            b.metode_pembayaran, b.created_at,
            u.nama AS nama_penyewa, u.email AS email_penyewa,
            k.nama_kos, k.kota,
            p.nama AS nama_pemilik
     FROM bookings b
     JOIN users u ON b.penyewa_id = u.id
     JOIN kos k ON b.kos_id = k.id
     JOIN users p ON k.pemilik_id = p.id
     WHERE $where_sql
     ORDER BY b.created_at DESC
     LIMIT ? OFFSET ?");
$params[] = $per_page; $params[] = $offset; $types .= 'ii';
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$bookings = mysqli_stmt_get_result($stmt);

// Summary per status
$r_sum = mysqli_query($koneksi,
    "SELECT status, COUNT(*) AS n, SUM(total_harga) AS total
     FROM bookings GROUP BY status");
$sum = [];
while ($row = mysqli_fetch_assoc($r_sum)) $sum[$row['status']] = $row;

$judul_halaman = "Semua Booking — Admin";
$css_tambahan  = "admin.css";
$body_class    = "admin-page";
require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';

$status_label = [
    'menunggu_pembayaran' => 'Menunggu Bayar',
    'aktif'               => 'Aktif',
    'ditolak'             => 'Ditolak',
    'selesai'             => 'Selesai',
    'dibatalkan'          => 'Dibatalkan',
];
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-page-header">
            <h1 class="admin-page-title">📅 Semua Booking</h1>
            <p class="admin-page-subtitle">Total <?= $total ?> booking ditemukan.</p>
        </div>

        <?= get_flash() ?>

        <!-- Status Summary Chips -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
            <a href="<?= BASE_URL ?>/pages/admin/bookings.php"
               class="btn-admin <?= !$filter_status ? 'primary' : 'neutral' ?>">
                Semua (<?= array_sum(array_column($sum, 'n')) ?>)
            </a>
            <?php foreach ($status_label as $s => $lbl): ?>
                <?php $n = $sum[$s]['n'] ?? 0; ?>
                <a href="<?= BASE_URL ?>/pages/admin/bookings.php?status=<?= $s ?>"
                   class="btn-admin <?= ($filter_status === $s) ? 'primary' : 'neutral' ?>">
                    <?= $lbl ?> (<?= $n ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">Daftar Booking</div>
                <form method="GET" class="admin-filter-bar">
                    <?php if ($filter_status): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                    <?php endif; ?>
                    <input type="text" name="cari" class="admin-search"
                           placeholder="🔍 Penyewa / nama kos..." value="<?= htmlspecialchars($cari) ?>">
                    <button type="submit" class="btn-admin primary">Cari</button>
                    <?php if ($cari): ?>
                        <a href="<?= BASE_URL ?>/pages/admin/bookings.php<?= $filter_status ? '?status='.$filter_status : '' ?>"
                           class="btn-admin neutral">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (mysqli_num_rows($bookings) === 0): ?>
                <div class="admin-empty">
                    <div class="admin-empty-icon">📅</div>
                    <p>Tidak ada booking ditemukan.</p>
                </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Penyewa</th>
                            <th>Kos / Pemilik</th>
                            <th>Periode</th>
                            <th>Total</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($b = mysqli_fetch_assoc($bookings)): ?>
                        <tr>
                            <td style="font-size:12px;color:var(--color-text-muted);">#<?= $b['id'] ?></td>
                            <td>
                                <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($b['nama_penyewa']) ?></div>
                                <div style="font-size:11px;color:var(--color-text-muted);"><?= htmlspecialchars($b['email_penyewa']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($b['nama_kos']) ?></div>
                                <div style="font-size:11px;color:var(--color-text-muted);">
                                    <?= htmlspecialchars($b['kota']) ?> · by <?= htmlspecialchars($b['nama_pemilik']) ?>
                                </div>
                            </td>
                            <td style="font-size:12px;">
                                <?= date('d M Y', strtotime($b['tanggal_masuk'])) ?><br>
                                <span style="color:var(--color-text-muted);"><?= $b['durasi_bulan'] ?> bln</span>
                            </td>
                            <td style="font-weight:700;font-size:13px;color:var(--color-accent);">
                                Rp <?= number_format($b['total_harga'], 0, ',', '.') ?>
                            </td>
                            <td style="font-size:11px;color:var(--color-text-muted);">
                                <?= $b['metode_pembayaran']
                                    ? ucwords(str_replace('_', ' ', $b['metode_pembayaran']))
                                    : '—' ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $b['status'] ?>">
                                    <?= $status_label[$b['status']] ?? $b['status'] ?>
                                </span>
                            </td>
                            <td style="font-size:11px;color:var(--color-text-muted);">
                                <?= date('d M Y', strtotime($b['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="admin-pagination">
                <?php
                $base_url = BASE_URL . '/pages/admin/bookings.php?' .
                    http_build_query(['cari'=>$cari,'status'=>$filter_status]);
                for ($p = 1; $p <= $total_pages; $p++):
                ?>
                    <a href="<?= $base_url ?>&page=<?= $p ?>"
                       class="admin-page-btn <?= ($p === $page) ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <span style="font-size:12px;color:var(--color-text-muted);margin-left:8px;">
                    <?= $total ?> total booking
                </span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php
mysqli_close($koneksi);
require_once __DIR__ . '/../../components/footer.php';
require_once __DIR__ . '/../../components/scripts.php';
?>
