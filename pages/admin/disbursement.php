<?php
/**
 * FILE: pages/admin/disbursement.php
 * FUNGSI: Admin mengelola pencairan dana (disbursement) ke pemilik kos.
 *         Menampilkan daftar pembagian_dana + rekening bank pemilik.
 */
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../config/session.php';

wajib_role('admin');

// Proses update status disbursement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $id_dana    = (int)($_POST['id_dana'] ?? 0);
    $aksi       = trim($_POST['aksi'] ?? '');
    $catatan    = trim($_POST['catatan'] ?? '');
    $valid_aksi = ['diproses', 'selesai'];

    if ($id_dana > 0 && in_array($aksi, $valid_aksi)) {
        $upd = mysqli_prepare($koneksi,
            "UPDATE pembagian_dana SET status_disbursement = ?, catatan = ?
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($upd, 'ssi', $aksi, $catatan, $id_dana);
        if (mysqli_stmt_execute($upd)) {
            $label = $aksi === 'selesai' ? 'Dana berhasil ditandai SELESAI ✅' : 'Status diubah ke Diproses 🔄';
            set_flash('sukses', $label);
        } else {
            set_flash('error', 'Gagal memperbarui status.');
        }
    }
    redirect(BASE_URL . '/pages/admin/disbursement.php');
}

// Filter
$filter_status  = $_GET['status'] ?? '';
$valid_statuses = ['pending', 'diproses', 'selesai'];
$where = '1=1';
$params = []; $types = '';

if ($filter_status && in_array($filter_status, $valid_statuses)) {
    $where   .= ' AND pd.status_disbursement = ?';
    $params[] = $filter_status;
    $types   .= 's';
}

// Ambil data disbursement + info pemilik + booking
$sql = "SELECT pd.*,
               u.nama         AS nama_pemilik,
               u.email        AS email_pemilik,
               u.no_hp        AS hp_pemilik,
               u.nama_bank,
               u.nomor_rekening,
               u.nama_pemilik_rekening,
               b.tanggal_masuk, b.tanggal_keluar, b.durasi_bulan,
               k.nama_kos, k.kota
        FROM pembagian_dana pd
        JOIN users   u ON pd.pemilik_id = u.id
        JOIN bookings b ON pd.booking_id = b.id
        JOIN kos     k ON b.kos_id = k.id
        WHERE $where
        ORDER BY
            FIELD(pd.status_disbursement, 'pending', 'diproses', 'selesai'),
            pd.created_at DESC";

