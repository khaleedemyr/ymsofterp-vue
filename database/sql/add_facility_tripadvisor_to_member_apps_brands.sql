-- Add facility and tripadvisor_link columns to member_apps_brands table
-- Facility will be stored as JSON array
-- TripAdvisor link will be stored as text

ALTER TABLE `member_apps_brands`
ADD COLUMN `facility` JSON NULL AFTER `website_url`,
ADD COLUMN `tripadvisor_link` VARCHAR(500) NULL AFTER `facility`;

-- Example facility JSON format:
-- ["wifi", "smoking_area", "mushola", "meeting_room", "valet_parking"]

