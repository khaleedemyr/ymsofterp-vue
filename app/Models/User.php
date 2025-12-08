<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nik', 'no_ktp', 'nama_lengkap', 'email', 'password', 'hint_password', 'nama_panggilan', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
        'suku', 'agama', 'status_pernikahan', 'alamat', 'alamat_ktp', 'golongan_darah', 'no_hp', 'nama_kontak_darurat',
        'no_hp_kontak_darurat', 'hubungan_kontak_darurat', 'nomor_kk', 'nama_rekening', 'no_rekening',
        'npwp_number', 'bpjs_health_number', 'bpjs_employment_number', 'last_education', 'name_school_college',
        'school_college_major', 'foto_ktp', 'foto_kk', 'upload_latest_color_photo', 'avatar', 'banner', 'imei',
        'device_info', 'status', 'pin_pos', 'pin_payroll', 'tanggal_masuk', 'total_training_hours', 'total_teaching_hours', 'cuti',
        // field lama
        'id_jabatan', 'id_outlet', 'division_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'total_training_hours' => 'decimal:2',
            'total_teaching_hours' => 'decimal:2',
        ];

    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    public function jabatan() {
        return $this->belongsTo(\App\Models\Jabatan::class, 'id_jabatan', 'id_jabatan');
    }
    public function divisi() {
        return $this->belongsTo(\App\Models\Divisi::class, 'division_id', 'id');
    }
    public function outlet() {
        return $this->belongsTo(\App\Models\Outlet::class, 'id_outlet', 'id_outlet');
    }
    
    public function leaveTransactions() {
        return $this->hasMany(\App\Models\LeaveTransaction::class, 'user_id', 'id');
    }


    public function userPins()
    {
        return $this->hasMany(UserPin::class, 'user_id', 'id');
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission($permissionCode)
    {
        if ($this->is_admin) {
            return true;
        }

        // Check if user has the permission through their role
        return $this->role && $this->role->permissions()
            ->where('code', $permissionCode)
            ->exists();
    }

    /**
     * Check if user can manage training schedules
     */
    public function canManageTrainingSchedules()
    {
        return $this->hasPermission('lms-schedules-view') || 
               $this->hasPermission('lms-schedules-create') ||
               $this->hasPermission('lms-schedules-update') ||
               $this->hasPermission('lms-schedules-delete');
    }

    /**
     * Check if user can invite participants
     */
    public function canInviteParticipants()
    {
        return $this->hasPermission('lms-schedules-invitation') ||
               $this->hasPermission('lms-schedules-invitation-create');
    }

    /**
     * Check if user can use QR scanner
     */
    public function canUseQRScanner()
    {
        return $this->hasPermission('lms-schedules-qr-scanner');
    }

    // Approval relationships
    public function approvalRequests()
    {
        return $this->hasMany(ApprovalRequest::class, 'user_id');
    }

    public function pendingApprovals()
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id')->where('status', 'pending');
    }

    public function approvedRequests()
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id')->where('status', 'approved');
    }

    public function rejectedRequests()
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id')->where('status', 'rejected');
    }

    public function pendingHrdApprovals()
    {
        return $this->hasMany(ApprovalRequest::class, 'hrd_approver_id')->where('status', 'approved')->where('hrd_status', 'pending');
    }

    // Training relationships - TEMPORARILY COMMENTED OUT
    // public function trainingHours()
    // {
    //     return $this->hasMany(UserTrainingHours::class, 'user_id');
    // }

    // public function teachingHours()
    // {
    //     return $this->hasMany(TrainerTeachingHours::class, 'trainer_id');
    // }

    // public function courseTrainers()
    // {
    //     return $this->hasMany(CourseTrainer::class, 'user_id');
    // }

    // public function requiredTrainings()
    // {
    //     return $this->hasManyThrough(
    //         JabatanRequiredTraining::class,
    //         Jabatan::class,
    //         'id_jabatan', // Foreign key on jabatan table
    //         'jabatan_id', // Foreign key on jabatan_required_trainings table
    //         'id_jabatan', // Local key on users table
    //         'id_jabatan'  // Local key on jabatan table
    //     );
    // }

    // Training methods - TEMPORARILY COMMENTED OUT
    // public function getMandatoryTrainings()
    // {
    //     return JabatanRequiredTraining::with(['course.category', 'course.trainers'])
    //         ->where('jabatan_id', $this->id_jabatan)
    //         ->where('is_mandatory', true)
    //         ->get();
    // }

    // public function getOptionalTrainings()
    // {
    //     return JabatanRequiredTraining::with(['course.category', 'course.trainers'])
    //         ->where('jabatan_id', $this->id_jabatan)
    //         ->where('is_mandatory', false)
    //         ->get();
    // }

    // public function getCompletedTrainings()
    // {
    //     return $this->trainingHours()
    //         ->with(['course.category', 'course.trainers'])
    //         ->where('status', 'completed')
    //         ->get();
    // }

    // public function getInProgressTrainings()
    // {
    //     return $this->trainingHours()
    //         ->with(['course.category', 'course.trainers'])
    //         ->where('status', 'in_progress')
    //         ->get();
    // }

    // public function getTrainingComplianceStatus()
    // {
    //     $mandatoryTrainings = $this->getMandatoryTrainings();
    //     $completedTrainings = $this->getCompletedTrainings();
        
    //     $mandatoryCourseIds = $mandatoryTrainings->pluck('course_id')->toArray();
    //     $completedCourseIds = $completedTrainings->pluck('course_id')->toArray();
        
    //     $completedMandatory = array_intersect($mandatoryCourseIds, $completedCourseIds);
        
    //     return [
    //         'total_mandatory' => count($mandatoryCourseIds),
    //         'completed_mandatory' => count($completedMandatory),
    //         'compliance_percentage' => count($mandatoryCourseIds) > 0 
    //             ? round((count($completedMandatory) / count($mandatoryCourseIds)) * 100, 2) 
    //             : 100,
    //         'missing_trainings' => array_diff($mandatoryCourseIds, $completedCourseIds)
    //     ];
    // }

    // public function getTotalTrainingHours()
    // {
    //     return $this->trainingHours()
    //         ->where('status', 'completed')
    //         ->sum('hours_completed');
    // }

    // public function getTotalTeachingHours()
    // {
    //     return $this->teachingHours()->sum('hours_taught');
    // }

    // public function isTrainer()
    // {
    //     return $this->courseTrainers()->exists();
    // }

    // public function getTrainerCourses()
    // {
    //     return $this->courseTrainers()
    //         ->with(['course.category'])
    //         ->get()
    //         ->pluck('course');
    // }
}
