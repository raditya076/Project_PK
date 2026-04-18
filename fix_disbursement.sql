-- Fix: Isi pembagian_dana untuk semua booking aktif/selesai yang belum tercatat
-- Jalankan sekali di phpMyAdmin atau MySQL client

INSERT INTO pembagian_dana
    (booking_id, pemilik_id, total_transaksi, persen_platform,
     biaya_platform, biaya_gateway, jatah_pemilik, status_disbursement, catatan)
SELECT
    b.id                                            AS booking_id,
    k.pemilik_id                                    AS pemilik_id,
    b.total_harga                                   AS total_transaksi,
    3                                               AS persen_platform,
    ROUND(b.total_harga * 3 / 100, 2)              AS biaya_platform,
    0.00                                            AS biaya_gateway,
    ROUND(b.total_harga - (b.total_harga * 3 / 100), 2) AS jatah_pemilik,
    'pending'                                       AS status_disbursement,
    CONCAT('Order: ', IFNULL(b.midtrans_order_id,'manual'), ' (backfill)') AS catatan
FROM bookings b
JOIN kos k ON b.kos_id = k.id
LEFT JOIN pembagian_dana pd ON pd.booking_id = b.id
WHERE b.status IN ('aktif', 'selesai')
  AND pd.id IS NULL;

-- Cek hasilnya
SELECT
    pd.id,
    pd.booking_id,
    u.nama AS nama_pemilik,
    k.nama_kos,
    pd.total_transaksi,
    pd.jatah_pemilik,
    pd.status_disbursement,
    pd.created_at
FROM pembagian_dana pd
JOIN users u ON u.id = pd.pemilik_id
JOIN bookings b ON b.id = pd.booking_id
JOIN kos k ON k.id = b.kos_id
ORDER BY pd.created_at DESC;
