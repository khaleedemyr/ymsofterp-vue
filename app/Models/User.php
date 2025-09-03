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
        'school_college_major', 'foto_ktp', 'foto_kk', 'upload_latest_color_photo', 'avatar', 'imei',
        'status', 'pin_pos', 'tanggal_masuk',
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
}
