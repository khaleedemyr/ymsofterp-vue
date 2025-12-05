CREATE TABLE training_invitations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    schedule_id BIGINT,
    user_id BIGINT,
    invitation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('invited', 'confirmed', 'attended', 'absent', 'cancelled') DEFAULT 'invited',
    qr_code VARCHAR(255), -- unique QR code untuk check-in
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    certificate_issued BOOLEAN DEFAULT FALSE,
    certificate_issued_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES training_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_invitation (schedule_id, user_id),
    INDEX idx_qr_code (qr_code),
    INDEX idx_status (status)
);
