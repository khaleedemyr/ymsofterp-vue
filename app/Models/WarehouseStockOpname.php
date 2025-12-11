<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WarehouseStockOpname extends Model
{
    use HasFactory;

    protected $table = 'warehouse_stock_opnames';

    protected $fillable = [
        'opname_number',
        'warehouse_id',
        'warehouse_division_id',
        'opname_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'opname_date' => 'date',
    ];

    // Relationships
    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }

    public function warehouseDivision()
    {
        return $this->belongsTo(\App\Models\WarehouseDivision::class, 'warehouse_division_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(WarehouseStockOpnameItem::class, 'stock_opname_id');
    }

    public function approvalFlows()
    {
        return $this->hasMany(WarehouseStockOpnameApprovalFlow::class, 'stock_opname_id')->orderBy('approval_level');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'DRAFT');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'SUBMITTED');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }

    // Methods
    public function isDraft()
    {
        return $this->status === 'DRAFT';
    }

    public function isSubmitted()
    {
        return $this->status === 'SUBMITTED';
    }

    public function isApproved()
    {
        return $this->status === 'APPROVED';
    }

    public function isCompleted()
    {
        return $this->status === 'COMPLETED';
    }

    public function isRejected()
    {
        return $this->status === 'REJECTED';
    }

    public function canBeEdited()
    {
        return $this->status === 'DRAFT';
    }

    public function canBeSubmitted()
    {
        return $this->status === 'DRAFT' && $this->items()->count() > 0;
    }

    public function canBeProcessed()
    {
        return $this->status === 'APPROVED';
    }
}

