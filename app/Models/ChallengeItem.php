<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'description',
        'is_available_for_reward'
    ];

    protected $casts = [
        'is_available_for_reward' => 'boolean'
    ];

    public function scopeAvailable($query)
    {
        return $query->where('is_available_for_reward', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
