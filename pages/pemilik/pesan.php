<?php
/**
 * ====================================================
 * FILE: pages/pemilik/pesan.php
 * FUNGSI: Dashboard pemilik — tampilkan semua pesan
 *         masuk dari calon penghuni.
 * ====================================================
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('pemilik');
$user = user_login();

// Tandai pesan sebagai 'dibaca' jika diklik
$id_baca = (int)($_GET['baca'] ?? 0);
if ($id_baca > 0) {
    $stmt_baca = mysqli_prepare($koneksi,
        "UPDATE pesan SET status = 'dibaca'
         WHERE id_pesan = ? AND pemilik_id = ?"
    );
    mysqli_stmt_bind_param($stmt_baca, 'ii', $id_baca, $user['id']);
    mysqli_stmt_execute($stmt_baca);
}

// Ambil semua pesan untuk pemilik ini, urutkan: baru dulu
$stmt = mysqli_prepare($koneksi,
    "SELECT p.*, k.nama_kos
     FROM pesan p
     JOIN kos k ON p.kos_id = k.id
     WHERE p.pemilik_id = ?
     ORDER BY p.status = 'baru' DESC, p.created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $user['id']);
mysqli_stmt_execute($stmt);
$result        = mysqli_stmt_get_result($stmt);
$jumlah_pesan  = mysqli_num_rows($result);

// Hitung pesan belum dibaca
$stmt_baru = mysqli_prepare($koneksi,
    "SELECT COUNT(*) as total FROM pesan WHERE pemilik_id = ? AND status = 'baru'"
);
mysqli_stmt_bind_param($stmt_baru, 'i', $user['id']);
mysqli_stmt_execute($stmt_baru);
$jumlah_baru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_baru))['total'];

$judul_halaman = "Pesan Masuk";
$css_tambahan  = "dashboard.css";

require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

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
            <a href="<?= BASE_URL ?>/pages/pemilik/pesan.php" class="sidebar-link aktif">
                <span class="link-icon">📩</span> Pesan Masuk
                <?php if ($jumlah_baru > 0): ?>
                    <span style="background:var(--color-accent);color:white;font-size:10px;font-weight:800;border-radius:20px;padding:1px 7px;margin-left:auto;">
                        <?= $jumlah_baru ?>
                    </span>
                <?php endif; ?>
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
                <h1 class="dashboard-title">📩 Pesan Masuk</h1>
                <p class="dashboard-subtitle">
                    <?= $jumlah_pesan ?> total pesan —
                    <span style="color:var(--color-accent); font-weight:700;">
                        <?= $jumlah_baru ?> belum dibaca
                    </span>
                </p>
            </div>
        </div>

        <?php if ($jumlah_pesan > 0): ?>
        <div class="table-card">
            <?php while ($pesan = mysqli_fetch_assoc($result)):
                $is_baru  = $pesan['status'] === 'baru';
                $tgl      = date('d M Y, H:i', strtotime($pesan['created_at']));
                $wa_link  = !empty($pesan['no_hp_pengirim'])
                    ? 'https://wa.me/62' . ltrim($pesan['no_hp_pengirim'], '0')
                      . '?text=' . urlencode("Halo {$pesan['nama_pengirim']}, saya pemilik kos {$pesan['nama_kos']}. Merespons pertanyaan Anda di Kosta'.")
                    : '';
            ?>
            <!-- Setiap pesan ditampilkan sebagai card -->
            <div style="padding:20px 22px; border-bottom:1px solid var(--color-border);
                        <?= $is_baru ? 'background:rgba(197,0,0,0.03);' : '' ?>">

                <!-- Header pesan -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <!-- Avatar inisial pengirim -->
                        <div style="width:40px;height:40px;border-radius:50%;background:var(--color-surface-alt);border:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:var(--color-text-muted);flex-shrink:0;">
                            <?= strtoupper(substr($pesan['nama_pengirim'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-size:14px;font-weight:700;color:var(--color-text);">
                                <?= htmlspecialchars($pesan['nama_pengirim']) ?>
                                <?php if ($is_baru): ?>
                                    <span style="display:inline-block;background:var(--color-accent);color:white;font-size:10px;font-weight:800;border-radius:4px;padding:1px 6px;margin-left:6px;vertical-align:middle;">
                                        BARU
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:12px;color:var(--color-text-muted);">
                                <?= htmlspecialchars($pesan['email_pengirim']) ?>
                                <?php if (!empty($pesan['no_hp_pengirim'])): ?>
                                    · <?= htmlspecialchars($pesan['no_hp_pengirim']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div style="text-align:right; flex-shrink:0;">
                        <div style="font-size:12px; font-weight:600; color:var(--color-text-muted);">
                            <?= $tgl ?>
                        </div>
                        <div style="font-size:11px; color:var(--color-accent); margin-top:2px;">
                            Tentang: <?= htmlspecialchars($pesan['nama_kos']) ?>
                        </div>
                    </div>
                </div>

                <!-- Isi pesan -->
                <div style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:8px; padding:14px 16px; font-size:13px; line-height:1.7; color:var(--color-text); margin-bottom:14px;">
                    <?= nl2br(htmlspecialchars($pesan['isi_pesan'])) ?>
                </div>

                <!-- Tombol aksi -->
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <?php if (!empty($wa_link)): ?>
                        <a href="<?= $wa_link ?>" target="_blank"
                           class="btn-action edit"
                           style="font-size:12px; padding:6px 14px; border-color:#25D366; color:#15803d; background:#F0FFF4;">
                            💬 Balas via WhatsApp
                        </a>
                    <?php endif; ?>
                    <a href="mailto:<?= htmlspecialchars($pesan['email_pengirim']) ?>?subject=Re: Kos <?= urlencode($pesan['nama_kos']) ?>"
                       class="btn-action edit" style="font-size:12px; padding:6px 14px;">
                        ✉️ Balas via Email
                    </a>
                    <?php if ($is_baru): ?>
                        <a href="<?= BASE_URL ?>/pages/pemilik/pesan.php?baca=<?= $pesan['id_pesan'] ?>"
                           class="btn-action" style="font-size:12px; padding:6px 14px; border-color:var(--color-border); color:var(--color-text-muted);">
                            ✓ Tandai Dibaca
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <?php else: ?>
        <div class="empty-state" style="padding:60px 20px;">
            <div class="empty-icon">📭</div>
            <h5>Belum Ada Pesan</h5>
            <p>Ketika calon penghuni mengirim pertanyaan melalui halaman kos kamu, pesannya akan muncul di sini.</p>
        </div>
        <?php endif; ?>

        <?php mysqli_close($koneksi); ?>
    </main>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
<?php require_once __DIR__ . '/../../components/scripts.php'; ?>
