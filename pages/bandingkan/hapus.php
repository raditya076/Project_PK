<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/index.php');
}

$kos_id  = (int)($_POST['kos_id']  ?? 0);
$reset   = isset($_POST['reset']);     // Tombol "Reset semua"
$kembali = $_POST['kembali'] ?? BASE_URL . '/pages/bandingkan/index.php';

if ($reset) {
    // Hapus seluruh daftar perbandingan dari session
    unset($_SESSION['bandingkan']);
    redirect(BASE_URL . '/index.php');
}

if ($kos_id > 0 && isset($_SESSION['bandingkan'])) {
    // array_diff() = kembalikan array tanpa nilai yang cocok
    // array_values() = reset index array (0,1,2,...) setelah item dihapus
    $_SESSION['bandingkan'] = array_values(
        array_diff($_SESSION['bandingkan'], [$kos_id])
    );
}

redirect($kembali);
