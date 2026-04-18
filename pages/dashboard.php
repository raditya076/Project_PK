<?php

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
