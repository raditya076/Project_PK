<?php
/**
 * ====================================================
 * FILE: pages/pemilik/tambah_kos.php
 * FUNGSI: Form untuk menambahkan listing kos baru
 *         beserta logika upload foto.
 * ====================================================
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

// Middleware: hanya pemilik
wajib_role('pemilik');

$user        = user_login();
$pesan_error = '';
$input       = []; // Menyimpan nilai input agar tidak hilang saat error

// ============================================================
// PROSES FORM (hanya jika POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil semua input teks dari form
    $input['nama_kos']          = trim($_POST['nama_kos']          ?? '');
    $input['deskripsi']         = trim($_POST['deskripsi']          ?? '');
    $input['tipe']              = trim($_POST['tipe']               ?? '');
    $input['alamat']            = trim($_POST['alamat']             ?? '');
    $input['kecamatan']         = trim($_POST['kecamatan']          ?? '');
    $input['kota']              = trim($_POST['kota']               ?? '');
    $input['provinsi']          = trim($_POST['provinsi']           ?? '');
    $input['harga_per_bulan']   = (int)($_POST['harga_per_bulan']  ?? 0);
    $input['jumlah_kamar']      = (int)($_POST['jumlah_kamar']     ?? 1);
    // Koordinat GPS (opsional) — NULL jika kosong
    $input['lat']               = !empty($_POST['lat']) ? (float)$_POST['lat'] : null;
    $input['lng']               = !empty($_POST['lng']) ? (float)$_POST['lng'] : null;

    // Fasilitas: jika checkbox tidak dicentang, nilainya tidak ada di $_POST
    // Gunakan isset() untuk cek dan paksa jadi 0 atau 1
    $input['wifi']                = isset($_POST['wifi'])               ? 1 : 0;
    $input['ac']                  = isset($_POST['ac'])                 ? 1 : 0;
    $input['kamar_mandi_dalam']   = isset($_POST['kamar_mandi_dalam'])  ? 1 : 0;
    $input['parkir']              = isset($_POST['parkir'])             ? 1 : 0;
    $input['dapur']               = isset($_POST['dapur'])              ? 1 : 0;
    $input['laundry']             = isset($_POST['laundry'])            ? 1 : 0;
    $input['security']            = isset($_POST['security'])           ? 1 : 0;
    $input['cctv']                = isset($_POST['cctv'])               ? 1 : 0;

    // ---- Validasi wajib ----
    if (empty($input['nama_kos'])) {
        $pesan_error = 'Nama kos wajib diisi.';
    } elseif ($input['harga_per_bulan'] <= 0) {
        $pesan_error = 'Harga per bulan harus lebih dari 0.';
    } elseif (!in_array($input['tipe'], ['putra', 'putri', 'campur'])) {
        $pesan_error = 'Tipe kos tidak valid.';
    } elseif (empty($input['alamat']) || empty($input['kota'])) {
        $pesan_error = 'Alamat dan kota wajib diisi.';
    } else {

        // ================================================================
        // LOGIKA UPLOAD FOTO
        // ================================================================
        // $_FILES['foto_utama'] = array yang berisi info file yang diupload
        // Strukturnya:
        //   ['name']     = nama asli file dari komputer user
        //   ['type']     = MIME type (misal: image/jpeg)
        //   ['tmp_name'] = path file sementara di server (sebelum dipindahkan)
        //   ['error']    = kode error (0 = tidak ada error)
        //   ['size']     = ukuran file dalam bytes
        // ================================================================
        $nama_file_tersimpan = ''; // Default: kosong (tidak ada foto)

        // Cek apakah ada file yang diupload (bukan upload kosong)
        if (isset($_FILES['foto_utama']) && $_FILES['foto_utama']['error'] === UPLOAD_ERR_OK) {

            $file_tmp  = $_FILES['foto_utama']['tmp_name']; // Path file sementara
            $file_name = $_FILES['foto_utama']['name'];     // Nama asli file
            $file_size = $_FILES['foto_utama']['size'];     // Ukuran file (bytes)
            $file_type = $_FILES['foto_utama']['type'];     // MIME type

            // Validasi 1: Hanya boleh gambar
            // mime_content_type() lebih aman dari $_FILES['type'] karena
            // tidak bergantung pada info dari browser (yang bisa dipalsukan)
            $tipe_diizinkan = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $tipe_actual    = mime_content_type($file_tmp); // Cek tipe asli dari server

            // Validasi 2: Ukuran maksimal 2MB (2 * 1024 * 1024 bytes)
            $ukuran_max = 2 * 1024 * 1024; // 2MB

            if (!in_array($tipe_actual, $tipe_diizinkan)) {
                $pesan_error = 'Format foto tidak valid. Gunakan JPG, PNG, atau WebP.';

            } elseif ($file_size > $ukuran_max) {
                $pesan_error = 'Ukuran foto terlalu besar. Maksimal 2MB.';

            } else {
                // Buat nama file unik untuk menghindari file tertimpa
                // uniqid() = string unik berdasarkan waktu
                // pathinfo()['extension'] = ambil ekstensi dari nama file asli
                $ekstensi  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $nama_baru = 'kos_' . $user['id'] . '_' . uniqid() . '.' . $ekstensi;

                // Path folder tujuan di server
                // __DIR__ = folder saat ini (/pages/pemilik/)
                // ../../assets/images/kos/ = naik 2 level ke root, lalu ke folder gambar
                $folder_tujuan = __DIR__ . '/../../assets/images/kos/';

                // Buat folder jika belum ada
                if (!is_dir($folder_tujuan)) {
                    mkdir($folder_tujuan, 0755, true); // true = buat parent folder juga
                }

                $path_tujuan = $folder_tujuan . $nama_baru;

                // move_uploaded_file() = PINDAHKAN file dari folder tmp ke folder tujuan
                // Ini lebih aman dari copy() karena khusus untuk file upload
                // Return true jika berhasil, false jika gagal
                if (move_uploaded_file($file_tmp, $path_tujuan)) {
                    $nama_file_tersimpan = $nama_baru; // Simpan nama file ke database
                } else {
                    $pesan_error = 'Gagal menyimpan foto. Cek izin folder assets/images/kos/';
                }
            }
        }
        // Jika tidak ada foto atau foto gagal upload tapi tidak ada error lain,
        // lanjutkan dan simpan dengan $nama_file_tersimpan = '' (placeholder akan dipakai)

        // ============================================================
        // SIMPAN KOS
        // ============================================================
        if (empty($pesan_error)) {
            $stmt = mysqli_prepare($koneksi,
                "INSERT INTO kos (
                    pemilik_id, nama_kos, deskripsi, tipe,
                    alamat, kecamatan, kota, provinsi,
                    harga_per_bulan, jumlah_kamar,
                    wifi, ac, kamar_mandi_dalam, parkir,
                    dapur, laundry, security, cctv,
                    foto_utama, lat, lng, status
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, 'aktif'
                )"
            );

            mysqli_stmt_bind_param($stmt, 'isssssssiiiiiiiiiisdd',
                $user['id'],
                $input['nama_kos'],
                $input['deskripsi'],
                $input['tipe'],
                $input['alamat'],
                $input['kecamatan'],
                $input['kota'],
                $input['provinsi'],
                $input['harga_per_bulan'],
                $input['jumlah_kamar'],
                $input['wifi'],
                $input['ac'],
                $input['kamar_mandi_dalam'],
                $input['parkir'],
                $input['dapur'],
                $input['laundry'],
                $input['security'],
                $input['cctv'],
                $nama_file_tersimpan,
                $input['lat'],
                $input['lng']
            );

            if (mysqli_stmt_execute($stmt)) {
                set_flash('sukses', 'Kos "' . $input['nama_kos'] . '" berhasil ditambahkan! 🎉');
                redirect(BASE_URL . '/pages/pemilik/index.php');
            } else {
                $pesan_error = 'Gagal menyimpan data kos. Silakan coba lagi.';
            }
        }
    }
}

$judul_halaman = "Tambah Kos";
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
            <a href="<?= BASE_URL ?>/pages/pemilik/tambah_kos.php" class="sidebar-link aktif">
                <span class="link-icon">➕</span> Tambah Kos
            </a>

        </nav>
        <p class="sidebar-menu-label">Akun</p>
        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/pages/logout.php" class="sidebar-link sidebar-link-logout">
                <span class="link-icon">🚪</span> Keluar
            </a>
        </nav>
    </aside>

    <!-- Konten Form -->
    <main class="dashboard-content">

        <div class="dashboard-header">
            <div>
                <h1 class="dashboard-title">Tambah Kos Baru</h1>
                <p class="dashboard-subtitle">Isi informasi kos kamu dengan lengkap agar mudah ditemukan.</p>
            </div>
            <a href="<?= BASE_URL ?>/pages/pemilik/index.php" class="btn-kosta-outline btn"
               style="font-size:13px;">
                ← Kembali
            </a>
        </div>

        <!-- Pesan Error -->
        <?php if (!empty($pesan_error)): ?>
            <div class="alert-kosta alert-error" style="background:#FFF3F3;color:#b91c1c;border:1px solid #fca5a5;padding:14px 18px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:20px;">
                ⚠️ <?= htmlspecialchars($pesan_error) ?>
            </div>
        <?php endif; ?>

        <!-- Form: enctype multipart/form-data WAJIB untuk upload file -->
        <form action="<?= BASE_URL ?>/pages/pemilik/tambah_kos.php"
              method="POST"
              enctype="multipart/form-data"
              id="form-tambah-kos"
              novalidate>

            <div class="form-card">

                <!-- BAGIAN 1: Informasi Dasar -->
                <div class="form-card-section">
                    <p class="form-card-section-title">📋 Informasi Dasar</p>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="form-group-kosta">
                                <label for="nama_kos" class="form-label-kosta">
                                    Nama Kos <span class="wajib">*</span>
                                </label>
                                <input type="text" id="nama_kos" name="nama_kos"
                                       class="form-input-kosta"
                                       placeholder="Contoh: Kos Melati Indah"
                                       value="<?= htmlspecialchars($input['nama_kos'] ?? '') ?>"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-kosta">
                                <label for="tipe" class="form-label-kosta">
                                    Tipe Kos <span class="wajib">*</span>
                                </label>
                                <select id="tipe" name="tipe" class="form-select-kosta" required>
                                    <option value="">-- Pilih Tipe --</option>
                                    <option value="putra"  <?= ($input['tipe'] ?? '') === 'putra'  ? 'selected' : '' ?>>Putra</option>
                                    <option value="putri"  <?= ($input['tipe'] ?? '') === 'putri'  ? 'selected' : '' ?>>Putri</option>
                                    <option value="campur" <?= ($input['tipe'] ?? '') === 'campur' ? 'selected' : '' ?>>Campur</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-kosta">
                        <label for="deskripsi" class="form-label-kosta">Deskripsi Kos</label>
                        <textarea id="deskripsi" name="deskripsi"
                                  class="form-textarea-kosta"
                                  placeholder="Ceritakan keunggulan kos kamu: lokasi, suasana, kondisi kamar, peraturan, dll."><?= htmlspecialchars($input['deskripsi'] ?? '') ?></textarea>
                        <p class="form-hint">Deskripsi yang detail meningkatkan kepercayaan calon penghuni.</p>
                    </div>

                </div><!-- /section 1 -->

                <!-- BAGIAN 2: Alamat -->
                <div class="form-card-section">
                    <p class="form-card-section-title">📍 Lokasi & Alamat</p>

                    <div class="form-group-kosta">
                        <label for="alamat" class="form-label-kosta">
                            Alamat Lengkap <span class="wajib">*</span>
                        </label>
                        <textarea id="alamat" name="alamat"
                                  class="form-textarea-kosta"
                                  style="min-height:80px;"
                                  placeholder="Jl. Margonda Raya No. 45, RT 02/RW 03"
                                  required><?= htmlspecialchars($input['alamat'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group-kosta">
                                <label for="kecamatan" class="form-label-kosta">Kecamatan</label>
                                <input type="text" id="kecamatan" name="kecamatan"
                                       class="form-input-kosta" placeholder="Beji"
                                       value="<?= htmlspecialchars($input['kecamatan'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-kosta">
                                <label for="kota" class="form-label-kosta">
                                    Kota / Kabupaten <span class="wajib">*</span>
                                </label>
                                <input type="text" id="kota" name="kota"
                                       class="form-input-kosta" placeholder="Depok"
                                       value="<?= htmlspecialchars($input['kota'] ?? '') ?>"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-kosta">
                                <label for="provinsi" class="form-label-kosta">Provinsi</label>
                                <input type="text" id="provinsi" name="provinsi"
                                       class="form-input-kosta" placeholder="Jawa Barat"
                                       value="<?= htmlspecialchars($input['provinsi'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Koordinat GPS (Opsional, untuk peta Leaflet) -->
                    <div class="row g-3" style="margin-top:4px;">
                        <div class="col-12">
                            <p style="font-size:12px;color:var(--color-text-muted);margin-bottom:8px;">
                                📍 <strong>Koordinat GPS</strong> (opsional — untuk menampilkan peta lokasi).<br>
                                Cara mendapatkan: Buka <a href="https://maps.google.com" target="_blank">Google Maps</a>,
                                klik kanan lokasi kos → salin koordinat.
                                Contoh: <code>-6.3612</code> (Lat) &amp; <code>106.8227</code> (Lng)
                            </p>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-kosta">
                                <label for="lat" class="form-label-kosta">Latitude</label>
                                <input type="number" id="lat" name="lat"
                                       class="form-input-kosta" placeholder="-6.3612"
                                       step="0.0000001" min="-11" max="6"
                                       value="<?= htmlspecialchars($input['lat'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-kosta">
                                <label for="lng" class="form-label-kosta">Longitude</label>
                                <input type="number" id="lng" name="lng"
                                       class="form-input-kosta" placeholder="106.8227"
                                       step="0.0000001" min="95" max="141"
                                       value="<?= htmlspecialchars($input['lng'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                </div><!-- /section 2 -->

                <!-- BAGIAN 3: Harga & Kamar -->
                <div class="form-card-section">
                    <p class="form-card-section-title">💰 Harga & Ketersediaan</p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group-kosta">
                                <label for="harga_per_bulan" class="form-label-kosta">
                                    Harga per Bulan (Rp) <span class="wajib">*</span>
                                </label>
                                <input type="number" id="harga_per_bulan" name="harga_per_bulan"
                                       class="form-input-kosta"
                                       placeholder="1500000"
                                       min="1" step="50000"
                                       value="<?= htmlspecialchars($input['harga_per_bulan'] ?? '') ?>"
                                       required>
                                <p class="form-hint">Masukkan angka tanpa titik atau koma. Contoh: 1500000</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-kosta">
                                <label for="jumlah_kamar" class="form-label-kosta">
                                    Jumlah Total Kamar <span class="wajib">*</span>
                                </label>
                                <input type="number" id="jumlah_kamar" name="jumlah_kamar"
                                       class="form-input-kosta"
                                       placeholder="10" min="1" max="200"
                                       value="<?= htmlspecialchars($input['jumlah_kamar'] ?? '1') ?>"
                                       required>
                            </div>
                        </div>
                    </div>

                </div><!-- /section 3 -->

                <!-- BAGIAN 4: Fasilitas -->
                <div class="form-card-section">
                    <p class="form-card-section-title">🛋️ Fasilitas Tersedia</p>
                    <p class="form-hint" style="margin-bottom:16px;">
                        Centang fasilitas yang tersedia di kos kamu.
                    </p>

                    <?php
                    // Array fasilitas: nama_kolom_db => [ikon, label]
                    $fasilitas_options = [
                        'wifi'               => ['📶', 'WiFi / Internet'],
                        'ac'                 => ['❄️', 'AC (Air Conditioner)'],
                        'kamar_mandi_dalam'  => ['🚿', 'Kamar Mandi Dalam'],
                        'parkir'             => ['🅿️', 'Parkir Kendaraan'],
                        'dapur'              => ['🍳', 'Dapur Bersama'],
                        'laundry'            => ['👕', 'Laundry'],
                        'security'           => ['👮', 'Petugas Keamanan'],
                        'cctv'              => ['📹', 'CCTV'],
                    ];
                    ?>
                    <div class="fasilitas-grid">
                        <?php foreach ($fasilitas_options as $kolom => [$ikon, $label]): ?>
                            <label class="check-item">
                                <input type="checkbox"
                                       name="<?= $kolom ?>"
                                       value="1"
                                       <?= !empty($input[$kolom]) ? 'checked' : '' ?>>
                                <span><?= $ikon ?></span>
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                </div><!-- /section 4 -->

                <!-- BAGIAN 5: Upload Foto -->
                <div class="form-card-section">
                    <p class="form-card-section-title">📸 Foto Utama Kos</p>

                    <!-- Zone klik untuk upload foto -->
                    <div class="upload-zone" id="upload-zone" onclick="document.getElementById('foto_utama').click();">
                        <div class="upload-icon">📷</div>
                        <p class="upload-text">Klik untuk pilih foto</p>
                        <p class="upload-hint">JPG, PNG, atau WebP — Maks. 2MB</p>
                        <p class="upload-hint" style="margin-top:6px; color:var(--color-text-muted);">
                            Jika tidak ada foto, icon placeholder akan digunakan.
                        </p>
                    </div>

                    <!-- Input file tersembunyi di belakang upload zone -->
                    <input type="file"
                           id="foto_utama"
                           name="foto_utama"
                           accept="image/jpeg,image/png,image/webp"
                           style="display:none;">

                    <!-- Preview foto yang dipilih -->
                    <div id="foto-preview-wrapper" style="display:none; margin-top:14px;">
                        <img id="foto-preview-img" src="" alt="Preview foto"
                             style="max-width:100%; max-height:220px; object-fit:cover; border-radius:8px; border:1px solid var(--color-border);">
                        <br>
                        <button type="button" id="hapus-preview"
                                style="margin-top:8px; font-size:12px; color:#b91c1c; background:none; border:none; cursor:pointer; font-weight:600;">
                            ✕ Hapus foto yang dipilih
                        </button>
                    </div>

                </div><!-- /section 5 -->

                <!-- Footer Form -->
                <div class="form-footer">
                    <a href="<?= BASE_URL ?>/pages/pemilik/index.php"
                       class="btn-kosta-outline btn">
                        Batal
                    </a>
                    <button type="submit" class="btn-kosta btn" id="btn-submit">
                        💾 Simpan Kos
                    </button>
                </div>

            </div><!-- /form-card -->
        </form>

        <?php mysqli_close($koneksi); ?>

    </main><!-- /dashboard-content -->
</div><!-- /dashboard-wrapper -->

<?php require_once __DIR__ . '/../../components/footer.php'; ?>

<script>
/**
 * Upload zone, foto preview, drag & drop
 */
var inputFoto   = document.getElementById('foto_utama');
var zone        = document.getElementById('upload-zone');
var preview     = document.getElementById('foto-preview-wrapper');
var previewImg  = document.getElementById('foto-preview-img');
var hapusBtn    = document.getElementById('hapus-preview');

inputFoto.addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        previewImg.src = e.target.result;
        preview.style.display = 'block';
        zone.style.display    = 'none';
    };
    reader.readAsDataURL(file);
});

hapusBtn.addEventListener('click', function() {
    inputFoto.value   = '';
    previewImg.src    = '';
    preview.style.display = 'none';
    zone.style.display    = 'block';
});

zone.addEventListener('dragover',  function(e) { e.preventDefault(); this.classList.add('drag-over'); });
zone.addEventListener('dragleave', function()   { this.classList.remove('drag-over'); });
zone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('drag-over');
    var file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        inputFoto.files = dataTransfer.files;
        inputFoto.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once __DIR__ . '/../../components/scripts.php'; ?>
