-- Insert data area untuk departemen_id = 2 (Kitchen)
-- Kode area melanjutkan sequence dari OPS031 sampai OPS056
-- Format: OPS + 3 digit nomor urut (031-056)

-- Pastikan departemen dengan id=2 ada dan memiliki kode_departemen
INSERT IGNORE INTO `departemens` (`id`, `nama_departemen`, `kode_departemen`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
(2, 'Kitchen', 'KIT', 'Departemen kitchen', 'A', NOW(), NOW());

-- Insert data area dengan kode sequence lanjutan
INSERT INTO `areas` (`nama_area`, `kode_area`, `departemen_id`, `deskripsi`, `status`, `created_at`, `updated_at`) VALUES
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
WHERE a.departemen_id = 2
ORDER BY a.kode_area;
