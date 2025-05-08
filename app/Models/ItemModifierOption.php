<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemModifierOption extends Model
{
    use HasFactory;

    protected $table = 'item_modifier_options';

    protected $fillable = [
        'item_id',
        'modifier_option_id',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function modifierOption()
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }
} 