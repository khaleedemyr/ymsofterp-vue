-- Fix CRM Dashboard 503 Error
-- Add indexes to optimize queries

-- Index untuk tabel orders (db_justus)
USE db_justus;

-- Index untuk member_id dan status (paling sering diquery)
ALTER TABLE orders ADD INDEX idx_member_status (member_id, status);

-- Index untuk created_at (untuk filter tanggal)
ALTER TABLE orders ADD INDEX idx_created_at (created_at);

-- Composite index untuk query yang sering dipakai
ALTER TABLE orders ADD INDEX idx_member_status_created (member_id, status, created_at);

-- Index untuk member_apps_members
USE ymsofterp;

-- Index untuk member_id (untuk JOIN)
ALTER TABLE member_apps_members ADD INDEX idx_member_id (member_id);

-- Index untuk is_active (untuk filter)
ALTER TABLE member_apps_members ADD INDEX idx_is_active (is_active);

-- Index untuk tanggal_lahir (untuk age calculation)
ALTER TABLE member_apps_members ADD INDEX idx_tanggal_lahir (tanggal_lahir);

-- Composite index untuk query kompleks
ALTER TABLE member_apps_members ADD INDEX idx_member_active_dob (member_id, is_active, tanggal_lahir);
