-- Tambah kolom share_token untuk link publik Customer Voice (WhatsApp share)
ALTER TABLE `feedback_cases`
  ADD COLUMN `share_token` varchar(64) DEFAULT NULL AFTER `meta`,
  ADD UNIQUE KEY `feedback_cases_share_token_unique` (`share_token`);
