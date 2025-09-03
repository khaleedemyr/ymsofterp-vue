# LMS Trainer & Curriculum Update Documentation

## Overview
Update ini menambahkan fitur trainer (internal/external) dan kurikulum training ke sistem LMS, serta menyesuaikan informasi training antara form create dan detail page.

## ✅ **Fitur yang Ditambahkan:**

### 1. **Trainer Management**
- **Trainer Internal**: Pilih dari daftar user yang memiliki role admin, trainer, atau manager
- **Trainer External**: Input nama dan deskripsi trainer external
- **Validasi**: Wajib memilih tipe trainer dan mengisi informasi yang diperlukan

### 2. **Curriculum Management**
- **Dynamic Curriculum**: Tambah/hapus sesi training secara dinamis
- **Sesi Fields**: 
  - Urutan (order_number)
  - Judul Sesi (title)
  - Durasi (duration_minutes)
  - Deskripsi (description)
- **Auto-reorder**: Urutan otomatis diupdate saat menghapus sesi

### 3. **Form Structure Update**
Form create course sekarang memiliki struktur:
1. **Informasi Dasar** (title, category, description)
2. **Pengaturan Course** (target divisi, jabatan, level, difficulty, duration)
3. **Kompetensi yang Akan Dikembangkan** (learning objectives)
4. **Persyaratan Peserta** (requirements)
5. **Kurikulum Training** (curriculum) ✨ **BARU**
6. **Informasi Trainer** (trainer_type, instructor_id, external_trainer_*) ✨ **BARU**
7. **Thumbnail Course** (upload thumbnail)
8. **Pengaturan Tambahan** (status)

## 🔧 **Technical Changes:**

### **Frontend (Vue.js)**
- **File**: `resources/js/Pages/Lms/Courses.vue`
- **New Props**: `internalTrainers` array
- **New Form Fields**:
  ```javascript
  curriculum: [
    {
      order_number: 1,
      title: '',
      description: '',
      duration_minutes: ''
    }
  ],
  trainer_type: '', // 'internal' or 'external'
  instructor_id: '', // For internal trainer
  external_trainer_name: '', // For external trainer
  external_trainer_description: '' // For external trainer description
  ```

### **Backend (Laravel)**
- **File**: `app/Http/Controllers/LmsController.php`
- **New Validation Rules**:
  ```php
  'curriculum' => 'nullable|array',
  'curriculum.*.order_number' => 'required|integer|min:1',
  'curriculum.*.title' => 'required|string|max:255',
  'curriculum.*.description' => 'nullable|string',
  'curriculum.*.duration_minutes' => 'required|integer|min:1',
  'trainer_type' => 'required|in:internal,external',
  'instructor_id' => 'nullable|exists:users,id',
  'external_trainer_name' => 'nullable|string|max:255',
  'external_trainer_description' => 'nullable|string'
  ```

### **Database**
- **File**: `database/sql/add_trainer_curriculum_fields_to_lms_courses.sql`
- **New Columns**:
  ```sql
  ALTER TABLE lms_courses 
  ADD COLUMN external_trainer_name VARCHAR(255) NULL,
  ADD COLUMN external_trainer_description TEXT NULL;
  ```

### **Model**
- **File**: `app/Models/LmsCourse.php`
- **New Fillable Fields**:
  ```php
  'external_trainer_name',
  'external_trainer_description'
  ```

## 🎨 **UI/UX Features:**

### **Trainer Selection**
- **Dropdown**: Pilih tipe trainer (Internal/External)
- **Conditional Fields**: 
  - Internal: Dropdown pilih trainer dari users
  - External: Input nama dan textarea deskripsi
- **Validation**: Real-time validation dengan error messages

### **Curriculum Builder**
- **Dynamic Cards**: Setiap sesi dalam card terpisah
- **Grid Layout**: 3 kolom untuk urutan, judul, durasi
- **Textarea**: Deskripsi sesi dengan 2 baris
- **Add/Remove**: Button untuk tambah/hapus sesi
- **Auto-numbering**: Urutan otomatis diupdate

### **Form Validation**
- **Required Fields**: Semua field wajib diisi
- **Array Validation**: Validasi untuk curriculum array
- **Conditional Validation**: Validasi berbeda untuk internal/external trainer
- **User Feedback**: SweetAlert untuk error dan success messages

## 📊 **Data Flow:**

### **Create Course Process**
1. User mengisi form dengan curriculum dan trainer info
2. Frontend validasi semua field required
3. FormData dikirim ke backend dengan curriculum array
4. Backend validasi dan simpan ke database
5. Curriculum lessons dibuat otomatis di tabel `lms_lessons`
6. External trainer info disimpan di `lms_courses`

### **Display Process**
1. Course detail page load data dari database
2. Trainer info ditampilkan sesuai tipe (internal/external)
3. Curriculum ditampilkan dari relasi `lessons`
4. Target info (divisi, jabatan, level) ditampilkan dengan accessors

## 🔄 **Integration Points:**

### **Course Detail Page**
- **File**: `resources/js/Pages/Lms/CourseDetail.vue`
- **Updates**:
  - Trainer info menampilkan external_trainer_name jika ada
  - Curriculum menampilkan lessons dari database
  - Informasi training menampilkan target divisi, jabatan, level

### **Controller Integration**
- **Internal Trainers**: Fetch dari users dengan role tertentu
- **Curriculum Creation**: Auto-create lessons saat course dibuat
- **External Trainer**: Store nama dan deskripsi di course table

## 🚀 **Usage Examples:**

### **Creating Course with Internal Trainer**
```javascript
// Form data
{
  title: "Advanced JavaScript Training",
  trainer_type: "internal",
  instructor_id: 123,
  curriculum: [
    {
      order_number: 1,
      title: "Introduction to ES6",
      description: "Learn modern JavaScript features",
      duration_minutes: 60
    }
  ]
}
```

### **Creating Course with External Trainer**
```javascript
// Form data
{
  title: "UX Design Workshop",
  trainer_type: "external",
  external_trainer_name: "John Doe",
  external_trainer_description: "Senior UX Designer with 10+ years experience",
  curriculum: [
    {
      order_number: 1,
      title: "Design Thinking Process",
      description: "Understanding user-centered design",
      duration_minutes: 90
    }
  ]
}
```

## 📋 **Migration Steps:**

1. **Run Database Migration**:
   ```bash
   mysql -u root -p ymsofterp < database/sql/add_trainer_curriculum_fields_to_lms_courses.sql
   ```

2. **Update Controller**: Pastikan `internalTrainers` dipass ke frontend

3. **Test Features**:
   - Create course dengan internal trainer
   - Create course dengan external trainer
   - Add/remove curriculum items
   - Verify display di detail page

## 🎯 **Benefits:**

1. **Flexible Trainer Management**: Support internal dan external trainer
2. **Structured Curriculum**: Kurikulum terorganisir dengan baik
3. **Consistent Data**: Form create dan detail page konsisten
4. **User Experience**: Interface yang intuitif dan responsive
5. **Data Integrity**: Validasi yang ketat di frontend dan backend

## 🔮 **Future Enhancements:**

1. **Trainer Profiles**: Halaman profil untuk internal trainers
2. **Curriculum Templates**: Template kurikulum yang bisa di-reuse
3. **Advanced Scheduling**: Integrasi dengan sistem jadwal training
4. **Progress Tracking**: Tracking progress per sesi kurikulum
5. **Certificate Generation**: Auto-generate sertifikat berdasarkan kurikulum

---

**Status**: ✅ Completed  
**Version**: 1.0  
**Last Updated**: December 2024
