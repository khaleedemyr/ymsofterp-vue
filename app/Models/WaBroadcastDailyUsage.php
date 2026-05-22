<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaBroadcastDailyUsage extends Model
{
    /** Tabel di migration/SQL: wa_broadcast_daily_usage (bukan plural usages) */
    protected $table = 'wa_broadcast_daily_usage';

    protected $fillable = [
        'usage_date',
        'phone_number_id',
        'sent_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];
}
