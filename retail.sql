CREATE TABLE retail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES maintenance_tasks(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE retail_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    retail_id INT NOT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    harga_barang DECIMAL(15,2) NOT NULL,
    nama_toko VARCHAR(255) NOT NULL,
    alamat_toko TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (retail_id) REFERENCES retail(id) ON DELETE CASCADE
);

CREATE TABLE retail_invoice_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    retail_item_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (retail_item_id) REFERENCES retail_items(id) ON DELETE CASCADE
);

CREATE TABLE retail_barang_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    retail_item_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (retail_item_id) REFERENCES retail_items(id) ON DELETE CASCADE
); 