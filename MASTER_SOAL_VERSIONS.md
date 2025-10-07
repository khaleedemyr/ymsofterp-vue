# Master Soal - Perbedaan Versi

## 🎯 **Ada 2 Versi Master Soal**

### ❌ **Versi Lama (TANPA Upload Gambar)**
- **URL**: `/master-soal/create`
- **Struktur**: 1 soal = 1 pertanyaan
- **Fitur**: Basic form tanpa upload gambar
- **Controller**: `MasterSoalController.php`
- **Views**: `MasterSoal/Create.vue`, `MasterSoal/Edit.vue`, `MasterSoal/Index.vue`

### ✅ **Versi Baru (DENGAN Upload Gambar)**
- **URL**: `/master-soal-new/create`
- **Struktur**: 1 judul = banyak pertanyaan
- **Fitur**: Upload gambar + card pertanyaan
- **Controller**: `MasterSoalNewController.php`
- **Views**: `MasterSoalNew/Create.vue`, `MasterSoalNew/Edit.vue`, `MasterSoalNew/Index.vue`

## 🔧 **Perbedaan Fitur**

### **Versi Lama (master-soal)**
```
Form sederhana:
- Judul Soal
- Tipe Soal
- Pertanyaan (text saja)
- Waktu Pengerjaan
- Skor
- Status
```

### **Versi Baru (master-soal-new)**
```
Form dengan card pertanyaan:
- Judul Soal
- Deskripsi
- Waktu Total
- Status
- [Card Pertanyaan 1]
  - Tipe Soal
  - Pertanyaan + Upload Gambar (Multiple)
  - Waktu
  - Skor (conditional)
  - Pilihan A,B,C,D + Upload Gambar (Optional)
- [Card Pertanyaan 2]
- [Card Pertanyaan 3]
- ... dst
```

## 🎨 **Fitur Upload Gambar (Hanya di Versi Baru)**

### **1. Upload Gambar Pertanyaan**
- Multiple images per pertanyaan
- Preview dalam grid
- Tombol hapus individual
- Background abu-abu untuk highlight

### **2. Upload Gambar Pilihan**
- Single image per pilihan (A, B, C, D)
- Preview gambar kecil
- Tombol hapus per pilihan
- Label "Optional" yang jelas

### **3. Skor Conditional**
- **Essay**: Tidak ada input skor (info box)
- **Pilihan Ganda**: Input skor required
- **Ya/Tidak**: Input skor required

## 🚀 **Cara Akses Fitur Upload Gambar**

### **Step 1: Akses URL yang Benar**
```
❌ SALAH: http://localhost:8000/master-soal/create
✅ BENAR: http://localhost:8000/master-soal-new/create
```

### **Step 2: Menu Sidebar**
- Menu "Master Soal" sudah diupdate ke `/master-soal-new`
- Klik menu → akan redirect ke versi baru

### **Step 3: Tambah Pertanyaan**
1. Klik "Tambah Soal"
2. Isi judul soal
3. Klik "Tambah Pertanyaan" (button hijau)
4. Pilih tipe soal
5. Masukkan pertanyaan
6. **Scroll ke bawah** → ada section "Gambar Pertanyaan (Optional)"

## 📋 **URL yang Tersedia**

### **Versi Lama (Tanpa Upload Gambar)**
- `/master-soal` - Daftar soal
- `/master-soal/create` - Tambah soal
- `/master-soal/{id}/edit` - Edit soal
- `/master-soal/{id}` - Detail soal

### **Versi Baru (Dengan Upload Gambar)**
- `/master-soal-new` - Daftar judul soal
- `/master-soal-new/create` - Tambah judul soal + pertanyaan
- `/master-soal-new/{id}/edit` - Edit judul soal + pertanyaan
- `/master-soal-new/{id}` - Detail judul soal + pertanyaan

## 🎯 **Rekomendasi**

### **Gunakan Versi Baru** (`/master-soal-new`)
- ✅ Fitur upload gambar
- ✅ 1 judul bisa banyak pertanyaan
- ✅ Card pertanyaan yang user-friendly
- ✅ Skor conditional
- ✅ Multiple images support

### **Hindari Versi Lama** (`/master-soal`)
- ❌ Tidak ada upload gambar
- ❌ 1 soal = 1 pertanyaan (terbatas)
- ❌ Form sederhana tanpa fitur advanced

## 🔍 **Troubleshooting**

### **Jika Tidak Ada Upload Gambar**
1. **Pastikan URL**: `/master-soal-new/create` (bukan `/master-soal/create`)
2. **Pastikan sudah klik**: "Tambah Pertanyaan" dulu
3. **Scroll ke bawah**: Section upload gambar ada di bawah
4. **Pastikan browser**: Support JavaScript

### **Jika Menu Tidak Redirect**
1. **Clear cache**: `php artisan cache:clear`
2. **Restart server**: `php artisan serve`
3. **Hard refresh**: Ctrl+F5 di browser

## 📁 **File yang Sudah Diupdate**

### **Menu Sidebar**
- ✅ `resources/js/Layouts/AppLayout.vue` - Route diupdate ke `/master-soal-new`

### **Versi Baru (Dengan Upload Gambar)**
- ✅ `app/Http/Controllers/MasterSoalNewController.php`
- ✅ `resources/js/Pages/MasterSoalNew/Create.vue`
- ✅ `resources/js/Pages/MasterSoalNew/Edit.vue`
- ✅ `resources/js/Pages/MasterSoalNew/Index.vue`

### **Database**
- ✅ `create_master_soal_tables_fixed.sql` - Struktur dengan field gambar

## 🎉 **Hasil Akhir**

**Menu "Master Soal" sekarang mengarah ke versi baru dengan fitur upload gambar!**

- ✅ Upload gambar pertanyaan (multiple images)
- ✅ Upload gambar pilihan (optional)
- ✅ Skor conditional
- ✅ Card pertanyaan yang user-friendly
- ✅ 1 judul bisa banyak pertanyaan

**Sekarang fitur upload gambar sudah tersedia!** 🎉
