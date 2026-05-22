<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaBroadcastDailyUsage extends Model
{
    protected $fillable = [
        'usage_date',
        'phone_number_id',
        'sent_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];
}
