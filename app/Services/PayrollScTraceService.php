<?php

namespace App\Services;

use App\Http\Controllers\PayrollReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Response as InertiaResponse;
use ReflectionProperty;

/**
 * Trace & bandingkan perhitungan SC ERP vs formula Excel manual.
 */
class PayrollScTraceService
{
    /** Referensi Excel manual (screenshot user). Key = nama lowercase trim. */
    public const EXCEL_REFERENCE = [
        'achmad yusuf bachtiar' => ['poin' => 3, 'hari' => 21, 'total_sc' => 787409],
        'agung setiawan' => ['poin' => 4, 'hari' => 25, 'total_sc' => 1119106],
        'iqbal hamdani' => ['poin' => 2, 'hari' => 16, 'total_sc' => 483634],
        'monita' => ['poin' => 4, 'hari' => 22, 'total_sc' => 984813],
        'hanhan anwari' => ['poin' => 2, 'hari' => 3, 'total_sc' => 90681],
        'sandy insyiro prayoga' => ['poin' => 2, 'hari' => 4, 'total_sc' => 120909],
    ];

    public const EXCEL_POOL_TOTALS = [
        'pool' => 22750551,
        'sum_hari' => 725,
        'sum_poin_hari' => 1565,
    ];

    public function run(int $outletId, int $year, int $month, ?float $serviceChargeOverride = null): array
    {
        $request = Request::create('/payroll/report', 'GET', array_filter([
            'outlet_id' => $outletId,
            'year' => $year,
            'month' => $month,
            'service_charge' => $serviceChargeOverride,
        ], fn ($v) => $v !== null));

        /** @var InertiaResponse $inertiaResponse */
        $inertiaResponse = app(PayrollReportController::class)->index($request);
        $props = $this->extractInertiaProps($inertiaResponse, $request);

        $payrollData = collect($props['payrollData'] ?? []);
        $filter = $props['filter'] ?? [];
        $pool = (float) ($serviceChargeOverride ?? ($filter['service_charge'] ?? 0));

        $scRows = $this->buildScRows($payrollData, $pool, $filter);
        $comparison = $this->compareWithExcel($scRows);

        return [
            'outlet_id' => $outletId,
            'year' => $year,
            'month' => $month,
            'pool' => $pool,
            'filter' => $filter,
            'erp_totals' => $comparison['erp_totals'],
            'excel_totals' => self::EXCEL_POOL_TOTALS,
            'rows' => $comparison['rows'],
            'mismatches' => $comparison['mismatches'],
            'only_in_erp' => $comparison['only_in_erp'],
            'only_in_excel' => $comparison['only_in_excel'],
        ];
    }

