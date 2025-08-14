<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;
use App\Models\CustomPayrollItem;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

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
                ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet']);

            // Ambil data jabatan dan divisi
            $jabatans = DB::table('tbl_data_jabatan')->pluck('nama_jabatan', 'id_jabatan');
            $divisions = DB::table('tbl_data_divisi')->pluck('nama_divisi', 'id');
            
            // Ambil data level dari jabatan
            $jabatanLevels = DB::table('tbl_data_jabatan')->pluck('id_level', 'id_jabatan');
            
            // Ambil data nominal lembur dan uang makan dari divisi
            $divisiNominalLembur = DB::table('tbl_data_divisi')
                ->pluck('nominal_lembur', 'id');
            $divisiNominalUangMakan = DB::table('tbl_data_divisi')
                ->pluck('nominal_uang_makan', 'id');
            
            // Ambil data nilai dasar potongan BPJS dari level
            $levelNominalDasarBPJS = DB::table('tbl_data_level')
                ->pluck('nilai_dasar_potongan_bpjs', 'id');

            // Ambil data master payroll
            $payrollMaster = DB::table('payroll_master')
                ->where('outlet_id', $outletId)
                ->get()
                ->keyBy('user_id');

            // Ambil data custom payroll items untuk periode ini
            $customItems = CustomPayrollItem::forOutlet($outletId)
                ->forPeriod($month, $year)
                ->get()
                ->groupBy('user_id');

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
                
                // Hitung gaji lembur menggunakan nominal_lembur dari divisi
                $gajiLembur = 0;
                if ($totalLembur > 0 && $masterData->ot == 1) {
                    // Ambil nominal lembur dari divisi karyawan
                    $nominalLembur = $divisiNominalLembur[$user->division_id] ?? 0;
                    $gajiLembur = $totalLembur * $nominalLembur;
                    
                    // Debug logging untuk perhitungan lembur
                    \Log::info('Overtime calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'division_id' => $user->division_id,
                        'total_lembur' => $totalLembur,
                        'ot_enabled' => $masterData->ot,
                        'nominal_lembur' => $nominalLembur,
                        'gaji_lembur' => $gajiLembur
                    ]);
                } else if ($totalLembur > 0 && $masterData->ot == 0) {
                    // Debug logging untuk karyawan yang tidak mendapatkan lembur
                    \Log::info('Overtime disabled for user', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'total_lembur' => $totalLembur,
                        'ot_enabled' => $masterData->ot,
                        'gaji_lembur' => 0
                    ]);
                }

                // Hitung uang makan berdasarkan hari kerja (menggunakan hari kerja yang sama dengan gaji per menit)
                $uangMakan = 0;
                if ($masterData->um == 1) {
                    // Ambil nominal uang makan dari divisi karyawan
                    $nominalUangMakan = $divisiNominalUangMakan[$user->division_id] ?? 0;
                    $uangMakan = $hariKerja * $nominalUangMakan;
                    
                    // Debug logging untuk perhitungan uang makan
                    \Log::info('Meal allowance calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'division_id' => $user->division_id,
                        'hari_kerja' => $hariKerja,
                        'um_enabled' => $masterData->um,
                        'nominal_uang_makan' => $nominalUangMakan,
                        'uang_makan' => $uangMakan,
                        'calculation_formula' => "{$hariKerja} hari × {$nominalUangMakan} = {$uangMakan}"
                    ]);
                } else {
                    // Debug logging untuk karyawan yang tidak mendapatkan uang makan
                    \Log::info('Meal allowance disabled for user', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'hari_kerja' => $hariKerja,
                        'um_enabled' => $masterData->um,
                        'uang_makan' => 0
                    ]);
                }

                // Hitung BPJS JKN dan BPJS TK berdasarkan level dan outlet
                $bpjsJKN = 0;
                $bpjsTK = 0;
                if ($masterData->bpjs_jkn == 1 || $masterData->bpjs_tk == 1) {
                    // Ambil id_level dari jabatan karyawan
                    $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
                    
                    // Ambil nilai dasar potongan BPJS dari level karyawan
                    $nilaiDasarBPJS = $userLevel ? ($levelNominalDasarBPJS[$userLevel] ?? 0) : 0;
                    
                    if ($masterData->bpjs_jkn == 1) {
                        $bpjsJKN = $nilaiDasarBPJS * 0.01; // 1% dari nilai dasar
                    }
                    
                    if ($masterData->bpjs_tk == 1) {
                        if ($user->id_outlet == 1) {
                            $bpjsTK = $nilaiDasarBPJS * 0.03; // 2% + 1% = 3% dari nilai dasar
                        } else {
                            $bpjsTK = $nilaiDasarBPJS * 0.02; // 2% dari nilai dasar
                        }
                    }
                    
                    // Debug logging untuk perhitungan BPJS
                    \Log::info('BPJS calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'id_jabatan' => $user->id_jabatan,
                        'id_level' => $userLevel,
                        'id_outlet' => $user->id_outlet,
                        'nilai_dasar_bpjs' => $nilaiDasarBPJS,
                        'bpjs_jkn_enabled' => $masterData->bpjs_jkn,
                        'bpjs_tk_enabled' => $masterData->bpjs_tk,
                        'bpjs_jkn' => $bpjsJKN,
                        'bpjs_tk' => $bpjsTK
                    ]);
                }

                // Hitung potongan telat (prorate dari gaji + tunjangan)
                $potonganTelat = 0;
                $gajiPerMenit = 0;
                if ($totalTelat > 0 && ($masterData->gaji > 0 || $masterData->tunjangan > 0) && $hariKerja > 0) {
                    // Gaji per menit = (Gaji Pokok + Tunjangan) ÷ (hari kerja × 8 jam × 60 menit)
                    $gajiPerMenit = ($masterData->gaji + $masterData->tunjangan) / ($hariKerja * 8 * 60);
                    $potonganTelat = $totalTelat * $gajiPerMenit;
                    
                    // Debug logging untuk perhitungan gaji per menit
                    \Log::info('Gaji per menit calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'gaji_pokok' => $masterData->gaji,
                        'tunjangan' => $masterData->tunjangan,
                        'hari_kerja' => $hariKerja,
                        'gaji_per_menit' => $gajiPerMenit,
                        'total_telat' => $totalTelat,
                        'potongan_telat' => $potonganTelat,
                        'calculation_formula' => "({$masterData->gaji} + {$masterData->tunjangan}) ÷ ({$hariKerja} × 8 × 60) = {$gajiPerMenit}"
                    ]);
                }

                // Hitung custom earnings dan deductions
                $userCustomItems = $customItems->get($user->id, collect());
                $customEarnings = $userCustomItems->where('item_type', 'earn')->sum('item_amount');
                $customDeductions = $userCustomItems->where('item_type', 'deduction')->sum('item_amount');

                // Hitung total gaji
                $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $customDeductions;

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
                    'nominal_lembur_per_jam' => $divisiNominalLembur[$user->division_id] ?? 0,
                    'gaji_lembur' => round($gajiLembur),
                    'nominal_uang_makan' => $divisiNominalUangMakan[$user->division_id] ?? 0,
                    'uang_makan' => round($uangMakan),
                    'bpjs_jkn' => round($bpjsJKN),
                    'bpjs_tk' => round($bpjsTK),
                    'custom_earnings' => round($customEarnings),
                    'custom_deductions' => round($customDeductions),
                    'custom_items' => $userCustomItems,
                    'gaji_per_menit' => round($gajiPerMenit, 2),
                    'potongan_telat' => round($potonganTelat),
                    'total_gaji' => round($totalGaji),
                    'hari_kerja' => $hariKerja,
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
                            // Create full datetime for shift end
                            $shiftEndDateTime = date('Y-m-d', strtotime($tanggal)) . ' ' . $shift->time_end;
                            
                            // Use scan out that's already in full datetime format
                            $scanOutDateTime = $row['jam_keluar'];
                            
                            // Calculate time difference
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
        $workingDays = [];
        $offDays = [];
        
        foreach ($shifts as $shift) {
            // Hitung sebagai hari kerja jika:
            // 1. Ada shift_id (tidak null)
            // 2. Shift name bukan 'off' atau kosong
            if ($shift->shift_id && 
                $shift->shift_name && 
                strtolower(trim($shift->shift_name)) !== 'off' && 
                strtolower(trim($shift->shift_name)) !== '') {
                $hariKerja++;
                $workingDays[] = $shift->tanggal . ' (' . $shift->shift_name . ')';
            } else {
                $offDays[] = $shift->tanggal . ' (' . ($shift->shift_name ?: 'no shift') . ')';
            }
        }

        // Debug logging untuk perhitungan hari kerja
        \Log::info('Hari kerja calculation', [
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_shifts' => $shifts->count(),
            'hari_kerja' => $hariKerja,
            'working_days' => $workingDays,
            'off_days' => $offDays
        ]);

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
            ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet']);

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
            if ($totalLembur > 0 && $masterData->ot == 1) {
                // Ambil nominal lembur dari divisi karyawan
                $nominalLembur = DB::table('tbl_data_divisi')
                    ->where('id', $user->division_id)
                    ->value('nominal_lembur') ?? 0;
                $gajiLembur = $totalLembur * $nominalLembur;
            }

            // Hitung uang makan berdasarkan hari kerja
            $uangMakan = 0;
            if ($masterData->um == 1) {
                // Ambil nominal uang makan dari divisi karyawan
                $nominalUangMakan = DB::table('tbl_data_divisi')
                    ->where('id', $user->division_id)
                    ->value('nominal_uang_makan') ?? 0;
                $uangMakan = $hariKerja * $nominalUangMakan;
            }

            // Hitung BPJS JKN dan BPJS TK berdasarkan level dan outlet
            $bpjsJKN = 0;
            $bpjsTK = 0;
            if ($masterData->bpjs_jkn == 1 || $masterData->bpjs_tk == 1) {
                // Ambil id_level dari jabatan karyawan
                $userLevel = DB::table('tbl_data_jabatan')
                    ->where('id_jabatan', $user->id_jabatan)
                    ->value('id_level');
                
                // Ambil nilai dasar potongan BPJS dari level karyawan
                $nilaiDasarBPJS = $userLevel ? (DB::table('tbl_data_level')
                    ->where('id', $userLevel)
                    ->value('nilai_dasar_potongan_bpjs') ?? 0) : 0;
                
                if ($masterData->bpjs_jkn == 1) {
                    $bpjsJKN = $nilaiDasarBPJS * 0.01; // 1% dari nilai dasar
                }
                
                if ($masterData->bpjs_tk == 1) {
                    if ($user->id_outlet == 1) {
                        $bpjsTK = $nilaiDasarBPJS * 0.03; // 2% + 1% = 3% dari nilai dasar
                    } else {
                        $bpjsTK = $nilaiDasarBPJS * 0.02; // 2% dari nilai dasar
                    }
                }
            }

            // Hitung potongan telat (prorate dari gaji + tunjangan)
            $potonganTelat = 0;
            $gajiPerMenit = 0;
            if ($totalTelat > 0 && ($masterData->gaji > 0 || $masterData->tunjangan > 0) && $hariKerja > 0) {
                // Gaji per menit = (Gaji Pokok + Tunjangan) ÷ (hari kerja × 8 jam × 60 menit)
                $gajiPerMenit = ($masterData->gaji + $masterData->tunjangan) / ($hariKerja * 8 * 60);
                $potonganTelat = $totalTelat * $gajiPerMenit;
            }
            $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan - $potonganTelat - $bpjsJKN - $bpjsTK;

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
                'Nominal Uang Makan/Hari' => DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nominal_uang_makan') ?? 0,
                'Uang Makan' => round($uangMakan),
                'BPJS JKN' => round($bpjsJKN),
                'BPJS TK' => round($bpjsTK),
                'Gaji per Menit' => round($gajiPerMenit, 2),
                'Potongan Telat' => round($potonganTelat),
                'Total Gaji' => round($totalGaji),
                'Hari Kerja' => $hariKerja,
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

    public function getAttendanceDetail(Request $request)
    {
        $userId = $request->input('user_id');
        $outletId = $request->input('outlet_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$userId || !$outletId || !$startDate || !$endDate) {
            return response()->json(['error' => 'Missing required parameters'], 400);
        }

        // Log all parameters for debugging
        \Log::info('getAttendanceDetail called with:', [
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        // Get raw scan data
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
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
            ->orderBy('a.scan_date')
            ->get();

        // If no scans found, try alternative query
        if ($scans->count() == 0) {
            \Log::info('No scans found with outlet_id, trying with user_pins only');
            
            $scans = DB::table('att_log as a')
                ->join('user_pins as up', 'a.pin', '=', 'up.pin')
                ->join('users as u', 'up.user_id', '=', 'u.id')
                ->join('tbl_data_outlet as o', 'up.outlet_id', '=', 'o.id_outlet')
                ->select(
                    'a.scan_date',
                    'a.inoutmode',
                    'u.id as user_id',
                    'u.nama_lengkap',
                    'o.id_outlet',
                    'o.nama_outlet'
                )
                ->where('u.id', $userId)
                ->where('up.outlet_id', $outletId)
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
                ->orderBy('a.scan_date')
                ->get();

            \Log::info('Alternative query result', [
                'scans_count' => $scans->count(),
                'scans_sample' => $scans->take(3)->toArray()
            ]);
        }

        // Process data like in AttendanceReportController
        $processedData = [];
        foreach ($scans as $scan) {
            $date = date('Y-m-d', strtotime($scan->scan_date));
            $key = $scan->user_id . '_' . $scan->id_outlet . '_' . $date;
            
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'user_id' => $scan->user_id,
                    'nama_lengkap' => $scan->nama_lengkap,
                    'id_outlet' => $scan->id_outlet,
                    'nama_outlet' => $scan->nama_outlet,
                    'scans' => []
                ];
            }
            
            $processedData[$key]['scans'][] = [
                'scan_date' => $scan->scan_date,
                'inoutmode' => $scan->inoutmode
            ];
        }

        // Process each group to determine jam masuk/keluar
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
                'total_masuk' => $inScans->count(),
                'total_keluar' => $outScans->count(),
                'is_cross_day' => $isCrossDay
            ];
        }

        // Get shift data and calculate telat/lembur
        $attendanceDetail = [];
        foreach ($finalData as $data) {
            // Get shift data for this date
            $shiftData = DB::table('user_shifts as us')
                ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                ->where('us.user_id', $data['user_id'])
                ->where('us.outlet_id', $data['id_outlet'])
                ->where('us.tanggal', $data['tanggal'])
                ->select('s.shift_name', 's.time_start', 's.time_end', 'us.shift_id')
                ->first();

            $telat = 0;
            $lembur = 0;
            $shiftName = $shiftData ? $shiftData->shift_name : null;
            $is_off = false;

            // Check if it's off day
            if ($shiftData) {
                if (is_null($shiftData->shift_id) || (strtolower($shiftData->shift_name ?? '') === 'off')) {
                    $is_off = true;
                }
            }

            // Calculate telat and lembur only if not off day and have both jam_masuk and jam_keluar
            if (!$is_off && $data['jam_masuk'] && $data['jam_keluar'] && $shiftData) {
                // Calculate telat (same logic as AttendanceReportController)
                if ($shiftData->time_start && $data['jam_masuk']) {
                    $jam_masuk_time = date('H:i:s', strtotime($data['jam_masuk']));
                    $start = strtotime($shiftData->time_start);
                    $masuk = strtotime($jam_masuk_time);
                    $diff = $masuk - $start;
                    $telat = $diff > 0 ? round($diff/60) : 0;
                }

                // Calculate lembur (same logic as AttendanceReportController)
                if ($shiftData->time_end && $data['jam_keluar']) {
                    // Create full datetime for shift end
                    $shiftEndDateTime = date('Y-m-d', strtotime($data['tanggal'])) . ' ' . $shiftData->time_end;
                    
                    // Use scan out that's already in full datetime format
                    $scanOutDateTime = $data['jam_keluar'];
                    
                    // Calculate time difference
                    $end = strtotime($shiftEndDateTime);
                    $keluar = strtotime($scanOutDateTime);
                    $diff = $keluar - $end;
                    $lembur = $diff > 0 ? floor($diff/3600) : 0;
                }
            } else if ($is_off) {
                // Set to null if it's off day
                $data['jam_masuk'] = null;
                $data['jam_keluar'] = null;
                $telat = 0;
                $lembur = 0;
            }

            // Always add to attendance detail, even if incomplete
            $attendanceDetail[] = [
                'tanggal' => $data['tanggal'],
                'jam_masuk' => $data['jam_masuk'] ? date('H:i:s', strtotime($data['jam_masuk'])) : null,
                'jam_keluar' => $data['jam_keluar'] ? date('H:i:s', strtotime($data['jam_keluar'])) : null,
                'total_masuk' => $data['total_masuk'],
                'total_keluar' => $data['total_keluar'],
                'telat' => $telat,
                'lembur' => $lembur,
                'shift_name' => $shiftName,
                'is_cross_day' => $data['is_cross_day'],
                'is_off' => $is_off
            ];

            // Debug logging for first few records
            if (count($attendanceDetail) <= 3) {
                \Log::info('Attendance detail calculation', [
                    'tanggal' => $data['tanggal'],
                    'jam_masuk' => $data['jam_masuk'] ? date('H:i:s', strtotime($data['jam_masuk'])) : null,
                    'jam_keluar' => $data['jam_keluar'] ? date('H:i:s', strtotime($data['jam_keluar'])) : null,
                    'shift_name' => $shiftName,
                    'shift_time_start' => $shiftData ? $shiftData->time_start : null,
                    'shift_time_end' => $shiftData ? $shiftData->time_end : null,
                    'is_off' => $is_off,
                    'telat' => $telat,
                    'lembur' => $lembur
                ]);
            }
        }

        // Debug logging for final result
        \Log::info('Attendance Detail Result', [
            'user_id' => $userId,
            'attendance_detail_count' => count($attendanceDetail),
            'attendance_detail_sample' => array_slice($attendanceDetail, 0, 3)
        ]);

        // If no data found, log for debugging
        if (count($attendanceDetail) == 0) {
            \Log::info('No attendance data found for user', [
                'user_id' => $userId,
                'outlet_id' => $outletId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        }

        return response()->json($attendanceDetail);
    }

    // Method untuk menambah custom payroll item
    public function addCustomItem(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'outlet_id' => 'required|integer',
            'payroll_period_month' => 'required|integer|between:1,12',
            'payroll_period_year' => 'required|integer|min:2020',
            'item_type' => 'required|in:earn,deduction',
            'item_name' => 'required|string|max:255',
            'item_amount' => 'required|numeric|min:0',
            'item_description' => 'nullable|string'
        ]);

        try {
            $customItem = CustomPayrollItem::create([
                'user_id' => $request->user_id,
                'outlet_id' => $request->outlet_id,
                'payroll_period_month' => $request->payroll_period_month,
                'payroll_period_year' => $request->payroll_period_year,
                'item_type' => $request->item_type,
                'item_name' => $request->item_name,
                'item_amount' => $request->item_amount,
                'item_description' => $request->item_description
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Custom item berhasil ditambahkan',
                'data' => $customItem
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan custom item: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method untuk menghapus custom payroll item
    public function deleteCustomItem(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer'
        ]);

        try {
            $customItem = CustomPayrollItem::findOrFail($request->item_id);
            $customItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Custom item berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus custom item: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method untuk mendapatkan custom items untuk periode tertentu
    public function getCustomItems(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer',
            'payroll_period_month' => 'required|integer|between:1,12',
            'payroll_period_year' => 'required|integer|min:2020'
        ]);

        try {
            $customItems = CustomPayrollItem::forOutlet($request->outlet_id)
                ->forPeriod($request->payroll_period_month, $request->payroll_period_year)
                ->with('user:id,nama_lengkap,nik')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $customItems
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil custom items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function showPayroll(Request $request)
    {
        // Same logic as printPayroll but return view instead of PDF
        $userId = $request->input('user_id');
        $outletId = $request->input('outlet_id');
        $month = $request->input('month');
        $year = $request->input('year');

        // Get user data
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Get outlet data
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
        if (!$outlet) {
            return response()->json(['error' => 'Outlet not found'], 404);
        }

        // Get master data
        $masterData = DB::table('payroll_master')
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$masterData) {
            return response()->json(['error' => 'Payroll data not found'], 404);
        }

        // Get position, division data
        $jabatan = DB::table('tbl_data_jabatan')->where('id_jabatan', $user->id_jabatan)->value('nama_jabatan');
        $divisi = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nama_divisi');

        // Calculate overtime
        $totalLembur = DB::table('attendance_logs')
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->sum('overtime_hours');

        $gajiLembur = $totalLembur * $masterData->ot_rate;
        $nominalLemburPerJam = $masterData->ot_rate;

        // Calculate meal allowance
        $hariKerja = DB::table('attendance_logs')
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('status', 'present')
            ->count();

        $uangMakan = $hariKerja * $masterData->um_rate;
        $nominalUangMakan = $masterData->um_rate;

        // Calculate late deductions
        $totalTelat = DB::table('attendance_logs')
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->sum('late_minutes');

        $gajiPerMenit = ($masterData->gaji + $masterData->tunjangan) / (22 * 8 * 60); // 22 days, 8 hours, 60 minutes
        $potonganTelat = $totalTelat * $gajiPerMenit;

        // Calculate BPJS
        $bpjsJKN = $masterData->bpjs_jkn_rate;
        $bpjsTK = $masterData->bpjs_tk_rate;

        // Get custom items
        $customItems = DB::table('payroll_custom_items')
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $customEarnings = $customItems->where('item_type', 'earn')->sum('item_amount');
        $customDeductions = $customItems->where('item_type', 'deduction')->sum('item_amount');

        // Debug logging untuk custom items
        \Log::info('Payroll Show Debug - Custom Items', [
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'month' => $month,
            'year' => $year,
            'custom_items_count' => $customItems->count(),
            'custom_earnings_count' => $customItems->where('item_type', 'earn')->count(),
            'custom_deductions_count' => $customItems->where('item_type', 'deduction')->count(),
            'custom_earnings_total' => $customEarnings,
            'custom_deductions_total' => $customDeductions,
            'custom_items_details' => $customItems->toArray()
        ]);

        // Calculate total salary
        $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $customDeductions;

        // Prepare logo data with better error handling
        $imagePath = public_path('images/logojustusgroup.png');
        $logoBase64 = '';
        
        try {
            if (file_exists($imagePath) && is_readable($imagePath)) {
                $imageContent = file_get_contents($imagePath);
                if ($imageContent !== false) {
                    $logoBase64 = base64_encode($imageContent);
                }
            }
        } catch (Exception $e) {
            \Log::error('Error reading logo file: ' . $e->getMessage());
        }
        
        \Log::info('Debug Logo Path', [
            'path' => $imagePath,
            'exists' => file_exists($imagePath),
            'readable' => is_readable($imagePath),
            'file_size' => file_exists($imagePath) ? filesize($imagePath) : 'N/A',
            'base64_length' => strlen($logoBase64),
            'base64_empty' => empty($logoBase64)
        ]);

        // Format period
        $periode = date('d/m/Y', strtotime("$year-$month-01")) . ' - ' . date('d/m/Y', strtotime("$year-$month-" . date('t', strtotime("$year-$month-01"))));

        // Check if download PDF is requested
        if ($request->has('download') && $request->download === 'pdf') {
            $pdf = \PDF::loadView('payroll.slip', [
                'user' => $user,
                'outlet' => $outlet,
                'jabatan' => $jabatan,
                'divisi' => $divisi,
                'periode' => $periode,
                'gaji_pokok' => $masterData->gaji,
                'tunjangan' => $masterData->tunjangan,
                'total_lembur' => $totalLembur,
                'gaji_lembur' => $gajiLembur,
                'nominal_lembur_per_jam' => $nominalLemburPerJam,
                'uang_makan' => $uangMakan,
                'nominal_uang_makan' => $nominalUangMakan,
                'total_telat' => $totalTelat,
                'bpjs_jkn' => $bpjsJKN,
                'bpjs_tk' => $bpjsTK,
                'custom_earnings' => $customEarnings,
                'custom_deductions' => $customDeductions,
                'custom_items' => $customItems,
                'gaji_per_menit' => round($gajiPerMenit, 2),
                'potongan_telat' => round($potonganTelat),
                'total_gaji' => round($totalGaji),
                'hari_kerja' => $hariKerja,
                'master_data' => $masterData,
                'logo_base64' => $logoBase64,
            ]);

            return $pdf->download("slip_gaji_{$user->nama_lengkap}_{$periode}.pdf");
        }

        return view('payroll.slip', [
            'user' => $user,
            'outlet' => $outlet,
            'jabatan' => $jabatan,
            'divisi' => $divisi,
            'periode' => $periode,
            'gaji_pokok' => $masterData->gaji,
            'tunjangan' => $masterData->tunjangan,
            'total_lembur' => $totalLembur,
            'gaji_lembur' => $gajiLembur,
            'nominal_lembur_per_jam' => $nominalLemburPerJam,
            'uang_makan' => $uangMakan,
            'nominal_uang_makan' => $nominalUangMakan,
            'total_telat' => $totalTelat,
            'bpjs_jkn' => $bpjsJKN,
            'bpjs_tk' => $bpjsTK,
            'custom_earnings' => $customEarnings,
            'custom_deductions' => $customDeductions,
            'custom_items' => $customItems,
            'gaji_per_menit' => round($gajiPerMenit, 2),
            'potongan_telat' => round($potonganTelat),
            'total_gaji' => round($totalGaji),
            'hari_kerja' => $hariKerja,
            'master_data' => $masterData,
            'logo_base64' => $logoBase64,
        ]);
    }

    public function printPayroll(Request $request)
    {
        $userId = $request->input('user_id');
        $outletId = $request->input('outlet_id');
        $month = $request->input('month');
        $year = $request->input('year');

        if (!$userId || !$outletId || !$month || !$year) {
            return response()->json(['error' => 'Parameter tidak lengkap'], 400);
        }

        // Hitung periode payroll
        $startDate = Carbon::create($year, $month, 26)->subMonth();
        $endDate = Carbon::create($year, $month, 25);

        // Ambil data karyawan
        $user = User::where('id', $userId)
            ->where('id_outlet', $outletId)
            ->where('status', 'A')
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Karyawan tidak ditemukan'], 404);
        }

        // Ambil data jabatan dan divisi
        $jabatan = DB::table('tbl_data_jabatan')->where('id_jabatan', $user->id_jabatan)->value('nama_jabatan');
        $divisi = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nama_divisi');
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');

        // Ambil data master payroll
        $masterData = DB::table('payroll_master')
            ->where('user_id', $userId)
            ->where('outlet_id', $outletId)
            ->first();

        if (!$masterData) {
            $masterData = (object)[
                'gaji' => 0,
                'tunjangan' => 0,
                'ot' => 0,
                'um' => 0,
                'ph' => 0,
                'sc' => 0,
                'bpjs_jkn' => 0,
                'bpjs_tk' => 0,
                'lb' => 0,
            ];
        }

        // Ambil data attendance
        $attendanceData = $this->getAttendanceData($userId, $outletId, $startDate, $endDate);
        $totalTelat = $attendanceData->sum('telat');
        $totalLembur = $attendanceData->sum('lembur');

        // Hitung hari kerja
        $hariKerja = $this->getHariKerja($userId, $outletId, $startDate, $endDate);

        // Ambil data nominal dari divisi
        $nominalLembur = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nominal_lembur') ?? 0;
        $nominalUangMakan = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nominal_uang_makan') ?? 0;

        // Hitung gaji lembur
        $gajiLembur = 0;
        if ($totalLembur > 0 && $masterData->ot == 1) {
            $gajiLembur = $totalLembur * $nominalLembur;
        }

        // Hitung uang makan
        $uangMakan = 0;
        if ($masterData->um == 1) {
            $uangMakan = $hariKerja * $nominalUangMakan;
        }

        // Hitung BPJS
        $bpjsJKN = 0;
        $bpjsTK = 0;
        if ($masterData->bpjs_jkn == 1 || $masterData->bpjs_tk == 1) {
            $userLevel = DB::table('tbl_data_jabatan')
                ->where('id_jabatan', $user->id_jabatan)
                ->value('id_level');
            
            $nilaiDasarBPJS = $userLevel ? (DB::table('tbl_data_level')
                ->where('id', $userLevel)
                ->value('nilai_dasar_potongan_bpjs') ?? 0) : 0;
            
            if ($masterData->bpjs_jkn == 1) {
                $bpjsJKN = $nilaiDasarBPJS * 0.01;
            }
            
            if ($masterData->bpjs_tk == 1) {
                if ($user->id_outlet == 1) {
                    $bpjsTK = $nilaiDasarBPJS * 0.03;
                } else {
                    $bpjsTK = $nilaiDasarBPJS * 0.02;
                }
            }
        }

        // Hitung potongan telat
        $potonganTelat = 0;
        $gajiPerMenit = 0;
        if ($totalTelat > 0 && ($masterData->gaji > 0 || $masterData->tunjangan > 0) && $hariKerja > 0) {
            $gajiPerMenit = ($masterData->gaji + $masterData->tunjangan) / ($hariKerja * 8 * 60);
            $potonganTelat = $totalTelat * $gajiPerMenit;
        }

        // Ambil custom items
        $customItems = CustomPayrollItem::forUser($userId)
            ->forOutlet($outletId)
            ->forPeriod($month, $year)
            ->get();
        
        $customEarnings = $customItems->where('item_type', 'earn')->sum('item_amount');
        $customDeductions = $customItems->where('item_type', 'deduction')->sum('item_amount');

        // Debug logging untuk custom items
        \Log::info('Payroll Print Debug - Custom Items', [
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'month' => $month,
            'year' => $year,
            'custom_items_count' => $customItems->count(),
            'custom_earnings_count' => $customItems->where('item_type', 'earn')->count(),
            'custom_deductions_count' => $customItems->where('item_type', 'deduction')->count(),
            'custom_earnings_total' => $customEarnings,
            'custom_deductions_total' => $customDeductions,
            'custom_items_details' => $customItems->toArray()
        ]);

        // Hitung total gaji
        $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $customDeductions;

        // Prepare logo data with better error handling
        $imagePath = public_path('images/logojustusgroup.png');
        $logoBase64 = '';
        
        try {
            if (file_exists($imagePath) && is_readable($imagePath)) {
                $imageContent = file_get_contents($imagePath);
                if ($imageContent !== false) {
                    $logoBase64 = base64_encode($imageContent);
                }
            }
        } catch (Exception $e) {
            \Log::error('Error reading logo file: ' . $e->getMessage());
        }
        
        \Log::info('Debug Logo Path', [
            'path' => $imagePath,
            'exists' => file_exists($imagePath),
            'readable' => is_readable($imagePath),
            'file_size' => file_exists($imagePath) ? filesize($imagePath) : 'N/A',
            'base64_length' => strlen($logoBase64),
            'base64_empty' => empty($logoBase64)
        ]);

        // Generate PDF
        $pdf = \PDF::loadView('payroll.slip', [
            'user' => $user,
            'jabatan' => $jabatan,
            'divisi' => $divisi,
            'outlet' => $outlet,
            'periode' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'gaji_pokok' => $masterData->gaji,
            'tunjangan' => $masterData->tunjangan,
            'total_telat' => $totalTelat,
            'total_lembur' => $totalLembur,
            'nominal_lembur_per_jam' => $nominalLembur,
            'gaji_lembur' => round($gajiLembur),
            'nominal_uang_makan' => $nominalUangMakan,
            'uang_makan' => round($uangMakan),
            'bpjs_jkn' => round($bpjsJKN),
            'bpjs_tk' => round($bpjsTK),
            'custom_earnings' => round($customEarnings),
            'custom_deductions' => round($customDeductions),
            'custom_items' => $customItems,
            'gaji_per_menit' => round($gajiPerMenit, 2),
            'potongan_telat' => round($potonganTelat),
            'total_gaji' => round($totalGaji),
            'hari_kerja' => $hariKerja,
            'master_data' => $masterData,
            'logo_base64' => $logoBase64,
        ]);

        $filename = "Slip_Gaji_{$user->nama_lengkap}_{$month}_{$year}.pdf";
        
        return $pdf->download($filename);
    }
}
