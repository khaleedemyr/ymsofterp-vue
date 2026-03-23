-- Seed awal blok home company profile (6 sel grid 2 kolom)
-- Urutan sort_order = urutan tampil di Next.js (kiri→kanan, baris demi baris).
-- video_path NULL: nanti isi lewat admin (edit blok → upload video).
-- Jalankan SETELAH create_web_profile_home_blocks.sql.
--
-- Kalau mau seed ulang dari nol:
--   DELETE FROM web_profile_home_blocks;

INSERT INTO `web_profile_home_blocks`
  (`sort_order`, `block_type`, `title`, `body`, `video_path`, `caption`, `bg_variant`, `is_active`, `created_at`, `updated_at`)
VALUES
  (0, 'text',
   'Crafted with Expertise, Served with Heart',
   'Every dish is thoughtfully prepared by experienced culinary professionals who value precision, quality ingredients, and consistent excellence.',
   NULL, NULL, 'dark', 1, NOW(), NOW()),

  (1, 'video',
   NULL, NULL, NULL,
   'VIDEO PRODUCT\nMAKING PROCESS',
   'video_dark', 1, NOW(), NOW()),

  (2, 'video',
   NULL, NULL, NULL,
   'VIDEO WAITER\nSERVING CUSTOMERS',
   'video_dark', 1, NOW(), NOW()),

  (3, 'text',
   'Professional Taste, Warm Embrace',
   'Our approach to dining is rooted in professionalism from carefully developed recipes and high-quality ingredients to consistent standards in preparation and presentation.',
   NULL, NULL, 'light', 1, NOW(), NOW()),

  (4, 'text',
   'A Refined Experience for Every Moment',
   'We believe that dining should be more than just a meal. Every element is carefully designed to deliver a sense of refinement without losing warmth and comfort.',
   NULL, NULL, 'light', 1, NOW(), NOW()),

  (5, 'video',
   NULL, NULL, NULL,
   'VIDEO POURING BUTTER\nON T-BONE STEAK',
   'video_dark', 1, NOW(), NOW());
