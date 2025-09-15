# Update Sistem Trainer: Internal & External di Training Schedule

## Overview
Update ini mengubah sistem trainer dari level course ke level training schedule, dengan support untuk trainer internal dan external. Trainer sekarang diatur langsung di jadwal training, bukan di course.

## ✅ **Perubahan yang Diimplementasikan:**

### 1. **Database Schema Update**
- **File**: `update_training_schedule_trainers_for_external.sql`
- **Tabel**: `training_schedule_trainers`
- **Fields Baru**:
  - `trainer_type` (ENUM: 'internal', 'external')
  - `external_trainer_name` (VARCHAR 255)
  - `external_trainer_email` (VARCHAR 255)
  - `external_trainer_phone` (VARCHAR 20)
  - `external_trainer_company` (VARCHAR 255)

### 2. **Model TrainingScheduleTrainer Update**
- **File**: `app/Models/TrainingScheduleTrainer.php`
- **Fitur Baru**:
  - Accessor `getTrainerNameAttribute()` - otomatis return nama internal atau external
  - Accessor `getTrainerEmailAttribute()` - otomatis return email internal atau external
  - Accessor `getTrainerTypeTextAttribute()` - text untuk tipe trainer
  - Accessor `getTrainerTypeColorAttribute()` - color untuk UI

### 3. **Modal Undang Trainer Update**
- **File**: `resources/js/Pages/Lms/TrainingSchedule/TrainerInvitationModal.vue`
- **Fitur Baru**:
  - Radio button untuk pilih tipe trainer (Internal/External)
  - Form khusus untuk external trainer (nama, email, phone, company)
  - Logic untuk handle internal vs external trainer
  - Validasi berbeda untuk setiap tipe

### 4. **Training Detail Modal Update**
- **File**: `resources/js/Pages/Lms/TrainingSchedule/TrainingDetailModal.vue`
- **Fitur Baru**:
  - Display badge untuk tipe trainer (Internal/External)
  - Avatar hanya untuk internal trainer
  - Method `getTrainerTypeColor()` untuk styling

### 5. **Controller Update**
- **File**: `app/Http/Controllers/TrainingScheduleController.php`
- **Fitur Baru**:
  - Validasi berbeda untuk internal vs external trainer
  - Logic untuk check duplicate trainer (internal by ID, external by name+email)
  - Handle creation untuk kedua tipe trainer

### 6. **Course Form Update**
- **Files**: 
  - `resources/js/Pages/Lms/Courses.vue` (Create)
  - `resources/js/Pages/Lms/CourseEdit.vue` (Edit)
- **Perubahan**:
  - Remove semua field trainer (trainer_type, instructor_id, external_trainer_*)
  - Tambah info box yang menjelaskan trainer diatur di schedule level

## 🚀 **Cara Penggunaan:**

### **Undang Internal Trainer:**
1. Buka Jadwal Training → Klik training schedule → "Undang Trainer"
2. Pilih "Internal Trainer"
3. Search dan pilih trainer dari daftar user
4. Klik "Undang X Trainer"

### **Undang External Trainer:**
1. Buka Jadwal Training → Klik training schedule → "Undang Trainer"
2. Pilih "External Trainer"
3. Isi form:
   - Nama Lengkap (wajib)
   - Email (opsional)
   - No. Telepon (opsional)
   - Perusahaan (opsional)
4. Klik "Tambah External Trainer"
5. Klik "Undang X Trainer"

### **View Trainer di Training Detail:**
- Badge "Internal" (biru) atau "External" (ungu)
- Badge "Primary" (kuning) untuk primary trainer
- Avatar hanya untuk internal trainer
- Info lengkap trainer di tabel

## 📋 **Setup Database:**

Jalankan SQL berikut untuk update tabel:

