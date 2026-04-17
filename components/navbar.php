<?php
/**
 * ====================================================
 * FILE: components/navbar.php
 * FUNGSI: Navbar responsif dengan dropdown profil user.
 *
 * PENTING (Struktur Bootstrap 5):
 *   - Menu navigasi (.navbar-nav) ada DALAM .collapse
 *     → ikut sembunyi/tampil saat hamburger diklik
 *   - Tombol profil (.dropdown) ada DI LUAR .collapse
 *     → SELALU tampil di semua ukuran layar
 *   - Ini mencegah bug dropdown tidak bisa diklik
 * ====================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$halaman_aktif = basename($_SERVER['PHP_SELF']);
$user_login    = sudah_login() ? user_login() : null;

// Navbar tidak ditampilkan untuk role admin dan pemilik
// Mereka sudah memiliki sidebar tersendiri sebagai navigasi utama
if ($user_login && in_array($user_login['role'], ['admin', 'pemilik'])) {
    return;
}
?>

<nav class="navbar navbar-expand-lg navbar-kosta">
    <div class="container">

        <!-- Logo — paling kiri -->
        <a class="navbar-brand me-3" href="<?= BASE_URL ?>/index.php">
            Kos<span>ta'</span>
        </a>

        <!-- Menu navigasi (dalam collapse) —  tampil di tengah desktop -->
        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= ($halaman_aktif === 'index.php') ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/index.php">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($halaman_aktif === 'cari.php') ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/pages/cari.php">Cari Kos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($halaman_aktif === 'tentang.php') ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/pages/tentang.php">Tentang</a>
                </li>
            </ul>
        </div><!-- /collapse -->

        <!-- Wrapper kanan: profil + hamburger (SELALU tampil) -->
        <div class="d-flex align-items-center gap-2 ms-auto">

            <!-- ===== DROPDOWN PROFIL (di luar collapse) ===== -->
            <?php if ($user_login): ?>
                <div class="dropdown">
                    <button class="navbar-user-btn dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            id="dropdown-user">
                        <span class="navbar-avatar">
                            <?= strtoupper(substr($user_login['nama'], 0, 1)) ?>
                        </span>
                        <span class="navbar-user-name d-none d-sm-inline">
                            <?= htmlspecialchars(explode(' ', $user_login['nama'])[0]) ?>
                        </span>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-kosta"
                        aria-labelledby="dropdown-user">
                        <!-- Info user -->
                        <li>
                            <div class="dropdown-header-kosta">
                                <span style="font-weight:700; font-size:13px;">
                                    <?= htmlspecialchars($user_login['nama']) ?>
                                </span><br>
                                <span style="font-size:11px; color:var(--color-text-muted);">
                                    <?= ucfirst($user_login['role']) ?>
                                </span>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>

                        <!-- Menu untuk pencari -->
                        <li>
                            <a class="dropdown-item-kosta" href="<?= BASE_URL ?>/pages/riwayat.php">
                                📋 Riwayat Booking
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item-kosta" href="<?= BASE_URL ?>/pages/favorit/index.php">
                                ❤️ Kos Favorit
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item-kosta" href="<?= BASE_URL ?>/pages/cari.php">
                                🔍 Cari Kos
                            </a>
                        </li>

                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item-kosta text-danger" href="<?= BASE_URL ?>/pages/logout.php">
                                🚪 Keluar
                            </a>
                        </li>
                    </ul>
                </div>

            <?php else: ?>
                <!-- Belum login -->
                <a href="<?= BASE_URL ?>/pages/login.php" class="btn-kosta-outline btn d-none d-sm-inline-flex">
                    Masuk
                </a>
                <a href="<?= BASE_URL ?>/pages/register.php" class="btn-kosta btn">
                    Daftar Gratis
                </a>
            <?php endif; ?>

            <!-- Hamburger (mobile) — untuk collapse menu navigasi -->
            <button class="navbar-toggler ms-1" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarMenu"
                    aria-controls="navbarMenu"
                    aria-expanded="false"
                    aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

        </div><!-- /d-flex ms-auto -->

    </div><!-- /container -->
</nav>

<!-- CSS inline spesifik navbar -->
<style>
.navbar-user-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--color-surface-alt);
    border: 1.5px solid var(--color-border);
    border-radius: 20px;
    padding: 5px 14px 5px 6px;
    cursor: pointer;
    font-family: var(--font-main);
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text);
    transition: all var(--transition);
}
.navbar-user-btn:hover {
    border-color: var(--color-accent);
    background: rgba(197,0,0,0.04);
}
.navbar-user-btn::after {
    font-size: 10px;
    margin-left: 2px;
}
.navbar-avatar {
    width: 28px; height: 28px;
    border-radius: 50%;
    background: var(--color-accent);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800;
    flex-shrink: 0;
}
.dropdown-menu-kosta {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    padding: 6px;
    min-width: 210px;
    background: var(--color-surface);
    /* z-index lebih tinggi dari navbar (1030) agar muncul di atas semua elemen */
    z-index: 1060 !important;
}
.dropdown-header-kosta {
    padding: 10px 12px;
    line-height: 1.4;
}
.dropdown-item-kosta {
    display: block;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text);
    text-decoration: none;
    transition: background var(--transition);
}
.dropdown-item-kosta:hover {
    background: var(--color-surface-alt);
    color: var(--color-text);
}
.dropdown-item-kosta.text-danger { color: #b91c1c !important; }
.dropdown-item-kosta.text-danger:hover { background: #FFF3F3; }
.dropdown-divider { border-color: var(--color-border); margin: 4px 0; }
</style>