    private function extractInertiaProps(InertiaResponse $response, Request $request): array
    {
        $ref = new ReflectionProperty(InertiaResponse::class, 'props');
        $ref->setAccessible(true);
        $rawProps = $ref->getValue($response);

        return $response->resolveProperties($request, $rawProps);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payrollData
     * @return list<array<string, mixed>>
     */
    /**
     * @param  array<string, mixed>  $filter
     */
    private function buildScRows(Collection $payrollData, float $pool, array $filter): array
    {
        $eligible = $payrollData->filter(function (array $row) {
            $master = $row['master_data'] ?? null;

            return (int) (is_object($master) ? ($master->sc ?? 0) : ($master['sc'] ?? 0)) === 1;
        });

        $sumHariGajian2 = 0;
        $sumHariKerja = 0;
        $sumPoinHariGajian2 = 0;
        $sumPoinHariKerja = 0;

        foreach ($eligible as $row) {
            $hariG2 = (int) ($row['hari_kerja_gajian2'] ?? 0);
            $hariG1 = (int) ($row['hari_kerja'] ?? 0);
            $poin = (int) ($row['point'] ?? 0);
            $sumHariGajian2 += $hariG2;
            $sumHariKerja += $hariG1;
            $sumPoinHariGajian2 += $poin * $hariG2;
            $sumPoinHariKerja += $poin * $hariG1;
        }

        $half = $pool / 2;
        $rateProG2 = $sumHariGajian2 > 0 ? $half / $sumHariGajian2 : 0;
        $ratePointG2 = $sumPoinHariGajian2 > 0 ? $half / $sumPoinHariGajian2 : 0;
        $rateProG1 = $sumHariKerja > 0 ? $half / $sumHariKerja : 0;
        $ratePointG1 = $sumPoinHariKerja > 0 ? $half / $sumPoinHariKerja : 0;

        $totalSimG2 = 0;
        $totalSimG1 = 0;
        $totalErpSc = 0;

        $rows = [];
        foreach ($eligible as $row) {
            $nama = (string) ($row['nama_lengkap'] ?? '');
            $hariG2 = (int) ($row['hari_kerja_gajian2'] ?? 0);
            $hariG1 = (int) ($row['hari_kerja'] ?? 0);
            $poin = (int) ($row['point'] ?? 0);
            $poinHariG2 = $poin * $hariG2;
            $poinHariG1 = $poin * $hariG1;

            $excelScG2 = $hariG2 > 0
                ? (int) round($rateProG2 * $hariG2 + $ratePointG2 * $poinHariG2)
                : 0;
            $excelScG1 = $hariG1 > 0
                ? (int) round($rateProG1 * $hariG1 + $ratePointG1 * $poinHariG1)
                : 0;

            $erpSc = (int) round((float) ($row['service_charge'] ?? 0));
            $erpByPoint = (int) round((float) ($row['service_charge_by_point'] ?? 0));
            $erpProRate = (int) round((float) ($row['service_charge_pro_rate'] ?? 0));

            $totalSimG2 += $excelScG2;
            $totalSimG1 += $excelScG1;
            $totalErpSc += $erpSc;

            $ref = self::EXCEL_REFERENCE[$this->normName($nama)] ?? null;

            $rows[] = [
                'nik' => $row['nik'] ?? '',
                'nama' => $nama,
                'poin' => $poin,
                'hari_kerja' => $hariG1,
                'hari_kerja_gajian2' => $hariG2,
                'poin_x_hari_g2' => $poinHariG2,
                'poin_x_hari_g1' => $poinHariG1,
                'erp_sc' => $erpSc,
                'erp_sc_by_point' => $erpByPoint,
                'erp_sc_pro_rate' => $erpProRate,
                'simulasi_excel_g2' => $excelScG2,
                'simulasi_excel_g1' => $excelScG1,
                'excel_ref_hari' => $ref['hari'] ?? null,
                'excel_ref_poin' => $ref['poin'] ?? null,
                'excel_ref_total' => $ref['total_sc'] ?? null,
                'is_resign' => (bool) ($row['is_resigned_employee'] ?? false),
                'is_mutasi' => (bool) ($row['is_mutated_employee'] ?? false),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['nama'], $b['nama']));

        return [
            'rows' => $rows,
            'erp_totals' => [
                'count_sc1' => $eligible->count(),
                'sum_hari_kerja' => $sumHariKerja,
                'sum_hari_gajian2' => $sumHariGajian2,
                'sum_poin_x_hari_kerja' => $sumPoinHariKerja,
                'sum_poin_x_hari_gajian2' => $sumPoinHariGajian2,
                'total_erp_sc' => $totalErpSc,
                'total_simulasi_g2' => $totalSimG2,
                'total_simulasi_g1' => $totalSimG1,
                'pool_total_hari_kerja_gajian2' => (int) ($filter['pool_total_hari_kerja_gajian2'] ?? $sumHariGajian2),
                'pool_total_point_hari_kerja' => (int) ($filter['pool_total_point_hari_kerja'] ?? $sumPoinHariGajian2),
            ],
        ];
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, erp_totals: array<string, int>}  $scRows
     */
    private function compareWithExcel(array $scRows): array
    {
        $rows = $scRows['rows'];
        $mismatches = [];
        $erpNames = [];
        $excelNames = array_keys(self::EXCEL_REFERENCE);

        foreach ($rows as &$row) {
            $key = $this->normName($row['nama']);
            $erpNames[] = $key;

            $diffErpVsSimG2 = $row['erp_sc'] - $row['simulasi_excel_g2'];
            $diffErpVsSimG1 = $row['erp_sc'] - $row['simulasi_excel_g1'];
            $row['diff_erp_vs_sim_g2'] = $diffErpVsSimG2;
            $row['diff_erp_vs_sim_g1'] = $diffErpVsSimG1;

            if ($row['excel_ref_total'] !== null) {
                $row['diff_erp_vs_excel_ref'] = $row['erp_sc'] - $row['excel_ref_total'];
                $row['diff_hari_vs_excel'] = $row['hari_kerja_gajian2'] - $row['excel_ref_hari'];
                $row['diff_hari_g1_vs_excel'] = $row['hari_kerja'] - $row['excel_ref_hari'];

                if ($row['diff_erp_vs_excel_ref'] !== 0
                    || $row['diff_hari_vs_excel'] !== 0
                    || $row['diff_hari_g1_vs_excel'] !== 0
                    || (int) $row['poin'] !== (int) $row['excel_ref_poin']) {
                    $mismatches[] = $row;
                }
            }
        }
        unset($row);

        $onlyInExcel = array_values(array_diff($excelNames, $erpNames));
        $onlyInErp = array_values(array_diff($erpNames, $excelNames));

        return [
            'rows' => $rows,
            'erp_totals' => $scRows['erp_totals'],
            'mismatches' => $mismatches,
            'only_in_excel' => $onlyInExcel,
            'only_in_erp' => $onlyInErp,
        ];
    }

    private function normName(string $name): string
    {
        return strtolower(trim($name));
    }

    public function formatReport(array $result): string
    {
        $lines = [];
        $t = $result['erp_totals'];
        $e = self::EXCEL_POOL_TOTALS;

        $lines[] = '=== PAYROLL SC TRACE ===';
        $lines[] = sprintf('Outlet: %d | Periode gajian: %02d/%d | Pool SC: %s',
            $result['outlet_id'], $result['month'], $result['year'], number_format($result['pool'], 0, ',', '.'));

        $lines[] = '';
        $lines[] = '--- TOTAL DENOMINATOR (sc=1) ---';
        $lines[] = sprintf('%-28s ERP gajian2    Excel ref', 'Metrik');
        $lines[] = sprintf('%-28s %12d    %12d    %s',
            'Σ hari (kolom D)',
            $t['sum_hari_gajian2'],
            $e['sum_hari'],
            $t['sum_hari_gajian2'] === $e['sum_hari'] ? 'OK' : 'SELISIH '.($t['sum_hari_gajian2'] - $e['sum_hari'])
        );
        $lines[] = sprintf('%-28s %12d    %12d    %s',
            'Σ poin×hari (kolom E)',
            $t['sum_poin_x_hari_gajian2'],
            $e['sum_poin_hari'],
            $t['sum_poin_x_hari_gajian2'] === $e['sum_poin_hari'] ? 'OK' : 'SELISIH '.($t['sum_poin_x_hari_gajian2'] - $e['sum_poin_hari'])
        );
        $lines[] = sprintf('%-28s %12d', 'Σ hari_kerja (gajian1)', $t['sum_hari_kerja']);
        $lines[] = sprintf('%-28s %12d', 'Σ poin×hari (gajian1)', $t['sum_poin_x_hari_kerja']);
        $lines[] = sprintf('%-28s %12d', 'Jumlah karyawan sc=1', $t['count_sc1']);
        $lines[] = sprintf('%-28s %12s', 'Pool ERP vs Excel', abs($result['pool'] - $e['pool']) < 1 ? 'OK' : 'SELISIH');
        $lines[] = sprintf('%-28s %12s', 'Σ ERP SC vs sim gajian2', ($t['total_erp_sc'] ?? 0) === ($t['total_simulasi_g2'] ?? 0) ? 'OK (formula konsisten)' : 'CEK');
        $lines[] = sprintf('%-28s %12s', 'Σ sim gajian1 (jika D=g1)', number_format($t['total_simulasi_g1'] ?? 0, 0, ',', '.'));

        $resigned = array_filter($result['rows'], fn ($r) => $r['is_resign']);
        if (! empty($resigned)) {
            $lines[] = '';
            $lines[] = '--- KARYAWAN RESIGN (sc=1) ---';
            foreach ($resigned as $r) {
                $lines[] = sprintf('%s | g1=%d g2=%d | SC %s',
                    $r['nama'], $r['hari_kerja'], $r['hari_kerja_gajian2'],
                    number_format($r['erp_sc'], 0, ',', '.')
                );
            }
        }

        $lines[] = '';
        $lines[] = '--- PER KARYAWAN (ada di Excel ref) ---';
        $lines[] = str_pad('Nama', 28)
            .str_pad('Poin', 5)
            .str_pad('H_G1', 5)
            .str_pad('H_G2', 5)
            .str_pad('ExD', 5)
            .str_pad('ERP SC', 10)
            .str_pad('SimG2', 10)
            .str_pad('SimG1', 10)
            .str_pad('Excel', 10)
            .str_pad('Δ ERP', 8);

        foreach ($result['rows'] as $row) {
            if ($row['excel_ref_total'] === null) {
                continue;
            }
            $lines[] = str_pad(mb_substr($row['nama'], 0, 27), 28)
                .str_pad((string) $row['poin'], 5)
                .str_pad((string) $row['hari_kerja'], 5)
                .str_pad((string) $row['hari_kerja_gajian2'], 5)
                .str_pad((string) $row['excel_ref_hari'], 5)
                .str_pad(number_format($row['erp_sc'], 0, ',', '.'), 10)
                .str_pad(number_format($row['simulasi_excel_g2'], 0, ',', '.'), 10)
                .str_pad(number_format($row['simulasi_excel_g1'], 0, ',', '.'), 10)
                .str_pad(number_format($row['excel_ref_total'], 0, ',', '.'), 10)
                .str_pad((string) ($row['diff_erp_vs_excel_ref'] ?? 0), 8);
        }

        if (! empty($result['mismatches'])) {
            $lines[] = '';
            $lines[] = '--- MISMATCH vs EXCEL REF ---';
            foreach ($result['mismatches'] as $m) {
                $lines[] = sprintf(
                    '%s | poin %d/%d | hari g1 %d g2 %d excel %d | SC erp %s excel %s Δ %d',
                    $m['nama'],
                    $m['poin'],
                    $m['excel_ref_poin'],
                    $m['hari_kerja'],
                    $m['hari_kerja_gajian2'],
                    $m['excel_ref_hari'],
                    number_format($m['erp_sc'], 0, ',', '.'),
                    number_format($m['excel_ref_total'], 0, ',', '.'),
                    $m['diff_erp_vs_excel_ref'] ?? 0
                );
            }
        }

        $hariDiffs = array_filter($result['rows'], fn ($r) => $r['hari_kerja_gajian2'] !== $r['hari_kerja']);
        if (! empty($hariDiffs)) {
            $lines[] = '';
            $lines[] = '--- HARI G1 ≠ G2 (sample max 15) ---';
            foreach (array_slice($hariDiffs, 0, 15) as $r) {
                $lines[] = sprintf('%s: gajian1=%d gajian2=%d', $r['nama'], $r['hari_kerja'], $r['hari_kerja_gajian2']);
            }
        }

        $erpNotSim = array_filter($result['rows'], fn ($r) => ($r['diff_erp_vs_sim_g2'] ?? 0) !== 0);
        if (! empty($erpNotSim)) {
            $lines[] = '';
            $lines[] = sprintf('--- ERP ≠ simulasi formula (gajian2): %d karyawan ---', count($erpNotSim));
            foreach (array_slice($erpNotSim, 0, 10) as $r) {
                $lines[] = sprintf('%s: erp %s sim %s Δ %d',
                    $r['nama'],
                    number_format($r['erp_sc'], 0, ',', '.'),
                    number_format($r['simulasi_excel_g2'], 0, ',', '.'),
                    $r['diff_erp_vs_sim_g2']
                );
            }
        }

        return implode(PHP_EOL, $lines);
    }
}
