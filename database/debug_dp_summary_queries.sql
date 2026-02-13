-- ============================================================
-- Query untuk trace manual: DP Summary (Revenue Report)
-- Ganti :date dan :outlet_id dengan nilai yang dipakai saat buka report.
-- Contoh: date = '2026-02-13', outlet_id = id_outlet Justus Bintaro.
-- ============================================================

-- 1) Cek outlet: apa yang dikirim frontend (kode_outlet) bisa di-resolve?
--    Frontend ambil kode_outlet dari orders[0].kode_outlet.
--    Cek dulu isi tbl_data_outlet (qr_code, id_outlet, nama_outlet).
SELECT id_outlet, qr_code, nama_outlet FROM tbl_data_outlet;

-- Resolusi outlet (sama seperti backend):
-- SELECT * FROM tbl_data_outlet WHERE qr_code = :kode_outlet;
-- SELECT * FROM tbl_data_outlet WHERE id_outlet = :kode_outlet;  -- jika numeric
-- SELECT * FROM tbl_data_outlet WHERE nama_outlet = :kode_outlet;


-- 2) DP diterima di tanggal yang dipilih, untuk reservasi tanggal mendatang
--    (ini yang harusnya mengembalikan DP Rp 1.000.000 untuk jadwal 25 Feb)
SELECT r.id, r.name, r.outlet_id, r.reservation_date, r.dp, r.created_at, r.payment_type_id
FROM reservations r
WHERE DATE(r.created_at) = :date
  AND r.reservation_date > :date
  AND r.outlet_id = :outlet_id
  AND r.dp IS NOT NULL
  AND r.dp > 0;
-- Contoh isi: SET @date = '2026-02-13'; SET @outlet_id = <id_outlet Bintaro>;


-- 3) Cek semua reservasi yang punya DP (tanpa filter outlet/date) untuk memastikan data ada
SELECT id, name, outlet_id, reservation_date, DATE(created_at) AS created_date, dp, payment_type_id
FROM reservations
WHERE dp IS NOT NULL AND dp > 0
ORDER BY created_at DESC
LIMIT 20;


-- 4) Bandingkan outlet_id di reservasi DP Anda vs outlet_id yang dipakai report
--    Report pakai outlet dari orders[0]. Jika report untuk outlet A tapi DP di outlet B, tidak akan ketemu.
SELECT r.id, r.name, r.outlet_id, o.nama_outlet
FROM reservations r
LEFT JOIN tbl_data_outlet o ON o.id_outlet = r.outlet_id
WHERE r.dp IS NOT NULL AND r.dp > 0
ORDER BY r.created_at DESC
LIMIT 10;
