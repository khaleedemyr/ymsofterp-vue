# Material File Upload Troubleshooting Guide

## **🔍 Masalah yang Ditemukan**

File upload untuk material type tidak tersimpan ke storage dan database.

## **✅ Solusi yang Telah Diterapkan**

### **1. Debug Logging Enhanced**
- ✅ Ditambahkan logging detail untuk material files
- ✅ Ditambahkan error handling untuk file upload
- ✅ Ditambahkan validation untuk file type

### **2. Storage Directory Fixed**
- ✅ Directory `storage/app/public/lms/materials` sudah dibuat
- ✅ Permissions sudah benar (0777)
- ✅ Storage link sudah ada

### **3. Database Structure Fixed**
- ✅ Model `LmsCurriculumMaterial` sudah diupdate
- ✅ Migration SQL sudah disiapkan

## **🧪 Testing yang Telah Dilakukan**

### **Test Script Results**
```bash
=== TEST MATERIAL FILE UPLOAD ===

1. Testing storage directory...
   ✅ Storage directory exists and writable: D:\Gawean\YM\web\ymsofterp\storage\app/public/lms/materials

2. Testing file creation...
   ✅ Test file created: lms/materials/test_material_1756824225.txt
   ✅ Test file exists in storage
   ✅ File URL: http://localhost:8000/storage/lms/materials/test_material_1756824225.txt
   ✅ Test file cleaned up

3. Testing database connection...
   ✅ Database connected. Materials count: 0

4. Testing model creation...
   ✅ Test material created with ID: 1
   ✅ Test material cleaned up

5. Testing storage link...
   ❌ Storage link not found: D:\Gawean\YM\web\ymsofterp\public\storage
   💡 Run: php artisan storage:link

6. Testing file permissions...
   Storage app permissions: 0777
   ✅ Storage app directory is writable
```

## **🚨 Masalah yang Masih Ada**

### **Storage Link Issue**
- Storage link di `public/storage` tidak terdeteksi oleh test script
- Tapi `dir public` menunjukkan `d----l storage` (directory link)
- Kemungkinan ada masalah dengan symlink detection di Windows

## **🔧 Langkah Troubleshooting**

### **1. Cek Storage Link Manual**
```bash
# Cek apakah storage link ada
dir public
# Harusnya ada: d----l storage

# Cek target symlink
dir public\storage
# Harusnya isi sama dengan storage/app/public
```

### **2. Test File Upload dari Frontend**
1. Buka form create course
2. Tambah session dengan material type
3. Upload file PDF/image/video
4. Submit form
5. Cek Laravel logs untuk debug info

### **3. Cek Laravel Logs**
```bash
# Cek logs untuk material upload
tail -f storage/logs/laravel.log

# Cari log dengan keyword:
# - "Processing material files"
# - "Material item found"
# - "File stored successfully"
```

### **4. Test dengan Simple Form**
Gunakan file `test_simple_upload.html` untuk test upload sederhana.

## **📋 Checklist Verification**

### **Frontend (Vue.js)**
- [ ] `material_files` array terisi dengan file objects
- [ ] File objects memiliki property `file` yang valid
- [ ] FormData.append() dipanggil dengan file yang benar
- [ ] Content-Type: multipart/form-data

### **Backend (Laravel)**
- [ ] Request validation berhasil
- [ ] `material_files` array ada di request
- [ ] File instanceof UploadedFile
- [ ] Storage::disk('public')->store() berhasil
- [ ] Database record terupdate dengan file_path

### **Storage**
- [ ] Directory `storage/app/public/lms/materials` exists
- [ ] Directory writable
- [ ] File tersimpan dengan nama yang benar
- [ ] File bisa diakses via URL

## **🐛 Debug Commands**

### **1. Test Storage Directory**
```bash
php test_material_upload.php
```

### **2. Cek Storage Contents**
```bash
# Cek isi storage
dir storage\app\public\lms\materials

# Cek isi public storage
dir public\storage\lms\materials
```

### **3. Cek Laravel Logs**
```bash
# Cek log terbaru
type storage\logs\laravel.log | findstr "material"
```

### **4. Test Database**
```bash
# Cek tabel materials
php artisan tinker
>>> App\Models\LmsCurriculumMaterial::all()
```

## **🔮 Next Steps**

### **1. Test Upload dari Frontend**
- Coba buat course dengan material file dari Vue.js form
- Monitor Laravel logs untuk debug info

### **2. Fix Storage Link Issue**
- Investigate mengapa test script tidak mendeteksi storage link
- Mungkin ada masalah dengan Windows symlink detection

### **3. Verify File Upload Flow**
- Pastikan file dari frontend sampai ke backend
- Pastikan file tersimpan ke storage
- Pastikan database record terupdate

## **📞 Support**

Jika masih ada masalah, cek:
1. Laravel logs untuk error detail
2. Browser console untuk frontend errors
3. Network tab untuk request/response
4. Storage directory permissions
5. Database connection dan structure
