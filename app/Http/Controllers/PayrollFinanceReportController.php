<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CustomPayrollItem;
use App\Models\EmployeeResignation;
use App\Models\Jurnal;
use App\Models\JurnalGlobal;
use App\Services\PayrollGajiSplitCalculator;
use App\Services\PayrollSlipBreakdownBuilder;
use App\Services\BankBookService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
                'payroll_generated_id' => $report['payroll_generated_id'] ?? null,
                'gajian1_paid_at' => $report['gajian1_paid_at'] ?? null,
                'gajian2_paid_at' => $report['gajian2_paid_at'] ?? null,
            ],
            'bankAccounts' => BankAccount::where('is_active', 1)
                ->with('outlet')
                ->orderBy('bank_name')
                ->get()
                ->map(fn ($bank) => [
                    'id' => $bank->id,
                    'label' => trim($bank->bank_name.' — '.$bank->account_number.' ('.($bank->outlet?->nama_outlet ?? 'Head Office').')'),
                    'coa_id' => $bank->coa_id,
                ]),
            'payrollCoa' => DB::table('chart_of_accounts')
                ->where('code', '6009')
                ->orWhere('name', 'PAYROLL')
                ->orderBy('id')
                ->first(['id', 'code', 'name']),
        ]);
    }

    public function pay(Request $request, BankBookService $bankBookService)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'phase' => 'required|in:gajian1,gajian2',
            'paid_at' => 'required|date',
            'transfer_bank_account_id' => 'nullable|exists:bank_accounts,id',
            'cash_bank_account_id' => 'nullable|exists:bank_accounts,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $outletId = (int) $validated['outlet_id'];
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $phase = (string) $validated['phase'];
        $paidAt = (string) $validated['paid_at'];
        $notes = $validated['notes'] ?? null;

        $payrollGenerated = DB::table('payroll_generated')
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $payrollGenerated) {
            return back()->with('error', 'Payroll belum di-generate untuk periode ini.');
        }

        if ($phase === 'gajian1' && ! empty($payrollGenerated->gajian1_paid_at)) {
            return back()->with('error', 'Gajian 1 sudah dibayar.');
        }
        if ($phase === 'gajian2' && ! empty($payrollGenerated->gajian2_paid_at)) {
            return back()->with('error', 'Gajian 2 sudah dibayar.');
        }

        $report = $this->buildReportData($outletId, $month, $year);
        if (! ($report['has_generated'] ?? false)) {
            return back()->with('error', 'Payroll belum di-generate untuk periode ini.');
        }

        $amountKey = $phase === 'gajian1' ? 'total_gaji_akhir_bulan' : 'total_gaji_tanggal_8';
        $transferTotal = 0.0;
        $cashTotal = 0.0;

        foreach (($report['payment_rows'] ?? []) as $row) {
            $method = ($row['payment_method'] ?? 'transfer') === 'cash' ? 'cash' : 'transfer';
            $amt = (float) ($row[$amountKey] ?? 0);
            if ($amt <= 0) {
                continue;
            }
            if ($method === 'cash') {
                $cashTotal += $amt;
            } else {
                $transferTotal += $amt;
            }
        }

        if ($transferTotal <= 0 && $cashTotal <= 0) {
            return back()->with('error', 'Tidak ada nominal payroll untuk dibayar pada fase ini.');
        }

        $transferBankId = $validated['transfer_bank_account_id'] ? (int) $validated['transfer_bank_account_id'] : null;
        $cashBankId = $validated['cash_bank_account_id'] ? (int) $validated['cash_bank_account_id'] : null;

        if ($transferTotal > 0 && ! $transferBankId) {
            return back()->with('error', 'Pilih rekening untuk pembayaran Transfer.');
        }
        if ($cashTotal > 0 && ! $cashBankId) {
            return back()->with('error', 'Pilih rekening untuk pembayaran Cash/Kas.');
        }

        $payrollCoa = DB::table('chart_of_accounts')
            ->where('code', '6009')
            ->orWhere('name', 'PAYROLL')
            ->orderBy('id')
            ->first();
        if (! $payrollCoa) {
            return back()->with('error', 'COA PAYROLL (code 6009) tidak ditemukan.');
        }

        $outletName = (string) (DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet') ?? 'Outlet');
        $periodLabel = str_pad((string) $month, 2, '0', STR_PAD_LEFT).'/'.$year;
        $baseDesc = "Pembayaran Payroll {$phase} {$outletName} {$periodLabel}";
        if ($notes) {
            $baseDesc .= " — {$notes}";
        }

        DB::beginTransaction();
        try {
            $noJurnal = Jurnal::generateNoJurnal();

            $createLines = function (int $bankAccountId, float $amount, string $methodLabel) use ($payrollCoa, $paidAt, $outletId, $noJurnal, $baseDesc, $payrollGenerated) {
                $bankAccount = BankAccount::find($bankAccountId);
                if (! $bankAccount || ! $bankAccount->coa_id) {
                    throw new \RuntimeException("Rekening {$methodLabel} tidak valid atau belum punya COA.");
                }

                $keterangan = $baseDesc." ({$methodLabel})";

                Jurnal::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $paidAt,
                    'keterangan' => $keterangan,
                    'coa_debit_id' => (int) $payrollCoa->id,
                    'coa_kredit_id' => (int) $bankAccount->coa_id,
                    'jumlah_debit' => $amount,
                    'jumlah_kredit' => $amount,
                    'outlet_id' => $outletId,
                    'reference_type' => 'payroll_payment',
                    'reference_id' => (int) $payrollGenerated->id,
                    'status' => 'posted',
                    'created_by' => auth()->id() ?? 1,
                ]);

                JurnalGlobal::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $paidAt,
                    'keterangan' => $keterangan,
                    'coa_debit_id' => (int) $payrollCoa->id,
                    'coa_kredit_id' => (int) $bankAccount->coa_id,
                    'jumlah_debit' => $amount,
                    'jumlah_kredit' => $amount,
                    'outlet_id' => $outletId,
                    'source_module' => 'payroll_payment',
                    'source_id' => (int) $payrollGenerated->id,
                    'reference_type' => 'payroll_payment',
                    'reference_id' => (int) $payrollGenerated->id,
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id() ?? 1,
                    'created_by' => auth()->id() ?? 1,
                ]);

                $bankBookService = app(BankBookService::class);
                $bankBookService->createEntry([
                    'bank_account_id' => $bankAccountId,
                    'transaction_date' => $paidAt,
                    'transaction_type' => 'debit',
                    'amount' => $amount,
                    'description' => $keterangan,
                    'reference_type' => 'payroll_payment',
                    'reference_id' => (int) $payrollGenerated->id,
                    'created_by' => auth()->id() ?? 1,
                ]);
            };

            if ($transferTotal > 0) {
                $createLines($transferBankId, round($transferTotal, 2), 'Transfer');
            }
            if ($cashTotal > 0) {
                $createLines($cashBankId, round($cashTotal, 2), 'Cash');
            }

            $update = [];
            if ($phase === 'gajian1') {
                $update = [
                    'gajian1_paid_at' => now(),
                    'gajian1_paid_by' => auth()->id(),
                    'gajian1_paid_transfer_bank_account_id' => $transferBankId,
                    'gajian1_paid_cash_bank_account_id' => $cashBankId,
                    'gajian1_paid_no_jurnal' => $noJurnal,
                    'updated_at' => now(),
                ];
            } else {
                $update = [
                    'gajian2_paid_at' => now(),
                    'gajian2_paid_by' => auth()->id(),
                    'gajian2_paid_transfer_bank_account_id' => $transferBankId,
                    'gajian2_paid_cash_bank_account_id' => $cashBankId,
                    'gajian2_paid_no_jurnal' => $noJurnal,
                    'updated_at' => now(),
                ];
            }

            DB::table('payroll_generated')->where('id', $payrollGenerated->id)->update($update);

            DB::commit();

            return back()->with('success', "Pembayaran {$phase} berhasil diposting. No Jurnal: {$noJurnal}");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal posting pembayaran payroll: '.$e->getMessage());
        }
    }

    public function rollbackPayment(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:tbl_data_outlet,id_outlet',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'phase' => 'required|in:gajian1,gajian2',
        ]);

        $outletId = (int) $validated['outlet_id'];
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $phase = (string) $validated['phase'];

        $payrollGenerated = DB::table('payroll_generated')
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $payrollGenerated) {
            return back()->with('error', 'Payroll belum di-generate untuk periode ini.');
        }

        $paidAtCol = $phase.'_paid_at';
        $noJurnalCol = $phase.'_paid_no_jurnal';
        $transferBankCol = $phase.'_paid_transfer_bank_account_id';
        $cashBankCol = $phase.'_paid_cash_bank_account_id';

        if (empty($payrollGenerated->{$paidAtCol})) {
            return back()->with('error', ucfirst($phase).' belum dibayar.');
        }

        $noJurnal = (string) ($payrollGenerated->{$noJurnalCol} ?? '');
        $phaseLabel = "Pembayaran Payroll {$phase}";

        DB::beginTransaction();
        try {
            if ($noJurnal !== '') {
                Jurnal::where('no_jurnal', $noJurnal)
                    ->where('reference_type', 'payroll_payment')
                    ->where('reference_id', (int) $payrollGenerated->id)
                    ->delete();

                JurnalGlobal::where('no_jurnal', $noJurnal)
                    ->where('reference_type', 'payroll_payment')
                    ->where('reference_id', (int) $payrollGenerated->id)
                    ->delete();
            }

            DB::table('bank_books')
                ->where('reference_type', 'payroll_payment')
                ->where('reference_id', (int) $payrollGenerated->id)
                ->where('description', 'like', '%'.$phaseLabel.'%')
                ->delete();

            $update = [
                $paidAtCol => null,
                str_replace('_at', '_by', $paidAtCol) => null,
                $transferBankCol => null,
                $cashBankCol => null,
                $noJurnalCol => null,
                'updated_at' => now(),
            ];
            DB::table('payroll_generated')->where('id', $payrollGenerated->id)->update($update);

            DB::commit();
            return back()->with('success', "Rollback pembayaran {$phase} berhasil.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal rollback pembayaran payroll: '.$e->getMessage());
        }
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

        $spreadsheet = new Spreadsheet();

        $paymentHeaders = [
            'No', 'Nama Karyawan', 'Jabatan', 'Divisi', 'Level', 'Join Date', 'Keterangan',
            'Nama Rekening', 'No. Rekening', 'Metode Bayar', 'Gaji Akhir Bulan', 'Gaji Tanggal 8', 'Total Gaji',
        ];
        $paymentRows = [];
        $rowNum = 1;
        foreach ($report['payment_rows'] as $row) {
            $paymentRows[] = [
                $rowNum++,
                $row['nama_lengkap'],
                $row['jabatan'],
                $row['divisi'],
                $row['level'],
                $this->formatDateForExport($row['tanggal_masuk']),
                $this->formatEmployeeStatusLabel($row),
                $row['nama_rekening'],
                $row['no_rekening'],
                $this->formatPaymentMethodLabel($row['payment_method'] ?? 'transfer'),
                $row['total_gaji_akhir_bulan'],
                $row['total_gaji_tanggal_8'],
                $row['total_gaji'],
            ];
        }
        $paymentTotal = [
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $report['summary']['total_gaji_akhir_bulan'],
            $report['summary']['total_gaji_tanggal_8'],
            $report['summary']['total_gaji'],
        ];

        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Pembayaran Gaji');
        $this->writeFormattedReportSheet(
            $sheet1,
            'LAPORAN PAYROLL - PEMBAYARAN GAJI',
            $report['outlet_name'],
            $report['periode'],
            $paymentHeaders,
            $paymentRows,
            [11, 12, 13],
            [9],
            $paymentTotal
        );

        $bpjsHeaders = [
            'No',
            'Nama Karyawan',
            'NIK',
            'Jabatan',
            'Divisi',
            'Level',
            'Join Date',
            'No. BPJS Kesehatan',
            'No. BPJS Ketenagakerjaan',
            'Keterangan',
            'BPJS Kesehatan (Perusahaan)',
            'JHT (Perusahaan)',
            'JP (Perusahaan)',
            'JKK (Perusahaan)',
            'JKM (Perusahaan)',
            'Total BPJS Perusahaan',
        ];
        $bpjsRows = [];
        $rowNum = 1;
        foreach ($report['bpjs_rows'] as $row) {
            $bpjsRows[] = [
                $rowNum++,
                $row['nama_lengkap'],
                $row['nik'],
                $row['jabatan'],
                $row['divisi'],
                $row['level'],
                $this->formatDateForExport($row['tanggal_masuk']),
                $row['bpjs_health_number'] ?? '-',
                $row['bpjs_employment_number'] ?? '-',
                $this->formatEmployeeStatusLabel($row),
                $row['kes_perusahaan'],
                $row['jht_perusahaan'],
                $row['jp_perusahaan'],
                $row['jkk_perusahaan'],
                $row['jkm_perusahaan'],
                $row['total_bpjs_perusahaan'],
            ];
        }
        $bpjsTotal = [
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            array_sum(array_column($report['bpjs_rows'], 'kes_perusahaan')),
            array_sum(array_column($report['bpjs_rows'], 'jht_perusahaan')),
            array_sum(array_column($report['bpjs_rows'], 'jp_perusahaan')),
            array_sum(array_column($report['bpjs_rows'], 'jkk_perusahaan')),
            array_sum(array_column($report['bpjs_rows'], 'jkm_perusahaan')),
            $report['summary']['total_bpjs_perusahaan'],
        ];

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('BPJS Perusahaan');
        $this->writeFormattedReportSheet(
            $sheet2,
            'LAPORAN PAYROLL - BPJS PERUSAHAAN',
            $report['outlet_name'],
            $report['periode'],
            $bpjsHeaders,
            $bpjsRows,
            [11, 12, 13, 14, 15, 16],
            [3, 8, 9],
            $bpjsTotal
        );

        $writer = new Xlsx($spreadsheet);
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
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->endOfDay();
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
            ->leftJoin('tbl_data_jabatan as j', 'j.id_jabatan', '=', 'u.id_jabatan')
            ->leftJoin('tbl_data_divisi as d', 'd.id', '=', 'u.division_id')
            ->leftJoin('tbl_data_level as l', 'l.id', '=', 'j.id_level')
            ->where('pgd.payroll_generated_id', $payrollGenerated->id)
            ->orderBy('u.nama_lengkap')
            ->select(
                'pgd.*',
                'u.nama_lengkap as user_nama_lengkap',
                'u.nama_rekening as user_nama_rekening',
                'u.no_rekening as user_no_rekening',
                'u.nik as user_nik',
                'u.tanggal_masuk',
                'u.bpjs_health_number as user_bpjs_health_number',
                'u.bpjs_employment_number as user_bpjs_employment_number',
                'j.nama_jabatan',
                'd.nama_divisi',
                'l.nama_level'
            )
            ->get();

        if ($details->isEmpty()) {
            return array_merge($empty, [
                'outlet_name' => $outletName,
                'periode' => $periode,
            ]);
        }

        $userIds = $details->pluck('user_id')->all();
        $resignations = EmployeeResignation::where('status', 'approved')
            ->whereBetween('resignation_date', [$start, $end])
            ->whereIn('employee_id', $userIds)
            ->get()
            ->keyBy('employee_id');

        [$mutationOutMap, $mutationInMap] = $this->buildMutationMaps($outletName, $start, $end, $userIds);

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
            $userCustomItems = $customItemsByUser->get($detail->user_id, collect());
            $customItemsCollection = $this->resolveCustomItemsCollection($detail, $userCustomItems);
            $customSums = $this->resolveCustomGajianSums($customItemsCollection);

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

            $employeeMeta = $this->buildEmployeeRowMeta(
                $detail,
                $resignations,
                $mutationOutMap,
                $mutationInMap,
                $startDate,
                $endDate
            );

            $paymentRows[] = array_merge($employeeMeta, [
                'user_id' => $detail->user_id,
                'nama_lengkap' => $detail->user_nama_lengkap ?: ($detail->nama_lengkap ?? '-'),
                'nama_rekening' => $detail->user_nama_rekening ?: '-',
                'no_rekening' => $detail->user_no_rekening ?: '-',
                'payment_method' => $detail->payment_method ?? 'transfer',
                'total_gaji_akhir_bulan' => $gajiSplit['total_gaji_akhir_bulan'],
                'total_gaji_tanggal_8' => $gajiSplit['total_gaji_tanggal_8'],
                'total_gaji' => $gajiSplit['total_gaji'],
                'slip_breakdown' => PayrollSlipBreakdownBuilder::build($detail, $customItemsCollection, $gajiSplit),
            ]);

            $sumAkhirBulan += $gajiSplit['total_gaji_akhir_bulan'];
            $sumTanggal8 += $gajiSplit['total_gaji_tanggal_8'];
            $sumTotalGaji += $gajiSplit['total_gaji'];

            $bpjsRow = $this->mapBpjsPerusahaanRow($detail);
            if ($bpjsRow !== null) {
                $bpjsRows[] = array_merge($employeeMeta, $bpjsRow);
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
            'payroll_generated_id' => (int) $payrollGenerated->id,
            'gajian1_paid_at' => $payrollGenerated->gajian1_paid_at ?? null,
            'gajian2_paid_at' => $payrollGenerated->gajian2_paid_at ?? null,
        ];
    }

    private function resolveCustomItemsCollection(object $detail, Collection $userCustomItems): Collection
    {
        $customItemsData = json_decode($detail->custom_items ?? '[]', true) ?? [];
        $customItemsCollection = collect($customItemsData);

        if ($userCustomItems->isNotEmpty()) {
            return $userCustomItems;
        }

        return $customItemsCollection;
    }

    /**
     * @return array{
     *     custom_earnings_gajian1: float,
     *     custom_deductions_gajian1: float,
     *     custom_earnings_gajian2: float,
     *     custom_deductions_gajian2: float
     * }
     */
    private function resolveCustomGajianSums(Collection $customItemsCollection): array
    {
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
            'bpjs_health_number' => $this->formatUserBpjsNumber($detail->user_bpjs_health_number ?? null),
            'bpjs_employment_number' => $this->formatUserBpjsNumber($detail->user_bpjs_employment_number ?? null),
            'kes_perusahaan' => round($lineAmounts['kes_perusahaan']),
            'jht_perusahaan' => round($lineAmounts['jht_perusahaan']),
            'jp_perusahaan' => round($lineAmounts['jp_perusahaan']),
            'jkk_perusahaan' => round($lineAmounts['jkk_perusahaan']),
            'jkm_perusahaan' => round($lineAmounts['jkm_perusahaan']),
            'total_bpjs_perusahaan' => round((float) ($bpjsDetail['total_perusahaan'] ?? 0)),
        ];
    }

    private function formatUserBpjsNumber(?string $value): string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed !== '' ? $trimmed : '-';
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @return array{array<int, array<string, string>>, array<int, array<string, string>>}
     */
    private function buildMutationMaps(?string $outletName, string $start, string $end, array $userIds): array
    {
        $mutationOutMap = [];
        $mutationInMap = [];

        if (! $outletName || $userIds === []) {
            return [$mutationOutMap, $mutationInMap];
        }

        $baseQuery = fn () => DB::table('employee_movements')
            ->where('employment_type', 'mutation')
            ->where('unit_property_change', true)
            ->whereNotNull('employment_effective_date')
            ->where('employment_effective_date', '>', $start)
            ->where('employment_effective_date', '<=', $end)
            ->whereIn('status', ['executed', 'approved', 'pending'])
            ->whereIn('employee_id', $userIds)
            ->select('employee_id', 'unit_property_from', 'unit_property_to', 'employment_effective_date');

        foreach ($baseQuery()->where('unit_property_from', $outletName)->get() as $mutation) {
            $mutationOutMap[$mutation->employee_id] = [
                'effective_date' => $mutation->employment_effective_date,
                'outlet_from' => $mutation->unit_property_from,
                'outlet_to' => $mutation->unit_property_to,
            ];
        }

        foreach ($baseQuery()->where('unit_property_to', $outletName)->get() as $mutation) {
            if (isset($mutationOutMap[$mutation->employee_id])) {
                continue;
            }

            $mutationInMap[$mutation->employee_id] = [
                'effective_date' => $mutation->employment_effective_date,
                'outlet_from' => $mutation->unit_property_from,
                'outlet_to' => $mutation->unit_property_to,
            ];
        }

        return [$mutationOutMap, $mutationInMap];
    }

    /**
     * @param  Collection<int, EmployeeResignation>  $resignations
     * @param  array<int, array<string, string>>  $mutationOutMap
     * @param  array<int, array<string, string>>  $mutationInMap
     * @return array<string, mixed>
     */
    private function buildEmployeeRowMeta(
        object $detail,
        Collection $resignations,
        array $mutationOutMap,
        array $mutationInMap,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $resignationDate = null;
        $resignation = $resignations->get($detail->user_id);
        if ($resignation && $resignation->resignation_date) {
            $resignDate = Carbon::parse($resignation->resignation_date);
            if ($resignDate->between($startDate, $endDate)) {
                $resignationDate = $resignDate->format('Y-m-d');
            }
        }

        $mutation = $mutationOutMap[$detail->user_id] ?? $mutationInMap[$detail->user_id] ?? null;
        $isMutated = $mutation !== null;

        $tanggalMasuk = $detail->tanggal_masuk
            ? Carbon::parse($detail->tanggal_masuk)->format('Y-m-d')
            : null;

        return [
            'jabatan' => $detail->jabatan ?: ($detail->nama_jabatan ?? '-'),
            'divisi' => $detail->divisi ?: ($detail->nama_divisi ?? '-'),
            'level' => $detail->nama_level ?? '-',
            'tanggal_masuk' => $tanggalMasuk,
            'is_mutated_employee' => $isMutated,
            'mutation_effective_date' => $isMutated
                ? Carbon::parse($mutation['effective_date'])->format('Y-m-d')
                : null,
            'mutation_outlet_from' => $isMutated ? ($mutation['outlet_from'] ?? null) : null,
            'mutation_outlet_to' => $isMutated ? ($mutation['outlet_to'] ?? null) : null,
            'resignation_date' => $resignationDate,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function formatEmployeeStatusLabel(array $row): string
    {
        $parts = [];

        if (! empty($row['is_mutated_employee']) && ! empty($row['mutation_effective_date'])) {
            $parts[] = sprintf(
                'Mutasi: %s dari %s → %s',
                $this->formatDateForExport($row['mutation_effective_date']),
                $row['mutation_outlet_from'] ?? '-',
                $row['mutation_outlet_to'] ?? '-'
            );
        }

        if (! empty($row['resignation_date'])) {
            $parts[] = 'Resign: ' . $this->formatDateForExport($row['resignation_date']);
        }

        return $parts !== [] ? implode(' | ', $parts) : '-';
    }

    private function formatDateForExport(?string $date): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->format('d/m/Y');
    }

    private function formatPaymentMethodLabel(?string $paymentMethod): string
    {
        return ($paymentMethod ?? 'transfer') === 'cash' ? 'Cash' : 'Transfer';
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

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, int>  $numericColIndexes  1-based column indexes
     * @param  array<int, int>  $textColIndexes  1-based column indexes (e.g. NIK, no rekening)
     * @param  array<int, mixed>|null  $totalRow
     */
    private function writeFormattedReportSheet(
        Worksheet $sheet,
        string $title,
        ?string $outletName,
        ?string $periode,
        array $headers,
        array $rows,
        array $numericColIndexes,
        array $textColIndexes = [],
        ?array $totalRow = null
    ): void {
        $colCount = count($headers);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $headerRow = 5;
        $dataStartRow = $headerRow + 1;
        $dataEndRow = $rows !== [] ? $dataStartRow + count($rows) - 1 : $headerRow;
        $totalRowIndex = $rows !== [] && $totalRow !== null ? $dataEndRow + 1 : null;
        $tableEndRow = $totalRowIndex ?? $dataEndRow;

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A2', 'Outlet: ' . ($outletName ?: '-'));
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A3', 'Periode: ' . ($periode ?: '-'));
        $sheet->mergeCells("A3:{$lastCol}3");

        $sheet->fromArray($headers, null, 'A' . $headerRow);

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A' . $dataStartRow);

            foreach ($rows as $rowOffset => $row) {
                foreach ($textColIndexes as $colIndex) {
                    $col = Coordinate::stringFromColumnIndex($colIndex);
                    $excelRow = $dataStartRow + $rowOffset;
                    $value = $row[$colIndex - 1] ?? '';
                    $sheet->setCellValueExplicit("{$col}{$excelRow}", (string) $value, DataType::TYPE_STRING);
                }
            }
        }

        if ($totalRowIndex !== null) {
            $sheet->fromArray($totalRow, null, 'A' . $totalRowIndex);
        }

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2:A3')->applyFromArray([
            'font' => ['size' => 11, 'color' => ['rgb' => '374151']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        if ($rows !== []) {
            for ($row = $dataStartRow; $row <= $dataEndRow; $row++) {
                if (($row - $dataStartRow) % 2 === 1) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F8FAFC'],
                        ],
                    ]);
                }
            }
        }

        if ($totalRowIndex !== null) {
            $sheet->getStyle("A{$totalRowIndex}:{$lastCol}{$totalRowIndex}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F172A'],
                ],
            ]);
            $sheet->getStyle("A{$totalRowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$tableEndRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFD1D5DB'));

        foreach ($numericColIndexes as $colIndex) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$tableEndRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$tableEndRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        foreach ($textColIndexes as $colIndex) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $endRow = max($dataEndRow, $totalRowIndex ?? $dataEndRow);
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$endRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $sheet->getStyle("A{$dataStartRow}:A{$tableEndRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $leftAlignUntil = min(3, $colCount);
        for ($colIndex = 2; $colIndex <= $leftAlignUntil; $colIndex++) {
            if (in_array($colIndex, $numericColIndexes, true)) {
                continue;
            }
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$tableEndRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        for ($colIndex = 1; $colIndex <= $colCount; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->freezePane('A' . $dataStartRow);
    }
}
