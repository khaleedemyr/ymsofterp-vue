<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coaching extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'supervisor_id',
        'coaching_date',
        'violation_date',
        'location',
        'violation_details',
        'disciplinary_actions',
        'supervisor_comments',
        'employee_response',
        'supervisor_signature',
        'employee_signature',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'coaching_date' => 'date',
        'violation_date' => 'date',
        'disciplinary_actions' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvers()
    {
        return $this->hasMany(CoachingApprover::class)->orderBy('approval_level');
    }
}