```sql
-- Update training_schedule_trainers table to support external trainers
ALTER TABLE `training_schedule_trainers` 
ADD COLUMN `trainer_type` ENUM('internal', 'external') DEFAULT 'internal' AFTER `trainer_id`,
ADD COLUMN `external_trainer_name` VARCHAR(255) NULL AFTER `trainer_type`,
ADD COLUMN `external_trainer_email` VARCHAR(255) NULL AFTER `external_trainer_name`,
ADD COLUMN `external_trainer_phone` VARCHAR(20) NULL AFTER `external_trainer_email`,
ADD COLUMN `external_trainer_company` VARCHAR(255) NULL AFTER `external_trainer_phone`;

-- Update existing records to be internal trainers
UPDATE `training_schedule_trainers` SET `trainer_type` = 'internal' WHERE `trainer_type` IS NULL;

-- Add index for better performance
ALTER TABLE `training_schedule_trainers` 
ADD INDEX `idx_trainer_type` (`trainer_type`),
ADD INDEX `idx_external_trainer_name` (`external_trainer_name`);
```

## 🔧 **Technical Details:**

### **Validation Rules:**
```php
// Internal Trainer
'trainer_id' => 'required_if:trainer_type,internal|exists:users,id'

// External Trainer
'external_trainer_name' => 'required_if:trainer_type,external|string|max:255'
'external_trainer_email' => 'nullable|email|max:255'
'external_trainer_phone' => 'nullable|string|max:20'
'external_trainer_company' => 'nullable|string|max:255'
```

### **Duplicate Check Logic:**
```php
// Internal Trainer - check by trainer_id
$existingTrainer = TrainingScheduleTrainer::where('schedule_id', $schedule->id)
    ->where('trainer_id', $trainerData['trainer_id'])
    ->first();

// External Trainer - check by name and email
$existingTrainer = TrainingScheduleTrainer::where('schedule_id', $schedule->id)
    ->where('trainer_type', 'external')
    ->where('external_trainer_name', $trainerData['external_trainer_name'])
    ->where('external_trainer_email', $trainerData['external_trainer_email'])
    ->first();
```

### **Model Accessors:**
```php
// Otomatis return nama trainer
public function getTrainerNameAttribute()
{
    if ($this->trainer_type === 'external') {
        return $this->external_trainer_name;
    }
    return $this->trainer ? $this->trainer->nama_lengkap : 'Trainer tidak ditemukan';
}

// Otomatis return email trainer
public function getTrainerEmailAttribute()
{
    if ($this->trainer_type === 'external') {
        return $this->external_trainer_email;
    }
    return $this->trainer ? $this->trainer->email : null;
}
```

## 🎯 **Benefits:**

1. **Flexibility**: Bisa mix internal dan external trainer dalam satu training
2. **Real-time**: Trainer diatur per schedule, bukan per course
3. **External Support**: Support trainer dari luar perusahaan
4. **Better Tracking**: Tracking yang lebih detail per trainer per schedule
5. **Simplified Course**: Course form lebih simple, fokus ke content

## 🔄 **Migration Path:**

1. **Existing Data**: Trainer yang sudah ada di course akan tetap ada sebagai fallback
2. **New Training**: Semua training baru harus set trainer di schedule level
3. **Backward Compatibility**: System tetap support course dengan trainer default

## 🚨 **Important Notes:**

1. **Course Form**: Trainer field sudah di-remove dari course form
2. **Schedule Level**: Semua trainer management sekarang di schedule level
3. **External Trainer**: Tidak perlu user account, cukup input manual
4. **Primary Trainer**: Hanya satu primary trainer per schedule
5. **Validation**: External trainer minimal butuh nama, internal trainer butuh user ID

## 🔮 **Future Enhancements:**

1. **External Trainer Portal**: Portal khusus untuk external trainer
2. **Trainer Rating**: Rating dan feedback untuk trainer
3. **Availability Check**: Cek ketersediaan trainer sebelum assign
4. **Bulk Assignment**: Assign trainer ke multiple schedule sekaligus
5. **Trainer Profile**: Profile lengkap untuk external trainer
