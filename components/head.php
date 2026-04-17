<?php
/**
 * ====================================================
 * FILE: components/head.php
 * FUNGSI: Komponen <head> HTML yang digunakan di semua halaman.
 * Berisi: meta tags, link CSS Bootstrap, link CSS custom.
 *
 * Cara pakai di halaman:
 *   $judul_halaman = "Beranda";
 *   $css_tambahan  = "home.css"; // (opsional)
 *   require_once 'components/head.php';
 *
 * Variabel yang bisa diatur sebelum include:
 *   $judul_halaman  (string) - Judul tab browser
 *   $css_tambahan   (string) - Nama file CSS halaman spesifik (opsional)
 * ====================================================
 */

// Nilai default jika $judul_halaman tidak diset
if (!isset($judul_halaman)) {
    $judul_halaman = "Kosta'";
}

// Nilai default jika $css_tambahan tidak diset
if (!isset($css_tambahan)) {
    $css_tambahan = "";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <!-- Viewport: membuat halaman responsif di semua ukuran layar -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Meta SEO dasar -->
    <meta name="description" content="Kosta' - Platform pencari kos terpercaya di Indonesia. Temukan kos putra, putri, dan campur sesuai kebutuhan kamu.">
    <meta name="keywords" content="cari kos, kos-kosan, kos dekat kampus, sewa kos, kos murah">
    <meta name="author" content="Kosta'">

    <!-- Judul halaman: dinamis sesuai variabel $judul_halaman -->
    <title><?= htmlspecialchars($judul_halaman) ?> – Kosta'</title>

    <!-- Favicon (menggunakan emoji sebagai placeholder) -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏠</text></svg>">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- CSS Global Kosta' (design system, navbar, footer, utilities) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <!-- CSS Halaman Spesifik (jika ada) -->
    <?php if (!empty($css_tambahan)): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= $css_tambahan ?>">
    <?php endif; ?>

    <!--
        $extra_head = Slot untuk HTML tambahan di <head>.
        Dipakai halaman yang butuh CDN khusus,
        misalnya: Leaflet CSS, extra stylesheet, custom meta.
        Set variabel ini SEBELUM memanggil require_once head.php.
    -->
    <?php if (!empty($extra_head)) echo $extra_head; ?>

</head>
<?php
// $body_class: opsional, tambahkan class ke <body>. Contoh: $body_class = "admin-page";
$body_class = $body_class ?? '';
?>
<body<?= $body_class ? ' class="' . htmlspecialchars($body_class) . '"' : '' ?>>
<!-- Komentar: <body> dibuka di sini, ditutup di components/scripts.php -->
