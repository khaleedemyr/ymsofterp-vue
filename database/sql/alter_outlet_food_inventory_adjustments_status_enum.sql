-- Alter ENUM status untuk tabel outlet_food_inventory_adjustments
-- Memastikan enum status sesuai dengan yang digunakan di aplikasi

ALTER TABLE `outlet_food_inventory_adjustments` 
MODIFY COLUMN `status` ENUM(
    'waiting_approval',
    'waiting_cost_control',
    'approved',
    'rejected'
) NOT NULL DEFAULT 'waiting_cost_control';

