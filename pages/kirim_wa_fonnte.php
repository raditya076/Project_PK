<?php


require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/fonnte.php';

header('Content-Type: application/json; charset=utf-8');

// ── Hanya menerima POST ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

// ── User harus sudah login ─────────────────────────────
if (!sudah_login()) {
    echo json_encode(['success' => false, 'message' => 'Kamu harus login terlebih dahulu.']);
    exit;
}

// ── Ambil & validasi input ─────────────────────────────
$id_kos = (int)($_POST['kos_id'] ?? 0);
$pesan  = trim($_POST['pesan']   ?? '');

if ($id_kos <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID kos tidak valid.']);
    exit;
}

if (mb_strlen($pesan) < 5) {
    echo json_encode(['success' => false, 'message' => 'Pesan terlalu pendek (min. 5 karakter).']);
    exit;
}

if (mb_strlen($pesan) > 500) {
    echo json_encode(['success' => false, 'message' => 'Pesan terlalu panjang (maks. 500 karakter).']);
    exit;
}

// ── Ambil data kos + nomor HP pemilik dari DB ──────────
$stmt = mysqli_prepare($koneksi,
    "SELECT k.nama_kos, u.nama AS nama_pemilik, u.no_hp AS hp_pemilik
     FROM kos k
     LEFT JOIN users u ON k.pemilik_id = u.id
     WHERE k.id = ? AND k.status = 'aktif'
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id_kos);
mysqli_stmt_execute($stmt);
$kos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$kos) {
    echo json_encode(['success' => false, 'message' => 'Kos tidak ditemukan.']);
    exit;
}

if (empty($kos['hp_pemilik'])) {
    echo json_encode(['success' => false, 'message' => 'Pemilik belum memiliki nomor WhatsApp terdaftar.']);
    exit;
}

// ── Normalisasi nomor HP ke format internasional (62xxx) ──
$hp = preg_replace('/\D/', '', $kos['hp_pemilik']); // hapus selain angka
if (substr($hp, 0, 1) === '0') {
    $hp = '62' . substr($hp, 1);         // 08xxx → 628xxx
} elseif (substr($hp, 0, 2) !== '62') {
    $hp = '62' . $hp;                    // 8xxx  → 628xxx
}

// ── Ambil nama pengirim (penyewa) ─────────────────────
$nama_pengirim = htmlspecialchars(user_login()['nama'] ?? 'Seseorang');

// ── Susun teks pesan yang dikirim ke pemilik ──────────
$nama_kos_bersih = htmlspecialchars($kos['nama_kos']);
$teks_pesan      =
    "📩 *Pertanyaan dari Kosta'*\n\n" .
    "Ada pertanyaan dari penyewa untuk kos *{$nama_kos_bersih}*.\n\n" .
    "👤 *Pengirim:* {$nama_pengirim}\n" .
    "💬 *Pesan:*\n{$pesan}\n\n" .
    "Silakan balas langsung ke nomor WhatsApp pengirim.";

// ── Kirim via Fonnte API ───────────────────────────────
$payload = [
    'target'  => $hp,
    'message' => $teks_pesan,
    'typing'  => false,           // tidak perlu simulasi mengetik
];

$ch = curl_init(FONNTE_API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Authorization: ' . FONNTE_TOKEN,
    ],
]);

$response = curl_exec($ch);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    error_log("[Fonnte] cURL error: {$curl_err}");
    echo json_encode(['success' => false, 'message' => 'Gagal menghubungi server Fonnte. Silakan coba lagi.']);
    exit;
}

// ── Parse response JSON Fonnte ─────────────────────────
$hasil = json_decode($response, true);

// Fonnte mengembalikan { "status": true/false, ... }
if (!empty($hasil['status']) && $hasil['status'] === true) {
    echo json_encode(['success' => true, 'message' => 'Pesan berhasil dikirim ke pemilik kos via WhatsApp!']);
} else {
    $detail = $hasil['reason'] ?? ($hasil['message'] ?? 'Tidak diketahui');
    error_log("[Fonnte] Gagal kirim: " . $response);
    echo json_encode(['success' => false, 'message' => "Pesan gagal dikirim. Detail: {$detail}"]);
}
