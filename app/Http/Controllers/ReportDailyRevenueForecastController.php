<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportDailyRevenueForecastController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');

        // Validate inputs
        if (!$month || !$year) {
            return response()->json(['error' => 'Month and year are required'], 400);
        }

        // Get all active outlets
        $outlets = $this->getActiveOutlets();

        $outletsData = [];
        $totalActualMtd = 0;
        $totalCoverMtd = 0;
        $totalBudgetMtd = 0;
        $totalVariance = 0;

        foreach ($outlets as $outlet) {
            $outletData = $this->getOutletData($outlet->qr_code, $month, $year);
            if ($outletData) {
                $outletsData[] = $outletData;
                
                // Calculate totals
                $totalActualMtd += $outletData['mtd_data']['actual_mtd'];
                $totalCoverMtd += $outletData['mtd_data']['cover_mtd'];
                $totalBudgetMtd += $outletData['monthly_budget'] ?? 0;
                $totalVariance += $outletData['performance_metrics']['variance'];
            }
        }

        // Calculate total performance metrics
        $totalVariancePercentage = $totalBudgetMtd > 0 ? round(($totalVariance / $totalBudgetMtd) * 100, 2) : 0;
        $totalPerformancePercentage = $totalBudgetMtd > 0 ? round(($totalActualMtd / $totalBudgetMtd) * 100, 2) : 0;
        
        $currentDate = Carbon::now();
        $daysPassed = $currentDate->day;
        $totalAverageRevenuePerDay = $daysPassed > 0 ? round($totalActualMtd / $daysPassed) : 0;
        $totalToBeAchievedPerDay = $totalVariance > 0 ? round($totalVariance / $daysPassed) : 0;

        // Get forecast settings (use first outlet's settings for modal)
        $forecastSettings = $this->getForecastSettings($outlets[0]->qr_code ?? '', $month, $year);

        $response = [
            'outlets_data' => $outletsData,
            'total_actual_mtd' => $totalActualMtd,
            'total_cover_mtd' => $totalCoverMtd,
            'total_budget_mtd' => $totalBudgetMtd,
            'total_variance' => $totalVariance,
            'total_variance_percentage' => $totalVariancePercentage,
            'total_performance_percentage' => $totalPerformancePercentage,
            'total_average_revenue_per_day' => $totalAverageRevenuePerDay,
            'total_to_be_achieved_per_day' => $totalToBeAchievedPerDay,
            'total_average_check' => $totalCoverMtd > 0 ? round($totalActualMtd / $totalCoverMtd) : 0,
            'forecast_settings' => $forecastSettings
        ];

        return response()->json($response);
    }

    private function getActiveOutlets()
    {
        return DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'qr_code', 'nama_outlet')
            ->get();
    }

    private function getOutletData($outlet, $month, $year)
    {
        // Get monthly budget
        $monthlyBudget = $this->getMonthlyBudget($outlet, $month, $year);

        // Get MTD actual data
        $mtdData = $this->getMtdData($outlet, $year, $month);

        // Calculate performance metrics
        $performanceMetrics = $this->calculatePerformanceMetrics($mtdData['actual_mtd'], $monthlyBudget);

        // Get forecast settings for this outlet
        $forecastSettings = $this->getForecastSettings($outlet, $month, $year);

        // Calculate dynamic targets for this outlet
        $dynamicTargets = $this->calculateDynamicTargets($month, $year, $mtdData['actual_mtd'], $monthlyBudget, $forecastSettings);

        // Get daily targets for this outlet
        $dailyTargets = $this->getDailyTargets($month, $year, $dynamicTargets);

        return [
            'outlet_name' => $this->getOutletName($outlet),
            'mtd_data' => $mtdData,
            'monthly_budget' => $monthlyBudget,
            'performance_metrics' => $performanceMetrics,
            'daily_targets' => $dailyTargets,
            'dynamic_targets' => $dynamicTargets
        ];
    }

    private function getMonthlyBudget($outlet, $month, $year)
    {
        return DB::table('outlet_monthly_budgets')
            ->where('outlet_qr_code', $outlet)
            ->where('month', (int)$month)
            ->where('year', (int)$year)
            ->value('budget_amount') ?? 0;
    }

    private function getMtdData($outlet, $year, $month)
    {
        $currentDate = Carbon::now();
        $selectedDate = Carbon::create($year, $month, 1);
        
        // Determine MTD period
        if ($currentDate->year == $year && $currentDate->month == $month) {
            $mtdEndDate = $currentDate->day;
        } else {
            $mtdEndDate = $selectedDate->daysInMonth;
        }

        // Get orders for MTD period
        $orders = DB::table('orders')
            ->where('kode_outlet', $outlet)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereDay('created_at', '<=', $mtdEndDate)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->select('grand_total', 'pax')
            ->get();

        $actualMtd = $orders->sum('grand_total');
        $coverMtd = $orders->sum('pax');
        $averageCheck = $coverMtd > 0 ? round($actualMtd / $coverMtd) : 0;

        return [
            'actual_mtd' => $actualMtd,
            'cover_mtd' => $coverMtd,
            'average_check' => $averageCheck,
            'mtd_end_date' => $mtdEndDate
        ];
    }

    private function getForecastSettings($outlet, $month, $year)
    {
        return DB::table('outlet_forecast_settings')
            ->where('outlet_qr_code', $outlet)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
    }

    private function calculateDynamicTargets($month, $year, $actualMtd, $monthlyBudget, $forecastSettings)
    {
        $currentDate = Carbon::now();
        $selectedDate = Carbon::create($year, $month, 1);
        
        // Calculate remaining days
        if ($currentDate->year == $year && $currentDate->month == $month) {
            $daysPassed = $currentDate->day;
            $daysInMonth = $currentDate->daysInMonth;
            $remainingDays = $daysInMonth - $daysPassed;
            
            // Calculate remaining weekdays and weekends
            $remainingWeekdays = 0;
            $remainingWeekends = 0;
            
            for ($day = $daysPassed + 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $month, $day);
                if (in_array($date->dayOfWeek, [0, 6])) { // 0=Minggu, 6=Sabtu
                    $remainingWeekends++;
                } else {
                    $remainingWeekdays++;
                }
            }
        } else {
            // For past or future months, use all days in the month
            $daysInMonth = $selectedDate->daysInMonth;
            $remainingDays = $daysInMonth;
            
            $remainingWeekdays = 0;
            $remainingWeekends = 0;
            
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $month, $day);
                if (in_array($date->dayOfWeek, [0, 6])) {
                    $remainingWeekends++;
                } else {
                    $remainingWeekdays++;
                }
            }
        }
        
        // Calculate variance
        $variance = $monthlyBudget - $actualMtd;
        
        // Auto calculate or use manual settings
        if ($forecastSettings && !$forecastSettings->auto_calculate) {
            // Use manual settings
            $weekdayTarget = $forecastSettings->weekday_target;
            $weekendTarget = $forecastSettings->weekend_target;
        } else {
            // Auto calculate based on variance
            if ($remainingDays > 0) {
                // Calculate total weekdays and weekends in remaining days
                $remainingWeekdays = 0;
                $remainingWeekends = 0;
                
                for ($day = 1; $day <= $remainingDays; $day++) {
                    $date = Carbon::create($year, $month, $day);
                    if (in_array($date->dayOfWeek, [0, 6])) { // 0=Minggu, 6=Sabtu
                        $remainingWeekends++;
                    } else {
                        $remainingWeekdays++;
                    }
                }
                
                // Calculate targets so that total = variance
                // Weekend target = 2x weekday target
                // Variance = (Weekday Target × Weekday Count) + (Weekend Target × Weekend Count)
                // Variance = (Weekday Target × Weekday Count) + (2 × Weekday Target × Weekend Count)
                // Variance = Weekday Target × (Weekday Count + 2 × Weekend Count)
                
                if (($remainingWeekdays + (2 * $remainingWeekends)) > 0) {
                    $weekdayTarget = $variance / ($remainingWeekdays + (2 * $remainingWeekends));
                    $weekendTarget = $weekdayTarget * 2; // 2x higher
                } else {
                    $weekdayTarget = 0;
                    $weekendTarget = 0;
                }
            } else {
                $weekdayTarget = 0;
                $weekendTarget = 0;
            }
        }
        
        return [
            'remaining_days' => $remainingDays,
            'remaining_weekdays' => $remainingWeekdays,
            'remaining_weekends' => $remainingWeekends,
            'variance' => $variance,
            'weekday_target' => round($weekdayTarget),
            'weekend_target' => round($weekendTarget),
            'target_per_day' => $remainingDays > 0 ? round($variance / $remainingDays) : 0
        ];
    }

    private function getDailyTargets($month, $year, $dynamicTargets)
    {
        $dailyTargets = [];
        $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        
        for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
            $isWeekend = in_array($dayOfWeek, [0, 6]); // 0=Minggu, 6=Sabtu
            
            $dailyTargets[$dayOfWeek] = [
                'day_name' => $dayNames[$dayOfWeek],
                'day_of_week' => $dayOfWeek,
                'is_weekend' => $isWeekend,
                'target_revenue' => $isWeekend ? $dynamicTargets['weekend_target'] : $dynamicTargets['weekday_target'],
                'count' => $this->getDayCount($month, $year, $dayOfWeek)
            ];
        }
        
        return $dailyTargets;
    }

    private function getDayCount($month, $year, $dayOfWeek)
    {
        $currentDate = Carbon::now();
        $selectedDate = Carbon::create($year, $month, 1);
        $daysInMonth = $selectedDate->daysInMonth;
        $count = 0;
        
        // Calculate count for remaining days only
        if ($currentDate->year == $year && $currentDate->month == $month) {
            $daysPassed = $currentDate->day;
            $startDay = $daysPassed + 1;
        } else {
            $startDay = 1;
        }
        
        for ($day = $startDay; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            if ($date->dayOfWeek == $dayOfWeek) {
                $count++;
            }
        }
        
        return $count;
    }

    private function calculatePerformanceMetrics($actualMtd, $monthlyBudget)
    {
        $variance = $monthlyBudget - $actualMtd;
        $variancePercentage = $monthlyBudget > 0 ? round(($variance / $monthlyBudget) * 100, 2) : 0;
        $performancePercentage = $monthlyBudget > 0 ? round(($actualMtd / $monthlyBudget) * 100, 2) : 0;
        
        // Calculate average revenue per day
        $currentDate = Carbon::now();
        $daysPassed = $currentDate->day;
        $averageRevenuePerDay = $daysPassed > 0 ? round($actualMtd / $daysPassed) : 0;
        
        // Calculate to be achieved per day
        $toBeAchievedPerDay = $variance > 0 ? round($variance / $daysPassed) : 0;
        
        return [
            'variance' => $variance,
            'variance_percentage' => $variancePercentage,
            'performance_percentage' => $performancePercentage,
            'average_revenue_per_day' => $averageRevenuePerDay,
            'to_be_achieved_per_day' => $toBeAchievedPerDay
        ];
    }

    private function getOutletName($outlet)
    {
        return DB::table('tbl_data_outlet')->where('qr_code', $outlet)->value('nama_outlet');
    }

    public function storeForecastSettings(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
            'weekday_target' => 'required|numeric|min:0',
            'weekend_target' => 'required|numeric|min:0',
            'auto_calculate' => 'required|boolean'
        ]);

        $month = $request->input('month');
        $year = $request->input('year');
        $weekdayTarget = $request->input('weekday_target');
        $weekendTarget = $request->input('weekend_target');
        $autoCalculate = $request->input('auto_calculate');

        // Get all outlets and save settings for each
        $outlets = $this->getActiveOutlets();
        
        foreach ($outlets as $outlet) {
            // Check if settings already exist
            $existingSettings = DB::table('outlet_forecast_settings')
                ->where('outlet_qr_code', $outlet->qr_code)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if ($existingSettings) {
                // Update existing settings
                DB::table('outlet_forecast_settings')
                    ->where('id', $existingSettings->id)
                    ->update([
                        'weekday_target' => $weekdayTarget,
                        'weekend_target' => $weekendTarget,
                        'auto_calculate' => $autoCalculate,
                        'updated_at' => now()
                    ]);
            } else {
                // Create new settings
                DB::table('outlet_forecast_settings')->insert([
                    'outlet_qr_code' => $outlet->qr_code,
                    'month' => $month,
                    'year' => $year,
                    'weekday_target' => $weekdayTarget,
                    'weekend_target' => $weekendTarget,
                    'auto_calculate' => $autoCalculate,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Forecast settings berhasil disimpan untuk semua outlet']);
    }
} 