# Learning Management System (LMS) - YM Soft ERP

## Overview
Sistem Learning Management System (LMS) yang terintegrasi dengan YM Soft ERP untuk mengelola pembelajaran, kursus, dan pelatihan karyawan secara digital.

## Fitur Utama

### 1. Dashboard LMS
- **Statistik Overview**: Total kursus, enrollment, progress rata-rata
- **Kursus Terdaftar**: Daftar kursus yang sedang diikuti user
- **Kursus Terbaru**: Rekomendasi kursus terbaru
- **Kategori Populer**: Kategori dengan kursus terbanyak
- **Aktivitas Belajar**: Timeline aktivitas belajar 30 hari terakhir

### 2. Manajemen Kursus
- **CRUD Kursus**: Buat, edit, hapus, dan lihat kursus
- **Kategori Kursus**: Pengelompokan kursus berdasarkan kategori
- **Level Kesulitan**: Beginner, Intermediate, Advanced
- **Harga & Gratis**: Sistem kursus berbayar dan gratis
- **Thumbnail & Deskripsi**: Media dan informasi kursus
- **Instruktur**: Assign instruktur untuk setiap kursus
- **Status**: Draft, Published, Archived

### 3. Manajemen Lesson
- **Tipe Lesson**: Video, Document, Quiz, Assignment
- **Urutan Lesson**: Pengaturan urutan pembelajaran
- **Video Upload**: Upload video lesson dengan thumbnail
- **Durasi Video**: Tracking durasi video otomatis
- **Konten HTML**: Rich text content untuk lesson

### 4. Sistem Enrollment
- **Enrollment Otomatis**: User dapat mendaftar ke kursus
- **Progress Tracking**: Tracking progress per lesson
- **Status Enrollment**: Enrolled, In Progress, Completed, Dropped
- **Batas Siswa**: Maksimal jumlah siswa per kursus

### 5. Quiz & Assessment
- **Tipe Quiz**: Multiple Choice, True/False, Essay
- **Batas Waktu**: Time limit untuk quiz
- **Nilai Minimum**: Passing score untuk lulus
- **Percobaan**: Maksimal percobaan quiz
- **Randomisasi**: Acak urutan soal
- **Grading Otomatis**: Auto-grading untuk multiple choice

### 6. Assignment System
- **Upload File**: Submit assignment dalam bentuk file
- **Text Submission**: Submit dalam bentuk text
- **Due Date**: Batas waktu pengumpulan
- **Late Submission**: Opsi untuk terlambat
- **Grading Manual**: Penilaian manual oleh instruktur

### 7. Sertifikat
- **Auto Generate**: Generate sertifikat otomatis saat selesai
- **Template Sertifikat**: Custom template sertifikat
- **Download**: Download sertifikat dalam format PDF
- **Verifikasi**: Sistem verifikasi sertifikat

### 8. Diskusi & Forum
- **Diskusi Kursus**: Forum diskusi per kursus
- **Nested Replies**: Reply bertingkat
- **Pin Discussion**: Pin diskusi penting
- **Lock Discussion**: Kunci diskusi yang sudah selesai
- **Mark Solution**: Tandai jawaban sebagai solusi

### 9. Laporan & Analytics
- **Statistik Overall**: Total kursus, enrollment, completion rate
- **Trend Enrollment**: Grafik enrollment 6 bulan terakhir
- **Top Courses**: Kursus dengan enrollment terbanyak
- **Category Stats**: Statistik per kategori
- **User Progress**: Progress per user

## Database Structure

### Tabel Utama

#### 1. `lms_categories` - Kategori Kursus
```sql
- id (Primary Key)
- name (Nama kategori)
- description (Deskripsi kategori)
- parent_id (ID kategori parent - untuk nested categories)
- icon (Icon FontAwesome)
- color (Warna kategori - hex)
- status (A=Active, N=Inactive)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

#### 2. `lms_courses` - Kursus
```sql
- id (Primary Key)
- title (Judul kursus)
- description (Deskripsi kursus)
- thumbnail_path (Path thumbnail)
- duration_hours (Durasi dalam jam)
- difficulty_level (beginner/intermediate/advanced)
- category_id (Foreign Key ke lms_categories)
- instructor_id (Foreign Key ke users)
- max_students (Maksimal siswa)
- price (Harga kursus)
- is_free (Apakah gratis)
- status (draft/published/archived)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

