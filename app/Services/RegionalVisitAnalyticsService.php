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
     * @param  array<int>  $userIds
     * @return array{
     *   outlets: array<int, array<string, mixed>>,
     *   summary: array<string, int|float|null>,
     *   hourly_frequency: array{labels: array<int, string>, data: array<int, int>}
     * }
     */
    public function getVisitStats(array $userIds, string $startDate, string $endDate): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds)) {
            return [
                'outlets' => [],
                'summary' => $this->emptySummary(),
                'hourly_frequency' => $this->emptyHourlyFrequency(),
            ];
        }

        $scopeOutlets = $this->getScopeOutlets();
        if ($scopeOutlets->isEmpty()) {
            return [
                'outlets' => [],
                'summary' => $this->emptySummary(),
                'hourly_frequency' => $this->emptyHourlyFrequency(),
            ];
        }

        $visitRows = $this->attendanceQuery($userIds, $startDate, $endDate)
            ->select(
                'o.id_outlet',
                DB::raw('COUNT(DISTINCT DATE(a.scan_date)) as visit_days'),
                DB::raw('COUNT(*) as scan_in_count'),
                DB::raw('MAX(a.scan_date) as last_visit'),
            )
            ->groupBy('o.id_outlet')
            ->get()
            ->keyBy('id_outlet');

        $visitorsByOutlet = $this->fetchVisitorsByOutlet($userIds, $startDate, $endDate);

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
                'visitors' => $this->mapOutletVisitors($visitorsByOutlet->get($outlet->id_outlet)),
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
            'hourly_frequency' => $this->buildHourlyFrequency(
                $this->attendanceQuery($userIds, $startDate, $endDate)
                    ->select('a.scan_date')
                    ->get()
            ),
        ];
    }

    /**
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
            ->whereNotNull('sn')
            ->where('sn', '!=', '')
            ->select('id_outlet', 'nama_outlet')
            ->first();

        if (! $outlet || empty($userIds)) {
            return $this->emptyOutletDetail($outlet);
        }

        $processedData = $this->buildOutletProcessedData($userIds, $outletId, $startDate, $endDate);

        $period = [];
        $dt = new \DateTime($startDate);
        $dtEnd = new \DateTime($endDate);
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }

        $byDate = [];
        $totalMinutes = 0;
        $totalScanIn = 0;
        $totalScanOut = 0;
        $uniqueVisitors = [];

        foreach ($period as $tanggal) {
            $nextDay = date('Y-m-d', strtotime($tanggal . ' +1 day'));
            $daySessions = [];

            foreach ($processedData as $key => $data) {
                if ($data['tanggal'] !== $tanggal) {
                    continue;
                }

                $scans = collect($data['scans'])->sortBy('scan_date');
                $scanInCount = $scans->where('inoutmode', 1)->count();
                $scanOutCount = $scans->where('inoutmode', 2)->count();

                if ($scanInCount === 0 && $scanOutCount === 0) {
                    continue;
                }

                $paired = $this->pairOutletDayAttendance($processedData, $data, $tanggal, $nextDay);
                $durasiMenit = 0;
                $isCrossDay = false;

                if ($paired['jam_in'] && $paired['jam_out']) {
                    $durasiMenit = max(0, (int) round((strtotime($paired['jam_out']) - strtotime($paired['jam_in'])) / 60));
                    $isCrossDay = date('Y-m-d', strtotime($paired['jam_out'])) !== $tanggal;
                }

                $scanDetails = $scans->map(function ($scan) {
                    $isIn = (int) $scan['inoutmode'] === 1;

                    return [
                        'type' => $isIn ? 'IN' : 'OUT',
                        'user_name' => $scan['user_name'],
                        'jam' => date('H:i', strtotime($scan['scan_date'])),
                        'jam_label' => date('H:i:s', strtotime($scan['scan_date'])),
                    ];
                })->values()->all();

                if ($scanInCount > 0) {
                    $uniqueVisitors[$data['user_id']] = true;
                }

                $daySessions[] = [
                    'user_id' => (int) $data['user_id'],
                    'user_name' => $data['user_name'],
                    'jam_masuk_display' => $paired['jam_in'] ? date('H:i', strtotime($paired['jam_in'])) : null,
                    'jam_keluar_display' => $paired['jam_out'] ? date('H:i', strtotime($paired['jam_out'])) : null,
                    'durasi_menit' => $durasiMenit,
                    'durasi_label' => $this->formatDurationLabel($durasiMenit),
                    'has_no_checkout' => $paired['has_no_checkout'],
                    'is_cross_day' => $isCrossDay,
                    'scan_in_count' => $scanInCount,
                    'scan_out_count' => $scanOutCount,
                    'scans' => $scanDetails,
                ];

                $totalScanIn += $scanInCount;
                $totalScanOut += $scanOutCount;
                $totalMinutes += $durasiMenit;
            }

            if (empty($daySessions)) {
                continue;
            }

            usort($daySessions, fn ($a, $b) => strcmp($a['user_name'], $b['user_name']));

            $byDate[$tanggal] = [
                'tanggal' => $tanggal,
                'tanggal_label' => date('d/m/Y', strtotime($tanggal)),
                'hari' => self::HARI_LABELS[(int) date('w', strtotime($tanggal))],
                'scan_in_count' => collect($daySessions)->sum('scan_in_count'),
                'sessions' => $daySessions,
            ];
        }

        krsort($byDate);

        $inScans = $this->attendanceQuery($userIds, $startDate, $endDate)
            ->where('o.id_outlet', $outletId)
            ->select('a.scan_date')
            ->get();

        return [
            'outlet' => [
                'id_outlet' => (int) $outlet->id_outlet,
                'nama_outlet' => $outlet->nama_outlet,
            ],
            'summary' => [
                'visit_days' => count($byDate),
                'scan_in_count' => $totalScanIn,
                'scan_out_count' => $totalScanOut,
                'unique_visitors' => count($uniqueVisitors),
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
            ],
            'daily_visits' => array_values($byDate),
            'hourly_frequency' => $this->buildHourlyFrequency($inScans),
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

    /**
     * @param  array<int>  $userIds
     */
    private function attendanceQuery(array $userIds, string $startDate, string $endDate)
    {
        return DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function ($q) {
                $q->on('a.pin', '=', 'up.pin')
                    ->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->whereIn('up.user_id', $userIds)
            ->where('o.is_outlet', 1)
            ->where('o.status', 'A')
            ->whereNotNull('o.sn')
            ->where('o.sn', '!=', '')
            ->where('a.inoutmode', 1)
            ->where('a.scan_date', '>=', $startDate . ' 00:00:00')
            ->where('a.scan_date', '<=', $endDate . ' 23:59:59');
    }

    /**
     * @param  array<int>  $userIds
     * @return array<string, array<string, mixed>>
     */
    private function buildOutletProcessedData(array $userIds, int $outletId, string $startDate, string $endDate): array
    {
        $fetchUntil = date('Y-m-d', strtotime($endDate . ' +2 day'));

        $rows = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function ($q) {
                $q->on('a.pin', '=', 'up.pin')
                    ->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->whereIn('up.user_id', $userIds)
            ->where('o.id_outlet', $outletId)
            ->where('o.is_outlet', 1)
            ->where('o.status', 'A')
            ->whereNotNull('o.sn')
            ->where('o.sn', '!=', '')
            ->where('a.scan_date', '>=', $startDate . ' 00:00:00')
            ->where('a.scan_date', '<', $fetchUntil . ' 00:00:00')
            ->select(
                'u.id as user_id',
                'u.nama_lengkap as user_name',
                'a.scan_date',
                'a.inoutmode',
            )
            ->orderBy('a.scan_date')
            ->get();

        $processedData = [];
        foreach ($rows as $row) {
            $date = date('Y-m-d', strtotime($row->scan_date));
            $key = $row->user_id . '_' . $date;

            if (! isset($processedData[$key])) {
                $processedData[$key] = [
                    'user_id' => (int) $row->user_id,
                    'user_name' => $row->user_name,
                    'tanggal' => $date,
                    'id_outlet' => $outletId,
                    'scans' => [],
                ];
            }

            $processedData[$key]['scans'][] = [
                'scan_date' => $row->scan_date,
                'inoutmode' => (int) $row->inoutmode,
                'user_name' => $row->user_name,
            ];
        }

        return $processedData;
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    private function buildHourlyFrequency(Collection $rows): array
    {
        $hourly = array_fill(0, 24, 0);

        foreach ($rows as $row) {
            $hour = (int) date('G', strtotime($row->scan_date));
            $hourly[$hour]++;
        }

        return [
            'labels' => collect(range(0, 23))->map(fn ($h) => sprintf('%02d:00', $h))->all(),
            'data' => $hourly,
        ];
    }

    /**
     * @return array{jam_in: ?string, jam_out: ?string, has_no_checkout: bool}
     */
    private function pairOutletDayAttendance(array $processedData, array $data, string $tanggal, string $nextDay): array
    {
        $scans = collect($data['scans'])->sortBy('scan_date');
        $inScans = $scans->where('inoutmode', 1);
        $outScans = $scans->where('inoutmode', 2);

        $jamIn = $inScans->first()['scan_date'] ?? null;
        $jamOut = null;

        if ($jamIn) {
            $sameDayOuts = $outScans->where('scan_date', '>', $jamIn);

            $nextDayKey = $data['user_id'] . '_' . $nextDay;
            $nextDayOuts = collect();

            if (isset($processedData[$nextDayKey])) {
                $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                $nextDayOuts = $nextDayScans->where('inoutmode', 2);
            }

            if ($sameDayOuts->isNotEmpty() && $nextDayOuts->isNotEmpty()) {
                $lastSameDayOut = $sameDayOuts->last()['scan_date'];
                $firstNextDayOut = $nextDayOuts->first()['scan_date'];
                $sameDayDuration = strtotime($lastSameDayOut) - strtotime($jamIn);
                $outHour = (int) date('H', strtotime($firstNextDayOut));

                if ($sameDayDuration < 18000 || ($outHour >= 0 && $outHour <= 6)) {
                    $jamOut = $firstNextDayOut;
                } else {
                    $jamOut = $lastSameDayOut;
                }
            } elseif ($sameDayOuts->isNotEmpty()) {
                $jamOut = $sameDayOuts->last()['scan_date'];
            } elseif ($nextDayOuts->isNotEmpty()) {
                $firstNextDayOut = $nextDayOuts->first()['scan_date'];
                $outHour = (int) date('H', strtotime($firstNextDayOut));

                if ($outHour >= 0 && $outHour <= 12) {
                    $jamOut = $firstNextDayOut;
                }
            }
        }

        return [
            'jam_in' => $jamIn,
            'jam_out' => $jamOut,
            'has_no_checkout' => $jamIn && ! $jamOut,
        ];
    }

    private function formatDurationLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}j {$mins}m";
        }
        if ($hours > 0) {
            return "{$hours}j";
        }

        return "{$mins}m";
    }

    /**
     * @param  array<int>  $userIds
     */
    private function fetchVisitorsByOutlet(array $userIds, string $startDate, string $endDate): Collection
    {
        return $this->attendanceQuery($userIds, $startDate, $endDate)
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->select(
                'o.id_outlet',
                'u.id as user_id',
                'u.nama_lengkap as name',
                'u.avatar',
                'u.upload_latest_color_photo',
                'j.nama_jabatan',
            )
            ->distinct()
            ->orderBy('u.nama_lengkap')
            ->get()
            ->groupBy('id_outlet');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapOutletVisitors(?Collection $rows): array
    {
        if (! $rows || $rows->isEmpty()) {
            return [];
        }

        return $rows->map(fn ($row) => [
            'id' => (int) $row->user_id,
            'name' => $row->name,
            'nama_jabatan' => $row->nama_jabatan ?: '-',
            'avatar' => $row->avatar,
            'photo' => $row->upload_latest_color_photo,
            'initials' => $this->userInitials($row->name),
        ])->values()->all();
    }

    private function userInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr(trim($name), 0, 2));
    }

    private function getScopeOutlets(): Collection
    {
        return DB::table('tbl_data_outlet')
            ->where('is_outlet', 1)
            ->where('status', 'A')
            ->whereNotNull('sn')
            ->where('sn', '!=', '')
            ->select('id_outlet', 'nama_outlet', 'sn')
            ->orderBy('nama_outlet')
            ->get();
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
                'scan_out_count' => 0,
                'unique_visitors' => 0,
                'total_minutes' => 0,
                'total_hours' => 0,
            ],
            'daily_visits' => [],
            'hourly_frequency' => $this->emptyHourlyFrequency(),
        ];
    }

    private function emptyHourlyFrequency(): array
    {
        return [
            'labels' => collect(range(0, 23))->map(fn ($h) => sprintf('%02d:00', $h))->all(),
            'data' => array_fill(0, 24, 0),
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
