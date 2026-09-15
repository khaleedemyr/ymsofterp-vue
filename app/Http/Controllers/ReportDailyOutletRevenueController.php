<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportDailyOutletRevenueController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');
        $outlet = $request->input('outlet');

        Log::info('Daily Outlet Revenue Request', [
            'month' => $month,
            'year' => $year,
            'outlet' => $outlet,
            'user_id' => auth()->id(),
            'user_outlet' => auth()->user()?->id_outlet,
        ]);

        if (! $month || ! $year) {
            return response()->json(['error' => 'Month and year are required'], 400);
        }

        if (! $outlet) {
            $user = auth()->user();
            if ($user && $user->id_outlet && $user->id_outlet != 1) {
                $outlet = DB::table('tbl_data_outlet')
                    ->where('id_outlet', $user->id_outlet)
                    ->value('qr_code');
            }
        }

        if (! $outlet) {
            Log::error('Daily Outlet Revenue: Outlet not found', [
                'user_id' => auth()->id(),
                'user_outlet' => auth()->user()?->id_outlet,
            ]);

            return response()->json(['error' => 'Outlet not found'], 400);
        }

        try {
            $monthStart = Carbon::create((int) $year, (int) $month, 1)->startOfDay();
            $monthEndExclusive = $monthStart->copy()->addMonth()->startOfDay();
            $daysInMonth = $monthStart->daysInMonth;

            $dailyData = [];
            $summary = [
                'lunch' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                'dinner' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                'total' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
            ];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create((int) $year, (int) $month, $day)->format('Y-m-d');
                $dayName = $this->getIndonesianDayName(
                    Carbon::create((int) $year, (int) $month, $day)->dayOfWeek
                );

                $dailyData[$date] = [
                    'day_name' => $dayName,
                    'lunch' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                    'dinner' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                    'total' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                ];
            }

            // Aggregate in SQL (range filter keeps created_at index usable; avoid loading every order row).
            $rows = DB::table('orders')
                ->where('kode_outlet', $outlet)
                ->where('created_at', '>=', $monthStart->toDateTimeString())
                ->where('created_at', '<', $monthEndExclusive->toDateTimeString())
                ->where('status', '!=', 'cancelled')
                ->where('grand_total', '>', 0)
                ->selectRaw("
                    DATE(created_at) as order_date,
                    CASE WHEN HOUR(created_at) <= 17 THEN 'lunch' ELSE 'dinner' END as period,
                    SUM(COALESCE(pax, 0)) as cover,
                    SUM(COALESCE(grand_total, 0)) as revenue,
                    SUM(COALESCE(discount, 0)) as disc
                ")
                ->groupByRaw("DATE(created_at), CASE WHEN HOUR(created_at) <= 17 THEN 'lunch' ELSE 'dinner' END")
                ->get();

            Log::info('Daily Outlet Revenue: Aggregated rows', [
                'count' => $rows->count(),
                'outlet' => $outlet,
                'month' => $month,
                'year' => $year,
            ]);

            foreach ($rows as $row) {
                $orderDate = (string) $row->order_date;
                $period = $row->period === 'dinner' ? 'dinner' : 'lunch';

                if (! isset($dailyData[$orderDate])) {
                    continue;
                }

                $cover = (float) $row->cover;
                $revenue = (float) $row->revenue;
                $disc = (float) $row->disc;

                $dailyData[$orderDate][$period]['cover'] += $cover;
                $dailyData[$orderDate][$period]['revenue'] += $revenue;
                $dailyData[$orderDate][$period]['disc'] += $disc;

                $dailyData[$orderDate]['total']['cover'] += $cover;
                $dailyData[$orderDate]['total']['revenue'] += $revenue;
                $dailyData[$orderDate]['total']['disc'] += $disc;

                $summary[$period]['cover'] += $cover;
                $summary[$period]['revenue'] += $revenue;
                $summary[$period]['disc'] += $disc;

                $summary['total']['cover'] += $cover;
                $summary['total']['revenue'] += $revenue;
                $summary['total']['disc'] += $disc;
            }

            foreach ($dailyData as &$dayData) {
                foreach (['lunch', 'dinner', 'total'] as $period) {
                    $dayData[$period]['avg_check'] = $dayData[$period]['cover'] > 0
                        ? (float) round($dayData[$period]['revenue'] / $dayData[$period]['cover'])
                        : 0;
                }
            }
            unset($dayData);

            foreach (['lunch', 'dinner', 'total'] as $period) {
                $summary[$period]['avg_check'] = $summary[$period]['cover'] > 0
                    ? (float) round($summary[$period]['revenue'] / $summary[$period]['cover'])
                    : 0;
            }

            return response()->json([
                'daily_data' => $dailyData,
                'summary' => $summary,
            ]);
        } catch (Throwable $e) {
            Log::error('Daily Outlet Revenue failed', [
                'outlet' => $outlet,
                'month' => $month,
                'year' => $year,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Gagal mengambil data report: '.$e->getMessage(),
            ], 500);
        }
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
            6 => 'Sabtu',
        ];

        return $days[$dayOfWeek] ?? '';
    }
}
