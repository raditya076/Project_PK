<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

// Middleware: hanya pemilik
wajib_role('pemilik');

$user        = user_login();
$pesan_error = '';
$input       = []; // Menyimpan nilai input agar tidak hilang saat error

// PROSES FORM (hanya jika POST)
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

        // PROSES UPLOAD MULTI-FOTO
        // $_FILES['foto_kos'] memiliki struktur array saat input multiple:
        //   ['name'][0]     = nama file pertama
        //   ['tmp_name'][0] = path sementara file pertama
        //   ['error'][0]    = kode error file pertama
        //   ['size'][0]     = ukuran file pertama
        // dst untuk [1], [2], ...

        $foto_berhasil_diupload = []; // Daftar nama file yang berhasil disimpan
        $tipe_diizinkan = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $map_mime_ke_ekstensi = [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg',
            'image/png'  => 'png', 'image/webp' => 'webp',
        ];
        $ukuran_max    = 3 * 1024 * 1024; // 3MB per foto
        $maks_foto     = 8;               // Maksimal 8 foto per kos
        $folder_tujuan = __DIR__ . '/../../assets/images/kos/';

        if (!is_dir($folder_tujuan)) {
            mkdir($folder_tujuan, 0755, true);
        }

        if (!empty($_FILES['foto_kos']['name'][0])) {

            $total_file = count($_FILES['foto_kos']['name']);

            if ($total_file > $maks_foto) {
                $pesan_error = 'Maksimal ' . $maks_foto . ' foto yang boleh diupload sekaligus.';
            } else {
                // Loop setiap file yang dipilih user
                for ($i = 0; $i < $total_file; $i++) {

                    // Lewati file yang error (misal: user memilih file kosong)
                    if ($_FILES['foto_kos']['error'][$i] !== UPLOAD_ERR_OK) continue;

                    $file_tmp  = $_FILES['foto_kos']['tmp_name'][$i];
                    $file_size = $_FILES['foto_kos']['size'][$i];

                    // Validasi ukuran
                    if ($file_size > $ukuran_max) {
                        $pesan_error = 'Salah satu foto melebihi ukuran 3MB. Silakan compress terlebih dahulu.';
                        break;
                    }

                    // Validasi MIME type (dari isi file, bukan header browser)
                    $mime = mime_content_type($file_tmp);
                    if (!in_array($mime, $tipe_diizinkan)) {
                        $pesan_error = 'Salah satu file bukan gambar yang valid (JPG/PNG/WebP).';
                        break;
                    }

                    // Buat nama file unik dan aman
                    $ekstensi  = $map_mime_ke_ekstensi[$mime] ?? 'jpg';
                    $nama_baru = 'kos_' . $user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ekstensi;

                    if (move_uploaded_file($file_tmp, $folder_tujuan . $nama_baru)) {
                        $foto_berhasil_diupload[] = $nama_baru;
                    }
                }
            }
        }

        // SIMPAN KOS KE DATABASE
        if (empty($pesan_error)) {

            // foto_utama = foto pertama yang diupload (jadi cover)
            $foto_utama = !empty($foto_berhasil_diupload) ? $foto_berhasil_diupload[0] : '';

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
                $foto_utama,
                $input['lat'],
                $input['lng']
            );

            if (mysqli_stmt_execute($stmt)) {
                $kos_id_baru = mysqli_insert_id($koneksi);

                // Simpan semua foto ke tabel kos_foto
                if (!empty($foto_berhasil_diupload)) {
                    $ins_foto = mysqli_prepare($koneksi,
                        "INSERT INTO kos_foto (kos_id, nama_file, urutan) VALUES (?, ?, ?)"
                    );
                    foreach ($foto_berhasil_diupload as $urutan => $nama_file) {
                        mysqli_stmt_bind_param($ins_foto, 'isi', $kos_id_baru, $nama_file, $urutan);
                        mysqli_stmt_execute($ins_foto);
                    }
                }

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

                <!-- BAGIAN 5: Upload Foto (Multi) -->
                <div class="form-card-section">
                    <p class="form-card-section-title">📸 Foto Kos (Dokumentasi)</p>
                    <p class="form-hint" style="margin-bottom:16px;">
                        Upload hingga <strong>8 foto</strong> untuk memperlihatkan kondisi kos kepada calon penghuni.
                        Format: JPG, PNG, WebP — Maks. <strong>3MB per foto</strong>.
                        Foto pertama akan dijadikan <strong>foto cover</strong>.
                    </p>

                    <!-- Zone klik / drag-drop -->
                    <div class="upload-zone" id="upload-zone"
                         onclick="document.getElementById('foto_kos').click();"
                         ondragover="event.preventDefault();this.classList.add('drag-over');"
                         ondragleave="this.classList.remove('drag-over');"
                         ondrop="handleDrop(event)">
                        <div class="upload-icon">📷</div>
                        <p class="upload-text">Klik atau seret foto ke sini</p>
                        <p class="upload-hint">Pilih hingga 8 foto sekaligus</p>
                    </div>

                    <!-- Input file tersembunyi — multiple -->
                    <input type="file"
                           id="foto_kos"
                           name="foto_kos[]"
                           accept="image/jpeg,image/png,image/webp"
                           multiple
                           style="display:none;">

                    <!-- Counter + grid preview -->
                    <div id="preview-area" style="margin-top:14px;display:none;">
                        <p style="font-size:12px;font-weight:700;color:var(--color-text-muted);margin-bottom:10px;">
                            Preview (<span id="foto-count">0</span> foto dipilih — foto pertama jadi cover):
                        </p>
                        <div id="preview-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;"></div>
                        <button type="button" onclick="clearFoto()"
                                style="margin-top:10px;font-size:12px;color:#b91c1c;background:none;border:none;cursor:pointer;font-weight:600;">
                            ✕ Hapus semua foto yang dipilih
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
 * Upload Multi-Foto — Preview grid thumbnail
 * Mendukung klik & drag-drop
 */
var inputFoto  = document.getElementById('foto_kos');
var zone       = document.getElementById('upload-zone');
var previewArea = document.getElementById('preview-area');
var previewGrid = document.getElementById('preview-grid');
var fotoCount   = document.getElementById('foto-count');
var MAKS_FOTO   = 8;

// Tampilkan preview thumbnail untuk setiap file yang dipilih
inputFoto.addEventListener('change', function() {
    tampilkanPreview(this.files);
});

function tampilkanPreview(files) {
    previewGrid.innerHTML = '';

    if (!files || files.length === 0) {
        previewArea.style.display = 'none';
        zone.style.display = 'block';
        return;
    }

    var total = Math.min(files.length, MAKS_FOTO);
    fotoCount.textContent = total;

    for (var i = 0; i < total; i++) {
        (function(file, idx) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var wrapper = document.createElement('div');
                wrapper.style.cssText = 'position:relative;border-radius:8px;overflow:hidden;border:2px solid ' + (idx === 0 ? 'var(--color-accent)' : 'var(--color-border)') + ';aspect-ratio:1;background:#f0ede8;';

                var img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';

                // Badge "Cover" untuk foto pertama
                if (idx === 0) {
                    var badge = document.createElement('div');
                    badge.textContent = 'Cover';
                    badge.style.cssText = 'position:absolute;top:4px;left:4px;background:var(--color-accent);color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:4px;';
                    wrapper.appendChild(badge);
                }

                wrapper.appendChild(img);
                previewGrid.appendChild(wrapper);
            };
            reader.readAsDataURL(file);
        })(files[i], i);
    }

    previewArea.style.display = 'block';
    zone.style.display = 'none';
}

// Hapus semua pilihan foto
function clearFoto() {
    inputFoto.value = '';
    previewGrid.innerHTML = '';
    previewArea.style.display = 'none';
    zone.style.display = 'block';
}

// Handle drag & drop
function handleDrop(e) {
    e.preventDefault();
    zone.classList.remove('drag-over');
    var files = e.dataTransfer.files;
    if (files.length > 0) {
        // Pindahkan files ke input[type=file]
        var dt = new DataTransfer();
        for (var i = 0; i < Math.min(files.length, MAKS_FOTO); i++) {
            if (files[i].type.startsWith('image/')) {
                dt.items.add(files[i]);
            }
        }
        inputFoto.files = dt.files;
        tampilkanPreview(dt.files);
    }
}
</script>

<?php require_once __DIR__ . '/../../components/scripts.php'; ?>



