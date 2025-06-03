<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryOutlet extends Model
{
    protected $table = 'category_outlet';
    protected $fillable = [
        'category_id',
        'outlet_id',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
} 