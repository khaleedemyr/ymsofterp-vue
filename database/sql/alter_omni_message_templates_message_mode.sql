-- Template balasan cepat: mode pesan WA (teks, tombol, CTA, lampiran)
ALTER TABLE `omni_message_templates`
    ADD COLUMN `message_mode` VARCHAR(32) NOT NULL DEFAULT 'text' AFTER `body`,
    ADD COLUMN `config` JSON NULL AFTER `message_mode`;
