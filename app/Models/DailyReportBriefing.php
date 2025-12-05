<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReportBriefing extends Model
{
    use HasFactory;

    protected $table = 'daily_report_briefings';

    protected $fillable = [
        'daily_report_id',
        'briefing_type',
        'time_of_conduct',
        'participant',
        'outlet',
        'service_in_charge',
        'bar_in_charge',
        'kitchen_in_charge',
        'so_product',
        'product_up_selling',
        'commodity_issue',
        'oe_issue',
        'guest_reservation_pax',
        'daily_revenue_target',
        'promotion_program_campaign',
        'guest_comment_target',
        'trip_advisor_target',
        'other_preparation',
    ];

    protected $casts = [
        'time_of_conduct' => 'datetime:H:i',
        'daily_revenue_target' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class);
    }
}
