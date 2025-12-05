-- Insert data area dengan handle duplikasi kode yang aman
-- Pastikan departemen dengan id=1 ada dan memiliki kode_departemen

INSERT IGNORE INTO `departemens` (`id`, `nama_departemen`, `kode_departemen`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Operations', 'OPS', 'Departemen operasional', 'A', NOW(), NOW());

-- Insert data area dengan kode sequence
-- Format: OPS + 3 digit nomor urut (001-030)
-- Lebih mudah dan konsisten

INSERT INTO `areas` (`nama_area`, `kode_area`, `departemen_id`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
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
('Employee Grooming (Service & Security)', 'OPS030', 1, 'Grooming karyawan (service & security)', 'A', NOW(), NOW());

-- Verifikasi data yang telah diinsert
SELECT 
    a.id,
    a.nama_area,
    a.kode_area,
    d.nama_departemen,
    a.deskripsi,
    a.status
FROM areas a
JOIN departemens d ON a.departemen_id = d.id
WHERE a.departemen_id = 1
ORDER BY a.nama_area;
