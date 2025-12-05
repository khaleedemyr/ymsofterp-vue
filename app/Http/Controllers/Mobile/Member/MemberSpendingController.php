<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsMonthlySpending;
use App\Services\MemberTierService;
use Illuminate\Http\Request;

class MemberSpendingController extends Controller
{
    /**
     * Get rolling 12-month spending for authenticated member
     */
    public function getRolling12MonthSpending(Request $request)
    {
        try {
            $member = $request->user();
            $rollingSpending = MemberAppsMonthlySpending::getRolling12MonthSpending($member->id);
            $tierProgress = MemberTierService::getTierProgress($member->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'rolling_12_month_spending' => $rollingSpending,
                    'tier_progress' => $tierProgress,
                ],
                'message' => 'Rolling 12-month spending retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve spending: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monthly spending history (last 12 months)
     */
    public function getMonthlyHistory(Request $request)
    {
        try {
            $member = $request->user();
            $asOfDate = now();

            // Get last 12 months
            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $date = $asOfDate->copy()->subMonths($i);
                $year = (int) $date->format('Y');
                $month = (int) $date->format('m');

                $monthlySpending = MemberAppsMonthlySpending::where('member_id', $member->id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->first();

                $months[] = [
                    'year' => $year,
                    'month' => $month,
                    'month_name' => $date->format('F Y'),
                    'spending' => $monthlySpending ? (float) $monthlySpending->total_spending : 0,
                    'transaction_count' => $monthlySpending ? $monthlySpending->transaction_count : 0,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $months,
                'message' => 'Monthly spending history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve monthly history: ' . $e->getMessage()
            ], 500);
        }
    }
}

