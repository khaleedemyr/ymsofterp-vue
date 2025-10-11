<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequisitionOutletBudget extends Model
{
    use HasFactory;

    protected $table = 'purchase_requisition_outlet_budgets';

    protected $fillable = [
        'category_id',
        'outlet_id',
        'allocated_budget',
        'used_budget',
    ];

    protected $casts = [
        'allocated_budget' => 'decimal:2',
        'used_budget' => 'decimal:2',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(PurchaseRequisitionCategory::class, 'category_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id', 'id_outlet');
    }

    // Scopes
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }

    // Accessors
    public function getFormattedAllocatedBudgetAttribute()
    {
        return 'Rp. ' . number_format($this->allocated_budget, 0, ',', '.');
    }

    public function getFormattedUsedBudgetAttribute()
    {
        return 'Rp. ' . number_format($this->used_budget, 0, ',', '.');
    }

    public function getRemainingBudgetAttribute()
    {
        return $this->allocated_budget - $this->used_budget;
    }

    public function getFormattedRemainingBudgetAttribute()
    {
        return 'Rp. ' . number_format($this->remaining_budget, 0, ',', '.');
    }

    public function getUsagePercentageAttribute()
    {
        if ($this->allocated_budget <= 0) {
            return 0;
        }
        return ($this->used_budget / $this->allocated_budget) * 100;
    }

    public function getBudgetStatusAttribute()
    {
        $percentage = $this->usage_percentage;
        
        if ($percentage >= 100) {
            return 'exceeded';
        } elseif ($percentage >= 90) {
            return 'critical';
        } elseif ($percentage >= 75) {
            return 'warning';
        } else {
            return 'safe';
        }
    }

    public function getBudgetStatusLabelAttribute()
    {
        return match($this->budget_status) {
            'exceeded' => 'Budget Exceeded',
            'critical' => 'Critical (90%+)',
            'warning' => 'Warning (75%+)',
            'safe' => 'Safe',
            default => 'Unknown',
        };
    }

    public function getBudgetStatusClassAttribute()
    {
        return match($this->budget_status) {
            'exceeded' => 'bg-red-100 text-red-800',
            'critical' => 'bg-red-100 text-red-800',
            'warning' => 'bg-yellow-100 text-yellow-800',
            'safe' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // Methods
    public function updateUsedBudget($year = null, $month = null)
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');

        $usedAmount = PurchaseRequisition::where('category_id', $this->category_id)
            ->where('outlet_id', $this->outlet_id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
            ->sum('amount');

        $this->update(['used_budget' => $usedAmount]);
        
        return $usedAmount;
    }

    public function canAllocate($amount)
    {
        return ($this->used_budget + $amount) <= $this->allocated_budget;
    }

    public function getRemainingAfterAllocation($amount)
    {
        return $this->allocated_budget - ($this->used_budget + $amount);
    }
}
