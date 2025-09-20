-- Insert semua data area (Operations + Kitchen) dengan sequence lengkap
-- Operations: OPS001 - OPS030 (departemen_id = 1)
-- Kitchen: OPS031 - OPS057 (departemen_id = 2)

-- Pastikan departemen ada
INSERT IGNORE INTO `departemens` (`id`, `nama_departemen`, `kode_departemen`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Operations', 'OPS', 'Departemen operasional', 'A', NOW(), NOW()),
(2, 'Kitchen', 'KIT', 'Departemen kitchen', 'A', NOW(), NOW());

-- Insert semua area dengan sequence lengkap OPS001 - OPS057
INSERT INTO `areas` (`nama_area`, `kode_area`, `departemen_id`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
-- Operations Areas (OPS001 - OPS030)
('Parking Area', 'OPS001', 1, 'Area parkir kendaraan', 'A', NOW(), NOW()),
('Neon Sign / Loly Pop', 'OPS002', 1, 'Area neon sign dan loly pop', 'A', NOW(), NOW()),
('Security Area', 'OPS003', 1, 'Area keamanan', 'A', NOW(), NOW()),
('Guest Waiting Area', 'OPS004', 1, 'Area tunggu tamu', 'A', NOW(), NOW()),
('Greeter Area', 'OPS005', 1, 'Area penyambut tamu', 'A', NOW(), NOW()),
('Cashier Area', 'OPS006', 1, 'Area kasir', 'A', NOW(), NOW()),
('Petty Cash', 'OPS007', 1, 'Area petty cash', 'A', NOW(), NOW()),
('Pastry Show Case', 'OPS008', 1, 'Area pastry show case', 'A', NOW(), NOW()),
('Dine-in Area & VIP Room', 'OPS009', 1, 'Area makan dan ruang VIP', 'A', NOW(), NOW()),
('AC, Air Curtain, Lighting & Sound System', 'OPS010', 1, 'Sistem AC, air curtain, lighting dan sound', 'A', NOW(), NOW()),
('Table & Chair/Sofa Set Up', 'OPS011', 1, 'Set up meja dan kursi/sofa', 'A', NOW(), NOW()),
('CCTV', 'OPS012', 1, 'Sistem CCTV', 'A', NOW(), NOW()),
('Wifi Connection', 'OPS013', 1, 'Koneksi WiFi', 'A', NOW(), NOW()),
('POS', 'OPS014', 1, 'Point of Sale system', 'A', NOW(), NOW()),
('CO Printer', 'OPS015', 1, 'Printer CO', 'A', NOW(), NOW()),
('Side Station Set Up', 'OPS016', 1, 'Set up side station', 'A', NOW(), NOW()),
('Equipment Set Up & Preparation (Condiments, Guest Supplies. etc)', 'OPS017', 1, 'Set up equipment dan persiapan (bumbu, perlengkapan tamu, dll)', 'A', NOW(), NOW()),
('Toilet/Washtafel', 'OPS018', 1, 'Area toilet dan washtafel', 'A', NOW(), NOW()),
('Sanitation & Hygiene', 'OPS019', 1, 'Sanitasi dan kebersihan', 'A', NOW(), NOW()),
('Mushola', 'OPS020', 1, 'Area mushola', 'A', NOW(), NOW()),
('Garden/Plantation', 'OPS021', 1, 'Area taman dan tanaman', 'A', NOW(), NOW()),
('Employee Locker', 'OPS022', 1, 'Loker karyawan', 'A', NOW(), NOW()),
('Garbage Room Area', 'OPS023', 1, 'Area ruang sampah', 'A', NOW(), NOW()),
('Janitor Area', 'OPS024', 1, 'Area janitor', 'A', NOW(), NOW()),
('Back Area (Steward)', 'OPS025', 1, 'Area belakang (steward)', 'A', NOW(), NOW()),
('Storage Area', 'OPS026', 1, 'Area penyimpanan', 'A', NOW(), NOW()),
('FB Office', 'OPS027', 1, 'Kantor FB', 'A', NOW(), NOW()),
('IPAL & Main Grease Trap', 'OPS028', 1, 'IPAL dan main grease trap', 'A', NOW(), NOW()),
('Media Promo (TV,Tend Card, Banner,Baligho, dll)', 'OPS029', 1, 'Media promosi (TV, tend card, banner, baligho, dll)', 'A', NOW(), NOW()),
('Employee Grooming (Service & Security)', 'OPS030', 1, 'Grooming karyawan (service & security)', 'A', NOW(), NOW()),

-- Kitchen Areas (OPS031 - OPS057)
('Kitchen Fuel', 'OPS031', 2, 'Bahan bakar kitchen', 'A', NOW(), NOW()),
('Chiller & Freezer Temperature', 'OPS032', 2, 'Suhu chiller dan freezer', 'A', NOW(), NOW()),
('Stove Burner', 'OPS033', 2, 'Kompor dan burner', 'A', NOW(), NOW()),
('Kwali range', 'OPS034', 2, 'Range kwali', 'A', NOW(), NOW()),
('Microwave', 'OPS035', 2, 'Microwave oven', 'A', NOW(), NOW()),
('Griller', 'OPS036', 2, 'Griller equipment', 'A', NOW(), NOW()),
('Fryer', 'OPS037', 2, 'Deep fryer', 'A', NOW(), NOW()),
('Bain Marie/ Warmer', 'OPS038', 2, 'Bain marie dan warmer', 'A', NOW(), NOW()),
('Oven', 'OPS039', 2, 'Oven equipment', 'A', NOW(), NOW()),
('Sous Vide Equipment', 'OPS040', 2, 'Equipment sous vide', 'A', NOW(), NOW()),
('Vacuum Sealing', 'OPS041', 2, 'Vacuum sealing machine', 'A', NOW(), NOW()),
('Sink', 'OPS042', 2, 'Kitchen sink', 'A', NOW(), NOW()),
('Kitchen Hood & Ducting', 'OPS043', 2, 'Kitchen hood dan ducting', 'A', NOW(), NOW()),
('Kitchen Fresh Air', 'OPS044', 2, 'Fresh air system kitchen', 'A', NOW(), NOW()),
('Kitchen Lighting', 'OPS045', 2, 'Lighting kitchen', 'A', NOW(), NOW()),
('Steward Lighting', 'OPS046', 2, 'Lighting steward area', 'A', NOW(), NOW()),
('Dishwashing Machine', 'OPS047', 2, 'Mesin cuci piring', 'A', NOW(), NOW()),
('Water Supply', 'OPS048', 2, 'Supply air', 'A', NOW(), NOW()),
('APAR', 'OPS049', 2, 'Alat Pemadam Api Ringan', 'A', NOW(), NOW()),
('Main Grease trap', 'OPS050', 2, 'Main grease trap', 'A', NOW(), NOW()),
('Gutter', 'OPS051', 2, 'Gutter system', 'A', NOW(), NOW()),
('IPAL', 'OPS052', 2, 'Instalasi Pengolahan Air Limbah', 'A', NOW(), NOW()),
('FIFO / FEFO Check', 'OPS053', 2, 'Pengecekan FIFO/FEFO', 'A', NOW(), NOW()),
('Commodity Labeling', 'OPS054', 2, 'Labeling komoditas', 'A', NOW(), NOW()),
('Storage Area', 'OPS055', 2, 'Area penyimpanan', 'A', NOW(), NOW()),
('Employee Grooming (Kitchen & Steward)', 'OPS056', 2, 'Grooming karyawan kitchen & steward', 'A', NOW(), NOW()),
('Employee Locker', 'OPS057', 2, 'Loker karyawan', 'A', NOW(), NOW());

-- Verifikasi semua data yang telah diinsert
SELECT 
    a.id,
    a.nama_area,
    a.kode_area,
    d.nama_departemen,
    a.deskripsi,
    a.status
FROM areas a
JOIN departemens d ON a.departemen_id = d.id
ORDER BY a.kode_area;
