<?php
/**
 * ====================================================
 * FILE: pages/404.php
 * FUNGSI: Halaman error 404 kustom dengan desain Kosta'.
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';

http_response_code(404);

$judul_halaman = "Halaman Tidak Ditemukan";
require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<div style="min-height:70vh; display:flex; align-items:center; justify-content:center; padding:60px 20px;">
    <div style="text-align:center; max-width:480px;">

        <!-- Nomor 404 besar -->
        <div style="font-size:clamp(80px, 15vw, 120px); font-weight:900; color:var(--color-text);
                    letter-spacing:-4px; line-height:1; margin-bottom:8px;
                    background:linear-gradient(135deg, #1C1C1C, #C50000);
                    -webkit-background-clip:text; -webkit-text-fill-color:transparent;">
            404
        </div>

        <div style="font-size:40px; margin-bottom:16px;">🏚️</div>

        <h1 style="font-size:22px; font-weight:800; color:var(--color-text);
                   letter-spacing:-0.5px; margin-bottom:10px;">
            Halaman Tidak Ditemukan
        </h1>
        <p style="font-size:14px; color:var(--color-text-muted); line-height:1.7; margin-bottom:32px;">
            Halaman yang kamu cari tidak ada atau sudah dipindahkan.
            Mungkin URL-nya salah, atau konten ini sudah dihapus.
        </p>

        <!-- Tombol aksi -->
        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/index.php" class="btn-kosta btn" style="padding:11px 24px;">
                🏠 Ke Beranda
            </a>
            <a href="<?= BASE_URL ?>/pages/cari.php" class="btn-kosta-outline btn" style="padding:11px 24px;">
                🔍 Cari Kos
            </a>
        </div>

        <!-- Quick links -->
        <div style="margin-top:40px; padding-top:32px; border-top:1px solid var(--color-border);">
            <p style="font-size:12px; color:var(--color-text-muted); margin-bottom:14px; font-weight:600; text-transform:uppercase; letter-spacing:.08em;">
                Halaman Populer
            </p>
            <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>/index.php" style="font-size:13px; color:var(--color-text-muted); text-decoration:none; padding:5px 12px; border:1px solid var(--color-border); border-radius:20px; transition:all .2s;" onmouseover="this.style.borderColor='var(--color-accent)';this.style.color='var(--color-accent)'" onmouseout="this.style.borderColor='var(--color-border)';this.style.color='var(--color-text-muted)'">Beranda</a>
                <a href="<?= BASE_URL ?>/pages/cari.php" style="font-size:13px; color:var(--color-text-muted); text-decoration:none; padding:5px 12px; border:1px solid var(--color-border); border-radius:20px; transition:all .2s;" onmouseover="this.style.borderColor='var(--color-accent)';this.style.color='var(--color-accent)'" onmouseout="this.style.borderColor='var(--color-border)';this.style.color='var(--color-text-muted)'">Cari Kos</a>
                <a href="<?= BASE_URL ?>/pages/tentang.php" style="font-size:13px; color:var(--color-text-muted); text-decoration:none; padding:5px 12px; border:1px solid var(--color-border); border-radius:20px; transition:all .2s;" onmouseover="this.style.borderColor='var(--color-accent)';this.style.color='var(--color-accent)'" onmouseout="this.style.borderColor='var(--color-border)';this.style.color='var(--color-text-muted)'">Tentang</a>
                <a href="<?= BASE_URL ?>/pages/login.php" style="font-size:13px; color:var(--color-text-muted); text-decoration:none; padding:5px 12px; border:1px solid var(--color-border); border-radius:20px; transition:all .2s;" onmouseover="this.style.borderColor='var(--color-accent)';this.style.color='var(--color-accent)'" onmouseout="this.style.borderColor='var(--color-border)';this.style.color='var(--color-text-muted)'">Login</a>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<?php require_once __DIR__ . '/../components/scripts.php'; ?>
