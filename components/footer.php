<?php
/**
 * ====================================================
 * FILE: components/footer.php
 * FUNGSI: Komponen Footer yang digunakan di semua halaman.
 * Cara pakai: require_once 'components/footer.php';
 * ====================================================
 */

// Footer tidak ditampilkan untuk role admin dan pemilik
// Mereka sudah memiliki layout dashboard tersendiri
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (function_exists('sudah_login') && sudah_login()) {
    $_u = user_login();
    if (isset($_u['role']) && in_array($_u['role'], ['admin', 'pemilik'])) {
        return;
    }
}

// Mengambil tahun saat ini secara otomatis agar tidak perlu update manual
$tahun_sekarang = date('Y');
?>

<!-- ===== FOOTER MULAI ===== -->
<footer class="footer-kosta">
    <div class="container">

        <!-- Baris utama footer: Brand + 3 kolom link -->
        <div class="row g-4 mb-4">

            <!-- Kolom 1: Brand & Deskripsi -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand mb-12">
                    Kos<span>ta'</span>
                </div>
                <p style="color:rgba(255,255,255,0.5); font-size:13px; line-height:1.7; margin-top: 12px; margin-bottom:20px;">
                    Platform pencari kos terpercaya di Indonesia.
                    Temukan hunian nyaman sesuai kebutuhan dan budget kamu.
                </p>
                <!-- Ikon sosial media -->
                <div style="display:flex; gap:10px;">
                    <a href="#" class="footer-social-icon" aria-label="Instagram">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="#" class="footer-social-icon" aria-label="TikTok">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                    </a>
                    <a href="#" class="footer-social-icon" aria-label="WhatsApp">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </a>
                </div>
            </div>

            <!-- Kolom 2: Link Pencarian -->
            <div class="col-lg-2 col-md-3 col-6">
                <h6>Pencarian</h6>
                <ul style="padding:0; list-style:none;">
                    <li><a href="<?= BASE_URL ?>/pages/cari.php?tipe=putra">Kos Putra</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/cari.php?tipe=putri">Kos Putri</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/cari.php?tipe=campur">Kos Campur</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/cari.php">Semua Kos</a></li>
                </ul>
            </div>

            <!-- Kolom 3: Informasi -->
            <div class="col-lg-2 col-md-3 col-6">
                <h6>Informasi</h6>
                <ul style="padding:0; list-style:none;">
                    <li><a href="<?= BASE_URL ?>/pages/tentang.php">Tentang Kami</a></li>
                    <li><a href="#">Blog & Tips</a></li>
                    <li><a href="#">Bantuan (FAQ)</a></li>
                    <li><a href="#">Kontak Kami</a></li>
                </ul>
            </div>

            <!-- Kolom 4: Pemilik Kos -->
            <div class="col-lg-4 col-md-6">
                <h6>Untuk Pemilik Kos</h6>
                <p style="color:rgba(255,255,255,0.5); font-size:13px; line-height:1.6; margin-bottom:14px;">
                    Punya kos kosong? Daftarkan sekarang dan jangkau
                    ribuan pencari kos setiap harinya. Gratis!
                </p>
                <a href="<?= BASE_URL ?>/pages/register.php"
                   class="btn-kosta btn"
                   style="font-size:13px; padding:9px 20px;">
                    Daftarkan Kos Saya →
                </a>
            </div>

        </div><!-- /row -->

        <!-- Garis bawah footer -->
        <div class="footer-bottom">
            <p style="margin:0;">
                &copy; <?= $tahun_sekarang ?> <strong style="color:#fff;">Kosta'</strong>.
                Dibuat dengan ❤️ untuk pencari hunian Indonesia.
            </p>
            <div style="display:flex; gap:16px;">
                <a href="#">Kebijakan Privasi</a>
                <a href="#">Syarat & Ketentuan</a>
            </div>
        </div>

    </div><!-- /container -->
</footer>
<!-- ===== FOOTER SELESAI ===== -->

<style>
/* CSS tambahan khusus untuk elemen footer social icon */
.footer-social-icon {
    width: 36px;
    height: 36px;
    background: rgba(255,255,255,0.08);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.6);
    transition: background 0.2s ease, color 0.2s ease;
}
.footer-social-icon:hover {
    background: var(--color-accent);
    color: #fff;
}
</style>
