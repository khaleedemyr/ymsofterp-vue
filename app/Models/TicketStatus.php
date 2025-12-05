<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketStatus extends Model
{
    use HasFactory;

    protected $table = 'ticket_statuses';

    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'is_final',
        'order',
        'status',
    ];

    protected $casts = [
        'is_final' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'status_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    public function scopeFinal($query)
    {
        return $query->where('is_final', 1);
    }

    public function scopeNonFinal($query)
    {
        return $query->where('is_final', 0);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    // Helper methods
    public function getTicketCount()
    {
        return $this->tickets()->count();
    }

    public function isOpen()
    {
        return $this->slug === 'open';
    }

    public function isInProgress()
    {
        return $this->slug === 'in_progress';
    }

    public function isResolved()
    {
        return $this->slug === 'resolved';
    }

    public function isClosed()
    {
        return $this->is_final;
    }
}
