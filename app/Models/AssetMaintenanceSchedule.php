<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetMaintenanceSchedule extends Model
{
    protected $table = 'asset_maintenance_schedules';
    
    protected $fillable = [
        'asset_id',
        'maintenance_type',
        'frequency',
        'next_maintenance_date',
        'last_maintenance_date',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'next_maintenance_date' => 'date',
        'last_maintenance_date' => 'date',
        'is_active' => 'boolean',
        'asset_id' => 'integer',
    ];

    /**
     * Get the asset for this maintenance schedule
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * Get all maintenance records for this schedule
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class, 'maintenance_schedule_id');
    }

    /**
     * Check if maintenance is due
     */
    public function isDue(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        return $this->next_maintenance_date <= now()->toDateString();
    }

    /**
     * Check if maintenance is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        return $this->next_maintenance_date < now()->toDateString();
    }
}

