-- SQL untuk membuat tabel master soal yang benar
-- Tabel utama untuk judul soal (1 judul bisa punya banyak pertanyaan)
CREATE TABLE IF NOT EXISTS master_soal (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL COMMENT 'Judul soal',
    deskripsi TEXT NULL COMMENT 'Deskripsi soal',
    kategori_id BIGINT UNSIGNED NULL COMMENT 'ID kategori soal',
    waktu_total_detik INT UNSIGNED DEFAULT 300 COMMENT 'Waktu total pengerjaan dalam detik',
    skor_total DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Total skor untuk semua pertanyaan',
    status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Status soal',
    created_by BIGINT UNSIGNED NULL COMMENT 'ID user yang membuat soal',
    updated_by BIGINT UNSIGNED NULL COMMENT 'ID user yang terakhir update soal',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_kategori_id (kategori_id),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master judul soal';

-- Tabel untuk pertanyaan individual dalam 1 judul soal
CREATE TABLE IF NOT EXISTS soal_pertanyaan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    master_soal_id BIGINT UNSIGNED NOT NULL COMMENT 'ID master soal',
    urutan INT UNSIGNED NOT NULL COMMENT 'Urutan pertanyaan',
    tipe_soal ENUM('essay', 'pilihan_ganda', 'yes_no') NOT NULL COMMENT 'Tipe soal: essay, pilihan_ganda, atau yes_no',
    pertanyaan TEXT NOT NULL COMMENT 'Pertanyaan soal',
    pertanyaan_gambar JSON NULL COMMENT 'Array URL gambar untuk pertanyaan (multiple images)',
    waktu_detik INT UNSIGNED DEFAULT 60 COMMENT 'Waktu pengerjaan dalam detik',
    jawaban_benar VARCHAR(255) NULL COMMENT 'Jawaban benar untuk pilihan ganda dan yes/no',
    pilihan_a VARCHAR(500) NULL COMMENT 'Pilihan A (untuk pilihan ganda)',
    pilihan_a_gambar VARCHAR(500) NULL COMMENT 'Gambar untuk pilihan A',
    pilihan_b VARCHAR(500) NULL COMMENT 'Pilihan B (untuk pilihan ganda)',
    pilihan_b_gambar VARCHAR(500) NULL COMMENT 'Gambar untuk pilihan B',
    pilihan_c VARCHAR(500) NULL COMMENT 'Pilihan C (untuk pilihan ganda)',
    pilihan_c_gambar VARCHAR(500) NULL COMMENT 'Gambar untuk pilihan C',
    pilihan_d VARCHAR(500) NULL COMMENT 'Pilihan D (untuk pilihan ganda)',
    pilihan_d_gambar VARCHAR(500) NULL COMMENT 'Gambar untuk pilihan D',
    skor DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Skor untuk pertanyaan ini (hanya untuk pilihan ganda dan yes/no)',
    status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Status pertanyaan',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (master_soal_id) REFERENCES master_soal(id) ON DELETE CASCADE,
    INDEX idx_master_soal_id (master_soal_id),
    INDEX idx_urutan (urutan),
    INDEX idx_tipe_soal (tipe_soal),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pertanyaan individual dalam master soal';

-- Kategori dihapus sesuai permintaan

-- Insert contoh master soal dengan beberapa pertanyaan
INSERT INTO master_soal (judul, deskripsi, waktu_total_detik, skor_total, status, created_by, updated_by) VALUES
('Matematika Dasar', 'Kumpulan soal matematika dasar untuk pemula', 600, 5.00, 'active', 1, 1),
('Bahasa Indonesia', 'Soal-soal bahasa Indonesia tingkat menengah', 450, 3.00, 'active', 1, 1),
('Logika Sederhana', 'Soal-soal logika untuk melatih kemampuan berpikir', 300, 2.00, 'active', 1, 1);

-- Insert pertanyaan untuk master soal "Matematika Dasar"
INSERT INTO soal_pertanyaan (master_soal_id, urutan, tipe_soal, pertanyaan, waktu_detik, jawaban_benar, pilihan_a, pilihan_b, pilihan_c, pilihan_d, skor) VALUES
(1, 1, 'pilihan_ganda', 'Berapa hasil dari 5 + 3?', 30, 'B', '6', '8', '7', '9', 1.00),
(1, 2, 'pilihan_ganda', 'Berapa hasil dari 10 - 4?', 30, 'A', '6', '5', '7', '8', 1.00),
(1, 3, 'essay', 'Jelaskan cara menghitung luas persegi panjang dengan panjang 10 cm dan lebar 5 cm!', 120, NULL, NULL, NULL, NULL, NULL, 2.00),
(1, 4, 'yes_no', 'Apakah 15 adalah bilangan ganjil?', 20, 'yes', NULL, NULL, NULL, NULL, 1.00);

-- Insert pertanyaan untuk master soal "Bahasa Indonesia"
INSERT INTO soal_pertanyaan (master_soal_id, urutan, tipe_soal, pertanyaan, waktu_detik, jawaban_benar, pilihan_a, pilihan_b, pilihan_c, pilihan_d, skor) VALUES
(2, 1, 'pilihan_ganda', 'Manakah yang termasuk kata benda?', 45, 'A', 'Meja', 'Berlari', 'Cantik', 'Sangat', 1.00),
(2, 2, 'pilihan_ganda', 'Manakah yang termasuk kata kerja?', 45, 'B', 'Indah', 'Menulis', 'Biru', 'Tinggi', 1.00),
(2, 3, 'essay', 'Buatlah kalimat dengan kata "belajar"!', 60, NULL, NULL, NULL, NULL, NULL, 1.00);

-- Insert pertanyaan untuk master soal "Logika Sederhana"
INSERT INTO soal_pertanyaan (master_soal_id, urutan, tipe_soal, pertanyaan, waktu_detik, jawaban_benar, pilihan_a, pilihan_b, pilihan_c, pilihan_d, skor) VALUES
(3, 1, 'pilihan_ganda', 'Jika semua kucing adalah hewan, dan Fluffy adalah kucing, maka Fluffy adalah?', 60, 'A', 'Hewan', 'Bukan hewan', 'Kucing', 'Tidak dapat ditentukan', 1.00),
(3, 2, 'yes_no', 'Apakah semua burung bisa terbang?', 30, 'no', NULL, NULL, NULL, NULL, 1.00);
