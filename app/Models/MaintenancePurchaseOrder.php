<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenancePurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'task_id',
        'supplier_id',
        'status',
        'total_amount',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'purchasing_manager_approval',
        'purchasing_manager_approval_by',
        'purchasing_manager_approval_date',
        'purchasing_manager_approval_notes',
        'gm_finance_approval',
        'gm_finance_approval_by',
        'gm_finance_approval_date',
        'gm_finance_approval_notes',
        'coo_approval',
        'coo_approval_by',
        'coo_approval_date',
        'coo_approval_notes',
        'ceo_approval',
        'ceo_approval_by',
        'ceo_approval_date',
        'ceo_approval_notes',
        'invoice_number',
        'invoice_date',
        'invoice_file_path',
        'receive_date',
        'receive_photos',
        'receive_notes',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'purchasing_manager_approval_date' => 'datetime',
        'gm_finance_approval_date' => 'datetime',
        'coo_approval_date' => 'datetime',
        'ceo_approval_date' => 'datetime',
        'invoice_date' => 'date',
        'receive_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function maintenanceTask()
    {
        return $this->belongsTo(MaintenanceTask::class, 'task_id');
    }

    public function items()
    {
        return $this->hasMany(MaintenancePurchaseOrderItem::class, 'po_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function gmFinanceApprovedBy()
    {
        return $this->belongsTo(User::class, 'gm_finance_approval_by');
    }

    public function managingDirectorApprovedBy()
    {
        return $this->belongsTo(User::class, 'managing_director_approval_by');
    }

    public function presidentDirectorApprovedBy()
    {
        return $this->belongsTo(User::class, 'president_director_approval_by');
    }

    public function invoices()
    {
        return $this->hasMany(\App\Models\MaintenancePurchaseOrderInvoice::class, 'po_id');
    }

    public function payments()
    {
        return $this->hasMany(MaintenancePOPayment::class, 'po_id');
    }
} 