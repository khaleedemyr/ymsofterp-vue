-- Create training feedback tables
CREATE TABLE training_feedback (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    schedule_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    training_rating INT NOT NULL COMMENT 'Rating 1-5 untuk training',
    comments TEXT NULL COMMENT 'Kesan dan pesan peserta',
    suggestions TEXT NULL COMMENT 'Saran untuk training selanjutnya',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (schedule_id) REFERENCES training_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_training_feedback (schedule_id, user_id)
);

CREATE TABLE training_trainer_feedback (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    feedback_id BIGINT NOT NULL,
    trainer_id BIGINT NOT NULL,
    rating INT NOT NULL COMMENT 'Rating 1-5 untuk trainer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (feedback_id) REFERENCES training_feedback(id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_trainer_feedback (feedback_id, trainer_id)
);

-- Add indexes for better performance
CREATE INDEX idx_training_feedback_schedule_id ON training_feedback(schedule_id);
CREATE INDEX idx_training_feedback_user_id ON training_feedback(user_id);
CREATE INDEX idx_training_feedback_rating ON training_feedback(training_rating);
CREATE INDEX idx_training_trainer_feedback_feedback_id ON training_trainer_feedback(feedback_id);
CREATE INDEX idx_training_trainer_feedback_trainer_id ON training_trainer_feedback(trainer_id);
CREATE INDEX idx_training_trainer_feedback_rating ON training_trainer_feedback(rating);
