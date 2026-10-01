-- Facility Safety QA2 seed for MySQL 5.7+ / Navicat.
-- Select the intended database before running this script.
-- Schema ALTER statements run first because MySQL DDL implicitly commits.
-- Data changes are committed after the verification query. The nullable-column ALTERs
-- are not transactional in MySQL and are applied before the data transaction.

-- Add the scoring mode columns only when missing.
SET @has_template_mode := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'qa2_templates'
      AND column_name = 'scoring_mode'
);
SET @ddl := IF(
    @has_template_mode = 0,
    'ALTER TABLE qa2_templates ADD COLUMN scoring_mode VARCHAR(20) NULL',
    'SELECT ''qa2_templates.scoring_mode already exists'''
);
PREPARE fsa_stmt FROM @ddl;
EXECUTE fsa_stmt;
DEALLOCATE PREPARE fsa_stmt;

SET @has_audit_mode := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'qa2_audits'
      AND column_name = 'scoring_mode'
);
SET @ddl := IF(
    @has_audit_mode = 0,
    'ALTER TABLE qa2_audits ADD COLUMN scoring_mode VARCHAR(20) NULL',
    'SELECT ''qa2_audits.scoring_mode already exists'''
);
PREPARE fsa_stmt FROM @ddl;
EXECUTE fsa_stmt;
DEALLOCATE PREPARE fsa_stmt;

-- Preflight: these queries should return no rows before the data phase.
SELECT 'duplicate category codes' AS check_name, code, COUNT(*) AS row_count
FROM qa2_categories
WHERE code IN ('FSA-C1', 'FSA-C2', 'FSA-C3', 'FSA-C4', 'FSA-C5')
GROUP BY code HAVING COUNT(*) > 1;

SELECT 'duplicate subcategory codes' AS check_name, code, COUNT(*) AS row_count
FROM qa2_subcategories
WHERE code LIKE 'FSA-S%'
GROUP BY code HAVING COUNT(*) > 1;

SELECT 'duplicate parameter codes' AS check_name, code, COUNT(*) AS row_count
FROM qa2_parameters
WHERE code LIKE 'FSA-%'
GROUP BY code HAVING COUNT(*) > 1;

SELECT 'duplicate target template' AS check_name, code, version, COUNT(*) AS row_count
FROM qa2_templates
WHERE code = 'FSA-BKP' AND version = 1
GROUP BY code, version HAVING COUNT(*) > 1;

-- Stop here and resolve duplicates if any preflight query above returned rows.

DROP TEMPORARY TABLE IF EXISTS tmp_fsa_categories;
CREATE TEMPORARY TABLE tmp_fsa_categories (
    code VARCHAR(30) NOT NULL PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    sort_order INT NOT NULL
);
INSERT INTO tmp_fsa_categories (code, name, sort_order) VALUES
    ('FSA-C1', 'CIVIL', 10),
    ('FSA-C2', 'MECHANICAL', 20),
    ('FSA-C3', 'ELECTRICAL', 30),
    ('FSA-C4', 'PLUMBING', 40),
    ('FSA-C5', 'PREVENTIVE MAINTENANCE', 50);

DROP TEMPORARY TABLE IF EXISTS tmp_fsa_subcategories;
CREATE TEMPORARY TABLE tmp_fsa_subcategories (
    code VARCHAR(30) NOT NULL PRIMARY KEY,
    category_code VARCHAR(30) NOT NULL,
    name VARCHAR(180) NOT NULL,
    sort_order INT NOT NULL
);
INSERT INTO tmp_fsa_subcategories (code, category_code, name, sort_order) VALUES
    ('FSA-S1-1', 'FSA-C1', 'Building & Structure', 10),
    ('FSA-S1-2', 'FSA-C1', 'Furniture', 20),
    ('FSA-S1-3', 'FSA-C1', 'Area & Guest Facility', 30),
    ('FSA-S2-1', 'FSA-C2', 'Mechanical Checklist', 10),
    ('FSA-S3-1', 'FSA-C3', 'Electrical Checklist', 10),
    ('FSA-S4-1', 'FSA-C4', 'Plumbing Checklist', 10),
    ('FSA-S5-1', 'FSA-C5', 'Planning', 10),
    ('FSA-S5-2', 'FSA-C5', 'Execution', 20),
    ('FSA-S5-3', 'FSA-C5', 'Corrective Action', 30),
    ('FSA-S5-4', 'FSA-C5', 'Documentation & Monitoring', 40);

