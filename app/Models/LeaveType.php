<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $table = 'leave_types';

    protected $fillable = [
        'name',
        'max_days',
        'requires_document',
        'description',
        'is_active'
    ];

    protected $casts = [
        'requires_document' => 'boolean',
        'is_active' => 'boolean',
        'max_days' => 'integer'
    ];

    /**
     * Scope to get only active leave types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
