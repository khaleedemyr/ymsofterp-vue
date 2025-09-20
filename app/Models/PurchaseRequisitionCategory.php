<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'division',
        'subcategory',
        'budget_limit',
        'description',
    ];

    protected $casts = [
        'budget_limit' => 'decimal:2',
    ];

    // Relationships
    public function purchaseRequisitions()
    {
        return $this->hasMany(PurchaseRequisition::class, 'category_id');
    }

    // Scopes
    public function scopeByDivision($query, $division)
    {
        return $query->where('division', $division);
    }

    // Accessors
    public function getFormattedBudgetLimitAttribute()
    {
        return 'Rp. ' . number_format($this->budget_limit, 0, ',', '.');
    }
}