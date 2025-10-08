-- SQL untuk membuat tabel enroll test
-- Tabel untuk menyimpan data enrollment test

-- Tabel untuk menyimpan enrollment test
CREATE TABLE IF NOT EXISTS enroll_tests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    master_soal_id BIGINT UNSIGNED NOT NULL COMMENT 'ID master soal',
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'ID user yang di-enroll',
    status ENUM('enrolled', 'in_progress', 'completed', 'expired', 'cancelled') DEFAULT 'enrolled' COMMENT 'Status enrollment',
    enrolled_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal enroll',
    started_at TIMESTAMP NULL COMMENT 'Tanggal mulai test',
    completed_at TIMESTAMP NULL COMMENT 'Tanggal selesai test',
    expired_at TIMESTAMP NULL COMMENT 'Tanggal expired test',
    time_limit_minutes INT UNSIGNED NULL COMMENT 'Batas waktu dalam menit (optional)',
    max_attempts INT UNSIGNED DEFAULT 1 COMMENT 'Maksimal percobaan',
    current_attempt INT UNSIGNED DEFAULT 0 COMMENT 'Percobaan saat ini',
    notes TEXT NULL COMMENT 'Catatan enrollment',
    created_by BIGINT UNSIGNED NULL COMMENT 'ID user yang membuat enrollment',
    updated_by BIGINT UNSIGNED NULL COMMENT 'ID user yang terakhir update',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (master_soal_id) REFERENCES master_soal(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_master_soal_id (master_soal_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_enrolled_at (enrolled_at),
    INDEX idx_completed_at (completed_at),
    UNIQUE KEY unique_user_soal (user_id, master_soal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Enrollment test untuk master soal';

-- Tabel untuk menyimpan hasil test
CREATE TABLE IF NOT EXISTS test_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enroll_test_id BIGINT UNSIGNED NOT NULL COMMENT 'ID enroll test',
    attempt_number INT UNSIGNED NOT NULL COMMENT 'Nomor percobaan',
    started_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal mulai test',
    completed_at TIMESTAMP NULL COMMENT 'Tanggal selesai test',
    total_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Total skor yang didapat',
    max_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Skor maksimal yang bisa didapat',
    percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Persentase skor',
    time_taken_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Waktu yang digunakan dalam detik',
    status ENUM('in_progress', 'completed', 'timeout', 'cancelled') DEFAULT 'in_progress' COMMENT 'Status test',
    answers JSON NULL COMMENT 'Jawaban user dalam format JSON',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (enroll_test_id) REFERENCES enroll_tests(id) ON DELETE CASCADE,
    
    INDEX idx_enroll_test_id (enroll_test_id),
    INDEX idx_attempt_number (attempt_number),
    INDEX idx_started_at (started_at),
    INDEX idx_completed_at (completed_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hasil test untuk setiap percobaan';

-- Tabel untuk menyimpan jawaban detail per pertanyaan
CREATE TABLE IF NOT EXISTS test_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    test_result_id BIGINT UNSIGNED NOT NULL COMMENT 'ID test result',
    soal_pertanyaan_id BIGINT UNSIGNED NOT NULL COMMENT 'ID soal pertanyaan',
    user_answer TEXT NULL COMMENT 'Jawaban user',
    is_correct BOOLEAN DEFAULT FALSE COMMENT 'Apakah jawaban benar',
    score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Skor yang didapat untuk pertanyaan ini',
    max_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Skor maksimal untuk pertanyaan ini',
    time_taken_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Waktu yang digunakan untuk pertanyaan ini',
    answered_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal jawab',
    
    FOREIGN KEY (test_result_id) REFERENCES test_results(id) ON DELETE CASCADE,
    FOREIGN KEY (soal_pertanyaan_id) REFERENCES soal_pertanyaan(id) ON DELETE CASCADE,
    
    INDEX idx_test_result_id (test_result_id),
    INDEX idx_soal_pertanyaan_id (soal_pertanyaan_id),
    INDEX idx_is_correct (is_correct),
    INDEX idx_answered_at (answered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detail jawaban per pertanyaan';
