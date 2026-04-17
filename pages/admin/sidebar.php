<?php
/**
 * FILE: pages/admin/sidebar.php
 * FUNGSI: Sidebar navigasi panel admin
 */
$admin_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div style="padding:12px 20px 20px;">
        <div style="font-size:18px;font-weight:900;color:#fff;letter-spacing:-0.5px;">
            ⚙️ Admin<span style="color:var(--color-accent);">Panel</span>
        </div>
        <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:2px;">Kosta' Management</div>
    </div>
    <hr class="admin-nav-divider">

    <div class="admin-sidebar-title">Utama</div>
    <a href="<?= BASE_URL ?>/pages/admin/index.php"
       class="admin-nav-link <?= ($admin_page === 'index.php') ? 'active' : '' ?>">
        <span class="nav-icon">📊</span> Dashboard
    </a>

    <hr class="admin-nav-divider">
    <div class="admin-sidebar-title">Manajemen</div>

    <a href="<?= BASE_URL ?>/pages/admin/users.php"
       class="admin-nav-link <?= ($admin_page === 'users.php') ? 'active' : '' ?>">
        <span class="nav-icon">👥</span> Pengguna
    </a>
    <a href="<?= BASE_URL ?>/pages/admin/kos.php"
       class="admin-nav-link <?= ($admin_page === 'kos.php') ? 'active' : '' ?>">
        <span class="nav-icon">🏘️</span> Listing Kos
    </a>
    <a href="<?= BASE_URL ?>/pages/admin/bookings.php"
       class="admin-nav-link <?= ($admin_page === 'bookings.php') ? 'active' : '' ?>">
        <span class="nav-icon">📅</span> Semua Booking
    </a>
    <a href="<?= BASE_URL ?>/pages/admin/disbursement.php"
       class="admin-nav-link <?= ($admin_page === 'disbursement.php') ? 'active' : '' ?>">
        <span class="nav-icon">💸</span> Disbursement
    </a>
    <a href="<?= BASE_URL ?>/pages/admin/reviews.php"
       class="admin-nav-link <?= ($admin_page === 'reviews.php') ? 'active' : '' ?>">
        <span class="nav-icon">⭐</span> Ulasan
    </a>

    <hr class="admin-nav-divider">
    <div class="admin-sidebar-title">Akun</div>
    <a href="<?= BASE_URL ?>/pages/logout.php" class="admin-nav-link"
       style="color:rgba(252,165,165,0.8);"
       onclick="return confirm('Yakin ingin keluar?')">
        <span class="nav-icon">🚪</span> Keluar
    </a>
</aside>
