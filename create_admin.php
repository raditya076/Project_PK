<?php
/**
 * ====================================================
 * FILE: create_admin.php
 * FUNGSI: Script sekali-pakai untuk membuat akun admin.
 *
 * CARA PAKAI:
 *   1. Akses: http://localhost/Project1/create_admin.php
 *   2. Setelah admin berhasil dibuat, HAPUS file ini!
 *
 * KEAMANAN: File ini WAJIB dihapus setelah digunakan
 *           karena memberi akses pembuatan akun tanpa login.
 * ====================================================
 */
require_once __DIR__ . '/config/koneksi.php';

// ── Konfigurasi Admin ─────────────────────────────────────────
$admin_nama     = 'Administrator';
$admin_email    = 'admin@kosta.com';
$admin_password = 'kosta_admin_2024';  // Ganti sesuai kebutuhan
$admin_no_hp    = '081234567890';
// ─────────────────────────────────────────────────────────────

$pesan = '';
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama']     ?? $admin_nama);
    $email    = trim($_POST['email']    ?? $admin_email);
    $password = trim($_POST['password'] ?? $admin_password);
    $no_hp    = trim($_POST['no_hp']    ?? $admin_no_hp);

    // Cek apakah email sudah ada
    $cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($cek, 's', $email);
    mysqli_stmt_execute($cek);
    mysqli_stmt_store_result($cek);

    if (mysqli_stmt_num_rows($cek) > 0) {
        $pesan = "❌ Email <strong>$email</strong> sudah terdaftar. Ganti email lain.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($koneksi,
            "INSERT INTO users (nama, email, password, role, no_hp, status, created_at)
             VALUES (?, ?, ?, 'admin', ?, 'aktif', NOW())");
        mysqli_stmt_bind_param($stmt, 'ssss', $nama, $email, $hash, $no_hp);

        if (mysqli_stmt_execute($stmt)) {
            $sukses = true;
            $pesan  = "✅ Akun admin berhasil dibuat!<br>login: <strong>$email</strong> / <strong>$password</strong><br><br>⚠️ <strong>HAPUS FILE INI SEKARANG!</strong>";
        } else {
            $pesan = "❌ Gagal membuat akun: " . mysqli_error($koneksi);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buat Akun Admin — Kosta'</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 12px; padding: 36px; width: 100%; max-width: 420px; box-shadow: 0 8px 32px rgba(0,0,0,.10); }
        h1 { font-size: 20px; font-weight: 800; margin: 0 0 6px; color: #1c1c1c; }
        p.sub { font-size: 13px; color: #6b6b6b; margin: 0 0 24px; }
        label { display: block; font-size: 12px; font-weight: 700; color: #6b6b6b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 12px; border: 1.5px solid #e8e6e3; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; margin-bottom: 16px; }
        input:focus { border-color: #c50000; }
        button { width: 100%; padding: 12px; background: #c50000; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
        button:hover { background: #e70000; }
        .alert { padding: 14px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; line-height: 1.6; }
        .alert.error   { background: #fff3f3; color: #b91c1c; border: 1px solid #fca5a5; }
        .alert.success { background: #f0fff4; color: #15803d; border: 1px solid #86efac; }
        .danger-note { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #92400e; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="card">
    <h1>⚙️ Buat Akun Admin</h1>
    <p class="sub">Gunakan form ini untuk membuat akun admin pertama Kosta'.</p>

    <div class="danger-note">
        ⚠️ <strong>Perhatian:</strong> File ini WAJIB dihapus setelah akun admin dibuat.
        Jangan biarkan file ini dapat diakses di lingkungan produksi!
    </div>

    <?php if ($pesan): ?>
        <div class="alert <?= $sukses ? 'success' : 'error' ?>"><?= $pesan ?></div>
    <?php endif; ?>

    <?php if (!$sukses): ?>
    <form method="POST">
        <label>Nama Lengkap</label>
        <input type="text" name="nama" value="<?= htmlspecialchars($admin_nama) ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($admin_email) ?>" required>

        <label>Password</label>
        <input type="text" name="password" value="<?= htmlspecialchars($admin_password) ?>" required>

        <label>No. HP</label>
        <input type="text" name="no_hp" value="<?= htmlspecialchars($admin_no_hp) ?>">

        <button type="submit">Buat Akun Admin</button>
    </form>
    <?php else: ?>
        <div style="text-align:center;margin-top:16px;">
            <a href="<?= BASE_URL ?>/pages/login.php"
               style="font-size:13px;font-weight:700;color:#c50000;">
                → Login sebagai Admin
            </a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
