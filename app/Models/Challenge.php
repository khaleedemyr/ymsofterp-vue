<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_type_id',
        'name',
        'description',
        'rules',
        'validity_period_days',
        'is_active',
        'start_date',
        'end_date',
        'created_by'
    ];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date'
    ];

    public function challengeType(): BelongsTo
    {
        return $this->belongsTo(ChallengeType::class);
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'challenge_outlets', 'challenge_id', 'outlet_id');
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserChallengeProgress::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ChallengeReward::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        $now = now()->toDateString();
        return $query->where(function($q) use ($now) {
            $q->whereNull('start_date')
              ->orWhere('start_date', '<=', $now);
        })->where(function($q) use ($now) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', $now);
        });
    }

    public function isActive(): bool
    {
        return $this->is_active && 
               (is_null($this->start_date) || $this->start_date <= now()->toDateString()) &&
               (is_null($this->end_date) || $this->end_date >= now()->toDateString());
    }
}
