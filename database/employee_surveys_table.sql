-- Create employee_surveys table
CREATE TABLE employee_surveys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    surveyor_id BIGINT UNSIGNED NOT NULL,
    surveyor_name VARCHAR(255) NOT NULL,
    surveyor_position VARCHAR(255) NOT NULL,
    surveyor_division VARCHAR(255) NOT NULL,
    surveyor_outlet VARCHAR(255) NOT NULL,
    survey_date DATE NOT NULL,
    status ENUM('draft', 'submitted') DEFAULT 'draft',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_surveyor_id (surveyor_id),
    INDEX idx_survey_date (survey_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create employee_survey_responses table
CREATE TABLE employee_survey_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id BIGINT UNSIGNED NOT NULL,
    question_category VARCHAR(255) NOT NULL,
    question_text TEXT NOT NULL,
    score TINYINT UNSIGNED NOT NULL CHECK (score >= 1 AND score <= 5),
    comment TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (survey_id) REFERENCES employee_surveys(id) ON DELETE CASCADE,
    INDEX idx_survey_id (survey_id),
    INDEX idx_question_category (question_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
