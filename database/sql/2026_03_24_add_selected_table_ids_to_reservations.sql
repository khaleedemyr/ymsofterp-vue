-- Simpan meja terpilih (single atau kombinasi) untuk conflict check reservasi.
-- Jalankan sekali di database ymsofterp.
ALTER TABLE reservations
ADD COLUMN selected_table_ids LONGTEXT NULL AFTER number_of_guests;

