<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;
use App\Models\CustomPayrollItem;
use App\Models\EmployeeResignation;
use App\Http\Controllers\AttendanceReportController;
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
        $lbAmount = $request->input('lb_amount', 0); // Nilai L & B total
        $deviasiAmount = $request->input('deviasi_amount', 0); // Nilai Deviasi total
        $cityLedgerAmount = $request->input('city_ledger_amount', 0); // Nilai City Ledger total

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
        $totalMP = 0;
        $totalMPAktif = 0;
        $totalMPResign = 0;

        if ($outletId && $month && $year) {
            // Hitung periode payroll (26 bulan sebelumnya - 25 bulan yang dipilih)
            // SAMA PERSIS dengan Employee Summary
            // Contoh: bulan 12 tahun 2025 = 26 Nov 2025 - 25 Des 2025
            $start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
            $end = date('Y-m-d', strtotime("$year-$month-25"));
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);

            // Ambil data karyawan di outlet tersebut (HANYA yang aktif) - KEMBALIKAN KE LOGIKA SEMULA
            $users = User::where('status', 'A')
                ->where('id_outlet', $outletId)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);

            // Ambil data resignation untuk periode tersebut (status approved dan resignation_date dalam periode)
            // HANYA karyawan yang resign di periode ini yang akan muncul
            $resignations = EmployeeResignation::where('status', 'approved')
                ->where('outlet_id', $outletId)
                ->whereBetween('resignation_date', [$start, $end])
                ->get(['employee_id', 'resignation_date'])
                ->keyBy('employee_id');

            // Tambahkan karyawan yang resign di periode ini ke list users (jika belum ada)
            $resignedEmployeeIds = $resignations->pluck('employee_id')->toArray();
            if (!empty($resignedEmployeeIds)) {
                $existingUserIds = $users->pluck('id')->toArray();
                $newResignedIds = array_diff($resignedEmployeeIds, $existingUserIds);
                if (!empty($newResignedIds)) {
                    $resignedUsers = User::whereIn('id', $newResignedIds)
                        ->where('id_outlet', $outletId)
                        ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);
                    $users = $users->merge($resignedUsers);
                }
            }

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

            // Cek apakah payroll sudah di-generate
            $payrollGenerated = DB::table('payroll_generated')
                ->where('outlet_id', $outletId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();
            
            $payrollGeneratedDetails = collect();
            $payrollGeneratedDetailsFull = collect();
            if ($payrollGenerated) {
                // Ambil payment_method untuk backward compatibility
                $payrollGeneratedDetails = DB::table('payroll_generated_details')
                    ->where('payroll_generated_id', $payrollGenerated->id)
                    ->pluck('payment_method', 'user_id');
                
                // Ambil semua data payroll yang sudah di-generate
                $payrollGeneratedDetailsFull = DB::table('payroll_generated_details')
                    ->where('payroll_generated_id', $payrollGenerated->id)
                    ->get()
                    ->keyBy('user_id');
            }
            
            // Jika payroll sudah di-generate, gunakan data dari payroll_generated_details
            if ($payrollGenerated && $payrollGeneratedDetailsFull->isNotEmpty()) {
                // Ambil data karyawan untuk mapping (include status untuk perhitungan statistik)
                $users = User::whereIn('id', $payrollGeneratedDetailsFull->pluck('user_id'))
                    ->where('id_outlet', $outletId)
                    ->orderBy('nama_lengkap')
                    ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);
                
                // Ambil data resignation untuk periode tersebut
                $resignations = EmployeeResignation::where('status', 'approved')
                    ->where('outlet_id', $outletId)
                    ->whereBetween('resignation_date', [$start, $end])
                    ->get(['employee_id', 'resignation_date'])
                    ->keyBy('employee_id');
                
                // Ambil data custom items untuk periode ini
                $customItems = CustomPayrollItem::forOutlet($outletId)
                    ->forPeriod($month, $year)
                    ->get()
                    ->groupBy('user_id');
                
                // Ambil semua leave types untuk breakdown izin/cuti
                $leaveTypes = DB::table('leave_types')
                    ->orderBy('name')
                    ->get(['id', 'name']);
                
                // Format data dari payroll_generated_details ke format yang dibutuhkan frontend
                foreach ($payrollGeneratedDetailsFull as $userId => $detail) {
                    $user = $users->firstWhere('id', $userId);
                    if (!$user) {
                        continue; // Skip jika user tidak ditemukan
                    }
                    
                    // Ambil custom items dari database (lebih lengkap daripada yang di JSON)
                    $userCustomItems = $customItems->get($user->id, collect());
                    
                    // Decode custom_items dari JSON sebagai fallback
                    $customItemsData = json_decode($detail->custom_items ?? '[]', true) ?? [];
                    $customItemsCollection = collect($customItemsData);
                    
                    // Jika ada custom items dari database, gunakan yang dari database (lebih lengkap)
                    // Jika tidak ada, gunakan yang dari JSON
                    if ($userCustomItems->isNotEmpty()) {
                        $customItemsCollection = $userCustomItems;
                    }
                    
                    // Pisahkan custom items berdasarkan gajian_type
                    $customItemsGajian1 = $customItemsCollection->filter(function($item) {
                        $gajianType = is_object($item) ? ($item->gajian_type ?? null) : ($item['gajian_type'] ?? null);
                        return !isset($gajianType) || $gajianType === null || $gajianType === 'gajian1';
                    });
                    $customItemsGajian2 = $customItemsCollection->filter(function($item) {
                        $gajianType = is_object($item) ? ($item->gajian_type ?? null) : ($item['gajian_type'] ?? null);
                        return $gajianType === 'gajian2';
                    });
                    
                    $customEarningsGajian1 = $customItemsGajian1->filter(function($item) {
                        $itemType = is_object($item) ? ($item->item_type ?? null) : ($item['item_type'] ?? null);
                        return $itemType === 'earn';
                    })->sum(function($item) {
                        return is_object($item) ? ($item->item_amount ?? 0) : ($item['item_amount'] ?? 0);
                    });
                    $customDeductionsGajian1 = $customItemsGajian1->filter(function($item) {
                        $itemType = is_object($item) ? ($item->item_type ?? null) : ($item['item_type'] ?? null);
                        return $itemType === 'deduction';
                    })->sum(function($item) {
                        return is_object($item) ? ($item->item_amount ?? 0) : ($item['item_amount'] ?? 0);
                    });
                    $customEarningsGajian2 = $customItemsGajian2->filter(function($item) {
                        $itemType = is_object($item) ? ($item->item_type ?? null) : ($item['item_type'] ?? null);
                        return $itemType === 'earn';
                    })->sum(function($item) {
                        return is_object($item) ? ($item->item_amount ?? 0) : ($item['item_amount'] ?? 0);
                    });
                    $customDeductionsGajian2 = $customItemsGajian2->filter(function($item) {
                        $itemType = is_object($item) ? ($item->item_type ?? null) : ($item['item_type'] ?? null);
                        return $itemType === 'deduction';
                    })->sum(function($item) {
                        return is_object($item) ? ($item->item_amount ?? 0) : ($item['item_amount'] ?? 0);
                    });
                    
                    // Decode leave_data
                    $leaveData = json_decode($detail->leave_data ?? '[]', true) ?? [];
                    
                    // Extract breakdown dari leaveData
                    $izinCutiBreakdown = [];
                    $totalIzinCuti = 0;
                    foreach ($leaveData as $key => $value) {
                        if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                            $izinCutiBreakdown[$key] = $value;
                            $totalIzinCuti += $value;
                        }
                    }
                    
                    // Cek apakah karyawan baru
                    $isNewEmployee = false;
                    if ($user->tanggal_masuk) {
                        $tanggalMasuk = Carbon::parse($user->tanggal_masuk);
                        $isNewEmployee = $tanggalMasuk->greaterThanOrEqualTo($startDate) && $tanggalMasuk->lessThanOrEqualTo($endDate);
                    }
                    
                    // Cek resignation
                    $resignation = $resignations->get($user->id);
                    $resignationDate = null;
                    if ($resignation && $resignation->resignation_date) {
                        $resignDate = Carbon::parse($resignation->resignation_date);
                        if ($resignDate->between($startDate, $endDate)) {
                            $resignationDate = $resignation->resignation_date->format('Y-m-d');
                        }
                    }
                    
                    // Hitung total gaji dari data yang sudah di-generate
                    $totalGaji = $detail->total_gaji ?? 0;
                    
                    // Format data sesuai dengan struktur yang dibutuhkan frontend
                    $payrollDataItem = [
                        'user_id' => $user->id,
                        'nik' => $detail->nik ?? $user->nik,
                        'nama_lengkap' => $detail->nama_lengkap ?? $user->nama_lengkap,
                        'no_rekening' => $detail->no_rekening ?? $user->no_rekening ?? null,
                        'tanggal_masuk' => $user->tanggal_masuk ?? null,
                        'is_new_employee' => $isNewEmployee,
                        'resignation_date' => $resignationDate,
                        'jabatan' => $detail->jabatan ?? null,
                        'divisi' => $detail->divisi ?? null,
                        'point' => $detail->point ?? 0,
                        'gaji_pokok' => round($detail->gaji_pokok ?? 0),
                        'tunjangan' => round($detail->tunjangan ?? 0),
                        'total_telat' => $detail->total_telat ?? 0,
                        'total_lembur' => $detail->total_lembur ?? 0,
                        'nominal_lembur_per_jam' => $detail->nominal_lembur_per_jam ?? 0,
                        'gaji_lembur' => round($detail->gaji_lembur ?? 0),
                        'nominal_uang_makan' => $detail->nominal_uang_makan ?? 0,
                        'uang_makan' => round($detail->uang_makan ?? 0),
                        'service_charge_by_point' => round($detail->service_charge_by_point ?? 0),
                        'service_charge_pro_rate' => round($detail->service_charge_pro_rate ?? 0),
                        'service_charge' => round($detail->service_charge ?? 0),
                        'bpjs_jkn' => round($detail->bpjs_jkn ?? 0),
                        'bpjs_tk' => round($detail->bpjs_tk ?? 0),
                        'lb_total' => round($detail->lb_total ?? 0),
                        'deviasi_total' => round($detail->deviasi_total ?? 0),
                        'city_ledger_total' => round($detail->city_ledger_total ?? 0),
                        'ph_bonus' => round($detail->ph_bonus ?? 0),
                        'custom_earnings' => round($customEarningsGajian1),
                        'custom_deductions' => round($customDeductionsGajian1),
                        'custom_earnings_gajian1' => round($customEarningsGajian1),
                        'custom_deductions_gajian1' => round($customDeductionsGajian1),
                        'custom_items_gajian1' => $customItemsGajian1,
                        'custom_earnings_gajian2' => round($customEarningsGajian2),
                        'custom_deductions_gajian2' => round($customDeductionsGajian2),
                        'custom_items_gajian2' => $customItemsGajian2,
                        'custom_items' => $customItemsCollection, // Untuk backward compatibility
                        'gaji_per_menit' => round($detail->gaji_per_menit ?? 500, 2),
                        'potongan_telat' => round($detail->potongan_telat ?? 0),
                        'total_alpha' => $detail->total_alpha ?? 0,
                        'potongan_alpha' => round($detail->potongan_alpha ?? 0),
                        'potongan_unpaid_leave' => round($detail->potongan_unpaid_leave ?? 0),
                        'total_gaji' => round($totalGaji),
                        'hari_kerja' => $detail->hari_kerja ?? 0,
                        'total_izin_cuti' => $totalIzinCuti,
                        'izin_cuti_breakdown' => $izinCutiBreakdown,
                        'extra_off_days' => isset($leaveData['extra_off_days']) ? $leaveData['extra_off_days'] : 0,
                        'leave_data' => $leaveData,
                        'periode' => $detail->periode ?? ($startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y')),
                        'payment_method' => $detail->payment_method ?? 'transfer',
                    ];
                    
                    // Add dynamic leave data directly to payrollData item
                    foreach ($leaveData as $key => $value) {
                        if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                            $payrollDataItem[$key] = $value;
                        }
                    }
                    
                    $payrollData->push($payrollDataItem);
                }
                
                // Hitung statistik karyawan dari data yang SEBENARNYA masuk ke payrollData (bukan dari $users)
                // Ambil user_id dari payrollData untuk memastikan sinkron dengan data di tabel
                $userIdsInPayrollData = $payrollData->pluck('user_id')->unique();
                $usersInPayrollData = $users->whereIn('id', $userIdsInPayrollData);
                
                $totalMP = $usersInPayrollData->count(); // Total semua karyawan yang ada di payrollData
                // Hitung total MP aktif dari users yang ada di payrollData (status = 'A')
                $totalMPAktif = $usersInPayrollData->where('status', 'A')->count();
                // Hitung total MP resign dari users yang ada di payrollData dan ada di resignations
                $resignedIdsInPayrollData = $resignations->pluck('employee_id')->toArray();
                $totalMPResign = $usersInPayrollData->whereIn('id', $resignedIdsInPayrollData)->count();
                
                // Return early dengan data dari payroll_generated_details
                return Inertia::render('Payroll/Report', [
                    'outlets' => $outlets,
                    'months' => $months,
                    'years' => $years,
                    'payrollData' => $payrollData,
                    'leaveTypes' => $leaveTypes,
                    'statistics' => [
                        'total_mp' => $totalMP ?? 0,
                        'total_mp_aktif' => $totalMPAktif ?? 0,
                        'total_mp_resign' => $totalMPResign ?? 0,
                    ],
                    'filter' => [
                        'outlet_id' => $outletId,
                        'month' => $month,
                        'year' => $year,
                        'service_charge' => $serviceCharge,
                        'lb_amount' => $lbAmount,
                        'deviasi_amount' => $deviasiAmount,
                        'city_ledger_amount' => $cityLedgerAmount,
                    ],
                ]);
            }

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
            // Ambil data absensi dari user yang aktif (status 'A') DAN user yang resign di periode ini
            $validUserIds = $users->pluck('id')->toArray();
            
            $chunkSize = 5000;
            $rawData = collect();
            
            // Query absensi - SAMA PERSIS dengan employeeSummary di AttendanceReportController
            // TIDAK filter berdasarkan status, hanya filter berdasarkan outlet_id
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
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$start, $end]);
            
            // Apply filter outlet - SAMA PERSIS dengan employeeSummary
            if (!empty($outletId)) {
                $sub->where('u.id_outlet', $outletId);
            }
            
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
                ->select('u.id', 'u.nik', 'u.no_rekening', 'u.tanggal_masuk', 'j.nama_jabatan as jabatan')
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
                    'deviasi' => 0,
                    'city_ledger' => 0,
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

                // Hitung total alpha menggunakan method yang sama persis dengan AttendanceReportController
                // GUNAKAN METHOD calculateAlpaDays yang sudah COPY PERSIS dari AttendanceReportController
                // SAMA PERSIS dengan employeeSummary: kirim null untuk outlet_id
                $totalAlpha = $this->calculateAlpaDays($userId, null, $start, $end);
                
                // Hitung breakdown izin/cuti per kategori menggunakan calculateLeaveData (sama seperti Employee Summary)
                $leaveData = $this->calculateLeaveData($userId, $start, $end);
                
                // Extract breakdown dari leaveData - SAMA PERSIS dengan Employee Summary
                // Langsung ambil semua key yang berakhiran '_days' kecuali 'extra_off_days'
                $izinCutiBreakdown = [];
                $totalIzinCuti = 0;
                foreach ($leaveData as $key => $value) {
                    if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                        $izinCutiBreakdown[$key] = $value;
                        $totalIzinCuti += $value;
                    }
                }
                
                // Hitung PH Bonus (Public Holiday bonus only, not extra_off)
                $phBonus = $this->calculatePHBonus($userId, $start, $end);
                
                // Debug: Log leave data
                \Log::info('Payroll - Leave data extracted', [
                    'user_id' => $userId,
                    'leave_data' => $leaveData,
                    'izin_cuti_breakdown' => $izinCutiBreakdown,
                    'extra_off_days' => $leaveData['extra_off_days'] ?? 0
                ]);

                // Ambil point dari level melalui jabatan
                $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
                $userPoint = $userLevel ? ($levelPoints[$userLevel] ?? 0) : 0;

                // Cek apakah karyawan baru (tanggal_masuk dalam periode payroll) - HARUS DILAKUKAN DI STEP 1
                $isNewEmployee = false;
                $hariKerjaKaryawanBaru = $hariKerja; // Default: hari kerja normal
                if ($user->tanggal_masuk) {
                    $tanggalMasuk = Carbon::parse($user->tanggal_masuk);
                    $isNewEmployee = $tanggalMasuk->greaterThanOrEqualTo($startDate) && $tanggalMasuk->lessThanOrEqualTo($endDate);
                    
                    // Jika karyawan baru, hitung hari kerja dari tanggal masuk sampai akhir periode
                    if ($isNewEmployee) {
                        // Hitung hari kerja dari tanggal masuk sampai akhir periode (hitung hari kalender)
                        $hariKerjaKaryawanBaru = $tanggalMasuk->diffInDays($endDate) + 1; // +1 untuk include tanggal masuk dan tanggal akhir
                    }
                }
                
                // Gunakan hari kerja yang sesuai (hariKerjaKaryawanBaru untuk karyawan baru, hariKerja untuk karyawan lama)
                // Ini penting untuk perhitungan service charge prorate yang konsisten
                $hariKerjaUntukServiceCharge = $isNewEmployee ? $hariKerjaKaryawanBaru : $hariKerja;

                // Simpan data user untuk perhitungan service charge
                $userData[$user->id] = [
                    'user' => $user,
                    'masterData' => $masterData,
                    'employeeRows' => $employeeRows, // Simpan employeeRows untuk digunakan nanti
                    'totalTelat' => $totalTelat,
                    'totalLembur' => $totalLembur,
                    'hariKerja' => $hariKerja, // Hari kerja aktual (jumlah hari bekerja)
                    'hariKerjaKaryawanBaru' => $hariKerjaKaryawanBaru, // Hari kerja untuk karyawan baru
                    'hariKerjaUntukServiceCharge' => $hariKerjaUntukServiceCharge, // Hari kerja yang digunakan untuk service charge
                    'isNewEmployee' => $isNewEmployee, // Flag apakah karyawan baru
                    'totalAlpha' => $totalAlpha,
                    'totalIzinCuti' => $totalIzinCuti,
                    'izinCutiBreakdown' => $izinCutiBreakdown,
                    'leaveData' => $leaveData, // Simpan leaveData untuk digunakan di Step 4
                    'userPoint' => $userPoint,
                ];
            }

            // Step 2: Hitung total untuk service charge (hanya untuk user yang sc = 1)
            // PENTING: Gunakan hariKerjaUntukServiceCharge untuk konsistensi dengan perhitungan per user
            $totalPointHariKerja = 0; // Sum(point × hari kerja) untuk semua user yang sc = 1
            $totalHariKerja = 0; // Sum(hari kerja) untuk semua user yang sc = 1
            
            foreach ($userData as $data) {
                if ($data['masterData']->sc == 1) {
                    // Gunakan hariKerjaUntukServiceCharge untuk konsistensi
                    $hariKerjaSC = $data['hariKerjaUntukServiceCharge'] ?? $data['hariKerja'];
                    $totalPointHariKerja += $data['userPoint'] * $hariKerjaSC;
                    $totalHariKerja += $hariKerjaSC;
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

            // Step 2b: Hitung total untuk L & B (hanya untuk user yang lb = 1)
            // Menggunakan (point × hari kerja) seperti service charge by point
            $totalPointHariKerjaLB = 0;
            foreach ($userData as $data) {
                if ($data['masterData']->lb == 1) {
                    $totalPointHariKerjaLB += $data['userPoint'] * $data['hariKerja'];
                }
            }

            // Step 3b: Hitung rate L & B (100% by point × hari kerja)
            $rateLBByPoint = 0;

            if ($lbAmount > 0 && $totalPointHariKerjaLB > 0) {
                $rateLBByPoint = $lbAmount / $totalPointHariKerjaLB;
            }

            // Step 2c: Hitung total untuk Deviasi (hanya untuk user yang deviasi = 1)
            // Menggunakan (point × hari kerja) seperti service charge by point
            $totalPointHariKerjaDeviasi = 0;
            foreach ($userData as $data) {
                if ($data['masterData']->deviasi == 1) {
                    $totalPointHariKerjaDeviasi += $data['userPoint'] * $data['hariKerja'];
                }
            }

            // Step 3c: Hitung rate Deviasi (100% by point × hari kerja)
            $rateDeviasiByPoint = 0;

            if ($deviasiAmount > 0 && $totalPointHariKerjaDeviasi > 0) {
                $rateDeviasiByPoint = $deviasiAmount / $totalPointHariKerjaDeviasi;
            }

            // Step 2d: Hitung total untuk City Ledger (hanya untuk user yang city_ledger = 1)
            // Menggunakan (point × hari kerja) seperti service charge by point
            $totalPointHariKerjaCityLedger = 0;
            foreach ($userData as $data) {
                if ($data['masterData']->city_ledger == 1) {
                    $totalPointHariKerjaCityLedger += $data['userPoint'] * $data['hariKerja'];
                }
            }

            // Step 3d: Hitung rate City Ledger (100% by point × hari kerja)
            $rateCityLedgerByPoint = 0;

            if ($cityLedgerAmount > 0 && $totalPointHariKerjaCityLedger > 0) {
                $rateCityLedgerByPoint = $cityLedgerAmount / $totalPointHariKerjaCityLedger;
            }

            // Step 4: Hitung service charge per user dan total gaji
            foreach ($userData as $userId => $data) {
                $user = $data['user'];
                $masterData = $data['masterData'];
                $employeeRows = $data['employeeRows']; // Gunakan employeeRows dari Employee Summary
                $totalTelat = $data['totalTelat'];
                $totalLembur = $data['totalLembur'];
                $hariKerja = $data['hariKerja']; // Hari kerja aktual (jumlah hari bekerja)
                $hariKerjaKaryawanBaru = $data['hariKerjaKaryawanBaru'] ?? $hariKerja; // Hari kerja untuk karyawan baru
                $hariKerjaUntukServiceCharge = $data['hariKerjaUntukServiceCharge'] ?? $hariKerja; // Hari kerja yang digunakan untuk service charge
                $isNewEmployee = $data['isNewEmployee'] ?? false; // Flag apakah karyawan baru
                $userPoint = $data['userPoint'];
                $totalAlpha = $data['totalAlpha'] ?? 0; // Ambil totalAlpha dari userData
                $totalIzinCuti = $data['totalIzinCuti'] ?? 0; // Ambil totalIzinCuti dari userData
                $leaveData = $data['leaveData'] ?? []; // Ambil leaveData dari userData, default ke array kosong jika tidak ada
                $izinCutiBreakdown = $data['izinCutiBreakdown'] ?? []; // Ambil izinCutiBreakdown dari userData, default ke array kosong jika tidak ada
                
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
                // PENTING: Gunakan hariKerjaUntukServiceCharge yang sudah dihitung di Step 1 untuk konsistensi
                $serviceChargeByPointAmount = 0;
                $serviceChargeProRateAmount = 0;
                $serviceChargeTotal = 0;
                
                if ($masterData->sc == 1 && $serviceCharge > 0) {
                    // Service charge by point = rate × (point × hari kerja)
                    // Untuk karyawan baru, gunakan hariKerjaKaryawanBaru untuk konsistensi dengan gaji pokok
                    $serviceChargeByPointAmount = $rateByPoint * ($userPoint * $hariKerjaUntukServiceCharge);
                    
                    // Service charge pro rate = rate × hari kerja
                    // Untuk karyawan baru, gunakan hariKerjaKaryawanBaru untuk konsistensi dengan gaji pokok
                    $serviceChargeProRateAmount = $rateProRate * $hariKerjaUntukServiceCharge;
                    
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
                        'hari_kerja_untuk_service_charge' => $hariKerjaUntukServiceCharge,
                        'is_new_employee' => $isNewEmployee,
                        'user_point' => $userPoint,
                        'reason' => $masterData->sc != 1 ? 'sc not enabled' : 'service_charge input is 0'
                    ]);
                }
                
                // Hitung gaji pokok dan tunjangan (pro rate untuk karyawan baru)
                // PENTING: Gunakan hari kerja yang sama dengan service charge prorate (proporsi yang sama)
                // Service charge prorate menggunakan: rate × hari kerja untuk service charge
                // Dimana rate = total service charge prorate / total hari kerja semua karyawan
                // Jadi untuk gaji pokok dan tunjangan, gunakan proporsi yang sama: (hari kerja untuk service charge / total hari kerja standar)
                $gajiPokokFinal = $masterData->gaji;
                $tunjanganFinal = $masterData->tunjangan;
                
                if ($isNewEmployee && $hariKerjaUntukServiceCharge > 0) {
                    // Hitung total hari kerja standar dalam periode payroll (dari tanggal 26 bulan sebelumnya sampai 25 bulan yang dipilih)
                    // Ini adalah total hari kalender dalam periode, bukan total hari kerja karyawan
                    $totalHariKalenderPeriode = $startDate->diffInDays($endDate) + 1; // +1 untuk include tanggal awal dan akhir
                    
                    // Pro rate menggunakan proporsi yang sama dengan service charge prorate
                    // Gaji pokok prorate = gaji pokok × (hari kerja untuk service charge / total hari kalender dalam periode)
                    // Ini sama dengan proporsi yang digunakan service charge prorate
                    $gajiPokokFinal = $masterData->gaji * ($hariKerjaUntukServiceCharge / $totalHariKalenderPeriode);
                    // Tunjangan prorate = tunjangan × (hari kerja untuk service charge / total hari kalender dalam periode)
                    $tunjanganFinal = $masterData->tunjangan * ($hariKerjaUntukServiceCharge / $totalHariKalenderPeriode);
                    
                    \Log::info('Karyawan baru - Pro rate calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'tanggal_masuk' => $user->tanggal_masuk,
                        'hari_kerja_karyawan_baru' => $hariKerjaKaryawanBaru,
                        'hari_kerja_untuk_service_charge' => $hariKerjaUntukServiceCharge,
                        'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                        'gaji_pokok_original' => $masterData->gaji,
                        'tunjangan_original' => $masterData->tunjangan,
                        'gaji_pokok_pro_rate' => $gajiPokokFinal,
                        'tunjangan_pro_rate' => $tunjanganFinal,
                        'formula_gaji' => "{$masterData->gaji} × ({$hariKerjaUntukServiceCharge} / {$totalHariKalenderPeriode}) = {$gajiPokokFinal}",
                        'formula_tunjangan' => "{$masterData->tunjangan} × ({$hariKerjaUntukServiceCharge} / {$totalHariKalenderPeriode}) = {$tunjanganFinal}"
                    ]);
                }

                // Hitung potongan alpha: 20% dari (gaji pokok + tunjangan) × total hari alpha
                // Gunakan gaji pokok dan tunjangan yang sudah di-pro rate untuk karyawan baru
                $potonganAlpha = 0;
                if ($totalAlpha > 0) {
                    $gajiPokokTunjangan = $gajiPokokFinal + $tunjanganFinal;
                    $potonganAlpha = ($gajiPokokTunjangan * 0.20) * $totalAlpha;
                    
                    // Debug logging untuk perhitungan potongan alpha
                    \Log::info('Potongan alpha calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'gaji_pokok' => $masterData->gaji,
                        'tunjangan' => $masterData->tunjangan,
                        'gaji_pokok_tunjangan' => $gajiPokokTunjangan,
                        'total_alpha' => $totalAlpha,
                        'potongan_alpha' => $potonganAlpha,
                        'calculation_formula' => "({$gajiPokokTunjangan} × 20%) × {$totalAlpha} hari = {$potonganAlpha}"
                    ]);
                }

                // Hitung potongan unpaid leave: (gaji pokok + tunjangan) / 26 × jumlah unpaid leave
                // Gunakan gaji pokok dan tunjangan yang sudah di-pro rate untuk karyawan baru
                $potonganUnpaidLeave = 0;
                $unpaidLeaveDays = isset($leaveData['unpaid_leave_days']) ? $leaveData['unpaid_leave_days'] : 0;
                if ($unpaidLeaveDays > 0) {
                    $gajiPokokTunjangan = $gajiPokokFinal + $tunjanganFinal;
                    $gajiPerHari = $gajiPokokTunjangan / 26; // Pro rate per hari kerja
                    $potonganUnpaidLeave = $gajiPerHari * $unpaidLeaveDays;
                    
                    // Debug logging untuk perhitungan potongan unpaid leave
                    \Log::info('Potongan unpaid leave calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'gaji_pokok' => $masterData->gaji,
                        'tunjangan' => $masterData->tunjangan,
                        'gaji_pokok_tunjangan' => $gajiPokokTunjangan,
                        'gaji_per_hari' => $gajiPerHari,
                        'unpaid_leave_days' => $unpaidLeaveDays,
                        'potongan_unpaid_leave' => $potonganUnpaidLeave,
                        'calculation_formula' => "({$gajiPokokTunjangan} / 26) × {$unpaidLeaveDays} hari = {$potonganUnpaidLeave}"
                    ]);
                }

            // Hitung L & B (By Point × Hari Kerja) jika enabled
            // Menggunakan (point × hari kerja) seperti service charge by point
            $lbByPointAmount = 0;
            $lbTotal = 0;
            
            if ($masterData->lb == 1 && $lbAmount > 0) {
                $lbByPointAmount = $rateLBByPoint * ($userPoint * $hariKerja);
                $lbTotal = $lbByPointAmount;
            }

            // Hitung Deviasi (By Point × Hari Kerja) jika enabled
            // Menggunakan (point × hari kerja) seperti service charge by point
            $deviasiByPointAmount = 0;
            $deviasiTotal = 0;
            
            if ($masterData->deviasi == 1 && $deviasiAmount > 0) {
                $deviasiByPointAmount = $rateDeviasiByPoint * ($userPoint * $hariKerja);
                $deviasiTotal = $deviasiByPointAmount;
            }

            // Hitung City Ledger (By Point × Hari Kerja) jika enabled
            // Menggunakan (point × hari kerja) seperti service charge by point
            $cityLedgerByPointAmount = 0;
            $cityLedgerTotal = 0;
            
            if ($masterData->city_ledger == 1 && $cityLedgerAmount > 0) {
                $cityLedgerByPointAmount = $rateCityLedgerByPoint * ($userPoint * $hariKerja);
                $cityLedgerTotal = $cityLedgerByPointAmount;
            }

                // Hitung custom earnings dan deductions - PISAHKAN BERDASARKAN GAJIAN TYPE
                $userCustomItems = $customItems->get($user->id, collect());
                
                // Custom items untuk Gajian 1 (gaji akhir bulan)
                $userCustomItemsGajian1 = $userCustomItems->where('gajian_type', 'gajian1');
                $customEarningsGajian1 = $userCustomItemsGajian1->where('item_type', 'earn')->sum('item_amount');
                $customDeductionsGajian1 = $userCustomItemsGajian1->where('item_type', 'deduction')->sum('item_amount');
                
                // Custom items untuk Gajian 2 (gaji tanggal 8)
                $userCustomItemsGajian2 = $userCustomItems->where('gajian_type', 'gajian2');
                $customEarningsGajian2 = $userCustomItemsGajian2->where('item_type', 'earn')->sum('item_amount');
                $customDeductionsGajian2 = $userCustomItemsGajian2->where('item_type', 'deduction')->sum('item_amount');
                
                // Untuk backward compatibility, jika gajian_type null atau tidak ada, default ke gajian1
                $userCustomItemsDefault = $userCustomItems->whereNull('gajian_type')->where(function($item) {
                    return !isset($item->gajian_type);
                });
                $customEarningsDefault = $userCustomItemsDefault->where('item_type', 'earn')->sum('item_amount');
                $customDeductionsDefault = $userCustomItemsDefault->where('item_type', 'deduction')->sum('item_amount');
                
                // Total custom untuk gajian1 (termasuk yang default/null)
                $customEarningsGajian1 += $customEarningsDefault;
                $customDeductionsGajian1 += $customDeductionsDefault;
                
                // Total custom untuk perhitungan total gaji (hanya gajian1)
                $customEarnings = $customEarningsGajian1;
                $customDeductions = $customDeductionsGajian1;

                // Hitung total gaji (service charge ditambahkan sebagai earning, L&B, Deviasi, City Ledger, potongan alpha dan unpaid leave sebagai deduction)
                // PH Bonus akan ditambahkan di gajian2, tidak dihitung di total gaji utama
                // Custom items gajian2 TIDAK masuk ke total gaji utama, hanya gajian1
                // Gunakan gaji pokok dan tunjangan yang sudah di-pro rate untuk karyawan baru
                $totalGaji = $gajiPokokFinal + $tunjanganFinal + $gajiLembur + $uangMakan + $serviceChargeTotal + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $lbTotal - $deviasiTotal - $cityLedgerTotal - $customDeductions - $potonganAlpha - $potonganUnpaidLeave;
                
                // Cek apakah user resign di periode ini SAJA
                // Hanya set resignation_date jika karyawan benar-benar resign di periode payroll yang dipilih
                $resignation = $resignations->get($user->id);
                $resignationDate = null;
                if ($resignation && $resignation->resignation_date) {
                    // Pastikan resignation_date benar-benar dalam periode
                    $resignDate = Carbon::parse($resignation->resignation_date);
                    if ($resignDate->between($startDate, $endDate)) {
                        $resignationDate = $resignation->resignation_date->format('Y-m-d');
                    }
                }
                
                $payrollDataItem = [
                    'user_id' => $user->id,
                    'nik' => $user->nik,
                    'nama_lengkap' => $user->nama_lengkap,
                    'no_rekening' => $user->no_rekening ?? null,
                    'tanggal_masuk' => $user->tanggal_masuk ?? null,
                    'is_new_employee' => $isNewEmployee,
                    'resignation_date' => $resignationDate,
                    'jabatan' => $jabatans[$user->id_jabatan] ?? '-',
                    'divisi' => $divisions[$user->division_id] ?? '-',
                    'point' => $userPoint,
                    'gaji_pokok' => round($gajiPokokFinal),
                    'tunjangan' => round($tunjanganFinal),
                    'total_telat' => $totalTelat,
                    'total_lembur' => $totalLembur,
                    'nominal_lembur_per_jam' => $divisiNominalLembur[$user->division_id] ?? 0,
                    'gaji_lembur' => round($gajiLembur),
                    'nominal_uang_makan' => $divisiNominalUangMakan[$user->division_id] ?? 0,
                    'uang_makan' => round($uangMakan),
                    'service_charge_by_point' => round($serviceChargeByPointAmount),
                    'service_charge_pro_rate' => round($serviceChargeProRateAmount),
                    'service_charge' => round($serviceChargeTotal),
                    'lb_by_point' => round($lbByPointAmount),
                    'lb_total' => round($lbTotal),
                    'deviasi_by_point' => round($deviasiByPointAmount),
                    'deviasi_total' => round($deviasiTotal),
                    'city_ledger_by_point' => round($cityLedgerByPointAmount),
                    'city_ledger_total' => round($cityLedgerTotal),
                    'bpjs_jkn' => round($bpjsJKN),
                    'bpjs_tk' => round($bpjsTK),
                    'custom_earnings' => round($customEarnings),
                    'custom_deductions' => round($customDeductions),
                    'custom_items' => $userCustomItems,
                    'custom_earnings_gajian1' => round($customEarningsGajian1),
                    'custom_deductions_gajian1' => round($customDeductionsGajian1),
                    'custom_items_gajian1' => $userCustomItemsGajian1->merge($userCustomItemsDefault),
                    'custom_earnings_gajian2' => round($customEarningsGajian2),
                    'custom_deductions_gajian2' => round($customDeductionsGajian2),
                    'custom_items_gajian2' => $userCustomItemsGajian2,
                    'gaji_per_menit' => round($gajiPerMenit, 2),
                    'potongan_telat' => round($potonganTelat),
                    'potongan_alpha' => round($potonganAlpha),
                    'potongan_unpaid_leave' => round($potonganUnpaidLeave),
                    'total_gaji' => round($totalGaji),
                    'hari_kerja' => $hariKerja,
                    'total_alpha' => $totalAlpha,
                    'total_izin_cuti' => $totalIzinCuti,
                    'izin_cuti_breakdown' => $izinCutiBreakdown,
                    'extra_off_days' => isset($leaveData['extra_off_days']) ? $leaveData['extra_off_days'] : 0, // SAMA PERSIS dengan Employee Summary
                    'ph_bonus' => round($phBonus), // PH Bonus (hanya bonus, bukan extra_off)
                    'leave_data' => $leaveData, // Simpan leave_data untuk generate payroll
                    'periode' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                    'master_data' => $masterData,
                    'payment_method' => $payrollGeneratedDetails->get($user->id, 'transfer'), // Default transfer jika belum di-generate
                ];
                
                // Add dynamic leave data directly to payrollData item - SAMA PERSIS dengan Employee Summary
                // Di Employee Summary (line 2101-2105), semua key dari $leaveData yang berakhiran '_days' kecuali 'extra_off_days' ditambahkan langsung
                // Tapi extra_off_days sudah ditambahkan di line 549, jadi kita hanya tambahkan yang lain
                foreach ($leaveData as $key => $value) {
                    if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                        $payrollDataItem[$key] = $value;
                    }
                }
                
                // Pastikan extra_off_days juga ditambahkan langsung ke payrollDataItem (setelah loop untuk memastikan tidak tertimpa)
                // Ini penting karena frontend mencari item['extra_off_days'] langsung
                // extra_off_days sudah ditambahkan di line 549, tapi kita pastikan lagi di sini
                $payrollDataItem['extra_off_days'] = isset($leaveData['extra_off_days']) ? $leaveData['extra_off_days'] : 0;
                
                // Debug: Log final payrollDataItem untuk memastikan extra_off_days ada
                $allLeaveKeys = [];
                foreach ($payrollDataItem as $key => $value) {
                    if (strpos($key, '_days') !== false) {
                        $allLeaveKeys[$key] = $value;
                    }
                }
                \Log::info('Payroll - Final payrollDataItem', [
                    'user_id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'extra_off_days' => $payrollDataItem['extra_off_days'] ?? 'NOT SET',
                    'sick_leave_days' => $payrollDataItem['sick_leave_days'] ?? 'NOT SET',
                    'all_leave_keys' => $allLeaveKeys,
                    'has_extra_off_days' => isset($payrollDataItem['extra_off_days']),
                    'extra_off_days_value' => $payrollDataItem['extra_off_days'] ?? null
                ]);
                
                $payrollData->push($payrollDataItem);
            }
            
            // Hitung statistik karyawan dari data yang SEBENARNYA masuk ke payrollData (bukan dari $users)
            // Ambil user_id dari payrollData untuk memastikan sinkron dengan data di tabel
            $userIdsInPayrollData = $payrollData->pluck('user_id')->unique();
            $usersInPayrollData = $users->whereIn('id', $userIdsInPayrollData);
            
            $totalMP = $usersInPayrollData->count(); // Total semua karyawan yang ada di payrollData
            // Hitung total MP aktif dari users yang ada di payrollData (status = 'A')
            $totalMPAktif = $usersInPayrollData->where('status', 'A')->count();
            // Hitung total MP resign dari users yang ada di payrollData dan ada di resignations
            $totalMPResign = $usersInPayrollData->whereIn('id', $resignedEmployeeIds)->count();
        } else {
            $totalMP = 0;
            $totalMPAktif = 0;
            $totalMPResign = 0;
        }

        return Inertia::render('Payroll/Report', [
            'outlets' => $outlets,
            'months' => $months,
            'years' => $years,
            'payrollData' => $payrollData,
            'leaveTypes' => $leaveTypes,
            'statistics' => [
                'total_mp' => $totalMP ?? 0,
                'total_mp_aktif' => $totalMPAktif ?? 0,
                'total_mp_resign' => $totalMPResign ?? 0,
            ],
            'filter' => [
                'outlet_id' => $outletId,
                'month' => $month,
                'year' => $year,
                'service_charge' => $serviceCharge,
                'lb_amount' => $lbAmount,
                'deviasi_amount' => $deviasiAmount,
                'city_ledger_amount' => $cityLedgerAmount,
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
        $lbAmount = $request->input('lb_amount', 0);
        $deviasiAmount = $request->input('deviasi_amount', 0);
        $cityLedgerAmount = $request->input('city_ledger_amount', 0);

        if (!$outletId || !$month || !$year) {
            return response()->json(['error' => 'Parameter tidak lengkap'], 400);
        }

        // Hitung periode payroll (26 bulan sebelumnya - 25 bulan yang dipilih)
        // SAMA PERSIS dengan index() dan Employee Summary
        $start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
        $end = date('Y-m-d', strtotime("$year-$month-25"));
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        // Ambil data seperti di index() - SAMA PERSIS
        $users = User::where('status', 'A')
            ->where('id_outlet', $outletId)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk']);

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

        // ========== GUNAKAN QUERY DAN PROSES YANG SAMA PERSIS DENGAN INDEX() DAN EMPLOYEE SUMMARY ==========
        // Query data absensi - SAMA PERSIS dengan index() dan Employee Summary
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

        // Group by employee - SAMA PERSIS dengan Employee Summary
        $employeeGroups = $rows->groupBy('user_id');

        // Step 1: Hitung semua data dasar untuk semua user - GUNAKAN DATA DARI EMPLOYEE GROUPS
        $userData = [];
        foreach ($employeeGroups as $userId => $employeeRows) {
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
                'deviasi' => 0,
                'city_ledger' => 0,
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
            $totalAlpha = $this->calculateAlpaDays($userId, $outletId, $start, $end);
            
            // Hitung breakdown izin/cuti per kategori menggunakan calculateLeaveData (sama seperti Employee Summary)
            $leaveData = $this->calculateLeaveData($userId, $start, $end);
            
            // Extract breakdown dari leaveData - SAMA PERSIS dengan Employee Summary
            $izinCutiBreakdown = [];
            $totalIzinCuti = 0;
            foreach ($leaveData as $key => $value) {
                if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                    $izinCutiBreakdown[$key] = $value;
                    $totalIzinCuti += $value;
                }
            }
            
            // Hitung PH Bonus (Public Holiday bonus only, not extra_off)
            $phBonus = $this->calculatePHBonus($userId, $start, $end);

            // Ambil point dari level melalui jabatan
            $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
            $userPoint = $userLevel ? ($levelPoints[$userLevel] ?? 0) : 0;

            // Cek apakah karyawan baru (tanggal_masuk dalam periode payroll) - HARUS DILAKUKAN DI STEP 1
            $isNewEmployee = false;
            $hariKerjaKaryawanBaru = $hariKerja; // Default: hari kerja normal
            if ($user->tanggal_masuk) {
                $tanggalMasuk = Carbon::parse($user->tanggal_masuk);
                $isNewEmployee = $tanggalMasuk->greaterThanOrEqualTo($startDate) && $tanggalMasuk->lessThanOrEqualTo($endDate);
                
                // Jika karyawan baru, hitung hari kerja dari tanggal masuk sampai akhir periode
                if ($isNewEmployee) {
                    // Hitung hari kerja dari tanggal masuk sampai akhir periode (hitung hari kalender)
                    $hariKerjaKaryawanBaru = $tanggalMasuk->diffInDays($endDate) + 1; // +1 untuk include tanggal masuk dan tanggal akhir
                }
            }
            
            // Gunakan hari kerja yang sesuai (hariKerjaKaryawanBaru untuk karyawan baru, hariKerja untuk karyawan lama)
            // Ini penting untuk perhitungan service charge prorate yang konsisten
            $hariKerjaUntukServiceCharge = $isNewEmployee ? $hariKerjaKaryawanBaru : $hariKerja;

            // Simpan data user untuk perhitungan service charge
            $userData[$user->id] = [
                'user' => $user,
                'masterData' => $masterData,
                'employeeRows' => $employeeRows, // Simpan employeeRows untuk digunakan nanti
                'totalTelat' => $totalTelat,
                'totalLembur' => $totalLembur,
                'hariKerja' => $hariKerja, // Hari kerja aktual (jumlah hari bekerja)
                'hariKerjaKaryawanBaru' => $hariKerjaKaryawanBaru, // Hari kerja untuk karyawan baru
                'hariKerjaUntukServiceCharge' => $hariKerjaUntukServiceCharge, // Hari kerja yang digunakan untuk service charge
                'isNewEmployee' => $isNewEmployee, // Flag apakah karyawan baru
                'totalAlpha' => $totalAlpha,
                'totalIzinCuti' => $totalIzinCuti,
                'izinCutiBreakdown' => $izinCutiBreakdown,
                'leaveData' => $leaveData, // Simpan leaveData untuk digunakan di Step 4
                'userPoint' => $userPoint,
            ];
        }

        // Step 2: Hitung total untuk service charge (hanya untuk user yang sc = 1)
        // PENTING: Gunakan hariKerjaUntukServiceCharge untuk konsistensi dengan perhitungan per user
        $totalPointHariKerja = 0; // Sum(point × hari kerja) untuk semua user yang sc = 1
        $totalHariKerja = 0; // Sum(hari kerja) untuk semua user yang sc = 1
        
        foreach ($userData as $data) {
            if ($data['masterData']->sc == 1) {
                // Gunakan hariKerjaUntukServiceCharge untuk konsistensi
                $hariKerjaSC = $data['hariKerjaUntukServiceCharge'] ?? $data['hariKerja'];
                $totalPointHariKerja += $data['userPoint'] * $hariKerjaSC;
                $totalHariKerja += $hariKerjaSC;
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

        // Step 2b: Hitung total untuk L & B (hanya untuk user yang lb = 1)
        // Menggunakan (point × hari kerja) seperti service charge by point
        $totalPointHariKerjaLB = 0;
        foreach ($userData as $data) {
            if ($data['masterData']->lb == 1) {
                $totalPointHariKerjaLB += $data['userPoint'] * $data['hariKerja'];
            }
        }

        // Step 3b: Hitung rate L & B (100% by point × hari kerja)
        $rateLBByPoint = 0;

        if ($lbAmount > 0 && $totalPointHariKerjaLB > 0) {
            $rateLBByPoint = $lbAmount / $totalPointHariKerjaLB;
        }

        // Step 2c: Hitung total untuk Deviasi (hanya untuk user yang deviasi = 1)
        // Menggunakan (point × hari kerja) seperti service charge by point
        $totalPointHariKerjaDeviasi = 0;
        foreach ($userData as $data) {
            if ($data['masterData']->deviasi == 1) {
                $totalPointHariKerjaDeviasi += $data['userPoint'] * $data['hariKerja'];
            }
        }

        // Step 3c: Hitung rate Deviasi (100% by point × hari kerja)
        $rateDeviasiByPoint = 0;

        if ($deviasiAmount > 0 && $totalPointHariKerjaDeviasi > 0) {
            $rateDeviasiByPoint = $deviasiAmount / $totalPointHariKerjaDeviasi;
        }

        // Step 2d: Hitung total untuk City Ledger (hanya untuk user yang city_ledger = 1)
        // Menggunakan (point × hari kerja) seperti service charge by point
        $totalPointHariKerjaCityLedger = 0;
        foreach ($userData as $data) {
            if ($data['masterData']->city_ledger == 1) {
                $totalPointHariKerjaCityLedger += $data['userPoint'] * $data['hariKerja'];
            }
        }

        // Step 3d: Hitung rate City Ledger (100% by point × hari kerja)
        $rateCityLedgerByPoint = 0;

        if ($cityLedgerAmount > 0 && $totalPointHariKerjaCityLedger > 0) {
            $rateCityLedgerByPoint = $cityLedgerAmount / $totalPointHariKerjaCityLedger;
        }

        // Cek apakah payroll sudah di-generate
        $payrollGenerated = DB::table('payroll_generated')
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
        
        $payrollGeneratedDetails = collect();
        if ($payrollGenerated) {
            $payrollGeneratedDetails = DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payrollGenerated->id)
                ->get()
                ->keyBy('user_id');
        }

        // Ambil custom payroll items untuk periode ini - SAMA PERSIS dengan index()
        $customItems = CustomPayrollItem::forOutlet($outletId)
            ->forPeriod($month, $year)
            ->get()
            ->groupBy('user_id');

        // Step 4: Hitung service charge per user dan export data - GUNAKAN LOGIKA YANG SAMA DENGAN index()
        $exportDataGajiAkhirBulan = [];
        $exportDataTanggal8 = [];
        foreach ($userData as $userId => $data) {
            $user = $data['user'];
            $masterData = $data['masterData'];
            $employeeRows = $data['employeeRows']; // Gunakan employeeRows dari Employee Summary
            $totalTelat = $data['totalTelat'];
            $totalLembur = $data['totalLembur'];
            $hariKerja = $data['hariKerja']; // Hari kerja aktual (jumlah hari bekerja)
            $hariKerjaKaryawanBaru = $data['hariKerjaKaryawanBaru'] ?? $hariKerja; // Hari kerja untuk karyawan baru
            $hariKerjaUntukServiceCharge = $data['hariKerjaUntukServiceCharge'] ?? $hariKerja; // Hari kerja yang digunakan untuk service charge
            $isNewEmployee = $data['isNewEmployee'] ?? false; // Flag apakah karyawan baru
            $userPoint = $data['userPoint'];
            $leaveData = $data['leaveData'] ?? []; // Ambil leaveData dari userData
            $totalAlpha = $data['totalAlpha'] ?? 0;
            $phBonus = $this->calculatePHBonus($userId, $start, $end);

            // Hitung gaji lembur menggunakan nominal_lembur dari divisi
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
            // PENTING: Gunakan hariKerjaUntukServiceCharge yang sudah dihitung di Step 1 untuk konsistensi
            $serviceChargeByPointAmount = 0;
            $serviceChargeProRateAmount = 0;
            $serviceChargeTotal = 0;
            
            if ($masterData->sc == 1 && $serviceCharge > 0) {
                // Service charge by point = rate × (point × hari kerja)
                // Untuk karyawan baru, gunakan hariKerjaUntukServiceCharge untuk konsistensi
                $serviceChargeByPointAmount = $rateByPoint * ($userPoint * $hariKerjaUntukServiceCharge);
                
                // Service charge pro rate = rate × hari kerja
                // Untuk karyawan baru, gunakan hariKerjaUntukServiceCharge untuk konsistensi
                $serviceChargeProRateAmount = $rateProRate * $hariKerjaUntukServiceCharge;
                
                // Total service charge per user
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

            // Hitung L & B (By Point × Hari Kerja) jika enabled
            // Menggunakan (point × hari kerja) seperti service charge by point
            $lbByPointAmount = 0;
            $lbTotal = 0;
            
            if ($masterData->lb == 1 && $lbAmount > 0) {
                $lbByPointAmount = $rateLBByPoint * ($userPoint * $hariKerja);
                $lbTotal = $lbByPointAmount;
            }

            // Hitung Deviasi (By Point × Hari Kerja) jika enabled
            // Menggunakan (point × hari kerja) seperti service charge by point
            $deviasiByPointAmount = 0;
            $deviasiTotal = 0;
            
            if ($masterData->deviasi == 1 && $deviasiAmount > 0) {
                $deviasiByPointAmount = $rateDeviasiByPoint * ($userPoint * $hariKerja);
                $deviasiTotal = $deviasiByPointAmount;
            }

            // Hitung City Ledger (By Point × Hari Kerja) jika enabled
            // Menggunakan (point × hari kerja) seperti service charge by point
            $cityLedgerByPointAmount = 0;
            $cityLedgerTotal = 0;
            
            if ($masterData->city_ledger == 1 && $cityLedgerAmount > 0) {
                $cityLedgerByPointAmount = $rateCityLedgerByPoint * ($userPoint * $hariKerja);
                $cityLedgerTotal = $cityLedgerByPointAmount;
            }

            // Hitung potongan telat (flat rate Rp 500 per menit)
            $potonganTelat = 0;
            $gajiPerMenit = 500; // Flat rate Rp 500 per menit
            if ($totalTelat > 0) {
                $potonganTelat = $totalTelat * $gajiPerMenit;
            }

            // Hitung gaji pokok dan tunjangan (pro rate untuk karyawan baru)
            // PENTING: Gunakan hari kerja yang sama dengan service charge prorate (proporsi yang sama)
            $gajiPokokFinal = $masterData->gaji;
            $tunjanganFinal = $masterData->tunjangan;
            
            if ($isNewEmployee && $hariKerjaUntukServiceCharge > 0) {
                // Hitung total hari kerja standar dalam periode payroll
                $totalHariKalenderPeriode = $startDate->diffInDays($endDate) + 1; // +1 untuk include tanggal awal dan akhir
                
                // Pro rate menggunakan proporsi yang sama dengan service charge prorate
                $gajiPokokFinal = $masterData->gaji * ($hariKerjaUntukServiceCharge / $totalHariKalenderPeriode);
                $tunjanganFinal = $masterData->tunjangan * ($hariKerjaUntukServiceCharge / $totalHariKalenderPeriode);
            }

            // Hitung potongan alpha: 20% dari (gaji pokok + tunjangan) × total hari alpha
            // Gunakan gaji pokok dan tunjangan yang sudah di-pro rate untuk karyawan baru
            $potonganAlpha = 0;
            if ($totalAlpha > 0) {
                $gajiPokokTunjangan = $gajiPokokFinal + $tunjanganFinal;
                $potonganAlpha = ($gajiPokokTunjangan * 0.20) * $totalAlpha;
            }

            // Hitung potongan unpaid leave: (gaji pokok + tunjangan) / 26 × jumlah unpaid leave
            // Gunakan gaji pokok dan tunjangan yang sudah di-pro rate untuk karyawan baru
            $potonganUnpaidLeave = 0;
            $unpaidLeaveDays = isset($leaveData['unpaid_leave_days']) ? $leaveData['unpaid_leave_days'] : 0;
            if ($unpaidLeaveDays > 0) {
                $gajiPokokTunjangan = $gajiPokokFinal + $tunjanganFinal;
                $gajiPerHari = $gajiPokokTunjangan / 26; // Pro rate per hari kerja
                $potonganUnpaidLeave = $gajiPerHari * $unpaidLeaveDays;
            }

            // Hitung custom earnings dan deductions - PISAHKAN BERDASARKAN GAJIAN TYPE
            $userCustomItems = $customItems->get($user->id, collect());
            
            // Custom items untuk Gajian 1
            $userCustomItemsGajian1 = $userCustomItems->where('gajian_type', 'gajian1');
            // Untuk backward compatibility, jika gajian_type null atau tidak ada, default ke gajian1
            $userCustomItemsDefault = $userCustomItems->filter(function($item) {
                return !isset($item->gajian_type) || $item->gajian_type === null;
            });
            $userCustomItemsGajian1 = $userCustomItemsGajian1->merge($userCustomItemsDefault);
            $customEarningsGajian1 = $userCustomItemsGajian1->where('item_type', 'earn')->sum('item_amount');
            $customDeductionsGajian1 = $userCustomItemsGajian1->where('item_type', 'deduction')->sum('item_amount');
            
            // Custom items untuk Gajian 2
            $userCustomItemsGajian2 = $userCustomItems->where('gajian_type', 'gajian2');
            $customEarningsGajian2 = $userCustomItemsGajian2->where('item_type', 'earn')->sum('item_amount');
            $customDeductionsGajian2 = $userCustomItemsGajian2->where('item_type', 'deduction')->sum('item_amount');
            
            // Set untuk gajian1 (default)
            $customEarnings = $customEarningsGajian1;
            $customDeductions = $customDeductionsGajian1;

            // Gunakan data dari payroll_generated_details jika sudah di-generate
            $payrollDetail = $payrollGeneratedDetails->get($userId);
            
            // Ambil payment method dari payroll_generated_details atau default 'transfer'
            $paymentMethod = $payrollDetail ? ($payrollDetail->payment_method ?? 'transfer') : 'transfer';
            
            // Jika payroll sudah di-generate, gunakan data dari payroll_generated_details
            if ($payrollDetail) {
                $gajiPokokFinal = $payrollDetail->gaji_pokok ?? $gajiPokokFinal;
                $tunjanganFinal = $payrollDetail->tunjangan ?? $tunjanganFinal;
                $totalTelat = $payrollDetail->total_telat ?? $totalTelat;
                $totalLembur = $payrollDetail->total_lembur ?? $totalLembur;
                $gajiLembur = $payrollDetail->gaji_lembur ?? $gajiLembur;
                $uangMakan = $payrollDetail->uang_makan ?? $uangMakan;
                $serviceChargeByPointAmount = $payrollDetail->service_charge_by_point ?? $serviceChargeByPointAmount;
                $serviceChargeProRateAmount = $payrollDetail->service_charge_pro_rate ?? $serviceChargeProRateAmount;
                $serviceChargeTotal = $payrollDetail->service_charge ?? $serviceChargeTotal;
                $bpjsJKN = $payrollDetail->bpjs_jkn ?? $bpjsJKN;
                $bpjsTK = $payrollDetail->bpjs_tk ?? $bpjsTK;
                $potonganTelat = $payrollDetail->potongan_telat ?? $potonganTelat;
                $lbTotal = $payrollDetail->lb_total ?? $lbTotal;
                $deviasiTotal = $payrollDetail->deviasi_total ?? $deviasiTotal;
                $cityLedgerTotal = $payrollDetail->city_ledger_total ?? $cityLedgerTotal;
                $phBonus = $payrollDetail->ph_bonus ?? $phBonus;
                $hariKerja = $payrollDetail->hari_kerja ?? $hariKerja;
                $paymentMethod = $payrollDetail->payment_method ?? 'transfer';
                
                // Ambil custom items dari JSON jika ada - PISAHKAN BERDASARKAN GAJIAN TYPE
                if ($payrollDetail->custom_items) {
                    $customItemsData = json_decode($payrollDetail->custom_items, true) ?? [];
                    $customItemsCollection = collect($customItemsData);
                    
                    // Custom items untuk Gajian 1
                    $customItemsGajian1 = $customItemsCollection->where('gajian_type', 'gajian1');
                    // Untuk backward compatibility, jika gajian_type null atau tidak ada, default ke gajian1
                    $customItemsDefault = $customItemsCollection->filter(function($item) {
                        return !isset($item['gajian_type']) || $item['gajian_type'] === null;
                    });
                    $customItemsGajian1 = $customItemsGajian1->merge($customItemsDefault);
                    $customEarningsGajian1 = $customItemsGajian1->where('item_type', 'earn')->sum('item_amount');
                    $customDeductionsGajian1 = $customItemsGajian1->where('item_type', 'deduction')->sum('item_amount');
                    
                    // Custom items untuk Gajian 2
                    $customItemsGajian2 = $customItemsCollection->where('gajian_type', 'gajian2');
                    $customEarningsGajian2 = $customItemsGajian2->where('item_type', 'earn')->sum('item_amount');
                    $customDeductionsGajian2 = $customItemsGajian2->where('item_type', 'deduction')->sum('item_amount');
                    
                    // Set untuk gajian1 (default)
                    $customEarnings = $customEarningsGajian1;
                    $customDeductions = $customDeductionsGajian1;
                }
                
                // Ambil potongan alpha dan unpaid leave
                $potonganAlpha = $payrollDetail->potongan_alpha ?? $potonganAlpha;
                $potonganUnpaidLeave = $payrollDetail->potongan_unpaid_leave ?? $potonganUnpaidLeave;
            }
            
            // Hitung Gajian 1: Gaji Pokok + Tunjangan + Custom Earning (gajian1) - Custom Deduction (gajian1) - BPJS JKN - BPJS TK - Telat - Alpha - Unpaid Leave
            $totalGajian1 = $gajiPokokFinal + $tunjanganFinal + $customEarnings - $customDeductions - ($bpjsJKN ?? 0) - ($bpjsTK ?? 0) - $potonganTelat - $potonganAlpha - $potonganUnpaidLeave;
            
            // Hitung Gajian 2: Service Charge + Uang Makan + Lembur + PH Bonus + Custom Earning (gajian2) - L & B - Deviasi - City Ledger - Custom Deduction (gajian2)
            $totalGajian2 = $serviceChargeTotal + $uangMakan + $gajiLembur + $phBonus + ($customEarningsGajian2 ?? 0) - $lbTotal - $deviasiTotal - $cityLedgerTotal - ($customDeductionsGajian2 ?? 0);

            // Sheet 1: Gaji Akhir Bulan (Gajian 1)
            $exportDataGajiAkhirBulan[] = [
                'NIK' => $user->nik,
                'Nama Karyawan' => $user->nama_lengkap,
                'No. Rekening' => $user->no_rekening ?? '-',
                'Payment Method' => ucfirst($paymentMethod),
                'Jabatan' => $jabatans[$user->id_jabatan] ?? '-',
                'Divisi' => $divisions[$user->division_id] ?? '-',
                'Gaji Pokok' => round($gajiPokokFinal),
                'Tunjangan' => round($tunjanganFinal),
                'Custom Earnings (Gajian 1)' => round($customEarnings),
                'Custom Deductions (Gajian 1)' => round($customDeductions),
                'BPJS JKN' => round($bpjsJKN ?? 0),
                'BPJS TK' => round($bpjsTK ?? 0),
                'Potongan Telat' => round($potonganTelat),
                'Potongan Alpha' => round($potonganAlpha),
                'Potongan Unpaid Leave' => round($potonganUnpaidLeave),
                'Total Gaji Akhir Bulan' => round($totalGajian1),
                'Hari Kerja' => $hariKerja,
                'Periode' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            ];

            // Sheet 2: Tanggal 8 (Gajian 2)
            $exportDataTanggal8[] = [
                'NIK' => $user->nik,
                'Nama Karyawan' => $user->nama_lengkap,
                'No. Rekening' => $user->no_rekening ?? '-',
                'Payment Method' => ucfirst($paymentMethod),
                'Jabatan' => $jabatans[$user->id_jabatan] ?? '-',
                'Divisi' => $divisions[$user->division_id] ?? '-',
                'Service Charge' => round($serviceChargeTotal),
                'Uang Makan' => round($uangMakan),
                'Gaji Lembur' => round($gajiLembur),
                'PH Bonus' => round($phBonus),
                'L & B' => round($lbTotal),
                'Deviasi' => round($deviasiTotal),
                'City Ledger' => round($cityLedgerTotal),
                'Custom Earnings (Gajian 2)' => round($customEarningsGajian2 ?? 0),
                'Custom Deductions (Gajian 2)' => round($customDeductionsGajian2 ?? 0),
                'Total Gaji Tanggal 8' => round($totalGajian2),
                'Periode' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            ];
        }

        // Generate Excel file dengan 2 sheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // Sheet 1: Gaji Akhir Bulan
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Gaji Akhir Bulan');
        $sheet1->setCellValue('A1', 'LAPORAN PAYROLL - GAJI AKHIR BULAN');
        $sheet1->setCellValue('A2', 'Outlet: ' . $outletName);
        $sheet1->setCellValue('A3', 'Periode: ' . $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'));

        if (!empty($exportDataGajiAkhirBulan)) {
            $headers1 = array_keys($exportDataGajiAkhirBulan[0]);
            $sheet1->fromArray($headers1, null, 'A5');
            $sheet1->fromArray($exportDataGajiAkhirBulan, null, 'A6');
        }

        // Auto size columns untuk sheet 1
        foreach (range('A', 'S') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // Sheet 2: Tanggal 8 (PH Bonus) - selalu dibuat
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Tanggal 8');
        $sheet2->setCellValue('A1', 'LAPORAN PAYROLL - TANGGAL 8 (PH BONUS)');
        $sheet2->setCellValue('A2', 'Outlet: ' . $outletName);
        $sheet2->setCellValue('A3', 'Periode: ' . $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'));

        if (!empty($exportDataTanggal8)) {
            $headers2 = array_keys($exportDataTanggal8[0]);
            $sheet2->fromArray($headers2, null, 'A5');
            $sheet2->fromArray($exportDataTanggal8, null, 'A6');
        }

        // Auto size columns untuk sheet 2
        foreach (range('A', 'H') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
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
            'item_description' => 'nullable|string',
            'gajian_type' => 'nullable|in:gajian1,gajian2' // Optional, default ke gajian1 jika tidak ada
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
                'item_description' => $request->item_description,
                'gajian_type' => $request->gajian_type ?? 'gajian1' // Default ke gajian1 jika tidak ada
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
        $type = $request->input('type', 'gajian1'); // 'gajian1' or 'gajian2'

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

        // Hitung periode payroll untuk perhitungan alpha dan leave (harus didefinisikan sebelum digunakan)
        $startDate = Carbon::create($year, $month, 26)->subMonth();
        $endDate = Carbon::create($year, $month, 25);

        // Jika payroll sudah di-generate, gunakan data dari payroll_generated_details
        if ($payrollDetail) {
            // Gunakan data dari payroll_generated_details
            // Ambil gaji pokok dan tunjangan dari payroll_generated_details jika ada, jika tidak gunakan masterData
            $gajiPokok = $payrollDetail->gaji_pokok ?? $masterData->gaji ?? 0;
            $tunjangan = $payrollDetail->tunjangan ?? $masterData->tunjangan ?? 0;
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
            $totalAlpha = $payrollDetail->total_alpha ?? 0;
            $potonganAlpha = $payrollDetail->potongan_alpha ?? 0;
            $potonganUnpaidLeave = $payrollDetail->potongan_unpaid_leave ?? 0;
            
            // Get custom items dari JSON
            $customItems = collect([]);
            if ($payrollDetail->custom_items) {
                // Decode sebagai objects (false) agar bisa diakses dengan -> seperti di view
                $decodedItems = json_decode($payrollDetail->custom_items, false) ?? [];
                $customItems = collect($decodedItems);
            }
            
            // Hitung leave data dari JSON atau hitung ulang jika tidak ada
            $leaveData = [];
            if ($payrollDetail->leave_data) {
                $leaveData = json_decode($payrollDetail->leave_data, true) ?? [];
            } else {
                // Hitung ulang jika tidak ada di database
                $leaveData = $this->calculateLeaveData($userId, $startDate, $endDate);
            }
        } else {
            // Jika belum di-generate, hitung ulang seperti biasa menggunakan logic dari printPayroll
            // Ambil gaji pokok dan tunjangan dari masterData
            $gajiPokok = $masterData->gaji ?? 0;
            $tunjangan = $masterData->tunjangan ?? 0;

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

            // Get custom items - PISAHKAN BERDASARKAN GAJIAN TYPE
            $customItems = CustomPayrollItem::forOutlet($outletId)
                ->forPeriod($month, $year)
                ->where('user_id', $userId)
                ->get();
            
            // Custom items untuk Gajian 1
            $customItemsGajian1 = $customItems->where('gajian_type', 'gajian1');
            // Untuk backward compatibility, jika gajian_type null atau tidak ada, default ke gajian1
            $customItemsDefault = $customItems->filter(function($item) {
                return !isset($item->gajian_type) || $item->gajian_type === null;
            });
            $customItemsGajian1 = $customItemsGajian1->merge($customItemsDefault);
            $customEarningsGajian1 = $customItemsGajian1->where('item_type', 'earn')->sum('item_amount');
            $customDeductionsGajian1 = $customItemsGajian1->where('item_type', 'deduction')->sum('item_amount');
            
            // Custom items untuk Gajian 2
            $customItemsGajian2 = $customItems->where('gajian_type', 'gajian2');
            $customEarningsGajian2 = $customItemsGajian2->where('item_type', 'earn')->sum('item_amount');
            $customDeductionsGajian2 = $customItemsGajian2->where('item_type', 'deduction')->sum('item_amount');
            
            // Set untuk gajian1 (default)
            $customEarnings = $customEarningsGajian1;
            $customDeductions = $customDeductionsGajian1;

            // Hitung service charge jika enabled
            $serviceChargeAmount = 0;
            if ($masterData->sc == 1 && $serviceCharge > 0) {
                $serviceChargeAmount = $serviceCharge;
            }
            
            // Hitung alpha dan leave data
            $totalAlpha = $this->calculateAlpaDays($userId, $outletId, $startDate, $endDate);
            $leaveData = $this->calculateLeaveData($userId, $startDate, $endDate);
            
            // Hitung potongan alpha: 20% dari (gaji pokok + tunjangan) × total hari alpha
            $potonganAlpha = 0;
            if ($totalAlpha > 0) {
                $gajiPokokTunjangan = $masterData->gaji + $masterData->tunjangan;
                $potonganAlpha = ($gajiPokokTunjangan * 0.20) * $totalAlpha;
            }
            
            // Hitung potongan unpaid leave: (gaji pokok + tunjangan) / 26 × jumlah unpaid leave
            $potonganUnpaidLeave = 0;
            $unpaidLeaveDays = isset($leaveData['unpaid_leave_days']) ? $leaveData['unpaid_leave_days'] : 0;
            if ($unpaidLeaveDays > 0) {
                $gajiPokokTunjangan = $masterData->gaji + $masterData->tunjangan;
                $gajiPerHari = $gajiPokokTunjangan / 26; // Pro rate per hari kerja
                $potonganUnpaidLeave = $gajiPerHari * $unpaidLeaveDays;
            }

            // Calculate total salary
            $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $serviceChargeAmount + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $customDeductions - $potonganAlpha - $potonganUnpaidLeave;
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
            $periode = $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');
        }
        
        // Get leave types untuk mapping nama leave type
        $leaveTypes = DB::table('leave_types')->get()->keyBy('id');
        
        // Pastikan semua variabel yang diperlukan sudah terdefinisi
        $totalAlpha = $totalAlpha ?? 0;
        $potonganAlpha = $potonganAlpha ?? 0;
        $potonganUnpaidLeave = $potonganUnpaidLeave ?? 0;
        $leaveData = $leaveData ?? [];

        // Ambil data tambahan untuk gajian2
        $lbTotal = 0;
        $deviasiTotal = 0;
        $cityLedgerTotal = 0;
        $serviceChargeByPoint = 0;
        $serviceChargeProRate = 0;
        $phBonus = 0;
        
        if ($payrollDetail) {
            $lbTotal = $payrollDetail->lb_total ?? 0;
            $deviasiTotal = $payrollDetail->deviasi_total ?? 0;
            $cityLedgerTotal = $payrollDetail->city_ledger_total ?? 0;
            $serviceChargeByPoint = $payrollDetail->service_charge_by_point ?? 0;
            $serviceChargeProRate = $payrollDetail->service_charge_pro_rate ?? 0;
            $phBonus = $payrollDetail->ph_bonus ?? 0;
        }
        
        // Hitung total gaji berdasarkan type
        $totalGajiFinal = 0;
        if ($type === 'gajian1') {
            // Gajian 1: Gaji Pokok + Tunjangan + Custom Earning (gajian1) - Custom Deduction (gajian1) - Telat - Alpha - Unpaid Leave
            $totalGajiFinal = ($masterData->gaji ?? 0) 
                + ($masterData->tunjangan ?? 0) 
                + ($customEarningsGajian1 ?? $customEarnings ?? 0) 
                - ($customDeductionsGajian1 ?? $customDeductions ?? 0) 
                - ($potonganTelat ?? 0) 
                - ($potonganAlpha ?? 0) 
                - ($potonganUnpaidLeave ?? 0);
        } else {
            // Gajian 2: Service Charge + Uang Makan + Lembur + PH Bonus + Custom Earning (gajian2) - L & B - Deviasi - City Ledger - Custom Deduction (gajian2)
            $totalGajiFinal = ($serviceChargeAmount ?? 0) 
                + ($uangMakan ?? 0) 
                + ($gajiLembur ?? 0) 
                + ($phBonus ?? 0) 
                + ($customEarningsGajian2 ?? 0)
                - ($lbTotal ?? 0) 
                - ($deviasiTotal ?? 0) 
                - ($cityLedgerTotal ?? 0)
                - ($customDeductionsGajian2 ?? 0);
        }

        // Check if download PDF is requested
        if ($request->has('download') && $request->download === 'pdf') {
            $pdf = \PDF::loadView('payroll.slip', [
                'user' => $user,
                'outlet' => $outlet,
                'jabatan' => $jabatan,
                'divisi' => $divisi,
                'periode' => $periode,
                'type' => $type,
                'gaji_pokok' => $masterData->gaji,
                'tunjangan' => $masterData->tunjangan,
                'total_lembur' => $totalLembur,
                'gaji_lembur' => $gajiLembur,
                'nominal_lembur_per_jam' => $nominalLemburPerJam,
                'uang_makan' => $uangMakan,
                'nominal_uang_makan' => $nominalUangMakan,
                'service_charge' => round($serviceChargeAmount),
                'service_charge_by_point' => round($serviceChargeByPoint),
                'service_charge_pro_rate' => round($serviceChargeProRate),
                'total_telat' => $totalTelat,
                'bpjs_jkn' => $bpjsJKN,
                'bpjs_tk' => $bpjsTK,
                'custom_earnings' => $customEarnings,
                'custom_deductions' => $customDeductions,
                'custom_items' => $customItems,
                'custom_earnings_gajian1' => $customEarningsGajian1 ?? $customEarnings,
                'custom_deductions_gajian1' => $customDeductionsGajian1 ?? $customDeductions,
                'custom_items_gajian1' => $customItemsGajian1 ?? collect(),
                'custom_earnings_gajian2' => $customEarningsGajian2 ?? 0,
                'custom_deductions_gajian2' => $customDeductionsGajian2 ?? 0,
                'custom_items_gajian2' => $customItemsGajian2 ?? collect(),
                'gaji_per_menit' => round($gajiPerMenit, 2),
                'potongan_telat' => round($potonganTelat),
                'total_alpha' => $totalAlpha,
                'potongan_alpha' => round($potonganAlpha),
                'potongan_unpaid_leave' => round($potonganUnpaidLeave),
                'lb_total' => round($lbTotal),
                'deviasi_total' => round($deviasiTotal),
                'city_ledger_total' => round($cityLedgerTotal),
                'ph_bonus' => round($phBonus),
                'leave_data' => $leaveData,
                'leave_types' => $leaveTypes,
                'total_gaji' => round($totalGajiFinal),
                'hari_kerja' => $hariKerja,
                'master_data' => $masterData,
                'logo_base64' => $logoBase64,
            ]);

            $typeLabel = $type === 'gajian1' ? 'Gajian1' : 'Gajian2';
            return $pdf->download("slip_gaji_{$typeLabel}_{$user->nama_lengkap}_{$periode}.pdf");
        }

        return view('payroll.slip', [
            'user' => $user,
            'outlet' => $outlet,
            'jabatan' => $jabatan,
            'divisi' => $divisi,
            'periode' => $periode,
            'type' => $type,
            'gaji_pokok' => isset($gajiPokok) ? $gajiPokok : ($masterData->gaji ?? 0),
            'tunjangan' => isset($tunjangan) ? $tunjangan : ($masterData->tunjangan ?? 0),
            'total_lembur' => $totalLembur,
            'gaji_lembur' => $gajiLembur,
            'nominal_lembur_per_jam' => $nominalLemburPerJam,
            'uang_makan' => $uangMakan,
            'nominal_uang_makan' => $nominalUangMakan,
            'service_charge' => round($serviceChargeAmount),
            'service_charge_by_point' => round($serviceChargeByPoint),
            'service_charge_pro_rate' => round($serviceChargeProRate),
            'total_telat' => $totalTelat,
                'bpjs_jkn' => $bpjsJKN,
                'bpjs_tk' => $bpjsTK,
                'custom_earnings' => $customEarnings,
                'custom_deductions' => $customDeductions,
                'custom_items' => $customItems,
                'custom_earnings_gajian1' => $customEarningsGajian1 ?? $customEarnings,
                'custom_deductions_gajian1' => $customDeductionsGajian1 ?? $customDeductions,
                'custom_items_gajian1' => $customItemsGajian1 ?? collect(),
                'custom_earnings_gajian2' => $customEarningsGajian2 ?? 0,
                'custom_deductions_gajian2' => $customDeductionsGajian2 ?? 0,
                'custom_items_gajian2' => $customItemsGajian2 ?? collect(),
                'gaji_per_menit' => round($gajiPerMenit, 2),
            'potongan_telat' => round($potonganTelat),
            'total_alpha' => $totalAlpha,
            'potongan_alpha' => round($potonganAlpha),
            'potongan_unpaid_leave' => round($potonganUnpaidLeave),
            'lb_total' => round($lbTotal),
            'deviasi_total' => round($deviasiTotal),
            'city_ledger_total' => round($cityLedgerTotal),
            'leave_data' => $leaveData,
            'leave_types' => $leaveTypes,
            'total_gaji' => round($totalGajiFinal),
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
        $type = $request->input('type', 'gajian1'); // 'gajian1' or 'gajian2'

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
            // Ambil gaji pokok dan tunjangan dari payroll_generated_details jika ada, jika tidak gunakan masterData
            $gajiPokok = $payrollDetail->gaji_pokok ?? $masterData->gaji ?? 0;
            $tunjangan = $payrollDetail->tunjangan ?? $masterData->tunjangan ?? 0;
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
            $totalAlpha = $payrollDetail->total_alpha ?? 0;
            $potonganAlpha = $payrollDetail->potongan_alpha ?? 0;
            $potonganUnpaidLeave = $payrollDetail->potongan_unpaid_leave ?? 0;
            
            // Get custom items dari JSON
            $customItems = collect([]);
            if ($payrollDetail->custom_items) {
                // Decode sebagai objects (false) agar bisa diakses dengan -> seperti di view
                $decodedItems = json_decode($payrollDetail->custom_items, false) ?? [];
                $customItems = collect($decodedItems);
            }
            
            // Hitung leave data dari JSON atau hitung ulang jika tidak ada
            $leaveData = [];
            if ($payrollDetail->leave_data) {
                $leaveData = json_decode($payrollDetail->leave_data, true) ?? [];
            } else {
                // Hitung ulang jika tidak ada di database
                $leaveData = $this->calculateLeaveData($userId, $startDate, $endDate);
            }
        } else {
            // Jika belum di-generate, hitung ulang seperti biasa
            // Ambil gaji pokok dan tunjangan dari masterData
            $gajiPokok = $masterData->gaji ?? 0;
            $tunjangan = $masterData->tunjangan ?? 0;
            
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

            // Get custom items - PISAHKAN BERDASARKAN GAJIAN TYPE
            $customItems = CustomPayrollItem::forOutlet($outletId)
                ->forPeriod($month, $year)
                ->where('user_id', $userId)
                ->get();
            
            // Custom items untuk Gajian 1
            $customItemsGajian1 = $customItems->where('gajian_type', 'gajian1');
            // Untuk backward compatibility, jika gajian_type null atau tidak ada, default ke gajian1
            $customItemsDefault = $customItems->filter(function($item) {
                return !isset($item->gajian_type) || $item->gajian_type === null;
            });
            $customItemsGajian1 = $customItemsGajian1->merge($customItemsDefault);
            $customEarningsGajian1 = $customItemsGajian1->where('item_type', 'earn')->sum('item_amount');
            $customDeductionsGajian1 = $customItemsGajian1->where('item_type', 'deduction')->sum('item_amount');
            
            // Custom items untuk Gajian 2
            $customItemsGajian2 = $customItems->where('gajian_type', 'gajian2');
            $customEarningsGajian2 = $customItemsGajian2->where('item_type', 'earn')->sum('item_amount');
            $customDeductionsGajian2 = $customItemsGajian2->where('item_type', 'deduction')->sum('item_amount');
            
            // Set untuk gajian1 (default)
            $customEarnings = $customEarningsGajian1;
            $customDeductions = $customDeductionsGajian1;

            // Hitung service charge jika enabled
            $serviceChargeAmount = 0;
            if ($masterData->sc == 1 && $serviceCharge > 0) {
                $serviceChargeAmount = $serviceCharge;
            }
            
            // Hitung alpha dan leave data
            $totalAlpha = $this->calculateAlpaDays($userId, $outletId, $startDate, $endDate);
            $leaveData = $this->calculateLeaveData($userId, $startDate, $endDate);
            
            // Hitung potongan alpha: 20% dari (gaji pokok + tunjangan) × total hari alpha
            $potonganAlpha = 0;
            if ($totalAlpha > 0) {
                $gajiPokokTunjangan = $masterData->gaji + $masterData->tunjangan;
                $potonganAlpha = ($gajiPokokTunjangan * 0.20) * $totalAlpha;
            }
            
            // Hitung potongan unpaid leave: (gaji pokok + tunjangan) / 26 × jumlah unpaid leave
            $potonganUnpaidLeave = 0;
            $unpaidLeaveDays = isset($leaveData['unpaid_leave_days']) ? $leaveData['unpaid_leave_days'] : 0;
            if ($unpaidLeaveDays > 0) {
                $gajiPokokTunjangan = $masterData->gaji + $masterData->tunjangan;
                $gajiPerHari = $gajiPokokTunjangan / 26; // Pro rate per hari kerja
                $potonganUnpaidLeave = $gajiPerHari * $unpaidLeaveDays;
            }

            // Calculate total salary
            $totalGaji = $masterData->gaji + $masterData->tunjangan + $gajiLembur + $uangMakan + $serviceChargeAmount + $customEarnings - $potonganTelat - $bpjsJKN - $bpjsTK - $customDeductions - $potonganAlpha - $potonganUnpaidLeave;
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
            $periode = $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');
        }
        
        // Get leave types untuk mapping nama leave type
        $leaveTypes = DB::table('leave_types')->get()->keyBy('id');
        
        // Pastikan semua variabel yang diperlukan sudah terdefinisi
        $totalAlpha = $totalAlpha ?? 0;
        $potonganAlpha = $potonganAlpha ?? 0;
        $potonganUnpaidLeave = $potonganUnpaidLeave ?? 0;
        $leaveData = $leaveData ?? [];

        // Ambil data tambahan untuk gajian2
        $lbTotal = 0;
        $deviasiTotal = 0;
        $cityLedgerTotal = 0;
        $serviceChargeByPoint = 0;
        $serviceChargeProRate = 0;
        $phBonus = 0;
        
        if ($payrollDetail) {
            $lbTotal = $payrollDetail->lb_total ?? 0;
            $deviasiTotal = $payrollDetail->deviasi_total ?? 0;
            $cityLedgerTotal = $payrollDetail->city_ledger_total ?? 0;
            $serviceChargeByPoint = $payrollDetail->service_charge_by_point ?? 0;
            $serviceChargeProRate = $payrollDetail->service_charge_pro_rate ?? 0;
            $phBonus = $payrollDetail->ph_bonus ?? 0;
        }
        
        // Hitung total gaji berdasarkan type
        $totalGajiFinal = 0;
        if ($type === 'gajian1') {
            // Gajian 1: Gaji Pokok + Tunjangan + Custom Earning (gajian1) - Custom Deduction (gajian1) - Telat - Alpha - Unpaid Leave
            $totalGajiFinal = ($masterData->gaji ?? 0) 
                + ($masterData->tunjangan ?? 0) 
                + ($customEarningsGajian1 ?? $customEarnings ?? 0) 
                - ($customDeductionsGajian1 ?? $customDeductions ?? 0) 
                - ($potonganTelat ?? 0) 
                - ($potonganAlpha ?? 0) 
                - ($potonganUnpaidLeave ?? 0);
        } else {
            // Gajian 2: Service Charge + Uang Makan + Lembur + PH Bonus + Custom Earning (gajian2) - L & B - Deviasi - City Ledger - Custom Deduction (gajian2)
            $totalGajiFinal = ($serviceChargeAmount ?? 0) 
                + ($uangMakan ?? 0) 
                + ($gajiLembur ?? 0) 
                + ($phBonus ?? 0) 
                + ($customEarningsGajian2 ?? 0)
                - ($lbTotal ?? 0) 
                - ($deviasiTotal ?? 0) 
                - ($cityLedgerTotal ?? 0)
                - ($customDeductionsGajian2 ?? 0);
        }

        // Generate PDF
        $pdf = \PDF::loadView('payroll.slip', [
            'user' => $user,
            'jabatan' => $jabatan,
            'divisi' => $divisi,
            'outlet' => $outlet,
            'periode' => $periode,
            'type' => $type,
            'gaji_pokok' => isset($gajiPokok) ? $gajiPokok : ($masterData->gaji ?? 0),
            'tunjangan' => isset($tunjangan) ? $tunjangan : ($masterData->tunjangan ?? 0),
            'total_telat' => $totalTelat,
            'total_lembur' => $totalLembur,
            'nominal_lembur_per_jam' => isset($nominalLemburPerJam) ? $nominalLemburPerJam : (isset($nominalLembur) ? $nominalLembur : 0),
            'gaji_lembur' => round($gajiLembur),
            'nominal_uang_makan' => $nominalUangMakan,
            'uang_makan' => round($uangMakan),
            'service_charge' => round($serviceChargeAmount),
            'service_charge_by_point' => round($serviceChargeByPoint),
            'service_charge_pro_rate' => round($serviceChargeProRate),
            'bpjs_jkn' => round($bpjsJKN),
            'bpjs_tk' => round($bpjsTK),
            'custom_earnings' => round($customEarnings),
            'custom_deductions' => round($customDeductions),
            'custom_items' => $customItems,
            'custom_earnings_gajian1' => round($customEarningsGajian1 ?? $customEarnings),
            'custom_deductions_gajian1' => round($customDeductionsGajian1 ?? $customDeductions),
            'custom_items_gajian1' => $customItemsGajian1 ?? collect(),
            'custom_earnings_gajian2' => round($customEarningsGajian2 ?? 0),
            'custom_deductions_gajian2' => round($customDeductionsGajian2 ?? 0),
            'custom_items_gajian2' => $customItemsGajian2 ?? collect(),
            'gaji_per_menit' => round($gajiPerMenit, 2),
            'potongan_telat' => round($potonganTelat),
            'total_alpha' => $totalAlpha,
            'potongan_alpha' => round($potonganAlpha),
            'potongan_unpaid_leave' => round($potonganUnpaidLeave),
            'lb_total' => round($lbTotal),
            'deviasi_total' => round($deviasiTotal),
            'city_ledger_total' => round($cityLedgerTotal),
            'leave_data' => $leaveData,
            'leave_types' => $leaveTypes,
            'total_gaji' => round($totalGajiFinal),
            'hari_kerja' => $hariKerja,
            'master_data' => $masterData,
            'logo_base64' => $logoBase64,
        ]);

        $typeLabel = $type === 'gajian1' ? 'Gajian1' : 'Gajian2';
        $filename = "Slip_Gaji_{$typeLabel}_{$user->nama_lengkap}_{$month}_{$year}.pdf";
        
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
            'lb_amount' => 'nullable|numeric|min:0',
            'deviasi_amount' => 'nullable|numeric|min:0',
            'city_ledger_amount' => 'nullable|numeric|min:0',
            'payroll_data' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $outletId = $request->outlet_id;
            $month = $request->month;
            $year = $request->year;
            $serviceCharge = $request->service_charge ?? 0;
            $lbAmount = $request->lb_amount ?? 0;
            $deviasiAmount = $request->deviasi_amount ?? 0;
            $cityLedgerAmount = $request->city_ledger_amount ?? 0;
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
                        'lb_amount' => $lbAmount,
                        'deviasi_amount' => $deviasiAmount,
                        'city_ledger_amount' => $cityLedgerAmount,
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
                    'lb_amount' => $lbAmount,
                    'deviasi_amount' => $deviasiAmount,
                    'city_ledger_amount' => $cityLedgerAmount,
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
                    'lb_total' => $item['lb_total'] ?? 0,
                    'deviasi_total' => $item['deviasi_total'] ?? 0,
                    'city_ledger_total' => $item['city_ledger_total'] ?? 0,
                    'ph_bonus' => $item['ph_bonus'] ?? 0,
                    'custom_earnings' => $item['custom_earnings'] ?? 0,
                    'custom_deductions' => $item['custom_deductions'] ?? 0,
                    'gaji_per_menit' => $item['gaji_per_menit'] ?? 0,
                    'potongan_telat' => $item['potongan_telat'] ?? 0,
                    'total_alpha' => $item['total_alpha'] ?? 0,
                    'potongan_alpha' => $item['potongan_alpha'] ?? 0,
                    'potongan_unpaid_leave' => $item['potongan_unpaid_leave'] ?? 0,
                    'total_gaji' => $item['total_gaji'] ?? 0,
                    'hari_kerja' => $item['hari_kerja'] ?? 0,
                    'periode' => $item['periode'] ?? null,
                    'custom_items' => isset($item['custom_items']) ? json_encode($item['custom_items']) : null,
                    'leave_data' => isset($item['leave_data']) ? json_encode($item['leave_data']) : (isset($item['izin_cuti_breakdown']) ? json_encode($item['izin_cuti_breakdown']) : null),
                    'payment_method' => $item['payment_method'] ?? 'transfer', // Use payment_method from item or default to transfer
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
            'lb_amount' => 'nullable|numeric|min:0',
            'deviasi_amount' => 'nullable|numeric|min:0',
            'city_ledger_amount' => 'nullable|numeric|min:0',
            'payroll_data' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $payrollId = $request->payroll_id;
            $serviceCharge = $request->service_charge ?? 0;
            $lbAmount = $request->lb_amount ?? 0;
            $deviasiAmount = $request->deviasi_amount ?? 0;
            $cityLedgerAmount = $request->city_ledger_amount ?? 0;
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
                    'lb_amount' => $lbAmount,
                    'deviasi_amount' => $deviasiAmount,
                    'city_ledger_amount' => $cityLedgerAmount,
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
                    'lb_total' => $item['lb_total'] ?? 0,
                    'deviasi_total' => $item['deviasi_total'] ?? 0,
                    'city_ledger_total' => $item['city_ledger_total'] ?? 0,
                    'ph_bonus' => $item['ph_bonus'] ?? 0,
                    'custom_earnings' => $item['custom_earnings'] ?? 0,
                    'custom_deductions' => $item['custom_deductions'] ?? 0,
                    'gaji_per_menit' => $item['gaji_per_menit'] ?? 0,
                    'potongan_telat' => $item['potongan_telat'] ?? 0,
                    'total_alpha' => $item['total_alpha'] ?? 0,
                    'potongan_alpha' => $item['potongan_alpha'] ?? 0,
                    'potongan_unpaid_leave' => $item['potongan_unpaid_leave'] ?? 0,
                    'total_gaji' => $item['total_gaji'] ?? 0,
                    'hari_kerja' => $item['hari_kerja'] ?? 0,
                    'periode' => $item['periode'] ?? null,
                    'custom_items' => isset($item['custom_items']) ? json_encode($item['custom_items']) : null,
                    'leave_data' => isset($item['leave_data']) ? json_encode($item['leave_data']) : (isset($item['izin_cuti_breakdown']) ? json_encode($item['izin_cuti_breakdown']) : null),
                    'payment_method' => $item['payment_method'] ?? 'transfer', // Use existing payment_method or default to transfer
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

    // Update Payment Method
    public function updatePaymentMethod(Request $request)
    {
        $request->validate([
            'payroll_id' => 'required|integer',
            'user_id' => 'required|integer',
            'payment_method' => 'required|in:transfer,cash',
        ]);

        try {
            DB::beginTransaction();

            $payrollId = $request->payroll_id;
            $userId = $request->user_id;
            $paymentMethod = $request->payment_method;

            // Cek apakah payroll detail ada
            $payrollDetail = DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payrollId)
                ->where('user_id', $userId)
                ->first();

            if (!$payrollDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll detail tidak ditemukan'
                ], 404);
            }

            // Update payment_method
            DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payrollId)
                ->where('user_id', $userId)
                ->update([
                    'payment_method' => $paymentMethod,
                    'updated_at' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment method berhasil di-update'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating payment method: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal update payment method: ' . $e->getMessage()
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
            $today = now();
            $currentDate = $today->format('Y-m-d');
            $currentDay = $today->day;
            
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

            // Generate 2 jenis slip gaji untuk setiap payroll
            $result = [];
            foreach ($payrollList as $payroll) {
                $month = $payroll->month;
                $year = $payroll->year;
                
                // Hitung tanggal gajian 1 (akhir bulan)
                $lastDayOfMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
                $gajian1Date = sprintf('%04d-%02d-%02d', $year, $month, $lastDayOfMonth);
                
                // Hitung tanggal gajian 2 (tanggal 8 bulan berikutnya)
                $nextMonth = $month + 1;
                $nextYear = $year;
                if ($nextMonth > 12) {
                    $nextMonth = 1;
                    $nextYear++;
                }
                $gajian2Date = sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, 8);
                
                // Gajian 1: Akhir bulan
                $gajian1 = (object) [
                    'id' => $payroll->id . '_gajian1',
                    'payroll_detail_id' => $payroll->id,
                    'user_id' => $payroll->user_id,
                    'outlet_id' => $payroll->outlet_id,
                    'outlet_name' => $payroll->outlet_name,
                    'month' => $month,
                    'year' => $year,
                    'type' => 'gajian1',
                    'type_label' => 'Gajian 1 (Akhir Bulan)',
                    'gajian_date' => $gajian1Date,
                    'gajian_date_formatted' => date('d F Y', strtotime($gajian1Date)),
                    'is_available' => $currentDate >= $gajian1Date, // Tersedia mulai tanggal gajian
                    'status' => $payroll->status,
                ];
                
                // Gajian 2: Tanggal 8 bulan berikutnya
                $gajian2 = (object) [
                    'id' => $payroll->id . '_gajian2',
                    'payroll_detail_id' => $payroll->id,
                    'user_id' => $payroll->user_id,
                    'outlet_id' => $payroll->outlet_id,
                    'outlet_name' => $payroll->outlet_name,
                    'month' => $month,
                    'year' => $year,
                    'type' => 'gajian2',
                    'type_label' => 'Gajian 2 (Tanggal 8)',
                    'gajian_date' => $gajian2Date,
                    'gajian_date_formatted' => date('d F Y', strtotime($gajian2Date)),
                    'is_available' => $currentDate >= $gajian2Date, // Tersedia mulai tanggal gajian
                    'status' => $payroll->status,
                ];
                
                // Hanya tambahkan jika sudah tersedia (tanggal sudah lewat atau sama dengan hari ini)
                if ($gajian1->is_available) {
                    $result[] = $gajian1;
                }
                if ($gajian2->is_available) {
                    $result[] = $gajian2;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $result
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
     * Get payroll slip detail by type (gajian1 or gajian2)
     */
    public function getUserPayrollSlipDetail(Request $request)
    {
        try {
            $userId = auth()->id();
            $payrollDetailId = $request->input('payroll_detail_id');
            $type = $request->input('type'); // 'gajian1' or 'gajian2'
            
            if (!$payrollDetailId || !$type) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak lengkap'
                ], 400);
            }
            
            // Ambil data payroll detail
            $payrollDetail = DB::table('payroll_generated_details as pgd')
                ->join('payroll_generated as pg', 'pgd.payroll_generated_id', '=', 'pg.id')
                ->leftJoin('tbl_data_outlet as o', 'pg.outlet_id', '=', 'o.id_outlet')
                ->where('pgd.id', $payrollDetailId)
                ->where('pgd.user_id', $userId)
                ->select('pgd.*', 'pg.month', 'pg.year', 'pg.outlet_id', 'o.nama_outlet as outlet_name')
                ->first();
            
            if (!$payrollDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data payroll tidak ditemukan'
                ], 404);
            }
            
            // Parse JSON fields
            $customItems = $payrollDetail->custom_items ? json_decode($payrollDetail->custom_items, true) : [];
            $leaveData = $payrollDetail->leave_data ? json_decode($payrollDetail->leave_data, true) : [];
            
            // Get leave types
            $leaveTypes = DB::table('leave_types')
                ->where('is_active', 1)
                ->select('id', 'name')
                ->get();
            
            // Build response berdasarkan jenis slip gaji
            $response = [
                'success' => true,
                'data' => [
                    'user_id' => $payrollDetail->user_id,
                    'nik' => $payrollDetail->nik,
                    'nama_lengkap' => $payrollDetail->nama_lengkap,
                    'jabatan' => $payrollDetail->jabatan,
                    'divisi' => $payrollDetail->divisi,
                    'outlet_id' => $payrollDetail->outlet_id,
                    'outlet_name' => $payrollDetail->outlet_name,
                    'month' => $payrollDetail->month,
                    'year' => $payrollDetail->year,
                    'type' => $type,
                    'type_label' => $type === 'gajian1' ? 'Gajian 1 (Akhir Bulan)' : 'Gajian 2 (Tanggal 8)',
                    'periode' => $payrollDetail->periode,
                ]
            ];
            
            if ($type === 'gajian1') {
                // Gajian 1: Akhir bulan
                // 1. Gaji Pokok
                // 2. Tunjangan
                // 3. Custom Deduction
                // 4. Custom Earning
                // 5. Telat
                // 6. Alpha & Unpaid Leave
                // 7. Leave type breakdown
                
                $customDeductions = 0;
                $customEarnings = 0;
                $customDeductionItems = [];
                $customEarningItems = [];
                
                // Parse custom items - bisa berupa array of objects atau array of arrays
                if (is_array($customItems)) {
                    foreach ($customItems as $item) {
                        // Handle both array and object format
                        $itemType = is_array($item) ? ($item['item_type'] ?? $item['type'] ?? null) : ($item->item_type ?? $item->type ?? null);
                        $itemAmount = is_array($item) ? ($item['item_amount'] ?? $item['amount'] ?? 0) : ($item->item_amount ?? $item->amount ?? 0);
                        $itemName = is_array($item) ? ($item['item_name'] ?? $item['name'] ?? '') : ($item->item_name ?? $item->name ?? '');
                        $itemDescription = is_array($item) ? ($item['item_description'] ?? $item['description'] ?? null) : ($item->item_description ?? $item->description ?? null);
                        
                        $itemData = [
                            'name' => $itemName,
                            'type' => $itemType,
                            'amount' => $itemAmount,
                            'description' => $itemDescription,
                        ];
                        
                        if ($itemType === 'deduction') {
                            $customDeductions += $itemAmount;
                            $customDeductionItems[] = $itemData;
                        } else if ($itemType === 'earn') {
                            $customEarnings += $itemAmount;
                            $customEarningItems[] = $itemData;
                        }
                    }
                }
                
                $response['data']['gajian1'] = [
                    'gaji_pokok' => $payrollDetail->gaji_pokok ?? 0,
                    'tunjangan' => $payrollDetail->tunjangan ?? 0,
                    'custom_deductions' => $payrollDetail->custom_deductions ?? 0,
                    'custom_deduction_items' => $customDeductionItems,
                    'custom_earnings' => $payrollDetail->custom_earnings ?? 0,
                    'custom_earning_items' => $customEarningItems,
                    'potongan_telat' => $payrollDetail->potongan_telat ?? 0,
                    'total_alpha' => $payrollDetail->total_alpha ?? 0,
                    'potongan_alpha' => $payrollDetail->potongan_alpha ?? 0,
                    'potongan_unpaid_leave' => $payrollDetail->potongan_unpaid_leave ?? 0,
                    'leave_data' => $leaveData,
                    'leave_types' => $leaveTypes,
                    'total_gaji_gajian1' => ($payrollDetail->gaji_pokok ?? 0) 
                        + ($payrollDetail->tunjangan ?? 0) 
                        + ($payrollDetail->custom_earnings ?? 0) 
                        - ($payrollDetail->custom_deductions ?? 0) 
                        - ($payrollDetail->potongan_telat ?? 0) 
                        - ($payrollDetail->potongan_alpha ?? 0) 
                        - ($payrollDetail->potongan_unpaid_leave ?? 0),
                ];
            } else {
                // Gajian 2: Tanggal 8 bulan berikutnya
                // 1. Service Charge Point
                // 2. Service Charge Prorate
                // 3. Uang Makan
                // 4. Lembur
                // 5. L & B
                // 6. Deviasi
                // 7. City Ledger
                // 8. PH Bonus
                
                $response['data']['gajian2'] = [
                    'service_charge_by_point' => $payrollDetail->service_charge_by_point ?? 0,
                    'service_charge_pro_rate' => $payrollDetail->service_charge_pro_rate ?? 0,
                    'service_charge' => $payrollDetail->service_charge ?? 0,
                    'uang_makan' => $payrollDetail->uang_makan ?? 0,
                    'nominal_uang_makan' => $payrollDetail->nominal_uang_makan ?? 0,
                    'total_lembur' => $payrollDetail->total_lembur ?? 0,
                    'nominal_lembur_per_jam' => $payrollDetail->nominal_lembur_per_jam ?? 0,
                    'gaji_lembur' => $payrollDetail->gaji_lembur ?? 0,
                    'lb_total' => $payrollDetail->lb_total ?? 0,
                    'deviasi_total' => $payrollDetail->deviasi_total ?? 0,
                    'city_ledger_total' => $payrollDetail->city_ledger_total ?? 0,
                    'ph_bonus' => $payrollDetail->ph_bonus ?? 0,
                    'total_gaji_gajian2' => ($payrollDetail->service_charge ?? 0) 
                        + ($payrollDetail->uang_makan ?? 0) 
                        + ($payrollDetail->gaji_lembur ?? 0) 
                        + ($payrollDetail->ph_bonus ?? 0) // PH Bonus ditambahkan sebagai earning
                        - ($payrollDetail->lb_total ?? 0) 
                        - ($payrollDetail->deviasi_total ?? 0) 
                        - ($payrollDetail->city_ledger_total ?? 0),
                ];
            }
            
            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Error getting payroll slip detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil detail slip gaji'
            ], 500);
        }
    }

    /**
     * Calculate PH Bonus (Public Holiday bonus only, not extra_off)
     */
    private function calculatePHBonus($userId, $startDate, $endDate)
    {
        // Get holiday attendance compensations for this user in the period
        // Only count bonus type, not extra_off
        $compensations = DB::table('holiday_attendance_compensations')
            ->where('user_id', $userId)
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->where('compensation_type', 'bonus') // Only bonus, not extra_off
            ->whereIn('status', ['approved', 'used']) // Only count approved or used compensations
            ->get();
        
        // Sum all bonus amounts
        $phBonus = 0;
        foreach ($compensations as $compensation) {
            $phBonus += $compensation->compensation_amount ?? 0;
        }
        
        return $phBonus;
    }

    /**
     * Calculate leave data (breakdown per leave type)
     * COPY PASTE LANGSUNG DARI AttendanceReportController - TIDAK ADA MODIFIKASI
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
        
        // Debug: Log query results
        \Log::info('calculateLeaveData - Query Results', [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'approved_absents_count' => $approvedAbsents->count(),
            'approved_absents' => $approvedAbsents->toArray(),
            'leave_types' => $leaveTypes->toArray()
        ]);
        
        // Initialize result with all leave types
        $result = [];
        foreach ($leaveTypes as $leaveType) {
            $key = strtolower(str_replace(' ', '_', $leaveType->name)) . '_days';
            $result[$key] = 0;
        }
        
        // Group by leave type and calculate days
        $leaveDataByType = [];
        foreach ($approvedAbsents as $absent) {
            $leaveTypeId = $absent->leave_type_id;
            $leaveTypeName = $absent->leave_type_name;
            
            // Calculate days between date_from and date_to
            $fromDate = new \DateTime($absent->date_from);
            $toDate = new \DateTime($absent->date_to);
            $daysCount = $fromDate->diff($toDate)->days + 1;
            
            \Log::info('calculateLeaveData - Processing absent', [
                'user_id' => $userId,
                'leave_type_id' => $leaveTypeId,
                'leave_type_name' => $leaveTypeName,
                'date_from' => $absent->date_from,
                'date_to' => $absent->date_to,
                'days_count' => $daysCount
            ]);
            
            if (!isset($leaveDataByType[$leaveTypeId])) {
                $leaveDataByType[$leaveTypeId] = [
                    'name' => $leaveTypeName,
                    'days' => 0
                ];
            }
            $leaveDataByType[$leaveTypeId]['days'] += $daysCount;
        }
        
        // Map to result format
        foreach ($leaveDataByType as $leaveTypeId => $data) {
            $key = strtolower(str_replace(' ', '_', $data['name'])) . '_days';
            $result[$key] = $data['days'];
            
            \Log::info('calculateLeaveData - Mapped leave data', [
                'leave_type_id' => $leaveTypeId,
                'leave_type_name' => $data['name'],
                'key' => $key,
                'days' => $data['days']
            ]);
        }
        
        // Keep legacy fields for backward compatibility
        $result['extra_off_days'] = $result['extra_off_days'] ?? 0;
        
        \Log::info('calculateLeaveData - Final result', [
            'user_id' => $userId,
            'result' => $result
        ]);
        
        return $result;
    }

    /**
     * Calculate alpa days (days with shift but no attendance and no absent request)
     * COPY PERSIS dari AttendanceReportController::calculateAlpaDays
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
        
        // Get user's shifts for the period - COPY PERSIS dari AttendanceReportController
        $shifts = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->where('us.outlet_id', $outletId)
            ->whereIn('us.tanggal', $period)
            ->whereNotNull('us.shift_id') // Must have a shift (not off)
            ->where('s.shift_name', '!=', 'off') // Exclude 'off' shifts
            ->select('us.tanggal', 's.shift_name')
            ->get()
            ->keyBy('tanggal');
        
        // Get user's attendance data - COPY PERSIS dari AttendanceReportController
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
            // Only count alpa for dates that have already passed (including today)
            if ($date > $today) {
                continue; // Skip future dates
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
     * Calculate alpha days using employeeRows data for consistency
     * This ensures alpha calculation uses the same attendance data as hari kerja calculation
     */
    private function calculateAlpaDaysFromEmployeeRows($userId, $outletId, $startDate, $endDate, $employeeRows)
    {
        // Get all days in the period
        $period = [];
        $dt = new \DateTime($startDate);
        $dtEnd = new \DateTime($endDate);
        while ($dt <= $dtEnd) {
            $period[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }
        
        // Get attendance dates from employeeRows (days with actual attendance)
        $attendanceDates = $employeeRows->pluck('tanggal')->unique()->toArray();
        
        // Get user's shifts for the period - COPY PERSIS dari AttendanceReportController
        $shifts = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->where('us.outlet_id', $outletId)
            ->whereIn('us.tanggal', $period)
            ->whereNotNull('us.shift_id') // Must have a shift (not off)
            ->where('s.shift_name', '!=', 'off') // Exclude 'off' shifts
            ->select('us.tanggal', 's.shift_name')
            ->get()
            ->keyBy('tanggal');
        
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
            // Only count alpa for dates that have already passed (including today) - COPY PERSIS dari AttendanceReportController
            if ($date > $today) {
                continue; // Skip future dates
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
