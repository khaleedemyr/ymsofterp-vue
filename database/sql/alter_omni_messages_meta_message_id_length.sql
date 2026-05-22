-- Instagram Login API memakai message id panjang (base64). Kolom 128 byte terlalu kecil.
-- Jalankan sekali di production setelah error: Data too long for column 'meta_message_id'

ALTER TABLE `omni_messages`
    MODIFY `meta_message_id` VARCHAR(512) NULL;
