# Perbaikan Penyimpanan Quiz dan Kuesioner di LMS

## 🎯 Masalah yang Ditemukan

Berdasarkan analisis kode dan struktur database, ditemukan masalah pada penyimpanan data material item sesi yang bertipe quiz dan kuesioner:

1. **Data tidak tersimpan** - Quiz dan kuesioner tidak tersimpan ke database
2. **Logika penyimpanan tidak konsisten** - Berbeda dengan tipe material yang bisa tersimpan
3. **Frontend tidak mengirim data lengkap** - `quiz_id` dan `questionnaire_id` tidak dikirim ke backend
4. **Validasi data tidak tepat** - Backend tidak memvalidasi data quiz dan kuesioner dengan benar

## 🔧 Solusi yang Diberikan

### 1. Perbaikan Backend (LmsController.php)

#### **Logika Penyimpanan yang Diperbaiki:**

```php
// Sebelumnya: itemId tidak direset untuk setiap item
$itemId = $itemData['item_id'] ?? null;

// Setelah diperbaiki: itemId direset untuk setiap item
$itemId = null; // Reset itemId for each item
```

#### **Validasi Quiz ID yang Diperbaiki:**

```php
// Sebelumnya: hanya cek if ($quizId)
if ($quizId) {
    // Create quiz material
}

// Setelah diperbaiki: cek lebih detail
if ($quizId && $quizId !== '' && $quizId !== 'null') {
    // Create quiz material
}
```

#### **Validasi Questionnaire ID yang Diperbaiki:**

```php
// Sebelumnya: hanya cek if ($questionnaireId)
if ($questionnaireId) {
    // Create questionnaire material
}

// Setelah diperbaiki: cek lebih detail
if ($questionnaireId && $questionnaireId !== '' && $questionnaireId !== 'null') {
    // Create questionnaire material
}
```

#### **Error Handling yang Diperbaiki:**

```php
// Sebelumnya: tidak ada pengecekan itemId
$sessionItemData['item_id'] = $itemId;

// Setelah diperbaiki: ada pengecekan dan error handling
if ($itemId !== null) {
    $sessionItemData['item_id'] = $itemId;
    // Log success
} else {
    \Log::error('Failed to create curriculum material for item type: ' . $itemData['item_type']);
    continue; // Skip this item if material creation failed
}
```

### 2. Perbaikan Frontend (Courses.vue)

#### **Pengiriman Data Quiz dan Kuesioner:**

```javascript
// Sebelumnya: quiz_id dan questionnaire_id tidak dikirim
formData.append(`sessions[${sessionIndex}][items][${itemIndex}][item_id]`, item.item_id || '')

// Setelah diperbaiki: quiz_id dan questionnaire_id dikirim sesuai tipe
// Handle quiz_id for quiz type items
if (item.item_type === 'quiz' && item.quiz_id) {
  formData.append(`sessions[${sessionIndex}][items][${itemIndex}][quiz_id]`, item.quiz_id)
  console.log(`Added quiz_id for quiz item: ${item.quiz_id}`)
}

// Handle questionnaire_id for questionnaire type items
if (item.item_type === 'questionnaire' && item.questionnaire_id) {
  formData.append(`sessions[${sessionIndex}][items][${itemIndex}][questionnaire_id]`, item.questionnaire_id)
  console.log(`Added questionnaire_id for questionnaire item: ${item.questionnaire_id}`)
}
```

#### **Reset Data yang Diperbaiki:**

```javascript
// Sebelumnya: hanya reset item_id dan material_files
const onItemTypeChange = (sessionIndex, itemIndex) => {
  const item = form.value.sessions[sessionIndex].items[itemIndex]
  
  // Reset item-specific data when type changes
  item.item_id = ''
  item.material_files = []
}

// Setelah diperbaiki: reset semua data terkait
const onItemTypeChange = (sessionIndex, itemIndex) => {
  const item = form.value.sessions[sessionIndex].items[itemIndex]
  
  // Reset item-specific data when type changes
  item.item_id = ''
  item.material_files = []
  item.quiz_id = '' // Reset quiz_id
  item.questionnaire_id = '' // Reset questionnaire_id
}
```

## 📊 Perbedaan Logika Penyimpanan

### **Tipe Material:**
1. Upload file(s) ke storage
2. Buat record di `lms_curriculum_materials`
3. Simpan file info ke `lms_curriculum_material_files`
4. Buat record di `lms_session_items` dengan `item_id` = material ID

### **Tipe Quiz:**
1. Ambil `quiz_id` dari request
2. Buat record di `lms_curriculum_materials` dengan `quiz_id`
3. Buat record di `lms_session_items` dengan `item_id` = material ID

