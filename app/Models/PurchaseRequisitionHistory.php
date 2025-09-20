<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionHistory extends Model
{
    use HasFactory;

    protected $table = 'purchase_requisition_history';

    protected $fillable = [
        'purchase_requisition_id',
        'user_id',
        'action',
        'old_status',
        'new_status',
        'description',
    ];

    // Relationships
    public function purchaseRequisition()
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeStatusChanges($query)
    {
        return $query->whereNotNull('old_status')->whereNotNull('new_status');
    }
}