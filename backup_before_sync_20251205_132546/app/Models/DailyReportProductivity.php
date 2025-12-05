<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReportProductivity extends Model
{
    use HasFactory;

    protected $table = 'daily_report_productivity';

    protected $fillable = [
        'daily_report_id',
        'product_knowledge_test',
        'sos_hospitality_role_play',
        'employee_daily_coaching',
        'others_activity',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class);
    }
}
