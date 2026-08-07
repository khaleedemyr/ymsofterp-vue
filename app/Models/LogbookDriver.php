<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogbookDriver extends Model
{
    protected $table = 'logbook_drivers';

    protected $fillable = [
        'number',
        'log_date',
        'outlet_id',
        'outlet_name',
        'driver_id',
        'driver_name',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'log_date' => 'date:Y-m-d',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(LogbookDriverItem::class, 'logbook_driver_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
}
