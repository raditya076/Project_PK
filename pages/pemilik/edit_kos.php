<?php
/**
 * ====================================================
 * FILE: pages/pemilik/edit_kos.php
 * FUNGSI: Form edit kos yang sudah ada.
 *         Hanya pemilik kos tersebut yang boleh akses.
 * ====================================================
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('pemilik');
$user = user_login();

$id_kos = (int)($_GET['id'] ?? 0);
if ($id_kos <= 0) redirect(BASE_URL . '/pages/pemilik/index.php');

// Ambil data kos — pastikan milik user ini
$stmt_get = mysqli_prepare($koneksi,
    "SELECT * FROM kos WHERE id = ? AND pemilik_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt_get, 'ii', $id_kos, $user['id']);
mysqli_stmt_execute($stmt_get);
$kos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));

if (!$kos) {
    set_flash('error', 'Kos tidak ditemukan atau kamu bukan pemiliknya.');
    redirect(BASE_URL . '/pages/pemilik/index.php');
}

$errors = [];
$input  = $kos; // Pre-fill form dengan data yang ada

// ============================================================
// PROSES UPDATE (jika form di-submit)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kumpulkan semua input
    $input['nama_kos']          = trim($_POST['nama_kos']          ?? '');
    $input['deskripsi']         = trim($_POST['deskripsi']         ?? '');
    $input['tipe']              = trim($_POST['tipe']              ?? '');
    $input['alamat']            = trim($_POST['alamat']            ?? '');
    $input['kecamatan']         = trim($_POST['kecamatan']         ?? '');
    $input['kota']              = trim($_POST['kota']              ?? '');
    $input['provinsi']          = trim($_POST['provinsi']          ?? '');
    $input['harga_per_bulan']   = (int)($_POST['harga_per_bulan']  ?? 0);
    $input['jumlah_kamar']      = (int)($_POST['jumlah_kamar']     ?? 1);
    $input['kamar_terisi']      = (int)($_POST['kamar_terisi']     ?? 0);
    $input['lat']               = !empty($_POST['lat']) ? (float)$_POST['lat'] : null;
    $input['lng']               = !empty($_POST['lng']) ? (float)$_POST['lng'] : null;
    $input['status']            = in_array($_POST['status'] ?? '', ['aktif','nonaktif']) ? $_POST['status'] : 'aktif';

    // Fasilitas
    foreach (['wifi','ac','kamar_mandi_dalam','parkir','dapur','laundry','security','cctv'] as $f) {
        $input[$f] = isset($_POST[$f]) ? 1 : 0;
    }

    // Validasi
    if (empty($input['nama_kos']))        $errors[] = 'Nama kos wajib diisi.';
    if (!in_array($input['tipe'], ['putra','putri','campur'])) $errors[] = 'Tipe kos tidak valid.';
    if (empty($input['alamat']))          $errors[] = 'Alamat wajib diisi.';
    if (empty($input['kota']))            $errors[] = 'Kota wajib diisi.';
    if ($input['harga_per_bulan'] <= 0)   $errors[] = 'Harga harus lebih dari 0.';
    if ($input['kamar_terisi'] > $input['jumlah_kamar']) $errors[] = 'Kamar terisi tidak boleh melebihi total kamar.';

    // Proses upload foto baru (opsional)
    $nama_foto_baru = $kos['foto_utama']; // Default: foto lama
    if (isset($_FILES['foto_baru']) && $_FILES['foto_baru']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_baru'];

        if ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Ukuran foto baru tidak boleh lebih dari 3MB.';
        }

        $mime         = mime_content_type($file['tmp_name']);
        $mime_allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $mime_allowed)) {
            $errors[] = 'Format foto harus JPG, PNG, atau WebP.';
        }

        if (empty($errors)) {
            $ext            = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nama_foto_baru = 'kos_' . $id_kos . '_' . time() . '.' . strtolower($ext);
            $tujuan         = __DIR__ . '/../../assets/images/kos/' . $nama_foto_baru;
            if (!move_uploaded_file($file['tmp_name'], $tujuan)) {
                $errors[] = 'Gagal menyimpan foto baru.';
                $nama_foto_baru = $kos['foto_utama'];
            } else {
                if (!empty($kos['foto_utama'])) {
                    $foto_lama = __DIR__ . '/../../assets/images/kos/' . $kos['foto_utama'];
                    if (file_exists($foto_lama)) unlink($foto_lama);
                }
            }
        }
    }

    // Simpan ke database jika tidak ada error
    if (empty($errors)) {
        $stmt_update = mysqli_prepare($koneksi,
            "UPDATE kos SET
                nama_kos=?, deskripsi=?, tipe=?,
                alamat=?, kecamatan=?, kota=?, provinsi=?,
                harga_per_bulan=?, jumlah_kamar=?, kamar_terisi=?,
                wifi=?, ac=?, kamar_mandi_dalam=?, parkir=?,
                dapur=?, laundry=?, security=?, cctv=?,
                foto_utama=?, lat=?, lng=?, status=?
             WHERE id=? AND pemilik_id=?"
        );
        mysqli_stmt_bind_param($stmt_update, 'sssssssiiiiiiiiiisddssii',
            $input['nama_kos'],
            $input['deskripsi'],
            $input['tipe'],
            $input['alamat'],
            $input['kecamatan'],
            $input['kota'],
            $input['provinsi'],
            $input['harga_per_bulan'],
            $input['jumlah_kamar'],
            $input['kamar_terisi'],
            $input['wifi'],
            $input['ac'],
            $input['kamar_mandi_dalam'],
            $input['parkir'],
            $input['dapur'],
            $input['laundry'],
            $input['security'],
            $input['cctv'],
            $nama_foto_baru,
            $input['lat'],
            $input['lng'],
            $input['status'],
            $id_kos,
            $user['id']
        );

        if (mysqli_stmt_execute($stmt_update)) {
            set_flash('sukses', 'Kos berhasil diperbarui! ✅');
            redirect(BASE_URL . '/pages/pemilik/index.php');
        } else {
            $errors[] = 'Terjadi kesalahan database. Coba lagi.';
        }
    }
}

$judul_halaman = "Edit Kos: " . $kos['nama_kos'];
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
            <a href="<?= BASE_URL ?>/pages/pemilik/pesan.php" class="sidebar-link">
                <span class="link-icon">📩</span> Pesan Masuk
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
                <h1 class="dashboard-title">✏️ Edit Kos</h1>
                <p class="dashboard-subtitle">Perbarui informasi kos: <?= htmlspecialchars($kos['nama_kos']) ?></p>
            </div>
            <a href="<?= BASE_URL ?>/pages/pemilik/index.php" class="btn-kosta-outline btn"
               style="font-size:13px;">
                ← Kembali
            </a>
        </div>

        <!-- Pesan Error -->
        <?php if (!empty($errors)): ?>
            <div class="alert-kosta alert-error" style="background:#FFF3F3;color:#b91c1c;border:1px solid #fca5a5;padding:14px 18px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:20px;">
                ⚠️ <strong>Ada kesalahan:</strong>
                <ul style="margin:8px 0 0;padding-left:20px;">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- FORM EDIT KOS -->
        <form action="<?= BASE_URL ?>/pages/pemilik/edit_kos.php?id=<?= $id_kos ?>"
              method="POST"
              enctype="multipart/form-data"
              id="form-edit-kos"
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
                                       value="<?= htmlspecialchars($input['nama_kos']) ?>"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-kosta">
                                <label for="tipe" class="form-label-kosta">
                                    Tipe Kos <span class="wajib">*</span>
                                </label>
                                <select id="tipe" name="tipe" class="form-select-kosta" required>
                                    <option value="putra"  <?= $input['tipe']==='putra'  ? 'selected':'' ?>>Putra</option>
                                    <option value="putri"  <?= $input['tipe']==='putri'  ? 'selected':'' ?>>Putri</option>
                                    <option value="campur" <?= $input['tipe']==='campur' ? 'selected':'' ?>>Campur</option>
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

                    <div class="form-group-kosta" style="max-width:300px;">
                        <label for="status" class="form-label-kosta">Status Listing</label>
                        <select id="status" name="status" class="form-select-kosta">
                            <option value="aktif"    <?= $input['status']==='aktif'    ? 'selected':'' ?>>✅ Aktif (Tampil di pencarian)</option>
                            <option value="nonaktif" <?= $input['status']==='nonaktif' ? 'selected':'' ?>>⏸️ Nonaktif (Sembunyikan)</option>
                        </select>
                    </div>

                </div><!-- /section 1 -->

                <!-- BAGIAN 2: Lokasi & Alamat -->
                <div class="form-card-section">
                    <p class="form-card-section-title">📍 Lokasi &amp; Alamat</p>

                    <div class="form-group-kosta">
                        <label for="alamat" class="form-label-kosta">
                            Alamat Lengkap <span class="wajib">*</span>
                        </label>
                        <textarea id="alamat" name="alamat"
                                  class="form-textarea-kosta"
                                  style="min-height:80px;"
                                  placeholder="Jl. Margonda Raya No. 45, RT 02/RW 03"
                                  required><?= htmlspecialchars($input['alamat']) ?></textarea>
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
                                       value="<?= htmlspecialchars($input['kota']) ?>"
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

                    <!-- Koordinat GPS (Opsional) -->
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

                <!-- BAGIAN 3: Harga & Ketersediaan -->
                <div class="form-card-section">
                    <p class="form-card-section-title">💰 Harga &amp; Ketersediaan</p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group-kosta">
                                <label for="harga_per_bulan" class="form-label-kosta">
                                    Harga per Bulan (Rp) <span class="wajib">*</span>
                                </label>
                                <input type="number" id="harga_per_bulan" name="harga_per_bulan"
                                       class="form-input-kosta"
                                       placeholder="1500000"
                                       min="1" step="50000"
                                       value="<?= htmlspecialchars($input['harga_per_bulan']) ?>"
                                       required>
                                <p class="form-hint">Masukkan angka tanpa titik atau koma. Contoh: 1500000</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-kosta">
                                <label for="jumlah_kamar" class="form-label-kosta">
                                    Jumlah Total Kamar <span class="wajib">*</span>
                                </label>
                                <input type="number" id="jumlah_kamar" name="jumlah_kamar"
                                       class="form-input-kosta"
                                       placeholder="10" min="1" max="200"
                                       value="<?= htmlspecialchars($input['jumlah_kamar']) ?>"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-kosta">
                                <label for="kamar_terisi" class="form-label-kosta">Kamar Terisi</label>
                                <input type="number" id="kamar_terisi" name="kamar_terisi"
                                       class="form-input-kosta" min="0"
                                       value="<?= htmlspecialchars($input['kamar_terisi']) ?>">
                                <p class="form-hint">Jumlah kamar yang sudah dihuni saat ini.</p>
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
                    $fasilitas_options = [
                        'wifi'               => ['📶', 'WiFi / Internet'],
                        'ac'                 => ['❄️', 'AC (Air Conditioner)'],
                        'kamar_mandi_dalam'  => ['🚿', 'Kamar Mandi Dalam'],
                        'parkir'             => ['🅿️', 'Parkir Kendaraan'],
                        'dapur'              => ['🍳', 'Dapur Bersama'],
                        'laundry'            => ['👕', 'Laundry'],
                        'security'           => ['👮', 'Petugas Keamanan'],
                        'cctv'               => ['📹', 'CCTV'],
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

                    <?php if (!empty($kos['foto_utama'])): ?>
                        <div style="margin-bottom:16px;">
                            <p class="form-label-kosta" style="margin-bottom:8px;">Foto saat ini:</p>
                            <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($kos['foto_utama']) ?>"
                                 alt="Foto saat ini"
                                 style="width:220px;height:145px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border);">
                        </div>
                    <?php endif; ?>

                    <!-- Upload zone -->
                    <div class="upload-zone" id="upload-zone"
                         onclick="document.getElementById('foto_baru').click();">
                        <div class="upload-icon">📷</div>
                        <p class="upload-text"><?= !empty($kos['foto_utama']) ? 'Klik untuk ganti foto' : 'Klik untuk pilih foto' ?></p>
                        <p class="upload-hint">JPG, PNG, atau WebP — Maks. 3MB</p>
                    </div>

                    <input type="file"
                           id="foto_baru"
                           name="foto_baru"
                           accept="image/jpeg,image/png,image/webp"
                           style="display:none;">

                    <div id="foto-preview-wrapper" style="display:none; margin-top:14px;">
                        <img id="foto-preview-img" src="" alt="Preview foto"
                             style="max-width:100%; max-height:220px; object-fit:cover; border-radius:8px; border:1px solid var(--color-border);">
                        <br>
                        <button type="button" id="hapus-preview"
                                style="margin-top:8px; font-size:12px; color:#b91c1c; background:none; border:none; cursor:pointer; font-weight:600;">
                            ✕ Hapus foto yang dipilih
                        </button>
                    </div>

                </div><!-- /section 6 -->

                <!-- Footer Form -->
                <div class="form-footer">
                    <a href="<?= BASE_URL ?>/pages/pemilik/index.php"
                       class="btn-kosta-outline btn">
                        Batal
                    </a>
                    <button type="submit" class="btn-kosta btn" id="btn-submit">
                        💾 Simpan Perubahan
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
var inputFoto  = document.getElementById('foto_baru');
var zone       = document.getElementById('upload-zone');
var preview    = document.getElementById('foto-preview-wrapper');
var previewImg = document.getElementById('foto-preview-img');
var hapusBtn   = document.getElementById('hapus-preview');

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
    inputFoto.value       = '';
    previewImg.src        = '';
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
        var dt = new DataTransfer();
        dt.items.add(file);
        inputFoto.files = dt.files;
        inputFoto.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once __DIR__ . '/../../components/scripts.php'; ?>
