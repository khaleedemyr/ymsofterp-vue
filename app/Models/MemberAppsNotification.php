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
        'is_read' => 'boolean', // Will be cast to 0/1 in database
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
        // Use update() to ensure database is updated directly
        $updated = $this->update([
            'is_read' => 1, // Use 1 instead of true for database
            'read_at' => now(),
        ]);
        
        // Refresh to get updated values
        $this->refresh();
        
        \Log::info('MemberAppsNotification markAsRead called', [
            'notification_id' => $this->id,
            'member_id' => $this->member_id,
            'updated' => $updated,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at,
        ]);
        
        return $updated;
    }
}

