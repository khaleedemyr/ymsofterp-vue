<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoachingApprover extends Model
{
    use HasFactory;

    protected $fillable = [
        'coaching_id',
        'approver_id',
        'approval_level',
        'status',
        'approved_at',
        'rejected_at',
        'comments'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function coaching()
    {
        return $this->belongsTo(Coaching::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
