# LMS Curriculum System Documentation

## Overview
Sistem kurikulum LMS memungkinkan setiap kursus memiliki multiple kurikulum yang dapat berisi quiz, kuesioner, dan materi pembelajaran (PDF, image, video, document, link). Setiap kurikulum dapat diatur urutannya dan memiliki persyaratan kelulusan yang fleksibel.

## Fitur Utama

### 1. Multiple Curriculum per Course
- Setiap kursus dapat memiliki beberapa kurikulum
- Kurikulum dapat diatur urutannya
- Setiap kurikulum dapat diatur sebagai wajib atau opsional
- Estimasi durasi untuk setiap kurikulum

### 2. Multiple Items per Curriculum
- **Quiz**: Link ke quiz yang sudah ada atau buat baru
- **Questionnaire**: Link ke kuesioner yang sudah ada atau buat baru  
- **Material**: Materi pembelajaran dengan berbagai tipe file

### 3. Multiple Materials per Item
- **PDF**: Dokumen PDF untuk pembelajaran
- **Image**: Gambar untuk ilustrasi
- **Video**: Video pembelajaran
- **Document**: Dokumen Word, Excel, dll
- **Link**: Link eksternal ke sumber pembelajaran

### 4. Progress Tracking
- Tracking progress siswa per item kurikulum
- Status: Belum Dimulai, Sedang Berlangsung, Selesai, Gagal
- Scoring dan grading untuk quiz
- Time tracking untuk setiap item
- Multiple attempts dengan batasan maksimal

## Database Structure

### Tabel Utama

#### 1. `lms_curriculum`
```sql
- id (Primary Key)
- course_id (Foreign Key ke lms_courses)
- title (Judul kurikulum)
- description (Deskripsi kurikulum)
- order_number (Urutan dalam kursus)
- is_required (Apakah wajib diselesaikan)
- estimated_duration_minutes (Estimasi durasi)
- status (active/inactive)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

#### 2. `lms_curriculum_items`
```sql
- id (Primary Key)
- curriculum_id (Foreign Key ke lms_curriculum)
- item_type (quiz/questionnaire/material)
- item_id (ID item sesuai tipe)
- title (Judul item)
- description (Deskripsi item)
- order_number (Urutan dalam kurikulum)
- is_required (Apakah wajib diselesaikan)
- estimated_duration_minutes (Estimasi durasi)
- passing_score (Nilai minimum untuk lulus)
- max_attempts (Maksimal percobaan)
- status (active/inactive)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

#### 3. `lms_curriculum_materials`
```sql
- id (Primary Key)
- curriculum_item_id (Foreign Key ke lms_curriculum_items)
- title (Judul materi)
- description (Deskripsi materi)
- material_type (pdf/image/video/document/link)
- file_path (Path file untuk upload)
- file_name (Nama file asli)
- file_size (Ukuran file dalam bytes)
- file_mime_type (MIME type file)
- external_url (URL eksternal untuk link)
- thumbnail_path (Path thumbnail)
- duration_seconds (Durasi video)
- is_downloadable (Apakah bisa didownload)
- is_previewable (Apakah bisa di-preview)
- order_number (Urutan materi)
- status (active/inactive)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

#### 4. `lms_curriculum_progress`
```sql
- id (Primary Key)
- user_id (Foreign Key ke users)
- curriculum_item_id (Foreign Key ke lms_curriculum_items)
- status (not_started/in_progress/completed/failed)
- started_at (Waktu mulai)
- completed_at (Waktu selesai)
- score (Nilai yang didapat)
- attempts_count (Jumlah percobaan)
- last_attempt_at (Waktu percobaan terakhir)
- time_spent_minutes (Total waktu yang dihabiskan)
- notes (Catatan progress)
- created_at, updated_at
```

## API Endpoints

### Curriculum Management
```
GET    /lms/courses/{course}/curriculum           - Tampilkan kurikulum
POST   /lms/courses/{course}/curriculum           - Buat kurikulum baru
PUT    /lms/courses/{course}/curriculum/{id}      - Update kurikulum
DELETE /lms/courses/{course}/curriculum/{id}      - Hapus kurikulum
POST   /lms/courses/{course}/curriculum/{id}/duplicate - Duplikasi kurikulum
```

### Curriculum Items
```
POST   /lms/courses/{course}/curriculum/{id}/items     - Buat item baru
PUT    /lms/courses/{course}/curriculum/{id}/items/{item} - Update item
DELETE /lms/courses/{course}/curriculum/{id}/items/{item} - Hapus item
POST   /lms/courses/{course}/curriculum/{id}/items/reorder - Reorder items
```

### Curriculum Materials
```
POST   /lms/courses/{course}/curriculum/{id}/items/{item}/materials     - Tambah materi
PUT    /lms/courses/{course}/curriculum/{id}/items/{item}/materials/{material} - Update materi
DELETE /lms/courses/{course}/curriculum/{id}/items/{item}/materials/{material} - Hapus materi
```

## Model Relationships

### LmsCurriculum
```php
// Relationships
public function course()                    // Belongs to LmsCourse
public function items()                     // Has many LmsCurriculumItem
public function quizzes()                   // Has many quiz items
public function questionnaires()            // Has many questionnaire items
public function materials()                 // Has many material items
public function creator()                   // Belongs to User
public function progress()                  // Has many through progress

