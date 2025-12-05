# Upload Gambar Features - Master Soal

## 🎯 **Fitur yang Ditambahkan**

### 1. **Upload Gambar Pertanyaan (Multiple Images)**
- Bisa upload multiple images untuk 1 pertanyaan
- Preview gambar dengan grid layout
- Bisa hapus gambar individual
- Support format: JPG, PNG, GIF, WebP

### 2. **Upload Gambar Pilihan Jawaban**
- Setiap pilihan (A, B, C, D) bisa punya gambar
- Preview gambar kecil (20x20)
- Bisa hapus gambar pilihan
- Support format: JPG, PNG, GIF, WebP

### 3. **Skor Conditional**
- **Essay**: Tidak ada input skor (manual oleh penilai)
- **Pilihan Ganda**: Ada input skor
- **Ya/Tidak**: Ada input skor

## 📊 **Struktur Database yang Diupdate**

### **Tabel `soal_pertanyaan`**
```sql
-- Field baru untuk gambar
pertanyaan_gambar JSON NULL COMMENT 'Array URL gambar untuk pertanyaan (multiple images)',
pilihan_a_gambar VARCHAR(500) NULL COMMENT 'Gambar untuk pilihan A',
pilihan_b_gambar VARCHAR(500) NULL COMMENT 'Gambar untuk pilihan B',
pilihan_c_gambar VARCHAR(500) NULL COMMENT 'Gambar untuk pilihan C',
pilihan_d_gambar VARCHAR(500) NULL COMMENT 'Gambar untuk pilihan D',
```

## 🎨 **UI Components yang Ditambahkan**

### **Upload Gambar Pertanyaan**
```html
<!-- Multiple Images Upload -->
<div class="border-2 border-dashed border-gray-300 rounded-lg p-4">
  <input type="file" multiple accept="image/*" />
  <button>Upload Gambar</button>
  
  <!-- Preview Grid -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
    <div v-for="image in pertanyaan_gambar">
      <img :src="image" class="w-full h-20 object-cover" />
      <button @click="removeImage">×</button>
    </div>
  </div>
</div>
```

### **Upload Gambar Pilihan**
```html
<!-- Single Image Upload per Pilihan -->
<div class="mt-2">
  <input type="file" accept="image/*" />
  <button>Upload Gambar</button>
  
  <!-- Preview -->
  <div v-if="pilihan_gambar">
    <img :src="pilihan_gambar" class="w-20 h-20 object-cover" />
    <button @click="removeImage">🗑️</button>
  </div>
</div>
```

### **Skor Conditional**
```html
<!-- Skor hanya untuk pilihan ganda dan yes/no -->
<div v-if="tipe_soal !== 'essay'">
  <label>Skor *</label>
  <input type="number" v-model="skor" />
</div>

<!-- Info untuk Essay -->
<div v-if="tipe_soal === 'essay'" class="bg-blue-50">
  <p>Untuk soal essay, skor akan diinput manual oleh penilai.</p>
</div>
```

## 🔧 **JavaScript Functions**

### **Upload Functions**
```javascript
// Handle multiple images untuk pertanyaan
const handlePertanyaanImages = (event, index) => {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => {
        pertanyaan.pertanyaan_gambar.push(e.target.result);
      };
      reader.readAsDataURL(file);
    }
  });
};

// Handle single image untuk pilihan
const handlePilihanImage = (event, index, pilihan) => {
  const file = event.target.files[0];
  if (file && file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (e) => {
      pertanyaan[`pilihan_${pilihan}_gambar`] = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};
```

### **Remove Functions**
```javascript
// Remove gambar pertanyaan
const removePertanyaanImage = (index, imgIndex) => {
  form.pertanyaans[index].pertanyaan_gambar.splice(imgIndex, 1);
};

// Remove gambar pilihan
const removePilihanImage = (index, pilihan) => {
  form.pertanyaans[index][`pilihan_${pilihan}_gambar`] = '';
};
```

## 📁 **File yang Sudah Diupdate**

### Database
- ✅ `create_master_soal_tables_fixed.sql` - Field gambar ditambahkan

### Models
- ✅ `app/Models/SoalPertanyaan.php` - Fillable dan casts diupdate

### Controller
- ✅ `app/Http/Controllers/MasterSoalNewController.php` - Validasi skor diupdate

### Views
- ✅ `resources/js/Pages/MasterSoalNew/Create.vue` - Form dengan upload gambar

## 🎯 **Fitur yang Tersedia**

### **1. Pertanyaan dengan Multiple Images**
- Upload multiple images untuk 1 pertanyaan
- Preview dalam grid layout
- Bisa hapus gambar individual
- Drag & drop support (future enhancement)

### **2. Pilihan Jawaban dengan Gambar**
- Setiap pilihan bisa punya gambar
- Preview gambar kecil
- Bisa hapus gambar pilihan
- Text + gambar kombinasi

### **3. Skor Conditional**
- **Essay**: Tidak ada input skor (info box muncul)
- **Pilihan Ganda**: Input skor required
- **Ya/Tidak**: Input skor required

## 🚀 **Cara Penggunaan**

### **1. Upload Gambar Pertanyaan**
1. Pilih tipe soal
2. Masukkan pertanyaan
3. Klik "Upload Gambar" untuk pertanyaan
4. Pilih multiple images
5. Preview akan muncul dalam grid
6. Bisa hapus gambar dengan tombol ×

### **2. Upload Gambar Pilihan**
1. Pilih tipe "Pilihan Ganda"
2. Masukkan pilihan A, B, C, D
3. Klik "Upload Gambar" untuk setiap pilihan
4. Preview gambar kecil akan muncul
5. Bisa hapus dengan tombol 🗑️

### **3. Skor Management**
1. **Essay**: Tidak perlu input skor (otomatis info box)
2. **Pilihan Ganda**: Input skor required
3. **Ya/Tidak**: Input skor required

## 🎉 **Keunggulan Fitur**

1. **Multiple Images**: 1 pertanyaan bisa punya banyak gambar
2. **Flexible**: Pilihan bisa text saja, gambar saja, atau kombinasi
3. **User Friendly**: Preview gambar langsung
4. **Conditional Logic**: Skor hanya muncul jika diperlukan
5. **Clean UI**: Upload area yang jelas dan mudah digunakan

## 📋 **Next Steps**

1. **Backend Upload**: Implementasi upload ke server
2. **Image Optimization**: Compress gambar otomatis
3. **Drag & Drop**: Support drag & drop untuk upload
4. **Image Editor**: Basic crop/resize functionality
5. **Bulk Upload**: Upload multiple images sekaligus

**Fitur upload gambar sudah siap!** 🎉
