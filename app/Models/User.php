<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nik', 'no_ktp', 'nama_lengkap', 'email', 'password', 'hint_password', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
        'agama', 'status_pernikahan', 'alamat', 'alamat_ktp', 'golongan_darah', 'no_hp', 'nama_kontak_darurat',
        'no_hp_kontak_darurat', 'hubungan_kontak_darurat', 'jumlah_anak', 'nomor_kk', 'nama_rekening', 'no_rekening',
        'npwp_number', 'bpjs_health_number', 'bpjs_employment_number', 'last_education', 'name_school_college',
        'school_college_major', 'position', 'foto_ktp', 'foto_kk', 'upload_latest_color_photo', 'imei',
        'avatar', 'status', 'pin_pos', 'work_start_date',
        // field lama
        'id_role', 'id_outlet', 'division_id',
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
}
