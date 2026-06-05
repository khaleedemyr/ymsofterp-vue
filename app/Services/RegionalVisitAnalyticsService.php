<?php

namespace App\Services;

use App\Models\UserRegional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegionalVisitAnalyticsService
{
    public function outletMatchesArea(string $namaOutlet, string $area): bool
    {
        $pattern = '/(^|[\s\-])' . preg_quote($area, '/') . '$/i';

        return (bool) preg_match($pattern, trim($namaOutlet));
    }

    public function getRegionalUsers(): Collection
    {
        return DB::table('users as u')
            ->join('user_regional as ur', 'u.id', '=', 'ur.user_id')
            ->where('u.status', 'A')
            ->select(
                'u.id',
                'u.nama_lengkap as name',
                'u.email',
                'ur.area',
            )
            ->orderBy('u.nama_lengkap')
            ->get();
    }

    /**
     * Kunjungan = hari unik dengan scan IN di outlet (att_log).
     * Outlet scope mengikuti area regional (suffix Bar/Kitchen/Service).
     *
     * @param  array<int>  $userIds
     * @return array{
     *   outlets: array<int, array<string, mixed>>,
     *   summary: array<string, int|float|null>
     * }
     */
    public function getVisitStats(array $userIds, string $area, string $startDate, string $endDate): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds) || ! in_array($area, UserRegional::AREAS, true)) {
            return [
                'outlets' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $scopeOutlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get()
            ->filter(fn ($o) => $this->outletMatchesArea($o->nama_outlet, $area))
            ->values();

        if ($scopeOutlets->isEmpty()) {
            return [
                'outlets' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $scopeOutletIds = $scopeOutlets->pluck('id_outlet')->all();

        $visitRows = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function ($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->whereIn('up.user_id', $userIds)
            ->whereIn('o.id_outlet', $scopeOutletIds)
            ->where('a.inoutmode', 1)
            ->where('a.scan_date', '>=', $startDate . ' 00:00:00')
            ->where('a.scan_date', '<=', $endDate . ' 23:59:59')
            ->select(
                'o.id_outlet',
                DB::raw('COUNT(DISTINCT DATE(a.scan_date)) as visit_days'),
                DB::raw('COUNT(*) as scan_in_count'),
                DB::raw('MAX(a.scan_date) as last_visit'),
            )
            ->groupBy('o.id_outlet')
            ->get()
            ->keyBy('id_outlet');

        $outlets = [];
        foreach ($scopeOutlets as $outlet) {
            $visit = $visitRows->get($outlet->id_outlet);
            $visitDays = (int) ($visit->visit_days ?? 0);

            $outlets[] = [
                'id_outlet' => (int) $outlet->id_outlet,
                'nama_outlet' => $outlet->nama_outlet,
                'visit_days' => $visitDays,
                'scan_in_count' => (int) ($visit->scan_in_count ?? 0),
                'last_visit' => $visit->last_visit ?? null,
                'last_visit_label' => $visit?->last_visit
                    ? date('d/m/Y H:i', strtotime($visit->last_visit))
                    : null,
                'frequency' => $visitDays === 0 ? 'never' : ($visitDays <= 2 ? 'rare' : ($visitDays <= 5 ? 'medium' : 'often')),
            ];
        }

        usort($outlets, fn ($a, $b) => $b['visit_days'] <=> $a['visit_days'] ?: strcmp($a['nama_outlet'], $b['nama_outlet']));

        $visited = collect($outlets)->where('visit_days', '>', 0)->count();
        $totalVisitDays = collect($outlets)->sum('visit_days');
        $maxVisit = collect($outlets)->max('visit_days') ?? 0;

        return [
            'outlets' => $outlets,
            'summary' => [
                'total_outlets' => count($outlets),
                'visited_outlets' => $visited,
                'never_visited_outlets' => count($outlets) - $visited,
                'total_visit_days' => $totalVisitDays,
                'max_visit_days' => $maxVisit,
                'visit_coverage_pct' => count($outlets) > 0
                    ? round(($visited / count($outlets)) * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * @return array{user_ids: array<int>, area: ?string}
     */
    public function resolveFilter(?int $userId, ?string $area): array
    {
        if ($userId) {
            $assignment = DB::table('user_regional')->where('user_id', $userId)->first();
            if (! $assignment) {
                return ['user_ids' => [], 'area' => null];
            }

            return [
                'user_ids' => [$userId],
                'area' => $assignment->area,
            ];
        }

        if ($area && in_array($area, UserRegional::AREAS, true)) {
            $userIds = DB::table('user_regional')
                ->where('area', $area)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return [
                'user_ids' => $userIds,
                'area' => $area,
            ];
        }

        return ['user_ids' => [], 'area' => null];
    }

    private function emptySummary(): array
    {
        return [
            'total_outlets' => 0,
            'visited_outlets' => 0,
            'never_visited_outlets' => 0,
            'total_visit_days' => 0,
            'max_visit_days' => 0,
            'visit_coverage_pct' => 0,
        ];
    }
}
