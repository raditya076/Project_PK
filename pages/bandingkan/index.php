<?php

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

// Ambil ID kos dari session
$ids_bandingkan = $_SESSION['bandingkan'] ?? [];

$daftar_kos = [];

if (!empty($ids_bandingkan)) {
    // Bangun IN clause yang aman: (?, ?, ?)
    // str_repeat membuat sejumlah placeholder sesuai jumlah ID
    $placeholders = implode(',', array_fill(0, count($ids_bandingkan), '?'));
    $tipe_bind    = str_repeat('i', count($ids_bandingkan)); // 'ii' atau 'iii'

    $stmt = mysqli_prepare($koneksi,
        "SELECT k.*, u.nama AS nama_pemilik, u.no_hp AS hp_pemilik
         FROM kos k
         LEFT JOIN users u ON k.pemilik_id = u.id
         WHERE k.id IN ($placeholders) AND k.status = 'aktif'"
    );

    // bind_param dengan jumlah parameter dinamis
    // array_merge() menggabungkan tipe dengan array nilai
    mysqli_stmt_bind_param($stmt, $tipe_bind, ...$ids_bandingkan);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $daftar_kos[] = $row;
    }
}

$jumlah = count($daftar_kos);

// Temukan harga terendah untuk highlight sel terbaik
$harga_min_nilai = PHP_INT_MAX;
foreach ($daftar_kos as $k) {
    if ($k['harga_per_bulan'] < $harga_min_nilai) {
        $harga_min_nilai = $k['harga_per_bulan'];
    }
}

// Fasilitas yang akan dibandingkan
$fasilitas_bandingkan = [
    'wifi'              => ['📶', 'WiFi / Internet'],
    'ac'                => ['❄️', 'AC'],
    'kamar_mandi_dalam' => ['🚿', 'KM Dalam'],
    'parkir'            => ['🅿️', 'Parkir'],
    'dapur'             => ['🍳', 'Dapur'],
    'laundry'           => ['👕', 'Laundry'],
    'security'          => ['👮', 'Security'],
    'cctv'              => ['📹', 'CCTV'],
];

$judul_halaman = "Bandingkan Kos";
$css_tambahan  = "compare.css";

require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';
?>

