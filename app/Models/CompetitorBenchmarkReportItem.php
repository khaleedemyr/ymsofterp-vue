<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorBenchmarkReportItem extends Model
{
    protected $fillable = [
        'report_id',
        'sort_order',
        'brand_restaurant_visited',
        'location',
        'visit_date',
        'product_benchmark',
        'service_benchmark',
        'pricing_benchmark',
        'operational_benchmark',
        'market_positioning_benchmark',
        'summary_report',
        'development_action_plan',
    ];

    protected $casts = [
        'visit_date' => 'date:Y-m-d',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(CompetitorBenchmarkReport::class, 'report_id');
    }
}
