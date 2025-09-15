# Sistem Undang Trainer untuk Training Schedule

## Overview
Sistem ini memungkinkan admin untuk mengundang multiple trainer ke training schedule yang sama, dengan fitur:
- Undang multiple trainer per training schedule
- Set primary trainer dan secondary trainer
- Tracking kehadiran trainer
- Tracking jam mengajar per trainer
- Management trainer per schedule

## Fitur yang Diimplementasikan

### 1. Modal Undang Trainer (`TrainerInvitationModal.vue`)
- **Lokasi**: `resources/js/Pages/Lms/TrainingSchedule/TrainerInvitationModal.vue`
- **Fitur**:
  - Search trainer berdasarkan nama, jabatan, divisi
  - Filter berdasarkan divisi dan jabatan
  - Pagination untuk performa yang baik
  - Preview trainer yang akan diundang
  - Bulk invite multiple trainer sekaligus

### 2. Update Training Detail Modal (`TrainingDetailModal.vue`)
- **Lokasi**: `resources/js/Pages/Lms/TrainingSchedule/TrainingDetailModal.vue`
- **Fitur Baru**:
  - Button "Undang Trainer" di Quick Actions
  - Tabel daftar trainer yang diundang
  - Status kehadiran trainer
  - Jam mengajar per trainer
  - Set primary trainer
  - Remove trainer dari schedule

### 3. Backend API Endpoints
- **Lokasi**: `app/Http/Controllers/TrainingScheduleController.php`
- **Endpoints Baru**:
  - `POST /lms/schedules/{schedule}/invite-trainers` - Undang multiple trainer
  - `PUT /lms/schedules/{schedule}/set-primary-trainer/{trainer}` - Set primary trainer
  - `DELETE /lms/schedules/{schedule}/trainers/{trainer}` - Remove trainer

### 4. Database Schema
- **Tabel**: `training_schedule_trainers`
- **Fields**:
  - `schedule_id` - ID training schedule
  - `trainer_id` - ID user yang menjadi trainer
  - `is_primary_trainer` - Boolean, apakah primary trainer
  - `hours_taught` - Jam mengajar (decimal)
  - `start_time` - Waktu mulai mengajar
  - `end_time` - Waktu selesai mengajar
  - `notes` - Catatan tambahan
  - `status` - Status kehadiran (invited/confirmed/attended/absent)

### 5. Model TrainingScheduleTrainer
- **Lokasi**: `app/Models/TrainingScheduleTrainer.php`
- **Fitur**:
  - Relasi dengan TrainingSchedule dan User
  - Auto calculate jam mengajar dari start_time dan end_time
  - Update TrainerTeachingHours table otomatis
  - Accessor untuk status_text, duration_text, dll

## Cara Penggunaan

### 1. Undang Trainer ke Training Schedule
1. Buka halaman **Jadwal Training**
2. Klik pada training schedule yang ingin diundang trainer
3. Di modal detail training, klik button **"Undang Trainer"**
4. Di modal undang trainer:
   - Search atau filter trainer yang diinginkan
   - Pilih trainer yang akan diundang
   - Klik **"Undang X Trainer"**

### 2. Set Primary Trainer
1. Di modal detail training, lihat tabel **"Daftar Trainer"**
2. Klik button **"Set Primary"** pada trainer yang ingin dijadikan primary
3. Konfirmasi aksi

### 3. Remove Trainer
1. Di modal detail training, lihat tabel **"Daftar Trainer"**
2. Klik button **"Hapus"** pada trainer yang ingin dihapus
3. Konfirmasi aksi

## Database Setup

Jalankan SQL berikut untuk membuat tabel:

```sql
-- Create training_schedule_trainers table
CREATE TABLE IF NOT EXISTS `training_schedule_trainers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `schedule_id` bigint(20) unsigned NOT NULL,
  `trainer_id` bigint(20) unsigned NOT NULL,
  `is_primary_trainer` tinyint(1) NOT NULL DEFAULT 0,
  `hours_taught` decimal(8,2) DEFAULT 0.00,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('invited','confirmed','attended','absent') DEFAULT 'invited',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `training_schedule_trainers_schedule_id_foreign` (`schedule_id`),
  KEY `training_schedule_trainers_trainer_id_foreign` (`trainer_id`),
  KEY `training_schedule_trainers_created_by_foreign` (`created_by`),
  KEY `training_schedule_trainers_updated_by_foreign` (`updated_by`),
  CONSTRAINT `training_schedule_trainers_schedule_id_foreign` FOREIGN KEY (`schedule_id`) REFERENCES `training_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_schedule_trainers_trainer_id_foreign` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_schedule_trainers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_schedule_trainers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Routes yang Ditambahkan

```php
// Trainer invitation management
Route::post('/schedules/{schedule}/invite-trainers', [TrainingScheduleController::class, 'inviteTrainers'])->name('schedules.invite-trainers');
Route::put('/schedules/{schedule}/set-primary-trainer/{trainer}', [TrainingScheduleController::class, 'setPrimaryTrainer'])->name('schedules.set-primary-trainer');
```

## Fitur Tracking

### 1. Jam Mengajar Otomatis
- Sistem otomatis menghitung jam mengajar dari `start_time` dan `end_time`
- Update ke `hours_taught` field
- Update ke `TrainerTeachingHours` table untuk tracking total jam mengajar

### 2. Status Kehadiran
- `invited` - Trainer diundang
- `confirmed` - Trainer konfirmasi hadir
- `attended` - Trainer hadir
- `absent` - Trainer tidak hadir

### 3. Primary vs Secondary Trainer
- Hanya satu primary trainer per schedule
- Multiple secondary trainer diperbolehkan
- Primary trainer ditandai dengan badge "Primary"

## Permissions
- Hanya user dengan permission `lms-schedules-create` atau admin yang bisa mengundang trainer
- Menggunakan method `canInviteParticipants()` dari TrainingSchedule model

## Error Handling
- Validasi input di backend
- SweetAlert2 untuk notifikasi user
- Error handling untuk API calls
- Graceful fallback jika data tidak ditemukan

## Performance Considerations
- Pagination untuk daftar trainer
- Lazy loading untuk relasi
- Index database untuk query yang optimal
- Caching untuk data yang jarang berubah

## Future Enhancements
1. **Email Notifications** - Kirim email otomatis ke trainer yang diundang
2. **Trainer Dashboard** - Halaman khusus untuk trainer melihat jadwal mereka
3. **Bulk Operations** - Undang trainer ke multiple schedule sekaligus
4. **Trainer Availability** - Cek ketersediaan trainer sebelum mengundang
5. **Recurring Training** - Set trainer untuk training berulang
6. **Trainer Rating** - Rating dan feedback untuk trainer
7. **Mobile App** - Notifikasi mobile untuk trainer
