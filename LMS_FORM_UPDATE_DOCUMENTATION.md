# LMS Training Form Update Documentation

## Overview
Form create training telah diupdate untuk menyesuaikan dengan field yang ada di halaman detail training. Sebelumnya form create hanya memiliki field dasar, sekarang sudah lengkap dengan field yang sama seperti di detail.

## Perubahan yang Dilakukan

### 1. Field Baru yang Ditambahkan ke Form Create

#### A. Kompetensi yang Akan Dikembangkan (Learning Objectives)
- **Tipe**: Array of strings
- **Validasi**: Minimal 1 kompetensi yang tidak kosong
- **UI**: Dynamic input fields dengan tombol tambah/hapus
- **Default**: 2 field kosong saat form dibuka

#### B. Persyaratan Peserta (Requirements)
- **Tipe**: Array of strings
- **Validasi**: Minimal 1 persyaratan yang tidak kosong
- **UI**: Dynamic input fields dengan tombol tambah/hapus
- **Default**: 2 field kosong saat form dibuka

#### C. Featured Course
- **Tipe**: Boolean checkbox
- **Fungsi**: Menandai course sebagai unggulan
- **Default**: false

#### D. Meta Title & Description
- **Tipe**: String (optional)
- **Fungsi**: Untuk SEO optimization
- **Validasi**: Max 255 chars untuk title, 500 chars untuk description

### 2. Struktur Form yang Diperbaiki

#### A. Pengelompokan Field
Form sekarang dibagi menjadi 4 section:
1. **Informasi Dasar**: Title, Category, Short Description, Full Description
2. **Pengaturan Course**: Target Division, Difficulty Level, Duration
3. **Kompetensi yang Akan Dikembangkan**: Dynamic learning objectives
4. **Persyaratan Peserta**: Dynamic requirements
5. **Pengaturan Tambahan**: Status, Featured, Meta fields

#### B. Layout yang Lebih Baik
- Modal diperbesar dari `max-w-2xl` ke `max-w-4xl`
- Grid layout yang lebih terorganisir
- Section headers dengan icon dan warna yang berbeda

### 3. Database Changes

#### A. Kolom Baru di Tabel `lms_courses`
```sql
ALTER TABLE `lms_courses` 
ADD COLUMN `learning_objectives` JSON DEFAULT NULL,
ADD COLUMN `requirements` JSON DEFAULT NULL;
```

#### B. Model Updates
- Field baru ditambahkan ke `$fillable` array
- Field baru ditambahkan ke `$casts` array sebagai 'array'

#### C. Controller Updates
- Validation rules untuk field baru
- Logic untuk filter empty values
- Fallback ke default values jika data kosong

### 4. Frontend Updates

#### A. Vue Component Changes
- Form data structure diperluas
- Dynamic field management untuk learning objectives dan requirements
- Validation logic yang diperbaiki
- UI/UX yang lebih user-friendly

#### B. Validation Logic
```javascript
// Learning objectives validation - at least one non-empty objective
if (form.value.learning_objectives.length === 0 || form.value.learning_objectives.every(obj => !obj.trim())) {
  return false
}

// Requirements validation - at least one non-empty requirement
if (form.value.requirements.length === 0 || form.value.requirements.every(req => !req.trim())) {
  return false
}
```

### 5. Migration File

File migration baru dibuat: `database/sql/add_learning_objectives_requirements_to_lms_courses.sql`

Isi migration:
- Menambah kolom `learning_objectives` dan `requirements`
- Membuat index untuk performance
- Update existing courses dengan default values

## Cara Menjalankan Update

### 1. Jalankan Migration
```sql
-- Jalankan file SQL migration
source database/sql/add_learning_objectives_requirements_to_lms_courses.sql
```

### 2. Clear Cache (jika perlu)
```bash
php artisan cache:clear
php artisan config:clear
```

## Field Comparison: Create vs Detail

| Field | Create Form | Detail Page | Status |
|-------|-------------|-------------|---------|
| Judul Course | ✅ | ✅ | Match |
| Deskripsi Singkat | ✅ | ✅ | Match |
| Deskripsi Lengkap | ✅ | ✅ | Match |
| Kategori | ✅ | ✅ | Match |
| Target Divisi | ✅ | ✅ | Match |
| Level Kesulitan | ✅ | ✅ | Match |
| Durasi | ✅ | ✅ | Match |
| Status | ✅ | ✅ | Match |
| **Kompetensi** | ✅ | ✅ | **Now Match** |
| **Persyaratan** | ✅ | ✅ | **Now Match** |
| **Featured** | ✅ | ✅ | **Now Match** |
| **Meta Title** | ✅ | ✅ | **Now Match** |
| **Meta Description** | ✅ | ✅ | **Now Match** |
| Kurikulum | ❌ | ✅ | Separate feature |
| Trainer Info | ❌ | ✅ | Auto-generated |
| Sertifikat | ❌ | ✅ | Auto-generated |

## Benefits

1. **Konsistensi Data**: Form create dan detail sekarang memiliki field yang sama
2. **User Experience**: User bisa input semua informasi yang akan ditampilkan di detail
3. **Data Quality**: Validasi memastikan data yang masuk berkualitas
4. **SEO Ready**: Meta fields untuk optimization
5. **Featured Courses**: Kemampuan menandai course unggulan

## Notes

- Field Kurikulum (Lessons) tetap terpisah karena memerlukan interface khusus
- Trainer info otomatis menggunakan user yang sedang login
- Sertifikat akan di-generate otomatis saat course selesai
- Default values diberikan untuk existing courses yang belum memiliki data
