<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Statistik kehadiran per outlet — pairing IN/OUT mengikuti AttendanceReportController::detail()
 * (cross-day OUT, same-day vs next-day, dll.).
 */
class AttendanceOutletAnalyticsService
{
    private const HARI_LABELS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOutletStatsForPeriod(int $userId, string $startDate, string $endDate): array
    {
        $processedData = $this->buildProcessedData($userId, $startDate, $endDate);

        $period = [];
        $dt = new \DateTime($startDate);
        $dtEnd = new \DateTime($endDate);
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }

        $outletStats = [];

        foreach ($period as $tanggal) {
            $nextDay = date('Y-m-d', strtotime($tanggal . ' +1 day'));

            foreach ($processedData as $data) {
                if ($data['tanggal'] !== $tanggal) {
                    continue;
                }

                $outletId = $data['id_outlet'];
                if (! isset($outletStats[$outletId])) {
                    $outletStats[$outletId] = [
                        'id_outlet' => $outletId,
                        'nama_outlet' => $data['nama_outlet'],
                        'scan_in' => 0,
                        'scan_out' => 0,
                        'total_minutes' => 0,
                        'total_hours' => 0,
                        'sessions' => 0,
                        'no_checkout_sessions' => 0,
                        'scan_in_percentage' => 0,
                    ];
                }

                $scans = collect($data['scans'])->sortBy('scan_date');
                $outletStats[$outletId]['scan_in'] += $scans->where('inoutmode', 1)->count();
                $outletStats[$outletId]['scan_out'] += $scans->where('inoutmode', 2)->count();

                $paired = $this->pairOutletDayAttendance($processedData, $data, $tanggal, $nextDay);

                if ($paired['jam_in']) {
                    $outletStats[$outletId]['sessions']++;
                    if ($paired['jam_out']) {
                        $minutes = (strtotime($paired['jam_out']) - strtotime($paired['jam_in'])) / 60;
                        $outletStats[$outletId]['total_minutes'] += max(0, (int) round($minutes));
                    } else {
                        $outletStats[$outletId]['no_checkout_sessions']++;
                    }
                }
            }
        }

        $totalScanIn = array_sum(array_column($outletStats, 'scan_in'));

        $result = [];
        foreach ($outletStats as $stat) {
            $stat['total_hours'] = round($stat['total_minutes'] / 60, 2);
            $stat['total_minutes'] = (int) $stat['total_minutes'];
            $stat['scan_in_percentage'] = $totalScanIn > 0
                ? round(($stat['scan_in'] / $totalScanIn) * 100, 1)
                : 0;
            $result[] = $stat;
        }

        usort($result, fn ($a, $b) => $b['scan_in'] <=> $a['scan_in']);