#### 3. `lms_lessons` - Lesson dalam Kursus
```sql
- id (Primary Key)
- course_id (Foreign Key ke lms_courses)
- title (Judul lesson)
- description (Deskripsi lesson)
- content (Konten HTML)
- video_path (Path video)
- video_duration (Durasi video dalam detik)
- thumbnail_path (Path thumbnail)
- lesson_type (video/document/quiz/assignment)
- order_number (Urutan lesson)
- is_free (Apakah lesson gratis)
- status (draft/published/archived)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

#### 4. `lms_enrollments` - Enrollment Siswa
```sql
- id (Primary Key)
- course_id (Foreign Key ke lms_courses)
- user_id (Foreign Key ke users)
- enrollment_date (Tanggal enroll)
- completion_date (Tanggal selesai)
- progress_percentage (Persentase progress)
- status (enrolled/in_progress/completed/dropped)
- certificate_path (Path sertifikat)
- created_at, updated_at
```

#### 5. `lms_lesson_progress` - Progress Lesson
```sql
- id (Primary Key)
- enrollment_id (Foreign Key ke lms_enrollments)
- lesson_id (Foreign Key ke lms_lessons)
- user_id (Foreign Key ke users)
- status (not_started/in_progress/completed)
- watched_duration (Durasi yang sudah ditonton)
- completion_date (Tanggal selesai)
- score (Nilai untuk quiz/assignment)
- notes (Catatan)
- created_at, updated_at
```

#### 6. `lms_quizzes` - Quiz
```sql
- id (Primary Key)
- lesson_id (Foreign Key ke lms_lessons)
- title (Judul quiz)
- description (Deskripsi quiz)
- time_limit (Batas waktu dalam menit)
- passing_score (Nilai minimum lulus)
- max_attempts (Maksimal percobaan)
- is_randomized (Apakah soal diacak)
- status (draft/published/archived)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

#### 7. `lms_quiz_questions` - Pertanyaan Quiz
```sql
- id (Primary Key)
- quiz_id (Foreign Key ke lms_quizzes)
- question_text (Teks pertanyaan)
- question_type (multiple_choice/true_false/essay)
- points (Poin untuk pertanyaan)
- order_number (Urutan pertanyaan)
- created_at, updated_at
```

#### 8. `lms_quiz_options` - Opsi Jawaban
```sql
- id (Primary Key)
- question_id (Foreign Key ke lms_quiz_questions)
- option_text (Teks opsi)
- is_correct (Apakah opsi benar)
- order_number (Urutan opsi)
- created_at, updated_at
```

#### 9. `lms_assignments` - Assignment
```sql
- id (Primary Key)
- lesson_id (Foreign Key ke lms_lessons)
- title (Judul assignment)
- description (Deskripsi assignment)
- instructions (Instruksi assignment)
- due_date (Batas waktu)
- max_points (Nilai maksimal)
- passing_score (Nilai minimum lulus)
- allow_late_submission (Boleh terlambat)
- status (draft/published/archived)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

#### 10. `lms_certificates` - Sertifikat
```sql
- id (Primary Key)
- enrollment_id (Foreign Key ke lms_enrollments)
- user_id (Foreign Key ke users)
- course_id (Foreign Key ke lms_courses)
- certificate_number (Nomor sertifikat)
- issue_date (Tanggal terbit)
- completion_date (Tanggal selesai)
- file_path (Path file sertifikat)
- status (pending/issued/revoked)
- created_at, updated_at
```

#### 11. `lms_discussions` - Diskusi
```sql
- id (Primary Key)
- course_id (Foreign Key ke lms_courses)
- user_id (Foreign Key ke users)
- title (Judul diskusi)
- content (Konten diskusi)
- is_pinned (Apakah di-pin)
- is_locked (Apakah dikunci)
- status (active/closed/archived)
- created_at, updated_at, deleted_at
```

## Setup Instructions

### 1. Jalankan Database Setup
```bash
# Jalankan script SQL untuk membuat tabel LMS
mysql -u username -p database_name < database/sql/create_lms_tables.sql

# Jalankan script untuk menambahkan menu dan permissions
mysql -u username -p database_name < database/sql/insert_lms_menu.sql
```

### 2. Buat Storage Directories
```bash
# Buat direktori untuk LMS
mkdir -p storage/app/public/lms
mkdir -p storage/app/public/lms/courses
mkdir -p storage/app/public/lms/lessons
mkdir -p storage/app/public/lms/assignments
mkdir -p storage/app/public/lms/certificates

