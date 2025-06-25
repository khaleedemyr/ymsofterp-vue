-- Create customers table
CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    address TEXT NULL,
    type ENUM('branch', 'customer') DEFAULT 'customer',
    region VARCHAR(20) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
);

-- Create warehouse_sales table
CREATE TABLE IF NOT EXISTS warehouse_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(20) UNIQUE NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    warehouse_division_id BIGINT UNSIGNED NULL,
    total_amount DECIMAL(15,2) DEFAULT 0,
    notes TEXT NULL,
    status ENUM('draft', 'completed', 'cancelled') DEFAULT 'draft',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (warehouse_division_id) REFERENCES warehouse_division(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Create warehouse_sale_items table
CREATE TABLE IF NOT EXISTS warehouse_sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    warehouse_sale_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    barcode VARCHAR(100) NULL,
    qty DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (warehouse_sale_id) REFERENCES warehouse_sales(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
); 