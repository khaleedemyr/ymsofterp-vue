<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class MemberAppsMember extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'member_apps_members';
    
    protected $fillable = [
        'member_id',
        'photo',
        'email',
        'nama_lengkap',
        'mobile_phone',
        'tanggal_lahir',
        'jenis_kelamin',
        'pekerjaan_id',
        'pin',
        'password',
        'is_exclusive_member',
        'member_level',
        'total_spending',
        'just_points',
        'is_active',
        'email_verified_at',
        'mobile_verified_at',
        'last_login_at'
    ];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'is_exclusive_member' => 'boolean',
        'is_active' => 'boolean',
        'total_spending' => 'decimal:2',
        'just_points' => 'integer',
        'email_verified_at' => 'datetime',
        'mobile_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function occupation()
    {
        return $this->belongsTo(MemberAppsOccupation::class, 'pekerjaan_id');
    }

    public function getJenisKelaminTextAttribute()
    {
        return $this->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan';
    }
}

