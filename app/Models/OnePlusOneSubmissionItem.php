<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnePlusOneSubmissionItem extends Model
{
    protected $fillable = [
        'submission_id',
        'user_id',
        'one_plus_one_date',
        'deduction_hours',
        'notes',
    ];

    protected $casts = [
        'one_plus_one_date' => 'date:Y-m-d',
        'deduction_hours' => 'float',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(OnePlusOneSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
