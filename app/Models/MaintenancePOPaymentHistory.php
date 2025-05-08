<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenancePOPaymentHistory extends Model
{
    protected $table = 'maintenance_po_payment_history';

    protected $fillable = [
        'po_id',
        'payment_id',
        'payment_date',
        'payment_amount',
        'payment_type',
        'payment_method',
        'payment_reference',
        'payment_proof_path',
        'notes'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2'
    ];

    public function po()
    {
        return $this->belongsTo(MaintenancePurchaseOrder::class, 'po_id');
    }

    public function payment()
    {
        return $this->belongsTo(MaintenancePOPayment::class, 'payment_id');
    }
} 