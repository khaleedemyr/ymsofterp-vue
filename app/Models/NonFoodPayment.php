<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
        'approved_finance_manager_by',
        'approved_finance_manager_at',
        'approved_gm_finance_by',
        'approved_gm_finance_at',
        'notes',
        'is_partial_payment',
        'payment_sequence'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'approved_finance_manager_at' => 'datetime',
        'approved_gm_finance_at' => 'datetime',
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

    public function financeManagerApprover()
    {
        return $this->belongsTo(User::class, 'approved_finance_manager_by');
    }

    public function gmFinanceApprover()
    {
        return $this->belongsTo(User::class, 'approved_gm_finance_by');
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
            'pending_finance_manager' => 'bg-orange-100 text-orange-800',
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
        // Superadmin can approve at any level
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        
        if ($isSuperadmin) {
            return $this->status === 'pending';
        }
        
        // Finance Manager can approve if status is pending (no approval yet)
        if ($user->id_jabatan == 160 && $user->status == 'A') {
            return $this->status === 'pending';
        }
        
        // GM Finance can approve if Finance Manager already approved
        // Status should still be 'pending' (not updated by Finance Manager)
        if ($user->id_jabatan == 316 && $user->status == 'A') {
            return $this->status === 'pending' && $this->approved_finance_manager_by !== null;
        }
        
        return false;
    }
    
    public function getCurrentApprovalLevel()
    {
        if ($this->approved_gm_finance_by !== null) {
            return 'approved'; // Fully approved
        }
        if ($this->approved_finance_manager_by !== null) {
            return 'pending_gm_finance'; // Waiting for GM Finance
        }
        return 'pending'; // Waiting for GM Finance (Finance Manager already approved)
    }

    public function canBeRejected()
    {
        // Can be rejected if still in pending state (any level)
        return $this->status === 'pending';
    }

    public function canBePaid()
    {
        // Can be paid if status is approved
        // For backward compatibility: if approved_gm_finance_by is null but status is approved,
        // it means it's old data that was approved before 2-level approval was implemented
        return $this->status === 'approved';
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    public function canBeEdited()
    {
        // Can only edit if still in initial pending state
        return $this->status === 'pending';
    }

    public function canBeDeleted()
    {
        // Can only delete if still in initial pending state
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
