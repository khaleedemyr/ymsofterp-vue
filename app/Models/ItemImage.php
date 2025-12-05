<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemImage extends Model
{
    use HasFactory;

    protected $table = 'item_images';

    protected $fillable = [
        'item_id',
        'path',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
} 