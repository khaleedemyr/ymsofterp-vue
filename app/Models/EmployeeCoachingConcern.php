<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCoachingConcern extends Model
{
    protected $fillable = [
        'employee_coaching_id',
        'concern_code',
        'other_label',
        'comment',
        'sort_order',
    ];

    public function coaching(): BelongsTo
    {
        return $this->belongsTo(EmployeeCoaching::class, 'employee_coaching_id');
    }
}
