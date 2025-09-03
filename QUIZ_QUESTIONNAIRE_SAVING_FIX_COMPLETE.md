# 🎯 **SOLUSI LENGKAP: Quiz & Questionnaire Tidak Tersimpan di Course Creation**

## 🔍 **MASALAH YANG DITEMUKAN**

Ketika membuat course baru dengan session yang bertipe quiz atau questionnaire, data tidak tersimpan karena:

1. **Frontend tidak mengirim `quiz_id` atau `questionnaire_id`** - Data ini kosong atau tidak terisi
2. **Backend tidak bisa membuat curriculum material** - Karena `quiz_id`/`questionnaire_id` kosong, `$itemId` menjadi `null`
3. **Session item tidak dibuat** - Karena `item_id` kosong, item tidak tersimpan ke database

## ✅ **STATUS PERBAIKAN**

### **Backend (LmsController.php) - SUDAH BENAR ✅**
- ✅ Validasi untuk `quiz_id` dan `questionnaire_id` sudah ada
- ✅ Logic pembuatan curriculum material sudah benar
- ✅ Database structure sudah benar (kolom `quiz_id` dan `questionnaire_id` ada)
- ✅ Foreign key constraints sudah ada

### **Database - SUDAH BENAR ✅**
- ✅ Tabel `lms_curriculum_materials` memiliki kolom `quiz_id` dan `questionnaire_id`
- ✅ Tabel `lms_quizzes` dan `lms_questionnaires` tersedia dengan data
- ✅ Foreign key relationships sudah benar

### **Frontend (Courses.vue) - PERLU DIPERIKSA ❌**
- ❌ Kemungkinan ada masalah di JavaScript yang mencegah data terkirim
- ❌ Kemungkinan ada error di form submission
- ❌ Kemungkinan ada masalah di data binding

## 🔧 **LANGKAH PERBAIKAN**

### **1. Periksa Frontend Console**

Buka browser developer tools dan periksa:

```javascript
// Di browser console, jalankan:
console.log('Form data:', form.value);
console.log('Sessions:', form.value.sessions);

// Periksa setiap session item
form.value.sessions.forEach((session, sessionIndex) => {
  console.log(`Session ${sessionIndex + 1}:`, session);
  if (session.items) {
    session.items.forEach((item, itemIndex) => {
      console.log(`  Item ${itemIndex + 1}:`, item);
      if (item.item_type === 'quiz') {
        console.log(`    Quiz ID:`, item.quiz_id);
      } else if (item.item_type === 'questionnaire') {
        console.log(`    Questionnaire ID:`, item.questionnaire_id);
      }
    });
  }
});
```

### **2. Periksa Network Tab**

1. Buka browser developer tools
2. Buka tab Network
3. Buat course baru dengan quiz/questionnaire
4. Periksa request yang dikirim ke `/lms/courses`
5. Lihat apakah `quiz_id` dan `questionnaire_id` ada di FormData

### **3. Tambahkan Debug Logging di Frontend**

Tambahkan console.log di `Courses.vue`:

```javascript
// Di fungsi submitForm, sebelum membuat FormData
console.log('=== SUBMITTING FORM ===');
console.log('Form data:', form.value);
console.log('Sessions:', form.value.sessions);

// Di loop FormData creation
if (item.item_type === 'quiz' && item.quiz_id) {
  console.log(`Adding quiz_id: ${item.quiz_id} for item ${itemIndex + 1}`);
  formData.append(`sessions[${sessionIndex}][items][${itemIndex}][quiz_id]`, item.quiz_id);
}

if (item.item_type === 'questionnaire' && item.questionnaire_id) {
  console.log(`Adding questionnaire_id: ${item.questionnaire_id} for item ${itemIndex + 1}`);
  formData.append(`sessions[${sessionIndex}][items][${itemIndex}][questionnaire_id]`, item.questionnaire_id);
}

// Setelah FormData dibuat
console.log('=== FORMDATA CONTENTS ===');
for (let [key, value] of formData.entries()) {
  console.log(`${key}:`, value);
}
```

### **4. Periksa Quiz/Questionnaire Selection**

Pastikan dropdown selection berfungsi:

