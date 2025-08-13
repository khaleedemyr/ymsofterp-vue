<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;
use Carbon\Carbon;

class PayrollReportController extends Controller
{
    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        // Dropdown Outlet
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        // Dropdown Bulan
        $months = [
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

        // Dropdown Tahun
        $years = [];
        $currentYear = date('Y');
        for ($i = $currentYear - 2; $i <= $currentYear + 1; $i++) {
            $years[] = ['id' => $i, 'name' => $i];
        }

        $payrollData = collect();

        if ($outletId && $month && $year) {
            // Hitung periode payroll (26 bulan sebelumnya - 25 bulan yang dipilih)
            $startDate = Carbon::create($year, $month, 26)->subMonth();
            $endDate = Carbon::create($year, $month, 25);

            // Ambil data karyawan di outlet tersebut
            $users = User::where('status', 'A')
                ->where('id_outlet', $outletId)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id']);

            // Ambil data jabatan
            $jabatans = DB::table('tbl_data_jabatan')->pluck('nama_jabatan', 'id_jabatan');
            $divisions = DB::table('tbl_data_divisi')->pluck('nama_divisi', 'id');

            // Ambil data master payroll
            $payrollMaster = DB::table('payroll_master')
                ->where('outlet_id', $outletId)
                ->get()
                ->keyBy('user_id');

            foreach ($users as $user) {
                // Ambil data master payroll untuk user ini
                $masterData = $payrollMaster->get($user->id, (object)[
                    'gaji' => 0,
                    'tunjangan' => 0,
                    'ot' => 0,
                    'um' => 0,
                    'ph' => 0,
                    'sc' => 0,
                    'bpjs_jkn' => 0,
                    'bpjs_tk' => 0,
                    'lb' => 0,
                ]);

                // Ambil data attendance untuk periode tersebut
                $attendanceData = $this->getAttendanceData($user->id, $outletId, $startDate, $endDate);

                // Hitung total telat dan lembur
                $totalTelat = $attendanceData->sum('telat');
                $totalLembur = $attendanceData->sum('lembur');

                // Hitung hari kerja yang sebenarnya dari periode yang dipilih
                $hariKerja = $this->getHariKerja($user->id, $outletId, $startDate, $endDate);
                
                // Hitung gaji lembur (1.5x gaji pokok per jam)
                $gajiLembur = 0;
                if ($totalLembur > 0 && $masterData->gaji > 0 && $hariKerja > 0) {
                    // Hitung gaji per jam berdasarkan hari kerja yang sebenarnya
                    $gajiPerJam = $masterData->gaji / (8 * $hariKerja);
                    $gajiLembur = $totalLembur * $gajiPerJam * 1.5;
                }

                // Hitung potongan telat (prorate dari gaji + tunjangan)
                $potonganTelat = 0;
                $gajiPerMenit = 0;
                if ($totalTelat > 0 && ($masterData->gaji > 0 || $masterData->tunjangan > 0) && $hariKerja > 0) {
                    // Gaji per menit = (Gaji Pokok + Tunjangan) ÷ (hari kerja × 8 jam × 60 menit)
                    $gajiPerMenit = ($masterData->gaji + $masterData->tunjangan) / ($hariKerja * 8 * 60);
                    $potonganTelat = $totalTelat * $gajiPerMenit;
                }

                // Hitung total gaji
                $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur - $potonganTelat;

                $payrollData->push([
                    'user_id' => $user->id,
                    'nik' => $user->nik,
                    'nama_lengkap' => $user->nama_lengkap,
                    'jabatan' => $jabatans[$user->id_jabatan] ?? '-',
                    'divisi' => $divisions[$user->division_id] ?? '-',
                    'gaji_pokok' => $masterData->gaji,
                    'tunjangan' => $masterData->tunjangan,
                    'total_telat' => $totalTelat,
                    'total_lembur' => $totalLembur,
                    'gaji_lembur' => round($gajiLembur),
                    'hari_kerja' => $hariKerja,
                    'gaji_per_menit' => round($gajiPerMenit, 2),
                    'potongan_telat' => round($potonganTelat),
                    'total_gaji' => round($totalGaji),
                    'periode' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                    'master_data' => $masterData,
                ]);
            }
        }

        return Inertia::render('Payroll/Report', [
            'outlets' => $outlets,
            'months' => $months,
            'years' => $years,
            'payrollData' => $payrollData,
            'filter' => [
                'outlet_id' => $outletId,
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    private function getAttendanceData($userId, $outletId, $startDate, $endDate)
    {
        // Ambil data scan attendance
        $scans = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->select(
                'a.scan_date',
                'a.inoutmode',
                'u.id as user_id',
                'u.nama_lengkap',
                'o.id_outlet',
                'o.nama_outlet'
            )
            ->where('u.id', $userId)
            ->where('o.id_outlet', $outletId)
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('a.scan_date')
            ->get();

        // Proses data scan seperti di AttendanceReportController
        $processedData = [];
        foreach ($scans as $scan) {
            $date = date('Y-m-d', strtotime($scan->scan_date));
            $key = $scan->user_id . '_' . $scan->id_outlet . '_' . $date;
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'user_id' => $scan->user_id,
                    'nama_lengkap' => $scan->nama_lengkap ?? '',
                    'id_outlet' => $scan->id_outlet,
                    'nama_outlet' => $scan->nama_outlet ?? '',
                    'tanggal' => $date,
                    'scans' => []
                ];
            }
            $processedData[$key]['scans'][] = [
                'scan_date' => $scan->scan_date,
                'inoutmode' => $scan->inoutmode,
            ];
        }

        // Tentukan jam masuk/jam keluar
        $finalData = [];
        foreach ($processedData as $key => $data) {
            $scans = collect($data['scans'])->sortBy('scan_date');
            $inScans = $scans->where('inoutmode', 1);
            $outScans = $scans->where('inoutmode', 2);
            
            $jamMasuk = $inScans->first()['scan_date'] ?? null;
            $jamKeluar = null;
            $isCrossDay = false;
            
            if ($jamMasuk) {
                $sameDayOut = $outScans->where('scan_date', '>', $jamMasuk)->first();
                if ($sameDayOut) {
                    $jamKeluar = $sameDayOut['scan_date'];
                    $isCrossDay = false;
                } else {
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $nextDayKey = $data['user_id'] . '_' . $data['id_outlet'] . '_' . $nextDay;
                    if (isset($processedData[$nextDayKey])) {
                        $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                        $nextDayOut = $nextDayScans->where('inoutmode', 2)->first();
                        if ($nextDayOut) {
                            $jamKeluar = $nextDayOut['scan_date'];
                            $isCrossDay = true;
                        }
                    }
                }
            }
            
            $finalData[] = [
                'tanggal' => $data['tanggal'],
                'user_id' => $data['user_id'],
                'nama_lengkap' => $data['nama_lengkap'],
                'id_outlet' => $data['id_outlet'],
                'nama_outlet' => $data['nama_outlet'],
                'jam_masuk' => $jamMasuk,
                'jam_keluar' => $jamKeluar,
                'is_cross_day' => $isCrossDay
            ];
        }

        $dataRows = collect($finalData);

        // Ambil data shift untuk perhitungan lembur
        $shiftData = DB::table('shifts')->first();

        // Hitung lembur untuk setiap baris
        foreach ($dataRows as $row) {
            if ($row['jam_masuk'] && $row['jam_keluar'] && $shiftData) {
                $shiftStart = $shiftData->time_start ?? '08:00:00';
                $shiftEnd = $shiftData->time_end ?? '17:00:00';
                
                $shiftEndDateTime = date('Y-m-d', strtotime($row['jam_masuk'])) . ' ' . $shiftEnd;
                $scanOutDateTime = $row['jam_keluar'];
                
                if ($row['is_cross_day']) {
                    $shiftEndDateTime = date('Y-m-d', strtotime($row['jam_masuk'] . ' +1 day')) . ' ' . $shiftEnd;
                }
                
                $shiftEndTime = strtotime($shiftEndDateTime);
                $scanOutTime = strtotime($scanOutDateTime);
                
                if ($scanOutTime > $shiftEndTime) {
                    $lemburHours = round(($scanOutTime - $shiftEndTime) / 3600, 2);
                    $row['lembur'] = $lemburHours;
                } else {
                    $row['lembur'] = 0;
                }
            } else {
                $row['lembur'] = 0;
            }
        }

        // Hitung telat dan lembur untuk setiap tanggal
        $rows = collect();
        $period = [];
        $dt = new \DateTime($startDate->format('Y-m-d'));
        $dtEnd = new \DateTime($endDate->format('Y-m-d'));
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }

        foreach ($period as $tanggal) {
            $dayData = $dataRows->where('tanggal', $tanggal);
            
            if ($dayData->count() > 0) {
                foreach ($dayData as $row) {
                    $jam_masuk = $row['jam_masuk'] ? date('H:i:s', strtotime($row['jam_masuk'])) : null;
                    $jam_keluar = $row['jam_keluar'] ? date('H:i:s', strtotime($row['jam_keluar'])) : null;
                    $telat = 0;
                    $lembur = 0;
                    $is_off = false;
                    
                    $shift = DB::table('user_shifts as us')
                        ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                        ->where('us.user_id', $row['user_id'])
                        ->where('us.tanggal', $tanggal)
                        ->where('us.outlet_id', $row['id_outlet'])
                        ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                        ->first();
                    
                    if ($shift) {
                        if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) {
                            $is_off = true;
                        }
                    }
                    
                    if (!$is_off) {
                        if ($shift && $shift->time_start && $jam_masuk) {
                            $start = strtotime($shift->time_start);
                            $masuk = strtotime($jam_masuk);
                            $diff = $masuk - $start;
                            $telat = $diff > 0 ? round($diff/60) : 0;
                        }
                        if ($shift && $shift->time_end && $jam_keluar) {
                            $shiftEndDateTime = date('Y-m-d', strtotime($tanggal)) . ' ' . $shift->time_end;
                            $scanOutDateTime = $row['jam_keluar'];
                            $end = strtotime($shiftEndDateTime);
                            $keluar = strtotime($scanOutDateTime);
                            $diff = $keluar - $end;
                            $lembur = $diff > 0 ? floor($diff/3600) : 0;
                        }
                    } else {
                        $jam_masuk = null;
                        $jam_keluar = null;
                        $telat = 0;
                        $lembur = 0;
                    }
                    
                    $rows->push([
                        'tanggal' => $tanggal,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'is_off' => $is_off,
                    ]);
                }
            }
        }

        return $rows;
    }

