<?php
/**
 * ====================================================
 * FILE: pages/register.php  (FASE 2 — dengan session)
 * FUNGSI: Pendaftaran akun baru dengan password_hash,
 *         pemisahan role, dan auto-login setelah daftar.
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

// Jika sudah login, langsung redirect
if (sudah_login()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

$input       = [];
$pesan_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil dan sanitasi semua input
    $input['nama']  = trim($_POST['nama']  ?? '');
    $input['email'] = trim($_POST['email'] ?? '');
    $input['no_hp'] = trim($_POST['no_hp'] ?? '');
    $input['role']  = trim($_POST['role']  ?? 'pencari');
    $password       = $_POST['password']         ?? '';
    $konfirmasi     = $_POST['konfirmasi_password'] ?? '';

    // ---- Validasi ----
    if (empty($input['nama']) || empty($input['email']) || empty($password)) {
        $pesan_error = 'Nama, email, dan password wajib diisi.';

    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $pesan_error = 'Format email tidak valid.';

    } elseif (strlen($password) < 6) {
        $pesan_error = 'Password minimal 6 karakter.';

    } elseif ($password !== $konfirmasi) {
        $pesan_error = 'Konfirmasi password tidak cocok.';

    } elseif (!in_array($input['role'], ['pencari', 'pemilik'])) {
        $pesan_error = 'Pilihan peran tidak valid.';

    } else {
        // Cek apakah email sudah terdaftar
        $stmt_cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_cek, 's', $input['email']);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);

        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $pesan_error = 'Email ini sudah terdaftar. Gunakan email lain atau masuk.';
        } else {
            // -----------------------------------------------
            // HASH PASSWORD — ini langkah keamanan krusial!
            //
            // password_hash() mengubah password menjadi string
            // acak yang tidak bisa dikembalikan ke bentuk asli.
            //
            // PASSWORD_BCRYPT = algoritma hashing yang kuat.
            // Setiap hash menghasilkan output berbeda meski
            // password sama (karena ada "salt" acak di dalamnya).
            //
            // Perbandingan password dilakukan dengan
            // password_verify($input, $hash) — bukan ==
            // -----------------------------------------------
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Simpan user baru ke database
            $stmt_insert = mysqli_prepare($koneksi,
                "INSERT INTO users (nama, email, password, no_hp, role) VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt_insert, 'sssss',
                $input['nama'],
                $input['email'],
                $password_hash,
                $input['no_hp'],
                $input['role']
            );

            if (mysqli_stmt_execute($stmt_insert)) {
                // Ambil ID user yang baru saja dibuat
                $id_baru = mysqli_insert_id($koneksi);

                // ---- AUTO-LOGIN setelah daftar ----
                // Langsung set session tanpa perlu login lagi
                $_SESSION['user_id']    = $id_baru;
                $_SESSION['user_nama']  = $input['nama'];
                $_SESSION['user_email'] = $input['email'];
                $_SESSION['user_role']  = $input['role'];

                // Pesan sambutan
                set_flash('sukses', "Akun berhasil dibuat! Selamat datang di Kosta', " . $input['nama'] . " 🎉");

                // Redirect ke dashboard
                redirect(BASE_URL . '/pages/dashboard.php');
            } else {
                $pesan_error = 'Terjadi kesalahan server. Silakan coba lagi.';
            }
        }
    }
}

$judul_halaman = "Daftar Gratis";
$css_tambahan  = "auth.css";

require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<main class="auth-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="auth-card">

                    <div class="auth-card-header">
                        <div class="auth-logo">✨</div>
                        <h1 class="auth-title">Buat Akun</h1>
                        <p class="auth-subtitle">Gratis selamanya. Tidak perlu kartu kredit.</p>
                    </div>

                    <?php if (!empty($pesan_error)): ?>
                        <div class="alert-kosta alert-error">
                            ⚠️ <?= htmlspecialchars($pesan_error) ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/pages/register.php" method="POST" novalidate>

                        <div class="form-group-kosta">
                            <label for="nama" class="form-label-kosta">Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" class="form-input-kosta"
                                   placeholder="Nama lengkap kamu"
                                   value="<?= htmlspecialchars($input['nama'] ?? '') ?>" required>
                        </div>

                        <div class="form-group-kosta">
                            <label for="email" class="form-label-kosta">Email</label>
                            <input type="email" id="email" name="email" class="form-input-kosta"
                                   placeholder="contoh@email.com"
                                   value="<?= htmlspecialchars($input['email'] ?? '') ?>" required>
                        </div>

                        <div class="form-group-kosta">
                            <label for="no_hp" class="form-label-kosta">
                                No. HP
                                <span style="color:var(--color-text-muted);font-weight:400;">(opsional)</span>
                            </label>
                            <input type="tel" id="no_hp" name="no_hp" class="form-input-kosta"
                                   placeholder="08xxxxxxxxxx"
                                   value="<?= htmlspecialchars($input['no_hp'] ?? '') ?>">
                        </div>

                        <!-- Pemilihan Role -->
                        <div class="form-group-kosta">
                            <label class="form-label-kosta">Saya mendaftar sebagai</label>
                            <div class="role-selector">
                                <label class="role-option <?= (($input['role'] ?? 'pencari') === 'pencari') ? 'selected' : '' ?>">
                                    <input type="radio" name="role" value="pencari"
                                           <?= (($input['role'] ?? 'pencari') === 'pencari') ? 'checked' : '' ?>>
                                    <span class="role-icon">🔍</span>
                                    <span class="role-label">Pencari Kos</span>
                                    <span style="font-size:10px; color:var(--color-text-muted); display:block; margin-top:2px;">Cari & simpan kos</span>
                                </label>
                                <label class="role-option <?= (($input['role'] ?? '') === 'pemilik') ? 'selected' : '' ?>">
                                    <input type="radio" name="role" value="pemilik"
                                           <?= (($input['role'] ?? '') === 'pemilik') ? 'checked' : '' ?>>
                                    <span class="role-icon">🏠</span>
                                    <span class="role-label">Pemilik Kos</span>
                                    <span style="font-size:10px; color:var(--color-text-muted); display:block; margin-top:2px;">Kelola & pasang iklan</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group-kosta">
                            <label for="password" class="form-label-kosta">Password</label>
                            <input type="password" id="password" name="password" class="form-input-kosta"
                                   placeholder="Minimal 6 karakter" required>
                        </div>

                        <div class="form-group-kosta">
                            <label for="konfirmasi_password" class="form-label-kosta">Konfirmasi Password</label>
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password"
                                   class="form-input-kosta" placeholder="Ulangi password" required>
                        </div>

                        <button type="submit" class="btn-kosta btn w-100 mt-2">
                            Buat Akun Gratis →
                        </button>

                        <p style="font-size:11px;color:var(--color-text-muted);text-align:center;margin-top:12px;margin-bottom:0;">
                            Dengan mendaftar, kamu menyetujui
                            <a href="#">Syarat & Ketentuan</a> kami.
                        </p>

                    </form>

                    <p class="auth-switch">
                        Sudah punya akun?
                        <a href="<?= BASE_URL ?>/pages/login.php"><strong>Masuk di sini</strong></a>
                    </p>

                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../components/footer.php'; ?>

<script>
// Toggle visual role selector
document.querySelectorAll('.role-option input[type="radio"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
        this.closest('.role-option').classList.add('selected');
    });
});
</script>

<?php require_once __DIR__ . '/../components/scripts.php'; ?>
