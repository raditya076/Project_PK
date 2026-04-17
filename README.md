# 🏠 Kosta' — Sistem Informasi Pencarian Kos

Platform web pencarian dan pemesanan kos berbasis **PHP Native**, **MySQL**, dan **Bootstrap 5** dengan integrasi pembayaran otomatis via **Midtrans**.

---

## ✨ Fitur Utama

- 🔍 Pencarian & filter kos (kota, tipe, harga, fasilitas)
- 📍 Peta lokasi kos via OpenStreetMap (tanpa API key)
- 💳 Pembayaran otomatis via Midtrans (GoPay, OVO, QRIS, Transfer Bank, Kartu Kredit)
- ❤️ Simpan kos favorit
- ⚖️ Bandingkan hingga 3 kos sekaligus
- ⭐ Sistem ulasan & rating
- 💬 Pesan langsung ke pemilik kos
- 📊 Dashboard admin dengan statistik platform

---

## 🚀 Cara Setup (Lokal)

### 1. Prasyarat
- **Laragon** (Apache + MySQL + PHP 8.x)
- Browser modern (Chrome / Firefox)
- Koneksi internet (untuk Midtrans Snap, Bootstrap & Google Fonts CDN)

### 2. Clone / Download Proyek
```bash
git clone https://github.com/raditya076/Project_PK.git
cd Project_PK
```
Atau download ZIP lalu ekstrak ke folder `C:/laragon/www/Project1/`

### 3. Setup Database
Buka **phpMyAdmin** atau MySQL CLI, lalu jalankan:
```sql
DROP DATABASE IF EXISTS kosta_db;
CREATE DATABASE kosta_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kosta_db;
```
Kemudian **import file**: `kosta_db.sql`

### 4. Konfigurasi Koneksi
Salin file template konfigurasi:
```
config/koneksi.example.php  →  config/koneksi.php
```
Edit `config/koneksi.php` sesuai environment lokal kamu:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');            // default Laragon: kosong
define('DB_NAME', 'kosta_db');
define('BASE_URL', 'http://localhost/Project1');
```

### 5. Konfigurasi Midtrans
Salin file template:
```
config/midtrans.example.php  →  config/midtrans.php
```
Isi dengan Server Key & Client Key dari [dashboard.midtrans.com](https://dashboard.midtrans.com).

### 6. Buat Akun Admin
```
Akses: http://localhost/Project1/create_admin.php
→ Isi form → Klik "Buat Akun Admin"
→ ⚠️ HAPUS file create_admin.php setelah selesai!
```

### 7. Akun Demo
| Role    | Email           | Password |
|---------|-----------------|----------|
| Pencari | doni@gmail.com  | password |
| Pemilik | budi@gmail.com  | password |
| Admin   | admin@kosta.com | password |

---

## 🔄 Alur Booking & Pembayaran

```
[Pencari] Pilih kos → Isi form booking
              ↓
         Status: menunggu_pembayaran
              ↓
[Pencari] Klik "Bayar Sekarang" → Midtrans Snap popup terbuka
              ↓
         Pilih metode (GoPay / OVO / QRIS / Transfer Bank / Kartu Kredit)
              ↓
         Midtrans memproses & konfirmasi otomatis
              ↓
         Status: aktif  ← booking aktif otomatis
              ↓ (masa sewa berakhir)
[Pemilik] Tandai Selesai
              ↓
         Status: selesai
              ↓