DROP TEMPORARY TABLE IF EXISTS tmp_fsa_parameters;
CREATE TEMPORARY TABLE tmp_fsa_parameters (
    subcategory_code VARCHAR(30) NOT NULL,
    code VARCHAR(40) NOT NULL PRIMARY KEY,
    parameter_text TEXT NOT NULL,
    sort_order INT NOT NULL,
    template_sort INT NOT NULL
);
INSERT INTO tmp_fsa_parameters (subcategory_code, code, parameter_text, sort_order, template_sort) VALUES
    ('FSA-S1-1', 'FSA-1.1.1', 'Kondisi lantai baik, tidak retak, pecah, berlubang, amblas, atau licin.', 10, 1),
    ('FSA-S1-1', 'FSA-1.1.2', 'Kondisi dinding baik, tidak retak, lembap, berjamur, mengelupas, atau rusak.', 20, 2),
    ('FSA-S1-1', 'FSA-1.1.3', 'Kondisi plafon baik, tidak bocor, bernoda, retak, atau berpotensi jatuh.', 30, 3),
    ('FSA-S1-1', 'FSA-1.1.4', 'Kondisi atap baik dan tidak terdapat kebocoran.', 40, 4),
    ('FSA-S1-1', 'FSA-1.1.5', 'Kondisi pintu baik; engsel, handle, lock, dan door closer berfungsi.', 50, 5),
    ('FSA-S1-1', 'FSA-1.1.6', 'Kondisi kaca dan jendela baik, tidak retak/pecah.', 60, 6),
    ('FSA-S1-1', 'FSA-1.1.7', 'Kondisi frame dan konstruksi bangunan kokoh dan aman.', 70, 7),
    ('FSA-S1-2', 'FSA-1.2.1', 'Meja dalam kondisi kokoh, stabil, tidak goyang, dan tidak memiliki bagian tajam.', 10, 8),
    ('FSA-S1-2', 'FSA-1.2.2', 'Permukaan meja tidak rusak, pecah, mengelupas, atau membahayakan tamu.', 20, 9),
    ('FSA-S1-2', 'FSA-1.2.3', 'Kursi dalam kondisi kokoh, stabil, tidak patah atau goyang.', 30, 10),
    ('FSA-S1-2', 'FSA-1.2.4', 'Sambungan dan kaki kursi dalam kondisi baik.', 40, 11),
    ('FSA-S1-2', 'FSA-1.2.5', 'Sofa dalam kondisi kokoh dan stabil.', 50, 12),
    ('FSA-S1-2', 'FSA-1.2.6', 'Cushion sofa dalam kondisi baik dan nyaman digunakan.', 60, 13),
    ('FSA-S1-2', 'FSA-1.2.7', 'Upholstery sofa tidak sobek, rusak, atau mengelupas.', 70, 14),
    ('FSA-S1-2', 'FSA-1.2.8', 'Furniture dalam kondisi bersih dan memiliki appearance yang baik.', 80, 15),
    ('FSA-S1-2', 'FSA-1.2.9', 'Built-in furniture seperti counter, bar, cashier, cabinet, dan shelving dalam kondisi baik.', 90, 16),
    ('FSA-S1-2', 'FSA-1.2.10', 'Penempatan furniture tidak mengganggu circulation dan jalur evakuasi.', 100, 17),
    ('FSA-S1-3', 'FSA-1.3.1', 'Dining area dalam kondisi rapi, aman, nyaman, dan terawat.', 10, 18),
    ('FSA-S1-3', 'FSA-1.3.2', 'Kitchen civil condition baik dan mudah dibersihkan.', 20, 19),
    ('FSA-S1-3', 'FSA-1.3.3', 'Toilet, cubicle, partition, ceiling, dan accessories dalam kondisi baik.', 30, 20),
    ('FSA-S1-3', 'FSA-1.3.4', 'Exterior dan facade dalam kondisi baik.', 40, 21),
    ('FSA-S1-3', 'FSA-1.3.5', 'Kanopi, railing, pedestrian, dan area luar aman.', 50, 22),
    ('FSA-S1-3', 'FSA-1.3.6', 'Signage terpasang kokoh dan memiliki appearance yang baik.', 60, 23),
    ('FSA-S1-3', 'FSA-1.3.7', 'Drainage area tidak tersumbat dan tidak menyebabkan genangan.', 70, 24),
    ('FSA-S1-3', 'FSA-1.3.8', 'Tidak terdapat kondisi civil/furniture yang berpotensi menyebabkan injury.', 80, 25),
    ('FSA-S2-1', 'FSA-2.1', 'AC/HVAC berfungsi normal.', 10, 26),
    ('FSA-S2-1', 'FSA-2.2', 'Temperatur ruangan sesuai standar.', 20, 27),
    ('FSA-S2-1', 'FSA-2.3', 'AC indoor unit bersih dan tidak bocor.', 30, 28),
    ('FSA-S2-1', 'FSA-2.4', 'AC outdoor unit dalam kondisi baik.', 40, 29),
    ('FSA-S2-1', 'FSA-2.5', 'Airflow AC optimal.', 50, 30),
    ('FSA-S2-1', 'FSA-2.6', 'Exhaust kitchen berfungsi optimal.', 60, 31),
    ('FSA-S2-1', 'FSA-2.7', 'Fresh air/ventilation berfungsi baik.', 70, 32),
    ('FSA-S2-1', 'FSA-2.8', 'Exhaust toilet berfungsi baik.', 80, 33),
    ('FSA-S2-1', 'FSA-2.9', 'Fan dan blower tidak menimbulkan abnormal noise atau vibration.', 90, 34),
    ('FSA-S2-1', 'FSA-2.10', 'Water pump berfungsi normal.', 100, 35),
    ('FSA-S2-1', 'FSA-2.11', 'Grease trap equipment berfungsi baik.', 110, 36),
    ('FSA-S2-1', 'FSA-2.12', 'Mechanical kitchen equipment berfungsi sesuai standar.', 120, 37),
    ('FSA-S2-1', 'FSA-2.13', 'Motor, belt, bearing, dan moving parts dalam kondisi baik.', 130, 38),
    ('FSA-S2-1', 'FSA-2.14', 'Tidak terdapat abnormal vibration atau overheating.', 140, 39),
    ('FSA-S2-1', 'FSA-2.15', 'Safety guard equipment tersedia dan terpasang dengan baik.', 150, 40),
    ('FSA-S2-1', 'FSA-2.16', 'Equipment memiliki identification/tagging.', 160, 41),
    ('FSA-S2-1', 'FSA-2.17', 'Critical mechanical equipment memiliki spare part yang diperlukan.', 170, 42),
    ('FSA-S2-1', 'FSA-2.18', 'Tidak terdapat equipment yang berpotensi mengganggu operasional.', 180, 43),
    ('FSA-S2-1', 'FSA-2.19', 'Kondisi mechanical equipment mendukung produktivitas operasional.', 190, 44),
    ('FSA-S2-1', 'FSA-2.20', 'Seluruh mechanical equipment dalam kondisi aman digunakan.', 200, 45),
    ('FSA-S3-1', 'FSA-3.1', 'Main electrical panel dalam kondisi baik dan aman.', 10, 46),
    ('FSA-S3-1', 'FSA-3.2', 'Sub-panel dalam kondisi baik.', 20, 47),
    ('FSA-S3-1', 'FSA-3.3', 'MCB/MCCB sesuai kapasitas dan berfungsi normal.', 30, 48),
    ('FSA-S3-1', 'FSA-3.4', 'Kabel listrik tidak terbuka atau terkelupas.', 40, 49),
    ('FSA-S3-1', 'FSA-3.5', 'Tidak terdapat kabel terbakar atau rusak.', 50, 50),
    ('FSA-S3-1', 'FSA-3.6', 'Tidak terjadi electrical overload.', 60, 51),
    ('FSA-S3-1', 'FSA-3.7', 'Socket dalam kondisi baik dan berfungsi.', 70, 52),
    ('FSA-S3-1', 'FSA-3.8', 'Switch berfungsi normal.', 80, 53),
    ('FSA-S3-1', 'FSA-3.9', 'Lighting dining area, neon sign berfungsi dan sesuai standar.', 90, 54),
    ('FSA-S3-1', 'FSA-3.10', 'Lighting kitchen berfungsi dan sesuai standar.', 100, 55),
    ('FSA-S3-1', 'FSA-3.11', 'Emergency lighting berfungsi.', 110, 56),
    ('FSA-S3-1', 'FSA-3.12', 'Exit sign menyala dan terlihat jelas.', 120, 57),
    ('FSA-S3-1', 'FSA-3.13', 'Grounding tersedia dan berfungsi.', 130, 58),
    ('FSA-S3-1', 'FSA-3.14', 'Panel memiliki label circuit yang jelas.', 140, 59),
    ('FSA-S3-1', 'FSA-3.15', 'Panel bersih dan bebas dari barang/material.', 150, 60),
    ('FSA-S3-1', 'FSA-3.16', 'Tidak terdapat indikasi overheating pada panel.', 160, 61),
    ('FSA-S3-1', 'FSA-3.17', 'Genset dalam kondisi siap digunakan.', 170, 62),
    ('FSA-S3-1', 'FSA-3.18', 'UPS/battery dalam kondisi baik bila tersedia.', 180, 63),
    ('FSA-S3-1', 'FSA-3.19', 'Electrical equipment memiliki preventive maintenance.', 190, 64),
    ('FSA-S3-1', 'FSA-3.20', 'Electrical testing dilakukan sesuai jadwal/requirement.', 200, 65),
    ('FSA-S3-1', 'FSA-3.21', 'Tidak terdapat sambungan kabel yang tidak standar.', 210, 66),
    ('FSA-S3-1', 'FSA-3.22', 'Tidak terdapat penggunaan extension/terminal secara berlebihan.', 220, 67),
    ('FSA-S3-1', 'FSA-3.23', 'Electrical installation dalam kondisi aman.', 230, 68),
    ('FSA-S3-1', 'FSA-3.24', 'Tidak terdapat potensi short circuit.', 240, 69),
    ('FSA-S3-1', 'FSA-3.25', 'Tidak terdapat potensi electrical fire atau electric shock.', 250, 70),
    ('FSA-S4-1', 'FSA-4.1', 'Water supply tersedia dan stabil.', 10, 71),
    ('FSA-S4-1', 'FSA-4.2', 'Water pressure sesuai kebutuhan operasional.', 20, 72),
    ('FSA-S4-1', 'FSA-4.3', 'Pipa air tidak mengalami kebocoran.', 30, 73),
    ('FSA-S4-1', 'FSA-4.4', 'Faucet/kran berfungsi dan tidak bocor.', 40, 74),
    ('FSA-S4-1', 'FSA-4.5', 'Sink berfungsi dan tidak bocor.', 50, 75),
    ('FSA-S4-1', 'FSA-4.6', 'Floor drain tidak tersumbat.', 60, 76),
    ('FSA-S4-1', 'FSA-4.7', 'Drain cover tersedia dan dalam kondisi baik.', 70, 77),
    ('FSA-S4-1', 'FSA-4.8', 'Saluran pembuangan mengalir lancar.', 80, 78),
    ('FSA-S4-1', 'FSA-4.9', 'Tidak terdapat bau dari saluran drain.', 90, 79),
    ('FSA-S4-1', 'FSA-4.10', 'Grease trap berfungsi dan dibersihkan sesuai jadwal.', 100, 80),
    ('FSA-S4-1', 'FSA-4.11', 'Water tank bersih, tertutup, dan dalam kondisi baik.', 110, 81),
    ('FSA-S4-1', 'FSA-4.12', 'Water pump berfungsi normal.', 120, 82),
    ('FSA-S4-1', 'FSA-4.13', 'Water heater berfungsi dan aman.', 130, 83),
    ('FSA-S4-1', 'FSA-4.14', 'Toilet flush berfungsi normal.', 140, 84),
    ('FSA-S4-1', 'FSA-4.15', 'Bidet/spray berfungsi normal.', 150, 85),
    ('FSA-S4-1', 'FSA-4.16', 'Tidak terdapat kebocoran air.', 160, 86),
    ('FSA-S4-1', 'FSA-4.17', 'Tidak terdapat genangan air.', 170, 87),
    ('FSA-S4-1', 'FSA-4.18', 'Tidak terjadi backflow.', 180, 88),
    ('FSA-S4-1', 'FSA-4.19', 'Plumbing tidak menjadi sumber kontaminasi.', 190, 89),
    ('FSA-S4-1', 'FSA-4.20', 'Tidak terdapat kondisi yang berpotensi menyebabkan water damage.', 200, 90),
    ('FSA-S5-1', 'FSA-5.1.1', 'Seluruh equipment memiliki preventive maintenance schedule.', 10, 91),
    ('FSA-S5-1', 'FSA-5.1.2', 'Frequency preventive maintenance ditentukan dengan jelas.', 20, 92),
    ('FSA-S5-1', 'FSA-5.1.3', 'Critical equipment memiliki prioritas preventive maintenance.', 30, 93),
    ('FSA-S5-1', 'FSA-5.1.4', 'Preventive maintenance schedule dikomunikasikan kepada PIC terkait.', 40, 94),
    ('FSA-S5-1', 'FSA-5.1.5', 'Tidak terdapat preventive maintenance yang overdue.', 50, 95),
    ('FSA-S5-2', 'FSA-5.2.1', 'Preventive maintenance dilakukan sesuai jadwal.', 10, 96),
    ('FSA-S5-2', 'FSA-5.2.2', 'PM checklist tersedia untuk setiap equipment.', 20, 97),
    ('FSA-S5-2', 'FSA-5.2.3', 'Checklist diisi lengkap dan benar.', 30, 98),
    ('FSA-S5-2', 'FSA-5.2.4', 'Maintenance dilakukan oleh personel/vendor yang kompeten.', 40, 99),
    ('FSA-S5-2', 'FSA-5.2.5', 'Hasil inspection dicatat.', 50, 100),
    ('FSA-S5-2', 'FSA-5.2.6', 'Spare part yang diganti dicatat.', 60, 101),
    ('FSA-S5-2', 'FSA-5.2.7', 'Equipment diverifikasi setelah maintenance.', 70, 102),
    ('FSA-S5-3', 'FSA-5.3.1', 'Setiap kerusakan memiliki Work Order.', 10, 103),
    ('FSA-S5-3', 'FSA-5.3.2', 'Setiap finding memiliki PIC.', 20, 104),
    ('FSA-S5-3', 'FSA-5.3.3', 'Setiap repair memiliki target completion.', 30, 105),
    ('FSA-S5-3', 'FSA-5.3.4', 'Outstanding repair dimonitor sampai selesai.', 40, 106),
    ('FSA-S5-3', 'FSA-5.3.5', 'Critical breakdown mendapatkan immediate action.', 50, 107),
    ('FSA-S5-3', 'FSA-5.3.6', 'Repeat breakdown dilakukan analisis.', 60, 108),
    ('FSA-S5-3', 'FSA-5.3.7', 'Corrective action dilakukan untuk mencegah kerusakan berulang.', 70, 109),
    ('FSA-S5-4', 'FSA-5.4.1', 'Maintenance history tersedia.', 10, 110),
    ('FSA-S5-4', 'FSA-5.4.2', 'Vendor/service report terdokumentasi.', 20, 111),
    ('FSA-S5-4', 'FSA-5.4.3', 'Warranty information terdokumentasi.', 30, 112),
    ('FSA-S5-4', 'FSA-5.4.4', 'Equipment manual tersedia.', 40, 113),
    ('FSA-S5-4', 'FSA-5.4.5', 'Calibration record tersedia untuk equipment yang membutuhkan.', 50, 114),
    ('FSA-S5-4', 'FSA-5.4.6', 'Breakdown frequency dimonitor.', 60, 115),
    ('FSA-S5-4', 'FSA-5.4.7', 'Equipment downtime dimonitor.', 70, 116),
    ('FSA-S5-4', 'FSA-5.4.8', 'Maintenance cost dimonitor.', 80, 117),
    ('FSA-S5-4', 'FSA-5.4.9', 'PM effectiveness dievaluasi secara berkala.', 90, 118);

