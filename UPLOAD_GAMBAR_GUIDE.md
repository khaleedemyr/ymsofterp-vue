# Upload Gambar Guide - Master Soal

## 🎯 **Fitur Upload Gambar yang Tersedia**

### ✅ **1. Upload Gambar Pertanyaan (Optional)**
- **Lokasi**: Setelah field "Pertanyaan"
- **Fitur**: Multiple images upload
- **Button**: "Upload Gambar Pertanyaan" (hijau)
- **Preview**: Grid layout dengan tombol hapus
- **Status**: Optional (tidak wajib)

### ✅ **2. Upload Gambar Pilihan Jawaban (Optional)**
- **Lokasi**: Di setiap pilihan A, B, C, D
- **Fitur**: Single image per pilihan
- **Button**: "Upload Gambar (Optional)" (biru)
- **Preview**: Gambar kecil dengan tombol hapus
- **Status**: Optional (tidak wajib)

## 🎨 **UI Components yang Sudah Diupdate**

### **Upload Gambar Pertanyaan**
```html
<!-- Section dengan background abu-abu -->
<div class="border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50">
  <button class="bg-green-500 text-white px-4 py-2 rounded-md">
    <i class="fa-solid fa-upload"></i>
    Upload Gambar Pertanyaan
  </button>
  <p class="text-sm text-gray-500">
    <i class="fa-solid fa-info-circle mr-1"></i>
    Optional - Bisa upload multiple images untuk pertanyaan
  </p>
</div>
```

### **Upload Gambar Pilihan**
```html
<!-- Button kecil untuk setiap pilihan -->
<button class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded">
  <i class="fa-solid fa-image"></i>
  Upload Gambar (Optional)
</button>
```

## 🔧 **Cara Menggunakan**

### **1. Upload Gambar Pertanyaan**
1. Masukkan pertanyaan di textarea
2. Scroll ke bawah, akan ada section "Gambar Pertanyaan (Optional)"
3. Klik tombol hijau "Upload Gambar Pertanyaan"
4. Pilih multiple images (bisa pilih banyak sekaligus)
5. Preview akan muncul dalam grid
6. Bisa hapus gambar dengan tombol × merah

### **2. Upload Gambar Pilihan**
1. Pilih tipe soal "Pilihan Ganda"
2. Masukkan pilihan A, B, C, D
3. Di setiap pilihan ada tombol biru "Upload Gambar (Optional)"
4. Klik tombol untuk upload gambar per pilihan
5. Preview gambar kecil akan muncul
6. Bisa hapus dengan tombol "Hapus"

## 📋 **URL yang Benar**

Pastikan Anda mengakses URL yang benar:
- ✅ **URL Baru**: `/master-soal-new/create` (dengan fitur upload gambar)
- ❌ **URL Lama**: `/master-soal/create` (tanpa fitur upload gambar)

## 🎯 **Fitur yang Tersedia**

### **Upload Gambar Pertanyaan**
- ✅ Multiple images upload
- ✅ Preview dalam grid layout
- ✅ Tombol hapus individual
- ✅ Background abu-abu untuk highlight
- ✅ Icon dan label yang jelas

### **Upload Gambar Pilihan**
- ✅ Single image per pilihan
- ✅ Preview gambar kecil
- ✅ Tombol hapus per pilihan
- ✅ Button style yang konsisten
- ✅ Label "Optional" yang jelas

### **Skor Conditional**
- ✅ **Essay**: Tidak ada input skor (info box muncul)
- ✅ **Pilihan Ganda**: Input skor required
- ✅ **Ya/Tidak**: Input skor required

## 🚀 **Cara Test Fitur**

### **Step 1: Akses URL yang Benar**
```
http://localhost:8000/master-soal-new/create
```

### **Step 2: Tambah Pertanyaan**
1. Klik "Tambah Pertanyaan"
2. Pilih tipe soal
3. Masukkan pertanyaan
4. Scroll ke bawah untuk lihat section upload gambar

### **Step 3: Upload Gambar Pertanyaan**
1. Klik tombol hijau "Upload Gambar Pertanyaan"
2. Pilih multiple images
3. Lihat preview dalam grid
4. Test tombol hapus

### **Step 4: Upload Gambar Pilihan (jika Pilihan Ganda)**
1. Pilih tipe "Pilihan Ganda"
2. Masukkan pilihan A, B, C, D
3. Klik tombol biru "Upload Gambar (Optional)" di setiap pilihan
4. Lihat preview gambar kecil
5. Test tombol hapus

## 🎉 **Hasil yang Diharapkan**

### **Form yang Terlihat**
- ✅ Section "Gambar Pertanyaan (Optional)" dengan background abu-abu
- ✅ Button hijau "Upload Gambar Pertanyaan"
- ✅ Button biru "Upload Gambar (Optional)" di setiap pilihan
- ✅ Preview gambar yang jelas
- ✅ Tombol hapus yang mudah digunakan

### **Fungsi yang Bekerja**
- ✅ Upload multiple images untuk pertanyaan
- ✅ Upload single image untuk pilihan
- ✅ Preview gambar langsung
- ✅ Hapus gambar individual
- ✅ Skor conditional (essay tidak perlu skor)

## 🔍 **Troubleshooting**

### **Jika Tidak Ada Upload Gambar**
1. Pastikan akses `/master-soal-new/create` (bukan `/master-soal/create`)
2. Pastikan sudah klik "Tambah Pertanyaan" dulu
3. Scroll ke bawah untuk lihat section upload gambar
4. Pastikan browser support JavaScript

### **Jika Upload Tidak Bekerja**
1. Pastikan file yang dipilih adalah gambar (JPG, PNG, GIF, WebP)
2. Pastikan ukuran file tidak terlalu besar
3. Cek console browser untuk error JavaScript

**Fitur upload gambar sudah siap dan optional!** 🎉
