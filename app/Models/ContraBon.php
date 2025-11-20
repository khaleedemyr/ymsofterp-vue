<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContraBon extends Model
{
    use HasFactory;

    protected $table = 'food_contra_bons';

    protected $fillable = [
        'number',
        'date',
        'supplier_id',
        'po_id',
        'total_amount',
        'discount_total_percent',
        'discount_total_amount',
        'notes',
        'image_path',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'finance_manager_approved_at',
        'finance_manager_approved_by',
        'finance_manager_note',
        'gm_finance_approved_at',
        'gm_finance_approved_by',
        'gm_finance_note',
        'supplier_invoice_number',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'discount_total_percent' => 'decimal:2',
        'discount_total_amount' => 'decimal:2'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrderFood::class, 'po_id');
    }

    public function retailFood()
    {
        return $this->belongsTo(RetailFood::class, 'source_id');
    }

    public function warehouseRetailFood()
    {
        return $this->belongsTo(RetailWarehouseFood::class, 'source_id');
    }

    public function items()
    {
        return $this->hasMany(ContraBonItem::class, 'contra_bon_id');
    }

    public function sources()
    {
        return $this->hasMany(ContraBonSource::class, 'contra_bon_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function financeManager()
    {
        return $this->belongsTo(User::class, 'finance_manager_approved_by');
    }

    public function gmFinance()
    {
        return $this->belongsTo(User::class, 'gm_finance_approved_by');
    }
} 