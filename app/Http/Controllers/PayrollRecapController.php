<?php

namespace App\Http\Controllers;

use App\Services\PayrollGajiSplitCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRecapController extends Controller
{
    public function index(Request $request)
    {
        $month = str_pad((string) ((int) $request->input('month', (int) date('m'))), 2, '0', STR_PAD_LEFT);
        $year = (int) $request->input('year', (int) date('Y'));

        $recap = $this->buildRecapData((int) $month, max(2000, min(2100, $year)));

        return Inertia::render('Payroll/RekapPayroll', [
            'months' => $this->getMonths(),
            'years' => $this->getYears(),
            'filter' => [
                'month' => $month,
                'year' => $recap['year'],
            ],
            'rows' => $recap['rows'],
            'summary' => $recap['summary'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $month = (int) $request->input('month', (int) date('m'));
        $year = (int) $request->input('year', (int) date('Y'));
        $recap = $this->buildRecapData($month, max(2000, min(2100, $year)));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Payroll');

        $headers = [
            'Outlet',
            'Gapok',
            'Tunjangan',
            'Telat',
            'Alpha',
            'Unpaid Leave',
            'Kasbon',
            'Lembur',
            'Service Charge',
            'Uang Makan',
            'Bonus PH',
            'L&B',
            'Deviasi',
            'City Ledger',
            'Custom Earn G1',
            'Custom Ded G1',
            'Custom Earn G2',
            'Custom Ded G2',
            'Total Gaji 1',
            'Total Gaji 2',
            'Grand Total Gaji',
            'BPJS Perusahaan',
        ];
        $sheet->setCellValue('A1', 'REKAP PAYROLL');
        $sheet->mergeCells('A1:V1');
        $sheet->setCellValue('A2', 'Periode: '.$this->monthName($recap['month']).' '.$recap['year']);
        $sheet->mergeCells('A2:V2');
        $sheet->fromArray($headers, null, 'A4');

        $rows = array_map(static fn (array $row) => [
            $row['outlet'],
            $row['gapok'] ?? 0,
            $row['tunjangan'] ?? 0,
            $row['potongan_telat'] ?? 0,
            $row['potongan_alpha'] ?? 0,
            $row['potongan_unpaid_leave'] ?? 0,
            $row['potongan_kasbon'] ?? 0,
            $row['gaji_lembur'] ?? 0,
            $row['service_charge'] ?? 0,
            $row['uang_makan'] ?? 0,
            $row['ph_bonus'] ?? 0,
            $row['lb_total'] ?? 0,
            $row['deviasi_total'] ?? 0,
            $row['city_ledger_total'] ?? 0,
            $row['custom_earnings_gajian1'] ?? 0,
            $row['custom_deductions_gajian1'] ?? 0,
            $row['custom_earnings_gajian2'] ?? 0,
            $row['custom_deductions_gajian2'] ?? 0,
            $row['total_gaji_1'],
            $row['total_gaji_2'],
            $row['grand_total_gaji'],
            $row['bpjs_perusahaan'],
        ], $recap['rows']);
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A5');
        }

        $totalRow = 5 + count($rows);
        $sheet->fromArray([[
            'TOTAL',
            $recap['summary']['gapok'] ?? 0,
            $recap['summary']['tunjangan'] ?? 0,
            $recap['summary']['potongan_telat'] ?? 0,
            $recap['summary']['potongan_alpha'] ?? 0,
            $recap['summary']['potongan_unpaid_leave'] ?? 0,
            $recap['summary']['potongan_kasbon'] ?? 0,
            $recap['summary']['gaji_lembur'] ?? 0,
            $recap['summary']['service_charge'] ?? 0,
            $recap['summary']['uang_makan'] ?? 0,
            $recap['summary']['ph_bonus'] ?? 0,
            $recap['summary']['lb_total'] ?? 0,
            $recap['summary']['deviasi_total'] ?? 0,
            $recap['summary']['city_ledger_total'] ?? 0,
            $recap['summary']['custom_earnings_gajian1'] ?? 0,
            $recap['summary']['custom_deductions_gajian1'] ?? 0,
            $recap['summary']['custom_earnings_gajian2'] ?? 0,
            $recap['summary']['custom_deductions_gajian2'] ?? 0,
            $recap['summary']['total_gaji_1'],
            $recap['summary']['total_gaji_2'],
            $recap['summary']['grand_total_gaji'],
            $recap['summary']['bpjs_perusahaan'],
        ]], null, "A{$totalRow}");

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A4:V4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $endRow = max($totalRow, 5);
        $sheet->getStyle("A4:V{$endRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("B5:V{$endRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');
        $sheet->getStyle("B5:V{$endRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$totalRow}:V{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
        ]);

        for ($i = 1; $i <= 22; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $filename = sprintf('rekap-payroll-%02d-%d.xlsx', $recap['month'], $recap['year']);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildRecapData(int $month, int $year): array
    {
        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));

        $payrolls = DB::table('payroll_generated as pg')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'pg.outlet_id')
            ->where('pg.month', $month)
            ->where('pg.year', $year)
            ->select('pg.id', 'o.nama_outlet as outlet_name')
            ->orderBy('o.nama_outlet')
            ->get();

        $rows = [];
        $totalGaji1 = 0.0;
        $totalGaji2 = 0.0;
        $totalGrand = 0.0;
        $totalBpjs = 0.0;
        $totalGapok = 0.0;
        $totalTunjangan = 0.0;
        $totalTelat = 0.0;
        $totalAlpha = 0.0;
        $totalUnpaidLeave = 0.0;
        $totalKasbon = 0.0;
        $totalLembur = 0.0;
        $totalServiceCharge = 0.0;
        $totalUangMakan = 0.0;
        $totalPhBonus = 0.0;
        $totalLb = 0.0;
        $totalDeviasi = 0.0;
        $totalCityLedger = 0.0;
        $totalCustomEarnG1 = 0.0;
        $totalCustomDedG1 = 0.0;
        $totalCustomEarnG2 = 0.0;
        $totalCustomDedG2 = 0.0;

        foreach ($payrolls as $payroll) {
            $details = DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payroll->id)
                ->select(
                    'gaji_pokok',
                    'tunjangan',
                    'bpjs_jkn',
                    'bpjs_tk',
                    'potongan_telat',
                    'potongan_alpha',
                    'potongan_unpaid_leave',
                    'potongan_kasbon',
                    'service_charge',
                    'uang_makan',
                    'gaji_lembur',
                    'ph_bonus',
                    'lb_total',
                    'deviasi_total',
                    'city_ledger_total',
                    'custom_items',
                    'bpjs_perusahaan_detail'
                )
                ->get();

            $gaji1 = 0.0;
            $gaji2 = 0.0;
            $bpjs = 0.0;
            $gapok = 0.0;
            $tunjangan = 0.0;
            $telat = 0.0;
            $alpha = 0.0;
            $unpaidLeave = 0.0;
            $kasbon = 0.0;
            $lembur = 0.0;
            $serviceCharge = 0.0;
            $uangMakan = 0.0;
            $phBonus = 0.0;
            $lbTotal = 0.0;
            $deviasiTotal = 0.0;
            $cityLedgerTotal = 0.0;
            $customEarnG1 = 0.0;
            $customDedG1 = 0.0;
            $customEarnG2 = 0.0;
            $customDedG2 = 0.0;

            foreach ($details as $detail) {
                $customSums = $this->resolveCustomGajianSums($detail->custom_items);
                $split = PayrollGajiSplitCalculator::calculate([
                    'gaji_pokok' => $detail->gaji_pokok ?? 0,
                    'tunjangan' => $detail->tunjangan ?? 0,
                    'custom_earnings_gajian1' => $customSums['custom_earnings_gajian1'],
                    'custom_deductions_gajian1' => $customSums['custom_deductions_gajian1'],
                    'bpjs_jkn' => $detail->bpjs_jkn ?? 0,
                    'bpjs_tk' => $detail->bpjs_tk ?? 0,
                    'potongan_telat' => $detail->potongan_telat ?? 0,
                    'potongan_alpha' => $detail->potongan_alpha ?? 0,
                    'potongan_unpaid_leave' => $detail->potongan_unpaid_leave ?? 0,
                    'potongan_kasbon' => $detail->potongan_kasbon ?? 0,
                    'service_charge' => $detail->service_charge ?? 0,
                    'uang_makan' => $detail->uang_makan ?? 0,
                    'gaji_lembur' => $detail->gaji_lembur ?? 0,
                    'ph_bonus' => $detail->ph_bonus ?? 0,
                    'custom_earnings_gajian2' => $customSums['custom_earnings_gajian2'],
                    'custom_deductions_gajian2' => $customSums['custom_deductions_gajian2'],
                    'lb_total' => $detail->lb_total ?? 0,
                    'deviasi_total' => $detail->deviasi_total ?? 0,
                    'city_ledger_total' => $detail->city_ledger_total ?? 0,
                ]);

                $gapok += (float) ($detail->gaji_pokok ?? 0);
                $tunjangan += (float) ($detail->tunjangan ?? 0);
                $telat += (float) ($detail->potongan_telat ?? 0);
                $alpha += (float) ($detail->potongan_alpha ?? 0);
                $unpaidLeave += (float) ($detail->potongan_unpaid_leave ?? 0);
                $kasbon += (float) ($detail->potongan_kasbon ?? 0);
                $lembur += (float) ($detail->gaji_lembur ?? 0);
                $serviceCharge += (float) ($detail->service_charge ?? 0);
                $uangMakan += (float) ($detail->uang_makan ?? 0);
                $phBonus += (float) ($detail->ph_bonus ?? 0);
                $lbTotal += (float) ($detail->lb_total ?? 0);
                $deviasiTotal += (float) ($detail->deviasi_total ?? 0);
                $cityLedgerTotal += (float) ($detail->city_ledger_total ?? 0);
                $customEarnG1 += (float) ($customSums['custom_earnings_gajian1'] ?? 0);
                $customDedG1 += (float) ($customSums['custom_deductions_gajian1'] ?? 0);
                $customEarnG2 += (float) ($customSums['custom_earnings_gajian2'] ?? 0);
                $customDedG2 += (float) ($customSums['custom_deductions_gajian2'] ?? 0);

                $gaji1 += (float) ($split['total_gaji_akhir_bulan'] ?? 0);
                $gaji2 += (float) ($split['total_gaji_tanggal_8'] ?? 0);

                $bpjsDetail = is_string($detail->bpjs_perusahaan_detail)
                    ? json_decode($detail->bpjs_perusahaan_detail, true)
                    : null;
                if (is_array($bpjsDetail)) {
                    $bpjs += (float) ($bpjsDetail['total_perusahaan'] ?? 0);
                }
            }

            $gaji1 = round($gaji1);
            $gaji2 = round($gaji2);
            $bpjs = round($bpjs);
            $grand = $gaji1 + $gaji2;
            $gapok = round($gapok);
            $tunjangan = round($tunjangan);
            $telat = round($telat);
            $alpha = round($alpha);
            $unpaidLeave = round($unpaidLeave);
            $kasbon = round($kasbon);
            $lembur = round($lembur);
            $serviceCharge = round($serviceCharge);
            $uangMakan = round($uangMakan);
            $phBonus = round($phBonus);
            $lbTotal = round($lbTotal);
            $deviasiTotal = round($deviasiTotal);
            $cityLedgerTotal = round($cityLedgerTotal);
            $customEarnG1 = round($customEarnG1);
            $customDedG1 = round($customDedG1);
            $customEarnG2 = round($customEarnG2);
            $customDedG2 = round($customDedG2);

            $rows[] = [
                'outlet' => $payroll->outlet_name ?: 'Unknown Outlet',
                'gapok' => $gapok,
                'tunjangan' => $tunjangan,
                'potongan_telat' => $telat,
                'potongan_alpha' => $alpha,
                'potongan_unpaid_leave' => $unpaidLeave,
                'potongan_kasbon' => $kasbon,
                'gaji_lembur' => $lembur,
                'service_charge' => $serviceCharge,
                'uang_makan' => $uangMakan,
                'ph_bonus' => $phBonus,
                'lb_total' => $lbTotal,
                'deviasi_total' => $deviasiTotal,
                'city_ledger_total' => $cityLedgerTotal,
                'custom_earnings_gajian1' => $customEarnG1,
                'custom_deductions_gajian1' => $customDedG1,
                'custom_earnings_gajian2' => $customEarnG2,
                'custom_deductions_gajian2' => $customDedG2,
                'total_gaji_1' => $gaji1,
                'total_gaji_2' => $gaji2,
                'grand_total_gaji' => $grand,
                'bpjs_perusahaan' => $bpjs,
            ];

            $totalGaji1 += $gaji1;
            $totalGaji2 += $gaji2;
            $totalGrand += $grand;
            $totalBpjs += $bpjs;
            $totalGapok += $gapok;
            $totalTunjangan += $tunjangan;
            $totalTelat += $telat;
            $totalAlpha += $alpha;
            $totalUnpaidLeave += $unpaidLeave;
            $totalKasbon += $kasbon;
            $totalLembur += $lembur;
            $totalServiceCharge += $serviceCharge;
            $totalUangMakan += $uangMakan;
            $totalPhBonus += $phBonus;
            $totalLb += $lbTotal;
            $totalDeviasi += $deviasiTotal;
            $totalCityLedger += $cityLedgerTotal;
            $totalCustomEarnG1 += $customEarnG1;
            $totalCustomDedG1 += $customDedG1;
            $totalCustomEarnG2 += $customEarnG2;
            $totalCustomDedG2 += $customDedG2;
        }

        return [
            'month' => $month,
            'year' => $year,
            'rows' => $rows,
            'summary' => [
                'gapok' => round($totalGapok),
                'tunjangan' => round($totalTunjangan),
                'potongan_telat' => round($totalTelat),
                'potongan_alpha' => round($totalAlpha),
                'potongan_unpaid_leave' => round($totalUnpaidLeave),
                'potongan_kasbon' => round($totalKasbon),
                'gaji_lembur' => round($totalLembur),
                'service_charge' => round($totalServiceCharge),
                'uang_makan' => round($totalUangMakan),
                'ph_bonus' => round($totalPhBonus),
                'lb_total' => round($totalLb),
                'deviasi_total' => round($totalDeviasi),
                'city_ledger_total' => round($totalCityLedger),
                'custom_earnings_gajian1' => round($totalCustomEarnG1),
                'custom_deductions_gajian1' => round($totalCustomDedG1),
                'custom_earnings_gajian2' => round($totalCustomEarnG2),
                'custom_deductions_gajian2' => round($totalCustomDedG2),
                'total_gaji_1' => round($totalGaji1),
                'total_gaji_2' => round($totalGaji2),
                'grand_total_gaji' => round($totalGrand),
                'bpjs_perusahaan' => round($totalBpjs),
            ],
        ];
    }

    /**
     * @return array{
     *   custom_earnings_gajian1: float,
     *   custom_deductions_gajian1: float,
     *   custom_earnings_gajian2: float,
     *   custom_deductions_gajian2: float
     * }
     */
    private function resolveCustomGajianSums(?string $customItemsJson): array
    {
        $items = json_decode($customItemsJson ?? '[]', true);
        if (! is_array($items)) {
            $items = [];
        }

        $sum = [
            'custom_earnings_gajian1' => 0.0,
            'custom_deductions_gajian1' => 0.0,
            'custom_earnings_gajian2' => 0.0,
            'custom_deductions_gajian2' => 0.0,
        ];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemType = (string) ($item['item_type'] ?? '');
            $gajianType = (string) ($item['gajian_type'] ?? 'gajian1');
            $amount = (float) ($item['item_amount'] ?? 0);

            if ($itemType === 'earn') {
                if ($gajianType === 'gajian2') {
                    $sum['custom_earnings_gajian2'] += $amount;
                } else {
                    $sum['custom_earnings_gajian1'] += $amount;
                }
            }

            if ($itemType === 'deduction') {
                if ($gajianType === 'gajian2') {
                    $sum['custom_deductions_gajian2'] += $amount;
                } else {
                    $sum['custom_deductions_gajian1'] += $amount;
                }
            }
        }

        return $sum;
    }

    private function getMonths(): array
    {
        return [
            ['id' => '01', 'name' => 'Januari'],
            ['id' => '02', 'name' => 'Februari'],
            ['id' => '03', 'name' => 'Maret'],
            ['id' => '04', 'name' => 'April'],
            ['id' => '05', 'name' => 'Mei'],
            ['id' => '06', 'name' => 'Juni'],
            ['id' => '07', 'name' => 'Juli'],
            ['id' => '08', 'name' => 'Agustus'],
            ['id' => '09', 'name' => 'September'],
            ['id' => '10', 'name' => 'Oktober'],
            ['id' => '11', 'name' => 'November'],
            ['id' => '12', 'name' => 'Desember'],
        ];
    }

    private function getYears(): array
    {
        $currentYear = (int) date('Y');

        return collect(range($currentYear - 2, $currentYear + 1))
            ->map(fn (int $year) => ['id' => $year, 'name' => (string) $year])
            ->all();
    }

    private function monthName(int $month): string
    {
        foreach ($this->getMonths() as $m) {
            if ((int) $m['id'] === $month) {
                return (string) $m['name'];
            }
        }

        return (string) $month;
    }
}

