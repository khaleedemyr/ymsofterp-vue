<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Repack extends Model
{
    use HasFactory;
    protected $table = 'food_repacks';
    protected $fillable = [
        'repack_number',
        'item_asal_id',
        'qty_asal',
        'item_hasil_id',
        'qty_hasil',
        'status',
        'created_by',
    ];

    public function itemAsal()
    {
        return $this->belongsTo(Item::class, 'item_asal_id');
    }
    public function itemHasil()
    {
        return $this->belongsTo(Item::class, 'item_hasil_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
} 