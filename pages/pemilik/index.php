<?php
/**
 * ====================================================
 * FILE: pages/pemilik/index.php
 * FUNGSI: Dashboard utama untuk role 'Pemilik'.
 *         Menampilkan statistik dan daftar kos miliknya.
 *
 * PATH NOTE: File ini 2 level dalam (/pages/pemilik/)
 *            sehingga path ke config menggunakan ../../
 * ====================================================
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

// Middleware: hanya pemilik yang boleh masuk
wajib_role('pemilik');

// Ambil data user yang sedang login
$user = user_login();

// -------------------------------------------------------
// Query statistik untuk kartu di atas dashboard
// -------------------------------------------------------

// Total kos milik pemilik ini
$q_total = mysqli_prepare($koneksi,
    "SELECT COUNT(*) as total FROM kos WHERE pemilik_id = ?"
);
mysqli_stmt_bind_param($q_total, 'i', $user['id']);
mysqli_stmt_execute($q_total);
$total_kos = mysqli_fetch_assoc(mysqli_stmt_get_result($q_total))['total'];

// Kos yang aktif
$q_aktif = mysqli_prepare($koneksi,
    "SELECT COUNT(*) as total FROM kos WHERE pemilik_id = ? AND status = 'aktif'"
);
mysqli_stmt_bind_param($q_aktif, 'i', $user['id']);
mysqli_stmt_execute($q_aktif);
$kos_aktif = mysqli_fetch_assoc(mysqli_stmt_get_result($q_aktif))['total'];

// Total kamar tersedia (jumlah_kamar - kamar_terisi) dari semua kos
$q_kamar = mysqli_prepare($koneksi,
    "SELECT SUM(jumlah_kamar - kamar_terisi) as tersedia FROM kos WHERE pemilik_id = ? AND status='aktif'"
);
mysqli_stmt_bind_param($q_kamar, 'i', $user['id']);
mysqli_stmt_execute($q_kamar);
$kamar_tersedia = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kamar))['tersedia'] ?? 0;

// Total kamar terisi
$q_terisi = mysqli_prepare($koneksi,
    "SELECT SUM(kamar_terisi) as terisi FROM kos WHERE pemilik_id = ? AND status='aktif'"
);
mysqli_stmt_bind_param($q_terisi, 'i', $user['id']);
mysqli_stmt_execute($q_terisi);
$kamar_terisi = mysqli_fetch_assoc(mysqli_stmt_get_result($q_terisi))['terisi'] ?? 0;

// -------------------------------------------------------
// Query daftar semua kos milik pemilik ini
// -------------------------------------------------------
$q_daftar = mysqli_prepare($koneksi,
    "SELECT * FROM kos WHERE pemilik_id = ? ORDER BY created_at DESC"
);
mysqli_stmt_bind_param($q_daftar, 'i', $user['id']);
mysqli_stmt_execute($q_daftar);
$result_kos = mysqli_stmt_get_result($q_daftar);

// Pesan baru (belum dibaca)
$q_pesan = mysqli_prepare($koneksi,
    "SELECT COUNT(*) as total FROM pesan WHERE pemilik_id = ? AND status = 'baru'"
);
mysqli_stmt_bind_param($q_pesan, 'i', $user['id']);
mysqli_stmt_execute($q_pesan);
$pesan_baru = mysqli_fetch_assoc(mysqli_stmt_get_result($q_pesan))['total'];

$judul_halaman = "Dashboard Pemilik";
$css_tambahan  = "dashboard.css";

require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

<div class="dashboard-wrapper">

    <!-- ===== SIDEBAR ===== -->
    <aside class="dashboard-sidebar">
        <!-- Profil singkat -->
        <div class="sidebar-profile">
            <div class="sidebar-avatar">
                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
            </div>
            <div class="sidebar-name"><?= htmlspecialchars($user['nama']) ?></div>
            <span class="sidebar-role-badge">Pemilik Kos</span>
        </div>

        <!-- Menu utama -->
        <p class="sidebar-menu-label">Menu Utama</p>
        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/pages/pemilik/index.php"
               class="sidebar-link aktif">
                <span class="link-icon">📊</span> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/pages/pemilik/tambah_kos.php"
               class="sidebar-link">
                <span class="link-icon">➕</span> Tambah Kos
            </a>
            <a href="<?= BASE_URL ?>/pages/pemilik/booking.php"
               class="sidebar-link">
                <span class="link-icon">📋</span> Booking Masuk
            </a>
            <a href="<?= BASE_URL ?>/pages/pemilik/pesan.php"
               class="sidebar-link">
                <span class="link-icon">📩</span> Pesan Masuk
            </a>

        </nav>

        <!-- Akun -->
        <p class="sidebar-menu-label">Akun</p>
        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/pages/logout.php"
               class="sidebar-link sidebar-link-logout">
                <span class="link-icon">🚪</span> Keluar
            </a>
        </nav>
    </aside>

    <!-- ===== KONTEN UTAMA ===== -->
    <main class="dashboard-content">

        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1 class="dashboard-title">
                    Selamat datang, <?= htmlspecialchars(explode(' ', $user['nama'])[0]) ?>! 👋
                </h1>
                <p class="dashboard-subtitle">
                    Kelola kos kamu dan pantau ketersediaan kamar dari sini.
                </p>
            </div>
            <a href="<?= BASE_URL ?>/pages/pemilik/tambah_kos.php" class="btn-kosta btn">
                + Tambah Kos Baru
            </a>
        </div>

        <!-- Kartu Statistik -->
        <div class="stat-cards-row">
            <div class="stat-card">
                <div class="stat-card-icon merah">🏠</div>
                <div>
                    <div class="stat-card-value"><?= $total_kos ?></div>
                    <div class="stat-card-label">Total Kos</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon hijau">✅</div>
                <div>
                    <div class="stat-card-value"><?= $kos_aktif ?></div>
                    <div class="stat-card-label">Kos Aktif</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon biru">🛏️</div>
                <div>
                    <div class="stat-card-value"><?= $kamar_tersedia ?></div>
                    <div class="stat-card-label">Kamar Kosong</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon kuning">👥</div>
                <div>
                    <div class="stat-card-value"><?= $kamar_terisi ?></div>
                    <div class="stat-card-label">Kamar Terisi</div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/pages/pemilik/pesan.php" class="stat-card" style="text-decoration:none;cursor:pointer;">
                <div class="stat-card-icon" style="background:rgba(139,92,246,0.1);color:#7c3aed;">📩</div>
                <div>
                    <div class="stat-card-value" style="<?= $pesan_baru > 0 ? 'color:var(--color-accent);' : '' ?>">
                        <?= $pesan_baru ?>
                    </div>
                    <div class="stat-card-label">Pesan Baru</div>
                </div>
            </a>
        </div>

        <!-- Tabel Daftar Kos -->
        <div class="table-card">
            <div class="table-card-header">
                <h2 class="table-card-title">Daftar Kos Saya</h2>
                <a href="<?= BASE_URL ?>/pages/pemilik/tambah_kos.php"
                   class="btn-kosta btn" style="font-size:13px; padding:8px 18px;">
                    + Tambah
                </a>
            </div>

            <?php if (mysqli_num_rows($result_kos) > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="table-kosta">
                        <thead>
                            <tr>
                                <th>Kos</th>
                                <th>Tipe</th>
                                <th>Harga/Bulan</th>
                                <th>Kamar</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($kos = mysqli_fetch_assoc($result_kos)):
                            $kamar_sisa   = $kos['jumlah_kamar'] - $kos['kamar_terisi'];
                            $harga_format = 'Rp ' . number_format($kos['harga_per_bulan'], 0, ',', '.');
                        ?>
                            <tr>
                                <td>
                                    <div class="table-kos-name">
                                        <?= htmlspecialchars($kos['nama_kos']) ?>
                                    </div>
                                    <div class="table-kos-location">
                                        📍 <?= htmlspecialchars($kos['kota']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-kos <?= $kos['tipe'] ?>">
                                        <?= ucfirst($kos['tipe']) ?>
                                    </span>
                                </td>
                                <td style="font-weight:700; color:var(--color-accent);">
                                    <?= $harga_format ?>
                                </td>
                                <td>
                                    <span style="font-weight:700;"><?= $kamar_sisa ?></span>
                                    <span style="color:var(--color-text-muted); font-size:12px;">
                                        / <?= $kos['jumlah_kamar'] ?> kosong
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill <?= $kos['status'] ?>">
                                        <?= ucfirst($kos['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos['id'] ?>"
                                           class="btn-action" target="_blank" title="Lihat">
                                            👁️
                                        </a>
                                        <a href="<?= BASE_URL ?>/pages/pemilik/edit_kos.php?id=<?= $kos['id'] ?>"
                                           class="btn-action edit">
                                            ✏️ Edit
                                        </a>
                                        <form method="POST"
                                              action="<?= BASE_URL ?>/pages/pemilik/hapus_kos.php"
                                              onsubmit="return confirm('Yakin menghapus kos ini?');"
                                              style="display:inline;">
                                            <input type="hidden" name="id" value="<?= $kos['id'] ?>">
                                            <button type="submit" class="btn-action hapus">
                                                🗑️ Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <!-- Belum punya kos: tampilkan empty state -->
                <div class="empty-state" style="padding:60px 20px;">
                    <div class="empty-icon">🏚️</div>
                    <h5>Belum Ada Kos</h5>
                    <p>Kamu belum mendaftarkan kos apapun. Mulai sekarang!</p>
                    <a href="<?= BASE_URL ?>/pages/pemilik/tambah_kos.php"
                       class="btn-kosta btn mt-3">
                        + Daftarkan Kos Pertamamu
                    </a>
                </div>
            <?php endif; ?>

        </div><!-- /table-card -->

        <?php mysqli_close($koneksi); ?>

    </main><!-- /dashboard-content -->
</div><!-- /dashboard-wrapper -->

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
<?php require_once __DIR__ . '/../../components/scripts.php'; ?>
