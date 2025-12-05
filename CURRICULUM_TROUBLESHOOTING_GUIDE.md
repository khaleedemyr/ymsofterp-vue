# Curriculum System Troubleshooting Guide

## Overview
Dokumen ini berisi panduan troubleshooting untuk sistem kurikulum LMS yang mengalami error saat input kurikulum.

## Masalah yang Ditemukan

### 1. **Struktur Database Tidak Konsisten**
- Ada inkonsistensi antara struktur database yang diharapkan dan yang digunakan
- Controller menggunakan field `session_number`, `session_title` yang mungkin tidak ada di database
- Model relationships tidak sesuai dengan struktur tabel

### 2. **Error pada Controller**
- Method `storeSession` dan `index` tidak memiliki error handling yang memadai
- Validasi data tidak lengkap
- Logging untuk debugging tidak cukup

### 3. **Frontend Issues**
- Component tidak menangani error dengan baik
- Loading state tidak optimal
- Error messages tidak informatif

## Solusi yang Telah Diterapkan

### 1. **Perbaikan Controller** ✅
- Menambahkan try-catch blocks di semua method
- Meningkatkan logging untuk debugging
- Menambahkan validasi duplikasi session number
- Memperbaiki error handling dan response format

### 2. **Script Database Fix** ✅
- File: `fix_curriculum_database_structure.sql`
- Memastikan struktur tabel yang benar
- Menambahkan foreign key constraints
- Membuat indexes untuk performance

### 3. **Testing Script** ✅
- File: `test_curriculum_system.php`
- Untuk testing dan debugging sistem
- Memeriksa struktur database
- Test API endpoints

## Langkah Troubleshooting

### **Step 1: Jalankan Database Fix Script**
```sql
-- Jalankan script ini di database
source fix_curriculum_database_structure.sql;
```

### **Step 2: Test Sistem dengan PHP Script**
```bash
php test_curriculum_system.php
```

### **Step 3: Periksa Log Laravel**
```bash
tail -f storage/logs/laravel.log
```

### **Step 4: Test API Endpoint**
```bash
# Test dengan curl atau browser
GET /lms/courses/{course_id}/curriculum
```

## Struktur Database yang Benar

### Tabel `lms_curriculum_items`
```sql
- id (Primary Key)
- course_id (Foreign Key ke lms_courses)
- session_number (Nomor sesi)
- session_title (Judul sesi)
- session_description (Deskripsi sesi)
- order_number (Urutan)
- is_required (Wajib/tidak)
- estimated_duration_minutes (Durasi estimasi)
- quiz_id (ID quiz, nullable)
- questionnaire_id (ID kuesioner, nullable)
- status (active/inactive)
- created_by, updated_by (User IDs)
- timestamps (created_at, updated_at, deleted_at)
```

### Tabel `lms_curriculum_materials`
```sql
- id (Primary Key)
- curriculum_item_id (Foreign Key ke lms_curriculum_items)
- title (Judul materi)
- description (Deskripsi materi)
- material_type (pdf, image, video, document, link)
- file_path (Path file)
- external_url (URL eksternal)
- order_number (Urutan)
- estimated_duration_minutes (Durasi estimasi)
- status, timestamps
```

## Common Errors & Solutions

### **Error 1: "Table doesn't exist"**
```sql
-- Jalankan script database fix
source fix_curriculum_database_structure.sql;
```

### **Error 2: "Column doesn't exist"**
```sql
-- Tambahkan kolom yang hilang
ALTER TABLE lms_curriculum_items 
ADD COLUMN IF NOT EXISTS session_number INT NOT NULL DEFAULT 1 AFTER course_id;
```

### **Error 3: "Foreign key constraint fails"**
```sql
-- Periksa data integrity
SELECT * FROM lms_courses WHERE id NOT IN (SELECT DISTINCT course_id FROM lms_curriculum_items);
```

### **Error 4: "Permission denied"**
- Periksa user permissions di database
- Pastikan user memiliki akses ke tabel yang diperlukan

## Testing Checklist

### **Database Level** ✅
- [ ] Tabel `lms_curriculum_items` exists
- [ ] Tabel `lms_curriculum_materials` exists
- [ ] Foreign key constraints valid
- [ ] Indexes created for performance

### **Model Level** ✅
- [ ] `LmsCurriculumItem` model loads
- [ ] `LmsCurriculumMaterial` model loads
- [ ] Relationships defined correctly
- [ ] Fillable fields set properly

### **Controller Level** ✅
- [ ] `LmsCurriculumController` methods work
- [ ] Error handling implemented
- [ ] Logging added for debugging
- [ ] Validation rules correct

### **API Level** ✅
- [ ] Routes defined correctly
- [ ] Endpoints return proper responses
- [ ] Error responses handled
- [ ] Authentication working

## Monitoring & Debugging

### **Log Files**
- `storage/logs/laravel.log` - Laravel application logs
- `storage/logs/curriculum.log` - Curriculum specific logs (if configured)

### **Database Queries**
```sql
-- Monitor curriculum items
SELECT * FROM lms_curriculum_items WHERE course_id = {course_id};

-- Monitor materials
SELECT * FROM lms_curriculum_materials 
WHERE curriculum_item_id IN (
    SELECT id FROM lms_curriculum_items WHERE course_id = {course_id}
);

-- Check for orphaned records
SELECT * FROM lms_curriculum_items 
WHERE course_id NOT IN (SELECT id FROM lms_courses);
```

### **Performance Monitoring**
```sql
-- Check query performance
EXPLAIN SELECT * FROM lms_curriculum_items 
WHERE course_id = {course_id} 
ORDER BY order_number;

-- Check index usage
SHOW INDEX FROM lms_curriculum_items;
```

## Next Steps

### **Immediate Actions**
1. Jalankan database fix script
2. Test dengan PHP script
3. Periksa log files
4. Test API endpoints

### **Long-term Improvements**
1. Implement comprehensive error handling
2. Add unit tests for curriculum system
3. Implement caching for better performance
4. Add monitoring and alerting

### **Documentation Updates**
1. Update API documentation
2. Create user manual for curriculum management
3. Document common troubleshooting steps
4. Create video tutorials

## Support & Contact

Jika masih mengalami masalah setelah mengikuti panduan ini:

1. **Check Logs**: Periksa `storage/logs/laravel.log`
2. **Database Check**: Jalankan `test_curriculum_system.php`
3. **API Test**: Test endpoint dengan Postman/curl
4. **Error Details**: Catat error message lengkap

## File References

- **Controller**: `app/Http/Controllers/LmsCurriculumController.php`
- **Models**: `app/Models/LmsCurriculumItem.php`, `app/Models/LmsCurriculumMaterial.php`
- **Frontend**: `resources/js/Pages/Lms/Courses/Curriculum/Index.vue`
- **Routes**: `routes/web.php` (lines 1258-1278)
- **Database Fix**: `fix_curriculum_database_structure.sql`
- **Test Script**: `test_curriculum_system.php`
