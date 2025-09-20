<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReportVisitTable extends Model
{
    use HasFactory;

    protected $table = 'daily_report_visit_tables';

    protected $fillable = [
        'daily_report_id',
        'guest_name',
        'table_no',
        'no_of_pax',
        'guest_experience',
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
