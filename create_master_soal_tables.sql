-- SQL untuk membuat tabel master soal
-- Tabel utama untuk menyimpan data soal
CREATE TABLE IF NOT EXISTS master_soal (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL COMMENT 'Judul soal',
    tipe_soal ENUM('essay', 'pilihan_ganda', 'yes_no') NOT NULL COMMENT 'Tipe soal: essay, pilihan_ganda, atau yes_no',
    pertanyaan TEXT NOT NULL COMMENT 'Pertanyaan soal',
    waktu_detik INT UNSIGNED DEFAULT 60 COMMENT 'Waktu pengerjaan dalam detik',
    jawaban_benar VARCHAR(255) NULL COMMENT 'Jawaban benar untuk pilihan ganda dan yes/no (A, B, C, D untuk pilihan ganda; yes, no untuk yes/no)',
    pilihan_a VARCHAR(500) NULL COMMENT 'Pilihan A (untuk pilihan ganda)',
    pilihan_b VARCHAR(500) NULL COMMENT 'Pilihan B (untuk pilihan ganda)',
    pilihan_c VARCHAR(500) NULL COMMENT 'Pilihan C (untuk pilihan ganda)',
    pilihan_d VARCHAR(500) NULL COMMENT 'Pilihan D (untuk pilihan ganda)',
    skor DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Skor untuk soal ini',
    status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Status soal',
    created_by BIGINT UNSIGNED NULL COMMENT 'ID user yang membuat soal',
    updated_by BIGINT UNSIGNED NULL COMMENT 'ID user yang terakhir update soal',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_tipe_soal (tipe_soal),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master data soal untuk testing';

-- Tabel untuk menyimpan kategori soal (opsional)
CREATE TABLE IF NOT EXISTS kategori_soal (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL COMMENT 'Nama kategori soal',
    deskripsi TEXT NULL COMMENT 'Deskripsi kategori',
    status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Status kategori',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_nama_kategori (nama_kategori),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kategori soal';

-- Menambahkan kolom kategori_id ke tabel master_soal
ALTER TABLE master_soal 
ADD COLUMN kategori_id BIGINT UNSIGNED NULL COMMENT 'ID kategori soal' AFTER tipe_soal,
ADD INDEX idx_kategori_id (kategori_id);

-- Foreign key constraint untuk kategori
ALTER TABLE master_soal 
ADD CONSTRAINT fk_master_soal_kategori 
FOREIGN KEY (kategori_id) REFERENCES kategori_soal(id) ON DELETE SET NULL;

-- Insert beberapa kategori contoh
INSERT INTO kategori_soal (nama_kategori, deskripsi) VALUES
('Matematika', 'Soal-soal matematika dasar'),
('Bahasa Indonesia', 'Soal-soal bahasa Indonesia'),
('Bahasa Inggris', 'Soal-soal bahasa Inggris'),
('Pengetahuan Umum', 'Soal-soal pengetahuan umum'),
('Logika', 'Soal-soal logika dan penalaran'),
('Komputer', 'Soal-soal komputer dan teknologi');

-- Insert beberapa contoh soal
INSERT INTO master_soal (judul, tipe_soal, kategori_id, pertanyaan, waktu_detik, jawaban_benar, pilihan_a, pilihan_b, pilihan_c, pilihan_d, skor) VALUES
('Penjumlahan Dasar', 'pilihan_ganda', 1, 'Berapa hasil dari 5 + 3?', 30, 'B', '6', '8', '7', '9', 1.00),
('Kata Benda', 'pilihan_ganda', 2, 'Manakah yang termasuk kata benda?', 45, 'A', 'Meja', 'Berlari', 'Cantik', 'Sangat', 1.00),
('Simple Present', 'yes_no', 3, 'Apakah "I am a student" termasuk simple present tense?', 20, 'no', NULL, NULL, NULL, NULL, 1.00),
('Ibu Kota Indonesia', 'pilihan_ganda', 4, 'Ibu kota Indonesia adalah?', 30, 'A', 'Jakarta', 'Surabaya', 'Bandung', 'Medan', 1.00),
('Essay Matematika', 'essay', 1, 'Jelaskan cara menghitung luas persegi panjang dengan panjang 10 cm dan lebar 5 cm!', 120, NULL, NULL, NULL, NULL, NULL, 2.00),
('Logika Sederhana', 'pilihan_ganda', 5, 'Jika semua kucing adalah hewan, dan Fluffy adalah kucing, maka Fluffy adalah?', 60, 'A', 'Hewan', 'Bukan hewan', 'Kucing', 'Tidak dapat ditentukan', 1.50);
