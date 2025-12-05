<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAssignment extends Model
{
    use HasFactory;

    protected $table = 'ticket_assignments';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'is_primary',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_primary' => 'boolean',
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

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Scopes
    public function scopePrimary($query)
    {
        return $query->where('is_primary', 1);
    }

    public function scopeSecondary($query)
    {
        return $query->where('is_primary', 0);
    }

    // Helper methods
    public function getTimeAgo()
    {
        return $this->assigned_at->diffForHumans();
    }

    public function isPrimary()
    {
        return $this->is_primary;
    }
}