```javascript
// Di browser console
console.log('Available quizzes:', availableQuizzes.value);
console.log('Available questionnaires:', availableQuestionnaires.value);

// Periksa apakah item.quiz_id dan item.questionnaire_id terisi
form.value.sessions.forEach((session, sessionIndex) => {
  session.items.forEach((item, itemIndex) => {
    if (item.item_type === 'quiz') {
      console.log(`Session ${sessionIndex + 1}, Item ${itemIndex + 1} quiz_id:`, item.quiz_id);
    }
    if (item.item_type === 'questionnaire') {
      console.log(`Session ${sessionIndex + 1}, Item ${itemIndex + 1} questionnaire_id:`, item.questionnaire_id);
    }
  });
});
```

## 🐛 **DEBUGGING CHECKLIST**

### **Frontend Issues:**
- [ ] Apakah dropdown quiz/questionnaire berfungsi?
- [ ] Apakah `item.quiz_id` dan `item.questionnaire_id` terisi?
- [ ] Apakah ada JavaScript error di console?
- [ ] Apakah FormData berisi data yang benar?
- [ ] Apakah request dikirim dengan benar?

### **Backend Issues:**
- [ ] Apakah data sampai di backend?
- [ ] Apakah validasi berhasil?
- [ ] Apakah curriculum material dibuat?
- [ ] Apakah session item dibuat?

### **Database Issues:**
- [ ] Apakah data tersimpan di `lms_curriculum_materials`?
- [ ] Apakah `quiz_id` dan `questionnaire_id` terisi?
- [ ] Apakah data tersimpan di `lms_session_items`?

## 🚀 **SOLUSI CEPAT**

### **Jika Frontend Tidak Mengirim Data:**

1. **Reset form data saat item type berubah:**
```javascript
const onItemTypeChange = (sessionIndex, itemIndex) => {
  const item = form.value.sessions[sessionIndex].items[itemIndex];
  
  // Reset semua field
  item.quiz_id = '';
  item.questionnaire_id = '';
  item.item_id = '';
  item.material_files = [];
  
  console.log(`Item type changed to ${item.item_type}, reset data:`, item);
};
```

2. **Pastikan data binding benar:**
```vue
<select v-model="item.quiz_id" @change="onQuizChange(sessionIndex, itemIndex)">
  <option value="">Pilih quiz...</option>
  <option v-for="quiz in availableQuizzes" :key="quiz.id" :value="quiz.id">
    {{ quiz.title }}
  </option>
</select>
```

3. **Tambahkan watcher untuk debugging:**
```javascript
watch(() => form.value.sessions, (newSessions) => {
  console.log('Sessions changed:', newSessions);
}, { deep: true });
```

## 📋 **TESTING CHECKLIST**

### **Test 1: Basic Functionality**
- [ ] Buat course baru dengan 1 session
- [ ] Tambahkan 1 quiz item
- [ ] Tambahkan 1 questionnaire item
- [ ] Submit form
- [ ] Periksa apakah data tersimpan

### **Test 2: Data Validation**
- [ ] Periksa console log untuk data yang dikirim
- [ ] Periksa network tab untuk request data
- [ ] Periksa database untuk data yang tersimpan

### **Test 3: Error Handling**
- [ ] Test dengan quiz_id kosong
- [ ] Test dengan questionnaire_id kosong
- [ ] Test dengan item_type tidak valid

## 🔍 **LOGS YANG HARUS DIPERIKSA**

### **Frontend Logs:**
```
=== SUBMITTING FORM ===
Form data: {...}
Sessions: [...]
=== FORMDATA CONTENTS ===
sessions[0][items][0][quiz_id]: 1
sessions[0][items][1][questionnaire_id]: 1
```

### **Backend Logs:**
```
[INFO] Quiz item found: {"quiz_id":"1","quiz_id_type":"string"}
[INFO] Quiz ID found: {"quiz_id":1,"quiz_id_type":"integer"}
[INFO] Quiz curriculum material created: {"material_id":123,"quiz_id":1}
```

## 🎯 **KESIMPULAN**

**Masalah utama ada di frontend yang tidak mengirim data `quiz_id` dan `questionnaire_id` dengan benar.**

**Backend sudah benar dan berfungsi dengan baik.** Yang perlu diperbaiki adalah:

1. **Frontend data binding** - Pastikan `item.quiz_id` dan `item.questionnaire_id` terisi
2. **Form submission** - Pastikan data terkirim dengan benar
3. **JavaScript debugging** - Tambahkan console.log untuk tracking data flow

Setelah frontend diperbaiki, quiz dan questionnaire items akan tersimpan dengan benar di database.
