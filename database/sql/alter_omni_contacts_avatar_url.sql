-- Avatar URL profil Instagram / Messenger (profile_pic dari Meta, URL kadang panjang)
ALTER TABLE `omni_contacts`
    ADD COLUMN `avatar_url` VARCHAR(1024) NULL AFTER `display_name`;
