<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsNotification extends Model
{
    protected $table = 'member_apps_notifications';
    
    protected $fillable = [
        'member_id',
        'type',
        'title',
        'message',
        'url',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}

