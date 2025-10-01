<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionCPA extends Model
{
    protected $table = 'inspection_cpas';
    
    protected $fillable = [
        'inspection_id',
        'inspection_detail_id',
        'action_plan',
        'responsible_person',
        'due_date',
        'notes',
        'documentation_paths',
        'status',
        'completion_date',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completion_date' => 'date',
        'documentation_paths' => 'array', // Cast JSON to array
    ];

    // Relationships
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function inspectionDetail(): BelongsTo
    {
        return $this->belongsTo(InspectionDetail::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('status', '!=', 'Completed');
    }

    public function scopeDueSoon($query, $days = 7)
    {
        return $query->whereBetween('due_date', [now(), now()->addDays($days)])
                    ->where('status', '!=', 'Completed');
    }
}
