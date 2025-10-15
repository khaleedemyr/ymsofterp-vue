<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonFoodPayment extends Model
{
    use HasFactory;

    protected $table = 'non_food_payments';

    protected $fillable = [
        'payment_number',
        'purchase_order_ops_id',
        'purchase_requisition_id',
        'supplier_id',
        'amount',
        'payment_method',
        'payment_date',
        'due_date',
        'status',
        'description',
        'reference_number',
        'created_by',
        'approved_by',
        'approved_at',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function purchaseOrderOps()
    {
        return $this->belongsTo(PurchaseOrderOps::class, 'purchase_order_ops_id');
    }

    public function purchaseRequisition()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments()
    {
        return $this->hasMany(NonFoodPaymentAttachment::class);
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
        return $query->whereBetween('payment_date', [$from, $to]);
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return 'Rp. ' . number_format($this->amount, 0, ',', '.');
    }

    public function getStatusBadgeClassAttribute()
    {
        return match($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-green-100 text-green-800',
            'paid' => 'bg-blue-100 text-blue-800',
            'rejected' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getPaymentMethodBadgeClassAttribute()
    {
        return match($this->payment_method) {
            'cash' => 'bg-green-100 text-green-800',
            'transfer' => 'bg-blue-100 text-blue-800',
            'check' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // Methods
    public function canBeApproved()
    {
        return $this->status === 'pending';
    }

    public function canBeRejected()
    {
        return $this->status === 'pending';
    }

    public function canBePaid()
    {
        return $this->status === 'approved';
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    public function canBeEdited()
    {
        return $this->status === 'pending';
    }

    public function canBeDeleted()
    {
        return $this->status === 'pending';
    }

    public function generatePaymentNumber()
    {
        $prefix = 'NFP';
        $dateStr = date('Ymd');
        
        // Get the last payment number for today
        $lastPayment = self::where('payment_number', 'like', "{$prefix}-{$dateStr}-%")
            ->orderBy('payment_number', 'desc')
            ->first();
        
        if ($lastPayment) {
            $lastNumber = (int) substr($lastPayment->payment_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return "{$prefix}-{$dateStr}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
