<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportMonthlyFbRevenuePerformanceController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');
        $outlet = $request->input('outlet');

        // Validate inputs
        if (!$month || !$year) {
            return response()->json(['error' => 'Month and year are required'], 400);
        }

        // Get outlet QR code
        $outlet = $this->getOutletQrCode($outlet);
        if (!$outlet) {
            return response()->json(['error' => 'Outlet not found'], 400);
        }

        // Get weekly data
        $weeklyData = $this->getWeeklyData($outlet, $year, $month);

        // Get outlet name
        $outletName = $this->getOutletName($outlet);

        $response = [
            'outlet_name' => $outletName,
            'weekly_data' => $weeklyData,
            'month' => $month,
            'year' => $year
        ];

        return response()->json($response);
    }

    private function getOutletQrCode($outlet)
    {
        // If outlet is provided in request, use it.
        // Otherwise, determine based on user's id_outlet.
        if ($outlet) {
            return $outlet;
        }

        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            return DB::table('tbl_data_outlet')
                ->where('id_outlet', $user->id_outlet)
                ->value('qr_code');
        }

        return null; // If no outlet provided and user is id_outlet=1 or no user/id_outlet
    }

    private function getWeeklyData($outlet, $year, $month)
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $weeklyData = [];

        // Define week ranges
        $weekRanges = [
            1 => [1, 7],
            2 => [8, 14],
            3 => [15, 21],
            4 => [22, $daysInMonth]
        ];

        foreach ($weekRanges as $weekNum => $range) {
            $startDay = $range[0];
            $endDay = $range[1];

            $weekData = $this->getWeekData($outlet, $year, $month, $startDay, $endDay);
            $weeklyData[$weekNum] = $weekData;
        }

        return $weeklyData;
    }

    private function getWeekData($outlet, $year, $month, $startDay, $endDay)
    {
        // Get orders for this week
        $orders = DB::table('orders')
            ->where('kode_outlet', $outlet)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereDay('created_at', '>=', $startDay)
            ->whereDay('created_at', '<=', $endDay)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->select('grand_total', 'pax', 'created_at')
            ->get();

        $weekdaysRevenue = 0;
        $weekendsRevenue = 0;
        $weekdaysCover = 0;
        $weekendsCover = 0;
        $lunchRevenue = 0;
        $dinnerRevenue = 0;
        $lunchCover = 0;
        $dinnerCover = 0;

        foreach ($orders as $order) {
            $orderDate = Carbon::parse($order->created_at);
            $isWeekend = in_array($orderDate->dayOfWeek, [0, 6]); // 0=Minggu, 6=Sabtu
            $isLunch = $orderDate->hour <= 17;

            if ($isWeekend) {
                $weekendsRevenue += $order->grand_total;
                $weekendsCover += $order->pax;
            } else {
                $weekdaysRevenue += $order->grand_total;
                $weekdaysCover += $order->pax;
            }

            if ($isLunch) {
                $lunchRevenue += $order->grand_total;
                $lunchCover += $order->pax;
            } else {
                $dinnerRevenue += $order->grand_total;
                $dinnerCover += $order->pax;
            }
        }

        $totalRevenue = $weekdaysRevenue + $weekendsRevenue;
        $totalCover = $weekdaysCover + $weekendsCover;

        return [
            'weekdays_revenue' => $weekdaysRevenue,
            'weekends_revenue' => $weekendsRevenue,
            'total_revenue' => $totalRevenue,
            'weekdays_cover' => $weekdaysCover,
            'weekends_cover' => $weekendsCover,
            'total_cover' => $totalCover,
            'lunch_revenue' => $lunchRevenue,
            'dinner_revenue' => $dinnerRevenue,
            'lunch_cover' => $lunchCover,
            'dinner_cover' => $dinnerCover,
            'weekdays_avg_check' => $weekdaysCover > 0 ? round($weekdaysRevenue / $weekdaysCover) : 0,
            'weekends_avg_check' => $weekendsCover > 0 ? round($weekendsRevenue / $weekendsCover) : 0,
            'avg_check' => $totalCover > 0 ? round($totalRevenue / $totalCover) : 0,
            'lunch_avg_check' => $lunchCover > 0 ? round($lunchRevenue / $lunchCover) : 0,
            'dinner_avg_check' => $dinnerCover > 0 ? round($dinnerRevenue / $dinnerCover) : 0
        ];
    }

    private function getOutletName($outlet)
    {
        return DB::table('tbl_data_outlet')->where('qr_code', $outlet)->value('nama_outlet');
    }
} 