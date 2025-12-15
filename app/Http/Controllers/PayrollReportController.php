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
        $serviceCharge = $request->input('service_charge', 0); // Nilai service charge per karyawan

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
        $leaveTypes = collect(); // Initialize leaveTypes

        if ($outletId && $month && $year) {
            // Hitung periode payroll (26 bulan sebelumnya - 25 bulan yang dipilih)
            // SAMA PERSIS dengan Employee Summary
            // Contoh: bulan 12 tahun 2025 = 26 Nov 2025 - 25 Des 2025
            $start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
            $end = date('Y-m-d', strtotime("$year-$month-25"));
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);

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
            
            // Ambil data point dari level
            $levelPoints = DB::table('tbl_data_level')
                ->pluck('nilai_point', 'id');
            
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

            // Ambil semua leave types untuk breakdown izin/cuti
            $leaveTypes = DB::table('leave_types')
                ->orderBy('name')
                ->get(['id', 'name']);

            // ========== GUNAKAN QUERY DAN PROSES YANG SAMA PERSIS DENGAN EMPLOYEE SUMMARY ==========
            // Query data absensi - SAMA PERSIS dengan Employee Summary
            $chunkSize = 5000;
            $rawData = collect();
            
            $sub = DB::table('att_log as a')
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
                    'u.division_id'
                )
                ->where('u.id_outlet', $outletId)
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);

            // Gunakan chunk untuk mencegah memory overflow
            $sub->orderBy('a.scan_date')->chunk($chunkSize, function($chunk) use (&$rawData) {
                $rawData = $rawData->merge($chunk);
            });

            // Proses data manual untuk menangani cross-day - SAMA PERSIS dengan Employee Summary
            $processedData = [];
            foreach ($rawData as $scan) {
                $date = date('Y-m-d', strtotime($scan->scan_date));
                $key = $scan->user_id . '_' . $date;
                
                if (!isset($processedData[$key])) {
                    $processedData[$key] = [
                        'tanggal' => $date,
                        'user_id' => $scan->user_id,
                        'nama_lengkap' => $scan->nama_lengkap,
                        'division_id' => $scan->division_id,
                        'scans' => []
                    ];
                }
                
                $processedData[$key]['scans'][] = [
                    'scan_date' => $scan->scan_date,
                    'inoutmode' => $scan->inoutmode
                ];
            }

            // Step 2: Proses setiap kelompok dengan smart cross-day processing - SAMA PERSIS dengan Employee Summary
            $finalData = [];
            foreach ($processedData as $key => $data) {
                // Gunakan smart cross-day processing yang sama dengan Employee Summary
                $result = $this->processSmartCrossDayAttendance($data, $processedData);
                $result['division_id'] = $data['division_id'];
                $finalData[] = $result;
            }

            $dataRows = collect($finalData)->map(function($item) {
                return (object) $item;
            });

            // Ambil data shift untuk perhitungan lembur - SAMA PERSIS dengan Employee Summary
            $allShiftData = DB::table('user_shifts as us')
                ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                ->whereIn('us.tanggal', $dataRows->pluck('tanggal')->unique()->values())
                ->select('us.user_id', 'us.tanggal', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                ->get()
                ->groupBy(function($item) {
                    return $item->user_id . '_' . $item->tanggal;
                });

            // Hitung lembur untuk setiap baris - SAMA PERSIS dengan Employee Summary
            foreach ($dataRows as $row) {
                $shiftKey = $row->user_id . '_' . $row->tanggal;
                $shiftData = $allShiftData->get($shiftKey, collect())->first();
                
                if ($row->jam_masuk && $row->jam_keluar && $shiftData) {
                    // Gunakan smart overtime calculation - SAMA PERSIS dengan Employee Summary
                    $row->lembur = $this->calculateSimpleOvertime($row->jam_keluar, $shiftData->time_end);
                } else {
                    $row->lembur = 0;
                }
            }

            // Ambil semua tanggal libur dalam periode
            $holidays = DB::table('tbl_kalender_perusahaan')
                ->whereBetween('tgl_libur', [$start, $end])
                ->pluck('keterangan', 'tgl_libur');

            // Build rows for each tanggal in period - SAMA PERSIS dengan Employee Summary
            $rows = collect();
            foreach ($dataRows as $row) {
                $jam_masuk = $row->jam_masuk ? date('H:i:s', strtotime($row->jam_masuk)) : null;
                $jam_keluar = $row->jam_keluar ? date('H:i:s', strtotime($row->jam_keluar)) : null;
                $telat = 0;
                $lembur = $row->lembur ?? 0;
                
                $shiftKey = $row->user_id . '_' . $row->tanggal;
                $shift = $allShiftData->get($shiftKey, collect())->first();

                if ($shift) {
                    // Hitung telat dan lembur berdasarkan shift - SAMA PERSIS dengan Employee Summary
                    if ($shift->time_start && $jam_masuk) {
                        $telat = $this->calculateLateness($jam_masuk, $shift->time_start, $row->is_cross_day ?? false);
                    }
                    
                    // Tambahkan telat jika checkout lebih awal dari shift end - SAMA PERSIS dengan Employee Summary
                    if ($shift->time_end && $jam_keluar) {
                        $earlyCheckoutTelat = $this->calculateEarlyCheckoutLateness($jam_keluar, $shift->time_end, $row->is_cross_day ?? false);
                        $telat += $earlyCheckoutTelat;
                        
                        // Gunakan perhitungan lembur yang konsisten
                        $lembur = $this->calculateSimpleOvertime($jam_keluar, $shift->time_end);
                    }
                }

                $rows->push((object)[
                    'tanggal' => $row->tanggal,
                    'user_id' => $row->user_id,
                    'nama_lengkap' => $row->nama_lengkap,
                    'division_id' => $row->division_id,
                    'jam_masuk' => $row->jam_masuk,
                    'jam_keluar' => $row->jam_keluar,
                    'total_masuk' => $row->total_masuk,
                    'total_keluar' => $row->total_keluar,
                    'telat' => $telat,
                    'lembur' => $lembur,
                    'is_cross_day' => $row->is_cross_day,
                    'shift_start' => $shift->time_start ?? null,
                    'shift_end' => $shift->time_end ?? null,
                ]);
            }

            // Get all user data (NIK and jabatan) at once - SAMA PERSIS dengan Employee Summary
            $userIds = $rows->pluck('user_id')->unique()->toArray();
            $allUserData = DB::table('users as u')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->select('u.id', 'u.nik', 'j.nama_jabatan as jabatan')
                ->whereIn('u.id', $userIds)
                ->get()
                ->keyBy('id');

            // Group by employee - SAMA PERSIS dengan Employee Summary
            $employeeGroups = $rows->groupBy('user_id');

            // Step 1: Hitung semua data dasar untuk semua user - GUNAKAN DATA DARI EMPLOYEE GROUPS
            $userData = [];
            foreach ($employeeGroups as $userId => $employeeRows) {
                $firstRow = $employeeRows->first();
                $user = $users->firstWhere('id', $userId);
                
                if (!$user) {
                    continue; // Skip jika user tidak ada di list users
                }

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

                // Hitung total telat dan lembur dari employeeRows - SAMA PERSIS dengan Employee Summary
                $totalTelat = $employeeRows->sum('telat');
                
                // Calculate Extra Off overtime total - SAMA PERSIS dengan Employee Summary
                $extraOffOvertimeTotal = floor($this->getExtraOffOvertimeHours($userId, $start, $end));
                
                // Total lembur = lembur biasa + extra off overtime - SAMA PERSIS dengan Employee Summary
                $totalLemburRegular = floor($employeeRows->sum('lembur'));
                $totalLembur = floor($totalLemburRegular + $extraOffOvertimeTotal);

                // Hitung hari kerja - SAMA PERSIS dengan Employee Summary (jumlah hari yang bekerja)
                $hariKerja = $employeeRows->count();

                // Hitung total alpha menggunakan method yang sama dengan Employee Summary
                // Gunakan null untuk outlet_id seperti di Employee Summary
                $totalAlpha = $this->calculateAlpaDays($userId, null, $start, $end);
                
                // Hitung breakdown izin/cuti per kategori menggunakan calculateLeaveData (sama seperti Employee Summary)
                $leaveData = $this->calculateLeaveData($userId, $start, $end);
                
                // Extract breakdown dari leaveData - sama seperti Employee Summary
                // Langsung ambil semua key yang berakhiran '_days' kecuali 'extra_off_days'
                $izinCutiBreakdown = [];
                $totalIzinCuti = 0;
                foreach ($leaveData as $key => $value) {
                    if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                        $izinCutiBreakdown[$key] = $value;
                        $totalIzinCuti += $value;
                    }
                }

                // Ambil point dari level melalui jabatan
                $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
                $userPoint = $userLevel ? ($levelPoints[$userLevel] ?? 0) : 0;

                // Simpan data user untuk perhitungan service charge
                $userData[$user->id] = [
                    'user' => $user,
                    'masterData' => $masterData,
                    'employeeRows' => $employeeRows, // Simpan employeeRows untuk digunakan nanti
                    'totalTelat' => $totalTelat,
                    'totalLembur' => $totalLembur,
                    'hariKerja' => $hariKerja,
                    'totalAlpha' => $totalAlpha,
                    'totalIzinCuti' => $totalIzinCuti,
                    'izinCutiBreakdown' => $izinCutiBreakdown,
                    'userPoint' => $userPoint,
                ];
            }

            // Step 2: Hitung total untuk service charge (hanya untuk user yang sc = 1)
            $totalPointHariKerja = 0; // Sum(point × hari kerja) untuk semua user yang sc = 1
            $totalHariKerja = 0; // Sum(hari kerja) untuk semua user yang sc = 1
            
            foreach ($userData as $data) {
                if ($data['masterData']->sc == 1) {
                    $totalPointHariKerja += $data['userPoint'] * $data['hariKerja'];
                    $totalHariKerja += $data['hariKerja'];
                }
            }

            // Step 3: Hitung rate service charge (50:50)
            $serviceChargeByPoint = 0; // 50% untuk by point
            $serviceChargeProRate = 0; // 50% untuk pro rate
            $rateByPoint = 0; // Rate per (point × hari kerja)
            $rateProRate = 0; // Rate per hari kerja

            if ($serviceCharge > 0) {
                $serviceChargeByPoint = $serviceCharge / 2; // 50%
                $serviceChargeProRate = $serviceCharge / 2; // 50%

                if ($totalPointHariKerja > 0) {
                    $rateByPoint = $serviceChargeByPoint / $totalPointHariKerja;
                }
                if ($totalHariKerja > 0) {
                    $rateProRate = $serviceChargeProRate / $totalHariKerja;
                }
            }

            // Step 4: Hitung service charge per user dan total gaji
            foreach ($userData as $userId => $data) {
                $user = $data['user'];
                $masterData = $data['masterData'];
                $employeeRows = $data['employeeRows']; // Gunakan employeeRows dari Employee Summary
                $totalTelat = $data['totalTelat'];
                $totalLembur = $data['totalLembur'];
                $hariKerja = $data['hariKerja'];
                $userPoint = $data['userPoint'];
                
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

                // Hitung potongan telat (flat rate Rp 500 per menit)
                $potonganTelat = 0;
                $gajiPerMenit = 500; // Flat rate Rp 500 per menit
                if ($totalTelat > 0) {
                    $potonganTelat = $totalTelat * $gajiPerMenit;
                    
                    // Debug logging untuk perhitungan gaji per menit
                    \Log::info('Gaji per menit calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'total_telat' => $totalTelat,
                        'gaji_per_menit' => $gajiPerMenit,
                        'potongan_telat' => $potonganTelat,
                        'calculation_formula' => "{$totalTelat} menit × Rp {$gajiPerMenit} = Rp {$potonganTelat}"
                    ]);
                }

                // Hitung service charge (By Point dan Pro Rate) jika enabled
                $serviceChargeByPointAmount = 0;
                $serviceChargeProRateAmount = 0;
                $serviceChargeTotal = 0;
                
                if ($masterData->sc == 1 && $serviceCharge > 0) {
                    // Service charge by point = rate × (point × hari kerja)
                    $serviceChargeByPointAmount = $rateByPoint * ($userPoint * $hariKerja);
                    
                    // Service charge pro rate = rate × hari kerja
                    $serviceChargeProRateAmount = $rateProRate * $hariKerja;
                    
                    // Total service charge per user
                    $serviceChargeTotal = $serviceChargeByPointAmount + $serviceChargeProRateAmount;
                } else {
                    // Debug logging untuk service charge = 0
                    \Log::info('Service charge = 0 for user', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'sc_enabled' => $masterData->sc,
                        'service_charge_input' => $serviceCharge,
                        'hari_kerja' => $hariKerja,
                        'user_point' => $userPoint,
                        'reason' => $masterData->sc != 1 ? 'sc not enabled' : 'service_charge input is 0'
                    ]);
                }

                // Hitung custom earnings dan deductions
                $userCustomItems = $customItems->get($user->id, collect());
                $customEarnings = $userCustomItems->where('item_type', 'earn')->sum('item_amount');
                $customDeductions = $userCustomItems->where('item_type', 'deduction')->sum('item_amount');

                // Hitung total gaji (service charge ditambahkan sebagai earning)
                $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $serviceChargeTotal + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $customDeductions;
                
                $payrollDataItem = [
                    'user_id' => $user->id,
                    'nik' => $user->nik,
                    'nama_lengkap' => $user->nama_lengkap,
                    'jabatan' => $jabatans[$user->id_jabatan] ?? '-',
                    'divisi' => $divisions[$user->division_id] ?? '-',
                    'point' => $userPoint,
                    'gaji_pokok' => $masterData->gaji,
                    'tunjangan' => $masterData->tunjangan,
                    'total_telat' => $totalTelat,
                    'total_lembur' => $totalLembur,
                    'nominal_lembur_per_jam' => $divisiNominalLembur[$user->division_id] ?? 0,
                    'gaji_lembur' => round($gajiLembur),
                    'nominal_uang_makan' => $divisiNominalUangMakan[$user->division_id] ?? 0,
                    'uang_makan' => round($uangMakan),
                    'service_charge_by_point' => round($serviceChargeByPointAmount),
                    'service_charge_pro_rate' => round($serviceChargeProRateAmount),
                    'service_charge' => round($serviceChargeTotal),
                    'bpjs_jkn' => round($bpjsJKN),
                    'bpjs_tk' => round($bpjsTK),
                    'custom_earnings' => round($customEarnings),
                    'custom_deductions' => round($customDeductions),
                    'custom_items' => $userCustomItems,
                    'gaji_per_menit' => round($gajiPerMenit, 2),
                    'potongan_telat' => round($potonganTelat),
                    'total_gaji' => round($totalGaji),
                    'hari_kerja' => $hariKerja,
                    'total_alpha' => $totalAlpha,
                    'total_izin_cuti' => $totalIzinCuti,
                    'izin_cuti_breakdown' => $izinCutiBreakdown,
                    'periode' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                    'master_data' => $masterData,
                ];
                
                // Add dynamic leave data directly to payrollData item (same as Employee Summary)
                foreach ($izinCutiBreakdown as $key => $value) {
                    $payrollDataItem[$key] = $value;
                }
                
                $payrollData->push($payrollDataItem);
            }
        }

        return Inertia::render('Payroll/Report', [
            'outlets' => $outlets,
            'months' => $months,
            'years' => $years,
            'payrollData' => $payrollData,
            'leaveTypes' => $leaveTypes,
            'filter' => [
                'outlet_id' => $outletId,
                'month' => $month,
                'year' => $year,
                'service_charge' => $serviceCharge,
            ],
        ]);
    }

    private function getAttendanceData($userId, $outletId, $startDate, $endDate)
    {
        // Use AttendanceController method to get attendance data (same logic as my attendance)
        $attendanceController = new AttendanceController();
        $attendanceDataWithFirstInLastOut = $attendanceController->getAttendanceDataWithFirstInLastOut($userId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
        
        // Get approved absent requests for the date range
        $approvedAbsentsGrouped = $this->getApprovedAbsentRequests($startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $userId);
        // Extract approved absents for this user (since we filtered by userId, it should be in the array)
        $approvedAbsents = $approvedAbsentsGrouped[$userId] ?? [];
        
        // Convert to format expected by PayrollReportController
        $rows = collect();
        $period = [];
        $dt = new \DateTime($startDate->format('Y-m-d'));
        $dtEnd = new \DateTime($endDate->format('Y-m-d'));
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }
        
        // Get today's date (without time) for comparison
        $today = date('Y-m-d');
        
        foreach ($period as $tanggal) {
            $attendanceInfo = $attendanceDataWithFirstInLastOut[$tanggal] ?? null;
            
            // Get shift data to determine if off day
            $shift = DB::table('user_shifts as us')
                ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                ->where('us.user_id', $userId)
                ->where('us.tanggal', $tanggal)
                ->where('us.outlet_id', $outletId)
                ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                ->first();
            
            $is_off = false;
            if ($shift) {
                if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) {
                    $is_off = true;
                }
            } else {
                // Jika tidak ada shift, anggap sebagai off day
                $is_off = true;
            }
            
            // Check directly to att_log table to ensure accuracy
            // First check from attendanceInfo, if not found, check directly from att_log
            $has_scan = false;
            if ($attendanceInfo && isset($attendanceInfo['first_in']) && $attendanceInfo['first_in']) {
                $has_scan = true;
            } else {
                // Double check directly from att_log table using the same join logic as getAttendanceDetail
                $scanCount = DB::table('att_log as a')
                    ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
                    ->join('user_pins as up', function($q) {
                        $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                    })
                    ->join('users as u', 'up.user_id', '=', 'u.id')
                    ->where('u.id', $userId)
                    ->where('o.id_outlet', $outletId)
                    ->whereDate('a.scan_date', $tanggal)
                    ->count();
                
                // If no scans found with outlet_id, try alternative query
                if ($scanCount == 0) {
                    $scanCount = DB::table('att_log as a')
                        ->join('user_pins as up', 'a.pin', '=', 'up.pin')
                        ->join('users as u', 'up.user_id', '=', 'u.id')
                        ->join('tbl_data_outlet as o', 'up.outlet_id', '=', 'o.id_outlet')
                        ->where('u.id', $userId)
                        ->where('o.id_outlet', $outletId)
                        ->whereDate('a.scan_date', $tanggal)
                        ->count();
                }
                
                $has_scan = $scanCount > 0;
            }
            
            // Check if user has approved absent for this date
            $approvedAbsent = null;
            $is_approved_absent = false;
            $approved_absent_name = null;
            if (isset($approvedAbsents[$tanggal])) {
                $approvedAbsent = $approvedAbsents[$tanggal];
                $is_approved_absent = true;
                $approved_absent_name = $approvedAbsent['leave_type_name'];
            }
            
            // Deteksi alpha: ada shift (bukan OFF), tidak ada scan, bukan approved absent, dan tanggal sudah terlewati
            $is_alpha = false;
            // Pastikan format tanggal benar dan perbandingan bekerja
            if (!$is_off && $shift && !$has_scan && !$is_approved_absent) {
                // Pastikan tanggal sudah terlewati (bukan hari ini atau hari yang akan datang)
                if (is_string($tanggal) && strlen($tanggal) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                    // Perbandingan string untuk format Y-m-d sudah benar secara lexicographic
                    if ($tanggal < $today) {
                        $is_alpha = true;
                    }
                } else {
                    // Jika format tidak sesuai, coba parse
                    $tanggalTimestamp = strtotime($tanggal);
                    $todayTimestamp = strtotime($today);
                    if ($tanggalTimestamp !== false && $todayTimestamp !== false && $tanggalTimestamp < $todayTimestamp) {
                        $is_alpha = true;
                    }
                }
            }
            
            $rows->push([
                'tanggal' => $tanggal,
                'telat' => $attendanceInfo['telat'] ?? 0,
                'lembur' => $attendanceInfo['lembur'] ?? 0,
                'extra_off_overtime' => $attendanceInfo['extra_off_overtime'] ?? 0,
                'total_lembur' => $attendanceInfo['total_lembur'] ?? 0,
                'is_off' => $is_off,
                'has_scan' => $has_scan,
                'is_alpha' => $is_alpha,
                'approved_absent' => $approvedAbsent,
                'is_approved_absent' => $is_approved_absent,
                'approved_absent_name' => $approved_absent_name,
            ]);
        }
        
        return $rows;
    }
    
    /**
     * Get approved absent requests for a date range
     * Same logic as AttendanceReportController
     */
    private function getApprovedAbsentRequests($startDate, $endDate, $userId = null)
    {
        $query = DB::table('absent_requests')
            ->join('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->where('absent_requests.status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('absent_requests.date_from', [$startDate, $endDate])
                      ->orWhereBetween('absent_requests.date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('absent_requests.date_from', '<=', $startDate)
                            ->where('absent_requests.date_to', '>=', $endDate);
                      });
            });
            
        // Filter by user if provided
        if ($userId) {
            $query->where('absent_requests.user_id', $userId);
        }
        
        $absents = $query->select([
                'absent_requests.user_id',
                'absent_requests.date_from',
                'absent_requests.date_to',
                'absent_requests.reason',
                'leave_types.name as leave_type_name'
            ])
            ->get();
        
        // Group by user_id and date for easy lookup (same structure as AttendanceReportController)
        $groupedAbsents = [];
        foreach ($absents as $absent) {
            $userIdKey = $absent->user_id;
            if (!isset($groupedAbsents[$userIdKey])) {
                $groupedAbsents[$userIdKey] = [];
            }
            
            // Add all dates in the range
            $fromDate = new \DateTime($absent->date_from);
            $toDate = new \DateTime($absent->date_to);
            
            while ($fromDate <= $toDate) {
                $dateStr = $fromDate->format('Y-m-d');
                if ($dateStr >= $startDate && $dateStr <= $endDate) {
                    $groupedAbsents[$userIdKey][$dateStr] = [
                        'leave_type_name' => $absent->leave_type_name,
                        'reason' => $absent->reason
                    ];
                }
                $fromDate->modify('+1 day');
            }
        }
        
        return $groupedAbsents;
    }

    /**
     * Perhitungan lembur yang MENANGANI CROSS-DAY dengan benar
     * Same logic as AttendanceReportController
     */
    private function calculateSimpleOvertime($jamKeluar, $shiftEnd) {
        if (!$jamKeluar || !$shiftEnd) {
            return 0;
        }
        
        // Ambil jam saja (abaikan tanggal)
        $jamKeluarTime = date('H:i:s', strtotime($jamKeluar));
        
        // Konversi ke timestamp untuk perhitungan
        $keluarTimestamp = strtotime($jamKeluarTime);
        $shiftEndTimestamp = strtotime($shiftEnd);
        
        // Hitung selisih dalam detik
        $diffSeconds = $keluarTimestamp - $shiftEndTimestamp;
        
        
        // Jika selisih negatif, kemungkinan cross-day ATAU checkout lebih awal
        if ($diffSeconds < 0) {
            // Cek apakah ini benar-benar cross-day atau checkout lebih awal
            $checkoutHour = (int)date('H', strtotime($jamKeluarTime));
            $shiftEndHour = (int)date('H', strtotime($shiftEnd));
            
            // Jika checkout di pagi sangat awal (00:00-06:00) dan shift end di sore/malam, ini cross-day
            if ($checkoutHour >= 0 && $checkoutHour <= 6 && $shiftEndHour >= 17) {
                // Untuk cross-day, hitung dari shift end sampai jam keluar di hari berikutnya
                // Misal: shift end 17:00, keluar 06:00 = 13 jam overtime
                $crossDaySeconds = (24 * 3600) + $diffSeconds; // 24 jam + selisih negatif
                $overtimeHours = floor($crossDaySeconds / 3600);
                
            } else {
                // Ini bukan cross-day, tapi checkout lebih awal dari shift end
                // Tidak ada lembur
                $overtimeHours = 0;
                
            }
        } else {
            // Normal overtime
            $overtimeHours = floor($diffSeconds / 3600);
            
        }
        
        // Batasi maksimal 12 jam untuk mencegah error
        $overtimeHours = min($overtimeHours, 12);
        
        // FIXED: Jangan hitung lembur jika checkout lebih awal dari shift end
        if ($overtimeHours < 0) {
            $overtimeHours = 0;
        }
        
        return $overtimeHours;
    }

    /**
     * Get overtime hours from Extra Off system for a specific user on a specific date
     * 
     * @param int $userId
     * @param string $date
     * @return float Overtime hours for that date (rounded down)
     */
    private function getExtraOffOvertimeHoursForDate($userId, $date)
    {
        try {
            // Get overtime transaction from Extra Off system for specific date
            $overtimeTransaction = DB::table('extra_off_transactions')
                ->where('user_id', $userId)
                ->where('source_type', 'overtime_work')
                ->where('transaction_type', 'earned')
                ->where('status', 'approved') // Only count approved transactions
                ->where('source_date', $date)
                ->first();

            if (!$overtimeTransaction) {
                return 0;
            }

            // Extract work hours from description
            // Format: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (jam 08:00 - 18:00, 10.45 jam)"
            // or: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (10.45 jam)"
            $workHours = 0;
            if (preg_match('/\(jam\s+[0-9:]+\s*-\s*[0-9:]+,\s*([0-9.]+)\s*jam\)/', $overtimeTransaction->description, $matches)) {
                // New format with time range
                $workHours = (float) $matches[1];
            } elseif (preg_match('/\(([0-9.]+)\s*jam\)/', $overtimeTransaction->description, $matches)) {
                // Old format without time range
                $workHours = (float) $matches[1];
            }

            // Round down (bulatkan ke bawah)
            return floor($workHours);

        } catch (\Exception $e) {
            \Log::error('Error calculating Extra Off overtime hours for date', [
                'user_id' => $userId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            
            return 0; // Return 0 if there's an error
        }
    }

    /**
     * Get overtime hours from Extra Off system for a specific user in a date range
     * 
     * @param int $userId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return float Total overtime hours for the period (rounded down)
     */
    private function getExtraOffOvertimeHoursForPeriod($userId, $startDate, $endDate)
    {
        try {
            // Get all overtime transactions from Extra Off system for the date range
            $overtimeTransactions = DB::table('extra_off_transactions')
                ->where('user_id', $userId)
                ->where('source_type', 'overtime_work')
                ->where('transaction_type', 'earned')
                ->where('status', 'approved') // Only count approved transactions
                ->whereBetween('source_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            $totalOvertimeHours = 0;

            foreach ($overtimeTransactions as $transaction) {
                // Extract work hours from description
                // Format: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (jam 08:00 - 18:00, 10.45 jam)"
                // or: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (10.45 jam)"
                $workHours = 0;
                if (preg_match('/\(jam\s+[0-9:]+\s*-\s*[0-9:]+,\s*([0-9.]+)\s*jam\)/', $transaction->description, $matches)) {
                    // New format with time range
                    $workHours = (float) $matches[1];
                } elseif (preg_match('/\(([0-9.]+)\s*jam\)/', $transaction->description, $matches)) {
                    // Old format without time range
                    $workHours = (float) $matches[1];
                }

                $totalOvertimeHours += $workHours;
            }

            // Round down (bulatkan ke bawah)
            return floor($totalOvertimeHours);

        } catch (\Exception $e) {
            \Log::error('Error calculating Extra Off overtime hours for period', [
                'user_id' => $userId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            
            return 0; // Return 0 if there's an error
        }
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
        $serviceCharge = $request->input('service_charge', 0);

        if (!$outletId || !$month || !$year) {
            return response()->json(['error' => 'Parameter tidak lengkap'], 400);
        }

        // Hitung periode payroll (26 bulan sebelumnya - 25 bulan yang dipilih)
        // Sama seperti report attendance
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

        // Ambil data level dari jabatan dan point
        $jabatanLevels = DB::table('tbl_data_jabatan')->pluck('id_level', 'id_jabatan');
        $levelPoints = DB::table('tbl_data_level')->pluck('nilai_point', 'id');
        $divisiNominalLembur = DB::table('tbl_data_divisi')->pluck('nominal_lembur', 'id');
        $divisiNominalUangMakan = DB::table('tbl_data_divisi')->pluck('nominal_uang_makan', 'id');
        $levelNominalDasarBPJS = DB::table('tbl_data_level')->pluck('nilai_dasar_potongan_bpjs', 'id');

        // Step 1: Hitung semua data dasar untuk semua user terlebih dahulu
        $userData = [];
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
            // Gunakan total_lembur jika ada (sudah include Extra Off overtime), jika tidak gunakan lembur biasa
            $totalLembur = $attendanceData->sum(function($item) {
                return $item['total_lembur'] ?? $item['lembur'] ?? 0;
            });

            // Hitung hari kerja berdasarkan data attendance yang sebenarnya terjadi
            $hariKerja = $attendanceData->filter(function($item) {
                return isset($item['has_scan']) && $item['has_scan'] && !$item['is_off'];
            })->count();

            // Ambil point dari level melalui jabatan
            $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
            $userPoint = $userLevel ? ($levelPoints[$userLevel] ?? 0) : 0;

            // Simpan data user
            $userData[$user->id] = [
                'user' => $user,
                'masterData' => $masterData,
                'attendanceData' => $attendanceData,
                'totalTelat' => $totalTelat,
                'totalLembur' => $totalLembur,
                'hariKerja' => $hariKerja,
                'userPoint' => $userPoint,
            ];
        }

        // Step 2: Hitung total untuk service charge
        $totalPointHariKerja = 0;
        $totalHariKerja = 0;
        
        foreach ($userData as $data) {
            if ($data['masterData']->sc == 1) {
                $totalPointHariKerja += $data['userPoint'] * $data['hariKerja'];
                $totalHariKerja += $data['hariKerja'];
            }
        }

        // Step 3: Hitung rate service charge (50:50)
        $serviceChargeByPoint = 0;
        $serviceChargeProRate = 0;
        $rateByPoint = 0;
        $rateProRate = 0;

        if ($serviceCharge > 0) {
            $serviceChargeByPoint = $serviceCharge / 2;
            $serviceChargeProRate = $serviceCharge / 2;

            if ($totalPointHariKerja > 0) {
                $rateByPoint = $serviceChargeByPoint / $totalPointHariKerja;
            }
            if ($totalHariKerja > 0) {
                $rateProRate = $serviceChargeProRate / $totalHariKerja;
            }
        }

        // Step 4: Hitung service charge per user dan export data
        $exportData = [];
        foreach ($userData as $userId => $data) {
            $user = $data['user'];
            $masterData = $data['masterData'];
            $totalTelat = $data['totalTelat'];
            $totalLembur = $data['totalLembur'];
            $hariKerja = $data['hariKerja'];
            $userPoint = $data['userPoint'];

            $gajiLembur = 0;
            if ($totalLembur > 0 && $masterData->ot == 1) {
                $nominalLembur = $divisiNominalLembur[$user->division_id] ?? 0;
                $gajiLembur = $totalLembur * $nominalLembur;
            }

            // Hitung uang makan berdasarkan hari kerja
            $uangMakan = 0;
            if ($masterData->um == 1) {
                $nominalUangMakan = $divisiNominalUangMakan[$user->division_id] ?? 0;
                $uangMakan = $hariKerja * $nominalUangMakan;
            }

            // Hitung service charge (By Point dan Pro Rate) jika enabled
            $serviceChargeByPointAmount = 0;
            $serviceChargeProRateAmount = 0;
            $serviceChargeTotal = 0;
            
            if ($masterData->sc == 1 && $serviceCharge > 0) {
                $serviceChargeByPointAmount = $rateByPoint * ($userPoint * $hariKerja);
                $serviceChargeProRateAmount = $rateProRate * $hariKerja;
                $serviceChargeTotal = $serviceChargeByPointAmount + $serviceChargeProRateAmount;
            }

            // Hitung BPJS JKN dan BPJS TK berdasarkan level dan outlet
            $bpjsJKN = 0;
            $bpjsTK = 0;
            if ($masterData->bpjs_jkn == 1 || $masterData->bpjs_tk == 1) {
                $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
                $nilaiDasarBPJS = $userLevel ? ($levelNominalDasarBPJS[$userLevel] ?? 0) : 0;
                
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

            // Hitung potongan telat (flat rate Rp 500 per menit)
            $potonganTelat = 0;
            $gajiPerMenit = 500; // Flat rate Rp 500 per menit
            if ($totalTelat > 0) {
                $potonganTelat = $totalTelat * $gajiPerMenit;
            }
            $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $serviceChargeTotal - $potonganTelat - $bpjsJKN - $bpjsTK;

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
                'Service Charge By Point' => round($serviceChargeByPointAmount),
                'Service Charge Pro Rate' => round($serviceChargeProRateAmount),
                'Service Charge Total' => round($serviceChargeTotal),
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

        // Use AttendanceController method to get attendance data (same logic as my attendance)
        $attendanceController = new AttendanceController();
        $attendanceDataWithFirstInLastOut = $attendanceController->getAttendanceDataWithFirstInLastOut($userId, $startDate, $endDate);
        
        // Get approved absent requests for the date range
        $approvedAbsentsGrouped = $this->getApprovedAbsentRequests($startDate, $endDate, $userId);
        // Extract approved absents for this user (since we filtered by userId, it should be in the array)
        $approvedAbsents = $approvedAbsentsGrouped[$userId] ?? [];
        
        // Get today's date (without time) for comparison
        $today = date('Y-m-d');
        
        // Get all dates in period
        $period = [];
        $dt = new \DateTime($startDate);
        $dtEnd = new \DateTime($endDate);
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }
        
        // Gunakan query yang sama dengan Employee Summary (SAMA PERSIS)
        $rawData = collect();
        $sub = DB::table('att_log as a')
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
                'u.division_id'
            )
            ->where('u.id', $userId)
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
            ->orderBy('a.scan_date')
            ->get();
        
        $rawData = $rawData->merge($sub);

        // Proses data manual untuk menangani cross-day - SAMA PERSIS dengan Employee Summary
        $processedData = [];
        foreach ($rawData as $scan) {
            $date = date('Y-m-d', strtotime($scan->scan_date));
            $key = $scan->user_id . '_' . $date;
            
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'user_id' => $scan->user_id,
                    'nama_lengkap' => $scan->nama_lengkap,
                    'division_id' => $scan->division_id,
                    'scans' => []
                ];
            }
            
            $processedData[$key]['scans'][] = [
                'scan_date' => $scan->scan_date,
                'inoutmode' => $scan->inoutmode
            ];
        }

        // Step 2: Proses setiap kelompok dengan smart cross-day processing - SAMA PERSIS dengan Employee Summary
        $finalData = [];
        foreach ($processedData as $key => $data) {
            // Gunakan smart cross-day processing yang sama dengan Employee Summary
            $result = $this->processSmartCrossDayAttendance($data, $processedData);
            $finalData[] = $result;
        }
        
        // Convert to object seperti Employee Summary
        $dataRows = collect($finalData)->map(function($item) {
            return (object) $item;
        });

        // Get shift data and calculate telat/lembur - SAMA PERSIS dengan Employee Summary
        // Batch query untuk shift data untuk mencegah N+1 query problem
        $allShiftData = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->whereIn('us.tanggal', $dataRows->pluck('tanggal')->unique()->values())
            ->select('us.user_id', 'us.tanggal', 's.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
            ->get()
            ->groupBy(function($item) {
                return $item->user_id . '_' . $item->tanggal;
            });
        
        // Hitung lembur untuk setiap baris - SAMA PERSIS dengan Employee Summary
        foreach ($dataRows as $row) {
            $shiftKey = $row->user_id . '_' . $row->tanggal;
            $shiftData = $allShiftData->get($shiftKey, collect())->first();
            
            if ($row->jam_masuk && $row->jam_keluar && $shiftData) {
                // Gunakan smart overtime calculation - SAMA PERSIS dengan Employee Summary
                $row->lembur = $this->calculateSimpleOvertime($row->jam_keluar, $shiftData->time_end);
                // Round down (bulatkan ke bawah)
                $row->lembur = floor($row->lembur);
            } else {
                $row->lembur = 0;
            }
        }
        
        // Ambil semua tanggal libur dalam periode
        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$startDate, $endDate])
            ->pluck('keterangan', 'tgl_libur');
        
        // Build rows for each tanggal in period - SAMA PERSIS dengan Employee Summary
        $attendanceDetail = [];
        foreach ($period as $tanggal) {
            $dayData = $dataRows->where('tanggal', $tanggal);
            
            if ($dayData->count() > 0) {
                foreach ($dayData as $row) {
                    $jam_masuk = $row->jam_masuk ? date('H:i:s', strtotime($row->jam_masuk)) : null;
                    $jam_keluar = $row->jam_keluar ? date('H:i:s', strtotime($row->jam_keluar)) : null;
                    $telat = 0;
                    $lembur = $row->lembur ?? 0;
                    $is_off = false;
                    $shift_name = null;
                    
                    // Get shift data
                    $shiftKey = $row->user_id . '_' . $row->tanggal;
                    $shiftData = $allShiftData->get($shiftKey, collect())->first();
                    
                    if ($shiftData) {
                        $shift_name = $shiftData->shift_name;
                        if (is_null($shiftData->shift_id) || (strtolower($shiftData->shift_name ?? '') === 'off')) {
                            $is_off = true;
                        }
                    }
                    
                    if (!$is_off) {
                        // Calculate telat - SAMA PERSIS dengan Employee Summary
                        if ($shiftData && $shiftData->time_start && $jam_masuk) {
                            $telat = $this->calculateLateness($jam_masuk, $shiftData->time_start, $row->is_cross_day ?? false);
                        }
                        
                        // Tambahkan telat jika checkout lebih awal dari shift end - SAMA PERSIS dengan Employee Summary
                        if (!($row->is_cross_day ?? false)) {
                            if ($shiftData && $shiftData->time_end && $jam_keluar) {
                                $earlyCheckoutTelat = $this->calculateEarlyCheckoutLateness($jam_keluar, $shiftData->time_end, $row->is_cross_day ?? false);
                                $telat += $earlyCheckoutTelat;
                            }
                        }
                    } else {
                        $jam_masuk = null;
                        $jam_keluar = null;
                        $telat = 0;
                        $lembur = 0;
                    }
                    
                    // Get overtime from Extra Off system for this date (tetap ambil meskipun is_off)
                    $extraOffOvertime = $this->getExtraOffOvertimeHoursForDate($row->user_id, $row->tanggal);
                    // Round down total lembur (bulatkan ke bawah)
                    $totalLembur = floor($lembur + $extraOffOvertime);
                    
                    // Check if user has approved absent for this date
                    $approvedAbsent = null;
                    $is_approved_absent = false;
                    $approved_absent_name = null;
                    if (isset($approvedAbsents[$row->tanggal])) {
                        $approvedAbsent = $approvedAbsents[$row->tanggal];
                        $is_approved_absent = true;
                        $approved_absent_name = $approvedAbsent['leave_type_name'];
                    }
                    
                    // Deteksi alpha: ada shift (bukan OFF), tidak ada scan, bukan approved absent, dan tanggal sudah terlewati
                    $is_alpha = false;
                    if (!$is_off && $shiftData && !$row->jam_masuk && !$row->jam_keluar && !$is_approved_absent) {
                        if ($row->tanggal < $today) {
                            $is_alpha = true;
                        }
                    }
                    
                    $attendanceDetail[] = [
                        'tanggal' => $row->tanggal,
                        'jam_masuk' => $jam_masuk,
                        'jam_keluar' => $jam_keluar,
                        'total_masuk' => $row->total_masuk ?? 0,
                        'total_keluar' => $row->total_keluar ?? 0,
                        'telat' => $telat,
                        'lembur' => $lembur,
                        'extra_off_overtime' => $extraOffOvertime,
                        'total_lembur' => $totalLembur,
                        'shift_name' => $shift_name,
                        'is_cross_day' => $row->is_cross_day ?? false,
                        'is_off' => $is_off,
                        'is_holiday' => $holidays->has($row->tanggal),
                        'holiday_name' => $holidays->get($row->tanggal),
                        'is_alpha' => $is_alpha,
                        'approved_absent' => $approvedAbsent,
                        'is_approved_absent' => $is_approved_absent,
                        'approved_absent_name' => $approved_absent_name
                    ];
                }
            } else {
                // No attendance data for this date - check for shift, off, alpha, Extra Off overtime
                $shiftKey = $userId . '_' . $tanggal;
                $shiftData = $allShiftData->get($shiftKey, collect())->first();
                
                $shift_name = $shiftData ? $shiftData->shift_name : null;
                $is_off = false;
                
                if ($shiftData) {
                    if (is_null($shiftData->shift_id) || (strtolower($shiftData->shift_name ?? '') === 'off')) {
                        $is_off = true;
                    }
                } else {
                    $is_off = true;
                }
                
                // Check if user has approved absent for this date
                $approvedAbsent = null;
                $is_approved_absent = false;
                $approved_absent_name = null;
                if (isset($approvedAbsents[$tanggal])) {
                    $approvedAbsent = $approvedAbsents[$tanggal];
                    $is_approved_absent = true;
                    $approved_absent_name = $approvedAbsent['leave_type_name'];
                }
                
                // Check for Extra Off overtime on this date
                $extraOffOvertime = $this->getExtraOffOvertimeHoursForDate($userId, $tanggal);
                
                // Deteksi alpha: ada shift (bukan OFF), tidak ada scan, bukan approved absent, tidak ada Extra Off overtime, dan tanggal sudah terlewati
                $is_alpha = false;
                if (!$is_off && $shiftData && $extraOffOvertime == 0 && !$is_approved_absent) {
                    if ($tanggal < $today) {
                        $is_alpha = true;
                    }
                }
                
                $attendanceDetail[] = [
                    'tanggal' => $tanggal,
                    'jam_masuk' => null,
                    'jam_keluar' => null,
                    'total_masuk' => 0,
                    'total_keluar' => 0,
                    'telat' => 0,
                    'lembur' => 0,
                    'extra_off_overtime' => $extraOffOvertime,
                    'total_lembur' => $extraOffOvertime,
                    'shift_name' => $shift_name,
                    'is_cross_day' => false,
                    'is_off' => $is_off,
                    'is_holiday' => $holidays->has($tanggal),
                    'holiday_name' => $holidays->get($tanggal),
                    'is_alpha' => $is_alpha,
                    'approved_absent' => $approvedAbsent,
                    'is_approved_absent' => $is_approved_absent,
                    'approved_absent_name' => $approved_absent_name
                ];
            }
        }
        
        // Sort by tanggal
        usort($attendanceDetail, function($a, $b) {
            return strcmp($a['tanggal'], $b['tanggal']);
        });

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
        $serviceCharge = $request->input('service_charge', 0);

        // Get user data
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Get outlet data
        $outletData = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
        if (!$outletData) {
            return response()->json(['error' => 'Outlet not found'], 404);
        }
        $outlet = $outletData->nama_outlet ?? 'Unknown Outlet';

        // Cek apakah payroll sudah di-generate
        $payrollGenerated = DB::table('payroll_generated')
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $payrollDetail = null;
        if ($payrollGenerated) {
            // Ambil data dari payroll_generated_details jika sudah di-generate
            $payrollDetail = DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payrollGenerated->id)
                ->where('user_id', $userId)
                ->first();
        }

        // Get master data (untuk konfigurasi payroll, bukan data per periode)
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

        // Get position, division data
        $jabatan = DB::table('tbl_data_jabatan')->where('id_jabatan', $user->id_jabatan)->value('nama_jabatan');
        $divisi = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nama_divisi');

        // Jika payroll sudah di-generate, gunakan data dari payroll_generated_details
        if ($payrollDetail) {
            // Gunakan data dari payroll_generated_details
            $gajiLembur = $payrollDetail->gaji_lembur ?? 0;
            $nominalLemburPerJam = $payrollDetail->nominal_lembur_per_jam ?? 0;
            $uangMakan = $payrollDetail->uang_makan ?? 0;
            $nominalUangMakan = $payrollDetail->nominal_uang_makan ?? 0;
            $totalTelat = $payrollDetail->total_telat ?? 0;
            $potonganTelat = $payrollDetail->potongan_telat ?? 0;
            $gajiPerMenit = $payrollDetail->gaji_per_menit ?? 500;
            $bpjsJKN = $payrollDetail->bpjs_jkn ?? 0;
            $bpjsTK = $payrollDetail->bpjs_tk ?? 0;
            $customEarnings = $payrollDetail->custom_earnings ?? 0;
            $customDeductions = $payrollDetail->custom_deductions ?? 0;
            $serviceChargeAmount = $payrollDetail->service_charge ?? 0;
            $totalGaji = $payrollDetail->total_gaji ?? 0;
            $hariKerja = $payrollDetail->hari_kerja ?? 0;
            $totalLembur = $payrollDetail->total_lembur ?? 0;
            
            // Get custom items dari JSON
            $customItems = collect([]);
            if ($payrollDetail->custom_items) {
                // Decode sebagai objects (false) agar bisa diakses dengan -> seperti di view
                $decodedItems = json_decode($payrollDetail->custom_items, false) ?? [];
                $customItems = collect($decodedItems);
            }
        } else {
            // Jika belum di-generate, hitung ulang seperti biasa menggunakan logic dari printPayroll
            // Hitung periode payroll
            $startDate = Carbon::create($year, $month, 26)->subMonth();
            $endDate = Carbon::create($year, $month, 25);

            // Ambil data attendance
            $attendanceData = $this->getAttendanceData($userId, $outletId, $startDate, $endDate);
            $totalTelat = $attendanceData->sum('telat');
            $totalLembur = $attendanceData->sum(function($item) {
                return $item['total_lembur'] ?? $item['lembur'] ?? 0;
            });

            // Hitung hari kerja
            $hariKerja = $attendanceData->filter(function($item) {
                return isset($item['has_scan']) && $item['has_scan'] && !$item['is_off'];
            })->count();

            // Ambil data nominal dari divisi
            $nominalLembur = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nominal_lembur') ?? 0;
            $nominalUangMakan = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nominal_uang_makan') ?? 0;

            // Hitung gaji lembur
            $gajiLembur = 0;
            if ($totalLembur > 0 && $masterData->ot == 1) {
                $gajiLembur = $totalLembur * $nominalLembur;
            }
            $nominalLemburPerJam = $nominalLembur;

            // Hitung uang makan
            $uangMakan = 0;
            if ($masterData->um == 1) {
                $uangMakan = $hariKerja * $nominalUangMakan;
            }
            $nominalUangMakan = $nominalUangMakan;

            // Hitung potongan telat
            $gajiPerMenit = 500; // Flat rate Rp 500 per menit
            $potonganTelat = $totalTelat * $gajiPerMenit;

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

            // Get custom items
            $customItems = CustomPayrollItem::forOutlet($outletId)
                ->forPeriod($month, $year)
                ->where('user_id', $userId)
                ->get();
            
            $customEarnings = $customItems->where('item_type', 'earn')->sum('item_amount');
            $customDeductions = $customItems->where('item_type', 'deduction')->sum('item_amount');

            // Hitung service charge jika enabled
            $serviceChargeAmount = 0;
            if ($masterData->sc == 1 && $serviceCharge > 0) {
                $serviceChargeAmount = $serviceCharge;
            }

            // Calculate total salary
            $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $serviceChargeAmount + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $customDeductions;
        }

        // Get position, division data (diluar if/else karena digunakan di kedua kondisi)
        $jabatan = DB::table('tbl_data_jabatan')->where('id_jabatan', $user->id_jabatan)->value('nama_jabatan');
        $divisi = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nama_divisi');

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

        // Format period - gunakan periode dari payroll_generated_details jika ada, atau hitung manual
        if ($payrollDetail && $payrollDetail->periode) {
            $periode = $payrollDetail->periode;
        } else {
            // Hitung periode payroll (26 bulan sebelumnya sampai 25 bulan ini)
            $startDate = Carbon::create($year, $month, 26)->subMonth();
            $endDate = Carbon::create($year, $month, 25);
            $periode = $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');
        }

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
                'service_charge' => round($serviceChargeAmount),
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
            'service_charge' => round($serviceChargeAmount),
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
        $serviceCharge = $request->input('service_charge', 0);

        if (!$userId || !$outletId || !$month || !$year) {
            return response()->json(['error' => 'Parameter tidak lengkap'], 400);
        }

        // Hitung periode payroll (26 bulan sebelumnya - 25 bulan yang dipilih)
        // Sama seperti report attendance
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

        // Cek apakah payroll sudah di-generate
        $payrollGenerated = DB::table('payroll_generated')
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $payrollDetail = null;
        if ($payrollGenerated) {
            // Ambil data dari payroll_generated_details jika sudah di-generate
            $payrollDetail = DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payrollGenerated->id)
                ->where('user_id', $userId)
                ->first();
        }

        // Get master data (untuk konfigurasi payroll, bukan data per periode)
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

        // Jika payroll sudah di-generate, gunakan data dari payroll_generated_details
        if ($payrollDetail) {
            // Gunakan data dari payroll_generated_details
            $gajiLembur = $payrollDetail->gaji_lembur ?? 0;
            $nominalLemburPerJam = $payrollDetail->nominal_lembur_per_jam ?? 0;
            $uangMakan = $payrollDetail->uang_makan ?? 0;
            $nominalUangMakan = $payrollDetail->nominal_uang_makan ?? 0;
            $totalTelat = $payrollDetail->total_telat ?? 0;
            $potonganTelat = $payrollDetail->potongan_telat ?? 0;
            $gajiPerMenit = $payrollDetail->gaji_per_menit ?? 500;
            $bpjsJKN = $payrollDetail->bpjs_jkn ?? 0;
            $bpjsTK = $payrollDetail->bpjs_tk ?? 0;
            $customEarnings = $payrollDetail->custom_earnings ?? 0;
            $customDeductions = $payrollDetail->custom_deductions ?? 0;
            $serviceChargeAmount = $payrollDetail->service_charge ?? 0;
            $totalGaji = $payrollDetail->total_gaji ?? 0;
            $hariKerja = $payrollDetail->hari_kerja ?? 0;
            $totalLembur = $payrollDetail->total_lembur ?? 0;
            
            // Get custom items dari JSON
            $customItems = collect([]);
            if ($payrollDetail->custom_items) {
                // Decode sebagai objects (false) agar bisa diakses dengan -> seperti di view
                $decodedItems = json_decode($payrollDetail->custom_items, false) ?? [];
                $customItems = collect($decodedItems);
            }
        } else {
            // Jika belum di-generate, hitung ulang seperti biasa
            // Ambil data attendance
            $attendanceData = $this->getAttendanceData($userId, $outletId, $startDate, $endDate);
            $totalTelat = $attendanceData->sum('telat');
            // Gunakan total_lembur jika ada (sudah include Extra Off overtime), jika tidak gunakan lembur biasa
            $totalLembur = $attendanceData->sum(function($item) {
                return $item['total_lembur'] ?? $item['lembur'] ?? 0;
            });

            // Hitung hari kerja berdasarkan data attendance yang sebenarnya terjadi
            // Hanya hitung hari yang benar-benar ada scan attendance (bukan yang dijadwalkan saja)
            $hariKerja = $attendanceData->filter(function($item) {
                return isset($item['has_scan']) && $item['has_scan'] && !$item['is_off'];
            })->count();

            // Ambil data nominal dari divisi
            $nominalLembur = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nominal_lembur') ?? 0;
            $nominalUangMakan = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nominal_uang_makan') ?? 0;

            // Hitung gaji lembur
            $gajiLembur = 0;
            if ($totalLembur > 0 && $masterData->ot == 1) {
                $gajiLembur = $totalLembur * $nominalLembur;
            }
            $nominalLemburPerJam = $nominalLembur;

            // Hitung uang makan
            $uangMakan = 0;
            if ($masterData->um == 1) {
                $uangMakan = $hariKerja * $nominalUangMakan;
            }
            $nominalUangMakan = $nominalUangMakan;

            // Hitung potongan telat
            $gajiPerMenit = 500; // Flat rate Rp 500 per menit
            $potonganTelat = $totalTelat * $gajiPerMenit;

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

            // Get custom items
            $customItems = CustomPayrollItem::forOutlet($outletId)
                ->forPeriod($month, $year)
                ->where('user_id', $userId)
                ->get();
            
            $customEarnings = $customItems->where('item_type', 'earn')->sum('item_amount');
            $customDeductions = $customItems->where('item_type', 'deduction')->sum('item_amount');

            // Hitung service charge jika enabled
            $serviceChargeAmount = 0;
            if ($masterData->sc == 1 && $serviceCharge > 0) {
                $serviceChargeAmount = $serviceCharge;
            }

            // Calculate total salary
            $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $serviceChargeAmount + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $customDeductions;
        }

        // Get position, division data (diluar if/else karena digunakan di kedua kondisi)
        $jabatan = DB::table('tbl_data_jabatan')->where('id_jabatan', $user->id_jabatan)->value('nama_jabatan');
        $divisi = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nama_divisi');
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');

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

        // Format period - gunakan periode dari payroll_generated_details jika ada, atau hitung manual
        if ($payrollDetail && $payrollDetail->periode) {
            $periode = $payrollDetail->periode;
        } else {
            // Hitung periode payroll (26 bulan sebelumnya sampai 25 bulan ini)
            $periode = $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');
        }

        // Generate PDF
        $pdf = \PDF::loadView('payroll.slip', [
            'user' => $user,
            'jabatan' => $jabatan,
            'divisi' => $divisi,
            'outlet' => $outlet,
            'periode' => $periode,
            'gaji_pokok' => $masterData->gaji,
            'tunjangan' => $masterData->tunjangan,
            'total_telat' => $totalTelat,
            'total_lembur' => $totalLembur,
            'nominal_lembur_per_jam' => isset($nominalLemburPerJam) ? $nominalLemburPerJam : (isset($nominalLembur) ? $nominalLembur : 0),
            'gaji_lembur' => round($gajiLembur),
            'nominal_uang_makan' => $nominalUangMakan,
            'uang_makan' => round($uangMakan),
            'service_charge' => round($serviceChargeAmount),
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

    // Generate Payroll - Simpan payroll ke database
    public function generatePayroll(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
            'service_charge' => 'nullable|numeric|min:0',
            'payroll_data' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $outletId = $request->outlet_id;
            $month = $request->month;
            $year = $request->year;
            $serviceCharge = $request->service_charge ?? 0;
            $payrollData = $request->payroll_data;

            // Cek apakah payroll untuk periode ini sudah ada
            $existingPayroll = DB::table('payroll_generated')
                ->where('outlet_id', $outletId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if ($existingPayroll) {
                // Update jika sudah ada
                DB::table('payroll_generated')
                    ->where('id', $existingPayroll->id)
                    ->update([
                        'service_charge' => $serviceCharge,
                        'status' => 'generated',
                        'updated_at' => now(),
                        'updated_by' => auth()->id(),
                    ]);

                $payrollId = $existingPayroll->id;
                
                // Hapus detail lama
                DB::table('payroll_generated_details')
                    ->where('payroll_generated_id', $payrollId)
                    ->delete();
            } else {
                // Insert baru
                $payrollId = DB::table('payroll_generated')->insertGetId([
                    'outlet_id' => $outletId,
                    'month' => $month,
                    'year' => $year,
                    'service_charge' => $serviceCharge,
                    'status' => 'generated',
                    'created_at' => now(),
                    'created_by' => auth()->id(),
                    'updated_at' => now(),
                    'updated_by' => auth()->id(),
                ]);
            }

            // Simpan detail payroll per karyawan
            foreach ($payrollData as $item) {
                DB::table('payroll_generated_details')->insert([
                    'payroll_generated_id' => $payrollId,
                    'user_id' => $item['user_id'],
                    'nik' => $item['nik'] ?? null,
                    'nama_lengkap' => $item['nama_lengkap'] ?? null,
                    'jabatan' => $item['jabatan'] ?? null,
                    'divisi' => $item['divisi'] ?? null,
                    'point' => $item['point'] ?? 0,
                    'gaji_pokok' => $item['gaji_pokok'] ?? 0,
                    'tunjangan' => $item['tunjangan'] ?? 0,
                    'total_telat' => $item['total_telat'] ?? 0,
                    'total_lembur' => $item['total_lembur'] ?? 0,
                    'nominal_lembur_per_jam' => $item['nominal_lembur_per_jam'] ?? 0,
                    'gaji_lembur' => $item['gaji_lembur'] ?? 0,
                    'nominal_uang_makan' => $item['nominal_uang_makan'] ?? 0,
                    'uang_makan' => $item['uang_makan'] ?? 0,
                    'service_charge_by_point' => $item['service_charge_by_point'] ?? 0,
                    'service_charge_pro_rate' => $item['service_charge_pro_rate'] ?? 0,
                    'service_charge' => $item['service_charge'] ?? 0,
                    'bpjs_jkn' => $item['bpjs_jkn'] ?? 0,
                    'bpjs_tk' => $item['bpjs_tk'] ?? 0,
                    'custom_earnings' => $item['custom_earnings'] ?? 0,
                    'custom_deductions' => $item['custom_deductions'] ?? 0,
                    'gaji_per_menit' => $item['gaji_per_menit'] ?? 0,
                    'potongan_telat' => $item['potongan_telat'] ?? 0,
                    'total_gaji' => $item['total_gaji'] ?? 0,
                    'hari_kerja' => $item['hari_kerja'] ?? 0,
                    'periode' => $item['periode'] ?? null,
                    'custom_items' => isset($item['custom_items']) ? json_encode($item['custom_items']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll berhasil di-generate dan disimpan',
                'payroll_id' => $payrollId
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error generating payroll: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    // Edit Payroll - Update payroll yang sudah di-generate
    public function editPayroll(Request $request)
    {
        $request->validate([
            'payroll_id' => 'required|integer',
            'service_charge' => 'nullable|numeric|min:0',
            'payroll_data' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $payrollId = $request->payroll_id;
            $serviceCharge = $request->service_charge ?? 0;
            $payrollData = $request->payroll_data;

            // Cek apakah payroll ada
            $payroll = DB::table('payroll_generated')
                ->where('id', $payrollId)
                ->first();

            if (!$payroll) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll tidak ditemukan'
                ], 404);
            }

            // Cek status, jika locked tidak bisa di-edit
            if ($payroll->status === 'locked') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll sudah di-lock dan tidak bisa di-edit'
                ], 403);
            }

            // Update payroll header
            DB::table('payroll_generated')
                ->where('id', $payrollId)
                ->update([
                    'service_charge' => $serviceCharge,
                    'status' => 'generated',
                    'updated_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

            // Hapus detail lama
            DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payrollId)
                ->delete();

            // Simpan detail payroll per karyawan
            foreach ($payrollData as $item) {
                DB::table('payroll_generated_details')->insert([
                    'payroll_generated_id' => $payrollId,
                    'user_id' => $item['user_id'],
                    'nik' => $item['nik'] ?? null,
                    'nama_lengkap' => $item['nama_lengkap'] ?? null,
                    'jabatan' => $item['jabatan'] ?? null,
                    'divisi' => $item['divisi'] ?? null,
                    'point' => $item['point'] ?? 0,
                    'gaji_pokok' => $item['gaji_pokok'] ?? 0,
                    'tunjangan' => $item['tunjangan'] ?? 0,
                    'total_telat' => $item['total_telat'] ?? 0,
                    'total_lembur' => $item['total_lembur'] ?? 0,
                    'nominal_lembur_per_jam' => $item['nominal_lembur_per_jam'] ?? 0,
                    'gaji_lembur' => $item['gaji_lembur'] ?? 0,
                    'nominal_uang_makan' => $item['nominal_uang_makan'] ?? 0,
                    'uang_makan' => $item['uang_makan'] ?? 0,
                    'service_charge_by_point' => $item['service_charge_by_point'] ?? 0,
                    'service_charge_pro_rate' => $item['service_charge_pro_rate'] ?? 0,
                    'service_charge' => $item['service_charge'] ?? 0,
                    'bpjs_jkn' => $item['bpjs_jkn'] ?? 0,
                    'bpjs_tk' => $item['bpjs_tk'] ?? 0,
                    'custom_earnings' => $item['custom_earnings'] ?? 0,
                    'custom_deductions' => $item['custom_deductions'] ?? 0,
                    'gaji_per_menit' => $item['gaji_per_menit'] ?? 0,
                    'potongan_telat' => $item['potongan_telat'] ?? 0,
                    'total_gaji' => $item['total_gaji'] ?? 0,
                    'hari_kerja' => $item['hari_kerja'] ?? 0,
                    'periode' => $item['periode'] ?? null,
                    'custom_items' => isset($item['custom_items']) ? json_encode($item['custom_items']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll berhasil di-update'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error editing payroll: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal update payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    // Rollback Payroll - Hapus payroll yang sudah di-generate
    public function rollbackPayroll(Request $request)
    {
        $request->validate([
            'payroll_id' => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            $payrollId = $request->payroll_id;

            // Cek apakah payroll ada
            $payroll = DB::table('payroll_generated')
                ->where('id', $payrollId)
                ->first();

            if (!$payroll) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll tidak ditemukan'
                ], 404);
            }

            // Cek status, jika locked tidak bisa di-rollback
            if ($payroll->status === 'locked') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll sudah di-lock dan tidak bisa di-rollback'
                ], 403);
            }

            // Hapus detail terlebih dahulu (karena foreign key constraint)
            DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payrollId)
                ->delete();

            // Hapus payroll header
            DB::table('payroll_generated')
                ->where('id', $payrollId)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payroll berhasil di-rollback (dihapus)'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error rolling back payroll: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal rollback payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get Payroll Status - Cek status payroll untuk periode tertentu
    public function getPayrollStatus(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|integer',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
        ]);

        try {
            $payroll = DB::table('payroll_generated')
                ->where('outlet_id', $request->outlet_id)
                ->where('month', $request->month)
                ->where('year', $request->year)
                ->first();

            if ($payroll) {
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'payroll_id' => $payroll->id,
                    'status' => $payroll->status,
                    'created_at' => $payroll->created_at,
                    'updated_at' => $payroll->updated_at,
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'exists' => false,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error getting payroll status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan status payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    // Verify Payroll PIN
    public function verifyPayrollPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        try {
            $user = auth()->user();
            
            if (!$user->pin_payroll) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN Payroll belum diatur. Silakan isi di Profile Anda.'
                ], 400);
            }

            if ($user->pin_payroll !== $request->pin) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN salah'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'PIN benar'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error verifying payroll PIN: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat verifikasi PIN'
            ], 500);
        }
    }

    // Get User Payroll List
    public function getUserPayrollList(Request $request)
    {
        try {
            $userId = auth()->id();
            
            // Ambil data payroll yang sudah di-generate untuk user ini
            $payrollList = DB::table('payroll_generated_details as pgd')
                ->join('payroll_generated as pg', 'pgd.payroll_generated_id', '=', 'pg.id')
                ->leftJoin('tbl_data_outlet as o', 'pg.outlet_id', '=', 'o.id_outlet')
                ->where('pgd.user_id', $userId)
                ->select(
                    'pgd.id',
                    'pgd.user_id',
                    'pg.outlet_id',
                    'o.nama_outlet as outlet_name',
                    'pg.month',
                    'pg.year',
                    'pgd.total_gaji',
                    'pgd.periode',
                    'pg.status',
                    'pg.created_at'
                )
                ->orderBy('pg.year', 'desc')
                ->orderBy('pg.month', 'desc')
                ->orderBy('pg.created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $payrollList
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting user payroll list: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data payroll'
            ], 500);
        }
    }

    /**
     * Calculate leave data (breakdown per leave type)
     * Same logic as AttendanceReportController::calculateLeaveData
     */
    private function calculateLeaveData($userId, $startDate, $endDate)
    {
        // Get all leave types
        $leaveTypes = DB::table('leave_types')
            ->where('is_active', 1)
            ->select('id', 'name')
            ->get();
        
        // Get approved absent requests for this user in the period
        $approvedAbsents = DB::table('absent_requests')
            ->join('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->where('absent_requests.user_id', $userId)
            ->where('absent_requests.status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('absent_requests.date_from', [$startDate, $endDate])
                      ->orWhereBetween('absent_requests.date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('absent_requests.date_from', '<=', $startDate)
                            ->where('absent_requests.date_to', '>=', $endDate);
                      });
            })
            ->select([
                'absent_requests.date_from',
                'absent_requests.date_to',
                'leave_types.id as leave_type_id',
                'leave_types.name as leave_type_name'
            ])
            ->get();
        
        // Initialize result with all leave types
        $result = [];
        foreach ($leaveTypes as $leaveType) {
            $result[strtolower(str_replace(' ', '_', $leaveType->name)) . '_days'] = 0;
        }
        
        // Group by leave type and calculate days - SAMA PERSIS dengan AttendanceReportController
        $leaveDataByType = [];
        foreach ($approvedAbsents as $absent) {
            $leaveTypeId = $absent->leave_type_id;
            $leaveTypeName = $absent->leave_type_name;
            
            // Calculate days between date_from and date_to, but only count days within the period
            $fromDate = new \DateTime(max($absent->date_from, $startDate));
            $toDate = new \DateTime(min($absent->date_to, $endDate));
            
            // Only count if the date range overlaps with the period
            if ($fromDate <= $toDate) {
                $daysCount = $fromDate->diff($toDate)->days + 1;
                
                if (!isset($leaveDataByType[$leaveTypeId])) {
                    $leaveDataByType[$leaveTypeId] = [
                        'name' => $leaveTypeName,
                        'days' => 0
                    ];
                }
                $leaveDataByType[$leaveTypeId]['days'] += $daysCount;
            }
        }
        
        // Map to result format
        foreach ($leaveDataByType as $leaveTypeId => $data) {
            $key = strtolower(str_replace(' ', '_', $data['name'])) . '_days';
            $result[$key] = $data['days'];
        }
        
        // Keep legacy fields for backward compatibility
        $result['extra_off_days'] = $result['extra_off_days'] ?? 0;
        
        return $result;
    }

    /**
     * Calculate alpa days (days with shift but no attendance and no absent request)
     * SAMA PERSIS dengan AttendanceReportController::calculateAlpaDays
     * Hanya hitung tanggal yang sudah lewat (< hari ini)
     */
    private function calculateAlpaDays($userId, $outletId, $startDate, $endDate)
    {
        // Get all days in the period
        $period = [];
        $dt = new \DateTime($startDate);
        $dtEnd = new \DateTime($endDate);
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }
        
        // Get user's shifts for the period - SAMA PERSIS dengan AttendanceReportController
        $shiftsQuery = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->whereIn('us.tanggal', $period)
            ->whereNotNull('us.shift_id') // Must have a shift (not off)
            ->where('s.shift_name', '!=', 'off'); // Exclude 'off' shifts
        
        // Only filter by outlet_id if it's not null (sama seperti Employee Summary)
        if ($outletId !== null) {
            $shiftsQuery->where('us.outlet_id', $outletId);
        }
        
        $shifts = $shiftsQuery->select('us.tanggal', 's.shift_name')
            ->get()
            ->keyBy('tanggal');
        
        // Get user's attendance data using the same logic as AttendanceReportController
        $rawData = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->where('up.user_id', $userId)
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
            ->select('a.scan_date', 'a.inoutmode')
            ->orderBy('a.scan_date')
            ->get();
        
        // Process attendance data like in AttendanceReportController
        $processedData = [];
        foreach ($rawData as $scan) {
            $date = date('Y-m-d', strtotime($scan->scan_date));
            $key = $date;
            
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'scans' => []
                ];
            }
            
            $processedData[$key]['scans'][] = [
                'scan_date' => $scan->scan_date,
                'inoutmode' => $scan->inoutmode
            ];
        }
        
        // Process each day to determine if there's valid attendance
        $attendanceDates = [];
        foreach ($processedData as $key => $data) {
            $scans = collect($data['scans'])->sortBy('scan_date');
            $inScans = $scans->where('inoutmode', 1);
            $outScans = $scans->where('inoutmode', 2);
            
            // Check if there's valid attendance (first in and last out)
            $jamMasuk = $inScans->first()['scan_date'] ?? null;
            $jamKeluar = null;
            $isCrossDay = false;
            
            if ($jamMasuk) {
                // Cari scan keluar di hari yang sama
                $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
                
                if ($sameDayOuts->isNotEmpty()) {
                    // Ada scan keluar di hari yang sama
                    $jamKeluar = $sameDayOuts->last()['scan_date'];
                    $isCrossDay = false;
                } else {
                    // Cari scan keluar di hari berikutnya (cross-day)
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $nextDayKey = $nextDay;
                    
                    if (isset($processedData[$nextDayKey])) {
                        $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                        $nextDayOuts = $nextDayScans->where('inoutmode', 2);
                        
                        if ($nextDayOuts->isNotEmpty()) {
                            $jamKeluar = $nextDayOuts->first()['scan_date'];
                            $isCrossDay = true;
                        }
                    }
                }
            }
            
            // If there's both check-in and check-out, consider it as attended
            if ($jamMasuk && $jamKeluar) {
                $attendanceDates[] = $data['tanggal'];
                
                // For cross-day, also mark the next day as attended (since OUT happened there)
                if ($isCrossDay) {
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $attendanceDates[] = $nextDay;
                }
            }
        }
        
        // Get user's approved absent requests for the period
        $absentDates = DB::table('absent_requests')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('date_from', [$startDate, $endDate])
                      ->orWhereBetween('date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('date_from', '<=', $startDate)
                            ->where('date_to', '>=', $endDate);
                      });
            })
            ->get();
        
        // Create array of all absent dates
        $absentDateArray = [];
        foreach ($absentDates as $absent) {
            $fromDate = new \DateTime($absent->date_from);
            $toDate = new \DateTime($absent->date_to);
            while ($fromDate <= $toDate) {
                $absentDateArray[] = $fromDate->format('Y-m-d');
                $fromDate->modify('+1 day');
            }
        }
        
        $alpaDays = 0;
        $today = date('Y-m-d');
        
        // Check each day in the period
        foreach ($period as $date) {
            // Only count alpa for dates that have already passed (< hari ini, bukan <=)
            if ($date >= $today) {
                continue; // Skip today and future dates
            }
            
            $hasShift = $shifts->has($date);
            $hasAttendance = in_array($date, $attendanceDates);
            $hasAbsent = in_array($date, $absentDateArray);
            
            // Alpa: has shift but no attendance and no absent request
            if ($hasShift && !$hasAttendance && !$hasAbsent) {
                $alpaDays++;
            }
        }
        
        return $alpaDays;
    }

    /**
     * Process smart cross-day attendance - SAMA PERSIS dengan AttendanceReportController
     * Digunakan untuk mendapatkan data attendance detail yang sama dengan Employee Summary
     */
    private function processSmartCrossDayAttendance($data, $allProcessedData) {
        $scans = collect($data['scans'])->sortBy('scan_date');
        $inScans = $scans->where('inoutmode', 1);
        $outScans = $scans->where('inoutmode', 2);
        
        $totalMasuk = $inScans->count();
        $totalKeluar = $outScans->count();
        
        // Ambil scan masuk pertama
        $jamMasuk = $inScans->first()['scan_date'] ?? null;
        $jamKeluar = null;
        $isCrossDay = false;
        
        if ($jamMasuk) {
            // SOLUSI TERBAIK: Logika sederhana dan konsisten dengan multi-outlet support
            
            // 1. Cari OUT scan di hari yang sama
            $sameDayOuts = $outScans->where('scan_date', '>', $jamMasuk);
            
            // 2. Cari OUT scan di hari berikutnya (cross-day)
            $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
            $nextDayKey = $data['user_id'] . '_' . $nextDay;
            $nextDayOuts = collect();
            
            if (isset($allProcessedData[$nextDayKey])) {
                $nextDayScans = collect($allProcessedData[$nextDayKey]['scans'])->sortBy('scan_date');
                $nextDayOuts = $nextDayScans->where('inoutmode', 2);
            }
            
            
            // 3. Tentukan OUT scan yang paling masuk akal - FIXED for multi-outlet
            if ($sameDayOuts->isNotEmpty() && $nextDayOuts->isNotEmpty()) {
                // Ada both same-day dan cross-day OUT scan
                $lastSameDayOut = $sameDayOuts->last()['scan_date'];
                $firstNextDayOut = $nextDayOuts->first()['scan_date'];
                
                // Cek durasi same-day OUT
                $sameDayDuration = strtotime($lastSameDayOut) - strtotime($jamMasuk);
                $outHour = (int)date('H', strtotime($firstNextDayOut));
                
                // Untuk multi-outlet cross-day, prioritas cross-day jika:
                // 1. Same-day OUT terlalu pendek (< 5 jam) ATAU
                // 2. Cross-day OUT di pagi sangat awal (00:00-06:00)
                if ($sameDayDuration < 18000 || ($outHour >= 0 && $outHour <= 6)) {
                    $jamKeluar = $firstNextDayOut;
                    $isCrossDay = true;
                    $totalKeluar = 1;
                    
                    // Hapus scan keluar dari hari berikutnya
                    $allProcessedData[$nextDayKey]['scans'] = $nextDayScans->where('inoutmode', '!=', 2)->values()->toArray();
                    
                } else {
                    $jamKeluar = $lastSameDayOut;
                    $isCrossDay = false;
                }
            } elseif ($sameDayOuts->isNotEmpty()) {
                // Hanya ada same-day OUT scan
                $jamKeluar = $sameDayOuts->last()['scan_date'];
                $isCrossDay = false;
                
                
            } elseif ($nextDayOuts->isNotEmpty()) {
                // Hanya ada cross-day OUT scan
                $firstNextDayOut = $nextDayOuts->first()['scan_date'];
                $outHour = (int)date('H', strtotime($firstNextDayOut));
                
                // Untuk cross-day, hanya gunakan jika di pagi sangat awal (00:00-12:00)
                if ($outHour >= 0 && $outHour <= 12) {
                    $jamKeluar = $firstNextDayOut;
                    $isCrossDay = true;
                    $totalKeluar = 1;
                    
                    
                    // Hapus scan keluar dari hari berikutnya
                    $allProcessedData[$nextDayKey]['scans'] = $nextDayScans->where('inoutmode', '!=', 2)->values()->toArray();
                }
            }
            
        }
        
        
        return [
            'tanggal' => $data['tanggal'],
            'user_id' => $data['user_id'],
            'nama_lengkap' => $data['nama_lengkap'],
            'jam_masuk' => $jamMasuk,
            'jam_keluar' => $jamKeluar,
            'total_masuk' => $totalMasuk,
            'total_keluar' => $totalKeluar,
            'is_cross_day' => $isCrossDay
        ];
    }

    /**
     * Calculate lateness - SAMA PERSIS dengan AttendanceReportController
     */
    private function calculateLateness($jamMasuk, $shiftStart, $isCrossDay) {
        if (!$jamMasuk || !$shiftStart) {
            return 0;
        }
        
        // Ambil jam saja dari jam masuk
        $jamMasukTime = date('H:i:s', strtotime($jamMasuk));
        
        // Konversi ke timestamp untuk perhitungan
        $masukTimestamp = strtotime($jamMasukTime);
        $shiftStartTimestamp = strtotime($shiftStart);
        
        // Hitung selisih dalam detik
        $diffSeconds = $masukTimestamp - $shiftStartTimestamp;
        
        // Konversi ke menit (hanya jika positif)
        $latenessMinutes = $diffSeconds > 0 ? round($diffSeconds / 60) : 0;
        
        return $latenessMinutes;
    }

    /**
     * Calculate early checkout lateness - SAMA PERSIS dengan AttendanceReportController
     */
    private function calculateEarlyCheckoutLateness($jamKeluar, $shiftEnd, $isCrossDay = false) {
        if (!$jamKeluar || !$shiftEnd) {
            return 0;
        }
        
        // Untuk cross-day, tidak ada telat dari early checkout
        if ($isCrossDay) {
            return 0;
        }
        
        // Ambil jam saja dari jam keluar
        $jamKeluarTime = date('H:i:s', strtotime($jamKeluar));
        
        // Konversi ke timestamp untuk perhitungan
        $keluarTimestamp = strtotime($jamKeluarTime);
        $shiftEndTimestamp = strtotime($shiftEnd);
        
        // Hitung selisih dalam detik (negatif jika checkout lebih awal)
        $diffSeconds = $shiftEndTimestamp - $keluarTimestamp;
        
        // Konversi ke menit (hanya jika positif, artinya checkout lebih awal)
        $latenessMinutes = $diffSeconds > 0 ? round($diffSeconds / 60) : 0;
        
        return $latenessMinutes;
    }

    /**
     * Get overtime hours from Extra Off system for a specific user and date range
     * SAMA PERSIS dengan AttendanceReportController
     * 
     * @param int $userId
     * @param string $startDate
     * @param string $endDate
     * @return float Total overtime hours
     */
    private function getExtraOffOvertimeHours($userId, $startDate, $endDate)
    {
        try {
            // Get all overtime transactions from Extra Off system for the date range
            $overtimeTransactions = DB::table('extra_off_transactions')
                ->where('user_id', $userId)
                ->where('source_type', 'overtime_work')
                ->where('transaction_type', 'earned')
                ->where('status', 'approved') // Only count approved transactions
                ->whereBetween('source_date', [$startDate, $endDate])
                ->get();

            $totalHours = 0;

            foreach ($overtimeTransactions as $transaction) {
                // Extract work hours from description
                // Format: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (jam 08:00 - 18:00, 10.45 jam)"
                // or: "Lembur dari kerja tanpa shift di tanggal 2025-10-12 (10.45 jam)"
                $workHours = 0;
                if (preg_match('/\(jam\s+[0-9:]+\s*-\s*[0-9:]+,\s*([0-9.]+)\s*jam\)/', $transaction->description, $matches)) {
                    // New format with time range
                    $workHours = (float) $matches[1];
                } elseif (preg_match('/\(([0-9.]+)\s*jam\)/', $transaction->description, $matches)) {
                    // Old format without time range
                    $workHours = (float) $matches[1];
                }

                $totalHours += $workHours;
            }

            // Round down (bulatkan ke bawah)
            return floor($totalHours);

        } catch (\Exception $e) {
            \Log::error('Error calculating Extra Off overtime hours', [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            
            return 0; // Return 0 if there's an error
        }
    }
}
