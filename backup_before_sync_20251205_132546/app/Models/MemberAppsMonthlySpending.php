<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberAppsMonthlySpending extends Model
{
    protected $table = 'member_apps_monthly_spending';
    
    protected $fillable = [
        'member_id',
        'year',
        'month',
        'total_spending',
        'transaction_count',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_spending' => 'decimal:2',
        'transaction_count' => 'integer',
    ];

    public function member()
    {
        return $this->belongsTo(MemberAppsMember::class, 'member_id');
    }

    /**
     * Get spending for a specific month
     */
    public static function getSpendingForMonth($memberId, $year, $month)
    {
        return self::where('member_id', $memberId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Add spending to a specific month
     */
    public static function addSpending($memberId, $year, $month, $amount)
    {
        $spending = self::firstOrNew([
            'member_id' => $memberId,
            'year' => $year,
            'month' => $month,
        ]);

        $spending->total_spending = ($spending->total_spending ?? 0) + $amount;
        $spending->transaction_count = ($spending->transaction_count ?? 0) + 1;
        $spending->save();

        return $spending;
    }

    /**
     * Get rolling 12-month spending for a member
     * Rolling 12-month means: from (current month - 11 months) to current month (inclusive)
     * Example: If today is November 2025, rolling 12-month = Dec 2024 to Nov 2025 (12 months)
     */
    public static function getRolling12MonthSpending($memberId, $asOfDate = null)
    {
        if ($asOfDate === null) {
            $asOfDate = now();
        }

        // Ensure $asOfDate is a Carbon instance
        if (!$asOfDate instanceof \Carbon\Carbon) {
            $asOfDate = \Carbon\Carbon::parse($asOfDate);
        }

        // Calculate start date: 11 months ago from asOfDate
        // This gives us 12 months total (11 months ago + current month)
        $startDate = $asOfDate->copy()->subMonths(11)->startOfMonth();
        $endDate = $asOfDate->copy()->endOfMonth();

        $startYear = (int) $startDate->format('Y');
        $startMonth = (int) $startDate->format('m');
        $endYear = (int) $endDate->format('Y');
        $endMonth = (int) $endDate->format('m');

        $totalSpending = 0;
        $monthlyBreakdown = [];
        $monthsIncluded = [];

        // Get all months in the rolling window (12 months total)
        $currentDate = $startDate->copy();
        $monthCount = 0;
        
        while ($monthCount < 12) {
            $year = (int) $currentDate->format('Y');
            $month = (int) $currentDate->format('m');

            $monthlySpending = self::where('member_id', $memberId)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            $monthAmount = 0;
            if ($monthlySpending) {
                $monthAmount = (float) $monthlySpending->total_spending;
                $totalSpending += $monthAmount;
            }

            $monthlyBreakdown[] = [
                'year' => $year,
                'month' => $month,
                'month_name' => $currentDate->format('M Y'),
                'spending' => $monthAmount
            ];
            
            $monthsIncluded[] = $currentDate->format('Y-m');
            $currentDate->addMonth();
            $monthCount++;
        }

        // Log for debugging
        \Log::info('Rolling 12-Month Spending Calculation', [
            'member_id' => $memberId,
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'start_month' => $startDate->format('Y-m'),
            'end_month' => $endDate->format('Y-m'),
            'months_included' => $monthsIncluded,
            'total_rolling_spending' => $totalSpending,
            'monthly_breakdown' => $monthlyBreakdown,
            'months_count' => count($monthlyBreakdown),
            'expected_months' => 12
        ]);

        return $totalSpending;
    }
}

