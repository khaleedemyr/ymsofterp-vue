<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StockOpname extends Model
{
    use HasFactory;

    protected $table = 'outlet_stock_opnames';

    protected $fillable = [
        'opname_number',
        'outlet_id',
        'warehouse_outlet_id',
        'opname_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'opname_date' => 'date',
    ];

    // Relationships
    public function outlet()
    {
        return $this->belongsTo(\App\Models\Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function warehouseOutlet()
    {
        return $this->belongsTo(\App\Models\WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }

    public function approvalFlows()
    {
        return $this->hasMany(StockOpnameApprovalFlow::class, 'stock_opname_id')->orderBy('approval_level');
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

