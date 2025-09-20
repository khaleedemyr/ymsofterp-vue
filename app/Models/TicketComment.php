<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    use HasFactory;

    protected $table = 'ticket_comments';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'comment',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class, 'comment_id');
    }

    // Scopes
    public function scopePublic($query)
    {
        return $query->where('is_internal', 0);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', 1);
    }

    // Helper methods
    public function getTimeAgo()
    {
        return $this->created_at->diffForHumans();
    }

    public function isInternal()
    {
        return $this->is_internal;
    }

    public function isPublic()
    {
        return !$this->is_internal;
    }
}