        return $result;
    }

    /**
     * Detail sesi absensi per hari untuk satu outlet (untuk modal chart/tabel).
     *
     * @return array{outlet: array<string, mixed>, sessions: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function getOutletSessionDetails(int $userId, int $outletId, string $startDate, string $endDate): array
    {
        $processedData = $this->buildProcessedData($userId, $startDate, $endDate, $outletId);

        $outletName = null;
        foreach ($processedData as $data) {
            if ((int) $data['id_outlet'] === $outletId) {
                $outletName = $data['nama_outlet'];
                break;
            }
        }

        if (! $outletName) {
            $outletName = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->value('nama_outlet') ?? 'Outlet';
        }

        $period = [];
        $dt = new \DateTime($startDate);
        $dtEnd = new \DateTime($endDate);
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }

        $sessions = [];
        $totalMinutes = 0;
        $totalScanIn = 0;
        $totalScanOut = 0;

        foreach ($period as $tanggal) {
            $key = $outletId . '_' . $tanggal;
            if (! isset($processedData[$key])) {
                continue;
            }

            $data = $processedData[$key];
            $nextDay = date('Y-m-d', strtotime($tanggal . ' +1 day'));
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
                    'tanggal' => date('Y-m-d', strtotime($scan['scan_date'])),
                    'hari' => self::HARI_LABELS[(int) date('w', strtotime($scan['scan_date']))],
                    'jam' => date('H:i:s', strtotime($scan['scan_date'])),
                    'datetime' => $scan['scan_date'],
                ];
            })->values()->all();

            $sessions[] = [
                'tanggal' => $tanggal,
                'hari' => self::HARI_LABELS[(int) date('w', strtotime($tanggal))],
                'tanggal_label' => date('d/m/Y', strtotime($tanggal)),
                'jam_masuk' => $paired['jam_in'] ? date('H:i:s', strtotime($paired['jam_in'])) : null,
                'jam_keluar' => $paired['jam_out'] ? date('H:i:s', strtotime($paired['jam_out'])) : null,
                'jam_masuk_display' => $paired['jam_in'] ? date('H:i', strtotime($paired['jam_in'])) : null,
                'jam_keluar_display' => $paired['jam_out'] ? date('H:i', strtotime($paired['jam_out'])) : null,
                'durasi_menit' => $durasiMenit,
                'durasi_label' => $this->formatDurationLabel($durasiMenit),
                'is_cross_day' => $isCrossDay,
                'has_no_checkout' => $paired['has_no_checkout'],
                'scan_in' => $scanInCount,
                'scan_out' => $scanOutCount,
                'scans' => $scanDetails,
            ];

            $totalScanIn += $scanInCount;
            $totalScanOut += $scanOutCount;
            $totalMinutes += $durasiMenit;
        }

        return [
            'outlet' => [
                'id_outlet' => $outletId,
                'nama_outlet' => $outletName,
            ],
            'sessions' => $sessions,
            'summary' => [
                'total_sessions' => count($sessions),
                'total_scan_in' => $totalScanIn,
                'total_scan_out' => $totalScanOut,
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
                'no_checkout_sessions' => collect($sessions)->where('has_no_checkout', true)->count(),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildProcessedData(int $userId, string $startDate, string $endDate, ?int $outletId = null): array
    {
        $fetchUntil = date('Y-m-d', strtotime($endDate . ' +2 day'));

        $query = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function ($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->where('up.user_id', $userId)
            ->where('a.scan_date', '>=', $startDate . ' 00:00:00')
            ->where('a.scan_date', '<', $fetchUntil . ' 00:00:00')
            ->select(
                'o.id_outlet',
                'o.nama_outlet',
                'a.scan_date',
                'a.inoutmode'
            )
            ->orderBy('a.scan_date');

        if ($outletId !== null) {
            $query->where('o.id_outlet', $outletId);
        }

        $rows = $query->get();

        $processedData = [];
        foreach ($rows as $row) {
            $date = date('Y-m-d', strtotime($row->scan_date));
            $key = $row->id_outlet . '_' . $date;

            if (! isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'id_outlet' => $row->id_outlet,
                    'nama_outlet' => $row->nama_outlet,
                    'scans' => [],
                ];
            }

            $processedData[$key]['scans'][] = [
                'scan_date' => $row->scan_date,
                'inoutmode' => $row->inoutmode,
            ];
        }

        return $processedData;
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
     * Pairing IN/OUT per outlet per hari — logika sama AttendanceReportController::detail().
     *
     * @return array{jam_in: ?string, jam_out: ?string, total_in: int, total_out: int, has_no_checkout: bool}
     */
    private function pairOutletDayAttendance(array $processedData, array $data, string $tanggal, string $nextDay): array
    {
        $scans = collect($data['scans'])->sortBy('scan_date');
        $inScans = $scans->where('inoutmode', 1);
        $outScans = $scans->where('inoutmode', 2);

        $jamIn = $inScans->first()['scan_date'] ?? null;
        $jamOut = null;
        $totalIn = $inScans->count();
        $totalOut = $outScans->count();

        if ($jamIn) {
            $sameDayOuts = $outScans->where('scan_date', '>', $jamIn);

            $nextDayKey = $data['id_outlet'] . '_' . $nextDay;
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
                    $totalOut = 1;
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
                    $totalOut = 1;
                }
            }
        }

        $hasNoCheckout = $jamIn && ! $jamOut;

        return [
            'jam_in' => $jamIn,
            'jam_out' => $jamOut,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'has_no_checkout' => $hasNoCheckout,
        ];
    }
}
