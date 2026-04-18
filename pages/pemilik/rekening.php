<?php
/**
 * FILE: pages/pemilik/rekening.php
 * FUNGSI: Form pemilik untuk mengisi/mengubah info rekening bank.
 *         Info ini digunakan admin untuk transfer hasil pembayaran.
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('pemilik');
$user = user_login();

// Ambil data rekening saat ini
$stmt = mysqli_prepare($koneksi,
    "SELECT nama_bank, nomor_rekening, nama_pemilik_rekening FROM users WHERE id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $user['id']);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$errors  = [];

// Proses POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_bank      = trim($_POST['nama_bank']              ?? '');
    $nomor_rek      = trim($_POST['nomor_rekening']         ?? '');
    $nama_rek       = trim($_POST['nama_pemilik_rekening']  ?? '');

    if (empty($nama_bank))  $errors[] = 'Nama bank wajib diisi.';
    if (empty($nomor_rek))  $errors[] = 'Nomor rekening wajib diisi.';
    if (empty($nama_rek))   $errors[] = 'Nama pemilik rekening wajib diisi.';
    if (!preg_match('/^[0-9\-\s]+$/', $nomor_rek)) $errors[] = 'Nomor rekening hanya boleh angka.';

    if (empty($errors)) {
        $upd = mysqli_prepare($koneksi,
            "UPDATE users SET nama_bank = ?, nomor_rekening = ?, nama_pemilik_rekening = ?
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($upd, 'sssi', $nama_bank, $nomor_rek, $nama_rek, $user['id']);
        if (mysqli_stmt_execute($upd)) {
            set_flash('sukses', '✅ Info rekening berhasil disimpan!');
            // Refresh data
            $data = ['nama_bank' => $nama_bank, 'nomor_rekening' => $nomor_rek, 'nama_pemilik_rekening' => $nama_rek];
        } else {
            $errors[] = 'Terjadi kesalahan. Coba lagi.';
        }
    }
}

$judul_halaman = "Info Rekening Bank";
$css_tambahan  = "dashboard.css";
require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div class="sidebar-profile">
            <div class="sidebar-avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
            <div class="sidebar-name"><?= htmlspecialchars($user['nama']) ?></div>
            <span class="sidebar-role-badge">Pemilik Kos</span>
        </div>
        <p class="sidebar-menu-label">Menu Utama</p>
        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/pages/pemilik/index.php" class="sidebar-link">
                <span class="link-icon">📊</span> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/pages/pemilik/tambah_kos.php" class="sidebar-link">
                <span class="link-icon">➕</span> Tambah Kos
            </a>
            <a href="<?= BASE_URL ?>/pages/pemilik/booking.php" class="sidebar-link">
                <span class="link-icon">📋</span> Booking Masuk
            </a>

            <a href="<?= BASE_URL ?>/pages/pemilik/rekening.php" class="sidebar-link aktif">
                <span class="link-icon">🏦</span> Rekening Bank
            </a>
        </nav>
        <p class="sidebar-menu-label">Akun</p>
        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/pages/logout.php" class="sidebar-link sidebar-link-logout">
                <span class="link-icon">🚪</span> Keluar
            </a>
        </nav>
    </aside>

    <main class="dashboard-content">
        <div class="dashboard-header">
            <div>
                <h1 class="dashboard-title">🏦 Info Rekening Bank</h1>
                <p class="dashboard-subtitle">
                    Info rekening ini digunakan admin untuk mentransfer hasil pembayaran sewa kos kamu.
                </p>
            </div>
        </div>

        <?= get_flash() ?>

        <!-- Alert jika belum isi rekening -->
        <?php if (empty($data['nomor_rekening'])): ?>
        <div style="background:#FFF9E6;border:1.5px solid #f59e0b;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#92660a;display:flex;gap:10px;align-items:flex-start;">
            <span style="font-size:18px;">⚠️</span>
            <div>
                <strong>Rekening belum diisi!</strong><br>
                Admin tidak bisa mentransfer hasil pembayaran ke kamu jika rekening belum diisi.
                Isi sekarang agar pembayaran bisa diproses.
            </div>
        </div>
        <?php endif; ?>

        <div class="table-card" style="max-width:560px;">

            <?php if (!empty($errors)): ?>
                <div style="background:#FFF3F3;border:1.5px solid #fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#b91c1c;">
                    <ul style="margin:0;padding-left:18px;">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="form-group-kosta" style="margin-bottom:20px;">
                    <label class="form-label-kosta">Nama Bank *</label>
                    <select name="nama_bank" class="form-input-kosta" required>
                        <option value="">— Pilih Bank —</option>
                        <?php
                        $banks = ['BCA','BRI','BNI','Mandiri','BSI (Bank Syariah Indonesia)',
                                  'CIMB Niaga','Danamon','Permata','BTN','OCBC NISP',
                                  'Jenius (BTPN)','GoPay','OVO','DANA','ShopeePay'];
                        foreach ($banks as $b):
                            $sel = ($data['nama_bank'] ?? '') === $b ? 'selected' : '';
                        ?>
                            <option value="<?= $b ?>" <?= $sel ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group-kosta" style="margin-bottom:20px;">
                    <label class="form-label-kosta">Nomor Rekening *</label>
                    <input type="text" name="nomor_rekening" class="form-input-kosta"
                           placeholder="Contoh: 1234567890"
                           value="<?= htmlspecialchars($data['nomor_rekening'] ?? '') ?>"
                           required inputmode="numeric">
                    <p style="font-size:12px;color:var(--color-text-muted);margin-top:4px;">
                        Pastikan nomor rekening sudah benar. Salah nomor = transfer gagal.
                    </p>
                </div>

                <div class="form-group-kosta" style="margin-bottom:24px;">
                    <label class="form-label-kosta">Nama Pemilik Rekening *</label>
                    <input type="text" name="nama_pemilik_rekening" class="form-input-kosta"
                           placeholder="Sesuai buku tabungan / aplikasi bank"
                           value="<?= htmlspecialchars($data['nama_pemilik_rekening'] ?? '') ?>"
                           required>
                    <p style="font-size:12px;color:var(--color-text-muted);margin-top:4px;">
                        Nama harus sama persis dengan yang terdaftar di bank.
                    </p>
                </div>

                <button type="submit" class="btn-kosta btn" style="width:100%;padding:13px;font-size:15px;">
                    💾 Simpan Info Rekening
                </button>

            </form>

            <?php if (!empty($data['nomor_rekening'])): ?>
            <!-- Preview rekening saat ini -->
            <div style="margin-top:24px;padding:16px;background:var(--color-bg);border:1.5px solid var(--color-border);border-radius:10px;">
                <div style="font-size:12px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">
                    Rekening Tersimpan
                </div>
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="font-size:28px;">🏦</div>
                    <div>
                        <div style="font-weight:800;font-size:15px;"><?= htmlspecialchars($data['nama_bank']) ?></div>
                        <div style="font-size:16px;font-weight:700;color:var(--color-accent);letter-spacing:.05em;">
                            <?= htmlspecialchars($data['nomor_rekening']) ?>
                        </div>
                        <div style="font-size:12px;color:var(--color-text-muted);">a.n. <?= htmlspecialchars($data['nama_pemilik_rekening']) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php mysqli_close($koneksi); ?>
    </main>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
<?php require_once __DIR__ . '/../../components/scripts.php'; ?>
