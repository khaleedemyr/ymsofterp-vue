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
        'budget_type',
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

    public function outletBudgets()
    {
        return $this->hasMany(PurchaseRequisitionOutletBudget::class, 'category_id');
    }

    // Scopes
    public function scopeByDivision($query, $division)
    {
        return $query->where('division', $division);
    }

    public function scopeByBudgetType($query, $budgetType)
    {
        return $query->where('budget_type', $budgetType);
    }

    // Accessors
    public function getFormattedBudgetLimitAttribute()
    {
        return 'Rp. ' . number_format($this->budget_limit, 0, ',', '.');
    }

    public function getBudgetTypeLabelAttribute()
    {
        return match($this->budget_type) {
            'GLOBAL' => 'Global (Semua Outlet)',
            'PER_OUTLET' => 'Per Outlet',
            default => 'Unknown',
        };
    }

    // Methods
    public function isGlobalBudget()
    {
        return $this->budget_type === 'GLOBAL';
    }

    public function isPerOutletBudget()
    {
        return $this->budget_type === 'PER_OUTLET';
    }
}