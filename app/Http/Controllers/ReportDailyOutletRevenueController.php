<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportDailyOutletRevenueController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');
        $outlet = $request->input('outlet');

        // Debug logging
        \Log::info('Daily Outlet Revenue Request', [
            'month' => $month,
            'year' => $year,
            'outlet' => $outlet,
            'user_id' => auth()->id(),
            'user_outlet' => auth()->user()?->id_outlet
        ]);

        // Validate inputs
        if (!$month || !$year) {
            return response()->json(['error' => 'Month and year are required'], 400);
        }

        // Get outlet QR code for non-superuser
        if (!$outlet) {
            $user = auth()->user();
            if ($user && $user->id_outlet && $user->id_outlet != 1) {
                $outlet = DB::table('tbl_data_outlet')
                    ->where('id_outlet', $user->id_outlet)
                    ->value('qr_code');
            }
        }

        if (!$outlet) {
            \Log::error('Daily Outlet Revenue: Outlet not found', [
                'user_id' => auth()->id(),
                'user_outlet' => auth()->user()?->id_outlet
            ]);
            return response()->json(['error' => 'Outlet not found'], 400);
        }

        \Log::info('Daily Outlet Revenue: Using outlet', ['outlet' => $outlet]);

        // Get days in month
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        
        $dailyData = [];
        $summary = [
            'lunch' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
            'dinner' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
            'total' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0]
        ];

        // Initialize daily data for all days in month
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day)->format('Y-m-d');
            $dayName = $this->getIndonesianDayName(Carbon::create($year, $month, $day)->dayOfWeek);
            
            $dailyData[$date] = [
                'day_name' => $dayName,
                'lunch' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                'dinner' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                'total' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0]
            ];
        }

        // Get orders data for the month
        $orders = DB::table('orders')
            ->where('kode_outlet', $outlet)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->select(
                'id',
                'grand_total',
                'discount',
                'pax',
                'created_at'
            )
            ->get();

        \Log::info('Daily Outlet Revenue: Orders found', [
            'count' => $orders->count(),
            'outlet' => $outlet,
            'month' => $month,
            'year' => $year
        ]);

        // Process orders and categorize by lunch/dinner
        foreach ($orders as $order) {
            $orderDate = Carbon::parse($order->created_at)->format('Y-m-d');
            $orderHour = Carbon::parse($order->created_at)->hour;
            
            // Determine if lunch (<= 17:00) or dinner (> 17:00)
            $period = $orderHour <= 17 ? 'lunch' : 'dinner';
            
            if (isset($dailyData[$orderDate])) {
                // Update daily data
                $dailyData[$orderDate][$period]['cover'] += $order->pax;
                $dailyData[$orderDate][$period]['revenue'] += $order->grand_total;
                $dailyData[$orderDate][$period]['disc'] += $order->discount;
                
                // Update total
                $dailyData[$orderDate]['total']['cover'] += $order->pax;
                $dailyData[$orderDate]['total']['revenue'] += $order->grand_total;
                $dailyData[$orderDate]['total']['disc'] += $order->discount;
                
                // Update summary
                $summary[$period]['cover'] += $order->pax;
                $summary[$period]['revenue'] += $order->grand_total;
                $summary[$period]['disc'] += $order->discount;
                
                $summary['total']['cover'] += $order->pax;
                $summary['total']['revenue'] += $order->grand_total;
                $summary['total']['disc'] += $order->discount;
            }
        }

        // Calculate average check for daily data
        foreach ($dailyData as $date => &$dayData) {
            // Lunch A/C
            if ($dayData['lunch']['cover'] > 0) {
                $dayData['lunch']['avg_check'] = round($dayData['lunch']['revenue'] / $dayData['lunch']['cover']);
            }
            
            // Dinner A/C
            if ($dayData['dinner']['cover'] > 0) {
                $dayData['dinner']['avg_check'] = round($dayData['dinner']['revenue'] / $dayData['dinner']['cover']);
            }
            
            // Total A/C
            if ($dayData['total']['cover'] > 0) {
                $dayData['total']['avg_check'] = round($dayData['total']['revenue'] / $dayData['total']['cover']);
            }
        }

        // Calculate average check for summary
        if ($summary['lunch']['cover'] > 0) {
            $summary['lunch']['avg_check'] = round($summary['lunch']['revenue'] / $summary['lunch']['cover']);
        }
        if ($summary['dinner']['cover'] > 0) {
            $summary['dinner']['avg_check'] = round($summary['dinner']['revenue'] / $summary['dinner']['cover']);
        }
        if ($summary['total']['cover'] > 0) {
            $summary['total']['avg_check'] = round($summary['total']['revenue'] / $summary['total']['cover']);
        }

        return response()->json([
            'daily_data' => $dailyData,
            'summary' => $summary
        ]);
    }

    private function getIndonesianDayName($dayOfWeek)
    {
        $days = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu'
        ];
        
        return $days[$dayOfWeek] ?? '';
    }
} 