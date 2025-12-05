<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicInspection extends Model
{
    protected $fillable = [
        'inspection_number',
        'outlet_id',
        'pic_name',
        'pic_position',
        'pic_division',
        'inspection_date',
        'status',
        'general_notes',
        'outlet_leader',
        'created_by'
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'status' => 'string'
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DynamicInspectionDetail::class);
    }
}
