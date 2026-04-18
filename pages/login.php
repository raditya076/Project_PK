<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

// Jika sudah login, langsung redirect ke dashboard
if (sudah_login()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

// Ambil pesan dari URL (misal: ?pesan=login_dulu)
$pesan_url = $_GET['pesan'] ?? '';

$pesan_error  = '';
$email_input  = '';

// Cek flash message dari session (misalnya dari redirect logout)
$flash_html = get_flash();

// PROSES FORM LOGIN (hanya jika method POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email_input = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';

    // Validasi tidak kosong
    if (empty($email_input) || empty($password)) {
        $pesan_error = 'Email dan password wajib diisi.';
    } else {

        // Cari user di database berdasarkan email menggunakan Prepared Statement
        // Prepared Statement = cara aman mengirim data ke SQL (mencegah SQL Injection)
        $stmt = mysqli_prepare($koneksi,
            "SELECT id, nama, email, password, role FROM users WHERE email = ? AND status = 'aktif' LIMIT 1"
        );
        // Bind parameter: 's' = string, lalu variabel yang dikirim
        mysqli_stmt_bind_param($stmt, 's', $email_input);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        // password_verify() = membandingkan password asli dengan hash di database
        // JANGAN PERNAH simpan atau bandingkan password secara plaintext!
        if ($user && password_verify($password, $user['password'])) {

            // ✅ LOGIN BERHASIL
            // Simpan data penting ke $_SESSION
            // $_SESSION adalah array global yang bertahan antar request (halaman)
            // Data ini disimpan di SERVER, bukan di browser —
            // browser hanya menyimpan ID session-nya (via cookie)
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_nama']  = $user['nama'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            // Perbarui timestamp login terakhir (opsional tapi berguna untuk audit)
            $stmt_update = mysqli_prepare($koneksi,
                "UPDATE users SET updated_at = NOW() WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt_update, 'i', $user['id']);
            mysqli_stmt_execute($stmt_update);

            // Set flash message sukses (akan ditampilkan di halaman setelah redirect)
            set_flash('sukses', 'Selamat datang kembali, ' . $user['nama'] . '! 👋');

            // Redirect ke dashboard (halaman yang sesuai role akan ditangani di dashboard.php)
            redirect(BASE_URL . '/pages/dashboard.php');

        } else {
            // ❌ LOGIN GAGAL
            $pesan_error = 'Email atau password salah. Silakan coba lagi.';
        }
    }
}

$judul_halaman = "Masuk";
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
                        <div class="auth-logo">🏠</div>
                        <h1 class="auth-title">Selamat Datang</h1>
                        <p class="auth-subtitle">Masuk ke akun Kosta' kamu</p>
                    </div>

                    <!-- Flash message dari redirect sebelumnya (misal setelah logout) -->
                    <?= $flash_html ?>

                    <!-- Pesan dari URL parameter -->
                    <?php if ($pesan_url === 'login_dulu'): ?>
                        <div class="alert-kosta" style="background:#FFFBEB;color:#92400e;border:1px solid #fcd34d;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;">
                            🔒 Kamu perlu masuk dulu untuk mengakses halaman tersebut.
                        </div>
                    <?php endif; ?>

                    <!-- Error validasi form -->
                    <?php if (!empty($pesan_error)): ?>
                        <div class="alert-kosta alert-error">
                            ⚠️ <?= htmlspecialchars($pesan_error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Form Login -->
                    <form action="<?= BASE_URL ?>/pages/login.php" method="POST" novalidate>

                        <div class="form-group-kosta">
                            <label for="email" class="form-label-kosta">Email</label>
                            <input type="email" id="email" name="email"
                                   class="form-input-kosta"
                                   placeholder="contoh@email.com"
                                   value="<?= htmlspecialchars($email_input) ?>"
                                   required autocomplete="email">
                        </div>

                        <div class="form-group-kosta">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <label for="password" class="form-label-kosta">Password</label>
                                <a href="#" style="font-size:12px;">Lupa password?</a>
                            </div>
                            <div style="position:relative;">
                                <input type="password" id="password" name="password"
                                       class="form-input-kosta"
                                       placeholder="Masukkan password"
                                       required autocomplete="current-password"
                                       style="padding-right:44px;">
                                <!-- Tombol toggle show/hide password -->
                                <button type="button" id="toggle-password"
                                        style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--color-text-muted);"
                                        aria-label="Tampilkan password">
                                    👁️
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-kosta btn w-100 mt-2" id="btn-login">
                            Masuk ke Akun
                        </button>

                    </form>

                    <p class="auth-switch">
                        Belum punya akun?
                        <a href="<?= BASE_URL ?>/pages/register.php"><strong>Daftar Gratis</strong></a>
                    </p>

                </div><!-- /auth-card -->
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../components/footer.php'; ?>

<script>
// Toggle show/hide password
document.getElementById('toggle-password').addEventListener('click', function() {
    var input = document.getElementById('password');
    if (input.type === 'password') {
        input.type = 'text';
        this.textContent = '🙈';
    } else {
        input.type = 'password';
        this.textContent = '👁️';
    }
});
</script>

<?php require_once __DIR__ . '/../components/scripts.php'; ?>
