<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModifierOption extends Model
{
    use HasFactory;

    protected $table = 'modifier_options';

    protected $fillable = [
        'modifier_id',
        'name',
        'modifier_bom_json',
    ];

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }
} 