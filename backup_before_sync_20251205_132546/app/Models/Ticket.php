<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'tickets';

    protected $fillable = [
        'ticket_number',
        'title',
        'description',
        'category_id',
        'priority_id',
        'status_id',
        'divisi_id',
        'outlet_id',
        'created_by',
        'due_date',
        'resolved_at',
        'closed_at',
        'source',
        'source_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function priority()
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function status()
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class);
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function history()
    {
        return $this->hasMany(TicketHistory::class);
    }

    public function assignments()
    {
        return $this->hasMany(TicketAssignment::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'ticket_assignments', 'ticket_id', 'user_id')
                    ->withPivot(['assigned_by', 'assigned_at', 'is_primary'])
                    ->withTimestamps();
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->whereHas('status', function($q) {
            $q->where('slug', 'open');
        });
    }

    public function scopeInProgress($query)
    {
        return $query->whereHas('status', function($q) {
            $q->where('slug', 'in_progress');
        });
    }

    public function scopeResolved($query)
    {
        return $query->whereHas('status', function($q) {
            $q->where('slug', 'resolved');
        });
    }

    public function scopeClosed($query)
    {
        return $query->whereHas('status', function($q) {
            $q->where('is_final', 1);
        });
    }

    public function scopeByDivisi($query, $divisiId)
    {
        return $query->where('divisi_id', $divisiId);
    }

    public function scopeByOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    public function scopeByPriority($query, $priorityId)
    {
        return $query->where('priority_id', $priorityId);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeFromDailyReport($query)
    {
        return $query->where('source', 'daily_report');
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    // Helper methods
    public function isOpen()
    {
        return $this->status && $this->status->slug === 'open';
    }

    public function isInProgress()
    {
        return $this->status && $this->status->slug === 'in_progress';
    }

    public function isResolved()
    {
        return $this->status && $this->status->slug === 'resolved';
    }

    public function isClosed()
    {
        return $this->status && $this->status->is_final;
    }

    public function isOverdue()
    {
        return $this->due_date && $this->due_date->isPast() && !$this->isClosed();
    }

    public function getTimeToResolve()
    {
        if ($this->resolved_at && $this->created_at) {
            return $this->created_at->diffForHumans($this->resolved_at, true);
        }
        return null;
    }

    public function getTimeToClose()
    {
        if ($this->closed_at && $this->created_at) {
            return $this->created_at->diffForHumans($this->closed_at, true);
        }
        return null;
    }

    public function getPrimaryAssignee()
    {
        return $this->assignedUsers()->wherePivot('is_primary', 1)->first();
    }

    public function getLastComment()
    {
        return $this->comments()->latest()->first();
    }

    public function getCommentCount()
    {
        return $this->comments()->count();
    }

    public function getAttachmentCount()
    {
        return $this->attachments()->count();
    }

    // Generate ticket number
    public static function generateTicketNumber()
    {
        $prefix = 'TK-' . date('Ym') . '-';
        $last = self::where('ticket_number', 'like', $prefix . '%')
                    ->orderByDesc('ticket_number')
                    ->value('ticket_number');

        if ($last) {
            $lastNumber = (int)substr($last, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
