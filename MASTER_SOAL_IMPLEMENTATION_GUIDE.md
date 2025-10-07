# Master Data Soal - Implementation Guide

## Overview
Fitur Master Data Soal adalah sistem untuk mengelola soal-soal yang akan digunakan dalam sistem testing. Fitur ini mendukung berbagai tipe soal dengan kemampuan kalkulasi otomatis skor.

## Fitur Utama

### 1. Tipe Soal yang Didukung
- **Essay**: Soal uraian yang memerlukan penilaian manual
- **Pilihan Ganda**: Soal dengan 4 pilihan (A, B, C, D) dengan jawaban benar yang dapat dikalkulasi otomatis
- **Ya/Tidak**: Soal dengan 2 pilihan (Ya/Tidak) dengan jawaban benar yang dapat dikalkulasi otomatis

### 2. Fitur Soal
- **Judul Soal**: Nama/identitas soal
- **Kategori**: Pengelompokan soal berdasarkan mata pelajaran/topik
- **Pertanyaan**: Teks pertanyaan
- **Waktu Pengerjaan**: Durasi dalam detik (1-3600 detik)
- **Skor**: Nilai yang akan diberikan untuk soal ini
- **Status**: Aktif/Tidak Aktif
- **Jawaban Benar**: Untuk pilihan ganda dan ya/tidak (kalkulasi otomatis)

## File yang Dibuat

### 1. Database
- `create_master_soal_tables.sql` - Query SQL untuk membuat tabel dan data contoh

### 2. Backend (Laravel)
- `app/Models/KategoriSoal.php` - Model untuk kategori soal
- `app/Models/MasterSoal.php` - Model untuk master soal
- `app/Http/Controllers/MasterSoalController.php` - Controller untuk CRUD master soal
- `routes/web.php` - Routes untuk master soal (sudah ditambahkan)

### 3. Frontend (Vue.js + Inertia)
- `resources/js/Pages/MasterSoal/Index.vue` - Halaman daftar soal
- `resources/js/Pages/MasterSoal/Create.vue` - Form tambah soal
- `resources/js/Pages/MasterSoal/Edit.vue` - Form edit soal
- `resources/js/Pages/MasterSoal/Show.vue` - Detail soal

## Struktur Database

### Tabel `kategori_soal`
```sql
- id (BIGINT, PRIMARY KEY)
- nama_kategori (VARCHAR(100))
- deskripsi (TEXT)
- status (ENUM: active, inactive)
- created_at, updated_at (TIMESTAMP)
```

### Tabel `master_soal`
```sql
- id (BIGINT, PRIMARY KEY)
- judul (VARCHAR(255))
- tipe_soal (ENUM: essay, pilihan_ganda, yes_no)
- kategori_id (BIGINT, FK ke kategori_soal)
- pertanyaan (TEXT)
- waktu_detik (INT, 1-3600)
- jawaban_benar (VARCHAR(255), untuk pilihan ganda dan yes/no)
- pilihan_a, pilihan_b, pilihan_c, pilihan_d (VARCHAR(500), untuk pilihan ganda)
- skor (DECIMAL(5,2), 0.01-100)
- status (ENUM: active, inactive)
- created_by, updated_by (BIGINT, FK ke users)
- created_at, updated_at (TIMESTAMP)
```

## Cara Implementasi

### Step 1: Setup Database
Jalankan query SQL untuk membuat tabel:
```bash
# Menggunakan MySQL command line
mysql -u username -p database_name < create_master_soal_tables.sql

# Atau copy-paste ke phpMyAdmin
```

### Step 2: Verifikasi File
Pastikan semua file telah dibuat di lokasi yang benar:
- Models: `app/Models/`
- Controller: `app/Http/Controllers/`
- Views: `resources/js/Pages/MasterSoal/`
- Routes: Sudah ditambahkan ke `routes/web.php`

### Step 3: Test Fitur
1. Akses `/master-soal` untuk melihat daftar soal
2. Klik "Tambah Soal" untuk membuat soal baru
3. Test berbagai tipe soal (Essay, Pilihan Ganda, Ya/Tidak)
4. Test fitur edit dan hapus