// Scopes
public function scopeActive()               // Active curriculum only
public function scopeRequired()             // Required curriculum only
public function scopeOrdered()              // Ordered by order_number

// Methods
public function getProgressForUser()        // Get progress for specific user
public function getEstimatedTotalDuration() // Calculate total duration
public function isAccessibleByUser()        // Check if user can access
public function duplicate()                 // Duplicate curriculum with items
```

### LmsCurriculumItem
```php
// Relationships
public function curriculum()                // Belongs to LmsCurriculum
public function course()                    // Has one through course
public function materials()                 // Has many LmsCurriculumMaterial
public function progress()                  // Has many LmsCurriculumProgress
public function linkedItem()                // Dynamic relationship based on type

// Methods
public function canBeAccessedByUser()      // Check prerequisites
public function startForUser()             // Start item for user
public function completeForUser()          // Complete item for user
public function failForUser()              // Fail item for user
public function canUserRetry()             // Check if user can retry
```

### LmsCurriculumMaterial
```php
// Relationships
public function curriculumItem()            // Belongs to LmsCurriculumItem
public function curriculum()                // Has one through curriculum
public function course()                    // Has one through course

// Methods
public function canBeDownloadedByUser()    // Check download permission
public function canBePreviewedByUser()     // Check preview permission
public function getPreviewUrl()            // Get preview URL
public function deleteFile()               // Delete physical file
public function updateFile()               // Update file
public function generateThumbnail()        // Generate thumbnail
```

### LmsCurriculumProgress
```php
// Relationships
public function user()                      // Belongs to User
public function curriculumItem()            // Belongs to LmsCurriculumItem
public function curriculum()                // Has one through curriculum
public function course()                    // Has one through course

// Methods
public function start()                     // Start progress
public function complete()                  // Complete progress
public function fail()                      // Fail progress
public function reset()                     // Reset progress
public function getGrade()                  // Calculate grade
public function getGradeColor()             // Get grade color
```

## Usage Examples

### 1. Membuat Kurikulum Baru
```php
$curriculum = LmsCurriculum::create([
    'course_id' => $courseId,
    'title' => 'Pengenalan Dasar',
    'description' => 'Modul pengenalan untuk pemula',
    'order_number' => 1,
    'is_required' => true,
    'estimated_duration_minutes' => 30,
    'created_by' => auth()->id(),
]);
```

### 2. Menambahkan Item Quiz
```php
$item = LmsCurriculumItem::create([
    'curriculum_id' => $curriculumId,
    'item_type' => 'quiz',
    'item_id' => $quizId,
    'title' => 'Quiz Pengenalan',
    'description' => 'Quiz untuk menguji pemahaman dasar',
    'order_number' => 1,
    'is_required' => true,
    'passing_score' => 70,
    'max_attempts' => 3,
    'created_by' => auth()->id(),
]);
```

### 3. Menambahkan Materi PDF
```php
$material = LmsCurriculumMaterial::create([
    'curriculum_item_id' => $itemId,
    'title' => 'Modul Pembelajaran',
    'description' => 'Dokumen pembelajaran lengkap',
    'material_type' => 'pdf',
    'file_path' => $filePath,
    'file_name' => 'modul.pdf',
    'file_size' => $fileSize,
    'file_mime_type' => 'application/pdf',
    'is_downloadable' => true,
    'is_previewable' => true,
    'order_number' => 1,
    'created_by' => auth()->id(),
]);
```

### 4. Tracking Progress User
```php
// Start item
$progress = $item->startForUser($userId);

// Complete item with score
$progress = $item->completeForUser($userId, 85, 25);

// Check if user can access next item
$nextItem = $item->getNextItem();
if ($nextItem && $nextItem->canBeAccessedByUser($userId)) {
    // User can access next item
}
```

## Frontend Integration

### Vue.js Components
- `CurriculumIndex.vue` - Halaman utama kurikulum
- `CurriculumForm.vue` - Form tambah/edit kurikulum
- `CurriculumItemForm.vue` - Form tambah/edit item
- `MaterialForm.vue` - Form tambah/edit materi
- `CurriculumProgress.vue` - Tampilan progress siswa

### Features
- Drag & Drop untuk reordering
- File upload dengan preview
- Progress visualization
- Material preview (PDF, image, video)
- Download management
- Progress tracking

## Security & Permissions

### Access Control
- Hanya user yang enrolled ke kursus yang bisa akses
- Hanya instructor/creator yang bisa manage kurikulum
- File access control berdasarkan enrollment status

### File Security
- File disimpan di storage private
- URL generation dengan signed URLs
- File size limits (100MB max)
- File type validation
- Virus scanning (optional)

## Performance Considerations

### Database Optimization
- Indexes pada foreign keys
- Composite indexes untuk ordering
- Eager loading untuk relationships
- Database views untuk reporting

### File Management
- File compression untuk video
- Thumbnail generation
- CDN integration untuk file delivery
- File cleanup untuk deleted materials

## Future Enhancements

### Planned Features
- Advanced prerequisites (AND/OR logic)
- Time-based access control
- Collaborative learning features
- Advanced analytics dan reporting
- Mobile app integration
- Offline content support
- AI-powered content recommendations

### Technical Improvements
- Real-time progress updates
- WebSocket integration
- Advanced caching strategies
- Microservices architecture
- API rate limiting
- Advanced search dan filtering
