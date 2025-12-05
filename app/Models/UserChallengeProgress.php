<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChallengeProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'challenge_id',
        'progress_data',
        'is_completed',
        'completed_at',
        'reward_claimed',
        'reward_claimed_at'
    ];

    protected $casts = [
        'progress_data' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'reward_claimed' => 'boolean',
        'reward_claimed_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeClaimed($query)
    {
        return $query->where('reward_claimed', true);
    }

    public function getProgressPercentageAttribute(): float
    {
        if (!$this->challenge) return 0;
        
        $rules = $this->challenge->rules;
        $progress = $this->progress_data ?? [];
        
        // Calculate progress based on challenge type
        switch ($this->challenge->challengeType->name) {
            case 'Spending-based':
                $required = $rules['min_amount'] ?? 0;
                $current = $progress['spending'] ?? 0;
                return $required > 0 ? min(100, ($current / $required) * 100) : 0;
                
            case 'Product-based':
                $required = $rules['quantity_required'] ?? 0;
                $current = $progress['products_tried'] ?? 0;
                return $required > 0 ? min(100, ($current / $required) * 100) : 0;
                
            case 'Multi-condition':
                $spendingProgress = ($progress['spending'] ?? 0) / ($rules['min_spending'] ?? 1);
                $transactionProgress = ($progress['transactions'] ?? 0) / ($rules['min_transactions'] ?? 1);
                $visitProgress = ($progress['visits'] ?? 0) / ($rules['min_visits'] ?? 1);
                return min(100, (($spendingProgress + $transactionProgress + $visitProgress) / 3) * 100);
                
            default:
                return 0;
        }
    }
}
