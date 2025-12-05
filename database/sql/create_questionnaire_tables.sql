-- Create questionnaire tables
CREATE TABLE lms_questionnaires (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructions TEXT,
    is_anonymous BOOLEAN DEFAULT FALSE,
    allow_multiple_responses BOOLEAN DEFAULT FALSE,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    start_date DATE,
    end_date DATE,
    created_by BIGINT,
    updated_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

CREATE TABLE lms_questionnaire_questions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    questionnaire_id BIGINT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'essay', 'true_false', 'rating', 'checkbox') NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    order_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (questionnaire_id) REFERENCES lms_questionnaires(id) ON DELETE CASCADE
);

CREATE TABLE lms_questionnaire_options (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    question_id BIGINT NOT NULL,
    option_text VARCHAR(500) NOT NULL,
    order_number INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES lms_questionnaire_questions(id) ON DELETE CASCADE
);

CREATE TABLE lms_questionnaire_responses (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    questionnaire_id BIGINT NOT NULL,
    user_id BIGINT NULL, -- NULL if anonymous
    respondent_name VARCHAR(255) NULL, -- For anonymous responses
    respondent_email VARCHAR(255) NULL, -- For anonymous responses
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (questionnaire_id) REFERENCES lms_questionnaires(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE lms_questionnaire_answers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    response_id BIGINT NOT NULL,
    question_id BIGINT NOT NULL,
    answer_text TEXT NULL, -- For essay questions
    selected_option_id BIGINT NULL, -- For multiple choice/checkbox
    rating_value INT NULL, -- For rating questions (1-5)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (response_id) REFERENCES lms_questionnaire_responses(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES lms_questionnaire_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES lms_questionnaire_options(id) ON DELETE SET NULL
);

-- Insert menu and permissions for questionnaire
INSERT INTO erp_menu (id, name, code, parent_id, route, icon, created_at, updated_at) VALUES
(128, 'Kuesioner', 'questionnaire', 127, 'lms.questionnaires.index', 'fas fa-clipboard-list', NOW(), NOW());

INSERT INTO erp_permission (id, menu_id, action, code, created_at, updated_at) VALUES
(511, 128, 'view', 'questionnaire.view', NOW(), NOW()),
(512, 128, 'create', 'questionnaire.create', NOW(), NOW()),
(513, 128, 'update', 'questionnaire.update', NOW(), NOW()),
(514, 128, 'delete', 'questionnaire.delete', NOW(), NOW());