$stmt = mysqli_prepare($koneksi, $sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Statistik ringkas
$r_stat = mysqli_query($koneksi,
    "SELECT
        SUM(total_transaksi) AS total_uang,
        SUM(jatah_pemilik)   AS total_jatah,
        SUM(biaya_platform)  AS total_platform,
        SUM(status_disbursement = 'pending')   AS jml_pending,
        SUM(status_disbursement = 'diproses')  AS jml_diproses,
        SUM(status_disbursement = 'selesai')   AS jml_selesai,
        SUM(CASE WHEN status_disbursement = 'pending'  THEN jatah_pemilik ELSE 0 END) AS uang_pending,
        SUM(CASE WHEN status_disbursement = 'selesai'  THEN jatah_pemilik ELSE 0 END) AS uang_selesai
     FROM pembagian_dana"
);
$stat = mysqli_fetch_assoc($r_stat);

$judul_halaman = "Disbursement — Admin";
$css_tambahan  = "admin.css";
$body_class    = "admin-page";
require_once __DIR__ . '/../../components/head.php';
require_once __DIR__ . '/../../components/navbar.php';

function rp_fmt($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
?>

<div class="admin-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-page-header">
            <h1 class="admin-page-title">💸 Disbursement Dana Pemilik</h1>
            <p class="admin-page-subtitle">Kelola pencairan hasil pembayaran sewa ke rekening pemilik kos.</p>
        </div>

        <?= get_flash() ?>

        <!-- Statistik -->
        <div class="admin-stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:24px;">
            <div class="admin-stat-card">
                <div class="admin-stat-icon orange">⏳</div>
                <div>
                    <div class="admin-stat-value"><?= $stat['jml_pending'] ?></div>
                    <div class="admin-stat-label">Menunggu Proses</div>
                    <div class="admin-stat-change down" style="font-size:11px;"><?= rp_fmt($stat['uang_pending']) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon blue">🔄</div>
                <div>
                    <div class="admin-stat-value"><?= $stat['jml_diproses'] ?></div>
                    <div class="admin-stat-label">Sedang Diproses</div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon green">✅</div>
                <div>
                    <div class="admin-stat-value"><?= $stat['jml_selesai'] ?></div>
                    <div class="admin-stat-label">Selesai Dibayar</div>
                    <div class="admin-stat-change up" style="font-size:11px;"><?= rp_fmt($stat['uang_selesai']) ?></div>
                </div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-icon red">💰</div>
                <div>
                    <div class="admin-stat-value" style="font-size:16px;"><?= rp_fmt($stat['total_platform']) ?></div>
                    <div class="admin-stat-label">Total Fee Platform</div>
                </div>
            </div>
        </div>

        <!-- Filter Tab -->
        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
            <?php
            $tabs = ['' => 'Semua', 'pending' => '⏳ Pending', 'diproses' => '🔄 Diproses', 'selesai' => '✅ Selesai'];
            foreach ($tabs as $val => $lbl):
                $aktif = ($filter_status === $val) ? 'primary' : 'neutral';
            ?>
                <a href="<?= BASE_URL ?>/pages/admin/disbursement.php<?= $val ? '?status='.$val : '' ?>"
                   class="btn-admin <?= $aktif ?>"><?= $lbl ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Tabel Disbursement -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">Daftar Pencairan Dana</div>
            </div>

            <?php if (mysqli_num_rows($result) === 0): ?>
                <div class="admin-empty">
                    <div class="admin-empty-icon">💸</div>
                    <p>Belum ada data disbursement<?= $filter_status ? ' dengan status "'.$filter_status.'"' : '' ?>.</p>
                </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Pemilik & Rekening</th>
                            <th>Kos / Booking</th>
                            <th>Dana</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($d = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td style="font-size:12px;color:var(--color-text-muted);">#<?= $d['id'] ?></td>

                            <!-- Pemilik + Rekening -->
                            <td>
                                <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($d['nama_pemilik']) ?></div>
                                <div style="font-size:11px;color:var(--color-text-muted);"><?= htmlspecialchars($d['email_pemilik']) ?></div>
                                <?php if (!empty($d['nomor_rekening'])): ?>
                                    <div style="margin-top:6px;padding:6px 10px;background:var(--color-bg);border-radius:6px;border:1px solid var(--color-border);">
                                        <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);"><?= htmlspecialchars($d['nama_bank']) ?></div>
                                        <div style="font-size:13px;font-weight:800;color:var(--color-accent);letter-spacing:.05em;"><?= htmlspecialchars($d['nomor_rekening']) ?></div>
                                        <div style="font-size:11px;color:var(--color-text-muted);">a.n. <?= htmlspecialchars($d['nama_pemilik_rekening']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top:6px;padding:6px 10px;background:#FFF9E6;border-radius:6px;border:1px solid #f59e0b;font-size:11px;color:#92660a;">
                                        ⚠️ Rekening belum diisi oleh pemilik
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Kos + Booking -->
                            <td>
                                <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($d['nama_kos']) ?></div>
                                <div style="font-size:11px;color:var(--color-text-muted);">📍 <?= htmlspecialchars($d['kota']) ?></div>
                                <div style="font-size:11px;color:var(--color-text-muted);margin-top:3px;">
                                    <?= date('d M Y', strtotime($d['tanggal_masuk'])) ?> · <?= $d['durasi_bulan'] ?> bln
                                </div>
                                <div style="font-size:10px;color:var(--color-text-muted);"><?= htmlspecialchars($d['catatan'] ?? '') ?></div>
                            </td>

                            <!-- Dana -->
                            <td>
                                <div style="font-size:11px;color:var(--color-text-muted);">Total transaksi</div>
                                <div style="font-weight:700;"><?= rp_fmt($d['total_transaksi']) ?></div>
                                <div style="font-size:11px;color:var(--color-text-muted);margin-top:4px;">Fee platform (<?= $d['persen_platform'] ?>%)</div>
                                <div style="color:#b91c1c;font-weight:600;font-size:13px;">- <?= rp_fmt($d['biaya_platform']) ?></div>
                                <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);margin-top:4px;border-top:1px solid var(--color-border);padding-top:4px;">Jatah Pemilik</div>
                                <div style="font-size:15px;font-weight:900;color:#15803d;"><?= rp_fmt($d['jatah_pemilik']) ?></div>
                            </td>

                            <!-- Status -->
                            <td>
                                <?php
                                $s = $d['status_disbursement'];
                                $badge = match($s) {
                                    'pending'  => 'background:#FFF9E6;color:#92660a;border:1px solid #f59e0b;',
                                    'diproses' => 'background:#EFF6FF;color:#1d4ed8;border:1px solid #93c5fd;',
                                    'selesai'  => 'background:#F0FFF4;color:#15803d;border:1px solid #86efac;',
                                    default    => '',
                                };
                                $ikon = match($s) { 'pending'=>'⏳', 'diproses'=>'🔄', 'selesai'=>'✅', default=>'' };
                                ?>
                                <span style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;<?= $badge ?>">
                                    <?= $ikon . ' ' . ucfirst($s) ?>
                                </span>
                                <div style="font-size:10px;color:var(--color-text-muted);margin-top:4px;">
                                    <?= date('d M Y', strtotime($d['created_at'])) ?>
                                </div>
                            </td>

                            <!-- Aksi -->
                            <td>
                                <?php if ($d['status_disbursement'] === 'pending'): ?>
                                    <button onclick="bukaModal(<?= $d['id'] ?>, 'diproses')"
                                            class="btn-admin neutral" style="font-size:12px;margin-bottom:4px;width:100%;">
                                        🔄 Tandai Diproses
                                    </button>
                                    <button onclick="bukaModal(<?= $d['id'] ?>, 'selesai')"
                                            class="btn-admin primary" style="font-size:12px;width:100%;">
                                        ✅ Tandai Selesai
                                    </button>
                                <?php elseif ($d['status_disbursement'] === 'diproses'): ?>
                                    <button onclick="bukaModal(<?= $d['id'] ?>, 'selesai')"
                                            class="btn-admin primary" style="font-size:12px;width:100%;">
                                        ✅ Tandai Selesai
                                    </button>
                                <?php else: ?>
                                    <span style="font-size:12px;color:#15803d;font-weight:600;">✅ Sudah selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- Modal Konfirmasi Aksi -->
<div id="modal-disb" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--color-surface);border-radius:12px;padding:28px;width:100%;max-width:420px;border:1.5px solid var(--color-border);">
        <h3 style="font-size:16px;font-weight:800;margin-bottom:8px;" id="modal-judul">Konfirmasi</h3>
        <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:16px;" id="modal-desc"></p>
        <form method="POST" action="">
            <input type="hidden" name="id_dana" id="modal-id">
            <input type="hidden" name="aksi"    id="modal-aksi">
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px;">
                    Catatan (opsional)
                </label>
                <textarea name="catatan" id="modal-catatan"
                          style="width:100%;border:1.5px solid var(--color-border);border-radius:8px;padding:10px;font-size:13px;min-height:70px;font-family:var(--font-main);resize:vertical;"
                          placeholder="Contoh: Transfer via BCA ref. 12345..."></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="tutupModal()" class="btn-admin neutral">Batal</button>
                <button type="submit" id="modal-submit" class="btn-admin primary">Konfirmasi</button>
            </div>
        </form>
    </div>
</div>

<?php mysqli_close($koneksi); ?>
<?php require_once __DIR__ . '/../../components/footer.php'; ?>
<?php require_once __DIR__ . '/../../components/scripts.php'; ?>

<script>
function bukaModal(id, aksi) {
    document.getElementById('modal-id').value   = id;
    document.getElementById('modal-aksi').value = aksi;
    document.getElementById('modal-catatan').value = '';

    if (aksi === 'selesai') {
        document.getElementById('modal-judul').textContent = '✅ Tandai Dana Selesai Ditransfer';
        document.getElementById('modal-desc').textContent  = 'Pastikan kamu sudah mentransfer dana ke rekening pemilik. Tindakan ini tidak bisa dibatalkan.';
        document.getElementById('modal-submit').textContent = 'Ya, Sudah Ditransfer';
    } else {
        document.getElementById('modal-judul').textContent = '🔄 Tandai Sedang Diproses';
        document.getElementById('modal-desc').textContent  = 'Tandai bahwa proses transfer sedang berjalan.';
        document.getElementById('modal-submit').textContent = 'Tandai Diproses';
    }

    var m = document.getElementById('modal-disb');
    m.style.display = 'flex';
}
function tutupModal() {
    document.getElementById('modal-disb').style.display = 'none';
}
document.getElementById('modal-disb').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});
</script>
