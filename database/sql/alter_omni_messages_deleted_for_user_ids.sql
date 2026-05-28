ALTER TABLE `omni_messages`
ADD COLUMN `deleted_for_user_ids` JSON NULL
AFTER `payload`;
