<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'parameters_config',
        'is_active'
    ];

    protected $casts = [
        'parameters_config' => 'array',
        'is_active' => 'boolean'
    ];

    public function challenges()
    {
        return $this->hasMany(Challenge::class);
    }
}
