<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Periode absen/payroll yang dipakai Attendance Report:
 * 26 bulan sebelumnya s.d. 25 bulan label (bulan/tahun kalender).
 */
class AttendancePayrollPeriod
{
    /**
     * @return array{bulan: int, tahun: int, start: string, end: string, label: string}
     */
    public static function forMonth(?int $bulan = null, ?int $tahun = null): array
    {
        $bulan = $bulan ?: (int) date('m');
        $tahun = $tahun ?: (int) date('Y');

        $start = date('Y-m-d', strtotime(sprintf('%04d-%02d-26 -1 month', $tahun, $bulan)));
        $end = date('Y-m-d', strtotime(sprintf('%04d-%02d-25', $tahun, $bulan)));

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'start' => $start,
            'end' => $end,
            'label' => Carbon::parse($start)->translatedFormat('d M Y').' - '.Carbon::parse($end)->translatedFormat('d M Y'),
        ];
    }

    /**
     * Batas atas (exclusive) query att_log: sertakan 1 hari penuh setelah akhir periode
     * agar OUT cross-day shift malam (mis. checkout pagi tgl 26 untuk masuk tgl 25) ikut ter-load.
     */
    public static function scanQueryEndExclusive(string $periodEnd): string
    {
        return date('Y-m-d', strtotime($periodEnd.' +2 day')).' 00:00:00';
    }

    /**
     * Periode berjalan (label = bulan kalender hari ini), sama default report attendance.
     *
     * @return array{bulan: int, tahun: int, start: string, end: string, label: string}
     */
    public static function current(): array
    {
        return self::forMonth((int) date('m'), (int) date('Y'));
    }

    /**
     * Siklus payroll yang sedang berjalan berdasarkan tanggal (26 s.d. 25).
     * Mulai tgl 26, siklus berikutnya sudah aktif (bukan menunggu bulan kalender berganti).
     *
     * @return array{bulan: int, tahun: int, start: string, end: string, label: string}
     */
    public static function running(?\DateTimeInterface $at = null): array
    {
        $at = $at ? Carbon::parse($at) : Carbon::now();
        $bulan = (int) $at->format('n');
        $tahun = (int) $at->format('Y');
        if ((int) $at->format('j') >= 26) {
            $bulan++;
            if ($bulan > 12) {
                $bulan = 1;
                $tahun++;
            }
        }

        return self::forMonth($bulan, $tahun);
    }

    /**
     * Periode sebelumnya (1 siklus payroll sebelum periode berjalan).
     *
     * @return array{bulan: int, tahun: int, start: string, end: string, label: string}
     */
    public static function previous(): array
    {
        $bulan = (int) date('m') - 1;
        $tahun = (int) date('Y');
        if ($bulan < 1) {
            $bulan = 12;
            $tahun--;
        }

        return self::forMonth($bulan, $tahun);
    }

    /**
     * 1 siklus sebelum {@see running()}.
     *
     * @return array{bulan: int, tahun: int, start: string, end: string, label: string}
     */
    public static function previousRunning(?\DateTimeInterface $at = null): array
    {
        $running = self::running($at);
        $bulan = $running['bulan'] - 1;
        $tahun = $running['tahun'];
        if ($bulan < 1) {
            $bulan = 12;
            $tahun--;
        }

        return self::forMonth($bulan, $tahun);
    }

    /**
     * Jendela antrian HRD: siklus payroll berjalan + (sementara) 1 siklus sebelumnya.
     * Contoh 27 Agustus 2026: 26 Jul–25 Agu dan 26 Agu–25 Sep.
     *
     * @return array{
     *     start: string,
     *     end: string,
     *     label: string,
     *     include_previous: bool,
     *     current: array{bulan: int, tahun: int, start: string, end: string, label: string},
     *     previous: ?array{bulan: int, tahun: int, start: string, end: string, label: string}
     * }
     */
    public static function forHrdApprovalQueue(?\DateTimeInterface $at = null): array
    {
        $current = self::running($at);

        // Sementara: tampilkan juga backlog 1 siklus sebelumnya.
        $includePrevious = true;
        if (! $includePrevious) {
            return [
                'start' => $current['start'],
                'end' => $current['end'],
                'label' => $current['label'],
                'include_previous' => false,
                'current' => $current,
                'previous' => null,
            ];
        }

        $previous = self::previousRunning($at);

        return [
            'start' => $previous['start'],
            'end' => $current['end'],
            'label' => $previous['label'].' + '.$current['label'],
            'include_previous' => true,
            'current' => $current,
            'previous' => $previous,
        ];
    }
}
