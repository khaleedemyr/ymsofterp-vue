# LMS Target Divisions Guide

## Overview
Sistem LMS sekarang mendukung target training yang fleksibel dengan 3 opsi:
1. **1 Divisi** - Training untuk satu divisi tertentu
2. **Multi Divisi** - Training untuk beberapa divisi yang dipilih
3. **Semua Divisi** - Training untuk seluruh karyawan perusahaan

## Database Changes

### 1. Update Table Structure
Jalankan SQL berikut untuk menambahkan kolom baru:

```sql
-- File: database/sql/update_lms_courses_target_divisions.sql

-- Tambah kolom baru ke tabel lms_courses
ALTER TABLE `lms_courses` 
ADD COLUMN `target_type` ENUM('single', 'multiple', 'all') DEFAULT 'single' COMMENT 'Tipe target: single=1 divisi, multiple=multi divisi, all=semua divisi' AFTER `difficulty_level`,
ADD COLUMN `target_divisions` JSON DEFAULT NULL COMMENT 'Array ID divisi yang ditarget (untuk multiple)' AFTER `target_type`;

-- Buat tabel relasi many-to-many
CREATE TABLE IF NOT EXISTS `lms_course_divisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint(20) unsigned NOT NULL COMMENT 'ID kursus',
  `division_id` bigint(20) unsigned NOT NULL COMMENT 'ID divisi',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_course_divisions_course_division_unique` (`course_id`, `division_id`),
  KEY `lms_course_divisions_course_id_foreign` (`course_id`),
  KEY `lms_course_divisions_division_id_foreign` (`division_id`),
  CONSTRAINT `lms_course_divisions_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `lms_courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lms_course_divisions_division_id_foreign` FOREIGN KEY (`division_id`) REFERENCES `divisi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relasi many-to-many kursus dan divisi';
```

## Model Updates

### LmsCourse Model
Model sudah diupdate dengan:
- Field baru: `target_type`, `target_divisions`
- Relationship: `targetDivisions()` untuk many-to-many
- Methods: `isTargetedForDivision()`, `syncTargetDivisions()`
- Accessors: `getTargetTypeTextAttribute()`, `getTargetDivisionIdsAttribute()`

## Controller Updates

### LmsController
Controller sudah diupdate untuk:
- Handle form submission dengan target division logic
- Filter courses berdasarkan target divisions
- Load target divisions relationship

## Frontend Updates

### Vue Component (Courses.vue)
Component sudah diupdate dengan:
- Form fields untuk target type selection
- Conditional form fields berdasarkan target type
- Display target division info di course cards
- Updated filtering logic

## How to Use

### 1. Create Course with Target Divisions

#### Single Division
```javascript
{
  title: "Training K3 Divisi Produksi",
  target_type: "single",
  target_division_id: 1, // ID divisi produksi
  // ... other fields
}
```

#### Multiple Divisions
```javascript
{
  title: "Training Leadership untuk Manager",
  target_type: "multiple",
  target_divisions: [1, 2, 3], // Array ID divisi
  // ... other fields
}
```

#### All Divisions
```javascript
{
  title: "Training Company Policy",
  target_type: "all",
  // target_division_id dan target_divisions akan null
  // ... other fields
}
```

### 2. Filter Courses by Division
Sistem akan otomatis filter courses berdasarkan divisi user:
- Courses dengan `target_type = 'all'` akan muncul untuk semua user
- Courses dengan `target_type = 'single'` akan muncul jika `target_division_id` sesuai
- Courses dengan `target_type = 'multiple'` akan muncul jika user divisi ada di `target_divisions`

### 3. Display Target Information
Course cards akan menampilkan:
- **Semua Divisi** - Badge biru "Semua Divisi"
- **1 Divisi** - Badge hijau dengan nama divisi
- **Multi Divisi** - Badge ungu dengan jumlah divisi

## Validation Rules

### Backend Validation
```php
'target_type' => 'required|in:single,multiple,all',
'target_division_id' => 'nullable|exists:tbl_data_divisi,id',
'target_divisions' => 'nullable|array',
'target_divisions.*' => 'exists:tbl_data_divisi,id',
```

### Frontend Validation
- Target type wajib dipilih
- Jika single: target_division_id wajib dipilih
- Jika multiple: minimal 1 divisi dipilih
- Jika all: tidak perlu pilih divisi

## Benefits

1. **Fleksibilitas** - Bisa target 1, beberapa, atau semua divisi
2. **Efisiensi** - Training yang relevan untuk divisi tertentu
3. **Scalability** - Mudah menambah/mengurangi target divisi
4. **User Experience** - User hanya lihat training yang relevan
5. **Reporting** - Bisa track enrollment per divisi

## Migration Notes

- Existing courses dengan `target_division_id` akan otomatis set `target_type = 'single'`
- Tabel `lms_course_divisions` akan otomatis sync saat create/update course
- Backward compatibility terjaga untuk existing data 