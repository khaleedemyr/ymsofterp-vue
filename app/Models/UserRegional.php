<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRegional extends Model
{
    use HasFactory;

    public const AREAS = ['Bar', 'Kitchen', 'Service'];

    protected $table = 'user_regional';

    protected $fillable = [
        'user_id',
        'area',
        'target_outlet_visits',
    ];

    protected $casts = [
        'target_outlet_visits' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForArea($query, string $area)
    {
        return $query->where('area', $area);
    }
}
