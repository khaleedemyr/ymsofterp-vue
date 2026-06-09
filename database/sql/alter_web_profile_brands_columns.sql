-- =============================================================================
-- Web Profile Brands — perbaikan struktur (kolom sudah ada di server Anda)
-- =============================================================================

-- Cek struktur saat ini
-- SHOW COLUMNS FROM web_profile_brands;

-- Opsional: jika create brand gagal karena content wajib diisi di DB,
-- ubah content jadi boleh NULL:
ALTER TABLE `web_profile_brands`
  MODIFY COLUMN `content` TEXT NULL;

-- Opsional: pastikan slug unik (skip jika error "Duplicate key name")
-- ALTER TABLE `web_profile_brands` ADD UNIQUE KEY `web_profile_brands_slug_unique` (`slug`);