-- The DDL above is intentionally separate from this transaction.
START TRANSACTION;

UPDATE qa2_templates
SET name = 'FORM AUDIT KELAYAKAN BANGUNAN, KESELAMATAN KERJA & PERALATAN',
    audit_type = 'Facility Evaluation',
    department = 'Engineering',
    scoring_mode = 'c_mn_my',
    status = 'A',
    notes = 'Seed checklist dari form audit kelayakan bangunan, keselamatan kerja, dan peralatan.',
    updated_at = NOW()
WHERE code = 'FSA-BKP' AND version = 1;

INSERT INTO qa2_templates
    (code, name, audit_type, department, version, scoring_mode, status, notes, created_at, updated_at)
SELECT
    'FSA-BKP',
    'FORM AUDIT KELAYAKAN BANGUNAN, KESELAMATAN KERJA & PERALATAN',
    'Facility Evaluation',
    'Engineering',
    1,
    'c_mn_my',
    'A',
    'Seed checklist dari form audit kelayakan bangunan, keselamatan kerja, dan peralatan.',
    NOW(),
    NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM qa2_templates WHERE code = 'FSA-BKP' AND version = 1
);

UPDATE qa2_categories c
JOIN tmp_fsa_categories x ON x.code = c.code
SET c.name = x.name, c.status = 'A', c.updated_at = NOW();

