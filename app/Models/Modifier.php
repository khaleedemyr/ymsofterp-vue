<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modifier extends Model
{
    use HasFactory;

    protected $table = 'modifiers';

    protected $fillable = [
        'name',
    ];

    public function options()
    {
        return $this->hasMany(ModifierOption::class, 'modifier_id');
    }
} 