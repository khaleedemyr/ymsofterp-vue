-- Create retail_warehouse_food table
CREATE TABLE IF NOT EXISTS retail_warehouse_food (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    retail_number VARCHAR(50) UNIQUE NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    warehouse_division_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    transaction_date DATE NOT NULL,
    total_amount DECIMAL(15,2) DEFAULT 0,
    notes TEXT NULL,
    payment_method ENUM('cash', 'contra_bon') NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    status ENUM('draft', 'approved', 'cancelled') DEFAULT 'approved',
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (warehouse_division_id) REFERENCES warehouse_division(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_retail_number (retail_number),
    INDEX idx_warehouse_id (warehouse_id),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_payment_method (payment_method),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create retail_warehouse_food_items table
CREATE TABLE IF NOT EXISTS retail_warehouse_food_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    retail_warehouse_food_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    qty DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (retail_warehouse_food_id) REFERENCES retail_warehouse_food(id) ON DELETE CASCADE,
    INDEX idx_retail_warehouse_food_id (retail_warehouse_food_id),
    INDEX idx_item_name (item_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create retail_warehouse_food_invoices table
CREATE TABLE IF NOT EXISTS retail_warehouse_food_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    retail_warehouse_food_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (retail_warehouse_food_id) REFERENCES retail_warehouse_food(id) ON DELETE CASCADE,
    INDEX idx_retail_warehouse_food_id (retail_warehouse_food_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

