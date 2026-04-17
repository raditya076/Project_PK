<?php
/**
 * ====================================================
 * FILE: pages/tentang.php
 * FUNGSI: Halaman Tentang Kami / About Us
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

$judul_halaman = "Tentang Kami";

require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<!-- Hero Kecil -->
<div style="background:linear-gradient(135deg, #1C1C1C, #2d1f1f); padding:64px 0; text-align:center;">
    <div class="container">
        <div style="font-size:48px; margin-bottom:16px;">🏠</div>
        <h1 style="font-size:36px; font-weight:800; color:#fff; letter-spacing:-1px; margin-bottom:12px;">
            Tentang <span style="color:var(--color-accent);">Kosta'</span>
        </h1>
        <p style="color:rgba(255,255,255,0.6); font-size:15px; max-width:480px; margin:0 auto;">
            Platform pencari kos terpercaya untuk generasi modern Indonesia.
        </p>
    </div>
</div>

<section style="padding:72px 0;">
    <div class="container">

        <!-- Misi -->
        <div class="row align-items-center g-5 mb-5">
            <div class="col-lg-6">
                <p style="font-size:12px; font-weight:700; color:var(--color-accent); text-transform:uppercase; letter-spacing:0.1em;">Misi Kami</p>
                <h2 style="font-size:28px; font-weight:800; letter-spacing:-0.5px; margin-bottom:16px;">
                    Membuat Pencarian Kos Jadi Mudah & Menyenangkan
                </h2>
                <p style="color:var(--color-text-muted); line-height:1.8;">
                    Kosta' hadir untuk menghubungkan pencari kos dengan pemilik kos secara langsung,
                    transparan, dan efisien. Kami percaya bahwa menemukan hunian yang tepat
                    seharusnya tidak memakan waktu berhari-hari.
                </p>
                <p style="color:var(--color-text-muted); line-height:1.8;">
                    Dibangun dengan teknologi sederhana namun handal, Kosta' bisa digunakan
                    oleh siapa saja — dari mahasiswa baru hingga pekerja profesional.
                </p>
            </div>
        </div>

        <!-- Nilai-nilai -->
        <div style="background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:48px;">
            <h2 style="text-align:center; font-size:22px; font-weight:800; margin-bottom:36px;">
                Nilai-Nilai Kami
            </h2>
            <div class="row g-4">
                <?php
                $nilai = [
                    ['🔍', 'Transparansi',   'Informasi kos ditampilkan apa adanya — harga, fasilitas, dan kondisi nyata.'],
                    ['⚡', 'Kemudahan',      'Antarmuka yang bersih dan intuitif, bisa digunakan siapa saja tanpa tutorial.'],
                    ['🔒', 'Keamanan',       'Data pengguna dilindungi dan transaksi dilakukan secara langsung antar pihak.'],
                    ['🤝', 'Kepercayaan',    'Pemilik kos terverifikasi. Ulasan nyata dari penghuni sebelumnya.'],
                ];
                foreach ($nilai as [$ikon, $judul, $deskripsi]):
                ?>
                <div class="col-lg-3 col-md-6">
                    <div style="text-align:center;">
                        <div style="width:56px; height:56px; background:rgba(197,0,0,0.08); border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; font-size:24px; margin:0 auto 14px;">
                            <?= $ikon ?>
                        </div>
                        <h3 style="font-size:15px; font-weight:700; margin-bottom:8px;"><?= $judul ?></h3>
                        <p style="font-size:13px; color:var(--color-text-muted); line-height:1.7; margin:0;"><?= $deskripsi ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<?php require_once __DIR__ . '/../components/scripts.php'; ?>
