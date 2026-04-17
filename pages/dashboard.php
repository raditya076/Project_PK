<?php
/**
 * ====================================================
 * FILE: pages/dashboard.php
 * FUNGSI: Router dashboard — mengarahkan user ke
 *         halaman dashboard yang sesuai role-nya.
 *
 * Analoginya: ini seperti resepsionis hotel.
 * Ketika tamu datang, resepsionis melihat kartu tamu
 * dan mengantar ke lantai yang benar.
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

// Wajib login — jika belum login, redirect ke halaman login
wajib_login();

$role = $_SESSION['user_role'];

// Arahkan ke halaman yang sesuai role
if ($role === 'pemilik') {
    redirect(BASE_URL . '/pages/pemilik/index.php');

} elseif ($role === 'admin') {
    // Placeholder untuk fase selanjutnya
    redirect(BASE_URL . '/pages/admin/index.php');

} else {
    // role 'pencari' atau lainnya
    redirect(BASE_URL . '/index.php');
}
