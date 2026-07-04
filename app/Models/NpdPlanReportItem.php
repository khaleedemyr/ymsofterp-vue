<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NpdPlanReportItem extends Model
{
    protected $fillable = [
        'report_id',
        'sort_order',
        'product_name',
        'category',
        'development_date',
        'purpose',
        'proposed_launch_date',
        'proposed_launch_area_outlet',
        'fb_cost',
        'selling_price',
    ];

    protected $casts = [
        'development_date' => 'date:Y-m-d',
        'proposed_launch_date' => 'date:Y-m-d',
        'fb_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(NpdPlanReport::class, 'report_id');
    }
}
