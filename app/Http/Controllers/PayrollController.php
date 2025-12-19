<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;

class PayrollController extends Controller
{
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
            $users = $userQuery->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nik', 'id_jabatan']);
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
            $payrollMaster = DB::table('payroll_master')
                ->when($outletId, function($q) use ($outletId) {
                    $q->where('outlet_id', $outletId);
                })
                ->when($divisionId, function($q) use ($divisionId) {
                    $q->where('division_id', $divisionId);
                })
                ->whereIn('user_id', $userIds)
                ->get()
                ->keyBy('user_id');
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
        
        // Jika division_id tidak ada atau 0, gunakan 0 sebagai default
        if (empty($divisionId) || $divisionId == 0) {
            $divisionId = 0;
        }
        
        $rows = $request->input('payrollData');
        foreach ($rows as $row) {
            DB::table('payroll_master')->updateOrInsert(
                [
                    'user_id' => $row['user_id'],
                    'outlet_id' => $outletId,
                    'division_id' => $divisionId,
                ],
                [
                    'gaji' => $row['gaji'] ?? 0,
                    'tunjangan' => $row['tunjangan'] ?? 0,
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

    public function downloadTemplate(Request $request)
    {
        // Ambil semua user aktif
        $users = \App\Models\User::where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get();
        $jabatans = \DB::table('tbl_data_jabatan')->pluck('nama_jabatan', 'id_jabatan');
        $outlets = \DB::table('tbl_data_outlet')->pluck('nama_outlet', 'id_outlet');
        $divisis = \DB::table('tbl_data_divisi')->pluck('nama_divisi', 'id');

        // Pakai PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Instruksi pengisian
        $sheet->setCellValue('A1', 'INSTRUKSI PENGISIAN:');
        $sheet->setCellValue('A2', '1. Kolom identitas (NIK, Nama, Jabatan, Outlet, Divisi) tidak perlu diubah.');
        $sheet->setCellValue('A3', '2. Kolom nominal (GAJI, TUNJANGAN) diisi angka tanpa pemisah ribuan.');
        $sheet->setCellValue('A4', '3. Kolom centang (OT, UM, PH, SC, BPJS JKN, BPJS TK, L&B) diisi 1 (ya) atau 0 (tidak).');
        $sheet->setCellValue('A5', '4. Jangan mengubah urutan/format kolom.');
        $sheet->setCellValue('A6', '5. Setelah diisi, upload kembali file ini ke sistem.');

        // Header kolom
        $header = ['NIK', 'Nama', 'Jabatan', 'Outlet', 'Divisi', 'GAJI', 'TUNJANGAN', 'OT', 'UM', 'PH', 'SC', 'BPJS JKN', 'BPJS TK', 'L&B'];
        $sheet->fromArray($header, null, 'A8');

        // Data karyawan
        $rows = [];
        foreach ($users as $u) {
            $rows[] = [
                $u->nik,
                $u->nama_lengkap,
                $jabatans[$u->id_jabatan] ?? '-',
                $outlets[$u->id_outlet] ?? '-',
                $divisis[$u->division_id] ?? '-',
                '', '', '', '', '', '', '', '', ''
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
        ]);
        $file = $request->file('file');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        // Cari baris header (kolom NIK)
        $headerRow = 0;
        foreach ($rows as $i => $row) {
            if (isset($row['A']) && strtoupper(trim($row['A'])) === 'NIK') {
                $headerRow = $i;
                break;
            }
        }
        if (!$headerRow) {
            return response()->json(['success' => false, 'message' => 'Header kolom NIK tidak ditemukan di file.'], 422);
        }
        $dataRows = array_slice($rows, $headerRow + 1);
        $niks = array_column($dataRows, 'A');
        // Ambil semua user aktif yang NIK-nya ada di file
        $users = \App\Models\User::where('status', 'A')
            ->whereIn('nik', $niks)
            ->get()->keyBy('nik');
        $success = 0; $failed = 0; $failMsg = [];
        foreach ($dataRows as $row) {
            $nik = trim($row['A'] ?? '');
            $outletName = trim($row['D'] ?? '');
            $divisionName = trim($row['E'] ?? '');
            if (!$nik || !isset($users[$nik])) {
                $failed++;
                $failMsg[] = "NIK $nik tidak ditemukan di sistem.";
                continue;
            }
            $user = $users[$nik];
            // Mapping nama outlet/divisi ke id
            $outletId = \DB::table('tbl_data_outlet')->where('nama_outlet', $outletName)->value('id_outlet');
            $divisionId = \DB::table('tbl_data_divisi')->where('nama_divisi', $divisionName)->value('id');
            if (!$outletId || !$divisionId) {
                $failed++;
                $failMsg[] = "Outlet/Divisi tidak ditemukan untuk NIK $nik (Outlet: $outletName, Divisi: $divisionName).";
                continue;
            }
            try {
                \DB::table('payroll_master')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'outlet_id' => $outletId,
                        'division_id' => $divisionId,
                    ],
                    [
                        'gaji' => $row['F'] ?? 0,
                        'tunjangan' => $row['G'] ?? 0,
                        'ot' => !empty($row['H']) ? 1 : 0,
                        'um' => !empty($row['I']) ? 1 : 0,
                        'ph' => !empty($row['J']) ? 1 : 0,
                        'sc' => !empty($row['K']) ? 1 : 0,
                        'bpjs_jkn' => !empty($row['L']) ? 1 : 0,
                        'bpjs_tk' => !empty($row['M']) ? 1 : 0,
                        'lb' => !empty($row['N']) ? 1 : 0,
                        'updated_at' => now(),
                    ]
                );
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $failMsg[] = "NIK $nik gagal: " . $e->getMessage();
            }
        }
        $msg = "Import selesai. Berhasil: $success, Gagal: $failed.";
        if ($failed) $msg .= "\n" . implode("\n", $failMsg);
        return response()->json(['success' => $failed == 0, 'message' => $msg]);
    }
} 