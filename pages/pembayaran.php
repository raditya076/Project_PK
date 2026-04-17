<?php
/**
 * ====================================================
 * FILE: pages/pembayaran.php
 * FUNGSI: Halaman pembayaran — pilih metode & upload
 *         bukti transfer untuk booking yang sudah dibuat.
 *
 * ALUR STATUS yang terjadi di file ini:
 *   Status SEBELUM: 'menunggu_pembayaran'
 *   Status SESUDAH: 'dibayar'  (setelah user upload bukti)
 *
 * User memilih metode: Transfer Bank / E-wallet / QRIS
 * Kemudian upload foto/screenshot bukti pembayaran.b 
 * ====================================================
 */
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/midtrans.php'; // Midtrans config & SDK

wajib_login();
$user = user_login();

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) redirect(BASE_URL . '/pages/riwayat.php');

// Ambil data booking + kos
// Pastikan booking ini milik user yang login
$stmt = mysqli_prepare($koneksi,
    "SELECT b.*, k.nama_kos, k.foto_utama, k.kota, k.harga_per_bulan
     FROM bookings b
     JOIN kos k ON b.kos_id = k.id
     WHERE b.id = ? AND b.penyewa_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user['id']);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$booking) {
    set_flash('error', 'Booking tidak ditemukan.');
    redirect(BASE_URL . '/pages/riwayat.php');
}

// Hanya bisa bayar jika status masih 'menunggu_pembayaran'
if ($booking['status'] !== 'menunggu_pembayaran') {
    set_flash('info', 'Status booking ini sudah: ' . ucwords(str_replace('_', ' ', $booking['status'])));
    redirect(BASE_URL . '/pages/riwayat.php');
}

$errors = [];

// ============================================================
// PROSES UPLOAD BUKTI PEMBAYARAN (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metode = trim($_POST['metode_pembayaran'] ?? '');

    // Validasi metode
    $metode_valid = ['transfer_bank', 'ewallet', 'qris'];
    if (!in_array($metode, $metode_valid)) {
        $errors[] = 'Pilih metode pembayaran terlebih dahulu.';
    }

    // Validasi file upload
    if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload bukti pembayaran wajib dilampirkan.';
    } else {
        $file = $_FILES['bukti_pembayaran'];

        // Cek ukuran file (max 5MB)
        // 5 * 1024 * 1024 = 5.242.880 bytes
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Ukuran file maksimal 5MB.';
        }

        // Cek tipe file menggunakan mime_content_type()
        // Ini lebih aman daripada cek ekstensi (ekstensi bisa dipalsukan!)
        $mime         = mime_content_type($file['tmp_name']);
        $mime_ok      = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $mime_ok)) {
            $errors[] = 'Bukti pembayaran harus berupa gambar (JPG, PNG, WebP, atau GIF).';
        }
    }

    if (empty($errors)) {
        // Buat nama file unik: bukti_{booking_id}_{timestamp}.ext
        $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nama_file  = 'bukti_' . $booking_id . '_' . time() . '.' . strtolower($ext);
        $folder     = __DIR__ . '/../assets/images/bukti_bayar/';
        $tujuan     = $folder . $nama_file;

        if (move_uploaded_file($file['tmp_name'], $tujuan)) {
            // ===========================================================
            // UPDATE STATUS BOOKING
            //
            // Di sinilah "state machine" bekerja:
            // Kita mengubah status dari 'menunggu_pembayaran' → 'dibayar'
            //
            // SQL UPDATE menggunakan WHERE clause ganda:
            //   - id = ?              : pastikan kita update booking yang benar
            //   - penyewa_id = ?      : pastikan hanya pemilik booking yang bisa update
            //   - status = 'menunggu_pembayaran' : keamanan tambahan, hindari
            //                          update jika status sudah berubah
            //                          (race condition protection)
            // ===========================================================
            // Simpan: metode dipilih, nama file bukti, waktu bayar, dan ubah status
            // WHERE ganda: cegah update ganda jika user refresh halaman
            $stmt_update = mysqli_prepare($koneksi,
                "UPDATE bookings
                 SET
                     metode_pembayaran = ?,
                     bukti_pembayaran  = ?,
                     tanggal_bayar     = NOW(),
                     status            = 'dibayar'
                 WHERE id           = ?
                   AND penyewa_id   = ?
                   AND status       = 'menunggu_pembayaran'"
            );
            mysqli_stmt_bind_param($stmt_update, 'ssii',
                $metode, $nama_file, $booking_id, $user['id']
            );

            if (mysqli_stmt_execute($stmt_update) && mysqli_stmt_affected_rows($stmt_update) > 0) {
                // Status berhasil diubah ke 'dibayar'
                // Sekarang tunggu pemilik kos untuk mengkonfirmasi
                set_flash('sukses',
                    'Bukti pembayaran berhasil dikirim! 🎉 ' .
                    'Pemilik akan mengkonfirmasi dalam 1×24 jam. ' .
                    'Pantau statusnya di Riwayat Booking.'
                );
                redirect(BASE_URL . '/pages/riwayat.php');
            } else {
                // Jika affected_rows = 0, berarti status sudah berubah
                $errors[] = 'Gagal memperbarui status. Status booking mungkin sudah berubah.';
            }
        } else {
            $errors[] = 'Gagal menyimpan file. Periksa izin folder uploads.';
        }
    }
}

