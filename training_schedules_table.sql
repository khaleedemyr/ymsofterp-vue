CREATE TABLE training_schedules (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    course_id BIGINT,
    trainer_id BIGINT, -- internal trainer
    external_trainer_name VARCHAR(255), -- jika external
    outlet_id BIGINT, -- dari tbl_data_outlet
    scheduled_date DATE,
    start_time TIME,
    end_time TIME,
    max_participants INT,
    min_participants INT DEFAULT 1,
    status ENUM('draft', 'published', 'ongoing', 'completed', 'cancelled') DEFAULT 'draft',
    notes TEXT,
    created_by BIGINT, -- user yang create
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES lms_courses(id),
    FOREIGN KEY (outlet_id) REFERENCES tbl_data_outlet(id_outlet),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
