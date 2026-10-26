<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesLeadOutletTarget extends Model
{
    protected $fillable = [
        'outlet_id',
        'year',
        'month',
        'budget_amount',
    ];

    protected $casts = [
        'budget_amount' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }
}
