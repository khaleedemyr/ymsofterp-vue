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
        'point_remainder',
        'is_active',
        'allow_notification',
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
        'allow_notification' => 'boolean',
        'total_spending' => 'decimal:2',
        'just_points' => 'integer',
        'point_remainder' => 'decimal:2',
        'email_verified_at' => 'datetime',
        'mobile_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function occupation()
    {
        return $this->belongsTo(MemberAppsOccupation::class, 'pekerjaan_id');
    }

    public function deviceTokens()
    {
        return $this->hasMany(MemberAppsDeviceToken::class, 'member_id');
    }

    public function getJenisKelaminTextAttribute()
    {
        return $this->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan';
    }

    /**
     * Check if member profile is complete
     * Profile is considered complete if photo is uploaded
     */
    public function isProfileComplete(): bool
    {
        return !empty($this->photo);
    }

    /**
     * Override the save method to prevent direct member_level updates
     * All member_level updates must go through MemberTierService
     */
    public function save(array $options = [])
    {
        // If member_level is being changed, ensure it's done through MemberTierService
        if ($this->isDirty('member_level') && $this->exists) {
            $originalLevel = $this->getOriginal('member_level');
            $newLevel = $this->member_level;
            
            // Only allow update if it's done through MemberTierService
            // Check if this is being called from MemberTierService by checking the call stack
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $calledFromTierService = false;
            
            foreach ($backtrace as $trace) {
                if (isset($trace['class']) && strpos($trace['class'], 'MemberTierService') !== false) {
                    $calledFromTierService = true;
                    break;
                }
            }
            
            if (!$calledFromTierService) {
                \Log::warning('Attempted to update member_level directly without using MemberTierService', [
                    'member_id' => $this->id,
                    'original_level' => $originalLevel,
                    'attempted_level' => $newLevel,
                    'backtrace' => array_slice($backtrace, 0, 5)
                ]);
                
                // Revert the change and update through MemberTierService instead
                $this->member_level = $originalLevel;
                
                // Update tier properly using MemberTierService
                \App\Services\MemberTierService::updateMemberTier($this->id);
                
                // Don't save member_level here, let MemberTierService handle it
                // Remove member_level from dirty attributes
                $this->syncOriginal();
            }
        }
        
        return parent::save($options);
    }
}

