<?php
/**
 * FILE: pages/admin/reviews.php
 * FUNGSI: Manajemen ulasan — lihat & hapus review yang melanggar
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('admin');

// ── Handle Hapus ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = (int)($_POST['review_id'] ?? 0);
    if ($review_id > 0) {
        $stmt = mysqli_prepare($koneksi, "DELETE FROM reviews WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $review_id);
        mysqli_stmt_execute($stmt);
        set_flash('sukses', 'Ulasan berhasil dihapus.');
    }
    redirect(BASE_URL . '/pages/admin/reviews.php');
}

// ── Filter & Query ────────────────────────────────────────────
$filter_rating = (int)($_GET['rating'] ?? 0);
$cari          = trim($_GET['cari'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$where  = ['1=1']; $params = []; $types = '';
if ($filter_rating >= 1 && $filter_rating <= 5) {
    $where[] = 'r.rating = ?'; $params[] = $filter_rating; $types .= 'i';
}
if ($cari) {
    $where[] = '(u.nama LIKE ? OR k.nama_kos LIKE ? OR r.isi_ulasan LIKE ?)';
    $like = "%$cari%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
$where_sql = implode(' AND ', $where);

// Total
$stmt_cnt = mysqli_prepare($koneksi,
    "SELECT COUNT(*) FROM reviews r
     JOIN users u ON r.user_id = u.id
     JOIN kos k ON r.kos_id = k.id
     WHERE $where_sql");
if ($types) mysqli_stmt_bind_param($stmt_cnt, $types, ...$params);
mysqli_stmt_execute($stmt_cnt);
$total = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt_cnt))[0];
$total_pages = max(1, ceil($total / $per_page));

// Data
$stmt = mysqli_prepare($koneksi,
    "SELECT r.id, r.rating, r.judul, r.isi_ulasan, r.created_at,
            u.nama AS nama_reviewer, u.email AS email_reviewer,
            k.nama_kos, k.kota, k.id AS kos_id
     FROM reviews r
     JOIN users u ON r.user_id = u.id
     JOIN kos k ON r.kos_id = k.id
     WHERE $where_sql
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?");
$params[] = $per_page; $params[] = $offset; $types .= 'ii';
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$reviews = mysqli_stmt_get_result($stmt);

// Distribusi rating
$r_dist = mysqli_query($koneksi,
    "SELECT rating, COUNT(*) AS n FROM reviews GROUP BY rating ORDER BY rating DESC");
$dist = [];
while ($row = mysqli_fetch_assoc($r_dist)) $dist[$row['rating']] = (int)$row['n'];

$judul_halaman = "Manajemen Ulasan — Admin";
$css_tambahan  = "admin.css";
$body_class    = "admin-page";
require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-page-header">
            <h1 class="admin-page-title">⭐ Manajemen Ulasan</h1>
            <p class="admin-page-subtitle">Total <?= $total ?> ulasan ditemukan.</p>
        </div>

        <?= get_flash() ?>

        <!-- Distribusi Rating -->
        <div class="admin-card" style="margin-bottom:20px;">
            <div class="admin-card-header">
                <div class="admin-card-title">📊 Distribusi Rating</div>
            </div>
            <div style="padding:16px 20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <a href="<?= BASE_URL ?>/pages/admin/reviews.php"
                   class="btn-admin <?= !$filter_rating ? 'primary' : 'neutral' ?>">
                    Semua (<?= $total ?>)
                </a>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <a href="<?= BASE_URL ?>/pages/admin/reviews.php?rating=<?= $i ?>"
                       class="btn-admin <?= ($filter_rating === $i) ? 'primary' : 'neutral' ?>">
                        <?= str_repeat('⭐', $i) ?> (<?= $dist[$i] ?? 0 ?>)
                    </a>
                <?php endfor; ?>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">Daftar Ulasan</div>
                <form method="GET" class="admin-filter-bar">
                    <?php if ($filter_rating): ?>
                        <input type="hidden" name="rating" value="<?= $filter_rating ?>">
                    <?php endif; ?>
                    <input type="text" name="cari" class="admin-search"
                           placeholder="🔍 Reviewer / kos / isi ulasan..."
                           value="<?= htmlspecialchars($cari) ?>">
                    <button type="submit" class="btn-admin primary">Cari</button>
                    <?php if ($cari): ?>
                        <a href="<?= BASE_URL ?>/pages/admin/reviews.php<?= $filter_rating ? '?rating='.$filter_rating : '' ?>"
                           class="btn-admin neutral">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (mysqli_num_rows($reviews) === 0): ?>
                <div class="admin-empty">
                    <div class="admin-empty-icon">⭐</div>
                    <p>Tidak ada ulasan ditemukan.</p>
                </div>
            <?php else: ?>

            <?php while ($rev = mysqli_fetch_assoc($reviews)): ?>
                <div style="padding:18px 20px;border-bottom:1px solid var(--color-border);">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">

                        <div style="flex:1;min-width:240px;">
                            <!-- Rating bintang -->
                            <div style="font-size:15px;margin-bottom:6px;">
                                <?= str_repeat('⭐', $rev['rating']) ?>
                                <?= str_repeat('☆', 5 - $rev['rating']) ?>
                                <span style="font-size:12px;font-weight:700;color:var(--color-text-muted);margin-left:6px;">
                                    <?= $rev['rating'] ?>/5
                                </span>
                            </div>
                            <!-- Judul & isi -->
                            <?php if ($rev['judul']): ?>
                                <div style="font-weight:800;font-size:14px;margin-bottom:4px;">
                                    <?= htmlspecialchars($rev['judul']) ?>
                                </div>
                            <?php endif; ?>
                            <p style="font-size:13px;color:var(--color-text);margin:0 0 10px;line-height:1.6;">
                                "<?= htmlspecialchars($rev['isi_ulasan']) ?>"
                            </p>
                            <!-- Meta -->
                            <div style="display:flex;gap:16px;font-size:11px;color:var(--color-text-muted);flex-wrap:wrap;">
                                <span>
                                    👤 <strong><?= htmlspecialchars($rev['nama_reviewer']) ?></strong>
                                    (<?= htmlspecialchars($rev['email_reviewer']) ?>)
                                </span>
                                <span>
                                    🏠 <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $rev['kos_id'] ?>"
                                          target="_blank" style="color:var(--color-accent);">
                                        <?= htmlspecialchars($rev['nama_kos']) ?>
                                    </a>
                                    · <?= htmlspecialchars($rev['kota']) ?>
                                </span>
                                <span>📅 <?= date('d M Y H:i', strtotime($rev['created_at'])) ?></span>
                            </div>
                        </div>

                        <!-- Tombol hapus -->
                        <form method="POST"
                              onsubmit="return confirm('Hapus ulasan ini secara permanen?')">
                            <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                            <button type="submit" class="btn-admin danger">🗑 Hapus</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="admin-pagination">
                <?php
                $base_url = BASE_URL . '/pages/admin/reviews.php?' .
                    http_build_query(['cari'=>$cari,'rating'=>$filter_rating]);
                for ($p = 1; $p <= $total_pages; $p++):
                ?>
                    <a href="<?= $base_url ?>&page=<?= $p ?>"
                       class="admin-page-btn <?= ($p === $page) ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <span style="font-size:12px;color:var(--color-text-muted);margin-left:8px;">
                    <?= $total ?> total ulasan
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
