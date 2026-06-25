<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeCoaching extends Model
{
    protected $fillable = [
        'employee_id',
        'employee_name',
        'jabatan_id',
        'jabatan_name',
        'outlet_id',
        'outlet_name',
        'division_id',
        'division_name',
        'performance_description',
        'action_taken',
        'action_due_date',
        'performance_review_plan_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'action_due_date' => 'date:Y-m-d',
        'performance_review_plan_date' => 'date:Y-m-d',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function concerns(): HasMany
    {
        return $this->hasMany(EmployeeCoachingConcern::class)->orderBy('sort_order');
    }
}