## Fitur CRUD

### 1. Create (Tambah Soal)
- Form dinamis berdasarkan tipe soal
- Validasi khusus untuk setiap tipe
- Auto-reset pilihan ketika tipe berubah

### 2. Read (Daftar Soal)
- Filter berdasarkan: pencarian, tipe soal, kategori, status
- Pagination dengan opsi per halaman
- Sorting berdasarkan tanggal dibuat

### 3. Update (Edit Soal)
- Form pre-filled dengan data existing
- Validasi yang sama dengan create
- Preserve data yang tidak berubah

### 4. Delete (Hapus Soal)
- Konfirmasi sebelum hapus
- Soft delete (opsional)

### 5. Toggle Status
- Aktifkan/nonaktifkan soal
- Konfirmasi sebelum perubahan

## Validasi

### Umum
- Judul: required, max 255 karakter
- Tipe soal: required, enum
- Pertanyaan: required
- Waktu: required, integer, 1-3600 detik
- Skor: required, decimal, 0.01-100
- Status: required, enum

### Khusus Pilihan Ganda
- Jawaban benar: required, enum A/B/C/D
- Pilihan A-D: required, max 500 karakter

### Khusus Ya/Tidak
- Jawaban benar: required, enum yes/no

## API Endpoints

```
GET    /master-soal              - Daftar soal
GET    /master-soal/create      - Form tambah soal
POST   /master-soal             - Simpan soal baru
GET    /master-soal/{id}        - Detail soal
GET    /master-soal/{id}/edit   - Form edit soal
PUT    /master-soal/{id}        - Update soal
DELETE /master-soal/{id}        - Hapus soal
PATCH  /master-soal/{id}/toggle-status - Toggle status
```

## Fitur Tambahan

### 1. Kalkulasi Otomatis Skor
- Untuk pilihan ganda dan ya/tidak
- Jawaban benar disimpan untuk kalkulasi
- Essay memerlukan penilaian manual

### 2. Manajemen Waktu
- Durasi dalam detik
- Konversi otomatis ke menit/detik
- Validasi 1-3600 detik

### 3. Kategori Soal
- Pengelompokan berdasarkan mata pelajaran
- Dropdown untuk pemilihan kategori
- Optional (bisa kosong)

### 4. Status Management
- Aktif: Soal dapat digunakan dalam test
- Tidak Aktif: Soal tidak dapat digunakan
- Toggle status dengan konfirmasi

## Troubleshooting

### 1. Error "Class not found"
- Pastikan model dan controller sudah dibuat
- Jalankan `composer dump-autoload`

### 2. Error "Route not found"
- Pastikan routes sudah ditambahkan ke `routes/web.php`
- Jalankan `php artisan route:clear`

### 3. Error "View not found"
- Pastikan file Vue sudah dibuat di `resources/js/Pages/MasterSoal/`
- Jalankan `npm run build` atau `npm run dev`

### 4. Database Error
- Pastikan tabel sudah dibuat
- Cek foreign key constraints
- Pastikan user memiliki permission yang cukup

## Pengembangan Selanjutnya

### 1. Fitur Testing
- Integrasi dengan sistem test
- Randomisasi soal
- Timer otomatis

### 2. Analytics
- Statistik penggunaan soal
- Tingkat kesulitan soal
- Performance analytics

### 3. Import/Export
- Import soal dari Excel
- Export soal ke PDF
- Template import

### 4. Advanced Features
- Soal dengan gambar
- Soal dengan audio
- Soal dengan video
- Multiple choice dengan multiple answers

## Support

Jika mengalami masalah dengan implementasi, periksa:
1. Log error di `storage/logs/laravel.log`
2. Browser console untuk error JavaScript
3. Network tab untuk error API
4. Database connection dan permissions

## Changelog

### v1.0.0 (Initial Release)
- ✅ CRUD Master Soal
- ✅ 3 Tipe Soal (Essay, Pilihan Ganda, Ya/Tidak)
- ✅ Kategori Soal
- ✅ Validasi Form
- ✅ Status Management
- ✅ Kalkulasi Otomatis Skor
- ✅ Responsive Design
- ✅ SweetAlert2 Integration
