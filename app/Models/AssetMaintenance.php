<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenance extends Model
{
    protected $table = 'asset_maintenances';
    
    protected $fillable = [
        'asset_id',
        'maintenance_schedule_id',
        'maintenance_date',
        'maintenance_type',
        'cost',
        'vendor',
        'notes',
        'status',
        'performed_by',
        'completed_at',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'cost' => 'decimal:2',
        'completed_at' => 'datetime',
        'asset_id' => 'integer',
        'maintenance_schedule_id' => 'integer',
        'performed_by' => 'integer',
    ];

    /**
     * Get the asset for this maintenance
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * Get the maintenance schedule (if this maintenance was from a schedule)
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    /**
     * Get the user who performed the maintenance
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Check if maintenance is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }
}

