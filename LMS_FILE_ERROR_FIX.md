# LMS File Error Fix Documentation

## **Masalah yang Ditemukan:**
Error `League\Flysystem\UnableToRetrieveMetadata` terjadi saat user klik detail course. Error ini menunjukkan bahwa sistem tidak bisa mengambil metadata file (seperti ukuran file) untuk beberapa file yang tersimpan.

## **Penyebab Error:**
1. **File tidak ditemukan** di storage yang diharapkan
2. **Path file tidak valid** atau file sudah dihapus
3. **Permission issue** pada storage
4. **File paths yang tidak valid** tersimpan di database

## **Solusi yang Telah Diimplementasikan:**

### 1. **Enhanced File Validation di LmsController**
- ✅ **Method `validateFilePaths()`** - Memvalidasi dan membersihkan file paths
- ✅ **Method `getSafeFileUrl()`** - Mendapatkan URL file yang aman
- ✅ **Method `getFileSize()`** - Mendapatkan ukuran file dengan error handling
- ✅ **Method `formatFileSize()`** - Format ukuran file yang user-friendly

### 2. **Improved showCourse Method**
- ✅ **File existence check** sebelum mencoba mengakses file
- ✅ **Safe file URL generation** dengan fallback
- ✅ **Error logging** untuk debugging
- ✅ **Graceful degradation** jika file tidak ditemukan

### 3. **File Cleanup System**
- ✅ **Method `cleanupInvalidFiles()`** - Membersihkan file references yang tidak valid
- ✅ **Route cleanup** - `/lms/cleanup-files` untuk menjalankan cleanup
- ✅ **Script cleanup** - `cleanup_lms_files.php` untuk manual cleanup

## **Cara Mengatasi Error yang Masih Terjadi:**

### **Langkah 1: Jalankan File Cleanup**
```bash
# Jalankan script cleanup
php cleanup_lms_files.php

# Atau melalui web route (perlu login admin)
POST /lms/cleanup-files
```

### **Langkah 2: Periksa Storage Permissions**
```bash
# Pastikan storage folder memiliki permission yang benar
chmod -R 755 storage/
chmod -R 755 public/storage/

# Pastikan symbolic link sudah dibuat
php artisan storage:link
```

### **Langkah 3: Periksa File Storage**
```bash
# Periksa apakah file ada di storage
ls -la storage/app/public/lms/materials/

# Periksa symbolic link
ls -la public/storage/
```

### **Langkah 4: Debug File Paths**
Tambahkan logging ini di `showCourse` method untuk debugging:

```php
// Tambahkan di awal showCourse method
\Log::info('=== SHOW COURSE DEBUG ===');
\Log::info('Course ID:', ['course_id' => $course->id]);
\Log::info('Storage path:', ['storage_path' => storage_path('app/public')]);
\Log::info('Public storage path:', ['public_storage' => public_path('storage')]);
```

## **Struktur Data yang Diharapkan:**

### **Database Structure:**
```sql
-- File paths disimpan sebagai JSON array
file_path: ["lms/materials/file1.pdf", "lms/materials/file2.jpg"]
file_type: ["pdf", "image"]
```

### **Processed Data Structure:**
```php
$material->processed_files = [
    [
        'path' => 'lms/materials/file1.pdf',
        'type' => 'pdf',
        'url' => 'http://localhost:8000/storage/lms/materials/file1.pdf',
        'exists' => true,
        'size' => '2.5 MB'
    ]
];

$material->file_errors = [
    'File not found or not readable: lms/materials/missing_file.pdf'
];
```

## **Troubleshooting Guide:**

### **Error: "File not found"**
**Solusi:**
1. Periksa apakah file ada di `storage/app/public/lms/materials/`
2. Jalankan `php artisan storage:link`
3. Periksa permission folder storage

### **Error: "Permission denied"**
**Solusi:**
```bash
# Set permission yang benar
sudo chown -R www-data:www-data storage/
sudo chmod -R 755 storage/
```

### **Error: "Symbolic link failed"**
**Solusi:**
```bash
# Hapus symbolic link yang ada
rm public/storage

# Buat ulang symbolic link
php artisan storage:link
```

## **Testing the Fix:**

### **1. Test File Upload**
- Upload beberapa file material
- Pastikan file tersimpan di storage
- Periksa database untuk file paths

### **2. Test Course Detail**
- Klik detail course yang memiliki material files
- Periksa apakah error masih muncul
- Periksa log untuk debugging info

### **3. Test File Cleanup**
- Jalankan cleanup script
- Periksa hasil cleanup
- Test course detail lagi

## **Monitoring dan Maintenance:**

### **Regular Cleanup Schedule**
```bash
# Tambahkan ke cron job untuk cleanup otomatis
0 2 * * * cd /path/to/project && php cleanup_lms_files.php >> storage/logs/cleanup.log
```

### **Log Monitoring**
```bash
# Monitor log untuk file errors
tail -f storage/logs/laravel.log | grep "File validation failed"
```

## **Fallback Strategy:**

Jika file tidak ditemukan:
1. **Show warning message** ke user
2. **Log error** untuk admin
3. **Continue loading** course tanpa file yang bermasalah
4. **Provide download link** jika file tersedia

## **Performance Considerations:**

- **File validation** hanya dilakukan saat `showCourse`
- **Caching** bisa ditambahkan untuk file metadata
- **Batch processing** untuk cleanup operations
- **Async cleanup** untuk large datasets

## **Security Notes:**

- **File type validation** untuk mencegah upload file berbahaya
- **Path sanitization** untuk mencegah directory traversal
- **Access control** untuk file downloads
- **Logging** untuk audit trail

---

## **Quick Fix Commands:**

```bash
# 1. Jalankan cleanup
php cleanup_lms_files.php

# 2. Recreate storage link
php artisan storage:link

# 3. Clear cache
php artisan cache:clear
php artisan config:clear

# 4. Check storage permissions
chmod -R 755 storage/
chmod -R 755 public/storage/
```

## **Support:**

Jika masih ada error setelah menjalankan semua langkah di atas:
1. Periksa log file di `storage/logs/laravel.log`
2. Jalankan cleanup script dan periksa output
3. Periksa storage folder structure
4. Test dengan file baru untuk memastikan upload berfungsi
