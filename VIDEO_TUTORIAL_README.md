# Video Tutorial Management System

## Overview
Sistem manajemen video tutorial untuk YM Soft ERP yang memungkinkan pengguna untuk mengupload, mengelola, dan mengelompokkan video tutorial dengan fitur yang lengkap.

## Fitur Utama

### 1. Video Tutorial Groups
- **Kelola Group**: Buat, edit, dan hapus group video tutorial
- **Deskripsi Group**: Tambahkan deskripsi untuk setiap group
- **Status Management**: Aktifkan/nonaktifkan group
- **Statistik**: Lihat jumlah total video dan video aktif dalam group

### 2. Video Tutorial Management
- **Upload Video**: Upload file video dengan format MP4, WebM, AVI, MOV (maksimal 100MB)
- **Thumbnail**: Upload thumbnail manual atau generate otomatis dari video
- **Metadata**: Ekstraksi durasi video otomatis
- **Grouping**: Kelompokkan video berdasarkan group
- **Status Control**: Aktifkan/nonaktifkan video tutorial
- **Search & Filter**: Cari video berdasarkan judul, deskripsi, group, dan status

### 3. Video Player
- **Native HTML5 Player**: Pemutaran video langsung di browser
- **Responsive Design**: Tampilan yang responsif di berbagai perangkat
- **Thumbnail Preview**: Preview thumbnail sebelum memutar video

## Database Structure

### Tabel: `video_tutorial_groups`
```sql
- id (Primary Key)
- name (Nama group)
- description (Deskripsi group)
- status (A=Active, N=Inactive)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

### Tabel: `video_tutorials`
```sql
- id (Primary Key)
- group_id (Foreign Key ke video_tutorial_groups)
- title (Judul video)
- description (Deskripsi video)
- video_path (Path file video)
- video_name (Nama file video)
- video_type (Tipe file video)
- video_size (Ukuran file dalam bytes)
- thumbnail_path (Path thumbnail)
- duration (Durasi video dalam detik)
- status (A=Active, N=Inactive)
- created_by (User yang membuat)
- created_at, updated_at, deleted_at
```

### Tabel: `erp_menu` (Menu System)
```sql
- id (Primary Key)
- name (Nama menu)
- code (Kode menu)
- parent_id (Parent menu ID, 3 untuk Master Data)
- route (URL route)
- icon (Icon FontAwesome)
- created_at, updated_at
```

### Tabel: `erp_permission` (Permission System)
```sql
- id (Primary Key)
- menu_id (Foreign Key ke erp_menu)
- action (view, create, update, delete)
- code (Kode permission)
- created_at, updated_at
```

## File Structure

### Backend
```
app/
├── Models/
│   ├── VideoTutorialGroup.php
│   └── VideoTutorial.php
├── Http/Controllers/
│   ├── VideoTutorialController.php
│   └── VideoTutorialGroupController.php
└── database/migrations/
    ├── 2025_01_20_000000_create_video_tutorial_groups_table.php
    └── 2025_01_20_000001_create_video_tutorials_table.php
```

### Frontend
```
resources/js/Pages/
├── VideoTutorial/
│   ├── Index.vue
│   ├── Create.vue
│   ├── Edit.vue
│   └── Show.vue
└── VideoTutorialGroup/
    ├── Index.vue
    ├── Create.vue
    ├── Edit.vue
    └── Show.vue
```

## Setup Instructions

### 1. Jalankan Migration
```bash
php artisan migrate
```

### 2. Insert Menu dan Permissions
Jalankan script SQL berikut di database MySQL:
```sql
-- File: database/sql/insert_video_tutorial_menu.sql
```

Script ini akan:
- Menambahkan menu "Master Data Video Tutorial" dan "Master Data Group Video Tutorial" ke parent_id = 3 (Master Data)
- Membuat permissions untuk view, create, update, delete untuk kedua menu
- Menggunakan struktur tabel `erp_menu` dan `erp_permission` yang sesuai

### 3. Install FFmpeg (Opsional)
Untuk fitur auto-generate thumbnail dan ekstraksi durasi video:
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install ffmpeg

# CentOS/RHEL
sudo yum install ffmpeg

# macOS
brew install ffmpeg

# Windows
# Download dari https://ffmpeg.org/download.html
```

### 4. Konfigurasi Storage
Pastikan storage link sudah dibuat:
```bash
php artisan storage:link
```

### 5. Assign Permissions ke Role
Setelah menjalankan script SQL, Anda perlu assign permissions ke role user:
```sql
-- Ganti role_id dengan ID role yang sesuai
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 
    1 as role_id,  -- Ganti dengan role_id admin
    p.id as permission_id,
    NOW() as created_at,
    NOW() as updated_at
FROM `erp_permission` p
WHERE p.code IN (
    'view-video-tutorial-groups',
    'create-video-tutorial-groups',
    'update-video-tutorial-groups',
    'delete-video-tutorial-groups',
    'view-video-tutorials',
    'create-video-tutorials',
    'update-video-tutorials',
    'delete-video-tutorials'
)
ON DUPLICATE KEY UPDATE updated_at = NOW();
```

## Routes

### Video Tutorial Groups
- `GET /video-tutorial-groups` - Index page
- `GET /video-tutorial-groups/create` - Create form
- `POST /video-tutorial-groups` - Store new group
- `GET /video-tutorial-groups/{id}` - Show group detail
- `GET /video-tutorial-groups/{id}/edit` - Edit form
- `PUT /video-tutorial-groups/{id}` - Update group
- `DELETE /video-tutorial-groups/{id}` - Delete group
- `PATCH /video-tutorial-groups/{id}/toggle-status` - Toggle status