### **Tipe Kuesioner:**
1. Ambil `questionnaire_id` dari request
2. Buat record di `lms_curriculum_materials` dengan `questionnaire_id`
3. Buat record di `lms_session_items` dengan `item_id` = material ID

## 🧪 Testing dan Verifikasi

### **1. Script Test Database:**
```bash
php test_quiz_questionnaire_saving.php
```

Script ini akan:
- Mengecek struktur database
- Test pembuatan quiz material
- Test pembuatan questionnaire material
- Test pembuatan regular material
- Verifikasi data tersimpan dengan benar

### **2. Test Manual di Frontend:**
1. Buat course baru
2. Tambah session
3. Tambah item dengan tipe quiz
4. Pilih quiz yang sudah ada
5. Simpan course
6. Verifikasi data tersimpan di database

### **3. Test Manual di Frontend:**
1. Buat course baru
2. Tambah session
3. Tambah item dengan tipe kuesioner
4. Pilih kuesioner yang sudah ada
5. Simpan course
6. Verifikasi data tersimpan di database

## 📝 Log yang Diharapkan

### **Log Sukses Quiz:**
```
[INFO] Processing quiz item 1: item_data: {...}
[INFO] Quiz ID found: quiz_id: 1, quiz_id_type: string
[INFO] Quiz curriculum material created: material_id: 123, quiz_id: 1
[INFO] Setting session item_id: curriculum_material_id: 123, quiz_id: 1
[INFO] Session item created: item_id: 456, type: quiz, referenced_id: 123
```

### **Log Sukses Kuesioner:**
```
[INFO] Processing questionnaire item 1: item_data: {...}
[INFO] Questionnaire ID found: questionnaire_id: 2, questionnaire_id_type: string
[INFO] Questionnaire curriculum material created: material_id: 124, questionnaire_id: 2
[INFO] Setting session item_id: curriculum_material_id: 124, questionnaire_id: 2
[INFO] Session item created: item_id: 457, type: questionnaire, referenced_id: 124
```

### **Log Error (jika data tidak lengkap):**
```
[WARNING] No quiz_id provided for quiz item 1 - skipping quiz item
[ERROR] Failed to create curriculum material for item type: quiz
[ERROR] Failed to create session item - missing item_id: item_type: quiz
```

## 🚀 Cara Menjalankan Perbaikan

### **1. Jalankan Script Database (Opsional):**
```bash
php fix_quiz_questionnaire_saving.php
```

### **2. Test dengan Script Test:**
```bash
php test_quiz_questionnaire_saving.php
```

### **3. Test Manual di Aplikasi:**
1. Buka aplikasi LMS
2. Buat course baru
3. Test dengan quiz dan kuesioner
4. Verifikasi data tersimpan

## ✅ Checklist Verifikasi

- [ ] Quiz material tersimpan dengan `quiz_id` yang benar
- [ ] Questionnaire material tersimpan dengan `questionnaire_id` yang benar
- [ ] Regular material tersimpan dengan file yang benar
- [ ] Session items terhubung dengan curriculum materials
- [ ] Log menampilkan proses penyimpanan yang benar
- [ ] Tidak ada error saat menyimpan quiz/kuesioner
- [ ] Data dapat diakses kembali setelah disimpan

## 🔍 Troubleshooting

### **Jika Quiz/Kuesioner Masih Tidak Tersimpan:**

1. **Cek Log Laravel:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Cek Database:**
   ```sql
   SELECT * FROM lms_curriculum_materials WHERE quiz_id IS NOT NULL;
   SELECT * FROM lms_curriculum_materials WHERE questionnaire_id IS NOT NULL;
   ```

3. **Cek Frontend Console:**
   - Buka Developer Tools
   - Lihat Console untuk error
   - Verifikasi data yang dikirim

4. **Cek Network Tab:**
   - Buka Developer Tools
   - Lihat Network tab
   - Verifikasi request yang dikirim

### **Jika Ada Error Database:**

1. **Cek Struktur Tabel:**
   ```sql
   DESCRIBE lms_curriculum_materials;
   ```

2. **Cek Foreign Key Constraints:**
   ```sql
   SHOW CREATE TABLE lms_curriculum_materials;
   ```

3. **Cek Data Referensi:**
   ```sql
   SELECT * FROM lms_quizzes LIMIT 5;
   SELECT * FROM lms_questionnaires LIMIT 5;
   ```

## 📞 Support

Jika masih ada masalah setelah menjalankan perbaikan ini:

1. Jalankan script test untuk verifikasi
2. Cek log Laravel untuk error detail
3. Verifikasi struktur database
4. Test dengan data minimal
5. Hubungi tim development untuk bantuan lebih lanjut

---

**Perbaikan ini memastikan bahwa quiz, kuesioner, dan material dapat tersimpan dengan benar di sistem LMS.**