# Pastikan storage link sudah ada
php artisan storage:link
```

### 3. Assign Permissions ke Role
```sql
-- Ganti role_id dengan ID role yang sesuai
INSERT INTO `erp_role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 
    1 as role_id,  -- Ganti dengan role_id admin
    p.id as permission_id,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_permission` p
WHERE p.menu_id IN (
    SELECT id FROM erp_menu WHERE parent_id = (
        SELECT id FROM erp_menu WHERE name = 'Learning Management System'
    )
)
ON DUPLICATE KEY UPDATE updated_at = NOW();
```

### 4. Install Dependencies (Opsional)
```bash
# Untuk generate sertifikat PDF
composer require barryvdh/laravel-dompdf

# Untuk video processing
composer require php-ffmpeg/php-ffmpeg
```

## File Structure

### Backend
```
app/
├── Models/
│   ├── LmsCategory.php
│   ├── LmsCourse.php
│   ├── LmsLesson.php
│   ├── LmsEnrollment.php
│   ├── LmsQuiz.php
│   ├── LmsAssignment.php
│   ├── LmsCertificate.php
│   └── LmsDiscussion.php
├── Http/Controllers/
│   ├── LmsController.php
│   ├── LmsCategoryController.php
│   ├── LmsCourseController.php
│   ├── LmsLessonController.php
│   ├── LmsEnrollmentController.php
│   ├── LmsQuizController.php
│   ├── LmsAssignmentController.php
│   ├── LmsCertificateController.php
│   └── LmsDiscussionController.php
└── database/sql/
    ├── create_lms_tables.sql
    └── insert_lms_menu.sql
