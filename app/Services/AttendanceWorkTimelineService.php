<?php

namespace App\Services;

/**
 * Menghitung jam kerja efektif dari timeline scan IN / OUT / KEMBALI.
 *
 * Aturan:
 * - IN(1) atau KEMBALI(4) membuka periode kerja; OUT(2) menutup periode.
 * - OUT diikuti IN  = pindah outlet; gap transfer tetap dihitung kerja.
 * - OUT diikuti KEMBALI = pulang; gap tidak dihitung kerja.
 * - Lembur = total jam kerja dibanding durasi shift (bukan span IN pertama–OUT terakhir).
 */
class AttendanceWorkTimelineService
{
    public const MODE_IN = 1;

    public const MODE_OUT = 2;

    public const MODE_KEMBALI = 4;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $allProcessedData
     * @return array<string, mixed>
     */
    public function processDay(array $data, array &$allProcessedData): array
    {
        $tanggal = $data['tanggal'];
        $userId = $data['user_id'];
        $dayScans = $this->normalizeScans($data['scans'] ?? []);

        $nextDay = date('Y-m-d', strtotime($tanggal.' +1 day'));
        $nextDayKey = $userId.'_'.$nextDay;
        $nextDayScans = isset($allProcessedData[$nextDayKey])
            ? $this->normalizeScans($allProcessedData[$nextDayKey]['scans'] ?? [])
            : [];

        [$timelineScans, $consumedScanDates] = $this->mergeCrossDayScans($dayScans, $nextDayScans);

        if (! empty($consumedScanDates) && isset($allProcessedData[$nextDayKey])) {
            $allProcessedData[$nextDayKey]['scans'] = collect($allProcessedData[$nextDayKey]['scans'])
                ->reject(fn (array $scan) => in_array($scan['scan_date'], $consumedScanDates, true))
                ->values()
                ->toArray();
        }

        $metrics = $this->calculateWorkMetrics($timelineScans);

        $inScans = collect($dayScans)->where('inoutmode', self::MODE_IN);
        $outScans = collect($dayScans)->where('inoutmode', self::MODE_OUT);
        $kembaliScans = collect($dayScans)->where('inoutmode', self::MODE_KEMBALI);

        $isCrossDay = $metrics['jam_keluar']
            && date('Y-m-d', strtotime($metrics['jam_keluar'])) !== $tanggal;

        return array_merge($metrics, [
            'tanggal' => $tanggal,
            'user_id' => $userId,
            'nama_lengkap' => $data['nama_lengkap'] ?? null,
            'total_masuk' => $inScans->count(),
            'total_keluar' => $outScans->count(),
            'total_kembali' => $kembaliScans->count(),
            'is_cross_day' => $isCrossDay,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $scans
     */
    public function calculateWorkMinutes(array $scans): int
    {
        return $this->calculateWorkMetrics($this->normalizeScans($scans))['work_minutes'];
    }

    /**
     * Lembur (jam, dibulatkan ke bawah) = selisih jam kerja efektif vs durasi shift.
     */
    public function calculateOvertimeHours(int $workMinutes, ?string $shiftStart, ?string $shiftEnd): int
    {
        if ($workMinutes <= 0 || ! $shiftStart || ! $shiftEnd) {
            return 0;
        }

        $shiftMinutes = $this->getShiftDurationMinutes($shiftStart, $shiftEnd);
        $excessMinutes = $workMinutes - $shiftMinutes;

        if ($excessMinutes <= 0) {
            return 0;
        }

        $hours = (int) floor($excessMinutes / 60);

        return min($hours, 12);
    }

    public function getShiftDurationMinutes(string $shiftStart, string $shiftEnd): int
    {
        $startTs = strtotime($shiftStart);
        $endTs = strtotime($shiftEnd);

        if ($startTs === false || $endTs === false) {
            return 0;
        }

        if ($endTs <= $startTs) {
            $endTs += 24 * 3600;
        }

        return (int) round(($endTs - $startTs) / 60);
    }

    /**
     * @param  array<int, array<string, mixed>>  $scans
     * @return array{
     *     jam_masuk: ?string,
     *     jam_keluar: ?string,
     *     work_minutes: int,
     *     last_outlet_id: ?int,
     *     has_no_checkout: bool
     * }
     */
    public function calculateWorkMetrics(array $scans): array
    {
        $scans = $this->normalizeScans($scans);
        $n = count($scans);

        $workSeconds = 0;
        $jamMasuk = null;
        $jamKeluar = null;
        $lastOutletId = null;

        foreach ($scans as $scan) {
            if ((int) $scan['inoutmode'] === self::MODE_IN) {
                $jamMasuk = $scan['scan_date'];
                break;
            }
        }

        if (! $jamMasuk) {
            foreach ($scans as $scan) {
                if ((int) $scan['inoutmode'] === self::MODE_KEMBALI) {
                    $jamMasuk = $scan['scan_date'];
                    break;
                }
            }
        }

        $i = 0;
        while ($i < $n) {
            $mode = (int) $scans[$i]['inoutmode'];

            if (! in_array($mode, [self::MODE_IN, self::MODE_KEMBALI], true)) {
                $i++;
                continue;
            }

            $startTs = strtotime($scans[$i]['scan_date']);
            if ($startTs === false) {
                $i++;
                continue;
            }

            $j = $i + 1;
            while ($j < $n && (int) $scans[$j]['inoutmode'] !== self::MODE_OUT) {
                $j++;
            }

            if ($j >= $n) {
                break;
            }

            $outTs = strtotime($scans[$j]['scan_date']);
            if ($outTs !== false) {
                $workSeconds += max(0, $outTs - $startTs);
                $lastOutletId = $scans[$j]['outlet_id'] ?? $scans[$i]['outlet_id'] ?? $lastOutletId;

                if ($j + 1 < $n && (int) $scans[$j + 1]['inoutmode'] === self::MODE_IN) {
                    $transferInTs = strtotime($scans[$j + 1]['scan_date']);
                    if ($transferInTs !== false) {
                        $workSeconds += max(0, $transferInTs - $outTs);
                    }
                }
            }

            $i = $j + 1;
        }

        for ($k = $n - 1; $k >= 0; $k--) {
            if ((int) $scans[$k]['inoutmode'] === self::MODE_OUT) {
                $jamKeluar = $scans[$k]['scan_date'];
                $lastOutletId = $scans[$k]['outlet_id'] ?? $lastOutletId;
                break;
            }
        }

        return [
            'jam_masuk' => $jamMasuk,
            'jam_keluar' => $jamKeluar,
            'work_minutes' => (int) floor($workSeconds / 60),
            'last_outlet_id' => $lastOutletId !== null ? (int) $lastOutletId : null,
            'has_no_checkout' => (bool) ($jamMasuk && ! $jamKeluar),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $dayScans
     * @param  array<int, array<string, mixed>>  $nextDayScans
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function mergeCrossDayScans(array $dayScans, array $nextDayScans): array
    {
        $merged = $dayScans;
        $consumed = [];

        if (empty($nextDayScans)) {
            return [$merged, $consumed];
        }

        $hasOpenBlock = $this->hasOpenWorkBlock($dayScans);
        $sameDayMetrics = $this->calculateWorkMetrics($dayScans);

        if (! $hasOpenBlock && $sameDayMetrics['jam_keluar']) {
            return [$merged, $consumed];
        }

        foreach ($nextDayScans as $scan) {
            $mode = (int) $scan['inoutmode'];

            if ($hasOpenBlock) {
                $merged[] = $scan;
                $consumed[] = $scan['scan_date'];

                if ($mode === self::MODE_OUT) {
                    break;
                }

                continue;
            }

            if ($mode !== self::MODE_OUT) {
                continue;
            }

            $outHour = (int) date('H', strtotime($scan['scan_date']));
            if ($outHour >= 0 && $outHour <= 12) {
                $merged[] = $scan;
                $consumed[] = $scan['scan_date'];
                break;
            }
        }

        usort($merged, fn (array $a, array $b) => strcmp($a['scan_date'], $b['scan_date']));

        return [$merged, $consumed];
    }

    /**
     * @param  array<int, array<string, mixed>>  $scans
     */
    private function hasOpenWorkBlock(array $scans): bool
    {
        $onDuty = false;

        foreach ($this->normalizeScans($scans) as $scan) {
            $mode = (int) $scan['inoutmode'];

            if (in_array($mode, [self::MODE_IN, self::MODE_KEMBALI], true)) {
                $onDuty = true;
            } elseif ($mode === self::MODE_OUT) {
                $onDuty = false;
            }
        }

        return $onDuty;
    }

    /**
     * @param  array<int, array<string, mixed>>  $scans
     * @return array<int, array<string, mixed>>
     */
    private function normalizeScans(array $scans): array
    {
        return collect($scans)
            ->map(function (array $scan) {
                return [
                    'scan_date' => $scan['scan_date'],
                    'inoutmode' => (int) ($scan['inoutmode'] ?? 0),
                    'outlet_id' => isset($scan['outlet_id']) ? (int) $scan['outlet_id'] : null,
                ];
            })
            ->sortBy('scan_date')
            ->values()
            ->all();
    }
}
