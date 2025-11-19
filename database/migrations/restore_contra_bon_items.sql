-- Query untuk mengembalikan item-item dari contra bon yang sudah dihapus
-- Query ini akan menghapus record di food_contra_bon_items yang terkait dengan contra bon yang sudah tidak ada
-- Setelah query ini dijalankan, item-item akan kembali muncul di daftar item yang bisa dipilih untuk contra bon baru

-- ============================================
-- OPSI 1: Jika menggunakan HARD DELETE
-- (contra bon benar-benar dihapus dari tabel food_contra_bons)
-- ============================================
-- Hapus item-item yang contra_bon_id-nya tidak ada lagi di food_contra_bons
DELETE cbi FROM food_contra_bon_items cbi
LEFT JOIN food_contra_bons cb ON cbi.contra_bon_id = cb.id
WHERE cb.id IS NULL;

-- ============================================
-- OPSI 2: Jika menggunakan SOFT DELETE
-- (ada kolom deleted_at di food_contra_bons)
-- ============================================
-- Uncomment query di bawah ini jika menggunakan soft delete
-- DELETE cbi FROM food_contra_bon_items cbi
-- INNER JOIN food_contra_bons cb ON cbi.contra_bon_id = cb.id
-- WHERE cb.deleted_at IS NOT NULL;

-- ============================================
-- CATATAN PENTING:
-- ============================================
-- 1. Sebelum menjalankan query, disarankan untuk backup data terlebih dahulu
-- 2. Query ini akan menghapus permanen record di food_contra_bon_items
-- 3. Setelah query dijalankan, item-item yang sebelumnya digunakan di contra bon yang sudah dihapus
--    akan kembali muncul di daftar item yang bisa dipilih untuk membuat contra bon baru
-- 4. Hal ini karena query getPOWithApprovedGR() mengecualikan item yang gr_item_id-nya ada di food_contra_bon_items
-- 5. Pastikan Anda menjalankan query yang sesuai dengan metode delete yang digunakan (hard delete atau soft delete)

-- ============================================
-- QUERY UNTUK CEK DATA SEBELUM DELETE (OPTIONAL):
-- ============================================
-- Cek berapa banyak item yang akan dihapus (untuk hard delete):
-- SELECT COUNT(*) as total_items_to_delete
-- FROM food_contra_bon_items cbi
-- LEFT JOIN food_contra_bons cb ON cbi.contra_bon_id = cb.id
-- WHERE cb.id IS NULL;

-- Cek detail item yang akan dihapus (untuk hard delete):
-- SELECT cbi.*, cbi.contra_bon_id as deleted_contra_bon_id
-- FROM food_contra_bon_items cbi
-- LEFT JOIN food_contra_bons cb ON cbi.contra_bon_id = cb.id
-- WHERE cb.id IS NULL;