### Video Tutorials
- `GET /video-tutorials` - Index page
- `GET /video-tutorials/create` - Create form
- `POST /video-tutorials` - Store new video
- `GET /video-tutorials/{id}` - Show video detail
- `GET /video-tutorials/{id}/edit` - Edit form
- `PUT /video-tutorials/{id}` - Update video
- `DELETE /video-tutorials/{id}` - Delete video
- `PATCH /video-tutorials/{id}/toggle-status` - Toggle status

## Features Detail

### 1. Video Upload
- **Supported Formats**: MP4, WebM, AVI, MOV
- **Max File Size**: 100MB
- **Auto Thumbnail**: Generate thumbnail otomatis dari detik ke-1 video
- **Duration Extraction**: Ekstraksi durasi video otomatis
- **File Validation**: Validasi tipe dan ukuran file

### 2. Thumbnail Management
- **Manual Upload**: Upload thumbnail custom (JPEG, PNG, JPG, max 2MB)
- **Auto Generation**: Generate thumbnail otomatis jika tidak diupload manual
- **Preview**: Preview thumbnail di halaman detail dan list

### 3. Search & Filter
- **Search**: Cari berdasarkan judul, deskripsi, nama file
- **Filter by Group**: Filter video berdasarkan group
- **Filter by Status**: Filter berdasarkan status aktif/inaktif
- **Pagination**: Pagination untuk performa yang baik

### 4. Status Management
- **Active/Inactive**: Toggle status video dan group
- **Visual Indicators**: Badge warna untuk status
- **Bulk Operations**: Toggle status dengan satu klik

### 5. Activity Logging
Semua operasi CRUD dicatat dalam `activity_logs` table dengan informasi:
- User ID
- Activity type (create, update, delete)
- Module (video_tutorials, video_tutorial_groups)
- Description
- IP address
- User agent
- Old data dan new data

## Usage Examples

### 1. Membuat Group Video Tutorial
1. Klik menu "Master Data Group Video Tutorial"
2. Klik tombol "Tambah Group"
3. Isi nama group dan deskripsi
4. Klik "Simpan Group"

### 2. Upload Video Tutorial
1. Klik menu "Master Data Video Tutorial"
2. Klik tombol "Upload Video Tutorial"
3. Pilih group video tutorial
4. Isi judul dan deskripsi
5. Upload file video (opsional: upload thumbnail)
6. Klik "Upload Video"

### 3. Mengelola Video
1. Lihat daftar video di halaman index
2. Gunakan search dan filter untuk menemukan video
3. Klik tombol aksi untuk detail, edit, atau hapus
4. Toggle status untuk mengaktifkan/nonaktifkan video

## Dependencies

### Backend
- Laravel 10+
- FFmpeg (opsional, untuk thumbnail generation)
- Inertia.js

### Frontend
- Vue.js 3
- Tailwind CSS
- SweetAlert2
- Lodash (debounce)

## Security Features

### File Upload Security
- **File Type Validation**: Hanya format video yang diizinkan
- **File Size Limit**: Maksimal 100MB untuk video, 2MB untuk thumbnail
- **Secure Storage**: File disimpan di storage yang aman
- **Virus Scanning**: Implementasi scanning virus (opsional)

### Access Control
- **Authentication**: Semua route memerlukan login
- **Authorization**: Permission-based access control
- **Activity Logging**: Semua aktivitas dicatat

## Performance Optimization

### Database
- **Indexing**: Index pada kolom yang sering dicari
- **Eager Loading**: Load relationship data dengan efisien
- **Pagination**: Pagination untuk data yang besar

### File Storage
- **CDN Ready**: Struktur file siap untuk CDN
- **Thumbnail Optimization**: Thumbnail dioptimasi untuk web
- **Lazy Loading**: Load video hanya saat diperlukan

## Troubleshooting

### Common Issues

#### 1. Video tidak bisa diupload
- Cek ukuran file (maksimal 100MB)
- Cek format file (MP4, WebM, AVI, MOV)
- Cek permission folder storage

#### 2. Thumbnail tidak generate
- Pastikan FFmpeg terinstall
- Cek path FFmpeg di controller
- Cek permission folder storage

#### 3. Video tidak bisa diputar
- Cek format video (browser support)
- Cek path file video
- Cek permission file

#### 4. Menu tidak muncul
- Jalankan script SQL menu
- Cek permission user
- Clear cache: `php artisan cache:clear`

### Debug Commands
```bash
# Check storage link
php artisan storage:link

# Clear all cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check FFmpeg
ffmpeg -version

# Check file permissions
ls -la storage/app/public/
```

## Future Enhancements

### Planned Features
- **Video Categories**: Kategori tambahan selain group
- **Video Playlists**: Buat playlist video
- **Video Analytics**: Track video views dan engagement
- **Video Comments**: Sistem komentar untuk video
- **Video Sharing**: Share video ke social media
- **Video Quality**: Multiple quality options
- **Video Subtitles**: Support subtitle/caption
- **Video Chapters**: Chapter markers dalam video

### Technical Improvements
- **Video Streaming**: Implementasi streaming untuk video besar
- **Video Compression**: Auto-compression untuk optimasi storage
- **CDN Integration**: Integrasi dengan CDN untuk delivery yang lebih cepat
- **Video Processing Queue**: Background processing untuk video upload
- **API Endpoints**: REST API untuk mobile app integration

## Support

Untuk bantuan teknis atau pertanyaan, silakan hubungi tim development atau buat issue di repository project. 