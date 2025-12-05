-- Insert data area dengan auto generate kode area
-- Query ini menggunakan sistem auto generate yang sudah dibuat di controller

-- Pastikan departemen dengan id=1 ada dan memiliki kode_departemen
-- Jika belum ada, buat dulu departemen dengan kode 'OPS' (Operations)
INSERT IGNORE INTO `departemens` (`id`, `nama_departemen`, `kode_departemen`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Operations', 'OPS', 'Departemen operasional', 'A', NOW(), NOW());

-- Insert data area (kode_area akan di-generate otomatis oleh sistem)
-- Format kode: [Kode Departemen][3 huruf pertama nama area]
-- Contoh: OPS + PAR = OPSPAR (Parking Area)

INSERT INTO `areas` (`nama_area`, `kode_area`, `departemen_id`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
('Parking Area', 'OPSPAR', 1, 'Area parkir kendaraan', 'A', NOW(), NOW()),
('Neon Sign / Loly Pop', 'OPSNEO', 1, 'Area neon sign dan loly pop', 'A', NOW(), NOW()),
('Security Area', 'OPSSEC', 1, 'Area keamanan', 'A', NOW(), NOW()),
('Guest Waiting Area', 'OPSGUE', 1, 'Area tunggu tamu', 'A', NOW(), NOW()),
('Greeter Area', 'OPSGRE', 1, 'Area penyambut tamu', 'A', NOW(), NOW()),
('Cashier Area', 'OPSCAS', 1, 'Area kasir', 'A', NOW(), NOW()),
('Petty Cash', 'OPSPET', 1, 'Area petty cash', 'A', NOW(), NOW()),
('Pastry Show Case', 'OPSPAS', 1, 'Area pastry show case', 'A', NOW(), NOW()),
('Dine-in Area & VIP Room', 'OPSDIN', 1, 'Area makan dan ruang VIP', 'A', NOW(), NOW()),
('AC, Air Curtain, Lighting & Sound System', 'OPSACL', 1, 'Sistem AC, air curtain, lighting dan sound', 'A', NOW(), NOW()),
('Table & Chair/Sofa Set Up', 'OPSTAB', 1, 'Set up meja dan kursi/sofa', 'A', NOW(), NOW()),
('CCTV', 'OPSCCT', 1, 'Sistem CCTV', 'A', NOW(), NOW()),
('Wifi Connection', 'OPSWIF', 1, 'Koneksi WiFi', 'A', NOW(), NOW()),
('POS', 'OPSPOS', 1, 'Point of Sale system', 'A', NOW(), NOW()),
('CO Printer', 'OPSCOP', 1, 'Printer CO', 'A', NOW(), NOW()),
('Side Station Set Up', 'OPSSID', 1, 'Set up side station', 'A', NOW(), NOW()),
('Equipment Set Up & Preparation (Condiments, Guest Supplies. etc)', 'OPSEQU', 1, 'Set up equipment dan persiapan (bumbu, perlengkapan tamu, dll)', 'A', NOW(), NOW()),
('Toilet/Washtafel', 'OPSTOI', 1, 'Area toilet dan washtafel', 'A', NOW(), NOW()),
('Sanitation & Hygiene', 'OPSSAN', 1, 'Sanitasi dan kebersihan', 'A', NOW(), NOW()),
('Mushola', 'OPSMUS', 1, 'Area mushola', 'A', NOW(), NOW()),
('Garden/Plantation', 'OPSGAR', 1, 'Area taman dan tanaman', 'A', NOW(), NOW()),
('Employee Locker', 'OPSEMP', 1, 'Loker karyawan', 'A', NOW(), NOW()),
('Garbage Room Area', 'OPSGAR', 1, 'Area ruang sampah', 'A', NOW(), NOW()),
('Janitor Area', 'OPSJAN', 1, 'Area janitor', 'A', NOW(), NOW()),
('Back Area (Steward)', 'OPSBAC', 1, 'Area belakang (steward)', 'A', NOW(), NOW()),
('Storage Area', 'OPSSTO', 1, 'Area penyimpanan', 'A', NOW(), NOW()),
('FB Office', 'OPSFBO', 1, 'Kantor FB', 'A', NOW(), NOW()),
('IPAL & Main Grease Trap', 'OPSIPA', 1, 'IPAL dan main grease trap', 'A', NOW(), NOW()),
('Media Promo (TV,Tend Card, Banner,Baligho, dll)', 'OPSMED', 1, 'Media promosi (TV, tend card, banner, baligho, dll)', 'A', NOW(), NOW()),
('Employee Grooming (Service & Security)', 'OPSEMG', 1, 'Grooming karyawan (service & security)', 'A', NOW(), NOW());

-- Note: Ada duplikasi kode untuk 'Garden/Plantation' dan 'Garbage Room Area' (keduanya OPSGAR)
-- Sistem auto generate akan handle ini dengan menambahkan nomor urut
-- Garden/Plantation akan menjadi OPSGAR
-- Garbage Room Area akan menjadi OPSGAR01
