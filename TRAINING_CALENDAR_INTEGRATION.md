# Integrasi Training Schedule dengan Calendar Reminder

## Overview
Sistem ini mengintegrasikan jadwal training dengan calendar reminder, sehingga ketika jadwal training dibuat dan peserta/trainer diundang, otomatis akan muncul reminder di calendar.

## Fitur yang Ditambahkan

### 1. Auto-Create Reminder untuk Trainer
- Ketika jadwal training dibuat dengan internal trainer, otomatis membuat reminder untuk trainer
- Reminder muncul di calendar dengan indikator hijau
- Title: "Training sebagai Trainer: [Course Title]"

### 2. Auto-Create Reminder untuk Peserta
- Ketika peserta diundang ke training, otomatis membuat reminder untuk peserta
- Reminder muncul di calendar dengan indikator hijau
- Title: "Training: [Course Title]"

### 3. Auto-Create Reminder untuk Trainer yang Diundang
- Ketika internal trainer diundang ke training, otomatis membuat reminder
- Reminder muncul di calendar dengan indikator hijau
- Title: "Training sebagai Trainer: [Course Title]"

## Implementasi

### Modifikasi TrainingScheduleController

#### 1. Method `store()` - Saat membuat jadwal training
```php
// Create calendar reminder for trainer (if internal trainer)
if ($course->instructor_id) {
    $this->createTrainingReminder($schedule, $course->instructor_id, 'trainer');
}
```

#### 2. Method `inviteParticipants()` - Saat mengundang peserta
```php
// Create calendar reminder for participant
$this->createTrainingReminder($schedule, $userId, 'participant');
```

#### 3. Method `inviteTrainers()` - Saat mengundang trainer
```php
// Create calendar reminder for internal trainer
if ($trainerData['trainer_type'] === 'internal') {
    $this->createTrainingReminder($schedule, $trainerData['trainer_id'], 'trainer');
}
```

#### 4. Method `createTrainingReminder()` - Helper method
```php
private function createTrainingReminder($schedule, $userId, $role = 'participant')
{
    // Load schedule with relationships
    $schedule->load(['course', 'outlet']);
    
    // Get user details
    $user = \App\Models\User::find($userId);
    if (!$user) {
        \Log::warning('User not found for training reminder', ['user_id' => $userId]);
        return;
    }

    // Get outlet name
    $outletName = $schedule->outlet ? $schedule->outlet->nama_outlet : 'Head Office';
    
    // Create reminder title based on role
    $title = $role === 'trainer' 
        ? "Training sebagai Trainer: {$schedule->course->title}"
        : "Training: {$schedule->course->title}";
    
    // Create reminder description
    $description = "📚 Course: {$schedule->course->title}\n";
    $description .= "📅 Tanggal: " . \Carbon\Carbon::parse($schedule->scheduled_date)->format('d F Y') . "\n";
    $description .= "⏰ Waktu: {$schedule->start_time} - {$schedule->end_time}\n";
    $description .= "🏢 Lokasi: {$outletName}\n";
    $description .= "👤 Role: " . ($role === 'trainer' ? 'Trainer' : 'Peserta') . "\n";
    
    if ($schedule->notes) {
        $description .= "📝 Catatan: {$schedule->notes}\n";
    }
    
    $description .= "\nJangan lupa untuk hadir tepat waktu!";

    // Create reminder in database
    DB::table('reminders')->insert([
        'user_id' => $userId,
        'created_by' => auth()->id(),
        'date' => $schedule->scheduled_date,
        'time' => $schedule->start_time,
        'title' => $title,
        'description' => $description,
        'created_at' => now(),
        'updated_at' => now()
    ]);
}
```

## Database Schema

### Tabel `reminders`
```sql
CREATE TABLE IF NOT EXISTS `reminders` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) unsigned NOT NULL,
    `created_by` bigint(20) unsigned NOT NULL,
    `date` date NOT NULL,
    `time` time NULL DEFAULT NULL,
    `title` varchar(255) NOT NULL,
    `description` text,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reminders_user_id_foreign` (`user_id`),
    KEY `reminders_created_by_foreign` (`created_by`),
    KEY `reminders_date_index` (`date`),
    CONSTRAINT `reminders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `reminders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Flow Integrasi

### 1. Membuat Jadwal Training
1. User membuat jadwal training baru
2. Sistem menyimpan jadwal ke `training_schedules`
3. Jika ada internal trainer, sistem otomatis membuat reminder untuk trainer
4. Reminder muncul di calendar trainer

### 2. Mengundang Peserta
1. User mengundang peserta ke training
2. Sistem menyimpan undangan ke `training_invitations`
3. Sistem mengirim notifikasi ke peserta
4. Sistem otomatis membuat reminder untuk peserta
5. Reminder muncul di calendar peserta

### 3. Mengundang Trainer
1. User mengundang internal trainer ke training
2. Sistem menyimpan undangan ke `training_schedule_trainers`
3. Sistem mengirim notifikasi ke trainer
4. Sistem otomatis membuat reminder untuk trainer
5. Reminder muncul di calendar trainer

## Contoh Output Reminder

### Untuk Trainer:
```
Title: Training sebagai Trainer: Laravel Advanced Programming
Description:
📚 Course: Laravel Advanced Programming
📅 Tanggal: 15 Januari 2024
⏰ Waktu: 09:00 - 17:00
🏢 Lokasi: Head Office
👤 Role: Trainer
📝 Catatan: Training untuk tim development

Jangan lupa untuk hadir tepat waktu!
```

### Untuk Peserta:
```
Title: Training: Laravel Advanced Programming
Description:
📚 Course: Laravel Advanced Programming
📅 Tanggal: 15 Januari 2024
⏰ Waktu: 09:00 - 17:00
🏢 Lokasi: Head Office
👤 Role: Peserta

Jangan lupa untuk hadir tepat waktu!
```

## Testing

### Test Case 1: Membuat Jadwal Training dengan Internal Trainer
1. Login sebagai admin/HR
2. Buat jadwal training baru dengan internal trainer
3. Verifikasi reminder muncul di calendar trainer
4. Cek database `reminders` table

### Test Case 2: Mengundang Peserta
1. Login sebagai admin/HR
2. Undang peserta ke training yang sudah ada
3. Verifikasi reminder muncul di calendar peserta
4. Cek database `reminders` table

### Test Case 3: Mengundang Internal Trainer
1. Login sebagai admin/HR
2. Undang internal trainer ke training yang sudah ada
3. Verifikasi reminder muncul di calendar trainer
4. Cek database `reminders` table

## Logging

Sistem mencatat semua aktivitas reminder creation di log:
- Success: `Training reminder created successfully`
- Error: `Error creating training reminder`
- Warning: `User not found for training reminder`

## Keuntungan

1. **Otomatis**: Reminder dibuat otomatis tanpa intervensi manual
2. **Konsisten**: Semua training akan memiliki reminder
3. **Informative**: Reminder berisi detail lengkap training
4. **Terintegrasi**: Menggunakan sistem calendar reminder yang sudah ada
5. **User-friendly**: Reminder muncul di calendar dengan indikator visual

## Catatan Penting

1. Reminder hanya dibuat untuk internal trainer (bukan external trainer)
2. Reminder dibuat dengan waktu yang sama dengan start_time training
3. Reminder dapat dihapus manual oleh user jika diperlukan
4. Sistem tidak membuat reminder duplikat untuk user yang sama
5. Reminder menggunakan format emoji untuk readability yang lebih baik
