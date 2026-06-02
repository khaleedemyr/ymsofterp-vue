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

        $headers = ['Outlet', 'Total Gaji 1', 'Total Gaji 2', 'Grand Total Gaji', 'BPJS Perusahaan'];
        $sheet->setCellValue('A1', 'REKAP PAYROLL');
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A2', 'Periode: '.$this->monthName($recap['month']).' '.$recap['year']);
        $sheet->mergeCells('A2:E2');
        $sheet->fromArray($headers, null, 'A4');

        $rows = array_map(static fn (array $row) => [
            $row['outlet'],
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
            $recap['summary']['total_gaji_1'],
            $recap['summary']['total_gaji_2'],
            $recap['summary']['grand_total_gaji'],
            $recap['summary']['bpjs_perusahaan'],
        ]], null, "A{$totalRow}");

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A4:E4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $endRow = max($totalRow, 5);
        $sheet->getStyle("A4:E{$endRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("B5:E{$endRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');
        $sheet->getStyle("B5:E{$endRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$totalRow}:E{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
        ]);

        for ($i = 1; $i <= 5; $i++) {
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

            $rows[] = [
                'outlet' => $payroll->outlet_name ?: 'Unknown Outlet',
                'total_gaji_1' => $gaji1,
                'total_gaji_2' => $gaji2,
                'grand_total_gaji' => $grand,
                'bpjs_perusahaan' => $bpjs,
            ];

            $totalGaji1 += $gaji1;
            $totalGaji2 += $gaji2;
            $totalGrand += $grand;
            $totalBpjs += $bpjs;
        }

        return [
            'month' => $month,
            'year' => $year,
            'rows' => $rows,
            'summary' => [
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

