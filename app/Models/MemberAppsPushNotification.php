<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsPushNotification extends Model
{
    protected $table = 'member_apps_push_notifications';
    
    protected $fillable = [
        'title',
        'message',
        'notification_type',
        'target_type',
        'target_member_ids',
        'target_filter_criteria',
        'image_url',
        'action_url',
        'data',
        'sent_count',
        'delivered_count',
        'opened_count',
        'scheduled_at',
        'sent_at',
        'created_by'
    ];

    protected $casts = [
        'target_member_ids' => 'array',
        'target_filter_criteria' => 'array',
        'data' => 'array',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'opened_count' => 'integer',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function recipients()
    {
        return $this->hasMany(MemberAppsPushNotificationRecipient::class, 'notification_id');
    }
}

