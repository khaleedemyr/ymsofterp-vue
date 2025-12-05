<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenancePurchaseOrderInvoice extends Model
{
    protected $table = 'maintenance_purchase_order_invoices';

    protected $fillable = [
        'po_id',
        'invoice_number',
        'invoice_file_path',
        'invoice_date',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(MaintenancePurchaseOrder::class, 'po_id');
    }
} 