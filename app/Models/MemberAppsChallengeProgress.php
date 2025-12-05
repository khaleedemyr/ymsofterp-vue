<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsChallengeProgress extends Model
{
    protected $table = 'member_apps_challenge_progress';
    
    protected $fillable = [
        'member_id',
        'challenge_id',
        'started_at',
        'progress_data',
        'is_completed',
        'completed_at',
        'reward_claimed',
        'reward_claimed_at',
        'reward_redeemed_at',
        'redeemed_outlet_id',
        'reward_expires_at',
        'serial_code'
    ];

    protected $casts = [
        'progress_data' => 'array',
        'is_completed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reward_claimed' => 'boolean',
        'reward_claimed_at' => 'datetime',
        'reward_redeemed_at' => 'datetime',
        'reward_expires_at' => 'datetime'
    ];

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }

    public function challenge()
    {
        return $this->belongsTo(MemberAppsChallenge::class, 'challenge_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeClaimed($query)
    {
        return $query->where('reward_claimed', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Check if reward is expired
     */
    public function isRewardExpired(): bool
    {
        if (!$this->is_completed || !$this->reward_expires_at) {
            return false;
        }
        
        return now()->isAfter($this->reward_expires_at);
    }

    /**
     * Check if reward can be claimed
     */
    public function canClaimReward(): bool
    {
        return $this->is_completed 
            && !$this->reward_claimed 
            && !$this->isRewardExpired();
    }
}

