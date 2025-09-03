# Quiz & Questionnaire Selection Debug Guide

## **🔍 Logging yang Telah Ditambahkan**

### **1. Backend Logging (Controller)**
File: `app/Http/Controllers/LmsCurriculumController.php` - Method `updateSession()`

**Log yang akan muncul:**
```
[2025-09-02 XX:XX:XX] local.INFO: === UPDATE SESSION STARTED ===
[2025-09-02 XX:XX:XX] local.INFO: Course ID: 5
[2025-09-02 XX:XX:XX] local.INFO: Item ID: {item_id}
[2025-09-02 XX:XX:XX] local.INFO: Request data: {"session_number":1,"session_title":"Test Sesi 1","quiz_id":1,...}
[2025-09-02 XX:XX:XX] local.INFO: Course found: {course_title}
[2025-09-02 XX:XX:XX] local.INFO: Curriculum item found: {...}
[2025-09-02 XX:XX:XX] local.INFO: Validation passed successfully
[2025-09-02 XX:XX:XX] local.INFO: Database transaction started
[2025-09-02 XX:XX:XX] local.INFO: Data to update: {...}
[2025-09-02 XX:XX:XX] local.INFO: Curriculum item updated successfully
[2025-09-02 XX:XX:XX] local.INFO: Updated item data: {...}
[2025-09-02 XX:XX:XX] local.INFO: Database transaction committed successfully
```

### **2. Frontend Logging (Browser Console)**
File: `resources/js/Pages/Lms/Courses/Curriculum/Index.vue`

**Log yang akan muncul saat pilih quiz:**
```
=== QUIZ SELECTION STARTED ===
Selected quiz: {id: 1, title: "Quiz Title", ...}
Selected session: {id: 1, session_title: "Test Sesi 1", ...}
Preparing request data...
Request data: {id: 1, session_title: "Test Sesi 1", quiz_id: 1, ...}
Request URL: /lms/courses/5/curriculum/sessions/1
Request method: PUT
Response status: 200
Response data: {success: true, message: "Sesi kurikulum berhasil diperbarui", ...}
Quiz selection successful
```

**Log yang akan muncul saat pilih questionnaire:**
```
=== QUESTIONNAIRE SELECTION STARTED ===
Selected questionnaire: {id: 1, title: "Questionnaire Title", ...}
Selected session: {id: 1, session_title: "Test Sesi 1", ...}
Preparing request data...
Request data: {id: 1, session_title: "Test Sesi 1", questionnaire_id: 1, ...}
Request URL: /lms/courses/5/curriculum/sessions/1
Request method: PUT
Response status: 200
Response data: {success: true, message: "Sesi kurikulum berhasil diperbarui", ...}
Questionnaire selection successful
```

## **🧪 Testing Steps**

### **Step 1: Jalankan Database Fix**
```bash
php run_simple_fix.php
```

### **Step 2: Test Quiz Selection**
1. Buka menu kurikulum
2. Buka browser console (F12)
3. Pilih quiz untuk salah satu sesi
4. Periksa console log dan network tab
5. Periksa Laravel log: `storage/logs/laravel.log`

### **Step 3: Test Questionnaire Selection**
1. Pilih questionnaire untuk salah satu sesi
2. Periksa console log dan network tab
3. Periksa Laravel log

### **Step 4: Verify Data**
1. Refresh halaman
2. Pastikan quiz/questionnaire yang dipilih tetap tersimpan
3. Periksa database langsung jika perlu

## **🔍 Debugging Checklist**

### **Frontend (Browser Console)**
- [ ] Quiz selection log muncul
- [ ] Request data lengkap
- [ ] Response status 200
- [ ] Response data success: true
- [ ] No JavaScript errors

### **Backend (Laravel Log)**
- [ ] UPDATE SESSION STARTED log muncul
- [ ] Request data diterima dengan benar
- [ ] Validation passed
- [ ] Database transaction berhasil
- [ ] Data tersimpan dengan benar

### **Database**
- [ ] Tabel `lms_curriculum_items` ada
- [ ] Column `course_id` ada
- [ ] Column `quiz_id` ada
- [ ] Column `questionnaire_id` ada
- [ ] Data tersimpan dengan benar

## **🚨 Common Issues & Solutions**

### **Issue 1: "Field 'curriculum_id' doesn't have a default value"**
**Solution:** Jalankan `php run_simple_fix.php`

### **Issue 2: Quiz selection tidak tersimpan**
**Check:**
1. Browser console log
2. Laravel log
3. Network tab response
4. Database structure

### **Issue 3: Validation failed**
**Check:**
1. Request data format
2. Required fields
3. Data types

### **Issue 4: Database transaction failed**
**Check:**
1. Database connection
2. Table structure
3. Foreign key constraints

## **📊 Expected Results**

### **Successful Quiz Selection:**
```
Frontend: Quiz selection successful
Backend: Database transaction committed successfully
Database: quiz_id field updated with selected quiz ID
UI: Quiz name displayed in session
```

### **Successful Questionnaire Selection:**
```
Frontend: Questionnaire selection successful
Backend: Database transaction committed successfully
Database: questionnaire_id field updated with selected questionnaire ID
UI: Questionnaire name displayed in session
```

## **🔧 Manual Database Check**

Jika masih ada masalah, cek database langsung:

```sql
-- Check table structure
DESCRIBE lms_curriculum_items;

-- Check existing data
SELECT id, course_id, session_title, quiz_id, questionnaire_id 
FROM lms_curriculum_items 
WHERE course_id = 5;

-- Check if quiz exists
SELECT id, title FROM lms_quizzes WHERE id = 1;

-- Check if questionnaire exists
SELECT id, title FROM lms_questionnaires WHERE id = 1;
```

## **📝 Next Steps**

1. **Jalankan database fix**: `php run_simple_fix.php`
2. **Test quiz selection** dengan console log terbuka
3. **Monitor Laravel logs** untuk error detail
4. **Verify data tersimpan** di database
5. **Report hasil** ke saya untuk troubleshooting lebih lanjut

---

**Status**: ✅ **LOGGING ADDED** - Sekarang kita bisa melihat detail proses quiz/questionnaire selection untuk debugging.
