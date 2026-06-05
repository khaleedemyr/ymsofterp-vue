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
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        $userId = $request->filled('user_id') ? (int) $request->get('user_id') : null;
        $area = $request->get('area');

        $regionalUsers = $this->analytics->getRegionalUsers();

        $resolved = $this->analytics->resolveFilter($userId, $area);
        $report = ['outlets' => [], 'summary' => [
            'total_outlets' => 0,
            'visited_outlets' => 0,
            'never_visited_outlets' => 0,
            'total_visit_days' => 0,
            'max_visit_days' => 0,
            'visit_coverage_pct' => 0,
        ]];

        $selectedRegional = null;
        if (! empty($resolved['user_ids']) && $resolved['area']) {
            $report = $this->analytics->getVisitStats(
                $resolved['user_ids'],
                $resolved['area'],
                $startDate,
                $endDate,
            );

            if ($userId) {
                $selectedRegional = $regionalUsers->firstWhere('id', $userId);
            }
        }

        return Inertia::render('Regional/VisitReport', [
            'regionalUsers' => $regionalUsers,
            'outletStats' => $report['outlets'],
            'summary' => $report['summary'],
            'selectedRegional' => $selectedRegional,
            'filters' => [
                'user_id' => $userId,
                'area' => $resolved['area'] ?? $area,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'areas' => UserRegional::AREAS,
        ]);
    }
}
