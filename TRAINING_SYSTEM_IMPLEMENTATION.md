# Training System Implementation

## Overview
Sistem training baru yang memungkinkan:
1. Training terikat ke jabatan tertentu (wajib/opsional)
2. Tracking jam training per user
3. Multiple trainers per training
4. Tracking compliance training
5. Tracking jam mengajar trainer

## Database Structure

### 1. Tabel Baru

#### `jabatan_required_trainings`
- Menyimpan training yang wajib/opsional untuk setiap jabatan
- Fields: jabatan_id, course_id, is_mandatory, notes

#### `course_trainers`
- Menyimpan multiple trainers untuk setiap course
- Fields: course_id, user_id, is_primary, notes

#### `user_training_hours`
- Tracking jam training per user per course
- Fields: user_id, course_id, hours_completed, total_course_hours, status

#### `trainer_teaching_hours`
- Tracking jam mengajar per trainer
- Fields: trainer_id, course_id, hours_taught, teaching_date

### 2. Modifikasi Tabel Existing

#### `users`
- Ditambah: total_training_hours, total_teaching_hours

## Models

### 1. JabatanRequiredTraining
- Relasi ke Jabatan dan LmsCourse
- Methods: getRequiredTrainingsForJabatan(), getMandatoryTrainingsForJabatan()

### 2. CourseTrainer
- Relasi ke LmsCourse dan User
- Methods: getTrainersForCourse(), getPrimaryTrainerForCourse()

### 3. UserTrainingHours
- Relasi ke User, LmsCourse, LmsEnrollment
- Methods: updateProgress(), markAsCompleted(), getTrainingHoursForUser()

### 4. TrainerTeachingHours
- Relasi ke User, LmsCourse, TrainingSchedule
- Methods: getTeachingHoursForTrainer(), getTotalTeachingHoursForTrainer()

### 5. User (Updated)
- Relasi baru: trainingHours(), teachingHours(), courseTrainers()
- Methods: getMandatoryTrainings(), getTrainingComplianceStatus(), isTrainer()

### 6. LmsCourse (Updated)
- Relasi baru: trainers(), userTrainingHours(), jabatanRequiredTrainings()
- Methods: getTrainersList(), addTrainer(), getUsersWithTrainingHours()

## Controllers

### 1. JabatanTrainingController
- Management training per jabatan
- CRUD operations untuk jabatan_required_trainings
- Bulk assign training ke multiple jabatan

### 2. TrainingComplianceController
- Dashboard compliance training
- Report compliance per user/jabatan
- Report jam mengajar trainer
- Export functionality

## Routes

### Training Compliance Routes
```
/training/compliance/dashboard - Dashboard utama
/training/compliance/report - Laporan compliance
/training/compliance/user/{user} - Detail compliance user
/training/compliance/trainer-report - Laporan trainer
/training/compliance/trainer/{trainer} - Detail trainer
/training/compliance/course-report - Laporan course
```

### Jabatan Training Routes
```
/jabatan-training - List training per jabatan
/jabatan-training/create - Tambah training ke jabatan
/jabatan-training/{id} - Detail training jabatan
/jabatan-training/{id}/edit - Edit training jabatan
/jabatan-training/bulk-assign - Bulk assign training
```

## Views

### 1. Training/Compliance/Dashboard.vue
- Dashboard utama dengan statistik
- Compliance per jabatan
- Top trainers
- Recent activities
- Low compliance users

## Features

### 1. Training per Jabatan
- Admin bisa set training wajib/opsional per jabatan
- User otomatis melihat training sesuai jabatan mereka
- Tracking compliance per jabatan

### 2. Multiple Trainers
- 1 course bisa punya multiple trainers
- Ada primary trainer dan secondary trainers
- Tracking jam mengajar per trainer

### 3. Training Hours Tracking
- Tracking jam training per user per course
- Progress tracking dengan persentase
- Status: in_progress, completed, dropped

