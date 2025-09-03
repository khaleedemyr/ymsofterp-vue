# LMS Jabatan & Level Targeting Documentation

## Overview
Sistem LMS sekarang mendukung target training yang lebih fleksibel dengan menambahkan target berdasarkan **Jabatan** dan **Level** (bukan level kesulitan). Sekarang peserta bisa ditarget berdasarkan kombinasi dari:

1. **Divisi** saja
2. **Jabatan** saja  
3. **Level** saja
4. **Kombinasi** dari 2 atau 3 target tersebut

## Perubahan yang Dilakukan

### 1. Database Changes

#### A. Kolom Baru di Tabel `lms_courses`
```sql
ALTER TABLE `lms_courses` 
ADD COLUMN `target_jabatan_ids` JSON DEFAULT NULL COMMENT 'Array of jabatan IDs that can access this course',
ADD COLUMN `target_level_ids` JSON DEFAULT NULL COMMENT 'Array of level IDs that can access this course';
```

#### B. Junction Tables untuk Many-to-Many Relationship
```sql
-- Tabel untuk relasi course-jabatan
CREATE TABLE `lms_course_jabatans` (
  `course_id` bigint(20) unsigned NOT NULL,
  `jabatan_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`course_id`, `jabatan_id`)
);

-- Tabel untuk relasi course-level
CREATE TABLE `lms_course_levels` (
  `course_id` bigint(20) unsigned NOT NULL,
  `level_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`course_id`, `level_id`)
);
```

### 2. Model Updates

#### A. LmsCourse Model
- Field baru ditambahkan ke `$fillable`: `target_jabatan_ids`, `target_level_ids`
- Field baru ditambahkan ke `$casts` sebagai 'array'
- Relationship baru: `targetJabatans()`, `targetLevels()`
- Accessor baru: `getTargetJabatanNamesAttribute()`, `getTargetLevelNamesAttribute()`

#### B. Relationship Methods
```php
public function targetJabatans()
{
    return $this->belongsToMany(Jabatan::class, 'lms_course_jabatans', 'course_id', 'jabatan_id', 'id', 'id_jabatan');
}

public function targetLevels()
{
    return $this->belongsToMany(DataLevel::class, 'lms_course_levels', 'course_id', 'level_id');
}
```

### 3. Controller Updates

#### A. Courses Method
- Menambahkan data jabatan dan level ke response
- Load jabatan dengan relationship divisi dan level

#### B. StoreCourse Method
- Validation rules untuk field baru
- Logic untuk sync many-to-many relationships
- Filter empty values

### 4. Frontend Updates

#### A. Form Structure
Form create sekarang memiliki 3 section target:
1. **Target Divisi**: Single/Multiple/All divisi
2. **Target Jabatan**: Checkbox multiple jabatan
3. **Target Level**: Checkbox multiple level

#### B. UI Features
- Checkbox dengan warna berbeda untuk setiap target type
- Scrollable area untuk list jabatan/level
- Display divisi info di samping nama jabatan
- Optional targeting (tidak wajib diisi)

#### C. Validation
- Target divisi, jabatan, dan level semuanya optional
- Minimal satu target harus dipilih (divisi, jabatan, atau level)
- Validasi individual untuk setiap target type

### 5. Display Updates

#### A. Course Detail Page
- Menampilkan target jabatan dan level di informasi training
- Conditional display (hanya tampil jika ada data)
- Format: "Jabatan: Manager, Supervisor" atau "Level: Staff, Senior"

#### B. Course List Page
- Target division display tetap seperti sebelumnya
- Bisa ditambahkan display jabatan/level jika diperlukan

## Cara Menjalankan Update

### 1. Jalankan Migration
```sql
-- Jalankan file SQL migration
source database/sql/add_jabatan_level_targets_to_lms_courses.sql
```

### 2. Clear Cache (jika perlu)
```bash
php artisan cache:clear
php artisan config:clear
```

## Target Combination Examples

### Example 1: Divisi + Jabatan
- **Divisi**: IT Department
- **Jabatan**: Manager, Supervisor
- **Level**: (kosong)
- **Hasil**: Course hanya untuk Manager dan Supervisor di IT Department

### Example 2: Level Saja
- **Divisi**: (kosong)
- **Jabatan**: (kosong)
- **Level**: Staff, Senior
- **Hasil**: Course untuk semua Staff dan Senior di semua divisi

### Example 3: Kombinasi Lengkap
- **Divisi**: Finance Department
- **Jabatan**: Manager
- **Level**: Senior
- **Hasil**: Course untuk Manager Senior di Finance Department

## Benefits

1. **Targeting yang Lebih Presisi**: Bisa target berdasarkan jabatan dan level spesifik
2. **Fleksibilitas Tinggi**: Kombinasi berbagai target type
3. **User Experience**: Interface yang intuitif dengan checkbox
4. **Data Integrity**: Many-to-many relationships yang proper
5. **Scalability**: Mudah ditambah target type baru di masa depan

## Notes

- Target divisi, jabatan, dan level semuanya optional
- Jika tidak ada target yang dipilih, course akan tersedia untuk semua
- Many-to-many relationships memastikan data consistency
- UI responsive dan user-friendly
- Validation memastikan minimal satu target dipilih
