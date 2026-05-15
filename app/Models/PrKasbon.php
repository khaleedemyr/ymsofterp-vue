<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrKasbon extends Model
{
    protected $table = 'pr_kasbons';

    protected $fillable = [
        'purchase_requisition_id',
        'pr_number',
        'outlet_id',
        'division_id',
        'employee_user_id',
        'total_amount',
        'termin_total',
        'installment_amount',
        'paid_installments',
        'status',
        'approved_at',
        'last_installment_at',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'termin_total' => 'integer',
        'paid_installments' => 'integer',
        'approved_at' => 'datetime',
        'last_installment_at' => 'datetime',
    ];

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }
}
