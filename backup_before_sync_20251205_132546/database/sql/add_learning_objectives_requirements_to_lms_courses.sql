-- =====================================================
-- Add Learning Objectives and Requirements to LMS Courses
-- =====================================================

-- Add learning_objectives and requirements columns to lms_courses table
ALTER TABLE `lms_courses` 
ADD COLUMN `learning_objectives` JSON DEFAULT NULL COMMENT 'Array of learning objectives for the course' AFTER `meta_description`,
ADD COLUMN `requirements` JSON DEFAULT NULL COMMENT 'Array of requirements for participants' AFTER `learning_objectives`;

-- Create indexes for better performance
CREATE INDEX `lms_courses_learning_objectives_index` ON `lms_courses` ((CAST(learning_objectives AS CHAR(1000))));
CREATE INDEX `lms_courses_requirements_index` ON `lms_courses` ((CAST(requirements AS CHAR(1000))));

-- Update existing courses with default learning objectives and requirements
UPDATE `lms_courses` 
SET `learning_objectives` = JSON_ARRAY(
    'Memahami kebijakan dan prosedur perusahaan',
    'Menguasai skill yang diperlukan untuk pekerjaan',
    'Meningkatkan produktivitas dan efisiensi kerja',
    'Mengembangkan kompetensi sesuai standar perusahaan',
    'Memenuhi persyaratan compliance dan regulasi'
),
`requirements` = JSON_ARRAY(
    'Karyawan aktif perusahaan',
    'Komputer dengan koneksi intranet',
    'Waktu belajar sesuai jadwal yang ditentukan',
    'Kemauan untuk mengikuti training'
)
WHERE `learning_objectives` IS NULL OR `requirements` IS NULL;
