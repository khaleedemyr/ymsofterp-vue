-- Drop tables in reverse order (due to foreign key constraints)
DROP TABLE IF EXISTS `document_versions`;
DROP TABLE IF EXISTS `document_permissions`;
DROP TABLE IF EXISTS `shared_documents`;

-- Verify tables are dropped
SHOW TABLES LIKE '%document%'; 