### 4. Compliance Monitoring
- Dashboard compliance real-time
- Report user yang belum training wajib
- Export laporan compliance

### 5. Trainer Performance
- Tracking jam mengajar per trainer
- Report performance trainer
- Statistik mengajar per bulan/tahun

## Usage Examples

### 1. Set Training Wajib untuk Jabatan
```php
// Set training "Safety Training" wajib untuk jabatan "Manager"
JabatanRequiredTraining::create([
    'jabatan_id' => 1, // Manager
    'course_id' => 5,  // Safety Training
    'is_mandatory' => true,
    'notes' => 'Training wajib untuk semua manager'
]);
```

### 2. Assign Multiple Trainers ke Course
```php
$course = LmsCourse::find(1);

// Set primary trainer
$course->addTrainer($userId1, true, 'Primary trainer');

// Set secondary trainers
$course->addTrainer($userId2, false, 'Backup trainer');
$course->addTrainer($userId3, false, 'Assistant trainer');
```

### 3. Update Training Progress
```php
$userTraining = UserTrainingHours::where('user_id', $userId)
    ->where('course_id', $courseId)
    ->first();

$userTraining->updateProgress(8.5, 'Completed 8.5 hours of training');
```

### 4. Record Teaching Hours
```php
TrainerTeachingHours::create([
    'trainer_id' => $trainerId,
    'course_id' => $courseId,
    'hours_taught' => 4.0,
    'teaching_date' => '2024-01-15',
    'start_time' => '09:00',
    'end_time' => '13:00',
    'participant_count' => 25
]);
```

### 5. Check User Compliance
```php
$user = User::find($userId);
$compliance = $user->getTrainingComplianceStatus();

echo "Compliance: " . $compliance['compliance_percentage'] . "%";
echo "Missing trainings: " . count($compliance['missing_trainings']);
```

## Migration

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Or use the provided script
```bash
php run_training_migrations.php
```

### 3. With sample data
```bash
php run_training_migrations.php --with-sample
```

## Permissions

Sistem ini memerlukan permission baru:
- `training-compliance-view` - View compliance dashboard
- `training-compliance-export` - Export compliance reports
- `jabatan-training-manage` - Manage training per jabatan
- `trainer-hours-view` - View trainer teaching hours

## Integration Points

### 1. Existing LMS System
- Terintegrasi dengan sistem course existing
- Menggunakan enrollment system yang ada
- Compatible dengan training schedule system

### 2. User Management
- Terintegrasi dengan sistem user/jabatan existing
- Menggunakan divisi dan outlet system

### 3. Reporting
- Export ke Excel format
- Compatible dengan existing report system

## Future Enhancements

1. **Automated Notifications**
   - Email reminder untuk training yang belum selesai
   - Notifikasi untuk trainer yang belum mengajar

2. **Advanced Analytics**
   - Chart dan grafik untuk trend training
   - Predictive analytics untuk compliance

3. **Mobile App Integration**
   - Mobile app untuk tracking training
   - Push notifications

4. **Gamification**
   - Point system untuk training completion
   - Badges dan achievements

5. **Integration dengan HR System**
   - Auto update employee records
   - Performance review integration

## Troubleshooting

### Common Issues

1. **Migration Fails**
   - Check database connection
   - Ensure Laravel is properly configured
   - Check for existing table conflicts

2. **Permission Errors**
   - Ensure user has proper permissions
   - Check middleware configuration

3. **Data Not Showing**
   - Check relationships in models
   - Verify data exists in database
   - Check query filters

### Debug Commands

```bash
# Check table structure
php artisan tinker
>>> Schema::hasTable('jabatan_required_trainings')

# Check relationships
>>> $user = User::find(1)
>>> $user->getMandatoryTrainings()

# Check compliance
>>> $user->getTrainingComplianceStatus()
```

## Support

Untuk support dan pertanyaan:
1. Check documentation ini
2. Review code comments
3. Check Laravel logs
4. Contact development team