<div style="background:var(--color-surface); border-bottom:1px solid var(--color-border); padding:20px 0;">
    <div class="container">
        <?= get_flash() ?>
        <div class="compare-page-header" style="padding:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <h1 class="compare-page-title">⚖️ Bandingkan Kos</h1>
                    <p class="compare-page-subtitle">
                        <?= $jumlah ?> kos dipilih — pilih 2-3 kos untuk perbandingan terbaik
                    </p>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="<?= BASE_URL ?>/index.php" class="btn-kosta-outline btn" style="font-size:13px;">
                        + Tambah Kos
                    </a>
                    <!-- Tombol reset semua -->
                    <form action="<?= BASE_URL ?>/pages/bandingkan/hapus.php" method="POST" style="display:inline;">
                        <input type="hidden" name="reset" value="1">
                        <button type="submit" class="btn-action hapus"
                                onclick="return confirm('Kosongkan semua perbandingan?');">
                            🗑️ Reset
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<section style="padding:40px 0 80px;">
    <div class="container">

        <?php if ($jumlah === 0): ?>
            <!-- EMPTY STATE -->
            <div class="compare-empty">
                <div class="compare-empty-icon">⚖️</div>
                <h3>Belum Ada Kos yang Dipilih</h3>
                <p>
                    Klik tombol <strong>"⚖️ Bandingkan"</strong> pada kartu kos
                    untuk menambahkannya ke daftar perbandingan ini.
                </p>
                <a href="<?= BASE_URL ?>/index.php" class="btn-kosta btn mt-4">
                    Jelajahi Kos →
                </a>
            </div>

        <?php elseif ($jumlah === 1): ?>
            <!-- TERLALU SEDIKIT -->
            <div class="compare-empty">
                <div class="compare-empty-icon">1️⃣</div>
                <h3>Pilih Minimal 2 Kos</h3>
                <p>
                    Kamu baru memilih <strong>1 kos</strong>.
                    Tambahkan minimal satu kos lagi untuk mulai membandingkan.
                </p>
                <a href="<?= BASE_URL ?>/index.php" class="btn-kosta btn mt-4">
                    Tambah Kos Lain
                </a>
            </div>

        <?php else: ?>
            <!-- TABEL PERBANDINGAN -->
            <div class="compare-table-wrapper">
                <table class="compare-table">

                    <!-- HEADER: Foto & Nama Kos -->
                    <thead>
                        <tr>
                            <th><!-- Kolom label kiri --></th>
                            <?php foreach ($daftar_kos as $kos): ?>
                            <th>
                                <!-- Foto kos -->
                                <?php if (!empty($kos['foto_utama'])): ?>
                                    <img src="<?= BASE_URL ?>/assets/images/kos/<?= htmlspecialchars($kos['foto_utama']) ?>"
                                         alt="<?= htmlspecialchars($kos['nama_kos']) ?>"
                                         class="compare-kos-photo">
                                <?php else: ?>
                                    <div class="compare-kos-photo-placeholder">🏠</div>
                                <?php endif; ?>

                                <div class="compare-kos-name">
                                    <?= htmlspecialchars($kos['nama_kos']) ?>
                                </div>
                                <div class="compare-kos-city">
                                    📍 <?= htmlspecialchars($kos['kota']) ?>
                                </div>

                                <!-- Tombol hapus dari perbandingan -->
                                <form action="<?= BASE_URL ?>/pages/bandingkan/hapus.php" method="POST">
                                    <input type="hidden" name="kos_id"  value="<?= $kos['id'] ?>">
                                    <input type="hidden" name="kembali" value="<?= BASE_URL ?>/pages/bandingkan/index.php">
                                    <button type="submit" class="compare-remove-btn">✕ Hapus</button>
                                </form>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <!-- BODY: Baris perbandingan data -->
                    <tbody>

                        <!-- Baris: Tipe Kos -->
                        <tr>
                            <td class="row-label">📋 Tipe</td>
                            <?php foreach ($daftar_kos as $kos): ?>
                            <td>
                                <span class="badge-kos <?= $kos['tipe'] ?>">
                                    <?= ucfirst($kos['tipe']) ?>
                                </span>
                            </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Baris: Harga -->
                        <tr>
                            <td class="row-label">💰 Harga / Bulan</td>
                            <?php foreach ($daftar_kos as $kos): ?>
                                <?php
                                    $harga_fmt  = 'Rp ' . number_format($kos['harga_per_bulan'], 0, ',', '.');
                                    // Highlight sel jika harga ini adalah yang termurah
                                    $is_best    = ($kos['harga_per_bulan'] === $harga_min_nilai);
                                ?>
                            <td class="<?= $is_best ? 'compare-best' : '' ?>">
                                <div class="compare-price"><?= $harga_fmt ?></div>
                            </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Baris: Ketersediaan Kamar -->
                        <tr>
                            <td class="row-label">🛏️ Kamar Kosong</td>
                            <?php foreach ($daftar_kos as $kos): ?>
                                <?php $sisa = $kos['jumlah_kamar'] - $kos['kamar_terisi']; ?>
                            <td style="font-weight:700; color:<?= $sisa > 0 ? '#15803d' : '#b91c1c' ?>;">
                                <?= $sisa > 0 ? "$sisa kamar" : "Penuh" ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Baris: Lokasi -->
                        <tr>
                            <td class="row-label">📍 Lokasi</td>
                            <?php foreach ($daftar_kos as $kos): ?>
                            <td style="font-size:13px; color:var(--color-text-muted);">
                                <?= htmlspecialchars($kos['kota']) ?>
                                <?php if (!empty($kos['kecamatan'])): ?><br>
                                    <span style="font-size:11px;"><?= htmlspecialchars($kos['kecamatan']) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- === BARIS FASILITAS === -->
                        <?php foreach ($fasilitas_bandingkan as $kolom => [$ikon, $label]): ?>
                        <tr>
                            <td class="row-label"><?= $ikon ?> <?= $label ?></td>
                            <?php foreach ($daftar_kos as $kos): ?>
                            <td>
                                <?php if ($kos[$kolom]): ?>
                                    <span class="compare-check-yes">✅</span>
                                <?php else: ?>
                                    <span class="compare-check-no">✕</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Baris: Pemilik -->
                        <tr>
                            <td class="row-label">👤 Pemilik</td>
                            <?php foreach ($daftar_kos as $kos): ?>
                            <td style="font-size:13px; font-weight:600;">
                                <?= htmlspecialchars($kos['nama_pemilik'] ?? '—') ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>

                    </tbody>

                    <!-- FOOTER: Tombol ke halaman detail -->
                    <tfoot>
                        <tr>
                            <td><!-- kosong --></td>
                            <?php foreach ($daftar_kos as $kos): ?>
                            <td>
                                <a href="<?= BASE_URL ?>/pages/detail.php?id=<?= $kos['id'] ?>"
                                   class="btn-kosta btn" style="font-size:12px; padding:8px 18px;">
                                    Lihat Detail →
                                </a>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>

                </table>
            </div><!-- /compare-table-wrapper -->

            <!-- Tombol tambah kos lagi (jika < 3) -->
            <?php if ($jumlah < 3): ?>
            <div style="text-align:center; margin-top:8px;">
                <p style="font-size:13px; color:var(--color-text-muted);">
                    Kamu bisa menambahkan <?= 3 - $jumlah ?> kos lagi untuk perbandingan lebih lengkap.
                </p>
                <a href="<?= BASE_URL ?>/index.php" class="btn-kosta-outline btn mt-2">
                    + Tambah Kos ke Perbandingan
                </a>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php mysqli_close($koneksi); ?>
    </div>
</section>

<!-- Badge Kos style (reuse) -->
<style>
.btn-action.hapus { display:inline-flex;align-items:center;gap:4px;padding:7px 14px;border-radius:6px;border:1.5px solid var(--color-border);color:var(--color-text-muted);font-size:12px;font-weight:700;background:none;cursor:pointer;font-family:var(--font-main);transition:all 0.2s; }
.btn-action.hapus:hover { border-color:#b91c1c;color:#b91c1c;background:#FFF3F3; }
</style>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
<?php require_once __DIR__ . '/../../components/scripts.php'; ?>
