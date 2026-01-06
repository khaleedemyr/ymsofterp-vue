<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $table = 'assets';
    
    protected $fillable = [
        'asset_code',
        'name',
        'category_id',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'supplier',
        'current_outlet_id',
        'status',
        'photos',
        'description',
        'qr_code',
        'qr_code_image',
        'useful_life',
        'warranty_expiry_date',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'photos' => 'array',
        'warranty_expiry_date' => 'date',
        'useful_life' => 'integer',
        'category_id' => 'integer',
        'current_outlet_id' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * Get the category that owns the asset
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    /**
     * Get the outlet where the asset is currently located
     */
    public function currentOutlet(): BelongsTo
    {
        return $this->belongsTo(DataOutlet::class, 'current_outlet_id', 'id_outlet');
    }

    /**
     * Get the user who created the asset
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all transfers for this asset
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class, 'asset_id');
    }

    /**
     * Get all maintenance schedules for this asset
     */
    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(AssetMaintenanceSchedule::class, 'asset_id');
    }

    /**
     * Get all maintenances for this asset
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class, 'asset_id');
    }

    /**
     * Get all disposals for this asset
     */
    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class, 'asset_id');
    }

    /**
     * Get all documents for this asset
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocument::class, 'asset_id');
    }

    /**
     * Get the depreciation record for this asset
     */
    public function depreciation(): BelongsTo
    {
        return $this->belongsTo(AssetDepreciation::class, 'id', 'asset_id');
    }

    /**
     * Get all depreciation history for this asset
     */
    public function depreciationHistory(): HasMany
    {
        return $this->hasMany(AssetDepreciationHistory::class, 'asset_id');
    }

    /**
     * Get active maintenance schedules
     */
    public function activeMaintenanceSchedules(): HasMany
    {
        return $this->hasMany(AssetMaintenanceSchedule::class, 'asset_id')
            ->where('is_active', true);
    }

    /**
     * Get pending transfers
     */
    public function pendingTransfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class, 'asset_id')
            ->where('status', 'Pending');
    }
}

