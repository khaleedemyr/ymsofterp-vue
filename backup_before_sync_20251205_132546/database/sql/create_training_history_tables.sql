-- Create training history tables
CREATE TABLE training_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    schedule_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    user_type ENUM('participant', 'trainer') NOT NULL,
    
    -- Training details (denormalized for history)
    course_title VARCHAR(255) NOT NULL,
    course_description TEXT NULL,
    scheduled_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    outlet_name VARCHAR(255) NULL,
    outlet_address TEXT NULL,
    
    -- User details (denormalized for history)
    user_name VARCHAR(255) NOT NULL,
    user_email VARCHAR(255) NULL,
    user_jabatan VARCHAR(255) NULL,
    user_divisi VARCHAR(255) NULL,
    
    -- Training duration tracking
    planned_duration_minutes INT NOT NULL COMMENT 'Durasi training yang direncanakan',
    actual_duration_minutes INT NULL COMMENT 'Durasi training yang sebenarnya',
    checkin_time TIMESTAMP NULL COMMENT 'Waktu check-in user',
    checkout_time TIMESTAMP NULL COMMENT 'Waktu check-out user',
    user_duration_minutes INT NULL COMMENT 'Durasi user mengikuti training',
    
    -- Training status
    training_status ENUM('completed', 'incomplete', 'cancelled') DEFAULT 'completed',
    completion_percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Persentase completion training',
    
    -- Additional info
    notes TEXT NULL COMMENT 'Catatan tambahan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (schedule_id) REFERENCES training_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_training_history_schedule_id (schedule_id),
    INDEX idx_training_history_user_id (user_id),
    INDEX idx_training_history_user_type (user_type),
    INDEX idx_training_history_scheduled_date (scheduled_date),
    INDEX idx_training_history_checkout_time (checkout_time)
);

-- Create training session history table
CREATE TABLE training_session_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    training_history_id BIGINT NOT NULL,
    session_id BIGINT NOT NULL,
    
    -- Session details (denormalized)
    session_title VARCHAR(255) NOT NULL,
    session_description TEXT NULL,
    session_order_number INT NOT NULL,
    is_required TINYINT(1) DEFAULT 1,
    estimated_duration_minutes INT NULL,
    
    -- Session completion
    session_status ENUM('not_started', 'in_progress', 'completed', 'skipped') DEFAULT 'not_started',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    actual_duration_minutes INT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (training_history_id) REFERENCES training_history(id) ON DELETE CASCADE,
    INDEX idx_training_session_history_training_history_id (training_history_id),
    INDEX idx_training_session_history_session_id (session_id)
);

-- Create training session item history table
CREATE TABLE training_session_item_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    training_session_history_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    
    -- Item details (denormalized)
    item_type ENUM('quiz', 'questionnaire', 'material') NOT NULL,
    item_title VARCHAR(255) NOT NULL,
    item_description TEXT NULL,
    item_order_number INT NOT NULL,
    is_required TINYINT(1) DEFAULT 1,
    estimated_duration_minutes INT NULL,
    
    -- Item completion
    item_status ENUM('not_started', 'in_progress', 'completed', 'skipped') DEFAULT 'not_started',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    actual_duration_minutes INT NULL,
    
    -- Quiz specific data
    quiz_score DECIMAL(5,2) NULL,
    quiz_attempts INT DEFAULT 0,
    quiz_passed TINYINT(1) NULL,
    
    -- Questionnaire specific data
    questionnaire_completed TINYINT(1) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (training_session_history_id) REFERENCES training_session_history(id) ON DELETE CASCADE,
    INDEX idx_training_session_item_history_training_session_history_id (training_session_history_id),
    INDEX idx_training_session_item_history_item_id (item_id),
    INDEX idx_training_session_item_history_item_type (item_type)
);
