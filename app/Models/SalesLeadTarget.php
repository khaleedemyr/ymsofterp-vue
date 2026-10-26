<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesLeadTarget extends Model
{
    protected $fillable = [
        'sales_user_id',
        'segment_1',
        'year',
        'month',
        'target_amount',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer',
    ];

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }
}
