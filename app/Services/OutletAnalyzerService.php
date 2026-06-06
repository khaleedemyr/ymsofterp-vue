<?php

namespace App\Services;

use App\Http\Controllers\AttendanceReportController;
use App\Http\Traits\ReportHelperTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OutletAnalyzerService
{
    use ReportHelperTrait;

    private const GSI_SUBJECT_COLUMNS = [
        'rating_service',
        'rating_food',
        'rating_beverage',
        'rating_cleanliness',
        'rating_staff',
        'rating_value',
    ];

    public function __construct(
        private RegionalVisitAnalyticsService $regionalVisits,
        private AttendanceReportController $attendanceReport,
    ) {}

    /**
     * @return array{month: string, start_date: string, end_date: string, label: string}
     */
    public function calendarPeriod(string $month): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'month' => $month,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'label' => $start->locale('id')->translatedFormat('F Y'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function analyze(int $outletId, string $month): ?array
    {
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet', 'qr_code', 'is_outlet')
            ->first();

        if (! $outlet) {
            return null;
        }

        $period = $this->calendarPeriod($month);
        $start = $period['start_date'];
        $end = $period['end_date'];

        $revenue = $this->getRevenue($outlet, $start, $end);
        $fj = $this->getFjInventory((int) $outlet->id_outlet, (string) $outlet->nama_outlet, $start, $end);
        $foodCostPct = $revenue['total'] > 0
            ? round(($fj['line_total'] / $revenue['total']) * 100, 2)
            : null;

        return [
            'outlet' => [
                'id_outlet' => (int) $outlet->id_outlet,
                'nama_outlet' => (string) $outlet->nama_outlet,
            ],
            'period' => $period,
            'revenue' => $revenue,
            'guest_comment_gsi' => $this->getGuestCommentGsi((int) $outlet->id_outlet, $start, $end),
            'google_review_gsi' => $this->getGoogleReviewGsi((int) $outlet->id_outlet, (string) $outlet->nama_outlet, $start, $end),
            'regional_visits' => $this->getRegionalVisits((int) $outlet->id_outlet, $start, $end),
            'fj_inventory' => array_merge($fj, ['food_cost_pct' => $foodCostPct]),
            'employee_attendance' => $this->getEmployeeAttendance((int) $outlet->id_outlet, $start, $end),
        ];
    }

    /**
     * @return array{total: float, cover: int, lunch: float, dinner: float}
     */
    private function getRevenue(object $outlet, string $start, string $end): array
    {
        $qrCode = trim((string) ($outlet->qr_code ?? ''));
        if ($qrCode === '') {
            return ['total' => 0.0, 'cover' => 0, 'lunch' => 0.0, 'dinner' => 0.0];
        }

        $orders = DB::table('orders')
            ->where('kode_outlet', $qrCode)
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->where('status', '!=', 'cancelled')
            ->where('grand_total', '>', 0)
            ->select('grand_total', 'pax', 'created_at')
            ->get();

        $total = 0.0;
        $cover = 0;
        $lunch = 0.0;
        $dinner = 0.0;

        foreach ($orders as $order) {
            $amount = (float) $order->grand_total;
            $pax = (int) ($order->pax ?? 0);
            $hour = (int) date('G', strtotime((string) $order->created_at));
            $period = $hour <= 17 ? 'lunch' : 'dinner';

            $total += $amount;
            $cover += $pax;
            if ($period === 'lunch') {
                $lunch += $amount;
            } else {
                $dinner += $amount;
            }
        }

        return [
            'total' => round($total, 2),
            'cover' => $cover,
            'lunch' => round($lunch, 2),
            'dinner' => round($dinner, 2),
        ];
    }

    /**
     * @return array{overall_pct: ?float, total_forms: int, total_responses: int}
     */
    private function getGuestCommentGsi(int $outletId, string $start, string $end): array
    {
        $base = DB::table('guest_comment_forms')
            ->where('status', 'verified')
            ->where('id_outlet', $outletId)
            ->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59']);

        $totalForms = (int) (clone $base)->count();
        $overallPositive = 0;
        $overallResponses = 0;

        foreach (self::GSI_SUBJECT_COLUMNS as $column) {
            $r = (clone $base)
                ->selectRaw("
                    SUM(CASE WHEN {$column} = 'excellent' THEN 1 ELSE 0 END) AS excellent,
                    SUM(CASE WHEN {$column} = 'good' THEN 1 ELSE 0 END) AS good,
                    SUM(CASE WHEN {$column} = 'average' THEN 1 ELSE 0 END) AS average,
                    SUM(CASE WHEN {$column} = 'poor' THEN 1 ELSE 0 END) AS poor
                ")
                ->first();

            $responses = (int) ($r->excellent ?? 0) + (int) ($r->good ?? 0)
                + (int) ($r->average ?? 0) + (int) ($r->poor ?? 0);
            $overallPositive += (int) ($r->excellent ?? 0) + (int) ($r->good ?? 0);
            $overallResponses += $responses;
        }

        return [
            'overall_pct' => $overallResponses > 0
                ? round(($overallPositive / $overallResponses) * 100, 2)
                : null,
            'total_forms' => $totalForms,
            'total_responses' => $overallResponses,
        ];
    }

    /**
     * @return array{overall_pct: ?float, total_reviews: int, positive_reviews: int, sources: array<string, int>}
     */
    private function getGoogleReviewGsi(int $outletId, string $outletName, string $start, string $end): array
    {
        $positive = 0;
        $total = 0;
        $sources = ['manual' => 0, 'ai_classified' => 0];

        $manualRows = DB::table('google_review_manual_reviews')
            ->where('id_outlet', $outletId)
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhereNull('is_active');
            })
            ->whereDate('review_date', '>=', $start)
            ->whereDate('review_date', '<=', $end)
            ->whereNotNull('rating')
            ->pluck('rating');

        foreach ($manualRows as $rating) {
            $score = (float) $rating;
            if ($score <= 0) {
                continue;
            }
            $total++;
            $sources['manual']++;
            if ($score >= 4) {
                $positive++;
            }
        }

        $aiRows = DB::table('google_review_ai_items as i')
            ->join('google_review_ai_reports as r', 'r.id', '=', 'i.report_id')
            ->where('r.status', 'completed')
            ->whereIn('r.source', ['apify_dataset', 'places_api', 'scraper_inline', 'manual_db'])
            ->where(function ($q) use ($outletId, $outletName) {
                $q->where('r.id_outlet', $outletId)
                    ->orWhere('r.nama_outlet', $outletName);
            })
            ->whereDate('i.created_at', '>=', $start)
            ->whereDate('i.created_at', '<=', $end)
            ->select('i.rating', 'i.severity')
            ->get();

        foreach ($aiRows as $row) {
            $total++;
            $sources['ai_classified']++;
            $severity = strtolower(trim((string) ($row->severity ?? '')));
            if ($severity === 'positive') {
                $positive++;

                continue;
            }
            $score = (float) $row->rating;
            if ($score >= 4) {
                $positive++;
            }
        }

        return [
            'overall_pct' => $total > 0 ? round(($positive / $total) * 100, 2) : null,
            'total_reviews' => $total,
            'positive_reviews' => $positive,
            'sources' => $sources,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getRegionalVisits(int $outletId, string $start, string $end): array
    {
        $regionalUserIds = DB::table('user_regional as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where('u.status', 'A')
            ->pluck('ur.user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($regionalUserIds)) {
            return [
                'visit_days' => 0,
                'scan_in_count' => 0,
                'total_hours' => 0.0,
                'unique_visitors' => 0,
                'visitors' => [],
            ];
        }

        $detail = $this->regionalVisits->getOutletVisitDetail($regionalUserIds, $outletId, $start, $end);
        $visitorMap = [];

        foreach ($detail['daily_visits'] ?? [] as $day) {
            foreach ($day['sessions'] ?? [] as $session) {
                $uid = (int) ($session['user_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                if (! isset($visitorMap[$uid])) {
                    $visitorMap[$uid] = [
                        'id' => $uid,
                        'name' => (string) ($session['user_name'] ?? '-'),
                        'visit_days' => 0,
                    ];
                }
                $visitorMap[$uid]['visit_days']++;
            }
        }

        $visitors = collect($visitorMap)
            ->sortByDesc('visit_days')
            ->values()
            ->all();

        return [
            'visit_days' => (int) ($detail['summary']['visit_days'] ?? 0),
            'scan_in_count' => (int) ($detail['summary']['scan_in_count'] ?? 0),
            'total_hours' => (float) ($detail['summary']['total_hours'] ?? 0),
            'unique_visitors' => (int) ($detail['summary']['unique_visitors'] ?? 0),
            'visitors' => $visitors,
        ];
    }

    /**
     * @return array<string, float|array<string, mixed>>
     */
    private function getFjInventory(int $outletId, string $outletName, string $start, string $end): array
    {
        $defaults = [
            'main_kitchen' => 0.0,
            'main_store' => 0.0,
            'chemical' => 0.0,
            'stationary' => 0.0,
            'marketing' => 0.0,
            'line_total' => 0.0,
            'categories' => [],
        ];

        $foodRows = $this->rekapFjFetchFoodGrPivotItemRows($start, $end)
            ->filter(fn ($row) => (string) $row->customer === $outletName);
        $serialRows = $this->rekapFjFetchSerialGrPivotItemRows($start, $end)
            ->filter(fn ($row) => (string) $row->customer === $outletName);

        $aggregated = $this->rekapFjAggregatePivotItemRowsByOutlet($foodRows->concat($serialRows));
        $row = $aggregated[$outletName] ?? null;

        if (! $row) {
            return $defaults;
        }

        $categories = [
            ['key' => 'main_kitchen', 'label' => 'Main Kitchen', 'amount' => round((float) $row->main_kitchen, 2)],
            ['key' => 'main_store', 'label' => 'Main Store', 'amount' => round((float) $row->main_store, 2)],
            ['key' => 'chemical', 'label' => 'Chemical', 'amount' => round((float) $row->chemical, 2)],
            ['key' => 'stationary', 'label' => 'Stationary', 'amount' => round((float) $row->stationary, 2)],
            ['key' => 'marketing', 'label' => 'Marketing', 'amount' => round((float) $row->marketing, 2)],
        ];

        return [
            'main_kitchen' => round((float) $row->main_kitchen, 2),
            'main_store' => round((float) $row->main_store, 2),
            'chemical' => round((float) $row->chemical, 2),
            'stationary' => round((float) $row->stationary, 2),
            'marketing' => round((float) $row->marketing, 2),
            'line_total' => round((float) $row->line_total, 2),
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getEmployeeAttendance(int $outletId, string $start, string $end): array
    {
        $employees = DB::table('users')
            ->where('id_outlet', $outletId)
            ->where('status', 'A')
            ->select('id', 'nama_lengkap')
            ->orderBy('nama_lengkap')
            ->get();

        $summary = [
            'employee_count' => $employees->count(),
            'present_days' => 0,
            'total_telat' => 0,
            'alpa_days' => 0,
            'off_days' => 0,
            'total_lembur' => 0,
            'ph_days' => 0,
            'leave_days' => 0,
            'percentage' => 0.0,
            'total_shift_days' => 0,
        ];

        $leaveMap = [];
        $employeeRows = [];

        foreach ($employees as $employee) {
            $empSummary = $this->attendanceReport->buildEmployeePeriodSummary((int) $employee->id, $start, $end);

            $summary['present_days'] += (int) ($empSummary['present_days'] ?? 0);
            $summary['total_telat'] += (int) ($empSummary['total_telat'] ?? 0);
            $summary['alpa_days'] += (int) ($empSummary['alpa_days'] ?? 0);
            $summary['off_days'] += (int) ($empSummary['off_days'] ?? 0);
            $summary['total_lembur'] += (int) ($empSummary['total_lembur'] ?? 0);
            $summary['ph_days'] += (int) ($empSummary['ph_days'] ?? 0);
            $summary['total_shift_days'] += (int) ($empSummary['total_shift_days'] ?? 0);

            $empLeaveDays = 0;
            foreach ($empSummary['leave_breakdown'] ?? [] as $leave) {
                $days = (int) ($leave['days'] ?? 0);
                $empLeaveDays += $days;
                $typeId = (int) ($leave['leave_type_id'] ?? 0);
                if (! isset($leaveMap[$typeId])) {
                    $leaveMap[$typeId] = [
                        'leave_type_id' => $typeId,
                        'name' => (string) ($leave['name'] ?? 'Izin'),
                        'days' => 0,
                    ];
                }
                $leaveMap[$typeId]['days'] += $days;
            }
            $summary['leave_days'] += $empLeaveDays;

            $shiftCount = (int) ($empSummary['total_shift_days'] ?? 0);
            $present = (int) ($empSummary['present_days'] ?? 0);
            $employeeRows[] = [
                'id' => (int) $employee->id,
                'name' => (string) $employee->nama_lengkap,
                'present_days' => $present,
                'total_telat' => (int) ($empSummary['total_telat'] ?? 0),
                'total_lembur' => (int) ($empSummary['total_lembur'] ?? 0),
                'alpa_days' => (int) ($empSummary['alpa_days'] ?? 0),
                'leave_days' => $empLeaveDays,
                'percentage' => $shiftCount > 0 ? round(($present / $shiftCount) * 100, 1) : 0.0,
            ];
        }

        if ($summary['total_shift_days'] > 0) {
            $summary['percentage'] = round(($summary['present_days'] / $summary['total_shift_days']) * 100, 1);
        }

        $leaveBreakdown = collect($leaveMap)
            ->filter(fn ($item) => $item['days'] > 0)
            ->sortByDesc('days')
            ->values()
            ->all();

        usort($employeeRows, fn ($a, $b) => $b['total_telat'] <=> $a['total_telat'] ?: strcmp($a['name'], $b['name']));

        return [
            'summary' => $summary,
            'leave_breakdown' => $leaveBreakdown,
            'composition' => [
                ['key' => 'hadir', 'label' => 'Hadir', 'days' => $summary['present_days']],
                ['key' => 'alpha', 'label' => 'Alpha', 'days' => $summary['alpa_days']],
                ['key' => 'off', 'label' => 'OFF', 'days' => $summary['off_days']],
                ['key' => 'izin', 'label' => 'Izin & Cuti', 'days' => $summary['leave_days']],
            ],
            'top_late' => array_slice($employeeRows, 0, 10),
            'top_overtime' => collect($employeeRows)->sortByDesc('total_lembur')->take(10)->values()->all(),
        ];
    }
}
