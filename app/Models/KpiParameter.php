<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KpiParameter extends Model
{
    protected $table = 'kpi_parameters';

    protected $fillable = [
        'code',
        'name',
        'source_type',
        'scope_type',
        'data_type',
        'description',
        'is_shared',
        'status',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function erpMapping(): HasOne
    {
        return $this->hasOne(KpiParameterErpMapping::class, 'kpi_parameter_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }
}
