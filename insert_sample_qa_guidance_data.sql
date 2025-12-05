-- Insert sample data untuk QA Guidance jika belum ada

-- Insert sample QA Categories jika belum ada
INSERT IGNORE INTO `qa_categories` (`kode_categories`, `categories`, `status`, `created_at`, `updated_at`) VALUES
('QA001', 'Food Safety', 'A', NOW(), NOW()),
('QA002', 'Hygiene', 'A', NOW(), NOW()),
('QA003', 'Service Quality', 'A', NOW(), NOW());

-- Insert sample QA Parameters jika belum ada
INSERT IGNORE INTO `qa_parameters` (`kode_parameter`, `parameter`, `status`, `created_at`, `updated_at`) VALUES
('QP001', 'Temperature Control', 'A', NOW(), NOW()),
('QP002', 'Hygiene Standards', 'A', NOW(), NOW()),
('QP003', 'Quality Check', 'A', NOW(), NOW()),
('QP004', 'Safety Protocols', 'A', NOW(), NOW()),
('QP005', 'Performance Metrics', 'A', NOW(), NOW());

-- Insert sample QA Guidances
INSERT IGNORE INTO `qa_guidances` (`title`, `departemen`, `status`, `created_at`, `updated_at`) VALUES
('Kitchen Quality Control', 'Kitchen', 'A', NOW(), NOW()),
('Bar Service Excellence', 'Bar', 'A', NOW(), NOW()),
('Service Quality Standards', 'Service', 'A', NOW(), NOW());

-- Insert sample guidance categories
INSERT IGNORE INTO `qa_guidance_categories` (`guidance_id`, `category_id`, `created_at`, `updated_at`) VALUES
(1, 1, NOW(), NOW()), -- Kitchen Quality Control -> Food Safety
(1, 2, NOW(), NOW()), -- Kitchen Quality Control -> Hygiene
(2, 2, NOW(), NOW()), -- Bar Service Excellence -> Hygiene
(2, 3, NOW(), NOW()), -- Bar Service Excellence -> Service Quality
(3, 1, NOW(), NOW()), -- Service Quality Standards -> Food Safety
(3, 3, NOW(), NOW()); -- Service Quality Standards -> Service Quality

-- Insert sample guidance category parameters
INSERT IGNORE INTO `qa_guidance_category_parameters` (`guidance_category_id`, `parameter_pemeriksaan`, `created_at`, `updated_at`) VALUES
-- Kitchen Quality Control -> Food Safety
(1, 'Temperature Control Check', NOW(), NOW()),
(1, 'Food Storage Check', NOW(), NOW()),
-- Kitchen Quality Control -> Hygiene  
(2, 'Hand Washing Check', NOW(), NOW()),
(2, 'Surface Cleanliness Check', NOW(), NOW()),
-- Bar Service Excellence -> Hygiene
(3, 'Bar Sanitization Check', NOW(), NOW()),
-- Bar Service Excellence -> Service Quality
(4, 'Customer Service Check', NOW(), NOW()),
(4, 'Order Accuracy Check', NOW(), NOW()),
-- Service Quality Standards -> Food Safety
(5, 'Food Presentation Check', NOW(), NOW()),
-- Service Quality Standards -> Service Quality
(6, 'Table Service Check', NOW(), NOW()),
(6, 'Customer Satisfaction Check', NOW(), NOW());

-- Insert sample parameter details
INSERT IGNORE INTO `qa_guidance_parameter_details` (`category_parameter_id`, `parameter_id`, `point`, `created_at`, `updated_at`) VALUES
-- Temperature Control Check
(1, 1, 10, NOW(), NOW()), -- Temperature Control (10 points)
(1, 2, 15, NOW(), NOW()), -- Hygiene Standards (15 points)
-- Food Storage Check
(2, 3, 20, NOW(), NOW()), -- Quality Check (20 points)
-- Hand Washing Check
(3, 2, 25, NOW(), NOW()), -- Hygiene Standards (25 points)
(3, 4, 30, NOW(), NOW()), -- Safety Protocols (30 points)
-- Surface Cleanliness Check
(4, 2, 20, NOW(), NOW()), -- Hygiene Standards (20 points)
-- Bar Sanitization Check
(5, 2, 15, NOW(), NOW()), -- Hygiene Standards (15 points)
(5, 4, 20, NOW(), NOW()), -- Safety Protocols (20 points)
-- Customer Service Check
(6, 5, 25, NOW(), NOW()), -- Performance Metrics (25 points)
-- Order Accuracy Check
(7, 3, 30, NOW(), NOW()), -- Quality Check (30 points)
-- Food Presentation Check
(8, 3, 20, NOW(), NOW()), -- Quality Check (20 points)
-- Table Service Check
(9, 5, 25, NOW(), NOW()), -- Performance Metrics (25 points)
-- Customer Satisfaction Check
(10, 5, 35, NOW(), NOW()); -- Performance Metrics (35 points)
