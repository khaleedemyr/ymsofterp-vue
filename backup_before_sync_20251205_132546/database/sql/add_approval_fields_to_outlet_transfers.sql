-- Query untuk menambahkan field approval ke tabel outlet_transfers
-- Jalankan query ini di database untuk menambahkan sistem approval

ALTER TABLE outlet_transfers 
ADD COLUMN approval_by BIGINT UNSIGNED NULL AFTER status,
ADD COLUMN approval_at TIMESTAMP NULL AFTER approval_by,
ADD COLUMN approval_notes TEXT NULL AFTER approval_at;

-- Tambahkan index untuk performance
ALTER TABLE outlet_transfers 
ADD INDEX idx_approval_by (approval_by),
ADD INDEX idx_approval_at (approval_at);

-- Update status default untuk transfer yang sudah ada (jika ada)
UPDATE outlet_transfers 
SET status = 'approved' 
WHERE status IS NULL OR status = '';

-- Tambahkan foreign key constraint (opsional)
-- ALTER TABLE outlet_transfers 
-- ADD CONSTRAINT fk_outlet_transfers_approval_by 
-- FOREIGN KEY (approval_by) REFERENCES users(id) ON DELETE SET NULL;
