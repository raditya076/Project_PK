<?php
/**
 * FILE: pages/admin/users.php
 * FUNGSI: Manajemen semua pengguna platform Kosta'
 *         - Lihat daftar user (filter role/status)
 *         - Aktifkan / Nonaktifkan akun
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('admin');

// ── Handle Aksi (POST) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi    = $_POST['aksi']    ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id > 0 && in_array($aksi, ['aktifkan', 'nonaktifkan'])) {
        $status_baru = ($aksi === 'aktifkan') ? 'aktif' : 'nonaktif';

        // Tidak boleh nonaktifkan diri sendiri
        if ($user_id === (int)$_SESSION['user_id']) {
            set_flash('error', 'Kamu tidak bisa menonaktifkan akun sendiri.');
        } else {
            $stmt = mysqli_prepare($koneksi,
                "UPDATE users SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $status_baru, $user_id);
            mysqli_stmt_execute($stmt);

            $label = ($aksi === 'aktifkan') ? 'diaktifkan' : 'dinonaktifkan';
            set_flash('sukses', "Akun pengguna berhasil $label.");
        }
    }
    redirect(BASE_URL . '/pages/admin/users.php');
}

// ── Filter & Query ────────────────────────────────────────────
$filter_role   = $_GET['role']   ?? '';
$filter_status = $_GET['status'] ?? '';
$cari          = trim($_GET['cari'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];
$types  = '';

if ($filter_role && in_array($filter_role, ['pencari','pemilik','admin'])) {
    $where[] = 'role = ?'; $params[] = $filter_role; $types .= 's';
}
if ($filter_status && in_array($filter_status, ['aktif','nonaktif'])) {
    $where[] = 'status = ?'; $params[] = $filter_status; $types .= 's';
}
if ($cari) {
    $where[] = '(nama LIKE ? OR email LIKE ?)';
    $like = "%$cari%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}
$where_sql = implode(' AND ', $where);

// Total
$stmt_cnt = mysqli_prepare($koneksi, "SELECT COUNT(*) FROM users WHERE $where_sql");
if ($types) mysqli_stmt_bind_param($stmt_cnt, $types, ...$params);
mysqli_stmt_execute($stmt_cnt);
$total = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt_cnt))[0];
$total_pages = max(1, ceil($total / $per_page));

// Data
$stmt = mysqli_prepare($koneksi,
    "SELECT id, nama, email, role, no_hp, status, created_at
     FROM users WHERE $where_sql
     ORDER BY created_at DESC
     LIMIT ? OFFSET ?");
$params[] = $per_page; $params[] = $offset; $types .= 'ii';
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$users = mysqli_stmt_get_result($stmt);

$judul_halaman = "Manajemen Pengguna — Admin";
$css_tambahan  = "admin.css";
$body_class    = "admin-page";
require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-page-header">
            <h1 class="admin-page-title">👥 Manajemen Pengguna</h1>
            <p class="admin-page-subtitle">Total <?= $total ?> pengguna terdaftar.</p>
        </div>

        <?= get_flash() ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">Daftar Pengguna</div>
                <!-- Filter -->
                <form method="GET" class="admin-filter-bar">
                    <input type="text" name="cari" class="admin-search"
                           placeholder="🔍 Cari nama/email..." value="<?= htmlspecialchars($cari) ?>">
                    <select name="role" class="admin-select" onchange="this.form.submit()">
                        <option value="">Semua Role</option>
                        <option value="pencari"  <?= $filter_role==='pencari'  ? 'selected' : '' ?>>Pencari</option>
                        <option value="pemilik"  <?= $filter_role==='pemilik'  ? 'selected' : '' ?>>Pemilik</option>
                        <option value="admin"    <?= $filter_role==='admin'    ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <select name="status" class="admin-select" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="aktif"    <?= $filter_status==='aktif'    ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $filter_status==='nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <button type="submit" class="btn-admin primary">Cari</button>
                    <?php if ($cari || $filter_role || $filter_status): ?>
                        <a href="<?= BASE_URL ?>/pages/admin/users.php" class="btn-admin neutral">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (mysqli_num_rows($users) === 0): ?>
                <div class="admin-empty">
                    <div class="admin-empty-icon">👥</div>
                    <p>Tidak ada pengguna ditemukan.</p>
                </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Pengguna</th>
                            <th>No. HP</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $no = $offset + 1; while ($u = mysqli_fetch_assoc($users)): ?>
                        <tr>
                            <td style="color:var(--color-text-muted);font-size:12px;"><?= $no++ ?></td>
                            <td>
                                <div style="font-weight:700;"><?= htmlspecialchars($u['nama']) ?></div>
                                <div style="font-size:11px;color:var(--color-text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td style="font-size:12px;"><?= $u['no_hp'] ? htmlspecialchars($u['no_hp']) : '-' ?></td>
                            <td><span class="role-badge <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><span class="user-status <?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                            <td style="font-size:11px;color:var(--color-text-muted);">
                                <?= date('d M Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Yakin mengubah status akun ini?')">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <?php if ($u['status'] === 'aktif'): ?>
                                        <input type="hidden" name="aksi" value="nonaktifkan">
                                        <button type="submit" class="btn-admin danger">🔒 Nonaktifkan</button>
                                    <?php else: ?>
                                        <input type="hidden" name="aksi" value="aktifkan">
                                        <button type="submit" class="btn-admin success">✅ Aktifkan</button>
                                    <?php endif; ?>
                                </form>
                                <?php else: ?>
                                    <span style="font-size:11px;color:var(--color-text-muted);">— Akun kamu</span>
                                <?php endif; ?>
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
                $base_url = BASE_URL . '/pages/admin/users.php?' .
                    http_build_query(['cari'=>$cari,'role'=>$filter_role,'status'=>$filter_status]);
                for ($p = 1; $p <= $total_pages; $p++):
                ?>
                    <a href="<?= $base_url ?>&page=<?= $p ?>"
                       class="admin-page-btn <?= ($p === $page) ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <span style="font-size:12px;color:var(--color-text-muted);margin-left:8px;">
                    <?= $total ?> total pengguna
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