[Pencari] Beri Ulasan ⭐
```

---

## 👥 Role Pengguna

### 👤 Pencari (`role: pencari`)
- Cari & filter kos (kota, tipe, harga, fasilitas)
- Lihat detail kos + peta lokasi interaktif
- ❤️ Simpan kos favorit
- ⚖️ Bandingkan hingga 3 kos sekaligus
- 📅 Booking kos & bayar via Midtrans
- 📋 Riwayat & status booking
- ⭐ Beri ulasan (hanya jika booking berstatus `selesai`)
- 💬 Kirim pesan ke pemilik kos

### 🏠 Pemilik (`role: pemilik`)
- Dashboard listing kos miliknya
- Tambah / edit / hapus listing kos + foto + lokasi GPS
- 🏁 Tandai booking selesai (pembayaran dikonfirmasi otomatis)
- 📬 Terima & balas pesan dari pencari

### ⚙️ Admin (`role: admin`)
- 📊 Dashboard statistik platform (user, kos, booking, revenue)
- 👥 Kelola semua pengguna
- 🏘️ Kelola semua listing kos
- 📅 Pantau semua transaksi booking
- ⭐ Moderasi ulasan

---

## 📁 Struktur Proyek

```
Project1/
├── config/
│   ├── koneksi.php             # ⚠️ Tidak di-upload (buat dari .example)
│   ├── koneksi.example.php     # Template konfigurasi DB
│   ├── midtrans.php            # ⚠️ Tidak di-upload (buat dari .example)
│   ├── midtrans.example.php    # Template konfigurasi Midtrans
│   └── session.php             # Auth, flash message, redirect helper
├── components/
│   ├── head.php                # <head>, Bootstrap CSS, meta tags
│   ├── navbar.php              # Navbar responsif + dropdown profil
│   ├── footer.php              # Footer global
│   └── scripts.php            # Bootstrap JS + script global
├── pages/
│   ├── login.php               # Form login
│   ├── register.php            # Form registrasi (pilih role)
│   ├── logout.php              # Handler logout
│   ├── dashboard.php           # Router role → halaman yang sesuai
│   ├── detail.php              # Detail kos + booking + review
│   ├── cari.php                # Pencarian & filter kos
│   ├── booking.php             # Form booking kos
│   ├── pembayaran.php          # Halaman pembayaran via Midtrans
│   ├── proses_bayar.php        # AJAX: minta Snap Token ke Midtrans
│   ├── callback_bayar.php      # Callback setelah Midtrans selesai
│   ├── notifikasi_midtrans.php # Webhook dari Midtrans (server-side)
│   ├── proses_cek_bayar.php    # AJAX polling status pembayaran
│   ├── riwayat.php             # Riwayat booking (pencari)
│   ├── tentang.php             # Halaman tentang Kosta'
│   ├── 404.php                 # Halaman error 404
│   ├── pemilik/
│   │   ├── index.php           # Dashboard pemilik
│   │   ├── booking.php         # Daftar booking masuk
│   │   ├── update_booking.php  # Handler tandai selesai
│   │   ├── tambah_kos.php      # Form tambah listing kos
│   │   ├── edit_kos.php        # Form edit listing kos
│   │   └── hapus_kos.php       # Handler hapus kos
│   ├── admin/
│   │   ├── index.php           # Dashboard admin (statistik)
│   │   ├── sidebar.php         # Sidebar navigasi admin
│   │   ├── users.php           # Kelola pengguna
│   │   ├── kos.php             # Kelola listing kos
│   │   ├── bookings.php        # Lihat semua booking
│   │   └── reviews.php         # Moderasi ulasan
│   ├── booking/
│   │   └── batalkan.php        # Handler batalkan booking
│   ├── favorit/
│   │   └── index.php           # Kos favorit (pencari)
│   ├── review/
│   │   └── kirim.php           # Submit ulasan
│   ├── pesan/
│   │   └── kirim.php           # Kirim pesan ke pemilik
│   └── bandingkan/
│       └── index.php           # Halaman perbandingan kos
├── assets/
│   ├── css/                    # File styling per halaman
│   └── images/
│       └── kos/                # Foto kos (diisi saat runtime)
├── kosta_db.sql                # 🗃️ Schema + data awal — import ini!
├── create_admin.php            # Script buat akun admin (hapus setelah pakai)
├── .htaccess                   # URL rewrite rules
└── .gitignore
```

---

## 🛡️ Keamanan

- Password di-hash dengan `password_hash()` (bcrypt)
- Prepared Statement di semua query → anti SQL Injection
- Validasi kepemilikan di setiap handler (pemilik_id / penyewa_id)
- Proteksi race condition dengan `WHERE status = 'current_status'`
- File `config/koneksi.php` & `config/midtrans.php` di-ignore Git
- Flash message via session (tidak bisa di-forge dari URL)

---

## ⚠️ Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Midtrans Snap tidak muncul | Pastikan `MIDTRANS_CLIENT_KEY` sudah benar di `config/midtrans.php` |
| Tombol bayar error / blank | Buka DevTools (F12) → tab Console untuk melihat detail error |
| Halaman 404 di semua route | Periksa `.htaccess`, pastikan `mod_rewrite` aktif di Apache/Laragon |
| Foto kos tidak tampil | Pastikan folder `assets/images/kos/` writable (`chmod 755`) |
| Koneksi DB gagal | Cek isi `config/koneksi.php`, pastikan MySQL aktif di Laragon |
| Session tidak tersimpan | Pastikan `session_start()` dipanggil sebelum output apapun |

---

## 🏗️ Tech Stack

| Teknologi | Kegunaan |
|-----------|----------|
| PHP 8.x (Native) | Backend & server-side logic |
| MySQL | Database |
| Bootstrap 5 | UI framework |
| Midtrans Snap | Payment gateway |
| OpenStreetMap + iframe | Peta lokasi kos (tanpa API key) |
| Laragon | Local development server |
