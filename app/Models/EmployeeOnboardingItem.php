<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOnboardingItem extends Model
{
    protected $fillable = [
        'onboarding_id',
        'template_item_id',
        'week_number',
        'area_name',
        'checklist_text',
        'pic_role_hint',
        'assigned_pic_user_id',
        'status',
        'remark',
        'sort_order',
        'updated_by',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'sort_order' => 'integer',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboarding::class, 'onboarding_id');
    }

    public function assignedPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_pic_user_id');
    }
}
