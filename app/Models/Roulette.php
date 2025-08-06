<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roulette extends Model
{
    use HasFactory;

    protected $table = 'roulettes';

    protected $fillable = [
        'nama',
        'email',
        'no_hp',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
} 