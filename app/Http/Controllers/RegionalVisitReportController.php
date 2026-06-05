<?php

namespace App\Http\Controllers;

use App\Models\UserRegional;
use App\Services\RegionalVisitAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegionalVisitReportController extends Controller
{
    public function __construct(
        private RegionalVisitAnalyticsService $analytics,
    ) {}

    public function index(Request $request)
    {
        $bulan = (int) $request->get('bulan', date('m'));
        $tahun = (int) $request->get('tahun', date('Y'));
        $period = $this->analytics->payrollPeriod($bulan, $tahun);

        $userId = $request->filled('user_id') ? (int) $request->get('user_id') : null;
        $area = $request->get('area');

        $regionalUsers = $this->analytics->getRegionalUsers();

        $resolved = $this->analytics->resolveFilter($userId, $area);
        $report = ['outlets' => [], 'summary' => [
            'regional_user_count' => 0,
            'total_outlets' => 0,
            'visited_outlets' => 0,
            'never_visited_outlets' => 0,
            'total_visit_days' => 0,
            'max_visit_days' => 0,
            'visit_coverage_pct' => 0,
        ]];

        $selectedRegional = null;
        $includedRegionalUsers = collect();
        $reportAttempted = (bool) ($userId || $area);
        $noRegionalUsers = false;

        if ($reportAttempted && empty($resolved['user_ids'])) {
            $noRegionalUsers = true;
        } elseif ($reportAttempted && ! empty($resolved['user_ids'])) {
            $report = $this->analytics->getVisitStats(
                $resolved['user_ids'],
                $period['start_date'],
                $period['end_date'],
            );

            $includedRegionalUsers = $regionalUsers
                ->whereIn('id', $resolved['user_ids'])
                ->values();

            if ($userId) {
                $selectedRegional = $regionalUsers->firstWhere('id', $userId);
            }
        }

        return Inertia::render('Regional/VisitReport', [
            'regionalUsers' => $regionalUsers,
            'outletStats' => $report['outlets'],
            'summary' => $report['summary'],
            'selectedRegional' => $selectedRegional,
            'includedRegionalUsers' => $includedRegionalUsers,
            'noRegionalUsers' => $noRegionalUsers,
            'filters' => [
                'user_id' => $userId,
                'area' => $resolved['area'] ?? $area,
                'bulan' => $period['bulan'],
                'tahun' => $period['tahun'],
                'start_date' => $period['start_date'],
                'end_date' => $period['end_date'],
            ],
            'areas' => UserRegional::AREAS,
        ]);
    }
}