// Harga & tanggal
$harga_format   = 'Rp ' . number_format($booking['total_harga'], 0, ',', '.');
$tgl_masuk_fmt  = date('d F Y', strtotime($booking['tanggal_masuk']));
$tgl_keluar_fmt = date('d F Y', strtotime($booking['tanggal_keluar']));

$extra_head    = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/transaction.css">';
$judul_halaman = "Pembayaran Booking #" . $booking_id;
$css_tambahan  = "detail.css";

require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/navbar.php';
?>

<div class="container" style="padding-top:12px;"><?= get_flash() ?></div>

<div class="breadcrumb-bar">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/riwayat.php">Riwayat</a></li>
                <li class="breadcrumb-item active">Pembayaran</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container">
<div class="payment-layout">

    <!-- ===== KOLOM KIRI: Form Pembayaran ===== -->
    <div>

            <!-- ================================
                 TOMBOL BAYAR MIDTRANS (Utama)
                 Klik → AJAX ke proses_bayar.php
                      → Snap popup terbuka
                 ================================ -->
            <div class="booking-form-card" style="margin-bottom:20px;border:2px solid var(--color-accent);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="font-size:28px;">⚡</div>
                    <div>
                        <div style="font-size:15px;font-weight:800;color:var(--color-text);">Bayar Otomatis dengan Midtrans</div>
                        <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px;">GoPay · OVO · QRIS · Transfer Bank · Kartu Kredit</div>
                    </div>
                </div>
                <button type="button"
                        id="btn-bayar-midtrans"
                        onclick="bayarDenganMidtrans()"
                        style="width:100%;padding:14px;font-size:15px;font-weight:700;
                               background:var(--color-accent);color:#fff;border:none;
                               border-radius:8px;cursor:pointer;transition:all .2s;
                               font-family:'Plus Jakarta Sans',sans-serif;
                               box-shadow:0 4px 14px rgba(197,0,0,0.3);">
                    💳 Bayar Sekarang — <?= $harga_format ?>
                </button>

                <!-- Div untuk menampilkan error/status secara visible di halaman -->
                <div id="midtrans-error" style="display:none;margin-top:12px;
                     padding:10px 14px;background:#FFF3F3;color:#b91c1c;
                     border:1px solid #fca5a5;border-radius:8px;
                     font-size:13px;font-weight:500;"></div>

                <p style="font-size:11px;color:var(--color-text-muted);text-align:center;margin-top:10px;margin-bottom:0;">
                    🔒 Transaksi aman & terenkripsi oleh Midtrans
                </p>
            </div>

            <div style="text-align:center;margin-bottom:16px;">
                <span style="font-size:12px;color:var(--color-text-muted);">
                    — atau bayar manual dengan upload bukti —
                </span>
            </div>

            

            <!-- Error -->
            <?php if (!empty($errors)): ?>
                <div class="alert-kosta error" style="margin-bottom:20px;">
                    <ul style="margin:0;padding-left:20px;">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- PILIH METODE PEMBAYARAN -->
            <form method="POST" action="<?= BASE_URL ?>/pages/pembayaran.php?booking_id=<?= $booking_id ?>"
                  enctype="multipart/form-data" id="form-bayar">

                <label class="form-label-kosta" style="margin-bottom:12px;display:block;">
                    Pilih Metode Pembayaran *
                </label>

                <!-- Tab Metode: Transfer Bank -->
                <div class="metode-tabs">
                    <div>
                        <input type="radio" class="metode-tab" name="metode_pembayaran"
                               id="tab_bank" value="transfer_bank" checked
                               onchange="gantiPanel('bank')">
                        <label class="metode-tab-label" for="tab_bank">
                            🏦 Transfer Bank
                        </label>
                    </div>
                    <div>
                        <input type="radio" class="metode-tab" name="metode_pembayaran"
                               id="tab_ewallet" value="ewallet"
                               onchange="gantiPanel('ewallet')">
                        <label class="metode-tab-label" for="tab_ewallet">
                            📱 E-Wallet
                        </label>
                    </div>
                    <div>
                        <input type="radio" class="metode-tab" name="metode_pembayaran"
                               id="tab_qris" value="qris"
                               onchange="gantiPanel('qris')">
                        <label class="metode-tab-label" for="tab_qris">
                            📷 QRIS
                        </label>
                    </div>
                </div>

                <!-- Panel: Transfer Bank -->
                <div id="panel-bank" class="metode-panel aktif" style="margin-bottom:20px;">
                    <p style="font-size:12px;color:var(--color-text-muted);margin-bottom:12px;">
                        Transfer ke salah satu rekening di bawah ini, lalu upload bukti transfernya.
                    </p>
                    <?php
                    $rekening = [
                        ['bank' => 'BCA', 'no' => '8890001234', 'nama' => "PT Kosta' Indonesia"],
                        ['bank' => 'Mandiri', 'no' => '1230045678', 'nama' => "PT Kosta' Indonesia"],
                        ['bank' => 'BRI', 'no' => '0088501234567', 'nama' => "PT Kosta' Indonesia"],
                    ];
                    foreach ($rekening as $r):
                    ?>
                    <div class="bank-rekening-card">
                        <button type="button" class="bank-copy-btn"
                                onclick="salinNoRek('<?= $r['no'] ?>', this)">
                            Salin
                        </button>
                        <div class="bank-nama"><?= $r['bank'] ?></div>
                        <div class="bank-nomor"><?= $r['no'] ?></div>
                        <div class="bank-atas-nama">a.n. <?= $r['nama'] ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="payment-step" style="padding:12px 0;">
                        <div class="payment-step-num">!</div>
                        <div class="payment-step-content">
                            <div class="payment-step-title">Jumlah Transfer Tepat</div>
                            <div class="payment-step-detail">
                                Transfer tepat sebesar <strong style="color:var(--color-accent);"><?= $harga_format ?></strong>
                                agar verifikasi lebih cepat. Jangan tambahkan kode unik.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel: E-Wallet -->
                <div id="panel-ewallet" class="metode-panel" style="margin-bottom:20px;">
                    <?php
                    $ewallet = [
                        ['nama'=>'GoPay',  'no'=>'0812-0000-1234', 'nama_akun'=>"Kosta' Indonesia"],
                        ['nama'=>'OVO',    'no'=>'0812-0000-1234', 'nama_akun'=>"Kosta' Indonesia"],
                        ['nama'=>'DANA',   'no'=>'0812-0000-1234', 'nama_akun'=>"Kosta' Indonesia"],
                        ['nama'=>'ShopeePay','no'=>'0812-0000-1234','nama_akun'=>"Kosta' Indonesia"],
                    ];
                    foreach ($ewallet as $e):
                    ?>
                    <div class="bank-rekening-card">
                        <button type="button" class="bank-copy-btn"
                                onclick="salinNoRek('<?= $e['no'] ?>', this)">
                            Salin
                        </button>
                        <div class="bank-nama"><?= $e['nama'] ?></div>
                        <div class="bank-nomor"><?= $e['no'] ?></div>
                        <div class="bank-atas-nama">a.n. <?= $e['nama_akun'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Panel: QRIS -->
                <div id="panel-qris" class="metode-panel" style="margin-bottom:20px;">
                    <div class="qris-container">
                        <img src="<?= BASE_URL ?>/assets/images/qris_kosta.png"
                             alt="QRIS Kosta' — Scan untuk Bayar">
                        <div class="qris-hint">
                            📷 Scan QR code di atas menggunakan aplikasi m-banking atau
                            dompet digital favoritmu (GoPay, OVO, DANA, dll.)
                        </div>
                        <div style="margin-top:10px;font-size:13px;font-weight:800;color:var(--color-accent);">
                            <?= $harga_format ?>
                        </div>
                    </div>
                </div>

                <!-- UPLOAD BUKTI PEMBAYARAN -->
                <div class="form-group-kosta" style="margin-top:24px;">
                    <label class="form-label-kosta">Upload Bukti Pembayaran *</label>
                    <div class="upload-area" onclick="document.getElementById('input-bukti').click()">
                        <input type="file" id="input-bukti" name="bukti_pembayaran"
                               accept="image/*" onchange="previewBukti(this)">
                        <span class="upload-icon">📄</span>
                        <div class="upload-text">
                            <strong>Klik untuk upload</strong> atau drag & drop<br>
                            Screenshot atau foto struk transfer. Maks. 5MB.
                        </div>
                        <img id="preview-bukti" class="upload-preview" alt="Preview bukti">
                    </div>
                </div>

                <button type="submit" class="btn-kosta btn"
                        style="width:100%;margin-top:20px;padding:13px;font-size:15px;">
                    📤 Kirim Bukti Pembayaran
                </button>

                <p style="font-size:11px;color:var(--color-text-muted);text-align:center;margin-top:12px;">
                    Pembayaranmu akan diverifikasi pemilik kos dalam <strong>1×24 jam</strong>.
                    Kamu akan dihubungi jika ada konfirmasi.
                </p>

            </form>
        </div>

        <!-- Tombol batalkan -->
        <div style="text-align:center;">
            <form method="POST" action="<?= BASE_URL ?>/pages/booking/batalkan.php"
                  onsubmit="return confirm('Yakin ingin membatalkan booking ini?');">
                <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                <button type="submit" class="btn-action"
                        style="font-size:13px; color:var(--color-text-muted);">
                    🗑️ Batalkan Booking Ini
                </button>
            </form>
        </div>

    </div><!-- /kolom kiri -->


    <!-- ===== KOLOM KANAN: Ringkasan Booking ===== -->
    <div>
        <div class="booking-summary-card">
            <div class="booking-summary-title">📋 Ringkasan Booking</div>

            <!-- Foto kos -->
            <?php if (!empty($booking['foto_utama'])): ?>
                <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($booking['foto_utama']) ?>"
                     alt="foto kos"
                     style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:14px;">
            <?php endif; ?>

            <div class="summary-row">
                <span class="label">Kos</span>
                <span class="value"><?= htmlspecialchars($booking['nama_kos']) ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Kota</span>
                <span class="value"><?= htmlspecialchars($booking['kota']) ?></span>
            </div>
            <?php if (!empty($booking['nomor_kamar'])): ?>
            <div class="summary-row">
                <span class="label">No. Kamar</span>
                <span class="value"><?= htmlspecialchars($booking['nomor_kamar']) ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row">
                <span class="label">Tanggal Masuk</span>
                <span class="value"><?= $tgl_masuk_fmt ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Tanggal Keluar</span>
                <span class="value"><?= $tgl_keluar_fmt ?></span>
            </div>
            <div class="summary-row">
                <span class="label">Durasi</span>
                <span class="value"><?= $booking['durasi_bulan'] ?> bulan</span>
            </div>
            <div class="summary-row">
                <span class="label">Harga/bulan</span>
                <span class="value">Rp <?= number_format($booking['harga_per_bulan'], 0, ',', '.') ?></span>
            </div>
            <div class="summary-row summary-total">
                <span class="label">Total</span>
                <span class="value"><?= $harga_format ?></span>
            </div>

            <!-- Status badge -->
            <div style="text-align:center; margin-top:14px;">
                <span class="status-badge menunggu_pembayaran">⏳ Menunggu Pembayaran</span>
            </div>
        </div>
    </div>

</div><!-- /payment-layout -->
<?php mysqli_close($koneksi); ?>
</div><!-- /container -->

<?php require_once __DIR__ . '/../components/footer.php'; ?>

<script>
// ── Ganti panel metode pembayaran (manual) ────────────
function gantiPanel(panel) {
    document.querySelectorAll('.metode-panel').forEach(function(el) {
        el.classList.remove('aktif');
    });
    document.getElementById('panel-' + panel).classList.add('aktif');
}

// ── Preview gambar bukti ──────────────────────────────
function previewBukti(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('preview-bukti');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Salin nomor rekening ke clipboard ────────────────
function salinNoRek(nomor, btn) {
    navigator.clipboard.writeText(nomor).then(function() {
        var orig = btn.textContent;
        btn.textContent = '✅ Disalin!';
        setTimeout(function() { btn.textContent = orig; }, 2000);
    });
}

// ── Tampilkan error di halaman (bukan alert) ───────────────
function tampilkanError(pesan) {
    var el = document.getElementById('midtrans-error');
    if (el) { el.textContent = '⚠️ ' + pesan; el.style.display = 'block'; }
}
function sembunyikanError() {
    var el = document.getElementById('midtrans-error');
    if (el) el.style.display = 'none';
}

// ── Bayar dengan Midtrans Snap ────────────────────────
function bayarDenganMidtrans() {
    console.log('[Kosta] bayarDenganMidtrans() dipanggil');
    sembunyikanError();

    // Cek apakah Snap JS dari Midtrans sudah termuat
    if (typeof snap === 'undefined') {
        tampilkanError('Sistem pembayaran Midtrans belum termuat. Refresh halaman dan coba lagi.');
        console.error('[Kosta] snap object tidak ditemukan — snap.js gagal dimuat');
        return;
    }

    var btn = document.getElementById('btn-bayar-midtrans');
    btn.disabled      = true;
    btn.textContent   = '⏳ Menghubungkan ke Midtrans...';
    btn.style.opacity = '0.7';

    fetch('<?= BASE_URL ?>/pages/proses_bayar.php', {
        method     : 'POST',
        headers    : { 'Content-Type': 'application/x-www-form-urlencoded' },
        body       : 'booking_id=<?= $booking_id ?>',
        credentials: 'same-origin'
    })
    .then(function(response) {
        console.log('[Kosta] proses_bayar response status:', response.status);
        // Jika bukan 200, kemungkinan redirect ke login atau error server
        if (!response.ok) {
            throw new Error('Server error: HTTP ' + response.status +
                (response.status === 405 ? ' (Method Not Allowed)' :
                 response.status === 302 ? ' (Redirect — session mungkin expired)' : ''));
        }
        return response.json();
    })
    .then(function(data) {
        console.log('[Kosta] proses_bayar response data:', data);
        if (data.error) {
            tampilkanError(data.error);
            resetBtn(btn);
            return;
        }
        // Buka Midtrans Snap Popup
        snap.pay(data.snap_token, {
            onSuccess: function(result) {
                console.log('[Midtrans] Success:', result);
                // Redirect ke halaman callback dengan parameter status
                window.location.href = '<?= BASE_URL ?>/pages/callback_bayar.php'
                    + '?order_id='           + encodeURIComponent(result.order_id)
                    + '&status_code='        + encodeURIComponent(result.status_code)
                    + '&transaction_status=' + encodeURIComponent(result.transaction_status)
                    + '&signature_key='      + encodeURIComponent(result.signature_key || '');
            },
            onPending: function(result) {
                console.log('[Midtrans] Pending:', result);
                window.location.href = '<?= BASE_URL ?>/pages/callback_bayar.php'
                    + '?order_id='           + encodeURIComponent(result.order_id)
                    + '&status_code='        + encodeURIComponent(result.status_code)
                    + '&transaction_status=' + encodeURIComponent(result.transaction_status)
                    + '&signature_key='      + encodeURIComponent(result.signature_key || '');
            },
            onError: function(result) {
                console.error('[Midtrans] Error:', result);
                tampilkanError('Pembayaran gagal. Silakan coba lagi.');
                resetBtn(btn);
            },
            onClose: function() {
                console.log('[Midtrans] Popup ditutup user');
                resetBtn(btn);
            }
        });
    })
    .catch(function(err) {
        console.error('[Kosta] Fetch error:', err);
        tampilkanError(err.message || 'Terjadi kesalahan. Buka F12 → Console untuk detail.');
        resetBtn(btn);
    });
}

// Helper: kembalikan tombol ke state semula
function resetBtn(btn) {
    btn.disabled      = false;
    btn.textContent   = '💳 Bayar Sekarang — <?= $harga_format ?>';
    btn.style.opacity = '1';
}
</script>

<?php
// Snap JS dimuat di bawah body (async) agar tidak memblokir render halaman
// async = browser download snap.js di background, body tampil lebih dulu
?>
<script src="<?= MIDTRANS_SNAP_JS ?>" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>" async></script>
<?php require_once __DIR__ . '/../components/scripts.php'; ?>