    private function getHariKerja($userId, $outletId, $startDate, $endDate)
    {
        // Ambil data shift karyawan untuk periode yang dipilih
        $shifts = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->where('us.outlet_id', $outletId)
            ->whereBetween('us.tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->select('us.tanggal', 's.shift_name', 'us.shift_id')
            ->get();

        $hariKerja = 0;
        foreach ($shifts as $shift) {
            // Hitung sebagai hari kerja jika:
            // 1. Ada shift_id (tidak null)
            // 2. Shift name bukan 'off' atau kosong
            if ($shift->shift_id && 
                $shift->shift_name && 
                strtolower(trim($shift->shift_name)) !== 'off' && 
                strtolower(trim($shift->shift_name)) !== '') {
                $hariKerja++;
            }
        }

        return $hariKerja;
    }

    public function export(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $month = $request->input('month');
        $year = $request->input('year');

        if (!$outletId || !$month || !$year) {
            return response()->json(['error' => 'Parameter tidak lengkap'], 400);
        }

        // Hitung periode payroll
        $startDate = Carbon::create($year, $month, 26)->subMonth();
        $endDate = Carbon::create($year, $month, 25);

        // Ambil data seperti di index()
        $users = User::where('status', 'A')
            ->where('id_outlet', $outletId)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id']);

        $jabatans = DB::table('tbl_data_jabatan')->pluck('nama_jabatan', 'id_jabatan');
        $divisions = DB::table('tbl_data_divisi')->pluck('nama_divisi', 'id');
        $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');

        $payrollMaster = DB::table('payroll_master')
            ->where('outlet_id', $outletId)
            ->get()
            ->keyBy('user_id');

        $exportData = [];
        foreach ($users as $user) {
            $masterData = $payrollMaster->get($user->id, (object)[
                'gaji' => 0,
                'tunjangan' => 0,
                'ot' => 0,
                'um' => 0,
                'ph' => 0,
                'sc' => 0,
                'bpjs_jkn' => 0,
                'bpjs_tk' => 0,
                'lb' => 0,
            ]);

            $attendanceData = $this->getAttendanceData($user->id, $outletId, $startDate, $endDate);
            $totalTelat = $attendanceData->sum('telat');
            $totalLembur = $attendanceData->sum('lembur');

            // Hitung hari kerja yang sebenarnya dari periode yang dipilih
            $hariKerja = $this->getHariKerja($user->id, $outletId, $startDate, $endDate);

            $gajiLembur = 0;
            if ($totalLembur > 0 && $masterData->gaji > 0 && $hariKerja > 0) {
                $gajiPerJam = $masterData->gaji / (8 * $hariKerja);
                $gajiLembur = $totalLembur * $gajiPerJam * 1.5;
            }

            // Hitung potongan telat (prorate dari gaji + tunjangan)
            $potonganTelat = 0;
            $gajiPerMenit = 0;
            if ($totalTelat > 0 && ($masterData->gaji > 0 || $masterData->tunjangan > 0) && $hariKerja > 0) {
                // Gaji per menit = (Gaji Pokok + Tunjangan) ÷ (hari kerja × 8 jam × 60 menit)
                $gajiPerMenit = ($masterData->gaji + $masterData->tunjangan) / ($hariKerja * 8 * 60);
                $potonganTelat = $totalTelat * $gajiPerMenit;
            }
            $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur - $potonganTelat;

            $exportData[] = [
                'NIK' => $user->nik,
                'Nama Karyawan' => $user->nama_lengkap,
                'Jabatan' => $jabatans[$user->id_jabatan] ?? '-',
                'Divisi' => $divisions[$user->division_id] ?? '-',
                'Gaji Pokok' => $masterData->gaji,
                'Tunjangan' => $masterData->tunjangan,
                'Total Menit Telat' => $totalTelat,
                'Total Jam Lembur' => $totalLembur,
                'Gaji Lembur' => round($gajiLembur),
                'Hari Kerja' => $hariKerja,
                'Gaji per Menit' => round($gajiPerMenit, 2),
                'Potongan Telat' => round($potonganTelat),
                'Total Gaji' => round($totalGaji),
                'Periode' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            ];
        }

        // Generate Excel file
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set title
        $sheet->setCellValue('A1', 'LAPORAN PAYROLL');
        $sheet->setCellValue('A2', 'Outlet: ' . $outletName);
        $sheet->setCellValue('A3', 'Periode: ' . $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'));

        // Set headers
        $headers = array_keys($exportData[0] ?? []);
        $sheet->fromArray($headers, null, 'A5');

        // Set data
        $sheet->fromArray($exportData, null, 'A6');

        // Auto size columns
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Download response
        $filename = 'laporan_payroll_' . $outletName . '_' . $month . '_' . $year . '_' . date('Ymd_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $excelOutput = ob_get_clean();
        
        return response($excelOutput)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
