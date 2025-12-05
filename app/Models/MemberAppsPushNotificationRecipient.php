<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsPushNotificationRecipient extends Model
{
    protected $table = 'member_apps_push_notification_recipients';
    
    protected $fillable = [
        'notification_id',
        'member_id',
        'device_token_id',
        'status',
        'fcm_message_id',
        'error_message',
        'delivered_at',
        'opened_at'
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(MemberAppsPushNotification::class, 'notification_id');
    }

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }

    public function deviceToken()
    {
        return $this->belongsTo(MemberAppsDeviceToken::class, 'device_token_id');
    }
}

