<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'challenge_id',
        'reward_type',
        'reward_value',
        'reward_data',
        'claimed_at',
        'expires_at',
        'is_used',
        'used_at'
    ];

    protected $casts = [
        'reward_data' => 'array',
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
        'used_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function scopeUnused($query)
    {
        return $query->where('is_used', false);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeValid($query)
    {
        return $query->where('is_used', false)
                    ->where(function($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isUsable(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }
}
