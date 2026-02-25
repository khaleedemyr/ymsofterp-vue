<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PurchaseRequisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'pr_number',
        'date',
        'warehouse_id',
        'requested_by',
        'department',
        'division_id',
        'category_id',
        'outlet_id',
        'ticket_id',
        'title',
        'description',
        'amount',
        'currency',
        'status',
        'priority',
        'notes',
        'kasbon_termin',
        'mode',
        'created_by',
        'updated_by',
        'approved_ssd_by',
        'approved_ssd_at',
        'approved_cc_by',
        'approved_cc_at',
        'is_held',
        'held_at',
        'held_by',
        'hold_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'kasbon_termin' => 'integer',
        'approved_ssd_at' => 'datetime',
        'approved_cc_at' => 'datetime',
        'is_held' => 'boolean',
        'held_at' => 'datetime',
    ];

    // Relationships
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function division()
    {
        return $this->belongsTo(Divisi::class, 'division_id');
    }

    public function category()
    {
        return $this->belongsTo(PurchaseRequisitionCategory::class, 'category_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedSsdBy()
    {
        return $this->belongsTo(User::class, 'approved_ssd_by');
    }

    public function approvedCcBy()
    {
        return $this->belongsTo(User::class, 'approved_cc_by');
    }

    public function heldBy()
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function attachments()
    {
        return $this->hasMany(PurchaseRequisitionAttachment::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(\App\Models\PurchaseOrderOps::class, 'source_id');
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class, 'purchase_requisition_id');
    }

    public function comments()
    {
        return $this->hasMany(PurchaseRequisitionComment::class);
    }

    public function history()
    {
        return $this->hasMany(PurchaseRequisitionHistory::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequisitionItem::class);
    }

    public function approvalFlows()
    {
        return $this->hasMany(PurchaseRequisitionApprovalFlow::class)->orderedByLevel();
    }

    // Scopes
    public function scopeByDivision($query, $divisionId)
    {
        return $query->where('division_id', $divisionId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    public function scopeByTicket($query, $ticketId)
    {
        return $query->where('ticket_id', $ticketId);
    }

    // Accessors
    public function getFormattedAmountAttribute()
    {
        return 'Rp. ' . number_format($this->amount, 0, ',', '.');
    }

    public function getStatusBadgeClassAttribute()
    {
        return match($this->status) {
            'DRAFT' => 'bg-gray-100 text-gray-800',
            'SUBMITTED' => 'bg-blue-100 text-blue-800',
            'APPROVED' => 'bg-green-100 text-green-800',
            'REJECTED' => 'bg-red-100 text-red-800',
            'PROCESSED' => 'bg-yellow-100 text-yellow-800',
            'COMPLETED' => 'bg-purple-100 text-purple-800',
            'PAID' => 'bg-emerald-100 text-emerald-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getPriorityBadgeClassAttribute()
    {
        return match($this->priority) {
            'LOW' => 'bg-green-100 text-green-800',
            'MEDIUM' => 'bg-yellow-100 text-yellow-800',
            'HIGH' => 'bg-orange-100 text-orange-800',
            'URGENT' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // Methods
    public function generateRequisitionNumber()
    {
        $year = date('Y');
        $month = date('m');
        
        // Get the last requisition number for this year and month
        $lastRequisition = self::where('pr_number', 'like', "PR-{$year}{$month}-%")
            ->orderBy('pr_number', 'desc')
            ->first();
        
        if ($lastRequisition) {
            $lastNumber = (int) substr($lastRequisition->pr_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return "PR-{$year}{$month}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function canBeApproved()
    {
        return in_array($this->status, ['DRAFT', 'SUBMITTED']);
    }

    public function canBeRejected()
    {
        return in_array($this->status, ['DRAFT', 'SUBMITTED']);
    }

    public function canBeProcessed()
    {
        return $this->status === 'APPROVED';
    }

    public function canBeCompleted()
    {
        return $this->status === 'PROCESSED';
    }

    public function isOnHold()
    {
        return $this->is_held === true;
    }

    public function canCreatePO()
    {
        return !$this->isOnHold() && in_array($this->status, ['APPROVED', 'PROCESSED']);
    }

    public function canCreatePayment()
    {
        return !$this->isOnHold();
    }
}