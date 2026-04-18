<?php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

$judul_halaman = "Tentang Kami";

require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<style>
.hero-tentang {
    background: linear-gradient(135deg, #1C1C1C 0%, #2d1212 50%, #1C1C1C 100%);
    padding: 80px 0 64px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.hero-tentang::before {
    content: '';
    position: absolute;
    top: -60px; left: 50%;
    transform: translateX(-50%);
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(197,0,0,0.18) 0%, transparent 70%);
    pointer-events: none;
}
.hero-tentang .hero-icon {
    font-size: 52px;
    margin-bottom: 20px;
    display: block;
    animation: floatIcon 3s ease-in-out infinite;
}
@keyframes floatIcon {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-8px); }
}
.hero-tentang h1 {
    font-size: clamp(28px, 5vw, 42px);
    font-weight: 800;
    color: #fff;
    letter-spacing: -1.5px;
    margin-bottom: 14px;
    line-height: 1.2;
}
.hero-tentang p {
    color: rgba(255,255,255,0.55);
    font-size: 16px;
    max-width: 500px;
    margin: 0 auto;
    line-height: 1.7;
}


.section-tentang {
    padding: 80px 0;
}
.label-section {
    font-size: 11px;
    font-weight: 700;
    color: var(--color-accent);
    text-transform: uppercase;
    letter-spacing: 0.15em;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.label-section::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(197,0,0,0.2);
    max-width: 40px;
}
.misi-heading {
    font-size: clamp(22px, 3vw, 30px);
    font-weight: 800;
    letter-spacing: -0.8px;
    color: var(--color-text);
    line-height: 1.3;
    margin-bottom: 20px;
}
.misi-body p {
    color: var(--color-text-muted);
    line-height: 1.85;
    font-size: 15px;
    margin-bottom: 14px;
}
.misi-body p:last-child { margin-bottom: 0; }

/* Visual sisi kanan misi */
.misi-visual {
    background: linear-gradient(135deg, #1C1C1C, #2d1212);
    border-radius: var(--radius-lg);
    padding: 40px 36px;
    height: 100%;
    min-height: 280px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 20px;
    position: relative;
    overflow: hidden;
}
.misi-visual::before {
    content: '';
    position: absolute;
    bottom: -40px; right: -40px;
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(197,0,0,0.25), transparent 70%);
}
.misi-feature {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}
.misi-feature-icon {
    width: 40px; height: 40px;
    background: rgba(197,0,0,0.15);
    border: 1px solid rgba(197,0,0,0.3);
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.misi-feature-text strong {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 2px;
}
.misi-feature-text span {
    font-size: 12px;
    color: rgba(255,255,255,0.5);
    line-height: 1.5;
}

.nilai-section {
    background: var(--color-surface-alt);
    padding: 80px 0;
    border-top: 1px solid var(--color-border);
    border-bottom: 1px solid var(--color-border);
}
.nilai-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 36px 28px;
    text-align: center;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    height: 100%;
}
.nilai-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 36px rgba(197,0,0,0.10);
    border-color: rgba(197,0,0,0.25);
}
.nilai-icon-wrap {
    width: 64px; height: 64px;
    background: rgba(197,0,0,0.06);
    border: 1px solid rgba(197,0,0,0.12);
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px;
    margin: 0 auto 20px;
    transition: background 0.25s ease;
}
.nilai-card:hover .nilai-icon-wrap {
    background: rgba(197,0,0,0.12);
}
.nilai-card h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: 10px;
    letter-spacing: -0.3px;
}
.nilai-card p {
    font-size: 13.5px;
    color: var(--color-text-muted);
    line-height: 1.75;
    margin: 0;
}

.cta-tentang {
    padding: 80px 0;
    text-align: center;
}
.cta-tentang h2 {
    font-size: clamp(22px, 3vw, 30px);
    font-weight: 800;
    letter-spacing: -0.8px;
    margin-bottom: 12px;
}
.cta-tentang p {
    color: var(--color-text-muted);
    font-size: 15px;
    max-width: 450px;
    margin: 0 auto 28px;
    line-height: 1.7;
}
.cta-btn-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .misi-visual { min-height: auto; }
}
</style>

<div class="hero-tentang">
    <div class="container" style="position:relative; z-index:1;">
        <span class="hero-icon">🏠</span>
        <h1>Tentang <span style="color:var(--color-accent);">Kosta'</span></h1>
        <p>Platform pencari kos terpercaya yang menghubungkan pencari dan pemilik hunian secara langsung, transparan, dan efisien.</p>
    </div>
