<?php
/**
 * ====================================================
 * FILE: pages/logout.php
 * FUNGSI: Menghancurkan session dan logout pengguna.
 *
 * Cara kerja logout:
 * 1. Kosongkan array $_SESSION
 * 2. Hapus cookie session dari browser
 * 3. Hancurkan data session di server
 * 4. Redirect ke halaman login
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

// Simpan nama untuk pesan perpisahan sebelum dihapus
$nama_user = $_SESSION['user_nama'] ?? 'Pengguna';

// LANGKAH 1: Kosongkan semua variabel session
// Ini menghapus data dari array $_SESSION
$_SESSION = [];

// LANGKAH 2: Hapus cookie session dari browser pengguna
// Ini memastikan browser tidak bisa menggunakan session ID lama
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),  // Nama cookie (biasanya 'PHPSESSID')
        '',              // Value kosong
        time() - 42000, // Tanggal kadaluarsa di masa lalu (hapus cookie)
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// LANGKAH 3: Hancurkan data session di server
session_destroy();

// LANGKAH 4: Mulai session BARU hanya untuk set flash message
session_start();
set_flash('sukses', 'Sampai jumpa, ' . $nama_user . '! Kamu berhasil keluar. 👋');

// Redirect ke halaman login
redirect(BASE_URL . '/pages/login.php');
