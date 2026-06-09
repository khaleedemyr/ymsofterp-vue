<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiKeyStrategy extends Model
{
    protected $table = 'kpi_key_strategies';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
}
