<?php

namespace App\Http\Controllers;

use App\Models\CustomPayrollItem;
use App\Services\PayrollGajiSplitCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollFinanceReportController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $report = $this->buildReportData(
            $outletId ? (int) $outletId : null,
            (int) $month,
            (int) $year
        );

        return Inertia::render('Payroll/FinanceReport', [
            'outlets' => $this->getOutlets(),
            'months' => $this->getMonths(),
            'years' => $this->getYears(),
            'paymentRows' => $report['payment_rows'],
            'bpjsRows' => $report['bpjs_rows'],
            'summary' => $report['summary'],
            'filter' => [
                'outlet_id' => $outletId,
                'month' => $month,
                'year' => $year,
            ],
            'meta' => [
                'outlet_name' => $report['outlet_name'],
                'periode' => $report['periode'],
                'has_generated' => $report['has_generated'],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $outletId = (int) $request->input('outlet_id');
        $month = (int) $request->input('month');
        $year = (int) $request->input('year');

        if (! $outletId || ! $month || ! $year) {
            abort(422, 'Outlet, bulan, dan tahun wajib diisi.');
        }

        $report = $this->buildReportData($outletId, $month, $year);

        if (! $report['has_generated']) {
            abort(404, 'Payroll belum di-generate untuk periode ini.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Pembayaran Gaji');
        $sheet1->setCellValue('A1', 'LAPORAN FINANCE - PEMBAYARAN GAJI');
        $sheet1->setCellValue('A2', 'Outlet: ' . $report['outlet_name']);
        $sheet1->setCellValue('A3', 'Periode: ' . $report['periode']);

        $paymentExport = collect($report['payment_rows'])->map(fn (array $row) => [
            'Nama Karyawan' => $row['nama_lengkap'],
            'Nama Rekening' => $row['nama_rekening'],
            'No. Rekening' => $row['no_rekening'],
            'Gaji Akhir Bulan' => $row['total_gaji_akhir_bulan'],
            'Gaji Tanggal 8' => $row['total_gaji_tanggal_8'],
            'Total Gaji' => $row['total_gaji'],
        ])->values()->all();

        if (! empty($paymentExport)) {
            $headers = array_keys($paymentExport[0]);
            $sheet1->fromArray($headers, null, 'A5');
            $sheet1->fromArray($paymentExport, null, 'A6');
        }

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('BPJS Perusahaan');
        $sheet2->setCellValue('A1', 'LAPORAN FINANCE - BPJS PERUSAHAAN');
        $sheet2->setCellValue('A2', 'Outlet: ' . $report['outlet_name']);
        $sheet2->setCellValue('A3', 'Periode: ' . $report['periode']);

        $bpjsExport = collect($report['bpjs_rows'])->map(fn (array $row) => [
            'Nama Karyawan' => $row['nama_lengkap'],
            'NIK' => $row['nik'],
            'BPJS Kesehatan (Perusahaan)' => $row['kes_perusahaan'],
            'JHT (Perusahaan)' => $row['jht_perusahaan'],
            'JP (Perusahaan)' => $row['jp_perusahaan'],
            'JKK (Perusahaan)' => $row['jkk_perusahaan'],
            'JKM (Perusahaan)' => $row['jkm_perusahaan'],
            'Total BPJS Perusahaan' => $row['total_bpjs_perusahaan'],
        ])->values()->all();

        if (! empty($bpjsExport)) {
            $headers = array_keys($bpjsExport[0]);
            $sheet2->fromArray($headers, null, 'A5');
            $sheet2->fromArray($bpjsExport, null, 'A6');
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = sprintf(
            'finance-payroll-%s-%s-%s.xlsx',
            preg_replace('/[^a-zA-Z0-9_-]+/', '_', $report['outlet_name'] ?? 'outlet'),
            str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            $year
        );

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{
     *     payment_rows: array<int, array<string, mixed>>,
     *     bpjs_rows: array<int, array<string, mixed>>,
     *     summary: array<string, float|int>,
     *     outlet_name: string|null,
     *     periode: string|null,
     *     has_generated: bool
     * }
     */
    private function buildReportData(?int $outletId, int $month, int $year): array
    {
        $empty = [
            'payment_rows' => [],
            'bpjs_rows' => [],
            'summary' => [
                'total_gaji_akhir_bulan' => 0,
                'total_gaji_tanggal_8' => 0,
                'total_gaji' => 0,
                'total_bpjs_perusahaan' => 0,
                'employee_count' => 0,
                'bpjs_employee_count' => 0,
            ],
            'outlet_name' => null,
            'periode' => null,
            'has_generated' => false,
        ];

        if (! $outletId || ! $month || ! $year) {
            return $empty;
        }

        $outletName = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->value('nama_outlet');

        $start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
        $end = date('Y-m-d', strtotime("$year-$month-25"));
        $periode = Carbon::parse($start)->format('d/m/Y') . ' - ' . Carbon::parse($end)->format('d/m/Y');

        $payrollGenerated = DB::table('payroll_generated')
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $payrollGenerated) {
            return array_merge($empty, [
                'outlet_name' => $outletName,
                'periode' => $periode,
            ]);
        }

        $details = DB::table('payroll_generated_details as pgd')
            ->join('users as u', 'u.id', '=', 'pgd.user_id')
            ->where('pgd.payroll_generated_id', $payrollGenerated->id)
            ->orderBy('u.nama_lengkap')
            ->select(
                'pgd.*',
                'u.nama_lengkap as user_nama_lengkap',
                'u.nama_rekening as user_nama_rekening',
                'u.no_rekening as user_no_rekening',
                'u.nik as user_nik'
            )
            ->get();

        if ($details->isEmpty()) {
            return array_merge($empty, [
                'outlet_name' => $outletName,
                'periode' => $periode,
            ]);
        }

        $customItemsByUser = CustomPayrollItem::forOutlet($outletId)
            ->forPeriod($month, $year)
            ->get()
            ->groupBy('user_id');

        $paymentRows = [];
        $bpjsRows = [];
        $sumAkhirBulan = 0.0;
        $sumTanggal8 = 0.0;
        $sumTotalGaji = 0.0;
        $sumBpjsPerusahaan = 0.0;

        foreach ($details as $detail) {
            $customSums = $this->resolveCustomGajianSums($detail, $customItemsByUser->get($detail->user_id, collect()));

            $gajiSplit = PayrollGajiSplitCalculator::calculate([
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

            $paymentRows[] = [
                'user_id' => $detail->user_id,
                'nama_lengkap' => $detail->user_nama_lengkap ?: ($detail->nama_lengkap ?? '-'),
                'nama_rekening' => $detail->user_nama_rekening ?: '-',
                'no_rekening' => $detail->user_no_rekening ?: '-',
                'total_gaji_akhir_bulan' => $gajiSplit['total_gaji_akhir_bulan'],
                'total_gaji_tanggal_8' => $gajiSplit['total_gaji_tanggal_8'],
                'total_gaji' => $gajiSplit['total_gaji'],
            ];

            $sumAkhirBulan += $gajiSplit['total_gaji_akhir_bulan'];
            $sumTanggal8 += $gajiSplit['total_gaji_tanggal_8'];
            $sumTotalGaji += $gajiSplit['total_gaji'];

            $bpjsRow = $this->mapBpjsPerusahaanRow($detail);
            if ($bpjsRow !== null) {
                $bpjsRows[] = $bpjsRow;
                $sumBpjsPerusahaan += $bpjsRow['total_bpjs_perusahaan'];
            }
        }

        return [
            'payment_rows' => $paymentRows,
            'bpjs_rows' => $bpjsRows,
            'summary' => [
                'total_gaji_akhir_bulan' => round($sumAkhirBulan),
                'total_gaji_tanggal_8' => round($sumTanggal8),
                'total_gaji' => round($sumTotalGaji),
                'total_bpjs_perusahaan' => round($sumBpjsPerusahaan),
                'employee_count' => count($paymentRows),
                'bpjs_employee_count' => count($bpjsRows),
            ],
            'outlet_name' => $outletName,
            'periode' => $periode,
            'has_generated' => true,
        ];
    }

    /**
     * @return array{
     *     custom_earnings_gajian1: float,
     *     custom_deductions_gajian1: float,
     *     custom_earnings_gajian2: float,
     *     custom_deductions_gajian2: float
     * }
     */
    private function resolveCustomGajianSums(object $detail, Collection $userCustomItems): array
    {
        $customItemsData = json_decode($detail->custom_items ?? '[]', true) ?? [];
        $customItemsCollection = collect($customItemsData);

        if ($userCustomItems->isNotEmpty()) {
            $customItemsCollection = $userCustomItems;
        }

        $gajian1 = $customItemsCollection->filter(function ($item) {
            $gajianType = is_object($item) ? ($item->gajian_type ?? null) : ($item['gajian_type'] ?? null);

            return ! isset($gajianType) || $gajianType === null || $gajianType === 'gajian1';
        });

        $gajian2 = $customItemsCollection->filter(function ($item) {
            $gajianType = is_object($item) ? ($item->gajian_type ?? null) : ($item['gajian_type'] ?? null);

            return $gajianType === 'gajian2';
        });

        return [
            'custom_earnings_gajian1' => $this->sumCustomItems($gajian1, 'earn'),
            'custom_deductions_gajian1' => $this->sumCustomItems($gajian1, 'deduction'),
            'custom_earnings_gajian2' => $this->sumCustomItems($gajian2, 'earn'),
            'custom_deductions_gajian2' => $this->sumCustomItems($gajian2, 'deduction'),
        ];
    }

    private function sumCustomItems(Collection $items, string $type): float
    {
        return (float) $items->filter(function ($item) use ($type) {
            $itemType = is_object($item) ? ($item->item_type ?? null) : ($item['item_type'] ?? null);

            return $itemType === $type;
        })->sum(function ($item) {
            return is_object($item) ? ($item->item_amount ?? 0) : ($item['item_amount'] ?? 0);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapBpjsPerusahaanRow(object $detail): ?array
    {
        $bpjsDetail = ! empty($detail->bpjs_perusahaan_detail)
            ? (json_decode($detail->bpjs_perusahaan_detail, true) ?: null)
            : null;

        if (! $bpjsDetail || (float) ($bpjsDetail['total_perusahaan'] ?? 0) <= 0) {
            return null;
        }

        $lineAmounts = [
            'kes_perusahaan' => 0.0,
            'jht_perusahaan' => 0.0,
            'jp_perusahaan' => 0.0,
            'jkk_perusahaan' => 0.0,
            'jkm_perusahaan' => 0.0,
        ];

        foreach ($bpjsDetail['lines'] ?? [] as $line) {
            $key = $line['key'] ?? null;
            if ($key && array_key_exists($key, $lineAmounts)) {
                $lineAmounts[$key] = (float) ($line['amount'] ?? 0);
            }
        }

        return [
            'user_id' => $detail->user_id,
            'nama_lengkap' => $detail->user_nama_lengkap ?? ($detail->nama_lengkap ?? '-'),
            'nik' => $detail->user_nik ?? ($detail->nik ?? '-'),
            'kes_perusahaan' => round($lineAmounts['kes_perusahaan']),
            'jht_perusahaan' => round($lineAmounts['jht_perusahaan']),
            'jp_perusahaan' => round($lineAmounts['jp_perusahaan']),
            'jkk_perusahaan' => round($lineAmounts['jkk_perusahaan']),
            'jkm_perusahaan' => round($lineAmounts['jkm_perusahaan']),
            'total_bpjs_perusahaan' => round((float) ($bpjsDetail['total_perusahaan'] ?? 0)),
        ];
    }

    private function getOutlets(): Collection
    {
        return DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
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

    private function getYears(): Collection
    {
        $currentYear = (int) date('Y');

        return collect(range($currentYear - 2, $currentYear + 1))->map(fn (int $y) => [
            'id' => $y,
            'name' => (string) $y,
        ]);
    }
}
