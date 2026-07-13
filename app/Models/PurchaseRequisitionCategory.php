<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
        'active',
        'show_on_retail',
    ];

    protected $casts = [
        'budget_limit' => 'decimal:2',
        'active' => 'boolean',
        'show_on_retail' => 'boolean',
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

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function scopeShowOnRetail($query)
    {
        if (! Schema::hasColumn((new static)->getTable(), 'show_on_retail')) {
            return $query;
        }

        return $query->where('show_on_retail', 1);
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