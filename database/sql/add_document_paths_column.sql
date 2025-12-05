-- Add document_paths column to absent_requests table
ALTER TABLE absent_requests ADD COLUMN document_paths TEXT NULL AFTER document_path;

-- Add document_paths column to approval_requests table (if exists)
ALTER TABLE approval_requests ADD COLUMN document_paths TEXT NULL AFTER document_path;
