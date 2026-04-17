<?php
/**
 * ====================================================
 * FILE: pages/pesan/kirim.php
 * FUNGSI: Menerima dan menyimpan pesan dari pengunjung
 *         ke pemilik kos ke dalam tabel 'pesan'.
 * ====================================================
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/index.php');
}

$kos_id  = (int)($_POST['kos_id']  ?? 0);
$kembali = $_POST['kembali'] ?? BASE_URL . '/index.php';

if ($kos_id <= 0) {
    set_flash('error', 'ID kos tidak valid.');
    redirect($kembali);
}

// Ambil data kos + pemilik
$stmt_kos = mysqli_prepare($koneksi,
    "SELECT id, nama_kos, pemilik_id FROM kos WHERE id = ? AND status = 'aktif' LIMIT 1"
);
mysqli_stmt_bind_param($stmt_kos, 'i', $kos_id);
mysqli_stmt_execute($stmt_kos);
$kos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kos));

if (!$kos) {
    set_flash('error', 'Kos tidak ditemukan.');
    redirect($kembali);
}

// Jika user login, prefill dari session; jika tidak, ambil dari form
if (sudah_login()) {
    $user          = user_login();
    $nama_pengirim = $user['nama'];
    $email_pengirim= $user['email'];
    $pengirim_id   = $user['id'];
} else {
    $nama_pengirim = trim($_POST['nama_pengirim']  ?? '');
    $email_pengirim= trim($_POST['email_pengirim'] ?? '');
    $pengirim_id   = null;
}

$no_hp_pengirim= trim($_POST['no_hp_pengirim'] ?? '');
$isi_pesan     = trim($_POST['isi_pesan']       ?? '');

// Validasi
if (empty($nama_pengirim) || empty($email_pengirim) || empty($isi_pesan)) {
    set_flash('error', 'Nama, email, dan isi pesan wajib diisi.');
    redirect($kembali);
}
if (!filter_var($email_pengirim, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Format email tidak valid.');
    redirect($kembali);
}
if (strlen($isi_pesan) < 10) {
    set_flash('error', 'Pesan terlalu pendek. Minimal 10 karakter.');
    redirect($kembali);
}

// Simpan pesan ke database
$stmt_insert = mysqli_prepare($koneksi,
    "INSERT INTO pesan
        (kos_id, pemilik_id, pengirim_id, nama_pengirim, email_pengirim, no_hp_pengirim, isi_pesan)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt_insert, 'iiissss',
    $kos['id'],
    $kos['pemilik_id'],
    $pengirim_id,
    $nama_pengirim,
    $email_pengirim,
    $no_hp_pengirim,
    $isi_pesan
);

if (mysqli_stmt_execute($stmt_insert)) {
    set_flash('sukses', 'Pesanmu berhasil terkirim ke pemilik kos! 📩 Mereka akan menghubungimu segera.');
} else {
    set_flash('error', 'Gagal mengirim pesan. Silakan coba lagi.');
}

mysqli_close($koneksi);
redirect($kembali);
