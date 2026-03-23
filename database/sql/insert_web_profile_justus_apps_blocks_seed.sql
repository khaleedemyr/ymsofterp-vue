-- Seed data awal untuk halaman Justus Apps
-- Jalankan SETELAH create_web_profile_justus_apps_blocks.sql
--
-- Opsional reset:
--   DELETE FROM web_profile_justus_apps_blocks;
--
-- Catatan image_path:
-- - Isi path file di storage public (misal: web-profile/justus-apps/member-photo-1.jpg)
-- - Upload filenya via admin dulu, lalu sesuaikan path jika perlu.

INSERT INTO `web_profile_justus_apps_blocks`
  (`title`, `body`, `image_path`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES
  (
    'Premium steakhouse catering crafted to elevate your events',
    'Bring the signature flavors of Justus Steakhouse to your event. Our premium catering features expertly prepared steaks, curated menus, and warm hospitality - perfect for corporate gatherings and private celebrations.',
    'web-profile/justus-apps/member-photo-1.jpg',
    0,
    1,
    NOW(),
    NOW()
  ),
  (
    'Premium steakhouse catering crafted to elevate your events',
    'Bring the signature flavors of Justus Steakhouse to your event. Our premium catering features expertly prepared steaks, curated menus, and warm hospitality - perfect for corporate gatherings and private celebrations.',
    'web-profile/justus-apps/member-photo-2.jpg',
    1,
    1,
    NOW(),
    NOW()
  ),
  (
    'Premium steakhouse catering crafted to elevate your events',
    'Bring the signature flavors of Justus Steakhouse to your event. Our premium catering features expertly prepared steaks, curated menus, and warm hospitality - perfect for corporate gatherings and private celebrations.',
    'web-profile/justus-apps/member-photo-3.jpg',
    2,
    1,
    NOW(),
    NOW()
  ),
  (
    'Premium steakhouse catering crafted to elevate your events',
    'Bring the signature flavors of Justus Steakhouse to your event. Our premium catering features expertly prepared steaks, curated menus, and warm hospitality - perfect for corporate gatherings and private celebrations.',
    'web-profile/justus-apps/member-photo-4.jpg',
    3,
    1,
    NOW(),
    NOW()
  );

-- Opsional: set link store + hero di tabel setting
-- INSERT INTO web_profile_settings (`key`, `value`, `type`, `created_at`, `updated_at`)
-- VALUES
--   ('justus_apps_playstore_url', 'https://play.google.com/store/apps/details?id=com.example.app', 'text', NOW(), NOW()),
--   ('justus_apps_appstore_url', 'https://apps.apple.com/id/app/example/id000000000', 'text', NOW(), NOW()),
--   ('justus_apps_hero_image', 'web-profile/justus-apps/justus-apps-hero.jpg', 'image', NOW(), NOW())
--   -- atau untuk video:
--   -- ('justus_apps_hero_image', 'web-profile/justus-apps/justus-apps-hero.mp4', 'video', NOW(), NOW())
-- ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`), `updated_at` = NOW();

