<?php


// --- Konfigurasi Database ---
define('DB_HOST', 'localhost');   // Server database
define('DB_USER', 'root');        // Username database
define('DB_PASS', '');            // Password database
define('DB_NAME', 'kosta_db');    // Nama database

// --- URL Aplikasi ---
// Sesuaikan dengan nama folder proyekmu di localhost
define('BASE_URL', 'http://localhost/Project1');

// --- Koneksi ---
$koneksi = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
mysqli_set_charset($koneksi, 'utf8mb4');

if (!$koneksi) {
    die('<p style="color:red; font-family:sans-serif; padding:20px;">
        ❌ <strong>Koneksi Database Gagal!</strong><br>
        Pesan Error: ' . mysqli_connect_error() . '<br><br>
        Pastikan:<br>
        1. Laragon sudah berjalan (MySQL service aktif)<br>
        2. Database <strong>' . DB_NAME . '</strong> sudah dibuat<br>
        3. Username & password di config/koneksi.php sudah benar
    </p>');
}
