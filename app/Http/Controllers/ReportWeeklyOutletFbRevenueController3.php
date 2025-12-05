<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportWeeklyOutletFbRevenueController3 extends Controller
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

        // Get monthly budget
        $monthlyBudget = $this->getMonthlyBudget($outlet, $month, $year);

        // Get days in month and holidays
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = $this->getHolidays($year, $month);

        // Generate weekly data structure
        $weeklyData = $this->generateWeeklyData($year, $month, $daysInMonth, $holidays);

        // Get and process orders data
        $orders = $this->getOrders($outlet, $year, $month);
        $dailyOrders = $this->processOrders($orders);

        // Update weekly data with order data and calculate summaries
        $monthlySummary = $this->updateWeeklyDataWithOrders($weeklyData, $dailyOrders);
        $weeklySummaries = $this->calculateWeeklySummaries($weeklyData);

        // Calculate MTD performance and day counts
        $mtdPerformance = $this->calculateMtdPerformance($monthlySummary['total_revenue'], $monthlyBudget);
        $dayCounts = $this->calculateDayCounts($year, $month, $daysInMonth, $holidays);

        $response = [
            'weekly_data' => $weeklyData,
            'weekly_summaries' => $weeklySummaries,
            'monthly_summary' => $monthlySummary,
            'monthly_budget' => $monthlyBudget,
            'mtd_performance' => $mtdPerformance,
            'day_counts' => $dayCounts,
            'outlet_name' => $this->getOutletName($outlet)
        ];

        return response()->json($response);
    }

    private function getOutletQrCode($outlet)
    {
        if ($outlet) {
            return $outlet;
        }

        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            return DB::table('tbl_data_outlet')
                ->where('id_outlet', $user->id_outlet)
                ->value('qr_code');
        }

        return null;
    }

    private function getMonthlyBudget($outlet, $month, $year)
    {
        $budget = DB::table('outlet_monthly_budgets')
            ->where('outlet_qr_code', $outlet)
            ->where('month', (int)$month)
            ->where('year', (int)$year)
            ->value('budget_amount');

        return $budget !== null ? (float)$budget : null;
    }

    private function getHolidays($year, $month)
    {
        return DB::table('tbl_kalender_perusahaan')
            ->whereYear('tgl_libur', $year)
            ->whereMonth('tgl_libur', $month)
            ->select('tgl_libur', 'keterangan')
            ->get()
            ->keyBy('tgl_libur');
    }

    private function generateWeeklyData($year, $month, $daysInMonth, $holidays)
    {
        $weeklyData = [1 => [], 2 => [], 3 => [], 4 => []];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateStr = $date->format('Y-m-d');
            $dayName = $this->getIndonesianDayName($date->dayOfWeek);
            $isWeekend = in_array($date->dayOfWeek, [0, 6]);
            $isHoliday = $holidays->has($dateStr);
            $holidayDescription = $isHoliday ? $holidays[$dateStr]->keterangan : null;

            // Static week assignment
            $weekNum = $this->getWeekNumber($day);

            $weeklyData[$weekNum][] = [
                'date' => $dateStr,
                'day' => $dayName,
                'week' => $weekNum,
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'holiday_description' => $holidayDescription,
                'revenue' => 0,
                'cover' => 0,
                'avg_check' => 0
            ];
        }

        return $weeklyData;
    }

    private function getWeekNumber($day)
    {
        if ($day >= 1 && $day <= 7) {
            return 1;
        } elseif ($day >= 8 && $day <= 14) {
            return 2;
        } elseif ($day >= 15 && $day <= 21) {
            return 3;
        } else {
            return 4;
        }
    }

    private function getOrders($outlet, $year, $month)
    {
        return DB::table('orders')
            ->where('kode_outlet', $outlet)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->select('id', 'grand_total', 'pax', 'created_at')
            ->get();
    }

    private function processOrders($orders)
    {
        $dailyOrders = [];
        foreach ($orders as $order) {
            $orderDate = Carbon::parse($order->created_at)->format('Y-m-d');
            if (!isset($dailyOrders[$orderDate])) {
                $dailyOrders[$orderDate] = ['revenue' => 0, 'cover' => 0];
            }
            $dailyOrders[$orderDate]['revenue'] += $order->grand_total;
            $dailyOrders[$orderDate]['cover'] += $order->pax;
        }
        return $dailyOrders;
    }

    private function updateWeeklyDataWithOrders(&$weeklyData, $dailyOrders)
    {
        $monthlySummary = [
            'total_revenue' => 0,
            'total_cover' => 0,
            'weekdays_revenue' => 0,
            'weekdays_cover' => 0,
            'weekends_revenue' => 0,
            'weekends_cover' => 0,
            'holidays_revenue' => 0,
            'holidays_cover' => 0
        ];

        foreach ($weeklyData as $weekNum => &$week) {
            foreach ($week as &$day) {
                if (isset($dailyOrders[$day['date']])) {
                    $day['revenue'] = $dailyOrders[$day['date']]['revenue'];
                    $day['cover'] = $dailyOrders[$day['date']]['cover'];
                    $day['avg_check'] = $day['cover'] > 0 ? round($day['revenue'] / $day['cover']) : 0;
                    
                    // Update monthly summary
                    $monthlySummary['total_revenue'] += $day['revenue'];
                    $monthlySummary['total_cover'] += $day['cover'];
                    
                    if ($day['is_holiday']) {
                        $monthlySummary['holidays_revenue'] += $day['revenue'];
                        $monthlySummary['holidays_cover'] += $day['cover'];
                    } elseif ($day['is_weekend']) {
                        $monthlySummary['weekends_revenue'] += $day['revenue'];
                        $monthlySummary['weekends_cover'] += $day['cover'];
                    } else {
                        $monthlySummary['weekdays_revenue'] += $day['revenue'];
                        $monthlySummary['weekdays_cover'] += $day['cover'];
                    }
                }
            }
        }

        return $monthlySummary;
    }

    private function calculateWeeklySummaries($weeklyData)
    {
        $weeklySummaries = [];
        foreach ($weeklyData as $weekNum => $week) {
            $weekRevenue = collect($week)->sum('revenue');
            $weekCover = collect($week)->sum('cover');
            $weekdays = collect($week)->filter(fn($day) => !$day['is_weekend'] && !$day['is_holiday']);
            $weekends = collect($week)->filter(fn($day) => $day['is_weekend'] || $day['is_holiday']);
            
            $weeklySummaries[$weekNum] = [
                'total_revenue' => $weekRevenue,
                'avg_revenue_per_day' => count($week) > 0 ? round($weekRevenue / count($week)) : 0,
                'weekdays_revenue' => $weekdays->sum('revenue'),
                'avg_weekdays_revenue' => $weekdays->count() > 0 ? round($weekdays->sum('revenue') / $weekdays->count()) : 0,
                'weekends_revenue' => $weekends->sum('revenue'),
                'avg_weekends_revenue' => $weekends->count() > 0 ? round($weekends->sum('revenue') / $weekends->count()) : 0,
                'total_cover' => $weekCover,
                'avg_cover_per_day' => count($week) > 0 ? round($weekCover / count($week)) : 0
            ];
        }
        return $weeklySummaries;
    }

    private function calculateMtdPerformance($totalRevenue, $monthlyBudget)
    {
        if (!$monthlyBudget || $monthlyBudget <= 0) {
            return 0;
        }
        return round(($totalRevenue / $monthlyBudget) * 100, 2);
    }

    private function calculateDayCounts($year, $month, $daysInMonth, $holidays)
    {
        $currentDate = Carbon::now();
        $daysToDate = 0;
        $weekdaysToDate = 0;
        $weekendsToDate = 0;
        
        if ($currentDate->year == $year && $currentDate->month == $month) {
            $daysToDate = $currentDate->day;
            for ($day = 1; $day <= $daysToDate; $day++) {
                $date = Carbon::create($year, $month, $day);
                $isWeekend = in_array($date->dayOfWeek, [0, 6]);
                $isHoliday = $holidays->has($date->format('Y-m-d'));
                
                if ($isHoliday || $isWeekend) {
                    $weekendsToDate++;
                } else {
                    $weekdaysToDate++;
                }
            }
        } else {
            $daysToDate = $daysInMonth;
            $weekdaysToDate = collect(range(1, $daysInMonth))->filter(function($day) use ($year, $month, $holidays) {
                $date = Carbon::create($year, $month, $day);
                $isWeekend = in_array($date->dayOfWeek, [0, 6]);
                $isHoliday = $holidays->has($date->format('Y-m-d'));
                return !$isWeekend && !$isHoliday;
            })->count();
            $weekendsToDate = $daysInMonth - $weekdaysToDate;
        }

        $weekdaysInMonth = collect(range(1, $daysInMonth))->filter(function($day) use ($year, $month, $holidays) {
            $date = Carbon::create($year, $month, $day);
            $isWeekend = in_array($date->dayOfWeek, [0, 6]);
            $isHoliday = $holidays->has($date->format('Y-m-d'));
            return !$isWeekend && !$isHoliday;
        })->count();

        $weekendsInMonth = $daysInMonth - $weekdaysInMonth;

        return [
            'total_days' => $daysInMonth,
            'weekdays' => $weekdaysInMonth,
            'weekends' => $weekendsInMonth,
            'days_to_date' => $daysToDate,
            'weekdays_to_date' => $weekdaysToDate,
            'weekends_to_date' => $weekendsToDate
        ];
    }

    private function getOutletName($outlet)
    {
        return DB::table('tbl_data_outlet')->where('qr_code', $outlet)->value('nama_outlet');
    }

    public function storeBudget(Request $request)
    {
        $request->validate([
            'outlet' => 'required|string',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
            'budget_amount' => 'required|numeric|min:0'
        ]);

        $outlet = $request->input('outlet');
        $month = $request->input('month');
        $year = $request->input('year');
        $budgetAmount = $request->input('budget_amount');

        // Check if budget already exists
        $existingBudget = DB::table('outlet_monthly_budgets')
            ->where('outlet_qr_code', $outlet)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existingBudget) {
            // Update existing budget
            DB::table('outlet_monthly_budgets')
                ->where('id', $existingBudget->id)
                ->update([
                    'budget_amount' => $budgetAmount,
                    'updated_at' => now()
                ]);
        } else {
            // Create new budget
            DB::table('outlet_monthly_budgets')->insert([
                'outlet_qr_code' => $outlet,
                'month' => $month,
                'year' => $year,
                'budget_amount' => $budgetAmount,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Budget berhasil disimpan']);
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