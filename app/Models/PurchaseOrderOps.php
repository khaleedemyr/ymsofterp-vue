<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderOps extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_ops';

    protected $fillable = [
        'number',
        'date',
        'supplier_id',
        'status',
        'created_by',
        'notes',
        'arrival_date',
        'purchasing_manager_approved_at',
        'purchasing_manager_approved_by',
        'purchasing_manager_note',
        'gm_finance_approved_at',
        'gm_finance_approved_by',
        'gm_finance_note',
        'ppn_enabled',
        'ppn_amount',
        'subtotal',
        'discount_total_percent',
        'discount_total_amount',
        'grand_total',
        'source_type',
        'source_id',
        'printed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'arrival_date' => 'date',
        'ppn_enabled' => 'boolean',
        'ppn_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_total_percent' => 'decimal:2',
        'discount_total_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'purchasing_manager_approved_at' => 'datetime',
        'gm_finance_approved_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderOpsItem::class, 'purchase_order_ops_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchasing_manager()
    {
        return $this->belongsTo(User::class, 'purchasing_manager_approved_by');
    }

    public function gm_finance()
    {
        return $this->belongsTo(User::class, 'gm_finance_approved_by');
    }

    public function source_pr()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'source_id');
    }

    public function purchase_requisition()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'source_id');
    }

    public function approvalFlows()
    {
        return $this->hasMany(PurchaseOrderOpsApprovalFlow::class, 'purchase_order_ops_id');
    }

    public function attachments()
    {
        return $this->hasMany(PurchaseOrderOpsAttachment::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class, 'purchase_order_id');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return 'Rp. ' . number_format($this->grand_total, 0, ',', '.');
    }

    public function getStatusBadgeClassAttribute()
    {
        return match($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'approved' => 'bg-green-100 text-green-800',
            'received' => 'bg-blue-100 text-blue-800',
            'rejected' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // Methods
    public function canBeApproved()
    {
        return in_array($this->status, ['draft']);
    }

    public function canBeRejected()
    {
        return in_array($this->status, ['draft', 'approved']);
    }

    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'approved']);
    }

    public function canBeDeleted()
    {
        return in_array($this->status, ['draft', 'approved']);
    }

    public function generateNumber()
    {
        $prefix = 'POO';
        $date = date('ym', strtotime(now()));
        $lastNumber = self::where('number', 'like', $prefix . $date . '%')
            ->orderBy('number', 'desc')
            ->value('number');

        if ($lastNumber) {
            $lastSequence = intval(substr($lastNumber, -4));
            $sequence = $lastSequence + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
