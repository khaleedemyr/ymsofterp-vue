<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemButcher extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'tanggal_burcher',
        'batch',
        'catatan'
    ];

    protected $casts = [
        'tanggal_burcher' => 'date',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
} 