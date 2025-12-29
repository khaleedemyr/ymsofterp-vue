<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AIBudgetService
{
    /**
     * Get current month usage for Claude API
     */
    public function getCurrentMonthUsage()
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        
        $usage = DB::table('ai_usage_logs')
            ->where('provider', 'claude')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('cost_rupiah');
        
        return $usage ?? 0;
    }
    
    /**
     * Get budget limit from config
     */
    public function getBudgetLimit()
    {
        return config('ai.claude.budget_limit', 2000000); // Default Rp 2 juta
    }
    
    /**
     * Check if budget limit is exceeded
     */
    public function isBudgetExceeded()
    {
        $currentUsage = $this->getCurrentMonthUsage();
        $budgetLimit = $this->getBudgetLimit();
        
        return $currentUsage >= $budgetLimit;
    }
    
    /**
     * Get remaining budget
     */
    public function getRemainingBudget()
    {
        $currentUsage = $this->getCurrentMonthUsage();
        $budgetLimit = $this->getBudgetLimit();
        
        $remaining = $budgetLimit - $currentUsage;
        return max(0, $remaining);
    }
    
    /**
     * Log API usage and cost
     */
    public function logUsage($provider, $requestType, $inputTokens, $outputTokens, $costUsd, $costRupiah)
    {
        try {
            DB::table('ai_usage_logs')->insert([
                'provider' => $provider,
                'request_type' => $requestType, // 'insight' or 'qa'
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $inputTokens + $outputTokens,
                'cost_usd' => $costUsd,
                'cost_rupiah' => $costRupiah,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Clear cache untuk usage stats
            Cache::forget('ai_usage_current_month');
            Cache::forget('ai_usage_remaining_budget');
        } catch (\Exception $e) {
            Log::error('Failed to log AI usage: ' . $e->getMessage());
        }
    }
    
    /**
     * Calculate cost based on tokens
     */
    public function calculateCost($provider, $inputTokens, $outputTokens)
    {
        $pricing = config("ai.{$provider}.pricing", []);
        
        if (empty($pricing)) {
            // Default Claude 3.5 Sonnet pricing
            $inputPricePerMillion = 3.00; // USD
            $outputPricePerMillion = 15.00; // USD
        } else {
            $inputPricePerMillion = $pricing['input'] ?? 3.00;
            $outputPricePerMillion = $pricing['output'] ?? 15.00;
        }
        
        $usdRate = config('ai.usd_to_rupiah_rate', 15000); // Default 1 USD = Rp 15,000
        
        $inputCostUsd = ($inputTokens / 1000000) * $inputPricePerMillion;
        $outputCostUsd = ($outputTokens / 1000000) * $outputPricePerMillion;
        $totalCostUsd = $inputCostUsd + $outputCostUsd;
        $totalCostRupiah = $totalCostUsd * $usdRate;
        
        return [
            'input_cost_usd' => $inputCostUsd,
            'output_cost_usd' => $outputCostUsd,
            'total_cost_usd' => $totalCostUsd,
            'total_cost_rupiah' => $totalCostRupiah,
        ];
    }
    
    /**
     * Get usage statistics
     */
    public function getUsageStats($month = null, $year = null)
    {
        $month = $month ?? Carbon::now()->month;
        $year = $year ?? Carbon::now()->year;
        
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();
        
        $stats = DB::table('ai_usage_logs')
            ->where('provider', 'claude')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(cost_usd) as total_cost_usd,
                SUM(cost_rupiah) as total_cost_rupiah,
                AVG(cost_rupiah) as avg_cost_per_request
            ')
            ->first();
        
        return [
            'month' => $month,
            'year' => $year,
            'total_requests' => $stats->total_requests ?? 0,
            'total_input_tokens' => $stats->total_input_tokens ?? 0,
            'total_output_tokens' => $stats->total_output_tokens ?? 0,
            'total_tokens' => $stats->total_tokens ?? 0,
            'total_cost_usd' => $stats->total_cost_usd ?? 0,
            'total_cost_rupiah' => $stats->total_cost_rupiah ?? 0,
            'avg_cost_per_request' => $stats->avg_cost_per_request ?? 0,
            'budget_limit' => $this->getBudgetLimit(),
            'remaining_budget' => $this->getRemainingBudget(),
            'budget_used_percentage' => $this->getBudgetLimit() > 0 
                ? ($stats->total_cost_rupiah ?? 0) / $this->getBudgetLimit() * 100 
                : 0,
        ];
    }
}

