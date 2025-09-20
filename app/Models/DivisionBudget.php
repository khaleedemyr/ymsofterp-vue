<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DivisionBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'division',
        'year',
        'total_budget',
        'used_budget',
        'remaining_budget',
    ];

    protected $casts = [
        'total_budget' => 'decimal:2',
        'used_budget' => 'decimal:2',
        'remaining_budget' => 'decimal:2',
    ];

    // Scopes
    public function scopeByDivision($query, $division)
    {
        return $query->where('division', $division);
    }

    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeCurrentYear($query)
    {
        return $query->where('year', date('Y'));
    }

    // Accessors
    public function getFormattedTotalBudgetAttribute()
    {
        return 'Rp. ' . number_format($this->total_budget, 0, ',', '.');
    }

    public function getFormattedUsedBudgetAttribute()
    {
        return 'Rp. ' . number_format($this->used_budget, 0, ',', '.');
    }

    public function getFormattedRemainingBudgetAttribute()
    {
        return 'Rp. ' . number_format($this->remaining_budget, 0, ',', '.');
    }

    public function getUsagePercentageAttribute()
    {
        if ($this->total_budget == 0) {
            return 0;
        }
        
        return round(($this->used_budget / $this->total_budget) * 100, 2);
    }

    public function getBudgetStatusAttribute()
    {
        $percentage = $this->usage_percentage;
        
        if ($percentage >= 90) {
            return 'critical';
        } elseif ($percentage >= 75) {
            return 'warning';
        } elseif ($percentage >= 50) {
            return 'moderate';
        } else {
            return 'good';
        }
    }

    public function getBudgetStatusClassAttribute()
    {
        return match($this->budget_status) {
            'critical' => 'bg-red-100 text-red-800',
            'warning' => 'bg-yellow-100 text-yellow-800',
            'moderate' => 'bg-blue-100 text-blue-800',
            'good' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // Methods
    public function updateUsedBudget()
    {
        $usedBudget = PurchaseRequisition::where('division', $this->division)
            ->whereYear('created_at', $this->year)
            ->whereIn('status', ['APPROVED', 'PROCESSED', 'COMPLETED'])
            ->sum('amount');
        
        $this->used_budget = $usedBudget;
        $this->remaining_budget = $this->total_budget - $usedBudget;
        $this->save();
        
        return $this;
    }

    public function canAfford($amount)
    {
        return $this->remaining_budget >= $amount;
    }
}