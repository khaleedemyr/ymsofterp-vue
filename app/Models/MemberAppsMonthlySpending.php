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
     */
    public static function getRolling12MonthSpending($memberId, $asOfDate = null)
    {
        if ($asOfDate === null) {
            $asOfDate = now();
        }

        $endYear = (int) $asOfDate->format('Y');
        $endMonth = (int) $asOfDate->format('m');

        // Calculate start date (12 months ago)
        $startDate = $asOfDate->copy()->subMonths(11); // 11 months ago + current month = 12 months
        $startYear = (int) $startDate->format('Y');
        $startMonth = (int) $startDate->format('m');

        $totalSpending = 0;

        // Get all months in the rolling window
        $currentDate = $startDate->copy();
        while ($currentDate->lte($asOfDate)) {
            $year = (int) $currentDate->format('Y');
            $month = (int) $currentDate->format('m');

            $monthlySpending = self::where('member_id', $memberId)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($monthlySpending) {
                $totalSpending += (float) $monthlySpending->total_spending;
            }

            $currentDate->addMonth();
        }

        return $totalSpending;
    }
}