```

### Frontend
```
resources/js/Pages/Lms/
├── Dashboard.vue
├── Courses/
│   ├── Index.vue
│   ├── Show.vue
│   ├── Create.vue
│   └── Edit.vue
├── Categories/
│   ├── Index.vue
│   ├── Create.vue
│   └── Edit.vue
├── Lessons/
│   ├── Index.vue
│   ├── Show.vue
│   ├── Create.vue
│   └── Edit.vue
├── MyCourses.vue
└── Reports.vue
```

## Routes

### LMS Routes
```php
Route::middleware(['auth'])->prefix('lms')->name('lms.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [LmsController::class, 'dashboard'])->name('dashboard');
    
    // Courses
    Route::get('/courses', [LmsController::class, 'courses'])->name('courses.index');
    Route::get('/courses/{course}', [LmsController::class, 'showCourse'])->name('courses.show');
    Route::post('/courses/{course}/enroll', [LmsController::class, 'enroll'])->name('courses.enroll');
    
    // My Courses
    Route::get('/my-courses', [LmsController::class, 'myCourses'])->name('my-courses');
    
    // Reports
    Route::get('/reports', [LmsController::class, 'reports'])->name('reports');
    
    // Resource Routes
    Route::resource('categories', LmsCategoryController::class);
    Route::resource('lessons', LmsLessonController::class);
    Route::resource('enrollments', LmsEnrollmentController::class);
    Route::resource('quizzes', LmsQuizController::class);
    Route::resource('assignments', LmsAssignmentController::class);
    Route::resource('certificates', LmsCertificateController::class);
    Route::resource('discussions', LmsDiscussionController::class);
});
```

## Usage Guide

### 1. Akses LMS
- Login ke sistem YM Soft ERP
- Menu "Learning Management System" akan muncul di sidebar
- Klik "Dashboard LMS" untuk melihat overview

### 2. Membuat Kategori
- Klik menu "Kategori Kursus"
- Klik "Tambah Kategori"
- Isi nama, deskripsi, icon, dan warna
- Klik "Simpan"

### 3. Membuat Kursus
- Klik menu "Kursus"
- Klik "Tambah Kursus"
- Isi informasi kursus (judul, deskripsi, kategori, dll)
- Upload thumbnail
- Set status ke "Published" untuk membuat publik
- Klik "Simpan"

### 4. Menambah Lesson
- Buka detail kursus
- Klik "Tambah Lesson"
- Pilih tipe lesson (Video/Document/Quiz/Assignment)
- Upload video atau isi konten
- Set urutan lesson
- Klik "Simpan"

### 5. Enrollment Siswa
- Siswa dapat melihat kursus di "Kursus"
- Klik "Daftar" pada kursus yang diinginkan
- Sistem akan membuat enrollment otomatis
- Siswa dapat mulai belajar

### 6. Tracking Progress
- Progress otomatis terupdate saat siswa menyelesaikan lesson
- Dapat dilihat di "My Courses"
- Progress bar menunjukkan persentase penyelesaian

### 7. Quiz & Assignment
- Instruktur dapat membuat quiz dan assignment
- Siswa dapat mengerjakan dan submit
- Sistem akan auto-grade untuk quiz
- Instruktur dapat grade assignment manual

### 8. Sertifikat
- Sertifikat otomatis generate saat siswa selesai kursus
- Dapat di-download dalam format PDF
- Memiliki nomor unik untuk verifikasi

## Features Detail

### 1. Video Player
- Native HTML5 video player
- Progress tracking otomatis
- Resume dari posisi terakhir
- Thumbnail preview

### 2. Quiz System
- Multiple choice dengan opsi benar
- True/False questions
- Essay questions dengan grading manual
- Time limit dan max attempts
- Randomisasi soal

### 3. Assignment System
- File upload (PDF, DOC, ZIP, dll)
- Text submission
- Due date management
- Late submission handling
- Manual grading dengan feedback

### 4. Discussion Forum
- Thread-based discussions
- Nested replies
- Mark as solution
- Pin important discussions
- Lock completed discussions

### 5. Certificate System
- Auto-generate saat completion
- Custom template
- Unique certificate number
- PDF format
- Verification system

### 6. Analytics & Reports
- Enrollment trends
- Course completion rates
- User progress tracking
- Category statistics
- Top performing courses

## Security & Permissions

### Permission Levels
1. **View**: Melihat konten
2. **Create**: Membuat konten baru
3. **Edit**: Mengedit konten
4. **Delete**: Menghapus konten

### Role-based Access
- **Admin**: Full access ke semua fitur
- **Instructor**: Dapat membuat dan mengelola kursus
- **Student**: Dapat enroll dan belajar
- **Manager**: Dapat melihat laporan dan analytics

## Troubleshooting

### Common Issues

#### 1. Video tidak dapat diputar
- Pastikan format video didukung (MP4, WebM, AVI)
- Check file permissions di storage
- Pastikan storage link sudah dibuat

#### 2. Upload file gagal
- Check file size limit di php.ini
- Pastikan direktori storage writable
- Check disk space

#### 3. Menu tidak muncul
- Pastikan script insert_lms_menu.sql sudah dijalankan
- Check role permissions
- Clear cache: `php artisan cache:clear`

#### 4. Progress tidak terupdate
- Check JavaScript console untuk error
- Pastikan AJAX request berhasil
- Check database connection

### Performance Tips
1. **Video Optimization**: Compress video sebelum upload
2. **Image Optimization**: Compress thumbnail images
3. **Database Indexing**: Pastikan index pada foreign keys
4. **Caching**: Implement caching untuk statistik
5. **CDN**: Gunakan CDN untuk video dan image

## Future Enhancements

### Planned Features
1. **Mobile App**: Native mobile application
2. **Live Streaming**: Real-time video streaming
3. **Gamification**: Points, badges, leaderboards
4. **Social Learning**: Group discussions, peer reviews
5. **AI Recommendations**: Smart course recommendations
6. **Advanced Analytics**: Detailed learning analytics
7. **Integration**: Integration dengan HR system
8. **Multi-language**: Support multiple languages

### Technical Improvements
1. **Microservices**: Split into microservices
2. **API**: RESTful API for mobile apps
3. **Real-time**: WebSocket for live features
4. **Search**: Advanced search with Elasticsearch
5. **Notifications**: Push notifications
6. **Backup**: Automated backup system

## Support

Untuk bantuan teknis atau pertanyaan, silakan hubungi:
- Email: support@ymsoft.com
- Documentation: https://docs.ymsoft.com/lms
- GitHub Issues: https://github.com/ymsoft/lms/issues

---

**Version**: 1.0.0  
**Last Updated**: January 2025  
**Compatibility**: Laravel 10+, Vue.js 3+, MySQL 8.0+ 