<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaBroadcastRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'phone_normalized',
        'wa_id',
        'member_apps_member_id',
        'omni_contact_id',
        'display_name',
        'source',
        'status',
        'skip_reason',
        'meta_message_id',
        'error_code',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WaBroadcastCampaign::class, 'campaign_id');
    }
}
