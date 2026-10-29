-- Cleaning IPAL — dukung multi foto before/after (JSON array of paths)
-- Jalankan sekali jika tabel sudah pernah dibuat.

ALTER TABLE `cleaning_ipal_logs`
  MODIFY `photo_before` LONGTEXT NULL,
  MODIFY `photo_after` LONGTEXT NULL;
