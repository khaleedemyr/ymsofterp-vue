<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutletRejection extends Model
{
    use HasFactory;

    protected $table = 'outlet_rejections';

    protected $fillable = [
        'number',
        'rejection_date',
        'outlet_id',
        'warehouse_id',
        'delivery_order_id',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'completed_by',
        'completed_at',
        'assistant_ssd_manager_approved_at',
        'assistant_ssd_manager_approved_by',
        'assistant_ssd_manager_note',
        'ssd_manager_approved_at',
        'ssd_manager_approved_by',
        'ssd_manager_note'
    ];

    protected $casts = [
        'rejection_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'assistant_ssd_manager_approved_at' => 'datetime',
        'ssd_manager_approved_at' => 'datetime',
    ];

    // Relationships
    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function assistantSsdManager()
    {
        return $this->belongsTo(User::class, 'assistant_ssd_manager_approved_by');
    }

    public function ssdManager()
    {
        return $this->belongsTo(User::class, 'ssd_manager_approved_by');
    }

    public function items()
    {
        return $this->hasMany(OutletRejectionItem::class);
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeByOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('rejection_date', [$startDate, $endDate]);
    }

    // Methods
    public function canBeSubmitted()
    {
        return $this->status === 'draft' && $this->items()->count() > 0;
    }

    public function canBeApproved()
    {
        return $this->status === 'submitted';
    }

    public function canBeCompleted()
    {
        return $this->status === 'approved';
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['draft', 'submitted']);
    }

    public function submit()
    {
        if ($this->canBeSubmitted()) {
            $this->update(['status' => 'submitted']);
            return true;
        }
        return false;
    }

    public function approve($userId)
    {
        if ($this->canBeApproved()) {
            $this->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now()
            ]);
            return true;
        }
        return false;
    }

    public function complete($userId)
    {
        if ($this->canBeCompleted()) {
            $this->update([
                'status' => 'completed',
                'completed_by' => $userId,
                'completed_at' => now()
            ]);
            return true;
        }
        return false;
    }

    public function cancel()
    {
        if ($this->canBeCancelled()) {
            $this->update(['status' => 'cancelled']);
            return true;
        }
        return false;
    }

    // Static methods
    public static function generateNumber()
    {
        $lastRejection = self::whereDate('created_at', today())->latest()->first();
        $sequence = $lastRejection ? (int)substr($lastRejection->number, -4) + 1 : 1;
        
        return 'ORJ-' . date('Ymd') . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getTotalRejectedQty()
    {
        return $this->items()->sum('qty_rejected');
    }

    public function getTotalReceivedQty()
    {
        return $this->items()->sum('qty_received');
    }

    public function getTotalValue()
    {
        return $this->items()->sum(\DB::raw('qty_received * mac_cost'));
    }
}
