-- =====================================================
-- Butcher Serial: Konversi Unit Fleksibel
-- User bisa pilih unit tujuan serial + qty konversi
-- Contoh: 5000 gram -> Kg (1 Kg = 1000 gram) -> 5 serial
-- =====================================================

ALTER TABLE inventory_item_serials
    ADD COLUMN repack_unit_id INT NULL AFTER qty_per_pack,
    ADD COLUMN repack_qty DECIMAL(12,4) NULL AFTER repack_unit_id;
