<?php

namespace App\Services;

use App\Models\UserRegional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegionalVisitAnalyticsService
{
    private const HARI_LABELS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function payrollPeriod(int $bulan, int $tahun): array
    {
        $startDate = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
        $endDate = date('Y-m-d', strtotime("$tahun-$bulan-25"));

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
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
     * $userIds berasal dari user_regional (per karyawan atau semua user pada area yang sama).
     * Area hanya menentukan user mana yang dihitung — bukan filter outlet.
     *
     * @param  array<int>  $userIds
     * @return array{
     *   outlets: array<int, array<string, mixed>>,
     *   summary: array<string, int|float|null>
     * }
     */
    public function getVisitStats(array $userIds, string $startDate, string $endDate): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds)) {
            return [
                'outlets' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $scopeOutlets = $this->getScopeOutlets();
        if ($scopeOutlets->isEmpty()) {
            return [
                'outlets' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $visitsBySn = $this->fetchVisitsBySn($userIds, $startDate, $endDate);
        $primaryOutletBySn = $this->primaryOutletBySn($scopeOutlets);

        $outlets = [];
        foreach ($scopeOutlets as $outlet) {
            $sn = trim((string) ($outlet->sn ?? ''));
            $isPrimaryForSn = $sn !== ''
                && isset($primaryOutletBySn[$sn])
                && (int) $primaryOutletBySn[$sn] === (int) $outlet->id_outlet;

            $visit = $isPrimaryForSn ? $visitsBySn->get($sn) : null;
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
                'regional_user_count' => count($userIds),
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
     * Detail kunjungan satu outlet: per hari + frekuensi per jam.
     *
     * @param  array<int>  $userIds
     * @return array<string, mixed>
     */
    public function getOutletVisitDetail(array $userIds, int $outletId, string $startDate, string $endDate): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet', 'sn')
            ->first();

        if (! $outlet || empty($userIds)) {
            return $this->emptyOutletDetail($outlet);
        }

        $sn = trim((string) ($outlet->sn ?? ''));
        if ($sn === '') {
            return $this->emptyOutletDetail($outlet);
        }

        $scopeOutlets = $this->getScopeOutlets();
        $primaryOutletBySn = $this->primaryOutletBySn($scopeOutlets);
        if ((int) ($primaryOutletBySn[$sn] ?? 0) !== $outletId) {
            return $this->emptyOutletDetail($outlet);
        }

        $pins = DB::table('user_pins')
            ->whereIn('user_id', $userIds)
            ->distinct()
            ->pluck('pin');

        if ($pins->isEmpty()) {
            return $this->emptyOutletDetail($outlet);
        }

        $rows = DB::table('att_log as a')
            ->join('user_pins as up', 'a.pin', '=', 'up.pin')
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->whereIn('up.user_id', $userIds)
            ->whereIn('a.pin', $pins)
            ->where('a.sn', $sn)
            ->where('a.inoutmode', 1)
            ->where('a.scan_date', '>=', $startDate . ' 00:00:00')
            ->where('a.scan_date', '<=', $endDate . ' 23:59:59')
            ->select(
                'a.scan_date',
                'a.pin',
                'u.id as user_id',
                'u.nama_lengkap as user_name',
            )
            ->orderBy('a.scan_date')
            ->get()
            ->unique(fn ($row) => $row->scan_date . '|' . $row->pin . '|' . $row->user_id)
            ->values();

        $hourly = array_fill(0, 24, 0);
        $byDate = [];

        foreach ($rows as $row) {
            $ts = strtotime($row->scan_date);
            $date = date('Y-m-d', $ts);
            $hour = (int) date('G', $ts);
            $hourly[$hour]++;

            if (! isset($byDate[$date])) {
                $byDate[$date] = [
                    'tanggal' => $date,
                    'tanggal_label' => date('d/m/Y', $ts),
                    'hari' => self::HARI_LABELS[(int) date('w', $ts)],
                    'scan_in_count' => 0,
                    'users' => [],
                    'scans' => [],
                ];
            }

            $byDate[$date]['scan_in_count']++;
            $byDate[$date]['users'][$row->user_id] = $row->user_name;
            $byDate[$date]['scans'][] = [
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'jam' => date('H:i', $ts),
                'jam_label' => date('H:i:s', $ts),
            ];
        }

        krsort($byDate);

        $dailyVisits = [];
        foreach ($byDate as $day) {
            $day['users'] = collect($day['users'])
                ->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])
                ->values()
                ->all();
            $dailyVisits[] = $day;
        }

        $visitDays = count($byDate);

        return [
            'outlet' => [
                'id_outlet' => (int) $outlet->id_outlet,
                'nama_outlet' => $outlet->nama_outlet,
            ],
            'summary' => [
                'visit_days' => $visitDays,
                'scan_in_count' => $rows->count(),
                'unique_visitors' => $rows->pluck('user_id')->unique()->count(),
            ],
            'daily_visits' => $dailyVisits,
            'hourly_frequency' => [
                'labels' => collect(range(0, 23))->map(fn ($h) => sprintf('%02d:00', $h))->all(),
                'data' => $hourly,
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

    private function getScopeOutlets(): Collection
    {
        return DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet', 'sn')
            ->orderBy('nama_outlet')
            ->get();
    }

    /**
     * Agregasi kunjungan per SN mesin absensi — hindari duplikasi saat banyak outlet share SN.
     *
     * @param  array<int>  $userIds
     */
    private function fetchVisitsBySn(array $userIds, string $startDate, string $endDate): Collection
    {
        $pins = DB::table('user_pins')
            ->whereIn('user_id', $userIds)
            ->distinct()
            ->pluck('pin');

        if ($pins->isEmpty()) {
            return collect();
        }

        return DB::table('att_log as a')
            ->whereIn('a.pin', $pins)
            ->where('a.inoutmode', 1)
            ->whereNotNull('a.sn')
            ->where('a.sn', '!=', '')
            ->where('a.scan_date', '>=', $startDate . ' 00:00:00')
            ->where('a.scan_date', '<=', $endDate . ' 23:59:59')
            ->select(
                'a.sn',
                DB::raw('COUNT(DISTINCT DATE(a.scan_date)) as visit_days'),
                DB::raw('COUNT(*) as scan_in_count'),
                DB::raw('MAX(a.scan_date) as last_visit'),
            )
            ->groupBy('a.sn')
            ->get()
            ->keyBy('sn');
    }

    /**
     * SN yang dipakai banyak outlet hanya dihitung ke outlet utama (id_outlet terkecil).
     *
     * @return array<string, int>
     */
    private function primaryOutletBySn(Collection $outlets): array
    {
        $map = [];

        foreach ($outlets as $outlet) {
            $sn = trim((string) ($outlet->sn ?? ''));
            if ($sn === '') {
                continue;
            }

            if (! isset($map[$sn]) || (int) $outlet->id_outlet < $map[$sn]) {
                $map[$sn] = (int) $outlet->id_outlet;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyOutletDetail(?object $outlet): array
    {
        return [
            'outlet' => [
                'id_outlet' => (int) ($outlet->id_outlet ?? 0),
                'nama_outlet' => $outlet->nama_outlet ?? 'Outlet',
            ],
            'summary' => [
                'visit_days' => 0,
                'scan_in_count' => 0,
                'unique_visitors' => 0,
            ],
            'daily_visits' => [],
            'hourly_frequency' => [
                'labels' => collect(range(0, 23))->map(fn ($h) => sprintf('%02d:00', $h))->all(),
                'data' => array_fill(0, 24, 0),
            ],
        ];
    }

    private function emptySummary(): array
    {
        return [
            'regional_user_count' => 0,
            'total_outlets' => 0,
            'visited_outlets' => 0,
            'never_visited_outlets' => 0,
            'total_visit_days' => 0,
            'max_visit_days' => 0,
            'visit_coverage_pct' => 0,
        ];
    }
}
