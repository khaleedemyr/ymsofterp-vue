<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;

class PayrollController extends Controller
{
    private function normalizePayrollNominal($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $clean = preg_replace('/[^\d.-]/', '', (string) $value);

        if ($clean === '' || $clean === '-') {
            return 0;
        }

        return (int) round((float) $clean);
    }

    private function resolvePayrollScopeForUser(User $user, $outletId, $divisionId): array
    {
        $effectiveOutletId = !empty($outletId) ? (int) $outletId : (int) $user->id_outlet;
        $effectiveDivisionId = !empty($divisionId) ? (int) $divisionId : (int) $user->division_id;

        return [$effectiveOutletId, $effectiveDivisionId];
    }

    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');

        // Dropdown Outlet
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();
        // Dropdown Divisi
        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->select('id', 'nama_divisi as name')
            ->orderBy('nama_divisi')
            ->get();

        // Data karyawan
        $users = collect();
        $payrollMaster = collect();
        // Query users sesuai filter yang diisi
        $userQuery = User::query()->where('status', 'A');
        if ($outletId) {
            $userQuery->where('id_outlet', $outletId);
        }
        if ($divisionId) {
            $userQuery->where('division_id', $divisionId);
        }
        if ($outletId || $divisionId) {
            $users = $userQuery->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'id_outlet', 'division_id']);
            // Join jabatan
            $jabatans = DB::table('tbl_data_jabatan')->pluck('nama_jabatan', 'id_jabatan');
            // Ambil data level dari jabatan
            $jabatanLevels = DB::table('tbl_data_jabatan')->pluck('id_level', 'id_jabatan');
            // Ambil data point dari level
            $levelPoints = DB::table('tbl_data_level')
                ->pluck('nilai_point', 'id');
            foreach ($users as $u) {
                $u->jabatan = $jabatans[$u->id_jabatan] ?? '-';
                // Ambil point dari level melalui jabatan
                $userLevel = $jabatanLevels[$u->id_jabatan] ?? null;
                $u->point = $userLevel ? ($levelPoints[$userLevel] ?? 0) : 0;
            }
            // Ambil payroll master untuk user2 tsb
            $userIds = $users->pluck('id');
            $payrollRows = DB::table('payroll_master')
                ->when($outletId, function($q) use ($outletId) {
                    $q->where('outlet_id', $outletId);
                })
                ->when($divisionId, function($q) use ($divisionId) {
                    $q->where('division_id', $divisionId);
                })
                ->whereIn('user_id', $userIds)
                ->orderByDesc('updated_at')
                ->get()
                ->groupBy('user_id');

            $payrollMaster = $users->mapWithKeys(function ($user) use ($payrollRows, $outletId, $divisionId) {
                [$effectiveOutletId, $effectiveDivisionId] = $this->resolvePayrollScopeForUser($user, $outletId, $divisionId);

                $payroll = collect($payrollRows->get($user->id, []))->first(function ($row) use ($effectiveOutletId, $effectiveDivisionId) {
                    return (int) $row->outlet_id === $effectiveOutletId
                        && (int) $row->division_id === $effectiveDivisionId;
                });

                return [$user->id => $payroll];
            })->filter();
        }

        return Inertia::render('Payroll/Master', [
            'outlets' => $outlets,
            'divisions' => $divisions,
            'users' => $users,
            'filter' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
            ],
            'payrollMaster' => $payrollMaster,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer',
            'division_id' => 'nullable|integer',
            'payrollData' => 'required|array',
            'payrollData.*.user_id' => 'required|integer',
        ]);
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');

        $rows = $request->input('payrollData');
        $usersById = User::query()
            ->whereIn('id', collect($rows)->pluck('user_id')->filter()->all())
            ->get(['id', 'id_outlet', 'division_id'])
            ->keyBy('id');

        foreach ($rows as $row) {
            $user = $usersById->get($row['user_id']);

            if (! $user) {
                continue;
            }

            [$effectiveOutletId, $effectiveDivisionId] = $this->resolvePayrollScopeForUser($user, $outletId, $divisionId);

            DB::table('payroll_master')->updateOrInsert(
                [
                    'user_id' => $row['user_id'],
                    'outlet_id' => $effectiveOutletId,
                    'division_id' => $effectiveDivisionId,
                ],
                [
                    'gaji' => $this->normalizePayrollNominal($row['gaji'] ?? 0),
                    'tunjangan' => $this->normalizePayrollNominal($row['tunjangan'] ?? 0),
                    'ot' => !empty($row['ot']) ? 1 : 0,
                    'um' => !empty($row['um']) ? 1 : 0,
                    'ph' => !empty($row['ph']) ? 1 : 0,
                    'sc' => !empty($row['sc']) ? 1 : 0,
                    'bpjs_jkn' => !empty($row['bpjs_jkn']) ? 1 : 0,
                    'bpjs_tk' => !empty($row['bpjs_tk']) ? 1 : 0,
                    'lb' => !empty($row['lb']) ? 1 : 0,
                    'deviasi' => !empty($row['deviasi']) ? 1 : 0,
                    'city_ledger' => !empty($row['city_ledger']) ? 1 : 0,
                    'updated_at' => now(),
                ]
            );
        }
        return response()->json(['success' => true, 'message' => 'Data payroll berhasil disimpan']);
    }

    /**
     * Normalisasi teks header Excel untuk mapping kolom import.
     */
    private function normalizePayrollHeader(?string $h): string
    {
        $h = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $h)));
        $h = preg_replace('/\s*\([^)]*\)\s*/', '', $h);

        return trim($h);
    }

    /**
     * Map header label -> key payroll; null = kolom informasi saja (abaikan saat import nilai).
     */
    private function payrollHeaderToFieldKey(string $normalized): ?string
    {
        $map = [
            'NIK' => 'nik',
            'NAMA KARYAWAN' => null,
            'NAMA' => null,
            'JABATAN' => null,
            'POINT' => null,
            'GAJI' => 'gaji',
            'TUNJANGAN' => 'tunjangan',
            'OT' => 'ot',
            'UM' => 'um',
            'PH' => 'ph',
            'SC' => 'sc',
            'BPJS JKN' => 'bpjs_jkn',
            'BPJS TK' => 'bpjs_tk',
            'L&B' => 'lb',
            'L & B' => 'lb',
            'LB' => 'lb',
            'DEVIASI' => 'deviasi',
            'CITY LEDGER' => 'city_ledger',
            // Template lama (import tetap bisa jika header ini ada)
            'OUTLET' => null,
            'DIVISI' => null,
        ];

        return array_key_exists($normalized, $map) ? $map[$normalized] : null;
    }

    private function payrollExcelBool($cell): int
    {
        if ($cell === null || $cell === '') {
            return 0;
        }
        if (is_numeric($cell)) {
            return (float) $cell != 0.0 ? 1 : 0;
        }
        $v = strtoupper(trim((string) $cell));

        return in_array($v, ['1', 'Y', 'YES', 'TRUE', 'YA', 'X', 'V'], true) ? 1 : 0;
    }

    private function payrollExcelNumber($cell): int|string
    {
        if ($cell === null || $cell === '') {
            return 0;
        }
        if (is_numeric($cell)) {
            return (int) round((float) $cell);
        }
        $s = preg_replace('/[^\d.-]/', '', (string) $cell);

        return $s === '' || $s === '-' ? 0 : (int) round((float) $s);
    }

    /**
     * User + jabatan + point sama seperti halaman Master Payroll (filter outlet/divisi).
     */
    private function usersForPayrollMaster(?string $outletId, ?string $divisionId)
    {
        $userQuery = User::query()->where('status', 'A');
        if ($outletId) {
            $userQuery->where('id_outlet', $outletId);
        }
        if ($divisionId) {
            $userQuery->where('division_id', $divisionId);
        }
        if (! $outletId && ! $divisionId) {
            return collect();
        }
        $users = $userQuery->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nik', 'id_jabatan']);
        $jabatanLevels = DB::table('tbl_data_jabatan')->pluck('id_level', 'id_jabatan');
        $levelPoints = DB::table('tbl_data_level')->pluck('nilai_point', 'id');
        $jabatans = DB::table('tbl_data_jabatan')->pluck('nama_jabatan', 'id_jabatan');
        foreach ($users as $u) {
            $u->jabatan = $jabatans[$u->id_jabatan] ?? '-';
            $userLevel = $jabatanLevels[$u->id_jabatan] ?? null;
            $u->point = $userLevel ? ($levelPoints[$userLevel] ?? 0) : 0;
        }

        return $users;
    }

    public function downloadTemplate(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');

        $users = $this->usersForPayrollMaster($outletId, $divisionId);

        // Pakai PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $outletLabel = $outletId ? (DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet') ?? '-') : '-';
        $divisiLabel = $divisionId ? (DB::table('tbl_data_divisi')->where('id', $divisionId)->value('nama_divisi') ?? '-') : '-';

        // Instruksi pengisian (sama urutan kolom dengan tabel Master Payroll)
        $sheet->setCellValue('A1', 'INSTRUKSI PENGISIAN:');
        $sheet->setCellValue('A2', '1. Kolom identitas (NIK, Nama Karyawan, Jabatan, Point) tidak perlu diubah.');
        $sheet->setCellValue('A3', '2. Filter aktif — Outlet: '.$outletLabel.' | Divisi: '.$divisiLabel.'. Upload hanya untuk kombinasi filter yang sama.');
        $sheet->setCellValue('A4', '3. Kolom nominal (GAJI, TUNJANGAN) diisi angka; boleh tanpa pemisah ribuan.');
        $sheet->setCellValue('A5', '4. Kolom flag (OT, UM, PH, SC, BPJS JKN, BPJS TK, L&B, Deviasi, City Ledger) diisi 1 (ya) atau 0 (tidak).');
        $sheet->setCellValue('A6', '5. Jangan mengubah urutan baris/kolom header. Setelah diisi, upload dari halaman Master Payroll dengan outlet & divisi yang sama.');

        // Header kolom — selaras dengan resources/js/Pages/Payroll/Master.vue
        $header = [
            'NIK',
            'Nama Karyawan',
            'Jabatan',
            'Point',
            'GAJI (EARN)',
            'TUNJANGAN (EARN)',
            'OT (EARN)',
            'UM (EARN)',
            'PH (EARN)',
            'SC (EARN)',
            'BPJS JKN (DEDUCTION)',
            'BPJS TK (DEDUCTION)',
            'L&B (DEDUCTION)',
            'Deviasi (DEDUCTION)',
            'City Ledger (DEDUCTION)',
        ];
        $sheet->fromArray($header, null, 'A8');

        $rows = [];
        foreach ($users as $u) {
            $rows[] = [
                $u->nik,
                $u->nama_lengkap,
                $u->jabatan,
                $u->point,
                '', '', '', '', '', '', '', '', '', '', '',
            ];
        }
        $sheet->fromArray($rows, null, 'A9');

        // Download response
        $filename = 'template_payroll_' . date('Ymd_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $excelOutput = ob_get_clean();
        return response($excelOutput)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'outlet_id' => 'required|integer',
            'division_id' => 'nullable',
        ]);

        $filterOutletId = (int) $request->input('outlet_id');
        $divisionRaw = $request->input('division_id');
        $filterDivisionId = ($divisionRaw === null || $divisionRaw === '' || (int) $divisionRaw === 0) ? 0 : (int) $divisionRaw;

        $file = $request->file('file');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $headerRowKey = null;
        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                if ($this->normalizePayrollHeader($cell) === 'NIK') {
                    $headerRowKey = $i;
                    break 2;
                }
            }
        }
        if ($headerRowKey === null) {
            return response()->json(['success' => false, 'message' => 'Header kolom NIK tidak ditemukan di file.'], 422);
        }

        $headerCells = $rows[$headerRowKey];
        $fieldToCol = [];
        $outletCol = null;
        $divisiCol = null;
        foreach ($headerCells as $colLetter => $label) {
            $n = $this->normalizePayrollHeader($label ?? '');
            if ($n === '') {
                continue;
            }
            if ($n === 'OUTLET') {
                $outletCol = $colLetter;
                continue;
            }
            if ($n === 'DIVISI') {
                $divisiCol = $colLetter;
                continue;
            }
            $fk = $this->payrollHeaderToFieldKey($n);
            if ($fk !== null) {
                $fieldToCol[$fk] = $colLetter;
            }
        }

        if (! isset($fieldToCol['nik'])) {
            return response()->json(['success' => false, 'message' => 'Kolom NIK tidak ditemukan pada baris header.'], 422);
        }

        $legacyOutletDivisi = $outletCol !== null && $divisiCol !== null;

        $dataRowKeys = array_filter(array_keys($rows), fn ($k) => $k > $headerRowKey);
        $success = 0;
        $failed = 0;
        $failMsg = [];

        foreach ($dataRowKeys as $rk) {
            $row = $rows[$rk];
            if (! is_array($row)) {
                continue;
            }
            $nik = trim((string) ($row[$fieldToCol['nik']] ?? ''));
            if ($nik === '') {
                continue;
            }

            $user = User::where('status', 'A')->where('nik', $nik)->first();
            if (! $user) {
                $failed++;
                $failMsg[] = "NIK {$nik} tidak ditemukan di sistem.";

                continue;
            }

            if ($legacyOutletDivisi) {
                $outletName = trim((string) ($row[$outletCol] ?? ''));
                $divisionName = trim((string) ($row[$divisiCol] ?? ''));
                $rowOutletId = DB::table('tbl_data_outlet')->where('nama_outlet', $outletName)->value('id_outlet');
                $rowDivisionId = DB::table('tbl_data_divisi')->where('nama_divisi', $divisionName)->value('id');
                if (! $rowOutletId || ! $rowDivisionId) {
                    $failed++;
                    $failMsg[] = "Outlet/Divisi tidak ditemukan untuk NIK {$nik} (Outlet: {$outletName}, Divisi: {$divisionName}).";

                    continue;
                }
                $effectiveOutletId = (int) $rowOutletId;
                $effectiveDivisionId = (int) $rowDivisionId;
            } else {
                if ($filterOutletId && (int) $user->id_outlet !== $filterOutletId) {
                    $failed++;
                    $failMsg[] = "NIK {$nik} tidak termasuk outlet yang dipilih di filter.";

                    continue;
                }
                if ($filterDivisionId && (int) $user->division_id !== $filterDivisionId) {
                    $failed++;
                    $failMsg[] = "NIK {$nik} tidak termasuk divisi yang dipilih di filter.";

                    continue;
                }
                $effectiveOutletId = $filterOutletId;
                $effectiveDivisionId = $filterDivisionId;
            }

            $payload = [
                'gaji' => isset($fieldToCol['gaji']) ? $this->payrollExcelNumber($row[$fieldToCol['gaji']] ?? null) : 0,
                'tunjangan' => isset($fieldToCol['tunjangan']) ? $this->payrollExcelNumber($row[$fieldToCol['tunjangan']] ?? null) : 0,
                'ot' => isset($fieldToCol['ot']) ? $this->payrollExcelBool($row[$fieldToCol['ot']] ?? null) : 0,
                'um' => isset($fieldToCol['um']) ? $this->payrollExcelBool($row[$fieldToCol['um']] ?? null) : 0,
                'ph' => isset($fieldToCol['ph']) ? $this->payrollExcelBool($row[$fieldToCol['ph']] ?? null) : 0,
                'sc' => isset($fieldToCol['sc']) ? $this->payrollExcelBool($row[$fieldToCol['sc']] ?? null) : 0,
                'bpjs_jkn' => isset($fieldToCol['bpjs_jkn']) ? $this->payrollExcelBool($row[$fieldToCol['bpjs_jkn']] ?? null) : 0,
                'bpjs_tk' => isset($fieldToCol['bpjs_tk']) ? $this->payrollExcelBool($row[$fieldToCol['bpjs_tk']] ?? null) : 0,
                'lb' => isset($fieldToCol['lb']) ? $this->payrollExcelBool($row[$fieldToCol['lb']] ?? null) : 0,
                'deviasi' => isset($fieldToCol['deviasi']) ? $this->payrollExcelBool($row[$fieldToCol['deviasi']] ?? null) : 0,
                'city_ledger' => isset($fieldToCol['city_ledger']) ? $this->payrollExcelBool($row[$fieldToCol['city_ledger']] ?? null) : 0,
                'updated_at' => now(),
            ];

            try {
                DB::table('payroll_master')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'outlet_id' => $effectiveOutletId,
                        'division_id' => $effectiveDivisionId,
                    ],
                    $payload
                );
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $failMsg[] = "NIK {$nik} gagal: ".$e->getMessage();
            }
        }

        $msg = "Import selesai. Berhasil: {$success}, Gagal: {$failed}.";
        if ($failed) {
            $msg .= "\n".implode("\n", $failMsg);
        }

        return response()->json(['success' => $failed === 0, 'message' => $msg]);
    }
} 