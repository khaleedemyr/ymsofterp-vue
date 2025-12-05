<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketPriority extends Model
{
    use HasFactory;

    protected $table = 'ticket_priorities';

    protected $fillable = [
        'name',
        'level',
        'max_days',
        'color',
        'description',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'priority_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    // Helper methods
    public function getTicketCount()
    {
        return $this->tickets()->count();
    }

    public function getOpenTicketCount()
    {
        return $this->tickets()->open()->count();
    }

    public function isHigh()
    {
        return $this->level >= 3;
    }

    public function isCritical()
    {
        return $this->level >= 4;
    }
}
