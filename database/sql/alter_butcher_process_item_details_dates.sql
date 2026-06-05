-- Tambah tanggal slaughter & packaging di pabrik butcher (terpisah dari tanggal supplier)
-- Jalankan sekali di MySQL.

ALTER TABLE `butcher_process_item_details`
  ADD COLUMN `butcher_slaughter_date` DATE NULL COMMENT 'Tanggal slaughter di butcher' AFTER `packing_date`,
  ADD COLUMN `butcher_packaging_date` DATE NULL COMMENT 'Tanggal packaging di butcher (basis EXP barcode)' AFTER `butcher_slaughter_date`;