</div>


<section class="section-tentang">
    <div class="container">
        <div class="row align-items-center g-5">
            <!-- Teks Misi -->
            <div class="col-lg-6">
                <p class="label-section">Misi Kami</p>
                <h2 class="misi-heading">Membuat Pencarian Kos Jadi Mudah &amp; Menyenangkan</h2>
                <div class="misi-body">
                    <p>Kosta' hadir untuk menghubungkan pencari kos dengan pemilik kos secara langsung, transparan, dan efisien. Kami percaya bahwa menemukan hunian yang tepat seharusnya tidak memakan waktu berhari-hari.</p>
                    <p>Dibangun dengan teknologi sederhana namun handal, Kosta' bisa digunakan oleh siapa saja — dari mahasiswa baru hingga pekerja profesional yang butuh hunian cepat.</p>
                </div>
            </div>
            <!-- Visual Fitur -->
            <div class="col-lg-6">
                <div class="misi-visual">
                    <div class="misi-feature">
                        <div class="misi-feature-icon">📍</div>
                        <div class="misi-feature-text">
                            <strong>Pencarian Berbasis Lokasi</strong>
                            <span>Temukan kos terdekat dari tempat kerja atau kampusmu.</span>
                        </div>
                    </div>
                    <div class="misi-feature">
                        <div class="misi-feature-icon">💳</div>
                        <div class="misi-feature-text">
                            <strong>Pembayaran Otomatis</strong>
                            <span>Booking dan bayar langsung lewat platform, aman dan terverifikasi.</span>
                        </div>
                    </div>
                    <div class="misi-feature">
                        <div class="misi-feature-icon">⭐</div>
                        <div class="misi-feature-text">
                            <strong>Ulasan Penghuni Nyata</strong>
                            <span>Baca pengalaman penghuni sebelumnya sebelum memutuskan.</span>
                        </div>
                    </div>
                    <div class="misi-feature">
                        <div class="misi-feature-icon">📸</div>
                        <div class="misi-feature-text">
                            <strong>Galeri Foto Lengkap</strong>
                            <span>Lihat kondisi nyata kos dari berbagai sudut sebelum survey.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="nilai-section">
    <div class="container">
        <div class="text-center mb-5">
            <p class="label-section justify-content-center" style="justify-content:center;">Filosofi Kami</p>
            <h2 style="font-size:clamp(22px,3vw,30px); font-weight:800; letter-spacing:-0.8px; margin-bottom:10px;">Nilai-Nilai yang Kami Pegang</h2>
            <p style="color:var(--color-text-muted); font-size:15px; max-width:460px; margin:0 auto; line-height:1.7;">
                Setiap fitur dan keputusan kami dilandasi oleh empat nilai utama berikut.
            </p>
        </div>
        <div class="row g-4">
            <?php
            $nilai = [
                ['🔍', 'Transparansi',  'Informasi kos ditampilkan apa adanya — harga, fasilitas, dan kondisi nyata tanpa rekayasa.'],
                ['⚡', 'Kemudahan',     'Antarmuka bersih dan intuitif, bisa digunakan siapa saja tanpa perlu tutorial panjang.'],
                ['🔒', 'Keamanan',      'Data pengguna dilindungi dan transaksi diproses secara aman melalui payment gateway terverifikasi.'],
                ['🤝', 'Kepercayaan',   'Pemilik kos terverifikasi. Ulasan otentik dari penghuni nyata sebagai bahan pertimbangan.'],
            ];
            foreach ($nilai as [$ikon, $judul, $deskripsi]):
            ?>
            <div class="col-lg-3 col-md-6">
                <div class="nilai-card">
                    <div class="nilai-icon-wrap"><?= $ikon ?></div>
                    <h3><?= $judul ?></h3>
                    <p><?= $deskripsi ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cta-tentang">
    <div class="container">
        <h2>Siap Menemukan Kos Impianmu?</h2>
        <p>Bergabung dengan ribuan pencari kos yang sudah menemukan hunian nyaman lewat Kosta'.</p>
        <div class="cta-btn-group">
            <a href="<?= BASE_URL ?>/pages/cari.php" class="btn btn-kosta px-4 py-2" style="font-size:15px;">
                🔍 Cari Kos Sekarang
            </a>
            <a href="<?= BASE_URL ?>/pages/register.php" class="btn btn-kosta-outline px-4 py-2" style="font-size:15px;">
                Daftar Gratis
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<?php require_once __DIR__ . '/../components/scripts.php'; ?>
