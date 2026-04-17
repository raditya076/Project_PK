# 🏠 Kosta' — Sistem Informasi Pencarian Kos

Platform web pencarian dan pemesanan kos berbasis PHP native, MySQL, dan Bootstrap 5.

---

## 🚀 Setup Cepat

### 1. Prasyarat
- **Laragon** (Apache + MySQL + PHP 8.x)
- Browser modern (Chrome / Firefox)
- Koneksi internet (untuk Bootstrap & Google Fonts CDN)

### 2. Instalasi Database
```sql
-- Buka phpMyAdmin atau MySQL CLI, lalu:
DROP DATABASE IF EXISTS kosta_db;
CREATE DATABASE kosta_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kosta_db;
-- Kemudian import: kosta_complete.sql
```

### 3. Konfigurasi
Edit `config/koneksi.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // default Laragon: kosong
define('DB_NAME', 'kosta_db');
define('BASE_URL', 'http://localhost/Project1');
```

### 4. Buat Akun Admin (Pertama Kali)
```
Akses: http://localhost/Project1/create_admin.php
→ Isi form → Klik "Buat Akun Admin"
→ HAPUS file create_admin.php setelah selesai!
```

### 5. Akun Demo
| Role    | Email              | Password    |
|---------|--------------------|-------------|
| Pencari | budi@gmail.com     | password123 |
| Pemilik | pemilik@gmail.com  | password123 |
| Admin   | admin@kosta.com    | kosta_admin_2024 |

---

## 📁 Struktur Proyek

```
Project1/
├── config/
│   ├── koneksi.php          # Koneksi DB + BASE_URL
│   └── session.php          # Auth, flash message, redirect helper
├── components/
│   ├── head.php             # <head>, Bootstrap CSS, meta tags
│   ├── navbar.php           # Navbar responsif + dropdown profil
│   ├── footer.php           # Footer global
│   └── scripts.php          # Bootstrap JS + script global
├── pages/
│   ├── login.php            # Form login
│   ├── register.php         # Form registrasi (pilih role)
│   ├── logout.php           # Handler logout
│   ├── dashboard.php        # Router role → halaman yang sesuai
│   ├── detail.php           # Detail kos + booking + review
│   ├── cari.php             # Pencarian & filter kos
│   ├── booking.php          # Form booking kos
│   ├── pembayaran.php       # Upload bukti pembayaran
│   ├── riwayat.php          # Riwayat booking (pencari)
│   ├── tentang.php          # Halaman tentang Kosta'
│   ├── 404.php              # Halaman error 404
│   ├── pemilik/
│   │   ├── index.php        # Dashboard pemilik
│   │   ├── booking.php      # Booking masuk (konfirmasi/tolak)
│   │   ├── update_booking.php # Handler status booking
│   │   ├── tambah_kos.php   # Form tambah listing kos
│   │   └── edit_kos.php     # Form edit listing kos
│   ├── admin/
│   │   ├── index.php        # Dashboard admin (statistik)
│   │   ├── sidebar.php      # Sidebar navigasi admin
│   │   ├── users.php        # Kelola pengguna
│   │   ├── kos.php          # Kelola listing kos
│   │   ├── bookings.php     # Lihat semua booking
│   │   └── reviews.php      # Kelola ulasan
│   ├── booking/
│   │   └── batalkan.php     # Handler batalkan booking
│   ├── favorit/
│   │   └── index.php        # Kos favorit (pencari)
│   ├── review/
│   │   └── store.php        # Submit ulasan
│   └── pesan/
│       └── index.php        # Pesan antar user
├── assets/
│   ├── css/
│   │   ├── style.css        # Design system global
│   │   ├── auth.css         # Login & register
│   │   ├── home.css         # Landing page
│   │   ├── detail.css       # Halaman detail kos
│   │   ├── dashboard.css    # Dashboard pemilik
│   │   ├── transaction.css  # Booking, pembayaran, riwayat
│   │   ├── admin.css        # Panel admin
│   │   ├── compare.css      # Perbandingan kos
│   │   ├── maps.css         # Halaman peta
│   │   └── review.css       # Komponen ulasan
│   └── images/
│       └── bukti_bayar/     # Upload bukti transfer (writable!)
├── kosta_complete.sql       # 🗃️ Schema + data awal (pakai ini!)
├── create_admin.php         # Script buat akun admin (hapus setelah pakai)
└── .htaccess                # URL rewrite rules
```

---

## 👥 Role Pengguna

### 👤 Pencari (`role: pencari`)
- Cari & filter kos (kota, tipe, harga, fasilitas)
- Lihat detail kos + peta lokasi
- ❤️ Simpan kos favorit
- 📅 Booking kos & upload bukti pembayaran
- 📋 Riwayat booking
- ⭐ Beri ulasan (hanya jika booking `selesai`)

### 🏠 Pemilik (`role: pemilik`)
- Dashboard listing kos miliknya
- Tambah / edit / hapus listing kos
- ✅ Konfirmasi / ❌ Tolak pembayaran
- 🏁 Tandai booking selesai
- 📬 Terima & balas pesan

### ⚙️ Admin (`role: admin`)
- 📊 Dashboard statistik platform
- 👥 Kelola semua pengguna (aktifkan/nonaktifkan)  
- 🏘️ Kelola semua listing kos
- 📅 Pantau semua booking
- ⭐ Moderasi ulasan

---

## 🔄 Alur Booking

```
[Pencari] Pilih kos → Isi form booking
              ↓
         Status: menunggu_pembayaran
              ↓
[Pencari] Upload bukti transfer
              ↓
         Status: dibayar
              ↓
[Pemilik] Konfirmasi? ──Ya──→ Status: aktif
                    └──Tidak──→ Status: ditolak
              ↓ (masa sewa berakhir)
[Pemilik] Tandai Selesai
              ↓
         Status: selesai
              ↓
[Pencari] Beri Ulasan ⭐
```

---

## 🛡️ Keamanan
- Password di-hash dengan `password_hash()` (bcrypt)
- Prepared Statement di semua query → anti SQL Injection
- Validasi kepemilikan (pemilik_id / penyewa_id) di setiap handler
- Proteksi race condition dengan `WHERE status = 'current_status'`
- Validasi file upload dengan `mime_content_type()` (bukan hanya ekstensi)
- Flash message via session (tidak bisa di-forge dari URL)

---

## ⚠️ Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Dropdown profil tidak bisa diklik | Pastikan tidak ada SRI hash yang salah di `scripts.php` |
| Upload bukti bayar gagal | Pastikan folder `assets/images/bukti_bayar/` writable (`chmod 755`) |
| Halaman 404 di semua route | Periksa `.htaccess` dan pastikan `mod_rewrite` aktif di Apache |
| Session tidak tersimpan | Pastikan `session_start()` dipanggil sebelum output apapun |
| Koneksi DB gagal | Cek username/password di `config/koneksi.php`, pastikan MySQL aktif |

---

## 📦 SQL Files
| File | Keterangan |
|------|-----------|
| `kosta_complete.sql` | ✅ **Gunakan ini** — gabungan semua schema |
| `database.sql` | Schema awal (fase 1-3) |
| `favorit_table.sql` | Tabel favorit (fase 3) |
| `fase4_tables.sql` | Landmark & pesan (fase 4) |
| `fase5_tables.sql` | Reviews (fase 5) |
| `booking_tables.sql` | Booking & transaksi (fase 5) |

> 💡 **Rekomendasi:** Selalu gunakan `kosta_complete.sql` untuk fresh install.
