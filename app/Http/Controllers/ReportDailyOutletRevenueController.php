<?php

namespace App\Http\Controllers;

use App\Exports\DailyOutletRevenueExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ReportDailyOutletRevenueController extends Controller
{
    public function index(Request $request)
    {
        try {
            $payload = $this->buildReportPayload($request);

            return response()->json($payload);
        } catch (Throwable $e) {
            $status = (int) $e->getCode();
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            if ($status === 500) {
                Log::error('Daily Outlet Revenue failed', [
                    'message' => $e->getMessage(),
                    'user_id' => auth()->id(),
                ]);
            }

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            $payload = $this->buildReportPayload($request);
            $monthName = Carbon::create((int) $request->input('year'), (int) $request->input('month'), 1)
                ->locale('id')
                ->translatedFormat('F');

            $payload['title'] = 'Daily Outlet Revenue Report';
            $payload['subtitle'] = ($payload['outlet_name'] ?? '').' — '.$monthName.' '.$request->input('year');

            $filename = 'daily-outlet-revenue-'.preg_replace('/\s+/', '-', strtolower($payload['outlet_name'] ?? 'outlet'))
                .'-'.$request->input('year').'-'.str_pad((string) $request->input('month'), 2, '0', STR_PAD_LEFT).'.xlsx';

            return Excel::download(new DailyOutletRevenueExport($payload), $filename);
        } catch (Throwable $e) {
            Log::error('Daily Outlet Revenue export failed', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $month = (int) $request->input('month');
        $year = (int) $request->input('year');
        $outletInput = $request->input('outlet');

        if (! $month || ! $year) {
            throw new \InvalidArgumentException('Month and year are required', 400);
        }

        $outletRow = $this->resolveOutlet($outletInput);
        if (! $outletRow) {
            throw new \InvalidArgumentException('Outlet not found', 400);
        }

        $outlet = $outletRow->qr_code;
        $outletId = (int) $outletRow->id_outlet;
        $outletName = $outletRow->nama_outlet;

        Log::info('Daily Outlet Revenue Request', [
            'month' => $month,
            'year' => $year,
            'outlet' => $outlet,
            'user_id' => auth()->id(),
            'user_outlet' => auth()->user()?->id_outlet,
        ]);

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEndExclusive = $monthStart->copy()->addMonth()->startOfDay();
        $daysInMonth = $monthStart->daysInMonth;
        $today = Carbon::now()->startOfDay();
        $mtdCutoff = $today->copy();
        if ($today->year !== $year || $today->month !== $month) {
            $mtdCutoff = $today->lt($monthStart)
                ? $monthStart->copy()->subDay()
                : $monthStart->copy()->endOfMonth();
        }

        $holidays = $this->getHolidays($year, $month);

        $dailyData = [];
        $summary = $this->emptyPeriodSummary();

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateCarbon = Carbon::create($year, $month, $day);
            $date = $dateCarbon->format('Y-m-d');
            $dayName = $this->getIndonesianDayName($dateCarbon->dayOfWeek);
            $isWeekend = in_array($dateCarbon->dayOfWeek, [0, 6], true);
            $isHoliday = $holidays->has($date);

            $dailyData[$date] = [
                'day_name' => $dayName,
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'holiday_description' => $isHoliday ? $holidays[$date]->keterangan : null,
                'lunch' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                'dinner' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
                'total' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
            ];
        }

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

            $orderCarbon = Carbon::parse($orderDate)->startOfDay();
            if ($orderCarbon->lte($mtdCutoff)) {
                $summary[$period]['cover'] += $cover;
                $summary[$period]['revenue'] += $revenue;
                $summary[$period]['disc'] += $disc;

                $summary['total']['cover'] += $cover;
                $summary['total']['revenue'] += $revenue;
                $summary['total']['disc'] += $disc;
            }
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

        $monthlyBudget = $this->getMonthlyBudgetFromRevenueTarget($outletId, $monthStart);
        $mtdRevenue = (float) $summary['total']['revenue'];
        $mtdCover = (float) $summary['total']['cover'];
        $mtdAvgCheck = $mtdCover > 0 ? (float) round($mtdRevenue / $mtdCover) : 0.0;
        $lastMonthComparison = $this->getLastMonthComparison(
            $outlet,
            $monthStart,
            $mtdCutoff,
            $mtdRevenue,
            $mtdCover,
            $mtdAvgCheck,
            $daysInMonth
        );
        $performance = $this->buildPerformanceMetrics($mtdRevenue, $mtdCover, $mtdAvgCheck, $monthlyBudget, $lastMonthComparison);

        return [
            'daily_data' => $dailyData,
            'summary' => $summary,
            'outlet_name' => $outletName,
            'outlet_qr' => $outlet,
            'outlet_id' => $outletId,
            'monthly_budget' => $monthlyBudget,
            'performance' => $performance,
            'can_select_outlet' => auth()->user()?->id_outlet == 1,
        ];
    }

    /**
     * @return object{id_outlet: int|string, qr_code: string, nama_outlet: string}|null
     */
    private function resolveOutlet(?string $outletQr): ?object
    {
        $user = auth()->user();
        if ($user && $user->id_outlet && (int) $user->id_outlet !== 1) {
            return DB::table('tbl_data_outlet')
                ->where('id_outlet', $user->id_outlet)
                ->first(['id_outlet', 'qr_code', 'nama_outlet']);
        }

        if (! $outletQr) {
            return null;
        }

        return DB::table('tbl_data_outlet')
            ->where('qr_code', $outletQr)
            ->first(['id_outlet', 'qr_code', 'nama_outlet']);
    }

    private function getHolidays(int $year, int $month)
    {
        return DB::table('tbl_kalender_perusahaan')
            ->whereYear('tgl_libur', $year)
            ->whereMonth('tgl_libur', $month)
            ->select('tgl_libur', 'keterangan')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->tgl_libur)->format('Y-m-d'));
    }

    private function getMonthlyBudgetFromRevenueTarget(int $outletId, Carbon $monthStart): ?float
    {
        $targetMonth = $monthStart->format('Y-m-01');
        $header = DB::table('outlet_revenue_target_headers')
            ->where('outlet_id', $outletId)
            ->where('target_month', $targetMonth)
            ->first(['monthly_target']);

        if (! $header || $header->monthly_target === null) {
            return null;
        }

        $budget = (float) $header->monthly_target;

        return $budget > 0 ? $budget : null;
    }

    /**
     * Last month MTD (same calendar day) + last month full + daily series for charts.
     *
     * @return array<string, mixed>
     */
    private function getLastMonthComparison(
        string $outletQr,
        Carbon $monthStart,
        Carbon $mtdCutoff,
        float $mtdRevenue,
        float $mtdCover,
        float $mtdAvgCheck,
        int $daysInMonth
    ): array {
        $prevMonthStart = $monthStart->copy()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEndExclusive = $prevMonthStart->copy()->addMonth()->startOfDay();
        $daysInPrevMonth = $prevMonthStart->daysInMonth;

        $compareDay = min(max(1, $mtdCutoff->day), $daysInPrevMonth);
        $prevMtdEndExclusive = $prevMonthStart->copy()->day($compareDay)->addDay()->startOfDay();

        $agg = fn (string $from, string $to) => DB::table('orders')
            ->where('kode_outlet', $outletQr)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('SUM(COALESCE(grand_total, 0)) as revenue, SUM(COALESCE(pax, 0)) as cover')
            ->first();

        $full = $agg($prevMonthStart->toDateTimeString(), $prevMonthEndExclusive->toDateTimeString());
        $mtd = $agg($prevMonthStart->toDateTimeString(), $prevMtdEndExclusive->toDateTimeString());

        $lastMonthFullRevenue = (float) ($full->revenue ?? 0);
        $lastMonthFullCover = (float) ($full->cover ?? 0);
        $lastMonthFullAvg = $lastMonthFullCover > 0 ? (float) round($lastMonthFullRevenue / $lastMonthFullCover) : 0.0;

        $lastMonthMtdRevenue = (float) ($mtd->revenue ?? 0);
        $lastMonthMtdCover = (float) ($mtd->cover ?? 0);
        $lastMonthMtdAvg = $lastMonthMtdCover > 0 ? (float) round($lastMonthMtdRevenue / $lastMonthMtdCover) : 0.0;

        $pct = static function (float $current, float $previous): ?float {
            if ($previous <= 0) {
                return null;
            }

            return round((($current - $previous) / $previous) * 100, 1);
        };

        $dailyRows = DB::table('orders')
            ->where('kode_outlet', $outletQr)
            ->where('created_at', '>=', $prevMonthStart->toDateTimeString())
            ->where('created_at', '<', $prevMonthEndExclusive->toDateTimeString())
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->selectRaw('DAY(created_at) as d, SUM(COALESCE(grand_total, 0)) as revenue, SUM(COALESCE(pax, 0)) as cover')
            ->groupByRaw('DAY(created_at)')
            ->get()
            ->keyBy('d');

        $chartDays = min($daysInMonth, $daysInPrevMonth);
        $lmDailyRevenue = [];
        $lmDailyCover = [];
        $lmDailyAvgCheck = [];
        for ($d = 1; $d <= $chartDays; $d++) {
            $row = $dailyRows->get($d);
            $rev = (float) ($row->revenue ?? 0);
            $cov = (float) ($row->cover ?? 0);
            $lmDailyRevenue[] = $rev;
            $lmDailyCover[] = $cov;
            $lmDailyAvgCheck[] = $cov > 0 ? (float) round($rev / $cov) : 0;
        }

        return [
            'last_month_label' => $prevMonthStart->locale('id')->translatedFormat('F Y'),
            'compare_day' => $compareDay,

            'last_month_mtd_to_date' => $lastMonthMtdRevenue,
            'last_month_full' => $lastMonthFullRevenue,
            'vs_last_mtd_var' => $mtdRevenue - $lastMonthMtdRevenue,
            'vs_last_mtd_percent' => $pct($mtdRevenue, $lastMonthMtdRevenue),
            'vs_last_full_var' => $mtdRevenue - $lastMonthFullRevenue,
            'vs_last_full_percent' => $pct($mtdRevenue, $lastMonthFullRevenue),

            'last_month_mtd_cover' => $lastMonthMtdCover,
            'last_month_full_cover' => $lastMonthFullCover,
            'vs_last_mtd_cover_var' => $mtdCover - $lastMonthMtdCover,
            'vs_last_mtd_cover_percent' => $pct($mtdCover, $lastMonthMtdCover),
            'vs_last_full_cover_var' => $mtdCover - $lastMonthFullCover,
            'vs_last_full_cover_percent' => $pct($mtdCover, $lastMonthFullCover),

            'last_month_mtd_avg_check' => $lastMonthMtdAvg,
            'last_month_full_avg_check' => $lastMonthFullAvg,
            'vs_last_mtd_avg_var' => $mtdAvgCheck - $lastMonthMtdAvg,
            'vs_last_mtd_avg_percent' => $pct($mtdAvgCheck, $lastMonthMtdAvg),
            'vs_last_full_avg_var' => $mtdAvgCheck - $lastMonthFullAvg,
            'vs_last_full_avg_percent' => $pct($mtdAvgCheck, $lastMonthFullAvg),

            'last_month_daily' => [
                'revenue' => $lmDailyRevenue,
                'cover' => $lmDailyCover,
                'avg_check' => $lmDailyAvgCheck,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $lastMonthComparison
     * @return array<string, mixed>
     */
    private function buildPerformanceMetrics(
        float $mtdRevenue,
        float $mtdCover,
        float $mtdAvgCheck,
        ?float $monthlyBudget,
        array $lastMonthComparison = []
    ): array {
        $base = [
            'mtd_revenue' => $mtdRevenue,
            'mtd_cover' => $mtdCover,
            'mtd_avg_check' => $mtdAvgCheck,
            'budget' => null,
            'perf_percent' => null,
            'variance' => null,
            'variance_percent' => null,
            ...$lastMonthComparison,
        ];

        if ($monthlyBudget === null || $monthlyBudget <= 0) {
            return $base;
        }

        $variance = $mtdRevenue - $monthlyBudget;
        $base['budget'] = $monthlyBudget;
        $base['perf_percent'] = round(($mtdRevenue / $monthlyBudget) * 100, 1);
        $base['variance'] = $variance;
        $base['variance_percent'] = round(($variance / $monthlyBudget) * 100, 1);

        return $base;
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function emptyPeriodSummary(): array
    {
        return [
            'lunch' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
            'dinner' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
            'total' => ['cover' => 0, 'revenue' => 0, 'avg_check' => 0, 'disc' => 0],
        ];
    }

    private function getIndonesianDayName(int $dayOfWeek): string
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
