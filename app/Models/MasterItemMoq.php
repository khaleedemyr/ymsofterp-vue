<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterItemMoq extends Model
{
    use HasFactory;

    protected $table = 'master_item_moq';

    protected $fillable = [
        'item_id',
        'unit_id',
        'conversion_qty',
        'moq_qty',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'conversion_qty' => 'decimal:4',
        'moq_qty' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
