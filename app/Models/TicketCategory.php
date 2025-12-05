<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketCategory extends Model
{
    use HasFactory;

    protected $table = 'ticket_categories';

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'A');
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
}
