<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaBroadcastCampaign extends Model
{
    protected $fillable = [
        'name',
        'status',
        'message_type',
        'template_name',
        'template_language',
        'template_body_params',
        'session_text',
        'filter_definition',
        'recipient_count_estimated',
        'recipient_count_total',
        'recipient_count_sent',
        'recipient_count_failed',
        'recipient_count_skipped',
        'daily_cap',
        'scheduled_at',
        'started_at',
        'finished_at',
        'created_by_user_id',
        'phone_number_id',
        'last_error',
    ];

    protected $casts = [
        'template_body_params' => 'array',
        'filter_definition' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(WaBroadcastRecipient::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