INSERT INTO qa2_categories (code, name, status, created_at, updated_at)
SELECT x.code, x.name, 'A', NOW(), NOW()
FROM tmp_fsa_categories x
WHERE NOT EXISTS (SELECT 1 FROM qa2_categories c WHERE c.code = x.code);

UPDATE qa2_subcategories s
JOIN tmp_fsa_subcategories x ON x.code = s.code
JOIN qa2_categories c ON c.code = x.category_code
SET s.category_id = c.id,
    s.name = x.name,
    s.sort_order = x.sort_order,
    s.status = 'A',
    s.updated_at = NOW();

INSERT INTO qa2_subcategories (category_id, code, name, sort_order, status, created_at, updated_at)
SELECT c.id, x.code, x.name, x.sort_order, 'A', NOW(), NOW()
FROM tmp_fsa_subcategories x
JOIN qa2_categories c ON c.code = x.category_code
WHERE NOT EXISTS (SELECT 1 FROM qa2_subcategories s WHERE s.code = x.code);

UPDATE qa2_parameters p
JOIN tmp_fsa_parameters x ON x.code = p.code
JOIN qa2_subcategories s ON s.code = x.subcategory_code
SET p.subcategory_id = s.id,
    p.parameter_text = x.parameter_text,
    p.weight = 0,
    p.sort_order = x.sort_order,
    p.status = 'A',
    p.updated_at = NOW();

