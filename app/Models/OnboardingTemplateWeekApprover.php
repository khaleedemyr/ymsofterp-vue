<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTemplateWeekApprover extends Model
{
    protected $fillable = [
        'template_id',
        'week_number',
        'approver_user_id',
        'approval_level',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'approval_level' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'template_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
