<?php
/**
 * ====================================================
 * FILE: config/session.php
 * FUNGSI: Manajemen session terpusat untuk seluruh aplikasi.
 *
 * Session adalah cara PHP "mengingat" siapa yang sedang login.
 * Bayangkan session seperti gelang tamu di sebuah hotel —
 * selama gelang masih dipakai, hotel tahu kamu sudah check-in.
 *
 * Cara pakai: require_once di bagian PALING ATAS setiap file
 * yang butuh , SEBELUM output HTML apapun.
 * ==================autentikasi==================================
 */

// Konfigurasikan session agar lebih aman sebelum memulainya
// ini harus dipanggil SEBELUM session_start()
ini_set('session.cookie_httponly', 1);   // Cookie tidak bisa diakses JavaScript
ini_set('session.use_strict_mode', 1);   // Tolak session ID yang tidak dikenal

// session_start() = memulai atau melanjutkan session yang sudah ada.
// PHP menyimpan data session di server, dan mengirim cookie ke browser
// berisi ID session (bukan datanya langsung — ini yang bikin session aman).
// Fungsi ini HARUS dipanggil sebelum echo/print/HTML apapun.
if (session_status() === PHP_SESSION_NONE) {
    // Cek dulu agar tidak error jika session sudah dimulai sebelumnya
    session_start();
}


// ============================================================
// FUNGSI-FUNGSI HELPER AUTENTIKASI
// ============================================================

/**
 * Cek apakah pengguna sudah login.
 * @return bool true jika sudah login, false jika belum
 */
function sudah_login(): bool {
    // Cek apakah key 'user_id' ada di array $_SESSION
    return isset($_SESSION['user_id']);
}

/**
 * Paksa halaman hanya bisa diakses oleh pengguna yang sudah login.
 * Jika belum login, redirect ke halaman login.
 *
 * @param string $redirect_ke URL tujuan jika belum login (default: halaman login)
 */
function wajib_login(string $redirect_ke = ''): void {
    if (!sudah_login()) {
        if (empty($redirect_ke)) {
            // BASE_URL sudah didefinisikan di koneksi.php
            $redirect_ke = BASE_URL . '/pages/login.php?pesan=login_dulu';
        }
        // header('Location: ...') = instruksi browser untuk pindah ke URL lain
        header('Location: ' . $redirect_ke);
        exit; // WAJIB! Hentikan eksekusi script setelah redirect
    }
}

/**
 * Paksa halaman hanya bisa diakses oleh role tertentu.
 * Jika role tidak sesuai, redirect ke halaman yang sesuai.
 *
 * @param string|array $role_diizinkan Role yang boleh mengakses ('pemilik', 'pencari', 'admin')
 */
function wajib_role($role_diizinkan): void {
    // Panggil wajib_login() dulu — kalau belum login, langsung redirect
    wajib_login();

    // Ubah ke array agar bisa cek multiple role sekaligus
    if (!is_array($role_diizinkan)) {
        $role_diizinkan = [$role_diizinkan];
    }

    // Cek apakah role user saat ini ada di daftar yang diizinkan
    if (!in_array($_SESSION['user_role'], $role_diizinkan)) {
        // Role tidak sesuai, redirect ke dashboard yang benar
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
        exit;
    }
}

/**
 * Ambil data user yang sedang login dari session.
 * @return array|null Array data user atau null jika belum login
 */
function user_login(): ?array {
    if (!sudah_login()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'nama'  => $_SESSION['user_nama'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

/**
 * Redirect ke URL tertentu.
 * Pembungkus header() agar lebih mudah dipakai.
 *
 * @param string $url URL tujuan
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}


// ============================================================
// SISTEM FLASH MESSAGE
// Flash message = pesan sementara yang ditampilkan SEKALI
// (misalnya: "Login berhasil!" setelah redirect dari login)
// Setelah dibaca, pesan langsung dihapus dari session.
// ============================================================

/**
 * Simpan flash message ke session.
 *
 * @param string $tipe  Tipe pesan: 'sukses', 'error', 'info', 'warning'
 * @param string $pesan Isi pesan
 */
function set_flash(string $tipe, string $pesan): void {
    $_SESSION['_flash'][$tipe] = $pesan;
}

/**
 * Ambil dan hapus flash message dari session.
 * Mengembalikan string HTML siap tampil, atau '' jika tidak ada.
 *
 * @param string $tipe Tipe pesan yang ingin diambil
 * @return string HTML pesan atau string kosong
 */
function get_flash(string $tipe = ''): string {
    $output = '';

    if (empty($tipe)) {
        // Tampilkan semua tipe flash yang ada
        if (isset($_SESSION['_flash'])) {
            foreach ($_SESSION['_flash'] as $t => $pesan) {
                $output .= render_flash($t, $pesan);
            }
            unset($_SESSION['_flash']); // Hapus setelah dibaca
        }
    } else {
        if (isset($_SESSION['_flash'][$tipe])) {
            $output = render_flash($tipe, $_SESSION['_flash'][$tipe]);
            unset($_SESSION['_flash'][$tipe]); // Hapus hanya tipe ini
        }
    }

    return $output;
}

/**
 * Render HTML untuk satu flash message.
 * (fungsi private, dipakai oleh get_flash() saja)
 */
function render_flash(string $tipe, string $pesan): string {
    $map = [
        'sukses'  => ['✅', 'alert-success',  'F0FFF4', '15803d', '86efac'],
        'error'   => ['⚠️', 'alert-error',   'FFF3F3', 'b91c1c', 'fca5a5'],
        'info'    => ['ℹ️', 'alert-info',    'EFF6FF', '1d4ed8', 'bfdbfe'],
        'warning' => ['⚡', 'alert-warning', 'FFFBEB', '92400e', 'fcd34d'],
    ];

    [$ikon, , $bg, $warna, $border] = $map[$tipe] ?? ['📌', '', 'F9FAFB', '374151', 'D1D5DB'];

    return sprintf(
        '<div class="alert-kosta" style="background:#%s; color:#%s; border:1px solid #%s; padding:12px 16px; border-radius:8px; font-size:13px; font-weight:500; margin-bottom:16px;">
            %s %s
        </div>',
        $bg, $warna, $border,
        $ikon,
        htmlspecialchars($pesan)
    );
}
