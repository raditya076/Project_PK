<?php


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

<footer class="footer-kosta">
    <div class="container">

        <!-- Baris utama footer: Brand + Pencarian + Pemilik Kos -->
        <div class="row g-4 mb-4">

            <!-- Kolom 1: Brand & Deskripsi -->
            <div class="col-lg-7 col-md-6">
                <div class="footer-brand mb-12">
                    Kos<span>ta'</span>
                </div>
                <p style="color:rgba(255,255,255,0.5); font-size:13px; line-height:1.7; margin-top: 12px; margin-bottom:20px;">
                    Platform pencari kos terpercaya di Indonesia.
                    Temukan hunian nyaman sesuai kebutuhan dan budget kamu.
                </p>
                <!-- Ikon sosial media -->
                <div style="display:flex; gap:10px; align-items:center;">
                    <a href="https://www.instagram.com/fadlyyy_15?igsh=cm8yMGhtZXNtYmNk" target="_blank" rel="noopener noreferrer" class="footer-social-icon" aria-label="Instagram"><svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
                    <a href="https://www.facebook.com/share/1JwBcf54HB/" target="_blank" rel="noopener noreferrer" class="footer-social-icon" aria-label="Facebook"><svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                </div>
            </div>

            <!-- Kolom 2: Untuk Pemilik Kos -->
            <div class="col-lg-5 col-md-6">
                <h6>Untuk Pemilik Kos</h6>
                <p style="color:rgba(255,255,255,0.5); font-size:13px; line-height:1.7; margin-bottom:16px;">
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

<style>
/* CSS tambahan khusus untuk elemen footer social icon */
.footer-social-icon {
    width: 38px !important;
    height: 38px !important;
    min-width: 38px !important;
    background: rgba(255,255,255,0.08) !important;
    border: 1px solid rgba(255,255,255,0.12) !important;
    border-radius: 8px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: rgba(255,255,255,0.65) !important;
    padding: 0 !important;
    margin: 0 !important;
    line-height: 1 !important;
    text-decoration: none !important;
    box-sizing: border-box !important;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
.footer-social-icon svg {
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    flex-shrink: 0 !important;
    pointer-events: none;
}
.footer-social-icon:hover {
    background: var(--color-accent) !important;
    border-color: var(--color-accent) !important;
    color: #fff !important;
}
</style>