INSERT INTO qa2_parameters (subcategory_id, code, parameter_text, weight, sort_order, status, created_at, updated_at)
SELECT s.id, x.code, x.parameter_text, 0, x.sort_order, 'A', NOW(), NOW()
FROM tmp_fsa_parameters x
JOIN qa2_subcategories s ON s.code = x.subcategory_code
WHERE NOT EXISTS (SELECT 1 FROM qa2_parameters p WHERE p.code = x.code);

SET @fsa_template_id := (
    SELECT id FROM qa2_templates WHERE code = 'FSA-BKP' AND version = 1 LIMIT 1
);

-- Replace only FSA parameter links in this one template; other templates/items remain untouched.
DELETE ti
FROM qa2_template_items ti
JOIN qa2_parameters p ON p.id = ti.parameter_id
WHERE ti.template_id = @fsa_template_id
  AND p.code LIKE 'FSA-%';

INSERT INTO qa2_template_items
    (template_id, parameter_id, sort_order, is_required, created_at, updated_at)
SELECT @fsa_template_id, p.id, x.template_sort, 1, NOW(), NOW()
FROM tmp_fsa_parameters x
JOIN qa2_parameters p ON p.code = x.code
ORDER BY x.template_sort;

-- Verify counts before committing. Expected: 5, 10, 118, 118.
SELECT
    (SELECT COUNT(*) FROM qa2_categories WHERE code LIKE 'FSA-C%') AS category_count,
    (SELECT COUNT(*) FROM qa2_subcategories WHERE code LIKE 'FSA-S%') AS subcategory_count,
    (SELECT COUNT(*) FROM qa2_parameters WHERE code LIKE 'FSA-%') AS parameter_count,
    (SELECT COUNT(*) FROM qa2_template_items WHERE template_id = @fsa_template_id) AS template_item_count,
    (SELECT scoring_mode FROM qa2_templates WHERE id = @fsa_template_id) AS scoring_mode;

-- Persist the seed data after the verification query is returned.
COMMIT;

DROP TEMPORARY TABLE IF EXISTS tmp_fsa_parameters;
DROP TEMPORARY TABLE IF EXISTS tmp_fsa_subcategories;
DROP TEMPORARY TABLE IF EXISTS tmp_fsa_categories;