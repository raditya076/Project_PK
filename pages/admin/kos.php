<?php
/**
 * FILE: pages/admin/kos.php
 * FUNGSI: Manajemen listing kos (approve, nonaktifkan, hapus)
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('admin');

// ── Handle Aksi ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi   = $_POST['aksi']   ?? '';
    $kos_id = (int)($_POST['kos_id'] ?? 0);

    if ($kos_id > 0 && in_array($aksi, ['aktifkan', 'nonaktifkan', 'hapus'])) {
        if ($aksi === 'hapus') {
            $stmt = mysqli_prepare($koneksi, "DELETE FROM kos WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $kos_id);
            mysqli_stmt_execute($stmt);
            set_flash('sukses', "Listing kos berhasil dihapus.");
        } else {
            $status_baru = ($aksi === 'aktifkan') ? 'aktif' : 'nonaktif';
            $stmt = mysqli_prepare($koneksi, "UPDATE kos SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $status_baru, $kos_id);
            mysqli_stmt_execute($stmt);
            set_flash('sukses', "Status kos berhasil diubah ke '$status_baru'.");
        }
    }
    redirect(BASE_URL . '/pages/admin/kos.php');
}

// ── Filter & Query ────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$filter_kota   = $_GET['kota']   ?? '';
$cari          = trim($_GET['cari'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

$where  = ['1=1']; $params = []; $types = '';
if ($filter_status && in_array($filter_status, ['aktif','nonaktif','pending'])) {
    $where[] = 'k.status = ?'; $params[] = $filter_status; $types .= 's';
}
if ($filter_kota) {
    $where[] = 'k.kota = ?'; $params[] = $filter_kota; $types .= 's';
}
if ($cari) {
    $where[] = '(k.nama_kos LIKE ? OR u.nama LIKE ?)';
    $like = "%$cari%"; $params[] = $like; $params[] = $like; $types .= 'ss';
}
$where_sql = implode(' AND ', $where);

// Daftar kota untuk filter
$kota_list = mysqli_query($koneksi, "SELECT DISTINCT kota FROM kos ORDER BY kota");

// Total
$stmt_cnt = mysqli_prepare($koneksi,
    "SELECT COUNT(*) FROM kos k JOIN users u ON k.pemilik_id=u.id WHERE $where_sql");
if ($types) mysqli_stmt_bind_param($stmt_cnt, $types, ...$params);
mysqli_stmt_execute($stmt_cnt);
$total = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt_cnt))[0];
$total_pages = max(1, ceil($total / $per_page));

// Data
$stmt = mysqli_prepare($koneksi,
    "SELECT k.id, k.nama_kos, k.kota, k.tipe, k.harga_per_bulan,
            k.jumlah_kamar, k.kamar_terisi, k.status, k.created_at,
            u.nama AS nama_pemilik
     FROM kos k JOIN users u ON k.pemilik_id = u.id
     WHERE $where_sql ORDER BY k.created_at DESC LIMIT ? OFFSET ?");
$params[] = $per_page; $params[] = $offset; $types .= 'ii';
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$kos_list = mysqli_stmt_get_result($stmt);

$judul_halaman = "Manajemen Kos — Admin";
$css_tambahan  = "admin.css";
$body_class    = "admin-page";
require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-page-header">
            <h1 class="admin-page-title">🏘️ Manajemen Listing Kos</h1>
            <p class="admin-page-subtitle">Total <?= $total ?> listing kos ditemukan.</p>
        </div>

        <?= get_flash() ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">Daftar Kos</div>
                <form method="GET" class="admin-filter-bar">
                    <input type="text" name="cari" class="admin-search"
                           placeholder="🔍 Nama kos / pemilik..." value="<?= htmlspecialchars($cari) ?>">
                    <select name="kota" class="admin-select" onchange="this.form.submit()">
                        <option value="">Semua Kota</option>
                        <?php while ($k = mysqli_fetch_assoc($kota_list)): ?>
                        <option value="<?= htmlspecialchars($k['kota']) ?>"
                                <?= $filter_kota === $k['kota'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['kota']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                    <select name="status" class="admin-select" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="aktif"    <?= $filter_status==='aktif'    ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $filter_status==='nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        <option value="pending"  <?= $filter_status==='pending'  ? 'selected' : '' ?>>Pending</option>
                    </select>
                    <button type="submit" class="btn-admin primary">Cari</button>
                    <?php if ($cari || $filter_kota || $filter_status): ?>
                        <a href="<?= BASE_URL ?>/pages/admin/kos.php" class="btn-admin neutral">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (mysqli_num_rows($kos_list) === 0): ?>
                <div class="admin-empty">
                    <div class="admin-empty-icon">🏘️</div>
                    <p>Tidak ada listing kos ditemukan.</p>
                </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Kos</th>
                            <th>Pemilik</th>
                            <th>Kota</th>
                            <th>Harga</th>
                            <th>Kamar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $no = $offset + 1; while ($k = mysqli_fetch_assoc($kos_list)): ?>
                        <tr>
                            <td style="color:var(--color-text-muted);font-size:12px;"><?= $no++ ?></td>
                            <td>
                                <div style="font-weight:700;">
                                    <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $k['id'] ?>"
                                       target="_blank" style="color:var(--color-text);text-decoration:none;">
                                        <?= htmlspecialchars($k['nama_kos']) ?> 🔗
                                    </a>
                                </div>
                                <span class="badge-kos <?= $k['tipe'] ?>"><?= ucfirst($k['tipe']) ?></span>
                            </td>
                            <td style="font-size:12px;"><?= htmlspecialchars($k['nama_pemilik']) ?></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($k['kota']) ?></td>
                            <td style="font-size:12px;font-weight:700;color:var(--color-accent);">
                                Rp <?= number_format($k['harga_per_bulan'], 0, ',', '.') ?>
                            </td>
                            <td style="font-size:12px;text-align:center;">
                                <?= $k['kamar_terisi'] ?>/<?= $k['jumlah_kamar'] ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $k['status'] ?> kos-status">
                                    <?= ucfirst($k['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="kos_id" value="<?= $k['id'] ?>">
                                        <?php if ($k['status'] !== 'aktif'): ?>
                                            <input type="hidden" name="aksi" value="aktifkan">
                                            <button type="submit" class="btn-admin success">✅ Aktifkan</button>
                                        <?php else: ?>
                                            <input type="hidden" name="aksi" value="nonaktifkan">
                                            <button type="submit" class="btn-admin neutral">⏸ Nonaktifkan</button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Hapus listing ini? Semua booking terkait juga akan terhapus!')">
                                        <input type="hidden" name="kos_id" value="<?= $k['id'] ?>">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <button type="submit" class="btn-admin danger">🗑 Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="admin-pagination">
                <?php
                $base_url = BASE_URL . '/pages/admin/kos.php?' .
                    http_build_query(['cari'=>$cari,'kota'=>$filter_kota,'status'=>$filter_status]);
                for ($p = 1; $p <= $total_pages; $p++):
                ?>
                    <a href="<?= $base_url ?>&page=<?= $p ?>"
                       class="admin-page-btn <?= ($p === $page) ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
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
