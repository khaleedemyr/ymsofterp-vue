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
use App\Services\PayrollBpjsCalculator;
use App\Services\PayrollGajiSplitCalculator;
use App\Services\PayrollKasbonService;
use App\Services\PayrollSplitPoolCalculator;
use App\Services\PayrollGeneratePhaseService;

class PayrollReportController extends Controller
{
    private function attendanceReportHelper(): AttendanceReportController
    {
        return app(AttendanceReportController::class);
    }

    private function defaultPayrollMasterData(): object
    {
        return (object) [
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
        ];
    }

    private function resolvePayrollScopeForUser(User $user, $outletId): array
    {
        $effectiveOutletId = !empty($outletId) ? (int) $outletId : (int) $user->id_outlet;
        $effectiveDivisionId = (int) $user->division_id;

        return [$effectiveOutletId, $effectiveDivisionId];
    }

    private function buildPayrollMasterLookup($outletId = null)
    {
        return DB::table('payroll_master')
            ->when($outletId, function ($query) use ($outletId) {
                $query->where('outlet_id', $outletId);
            })
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('user_id');
    }

    private function resolvePayrollMasterForUser($user, $payrollMasterRows, $outletId, ?object $default = null): object
    {
        $default ??= $this->defaultPayrollMasterData();

        [$effectiveOutletId, $effectiveDivisionId] = $this->resolvePayrollScopeForUser($user, $outletId);

        $payroll = collect($payrollMasterRows->get($user->id, []))->first(function ($row) use ($effectiveOutletId, $effectiveDivisionId) {
            return (int) $row->outlet_id === $effectiveOutletId
                && (int) $row->division_id === $effectiveDivisionId;
        });

        return $payroll ?? $default;
    }

    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        $serviceChargeInput = $request->input('service_charge', null); // Input manual service charge (nullable)

        $payrollGeneratedHeader = null;
        if ($outletId && $month && $year) {
            $payrollGeneratedHeader = DB::table('payroll_generated')
                ->where('outlet_id', $outletId)
                ->where('month', (int) $month)
                ->where('year', (int) $year)
                ->first();
        }

        $lbAmount = $request->filled('lb_amount')
            ? (float) $request->input('lb_amount')
            : (float) ($payrollGeneratedHeader?->lb_amount ?? 0);
        $deviasiAmount = $request->filled('deviasi_amount')
            ? (float) $request->input('deviasi_amount')
            : (float) ($payrollGeneratedHeader?->deviasi_amount ?? 0);
        $cityLedgerAmount = $request->filled('city_ledger_amount')
            ? (float) $request->input('city_ledger_amount')
            : (float) ($payrollGeneratedHeader?->city_ledger_amount ?? 0);

        // Hitung service charge dari orders jika tidak ada input manual
        $serviceCharge = 0;
        if ($serviceChargeInput !== null && $serviceChargeInput !== '') {
            $serviceCharge = (float) $serviceChargeInput;
        } elseif ($payrollGeneratedHeader) {
            $serviceCharge = (float) ($payrollGeneratedHeader->service_charge ?? 0);
        } elseif ($outletId && $month && $year) {
            // Jika tidak ada input manual, ambil dari orders
            // Periode service charge: 1-31 dari bulan yang dipilih
            $serviceChargeStart = Carbon::create($year, $month, 1)->startOfDay();
            $serviceChargeEnd = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
            
            // Get outlet qr_code untuk filter orders
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->where('status', 'A')
                ->first(['qr_code']);
            
            if ($outlet && $outlet->qr_code) {
                // Sum service charge dari orders
                $serviceChargeResult = DB::table('orders')
                    ->where('kode_outlet', $outlet->qr_code)
                    ->whereBetween('created_at', [$serviceChargeStart, $serviceChargeEnd])
                    ->where('status', '!=', 'cancelled') // Exclude cancelled orders
                    ->sum('service');
                
                // Ambil 80% dari total service charge
                $serviceCharge = (float)($serviceChargeResult ?? 0) * 0.8;
            }
        }

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
        $mutatedEmployeeIds = []; // Initialize mutatedEmployeeIds to avoid undefined variable error
        $poolTotalHariKerjaGajian2 = 0;
        $poolTotalPointHariKerja = 0;

        if ($outletId && $month && $year) {
            // Hitung periode payroll (26 bulan sebelumnya - 25 bulan yang dipilih)
            // SAMA PERSIS dengan Employee Summary
            // Contoh: bulan 12 tahun 2025 = 26 Nov 2025 - 25 Des 2025
            $start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
            $end = date('Y-m-d', strtotime("$year-$month-25"));
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
            $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

            // Ambil data karyawan di outlet tersebut (HANYA yang aktif) - KEMBALIKAN KE LOGIKA SEMULA
            $users = User::where('status', 'A')
                ->where('id_outlet', $outletId)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);

            // Ambil data resignation untuk periode tersebut (status approved dan resignation_date dalam periode)
            // HANYA karyawan yang resign di periode ini yang akan muncul
            // PERBAIKAN: Ambil semua resignations yang approved dalam periode, lalu filter berdasarkan outlet user
            // TIDAK memfilter berdasarkan created_at atau approved_at, HANYA berdasarkan resignation_date
            $allResignations = $this->queryApprovedResignationsForPayroll($start, $end, (int) $year, (int) $month);
            
            // Filter resignations berdasarkan outlet user (bukan outlet di resignation)
            // Ambil employee_id dari resignations yang memiliki user dengan outlet yang sesuai
            $resignedEmployeeIds = $allResignations->pluck('employee_id')->toArray();
            if (!empty($resignedEmployeeIds)) {
                // Ambil user yang resign dengan outlet yang sesuai - TIDAK memfilter status (bisa 'A' atau 'N')
                $resignedUsers = User::whereIn('id', $resignedEmployeeIds)
                    ->where('id_outlet', $outletId)
                    ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);
                
                // Buat collection resignations yang sudah difilter berdasarkan outlet user
                $resignedUserIds = $resignedUsers->pluck('id')->toArray();
                $resignations = $allResignations->whereIn('employee_id', $resignedUserIds)
                ->keyBy('employee_id');

            // Tambahkan karyawan yang resign di periode ini ke list users (jika belum ada)
                $existingUserIds = $users->pluck('id')->toArray();
                $newResignedIds = array_diff($resignedUserIds, $existingUserIds);
                if (!empty($newResignedIds)) {
                    $newResignedUsers = $resignedUsers->whereIn('id', $newResignedIds);
                    $users = $users->merge($newResignedUsers);
                }
            } else {
                $resignations = collect()->keyBy('employee_id');
            }

            // Ambil data karyawan yang mutasi dari outlet ini dalam periode payroll
            // Mutasi berdasarkan effective_date, bukan status
            // PENTING: User yang mutasi mungkin sudah pindah ke outlet baru, jadi id_outlet mereka sudah bukan outlet asal
            // Tapi kita tetap perlu include mereka karena mereka bekerja di outlet asal untuk sebagian periode
            // PERBAIKAN: Ada 2 jenis payroll:
            // - Gajian 1 (akhir bulan): periode 26 bulan sebelumnya - 25 bulan saat ini (untuk gaji pokok, tunjangan, dll)
            // - Gajian 2 (tanggal 8): periode 1-30/31 bulan saat ini (untuk service charge, L&B, Deviasi, City Ledger)
            // Untuk mutasi, kita perlu cek 2 periode:
            // 1. Periode gajian 1 (26-25) - untuk karyawan yang mutasi dalam periode ini
            // 2. Periode gajian 2 (1-30) - untuk karyawan yang mutasi dalam periode ini (service charge dll)
            // Jadi kita ambil mutasi yang effective_date antara tanggal 1 bulan yang dipilih sampai akhir periode gajian 1
            $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
            $mutations = collect();
            $mutationMap = [];
            if ($outletName) {
                $mutations = $this->collectMutationsForPayrollOutlet(
                    (int) $outletId,
                    $outletName,
                    $start,
                    $end,
                    $gajian2Start,
                    $gajian2End
                );
                $mutationMap = $this->buildPayrollMutationMap($mutations, (int) $outletId, $outletName);
                $users = $this->mergeMutatedUsersIntoPayrollUsers($users, $mutations);
                $users = $this->filterPayrollUsersForMutationEffectiveDate(
                    $users,
                    $mutationMap,
                    $startDate,
                    $endDate,
                    $gajian2End,
                    (int) $outletId,
                    $outletName
                );

                \Log::info('Payroll - Mutations found and mapped', [
                    'outlet_id' => $outletId,
                    'outlet_name' => $outletName,
                    'start_date' => $start,
                    'end_date' => $end,
                    'mutations_count' => $mutations->count(),
                    'mutation_map' => $mutationMap,
                ]);
            }

            // Simpan mutations keyed by employee_id untuk digunakan nanti
            $mutationsByEmployee = $mutations->keyBy('employee_id');

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
            
            // Ambil data level (dasar potongan BPJS kesehatan & ketenagakerjaan)
            $dataLevelRowsById = DB::table('tbl_data_level')->get()->keyBy('id');
            $levelBpjsKategoriId = DB::table('tbl_data_level')
                ->pluck('id_bpjs_kategori', 'id');
            $bpjsKategoriById = DB::table('tbl_bpjs_kategori')
                ->where('status', 'A')
                ->get()
                ->keyBy('id');

            // Ambil data master payroll per outlet + divisi (sama seperti Master Payroll)
            $payrollMaster = $this->buildPayrollMasterLookup($outletId);

            // Cek apakah payroll sudah di-generate
            $payrollGenerated = DB::table('payroll_generated')
                ->where('outlet_id', $outletId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();
            
            $payrollGeneratedDetails = collect();
            $payrollGeneratedDetailsFull = collect();
            $payrollPhaseService = app(PayrollGeneratePhaseService::class);
            $gajian1Saved = false;
            $gajian2Saved = false;
            $gajian1Locked = false;
            $gajian2Locked = false;

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

                [$gajian1Saved, $gajian2Saved] = $payrollPhaseService->resolveLegacyPhaseSavedFlags($payrollGenerated);
                $gajian1Locked = ($payrollGenerated->gajian1_status ?? '') === 'locked';
                $gajian2Locked = ($payrollGenerated->gajian2_status ?? '') === 'locked';
            }
            
            // Debug: Log status payroll generated
            \Log::info('Payroll - Check generated status', [
                'payroll_generated_exists' => $payrollGenerated ? true : false,
                'payroll_generated_details_count' => $payrollGeneratedDetailsFull->count(),
                'gajian1_saved' => $gajian1Saved,
                'gajian2_saved' => $gajian2Saved,
                'outlet_id' => $outletId,
                'month' => $month,
                'year' => $year
            ]);
            
            // Snapshot penuh dari DB hanya jika kedua fase sudah di-lock.
            // Status "generated" tetap hitung ulang agar SC/L&B/deviasi/city ledger mengikuti logic terbaru.
            if ($payrollGenerated && $payrollGeneratedDetailsFull->isNotEmpty() && $gajian1Locked && $gajian2Locked) {
                \Log::info('Payroll - Using generated payroll data');
                // Ambil data karyawan untuk mapping (include status untuk perhitungan statistik)
                $users = User::whereIn('id', $payrollGeneratedDetailsFull->pluck('user_id'))
                    ->where('id_outlet', $outletId)
                    ->orderBy('nama_lengkap')
                    ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);
                
                // PENTING: Tambahkan juga karyawan yang mutasi dari outlet ini (employee_movements)
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
                $mutations = collect();
                if ($outletName) {
                    $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
                    $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

                    $mutations = $this->collectMutationsForPayrollOutlet(
                        (int) $outletId,
                        $outletName,
                        $start,
                        $end,
                        $gajian2Start,
                        $gajian2End
                    );
                    $users = $this->mergeMutatedUsersIntoPayrollUsers($users, $mutations);
                    $mutationMap = $this->buildPayrollMutationMap($mutations, (int) $outletId, $outletName);
                    $users = $this->filterPayrollUsersForMutationEffectiveDate(
                        $users,
                        $mutationMap,
                        $startDate,
                        $endDate,
                        $gajian2End,
                        (int) $outletId,
                        $outletName
                    );

                    \Log::info('Payroll Generated - Mutations found', [
                        'outlet_id' => $outletId,
                        'outlet_name' => $outletName,
                        'mutations_count' => $mutations->count(),
                    ]);
                }
                $mutationMap = $mutationMap ?? [];

                // Simpan mutations keyed by employee_id untuk digunakan nanti
                $mutationsByEmployee = $mutations->keyBy('employee_id');
                
                // Ambil data resignation untuk periode tersebut
                // PERBAIKAN: Ambil semua resignations yang approved dalam periode, lalu filter berdasarkan outlet user
                // TIDAK memfilter berdasarkan created_at atau approved_at, HANYA berdasarkan resignation_date
                $allResignations = $this->queryApprovedResignationsForPayroll($start, $end, (int) $year, (int) $month);
                
                // Filter resignations berdasarkan outlet user (bukan outlet di resignation)
                // TIDAK memfilter status user (bisa 'A' atau 'N')
                $resignedEmployeeIds = $allResignations->pluck('employee_id')->toArray();
                if (!empty($resignedEmployeeIds)) {
                    // Ambil user yang resign dengan outlet yang sesuai - TIDAK memfilter status
                    $resignedUsers = User::whereIn('id', $resignedEmployeeIds)
                        ->where('id_outlet', $outletId)
                        ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);
                    
                    $resignedUserIds = $resignedUsers->pluck('id')->toArray();
                    $resignations = $allResignations->whereIn('employee_id', $resignedUserIds)
                    ->keyBy('employee_id');
                    
                    // PERBAIKAN: Tambahkan karyawan yang resign ke list users jika belum ada di payroll_generated_details
                    $existingUserIds = $users->pluck('id')->toArray();
                    $newResignedIds = array_diff($resignedUserIds, $existingUserIds);
                    if (!empty($newResignedIds)) {
                        $newResignedUsers = $resignedUsers->whereIn('id', $newResignedIds);
                        $users = $users->merge($newResignedUsers);
                    }
                } else {
                    $resignations = collect()->keyBy('employee_id');
                }
                
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
                // PERBAIKAN: Loop berdasarkan $users untuk memastikan semua user (termasuk yang resign) diproses
                foreach ($users as $user) {
                    $userId = $user->id;
                    $detail = $payrollGeneratedDetailsFull->get($userId);
                    
                    // Jika user tidak ada di payroll_generated_details, buat detail kosong
                    if (!$detail) {
                        $detail = (object)[
                            'nik' => $user->nik,
                            'nama_lengkap' => $user->nama_lengkap,
                            'no_rekening' => $user->no_rekening,
                            'gaji_pokok' => 0,
                            'tunjangan' => 0,
                            'total_telat' => 0,
                            'total_lembur' => 0,
                            'nominal_lembur_per_jam' => 0,
                            'gaji_lembur' => 0,
                            'nominal_uang_makan' => 0,
                            'uang_makan' => 0,
                            'service_charge_by_point' => 0,
                            'service_charge_pro_rate' => 0,
                            'service_charge' => 0,
                            'bpjs_jkn' => 0,
                            'bpjs_tk' => 0,
                            'bpjs_perusahaan_detail' => null,
                            'lb_by_point' => 0,
                            'lb_pro_rate' => 0,
                            'lb_total' => 0,
                            'deviasi_by_point' => 0,
                            'deviasi_pro_rate' => 0,
                            'deviasi_total' => 0,
                            'city_ledger_by_point' => 0,
                            'city_ledger_pro_rate' => 0,
                            'city_ledger_total' => 0,
                            'ph_bonus' => 0,
                            'total_gaji_akhir_bulan' => 0,
                            'total_gaji_tanggal_8' => 0,
                            'total_gaji' => 0,
                            'hari_kerja' => 0,
                            'gaji_per_menit' => 500,
                            'potongan_telat' => 0,
                            'total_alpha' => 0,
                            'potongan_alpha' => 0,
                            'potongan_unpaid_leave' => 0,
                            'custom_items' => '[]',
                            'leave_data' => '[]',
                            'periode' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                            'payment_method' => 'transfer',
                            'jabatan' => null,
                            'divisi' => null,
                            'point' => 0,
                        ];
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
                    
                    // Ambil data master payroll untuk user ini (untuk setting enabled/disabled)
                    $masterData = $this->resolvePayrollMasterForUser($user, $payrollMaster, $outletId);
                    
                    // Hitung total gaji dari komponen (gajian 1 + gajian 2)
                    $gajiSplit = PayrollGajiSplitCalculator::calculate([
                        'gaji_pokok' => $detail->gaji_pokok ?? 0,
                        'tunjangan' => $detail->tunjangan ?? 0,
                        'custom_earnings_gajian1' => $customEarningsGajian1,
                        'custom_deductions_gajian1' => $customDeductionsGajian1,
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
                        'custom_earnings_gajian2' => $customEarningsGajian2,
                        'custom_deductions_gajian2' => $customDeductionsGajian2,
                        'lb_total' => $detail->lb_total ?? 0,
                        'deviasi_total' => $detail->deviasi_total ?? 0,
                        'city_ledger_total' => $detail->city_ledger_total ?? 0,
                    ]);
                    $totalGajiAkhirBulan = $gajiSplit['total_gaji_akhir_bulan'];
                    $totalGajiTanggal8 = $gajiSplit['total_gaji_tanggal_8'];
                    $totalGaji = $gajiSplit['total_gaji'];
                    
                    // Cek mutasi outlet (badge & info tampilan)
                    $isMutatedEmployee = isset($mutationMap[$user->id]);
                    $mutationData = $isMutatedEmployee ? $mutationMap[$user->id] : null;

                    // Format data sesuai dengan struktur yang dibutuhkan frontend
                    $payrollDataItem = [
                        'user_id' => $user->id,
                        'nik' => $detail->nik ?? $user->nik,
                        'nama_lengkap' => $detail->nama_lengkap ?? $user->nama_lengkap,
                        'no_rekening' => $detail->no_rekening ?? $user->no_rekening ?? null,
                        'tanggal_masuk' => $user->tanggal_masuk ?? null,
                        'is_new_employee' => $isNewEmployee,
                        'is_mutated_employee' => $isMutatedEmployee,
                        'mutation_effective_date' => $mutationData
                            ? Carbon::parse($mutationData['effective_date'])->format('Y-m-d')
                            : null,
                        'mutation_outlet_from' => $mutationData['outlet_from_name'] ?? null,
                        'mutation_outlet_to' => $mutationData['outlet_to_name'] ?? null,
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
                        'bpjs_perusahaan_detail' => ! empty($detail->bpjs_perusahaan_detail)
                            ? (json_decode($detail->bpjs_perusahaan_detail, true) ?: null)
                            : null,
                        'lb_by_point' => round($detail->lb_by_point ?? 0),
                        'lb_pro_rate' => round($detail->lb_pro_rate ?? 0),
                        'lb_total' => round($detail->lb_total ?? 0),
                        'deviasi_by_point' => round($detail->deviasi_by_point ?? 0),
                        'deviasi_pro_rate' => round($detail->deviasi_pro_rate ?? 0),
                        'deviasi_total' => round($detail->deviasi_total ?? 0),
                        'city_ledger_by_point' => round($detail->city_ledger_by_point ?? 0),
                        'city_ledger_pro_rate' => round($detail->city_ledger_pro_rate ?? 0),
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
                        'potongan_kasbon' => round($detail->potongan_kasbon ?? 0),
                        'pr_kasbon_id' => $detail->pr_kasbon_id ?? null,
                        'kasbon_cicilan_ke' => $detail->kasbon_cicilan_ke ?? null,
                        'kasbon_pr_number' => null,
                        'total_gaji_akhir_bulan' => $totalGajiAkhirBulan,
                        'total_gaji_tanggal_8' => $totalGajiTanggal8,
                        'total_gaji' => round($totalGaji),
                        'hari_kerja' => $detail->hari_kerja ?? 0,
                        'total_izin_cuti' => $totalIzinCuti,
                        'izin_cuti_breakdown' => $izinCutiBreakdown,
                        'extra_off_days' => isset($leaveData['extra_off_days']) ? $leaveData['extra_off_days'] : 0,
                        'leave_data' => $leaveData,
                        'periode' => $detail->periode ?? ($startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y')),
                        'payment_method' => $detail->payment_method ?? 'transfer',
                        'master_data' => $masterData, // PENTING: Tambahkan master_data untuk setting enabled/disabled
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
                \Log::info('Payroll - Returning generated payroll data to frontend', [
                    'payroll_data_count' => $payrollData->count()
                ]);
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
                    'u.division_id',
                    'o.id_outlet as outlet_id'
                )
                ->where('a.scan_date', '>=', $start . ' 00:00:00')
                ->where('a.scan_date', '<', $gajian2End->copy()->addDay()->format('Y-m-d') . ' 00:00:00');
            
            // Apply filter outlet - SAMA PERSIS dengan employeeSummary
            // PERBAIKAN: Jangan filter berdasarkan outlet untuk karyawan mutasi, karena attendance tidak berdasarkan outlet
            // Tapi tetap filter untuk karyawan biasa untuk performa
            if (!empty($outletId)) {
                // Ambil dulu list employee_id yang mutasi
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
                $mutatedEmployeeIdsForFilter = [];
                if ($outletName) {
                    $mutationsForFilter = $this->collectMutationsForPayrollOutlet(
                        (int) $outletId,
                        $outletName,
                        $start,
                        $end,
                        $gajian2Start,
                        $gajian2End
                    );
                    $mutatedEmployeeIdsForFilter = $mutationsForFilter->pluck('employee_id')->toArray();
                }
                
                // Filter berdasarkan outlet, TAPI exclude karyawan mutasi (karena mereka perlu attendance dari semua outlet)
                if (!empty($mutatedEmployeeIdsForFilter)) {
                    $sub->where(function($q) use ($outletId, $mutatedEmployeeIdsForFilter) {
                        $q->where('u.id_outlet', $outletId)
                          ->orWhereIn('u.id', $mutatedEmployeeIdsForFilter);
                    });
                } else {
                    $sub->where('u.id_outlet', $outletId);
                }
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
                    'inoutmode' => $scan->inoutmode,
                    'outlet_id' => $scan->outlet_id ?? null,
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
                    $row->lembur = floor(app(\App\Services\AttendanceWorkTimelineService::class)->calculateOvertimeHours(
                        (int) ($row->work_minutes ?? 0),
                        $shiftData->time_start,
                        $shiftData->time_end
                    ));
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
                $shiftKey = $row->user_id . '_' . $row->tanggal;
                $shift = $allShiftData->get($shiftKey, collect())->first();
                $isOffDay = $this->attendanceReportHelper()->isShiftOff($shift);
                $telatLembur = $this->attendanceReportHelper()->calculateDailyTelatLembur($row, $shift, $row->tanggal, $isOffDay);
                $telat = $telatLembur['telat'];
                $lembur = $telatLembur['lembur'];

                $dayRow = (object) [
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
                ];
                $this->attendanceReportHelper()->enrichAttendanceDayRow($dayRow, $shift, $holidays);

                if (! $this->attendanceReportHelper()->shouldIncludeAttendanceSummaryRow($dayRow)) {
                    continue;
                }

                $rows->push($dayRow);
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

            // Debug: Log bahwa kita masuk ke path non-generated payroll
            \Log::info('Payroll - Using non-generated payroll path', [
                'users_count' => $users->count(),
                'employee_groups_count' => $employeeGroups->count(),
                'start_date' => $start,
                'end_date' => $end
            ]);

            // Step 1: Hitung semua data dasar untuk semua user - GUNAKAN DATA DARI EMPLOYEE GROUPS
            // PERBAIKAN: Loop berdasarkan $users untuk memastikan semua user (termasuk yang resign tanpa absensi) diproses
            $userData = [];
            foreach ($users as $user) {
                $userId = $user->id;
                
                // Debug: Log masuk ke loop user
                \Log::info('Payroll - Processing user', [
                    'user_id' => $userId,
                    'nama_lengkap' => $user->nama_lengkap ?? 'Unknown'
                ]);
                
                $allEmployeeRows = $employeeGroups->get($userId, collect());
                // Gajian 1 (26–25): telat, lembur, uang makan, hari_kerja tampilan
                $employeeRows = $this->filterAttendanceRowsForDateRange($allEmployeeRows, $startDate, $endDate);
                
                // Jika user tidak punya data absensi, buat employeeRows kosong
                if ($employeeRows->isEmpty()) {
                    $firstRow = null;
                } else {
                $firstRow = $employeeRows->first();
                }

                // Ambil data master payroll untuk user ini
                $masterData = $this->resolvePayrollMasterForUser($user, $payrollMaster, $outletId);

                // Mutasi outlet: tentukan segmen gajian 1 sebelum hitung absensi/alpha/leave
                $isMutatedEmployee = isset($mutationMap[$userId]);
                $mutationData = $isMutatedEmployee ? $mutationMap[$userId] : null;
                $mutationRole = null;
                $mutationEffectiveDate = null;
                $hariKerjaGajian2 = 0;
                $hariKerjaOutletLama = 0;
                $hariKerjaOutletBaru = 0;
                $mutationGajian1Ratio = 1.0;
                $mutationGajian2Ratio = 1.0;
                $gajian1SegmentStart = $startDate->copy();
                $gajian1SegmentEnd = $endDate->copy();

                if ($isMutatedEmployee && $mutationData) {
                    $mutCtx = $this->resolveMutationPayrollContext(
                        $mutationData,
                        $startDate,
                        $endDate,
                        $gajian2Start,
                        $gajian2End
                    );
                    $mutationRole = $mutCtx['mutationRole'];
                    $mutationEffectiveDate = $mutCtx['mutationEffectiveDate'];
                    $hariKerjaGajian2 = $mutCtx['hariKerjaGajian2'];
                    $hariKerjaOutletLama = $mutCtx['hariKerjaOutletLama'];
                    $hariKerjaOutletBaru = $mutCtx['hariKerjaOutletBaru'];
                    $ratios = $this->buildMutationPayrollRatios(
                        $mutCtx['hariKerjaGajian1'],
                        $hariKerjaGajian2,
                        $startDate,
                        $endDate,
                        $gajian2Start,
                        $gajian2End
                    );
                    $mutationGajian1Ratio = $ratios['gajian1'];
                    $mutationGajian2Ratio = $ratios['gajian2'];

                    $segment = PayrollSplitPoolCalculator::resolveMutationDateSegment(
                        $mutationEffectiveDate,
                        $mutationRole,
                        $startDate,
                        $endDate
                    );
                    if ($segment) {
                        $gajian1SegmentStart = $segment['start'];
                        $gajian1SegmentEnd = $segment['end'];
                        $employeeRows = $this->filterAttendanceRowsForMutationSegment(
                            $employeeRows,
                            $mutationEffectiveDate,
                            $mutationRole
                        );
                    } else {
                        $employeeRows = collect();
                    }
                }

                $gajian1SegmentStartStr = $gajian1SegmentStart->format('Y-m-d');
                $gajian1SegmentEndStr = $gajian1SegmentEnd->format('Y-m-d');

                // Hitung total telat dan lembur dari baris absensi (sudah difilter segmen mutasi bila ada)
                $totalTelat = $this->attendanceReportHelper()->sumTelatFromAttendanceRows($employeeRows);
                $extraOffOvertimeTotal = floor($this->getExtraOffOvertimeHoursForPeriod(
                    $userId,
                    $gajian1SegmentStart,
                    $gajian1SegmentEnd
                ));
                $totalLemburRegular = floor($employeeRows->sum('lembur'));
                $totalLembur = floor($totalLemburRegular + $extraOffOvertimeTotal);

                $hariKerjaAttendance = $this->attendanceReportHelper()->countHariKerjaFromRows($employeeRows);
                $hariKerjaMutationSc = ($isMutatedEmployee && $mutationRole)
                    ? $this->countMutationSegmentScDays(
                        $userId,
                        (int) $outletId,
                        $gajian1SegmentStart,
                        $gajian1SegmentEnd,
                        $mutationRole
                    )
                    : null;
                $hariKerja = $this->resolveHariKerjaForPayrollSegment(
                    $isMutatedEmployee,
                    $mutCtx ?? null,
                    $hariKerjaAttendance,
                    $hariKerjaMutationSc
                );

                $totalAlpha = $this->calculateAlpaDays($userId, null, $gajian1SegmentStartStr, $gajian1SegmentEndStr);
                $leaveData = $this->calculateLeaveData($userId, $gajian1SegmentStartStr, $gajian1SegmentEndStr);

                $izinCutiBreakdown = [];
                $totalIzinCuti = 0;
                foreach ($leaveData as $key => $value) {
                    if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                        $izinCutiBreakdown[$key] = $value;
                        $totalIzinCuti += $value;
                    }
                }

                $phBonus = $this->calculatePHBonus($userId, $gajian1SegmentStartStr, $gajian1SegmentEndStr);

                if ($isMutatedEmployee && $mutationData) {
                    \Log::info('Payroll - Mutated employee segment metrics', [
                        'user_id' => $userId,
                        'employee_name' => $mutationData['employee_name'],
                        'effective_date' => $mutationEffectiveDate->format('Y-m-d'),
                        'mutation_role' => $mutationRole,
                        'segment_start' => $gajian1SegmentStartStr,
                        'segment_end' => $gajian1SegmentEndStr,
                        'hari_kerja_gajian1' => $hariKerja,
                        'hari_kerja_gajian2' => $hariKerjaGajian2,
                        'gajian1_ratio' => $mutationGajian1Ratio,
                        'gajian2_ratio' => $mutationGajian2Ratio,
                        'attendance_in_segment' => $hariKerjaAttendance,
                        'total_lembur' => $totalLembur,
                        'total_telat' => $totalTelat,
                    ]);
                }

                // Debug: Log PH Bonus untuk semua user (termasuk yang 0)
                \Log::info('Payroll - PH Bonus calculated', [
                    'user_id' => $userId,
                    'nama_lengkap' => $user->nama_lengkap ?? 'Unknown',
                    'start_date' => $gajian1SegmentStartStr,
                    'end_date' => $gajian1SegmentEndStr,
                    'ph_bonus' => $phBonus,
                    'ph_bonus_type' => gettype($phBonus)
                ]);

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
                $tanggalMasuk = null;
                if ($user->tanggal_masuk) {
                    $tanggalMasuk = Carbon::parse($user->tanggal_masuk);
                    // HANYA dianggap karyawan baru jika tanggal masuk BENAR-BENAR dalam periode payroll
                    $isNewEmployee = $tanggalMasuk->greaterThanOrEqualTo($startDate) && $tanggalMasuk->lessThanOrEqualTo($endDate);
                    
                    // Jika karyawan baru, hitung hari kerja dari tanggal masuk sampai akhir periode
                    if ($isNewEmployee) {
                        // Hitung hari kerja dari tanggal masuk sampai akhir periode (hitung hari kalender)
                        $hariKerjaKaryawanBaru = $tanggalMasuk->diffInDays($endDate) + 1; // +1 untuk include tanggal masuk dan tanggal akhir
                    }
                }
                
                // Debug: Log untuk memastikan deteksi karyawan baru benar
                if ($isNewEmployee) {
                    \Log::info('Detected new employee', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'tanggal_masuk' => $user->tanggal_masuk,
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'is_new_employee' => $isNewEmployee,
                    ]);
                }
                
                // Cek apakah karyawan resign (resignation_date dalam periode gajian 1 atau gajian 2)
                $resignation = $resignations->get($user->id);
                $resignCtx = $this->resolveResignationPayrollContext(
                    $resignation,
                    $hariKerja,
                    $startDate,
                    $endDate,
                    $gajian2Start,
                    $gajian2End
                );
                $isResignedEmployee = $resignCtx['isResignedEmployee'];
                $affectsGajian2 = $resignCtx['affectsGajian2'];
                $resignationDate = $resignCtx['resignationDate'];
                $hariKerjaKaryawanResign = $resignCtx['hariKerjaKaryawanResign'];
                
                // Debug: Log untuk memastikan deteksi karyawan resign benar
                if ($isResignedEmployee || $affectsGajian2) {
                    \Log::info('Detected resigned employee', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'resignation_date' => $resignation ? $resignation->resignation_date : null,
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'gajian2_start' => $gajian2Start->format('Y-m-d'),
                        'gajian2_end' => $gajian2End->format('Y-m-d'),
                        'is_resigned_employee' => $isResignedEmployee,
                        'affects_gajian2' => $affectsGajian2,
                    ]);
                }
                
                if ($isNewEmployee && $isResignedEmployee && $tanggalMasuk && $resignationDate) {
                    $hariKerjaProrateGajian1 = $tanggalMasuk->diffInDays($resignationDate) + 1;
                } elseif ($isNewEmployee) {
                    $hariKerjaProrateGajian1 = $hariKerjaKaryawanBaru;
                } elseif ($isResignedEmployee) {
                    $hariKerjaProrateGajian1 = $hariKerjaKaryawanResign;
                } else {
                    $hariKerjaProrateGajian1 = $hariKerja;
                }

                // Hari kerja gajian 2 (absensi 1–akhir bulan) — komponen payroll akhir bulan
                $hariKerjaGajian2Attendance = $this->countHariKerjaGajian2Attendance(
                    $allEmployeeRows,
                    $gajian2Start,
                    $gajian2End,
                    $affectsGajian2 ? $resignationDate : null,
                    $this->resolveTanggalMasukForGajian2Pool($tanggalMasuk, $gajian2Start),
                    $isMutatedEmployee ? $mutationEffectiveDate : null,
                    $isMutatedEmployee ? $mutationRole : null
                );

                $hariKerjaUntukServiceCharge = 0;

                if ($isMutatedEmployee && $hariKerja <= 0) {
                    $hariKerjaGajian2 = 0;
                    $hariKerjaProrateGajian1 = 0;
                } else {
                    $this->syncGajian1ProrateDaysWithAttendance(
                        $hariKerja,
                        $hariKerjaKaryawanBaru,
                        $hariKerjaKaryawanResign,
                        $isNewEmployee,
                        $isResignedEmployee
                    );
                    if ($isNewEmployee) {
                        $hariKerjaProrateGajian1 = $hariKerjaKaryawanBaru;
                    } elseif ($isResignedEmployee) {
                        $hariKerjaProrateGajian1 = $hariKerjaKaryawanResign;
                    }
                    $hariKerjaUntukServiceCharge = PayrollSplitPoolCalculator::resolveGajian1PoolDays($hariKerja);
                }

                $mutationOutletFrom = $isMutatedEmployee ? ($mutationData['outlet_from_name'] ?? null) : null;
                $mutationOutletTo = $isMutatedEmployee ? ($mutationData['outlet_to_name'] ?? null) : null;

                // Simpan data user untuk perhitungan service charge
                $userData[$user->id] = [
                    'user' => $user,
                    'masterData' => $masterData,
                    'employeeRows' => $employeeRows, // Simpan employeeRows untuk digunakan nanti
                    'totalTelat' => $totalTelat,
                    'totalLembur' => $totalLembur,
                    'hariKerja' => $hariKerja, // Hari kerja gajian 1 (kalender) di outlet ini
                    'hariKerjaKaryawanBaru' => $hariKerjaKaryawanBaru, // Hari kerja untuk karyawan baru
                    'hariKerjaKaryawanResign' => $hariKerjaKaryawanResign, // Hari kerja untuk karyawan resign
                    'hariKerjaUntukServiceCharge' => $hariKerjaUntukServiceCharge, // Hari kerja pool SC (gajian 1)
                    'hariKerjaProrateGajian1' => $hariKerjaProrateGajian1,
                    'hariKerjaGajian2' => $hariKerjaGajian2,
                    'hariKerjaGajian2Attendance' => $hariKerjaGajian2Attendance,
                    'mutationGajian1Ratio' => $mutationGajian1Ratio,
                    'mutationGajian2Ratio' => $mutationGajian2Ratio,
                    'isNewEmployee' => $isNewEmployee, // Flag apakah karyawan baru
                    'isResignedEmployee' => $isResignedEmployee, // Flag apakah karyawan resign (gajian 1)
                    'affectsGajian2' => $affectsGajian2, // Flag resign mempengaruhi pool gajian 2
                    'resignationDate' => $resignationDate, // Tanggal resign efektif
                    'tanggalMasuk' => $tanggalMasuk, // Untuk prorate gajian 2 karyawan baru+resign
                    'isMutatedEmployee' => $isMutatedEmployee, // Flag apakah karyawan mutasi outlet
                    'mutationRole' => $mutationRole,
                    'mutationEffectiveDate' => $mutationEffectiveDate, // Tanggal efektif mutasi
                    'mutationOutletFrom' => $mutationOutletFrom,
                    'mutationOutletTo' => $mutationOutletTo, // Outlet tujuan mutasi
                    'mutationData' => $mutationData, // Full mutation data
                    'hariKerjaOutletLama' => $hariKerjaOutletLama,
                    'hariKerjaOutletBaru' => $hariKerjaOutletBaru,
                    'totalAlpha' => $totalAlpha,
                    'totalIzinCuti' => $totalIzinCuti,
                    'izinCutiBreakdown' => $izinCutiBreakdown,
                    'leaveData' => $leaveData, // Simpan leaveData untuk digunakan di Step 4
                    'phBonus' => $phBonus, // Simpan phBonus untuk digunakan di Step 4
                    'userPoint' => $userPoint,
                ];
            }

            $kasbonService = app(PayrollKasbonService::class);
            $kasbonEligibleByUser = $kasbonService->loadEligibleByUserIds(
                array_keys($userData),
                (int) $outletId
            );

            // Step 2–3: Pool & rate (Σ hari / Σ poin×hari sama untuk SC, L&B, deviasi, city ledger — seperti Excel)
            $poolTotals = PayrollSplitPoolCalculator::calculatePoolTotals($userData);
            $totalPointHariKerja = $poolTotals['totalPointHariKerja'];
            $totalHariKerja = $poolTotals['totalHariKerja'];
            $scRates = PayrollSplitPoolCalculator::calculateRates((float) $serviceCharge, $totalPointHariKerja, $totalHariKerja);
            $rateByPoint = $scRates['rateByPoint'];
            $rateProRate = $scRates['rateProRate'];

            $lbRates = PayrollSplitPoolCalculator::calculateRates((float) $lbAmount, $totalPointHariKerja, $totalHariKerja);
            $rateLBByPoint = $lbRates['rateByPoint'];
            $rateLBProRate = $lbRates['rateProRate'];

            $deviasiRates = PayrollSplitPoolCalculator::calculateRates((float) $deviasiAmount, $totalPointHariKerja, $totalHariKerja);
            $rateDeviasiByPoint = $deviasiRates['rateByPoint'];
            $rateDeviasiProRate = $deviasiRates['rateProRate'];

            $cityLedgerRates = PayrollSplitPoolCalculator::calculateRates((float) $cityLedgerAmount, $totalPointHariKerja, $totalHariKerja);
            $rateCityLedgerByPoint = $cityLedgerRates['rateByPoint'];
            $rateCityLedgerProRate = $cityLedgerRates['rateProRate'];

            // Step 4: Hitung service charge per user dan total gaji
            foreach ($userData as $userId => $data) {
                $user = $data['user'];
                $masterData = $data['masterData'];
                $employeeRows = $data['employeeRows']; // Gunakan employeeRows dari Employee Summary
                $totalTelat = $data['totalTelat'];
                $totalLembur = $data['totalLembur'];
                $hariKerja = $data['hariKerja']; // Hari kerja aktual (jumlah hari bekerja)
                $hariKerjaKaryawanBaru = $data['hariKerjaKaryawanBaru'] ?? $hariKerja; // Hari kerja untuk karyawan baru
                $hariKerjaKaryawanResign = $data['hariKerjaKaryawanResign'] ?? $hariKerja; // Hari kerja untuk karyawan resign
                $hariKerjaUntukServiceCharge = $data['hariKerjaUntukServiceCharge'] ?? $hariKerja; // Hari kerja pool gajian 2
                $hariKerjaProrateGajian1 = $data['hariKerjaProrateGajian1'] ?? $hariKerja; // Hari prorate gajian 1
                $isNewEmployee = $data['isNewEmployee'] ?? false; // Flag apakah karyawan baru
                $isResignedEmployee = $data['isResignedEmployee'] ?? false; // Flag apakah karyawan resign
                $affectsGajian2 = $data['affectsGajian2'] ?? false;
                $resignationDate = $data['resignationDate'] ?? null;
                $tanggalMasuk = $data['tanggalMasuk'] ?? null;
                $isMutatedEmployee = $data['isMutatedEmployee'] ?? false; // Flag apakah karyawan mutasi
                $mutationEffectiveDate = $data['mutationEffectiveDate'] ?? null; // Tanggal efektif mutasi
                $mutationRole = $data['mutationRole'] ?? null;
                $mutationOutletTo = $data['mutationOutletTo'] ?? null; // Outlet tujuan mutasi
                $userPoint = $data['userPoint'];
                $totalAlpha = $data['totalAlpha'] ?? 0; // Ambil totalAlpha dari userData
                $totalIzinCuti = $data['totalIzinCuti'] ?? 0; // Ambil totalIzinCuti dari userData
                $leaveData = $data['leaveData'] ?? []; // Ambil leaveData dari userData, default ke array kosong jika tidak ada
                $izinCutiBreakdown = $data['izinCutiBreakdown'] ?? []; // Ambil izinCutiBreakdown dari userData, default ke array kosong jika tidak ada
                $phBonus = $data['phBonus'] ?? 0; // Ambil phBonus dari userData, default ke 0 jika tidak ada
                
                // Hitung gaji lembur menggunakan nominal_lembur dari divisi
                // PENTING: Periode lembur adalah 26-25 (periode gajian 1), sama dengan uang makan
                // Service charge menggunakan periode 1-30 (periode gajian 2)
                $gajiLembur = 0;
                // Untuk karyawan mutasi, totalLembur sudah dihitung dari periode 26-25 di Step 1
                // Karena attendance tidak berdasarkan outlet, totalLembur sudah benar
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
                        'gaji_lembur' => $gajiLembur,
                        'is_mutated_employee' => $isMutatedEmployee,
                        'period' => $startDate->format('Y-m-d') . ' - ' . $endDate->format('Y-m-d')
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
                // PENTING: Periode uang makan dan lembur adalah 26-25 (periode gajian 1), bukan 1-30
                // Service charge menggunakan periode 1-30 (periode gajian 2)
                $uangMakan = 0;
                if ($masterData->um == 1) {
                    // Ambil nominal uang makan dari divisi karyawan
                    $nominalUangMakan = $divisiNominalUangMakan[$user->division_id] ?? 0;
                    
                    // Jika karyawan mutasi, hitung pro rate berdasarkan effective_date
                    // Untuk uang makan, tetap gunakan periode 26-25 (periode gajian 1)
                    if ($isMutatedEmployee && $mutationEffectiveDate) {
                        // Periode gajian 1: 26 bulan sebelumnya - 25 bulan yang dipilih (untuk uang makan dan lembur)
                        // Attendance sudah terambil untuk periode ini di Step 1, jadi kita gunakan $hariKerja yang sudah ada
                        // Tapi jika mutasi terjadi dalam periode 26-25, kita perlu hitung pro rate
                        
                        // Jika mutasi terjadi dalam periode 26-25, hitung pro rate
                        if ($mutationEffectiveDate->greaterThanOrEqualTo($startDate) && $mutationEffectiveDate->lessThanOrEqualTo($endDate)) {
                            // Mutasi dalam periode 26-25, hitung pro rate berdasarkan effective_date
                            // Attendance sudah terambil untuk periode ini, jadi kita gunakan $hariKerja yang sudah ada
                            // Tapi kita perlu pastikan attendance terambil dengan benar (tidak difilter outlet)
                            $uangMakan = $hariKerja * $nominalUangMakan;
                            
                            \Log::info('Uang makan untuk karyawan mutasi - dalam periode 26-25', [
                                'user_id' => $user->id,
                                'nama_lengkap' => $user->nama_lengkap,
                                'mutation_effective_date' => $mutationEffectiveDate->format('Y-m-d'),
                                'start_date' => $startDate->format('Y-m-d'),
                                'end_date' => $endDate->format('Y-m-d'),
                                'hari_kerja' => $hariKerja,
                                'uang_makan' => $uangMakan
                            ]);
                        } else {
                            // Mutasi di luar periode 26-25, gunakan hari kerja yang sudah ada
                            $uangMakan = $hariKerja * $nominalUangMakan;
                        }
                    } else {
                        $uangMakan = $hariKerja * $nominalUangMakan;
                    }
                    
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

                // Hitung BPJS JKN & TK (karyawan) + rincian perusahaan (informasi, tidak mengurangi THP)
                $bpjsJKN = 0;
                $bpjsTK = 0;
                $bpjsPerusahaanDetail = null;
                if ($masterData->bpjs_jkn == 1 || $masterData->bpjs_tk == 1) {
                    $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
                    $levelRow = $userLevel ? $dataLevelRowsById->get($userLevel) : null;
                    $dasarBpjs = PayrollBpjsCalculator::resolveDasarFromLevel($levelRow);
                    $katId = $userLevel ? ($levelBpjsKategoriId[$userLevel] ?? null) : null;
                    $katRow = ($katId && $bpjsKategoriById->has($katId)) ? $bpjsKategoriById->get($katId) : null;
                    $bpjsCalc = PayrollBpjsCalculator::calculate(
                        $masterData,
                        $dasarBpjs['kesehatan'],
                        $dasarBpjs['ketenagakerjaan'],
                        $katRow,
                        (int) ($user->id_outlet ?? 0)
                    );
                    $bpjsJKN = $bpjsCalc['bpjs_jkn'];
                    $bpjsTK = $bpjsCalc['bpjs_tk'];
                    $bpjsPerusahaanDetail = $bpjsCalc['perusahaan_detail'];

                    if ($isMutatedEmployee) {
                        $bpjsProrated = $this->prorateBpjsForMutationSegment(
                            $bpjsJKN,
                            $bpjsTK,
                            $bpjsPerusahaanDetail,
                            (float) ($data['mutationGajian1Ratio'] ?? 0)
                        );
                        $bpjsJKN = $bpjsProrated['bpjs_jkn'];
                        $bpjsTK = $bpjsProrated['bpjs_tk'];
                        $bpjsPerusahaanDetail = $bpjsProrated['perusahaan_detail'];
                    }

                    \Log::info('BPJS calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'id_jabatan' => $user->id_jabatan,
                        'id_level' => $userLevel,
                        'id_outlet' => $user->id_outlet,
                        'nilai_dasar_bpjs_kesehatan' => $dasarBpjs['kesehatan'],
                        'nilai_dasar_bpjs_ketenagakerjaan' => $dasarBpjs['ketenagakerjaan'],
                        'bpjs_jkn_enabled' => $masterData->bpjs_jkn,
                        'bpjs_tk_enabled' => $masterData->bpjs_tk,
                        'bpjs_jkn' => $bpjsJKN,
                        'bpjs_tk' => $bpjsTK,
                        'bpjs_kategori_id' => $katId,
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
                    $scAmounts = PayrollSplitPoolCalculator::calculateUserAmount(
                        true,
                        (float) $serviceCharge,
                        $rateByPoint,
                        $rateProRate,
                        (float) $userPoint,
                        (float) $hariKerjaUntukServiceCharge,
                        $isMutatedEmployee,
                        $mutationEffectiveDate,
                        (int) $year,
                        (int) $month,
                        $affectsGajian2,
                        $resignationDate,
                        $tanggalMasuk,
                        $isNewEmployee,
                        $mutationRole
                    );
                    $serviceChargeByPointAmount = $scAmounts['by_point'];
                    $serviceChargeProRateAmount = $scAmounts['pro_rate'];
                    $serviceChargeTotal = $scAmounts['total'];
                } else {
                    \Log::info('Service charge = 0 for user', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'sc_enabled' => $masterData->sc,
                        'service_charge_input' => $serviceCharge,
                        'hari_kerja' => $hariKerja,
                        'hari_kerja_untuk_service_charge' => $hariKerjaUntukServiceCharge,
                        'user_point' => $userPoint,
                        'rate_by_point' => $rateByPoint,
                        'rate_pro_rate' => $rateProRate,
                        'reason' => $masterData->sc != 1 ? 'sc not enabled' : 'service_charge input is 0'
                    ]);
                }
                
                // Hitung gaji pokok dan tunjangan (pro rate untuk karyawan baru dan karyawan resign)
                // PENTING: Gunakan hari kerja yang sama dengan service charge prorate (proporsi yang sama)
                // Service charge prorate menggunakan: rate × hari kerja untuk service charge
                // Dimana rate = total service charge prorate / total hari kerja semua karyawan
                // Jadi untuk gaji pokok dan tunjangan, gunakan proporsi yang sama: (hari kerja untuk service charge / total hari kerja standar)
                $gajiPokokFinal = $masterData->gaji;
                $tunjanganFinal = $masterData->tunjangan;
                
                // Hitung total hari kerja standar dalam periode payroll (dari tanggal 26 bulan sebelumnya sampai 25 bulan yang dipilih)
                // Ini adalah total hari kalender dalam periode, bukan total hari kerja karyawan
                $totalHariKalenderPeriode = $startDate->diffInDays($endDate) + 1; // +1 untuk include tanggal awal dan akhir
                
                // Tanpa hari kerja absensi → tidak ada gaji pokok / tunjangan (termasuk prorate resign)
                if ($hariKerja <= 0) {
                    $gajiPokokFinal = 0;
                    $tunjanganFinal = 0;
                } elseif ($isNewEmployee === true && $isResignedEmployee === true && $hariKerjaProrateGajian1 > 0) {
                    // Kasus khusus: Karyawan baru yang resign dalam periode yang sama
                    // Pro rate menggunakan proporsi yang sama dengan service charge prorate
                    // Gaji pokok prorate = gaji pokok × (hari kerja untuk service charge / total hari kalender dalam periode)
                    // Hari kerja untuk service charge sudah dihitung dari tanggal masuk sampai tanggal resign
                    $gajiPokokFinal = $masterData->gaji * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                    // Tunjangan prorate = tunjangan × (hari kerja untuk service charge / total hari kalender dalam periode)
                    $tunjanganFinal = $masterData->tunjangan * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                    
                    $resignation = $resignations->get($user->id);
                    \Log::info('Karyawan baru yang resign - Pro rate calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'tanggal_masuk' => $user->tanggal_masuk,
                        'resignation_date' => $resignation ? $resignation->resignation_date : null,
                        'is_new_employee' => $isNewEmployee,
                        'is_resigned_employee' => $isResignedEmployee,
                        'hari_kerja_dari_masuk_ke_resign' => $hariKerjaProrateGajian1,
                        'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                        'gaji_pokok_original' => $masterData->gaji,
                        'tunjangan_original' => $masterData->tunjangan,
                        'gaji_pokok_pro_rate' => $gajiPokokFinal,
                        'tunjangan_pro_rate' => $tunjanganFinal,
                        'formula_gaji' => "{$masterData->gaji} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$gajiPokokFinal}",
                        'formula_tunjangan' => "{$masterData->tunjangan} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$tunjanganFinal}"
                    ]);
                } elseif ($isNewEmployee === true && $hariKerjaProrateGajian1 > 0) {
                    // Karyawan baru saja (tidak resign)
                    // Pro rate menggunakan proporsi yang sama dengan service charge prorate
                    // Gaji pokok prorate = gaji pokok × (hari kerja untuk service charge / total hari kalender dalam periode)
                    // Ini sama dengan proporsi yang digunakan service charge prorate
                    $gajiPokokFinal = $masterData->gaji * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                    // Tunjangan prorate = tunjangan × (hari kerja untuk service charge / total hari kalender dalam periode)
                    $tunjanganFinal = $masterData->tunjangan * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                    
                    \Log::info('Karyawan baru - Pro rate calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'tanggal_masuk' => $user->tanggal_masuk,
                        'is_new_employee' => $isNewEmployee,
                        'hari_kerja_karyawan_baru' => $hariKerjaKaryawanBaru,
                        'hari_kerja_untuk_service_charge' => $hariKerjaUntukServiceCharge,
                        'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                        'gaji_pokok_original' => $masterData->gaji,
                        'tunjangan_original' => $masterData->tunjangan,
                        'gaji_pokok_pro_rate' => $gajiPokokFinal,
                        'tunjangan_pro_rate' => $tunjanganFinal,
                        'formula_gaji' => "{$masterData->gaji} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$gajiPokokFinal}",
                        'formula_tunjangan' => "{$masterData->tunjangan} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$tunjanganFinal}"
                    ]);
                } elseif ($isResignedEmployee === true && $hariKerjaProrateGajian1 > 0) {
                    // Karyawan resign saja (bukan baru)
                    // Untuk karyawan resign, hitung prorate dari awal periode sampai tanggal resign
                    // Gaji pokok prorate = gaji pokok × (hari kerja untuk service charge / total hari kalender dalam periode)
                    $gajiPokokFinal = $masterData->gaji * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                    // Tunjangan prorate = tunjangan × (hari kerja untuk service charge / total hari kalender dalam periode)
                    $tunjanganFinal = $masterData->tunjangan * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                    
                    $resignation = $resignations->get($user->id);
                    \Log::info('Karyawan resign - Pro rate calculation', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'is_resigned_employee' => $isResignedEmployee,
                        'resignation_date' => $resignation ? $resignation->resignation_date : null,
                        'hari_kerja_karyawan_resign' => $hariKerjaKaryawanResign,
                        'hari_kerja_untuk_service_charge' => $hariKerjaUntukServiceCharge,
                        'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                        'gaji_pokok_original' => $masterData->gaji,
                        'tunjangan_original' => $masterData->tunjangan,
                        'gaji_pokok_pro_rate' => $gajiPokokFinal,
                        'tunjangan_pro_rate' => $tunjanganFinal,
                        'formula_gaji' => "{$masterData->gaji} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$gajiPokokFinal}",
                        'formula_tunjangan' => "{$masterData->tunjangan} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$tunjanganFinal}"
                    ]);
                } elseif ($isMutatedEmployee === true && $hariKerja > 0) {
                    $gajiPokokFinal = $masterData->gaji * ($hariKerja / $totalHariKalenderPeriode);
                    $tunjanganFinal = $masterData->tunjangan * ($hariKerja / $totalHariKalenderPeriode);

                    \Log::info('Karyawan mutasi outlet - Pro rate gajian 1', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'mutation_role' => $mutationRole,
                        'mutation_effective_date' => $mutationEffectiveDate?->format('Y-m-d'),
                        'hari_kerja_gajian1' => $hariKerja,
                        'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                        'gaji_pokok_pro_rate' => $gajiPokokFinal,
                        'tunjangan_pro_rate' => $tunjanganFinal,
                    ]);
                } else {
                    // Karyawan biasa: tidak di-prorate (gaji pokok dan tunjangan full)
                    \Log::info('Karyawan biasa - No pro rate', [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'is_new_employee' => $isNewEmployee,
                        'is_resigned_employee' => $isResignedEmployee,
                        'hari_kerja_untuk_service_charge' => $hariKerjaUntukServiceCharge,
                        'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                        'gaji_pokok_final' => $gajiPokokFinal,
                        'tunjangan_final' => $tunjanganFinal,
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

            // Hitung L & B (mirror service charge: 50% by point + 50% pro rate)
            $lbByPointAmount = 0;
            $lbProRateAmount = 0;
            $lbTotal = 0;

            if ($masterData->lb == 1 && $lbAmount > 0) {
                $lbAmounts = PayrollSplitPoolCalculator::calculateUserAmount(
                    true,
                    (float) $lbAmount,
                    $rateLBByPoint,
                    $rateLBProRate,
                    (float) $userPoint,
                    (float) $hariKerjaUntukServiceCharge,
                    $isMutatedEmployee,
                    $mutationEffectiveDate,
                    (int) $year,
                    (int) $month,
                    $affectsGajian2,
                    $resignationDate,
                    $tanggalMasuk,
                    $isNewEmployee,
                    $mutationRole
                );
                $lbByPointAmount = $lbAmounts['by_point'];
                $lbProRateAmount = $lbAmounts['pro_rate'];
                $lbTotal = $lbAmounts['total'];
            }

            // Hitung Deviasi (mirror service charge)
            $deviasiByPointAmount = 0;
            $deviasiProRateAmount = 0;
            $deviasiTotal = 0;

            if ($masterData->deviasi == 1 && $deviasiAmount > 0) {
                $deviasiAmounts = PayrollSplitPoolCalculator::calculateUserAmount(
                    true,
                    (float) $deviasiAmount,
                    $rateDeviasiByPoint,
                    $rateDeviasiProRate,
                    (float) $userPoint,
                    (float) $hariKerjaUntukServiceCharge,
                    $isMutatedEmployee,
                    $mutationEffectiveDate,
                    (int) $year,
                    (int) $month,
                    $affectsGajian2,
                    $resignationDate,
                    $tanggalMasuk,
                    $isNewEmployee,
                    $mutationRole
                );
                $deviasiByPointAmount = $deviasiAmounts['by_point'];
                $deviasiProRateAmount = $deviasiAmounts['pro_rate'];
                $deviasiTotal = $deviasiAmounts['total'];
            }

            // Hitung City Ledger (mirror service charge)
            $cityLedgerByPointAmount = 0;
            $cityLedgerProRateAmount = 0;
            $cityLedgerTotal = 0;

            if ($masterData->city_ledger == 1 && $cityLedgerAmount > 0) {
                $cityLedgerAmounts = PayrollSplitPoolCalculator::calculateUserAmount(
                    true,
                    (float) $cityLedgerAmount,
                    $rateCityLedgerByPoint,
                    $rateCityLedgerProRate,
                    (float) $userPoint,
                    (float) $hariKerjaUntukServiceCharge,
                    $isMutatedEmployee,
                    $mutationEffectiveDate,
                    (int) $year,
                    (int) $month,
                    $affectsGajian2,
                    $resignationDate,
                    $tanggalMasuk,
                    $isNewEmployee,
                    $mutationRole
                );
                $cityLedgerByPointAmount = $cityLedgerAmounts['by_point'];
                $cityLedgerProRateAmount = $cityLedgerAmounts['pro_rate'];
                $cityLedgerTotal = $cityLedgerAmounts['total'];
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

                if ($isMutatedEmployee) {
                    $gajian1Ratio = (float) ($data['mutationGajian1Ratio'] ?? 0);
                    $gajian2Ratio = (float) ($data['mutationGajian2Ratio'] ?? 0);
                    $customEarningsGajian1 = round($customEarningsGajian1 * $gajian1Ratio);
                    $customDeductionsGajian1 = round($customDeductionsGajian1 * $gajian1Ratio);
                    $customEarningsGajian2 = round($customEarningsGajian2 * $gajian2Ratio);
                    $customDeductionsGajian2 = round($customDeductionsGajian2 * $gajian2Ratio);
                    $customEarnings = $customEarningsGajian1;
                    $customDeductions = $customDeductionsGajian1;
                }

                $potonganKasbon = 0;
                $prKasbonId = null;
                $kasbonCicilanKe = null;
                $kasbonPrNumber = null;
                $kasbonPreview = $kasbonService->previewForUser($user->id, $kasbonEligibleByUser);
                $kasbonTerminTotal = null;
                if ($kasbonPreview) {
                    $potonganKasbon = $kasbonPreview['potongan_kasbon'];
                    if ($isMutatedEmployee) {
                        $potonganKasbon = round($potonganKasbon * (float) ($data['mutationGajian1Ratio'] ?? 0));
                    }
                    $prKasbonId = $kasbonPreview['pr_kasbon_id'];
                    $kasbonCicilanKe = $kasbonPreview['kasbon_cicilan_ke'];
                    $kasbonPrNumber = $kasbonPreview['kasbon_pr_number'];
                    $kasbonTerminTotal = $kasbonEligibleByUser[$user->id]['termin_total'] ?? null;
                }

                // Total gaji = Gajian 1 (akhir bulan) + Gajian 2 (tanggal 8)
                $gajiSplit = PayrollGajiSplitCalculator::calculate([
                    'gaji_pokok' => $gajiPokokFinal,
                    'tunjangan' => $tunjanganFinal,
                    'custom_earnings_gajian1' => $customEarningsGajian1,
                    'custom_deductions_gajian1' => $customDeductionsGajian1,
                    'bpjs_jkn' => $bpjsJKN,
                    'bpjs_tk' => $bpjsTK,
                    'potongan_telat' => $potonganTelat,
                    'potongan_alpha' => $potonganAlpha,
                    'potongan_unpaid_leave' => $potonganUnpaidLeave,
                    'potongan_kasbon' => $potonganKasbon,
                    'service_charge' => $serviceChargeTotal,
                    'uang_makan' => $uangMakan,
                    'gaji_lembur' => $gajiLembur,
                    'ph_bonus' => $phBonus,
                    'custom_earnings_gajian2' => $customEarningsGajian2,
                    'custom_deductions_gajian2' => $customDeductionsGajian2,
                    'lb_total' => $lbTotal,
                    'deviasi_total' => $deviasiTotal,
                    'city_ledger_total' => $cityLedgerTotal,
                ]);
                $totalGajiAkhirBulan = $gajiSplit['total_gaji_akhir_bulan'];
                $totalGajiTanggal8 = $gajiSplit['total_gaji_tanggal_8'];
                $totalGaji = $gajiSplit['total_gaji'];
                
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
                
                // Ambil informasi mutasi dari userData
                $isMutatedEmployee = $data['isMutatedEmployee'] ?? false;
                $mutationEffectiveDate = $data['mutationEffectiveDate'] ?? null;
                $mutationOutletFrom = $data['mutationOutletFrom'] ?? null;
                $mutationOutletTo = $data['mutationOutletTo'] ?? null;
                
                $payrollDataItem = [
                    'user_id' => $user->id,
                    'nik' => $user->nik,
                    'nama_lengkap' => $user->nama_lengkap,
                    'no_rekening' => $user->no_rekening ?? null,
                    'tanggal_masuk' => $user->tanggal_masuk ?? null,
                    'is_new_employee' => $isNewEmployee,
                    'is_mutated_employee' => $isMutatedEmployee,
                    'mutation_effective_date' => $mutationEffectiveDate ? $mutationEffectiveDate->format('Y-m-d') : null,
                    'mutation_outlet_from' => $mutationOutletFrom,
                    'mutation_outlet_to' => $mutationOutletTo,
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
                    'lb_pro_rate' => round($lbProRateAmount),
                    'lb_total' => round($lbTotal),
                    'deviasi_by_point' => round($deviasiByPointAmount),
                    'deviasi_pro_rate' => round($deviasiProRateAmount),
                    'deviasi_total' => round($deviasiTotal),
                    'city_ledger_by_point' => round($cityLedgerByPointAmount),
                    'city_ledger_pro_rate' => round($cityLedgerProRateAmount),
                    'city_ledger_total' => round($cityLedgerTotal),
                    'bpjs_jkn' => round($bpjsJKN),
                    'bpjs_tk' => round($bpjsTK),
                    'bpjs_perusahaan_detail' => $bpjsPerusahaanDetail,
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
                    'potongan_kasbon' => round($potonganKasbon),
                    'pr_kasbon_id' => $prKasbonId,
                    'kasbon_cicilan_ke' => $kasbonCicilanKe,
                    'kasbon_pr_number' => $kasbonPrNumber,
                    'kasbon_termin_total' => $kasbonTerminTotal,
                    'total_gaji_akhir_bulan' => $totalGajiAkhirBulan,
                    'total_gaji_tanggal_8' => $totalGajiTanggal8,
                    'total_gaji' => $totalGaji,
                    'hari_kerja' => $hariKerja,
                    'hari_kerja_gajian2' => $hariKerjaUntukServiceCharge,
                    'total_alpha' => $totalAlpha,
                    'total_izin_cuti' => $totalIzinCuti,
                    'izin_cuti_breakdown' => $izinCutiBreakdown,
                    'extra_off_days' => isset($leaveData['extra_off_days']) ? $leaveData['extra_off_days'] : 0, // SAMA PERSIS dengan Employee Summary
                    'ph_bonus' => round($phBonus), // PH Bonus (hanya bonus, bukan extra_off)
                    'leave_data' => $leaveData, // Simpan leave_data untuk generate payroll
                    
                    // Debug: Log ph_bonus sebelum disimpan ke payrollDataItem
                    '_debug_ph_bonus_before_round' => $phBonus,
                    '_debug_ph_bonus_after_round' => round($phBonus),
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
                    'ph_bonus' => $payrollDataItem['ph_bonus'] ?? 'NOT SET',
                    'all_leave_keys' => $allLeaveKeys,
                    'has_extra_off_days' => isset($payrollDataItem['extra_off_days']),
                    'extra_off_days_value' => $payrollDataItem['extra_off_days'] ?? null,
                    'has_ph_bonus' => isset($payrollDataItem['ph_bonus']),
                    'ph_bonus_value' => $payrollDataItem['ph_bonus'] ?? null
                ]);

                $savedDetail = $payrollGeneratedDetailsFull->get($user->id);
                if ($payrollGenerated && $savedDetail && ($gajian1Saved || $gajian2Saved)) {
                    $payrollDataItem = $payrollPhaseService->overlaySavedPhaseFields(
                        $payrollDataItem,
                        $savedDetail,
                        $gajian1Saved && $gajian1Locked,
                        $gajian2Saved && $gajian2Locked
                    );

                    if ($gajian1Saved && !empty($payrollDataItem['leave_data']) && is_array($payrollDataItem['leave_data'])) {
                        foreach ($payrollDataItem['leave_data'] as $key => $value) {
                            if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                                $payrollDataItem[$key] = $value;
                            }
                        }
                        $payrollDataItem['extra_off_days'] = $payrollDataItem['leave_data']['extra_off_days'] ?? 0;
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
            $totalMPResign = $usersInPayrollData->whereIn('id', $resignedEmployeeIds)->count();

            $poolTotalHariKerjaGajian2 = (int) $payrollData->sum(fn ($item) => (int) ($item['hari_kerja'] ?? 0));
            $poolTotalPointHariKerja = (int) $payrollData->sum(
                fn ($item) => (int) ($item['point'] ?? 0) * (int) ($item['hari_kerja'] ?? 0)
            );
        } else {
            $totalMP = 0;
            $totalMPAktif = 0;
            $totalMPResign = 0;
        }

        // Debug: Log PH Bonus data yang dikirim ke frontend
        $phBonusSummary = $payrollData->map(function($item) {
            $phBonus = $item['ph_bonus'] ?? 0;
            // Convert to number if it's a string
            if (is_string($phBonus)) {
                $phBonus = (float) $phBonus;
            }
            return [
                'user_id' => $item['user_id'] ?? 'N/A',
                'nama_lengkap' => $item['nama_lengkap'] ?? 'N/A',
                'ph_bonus' => $phBonus,
                'ph_bonus_type' => gettype($item['ph_bonus'] ?? null),
                'has_ph_bonus_key' => isset($item['ph_bonus'])
            ];
        });
        
        $usersWithPHBonus = $phBonusSummary->filter(function($item) {
            return ($item['ph_bonus'] ?? 0) > 0;
        });
        
        // Log sample data untuk debug
        $sampleData = $payrollData->take(3)->map(function($item) {
            return [
                'user_id' => $item['user_id'] ?? 'N/A',
                'nama_lengkap' => $item['nama_lengkap'] ?? 'N/A',
                'ph_bonus' => $item['ph_bonus'] ?? 'NOT SET',
                'ph_bonus_type' => gettype($item['ph_bonus'] ?? null),
                'has_ph_bonus_key' => isset($item['ph_bonus'])
            ];
        });
        
        // Log mutated users in payrollData
        $mutatedUsersInPayroll = $payrollData->filter(function($item) use ($mutatedEmployeeIds) {
            return in_array($item['user_id'] ?? null, $mutatedEmployeeIds ?? []);
        });
        
        \Log::info('Payroll - Sending PH Bonus data to frontend', [
            'total_users' => $payrollData->count(),
            'users_with_ph_bonus' => $usersWithPHBonus->count(),
            'ph_bonus_summary' => $usersWithPHBonus->toArray(),
            'sample_data' => $sampleData->toArray(),
            'mutated_users_in_payroll' => $mutatedUsersInPayroll->count(),
            'mutated_users_details' => $mutatedUsersInPayroll->map(function($item) {
                return [
                    'user_id' => $item['user_id'] ?? null,
                    'nama_lengkap' => $item['nama_lengkap'] ?? null,
                    'gaji_pokok' => $item['gaji_pokok'] ?? null,
                    'service_charge' => $item['service_charge'] ?? null,
                    'is_mutated_employee' => $item['is_mutated_employee'] ?? false,
                    'mutation_effective_date' => $item['mutation_effective_date'] ?? null,
                    'mutation_outlet_to' => $item['mutation_outlet_to'] ?? null,
                    'hari_kerja' => $item['hari_kerja'] ?? 0,
                    'nik' => $item['nik'] ?? null,
                    'jabatan' => $item['jabatan'] ?? null,
                    'divisi' => $item['divisi'] ?? null,
                    'all_keys' => array_keys($item ?? [])
                ];
            })->toArray()
        ]);
        
        // Log full payrollData untuk mutated users (untuk debugging)
        if ($mutatedUsersInPayroll->isNotEmpty()) {
            \Log::info('Payroll - Full mutated users data being sent to frontend', [
                'mutated_users_full_data' => $mutatedUsersInPayroll->toArray()
            ]);
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
                'pool_total_hari_kerja_gajian2' => $poolTotalHariKerjaGajian2,
                'pool_total_point_hari_kerja' => $poolTotalPointHariKerja,
            ],
        ]);
    }

    /**
     * Calculate payroll data - SHARED FUNCTION untuk index() dan export()
     * Ini adalah single source of truth untuk semua payroll calculation
     * 
     * @param int $outletId
     * @param string $month
     * @param string $year
     * @param float $serviceCharge
     * @param float $lbAmount
     * @param float $deviasiAmount
     * @param float $cityLedgerAmount
     * @return array [payrollData, leaveTypes, totalMP, totalMPAktif, totalMPResign, mutatedEmployeeIds]
     */
    private function calculatePayrollData($outletId, $month, $year, $serviceCharge, $lbAmount, $deviasiAmount, $cityLedgerAmount)
    {
        // Initialize return values
        $payrollData = collect();
        $leaveTypes = collect();
        $totalMP = 0;
        $totalMPAktif = 0;
        $totalMPResign = 0;
        $mutatedEmployeeIds = [];

        if (!$outletId || !$month || !$year) {
            return [
                'payrollData' => $payrollData,
                'leaveTypes' => $leaveTypes,
                'totalMP' => $totalMP,
                'totalMPAktif' => $totalMPAktif,
                'totalMPResign' => $totalMPResign,
                'mutatedEmployeeIds' => $mutatedEmployeeIds,
            ];
        }

        // Hitung periode payroll (26 bulan sebelumnya - 25 bulan yang dipilih)
        $start = date('Y-m-d', strtotime("$year-$month-26 -1 month"));
        $end = date('Y-m-d', strtotime("$year-$month-25"));
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
        $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        // Ambil data karyawan di outlet tersebut (HANYA yang aktif)
        $users = User::where('status', 'A')
            ->where('id_outlet', $outletId)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);

        // Ambil data resignation untuk periode tersebut
        $allResignations = $this->queryApprovedResignationsForPayroll($start, $end, (int) $year, (int) $month);
        
        $resignedEmployeeIds = $allResignations->pluck('employee_id')->toArray();
        if (!empty($resignedEmployeeIds)) {
            $resignedUsers = User::whereIn('id', $resignedEmployeeIds)
                ->where('id_outlet', $outletId)
                ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);
            
            $resignedUserIds = $resignedUsers->pluck('id')->toArray();
            $resignations = $allResignations->whereIn('employee_id', $resignedUserIds)->keyBy('employee_id');

            $existingUserIds = $users->pluck('id')->toArray();
            $newResignedIds = array_diff($resignedUserIds, $existingUserIds);
            if (!empty($newResignedIds)) {
                $newResignedUsers = $resignedUsers->whereIn('id', $newResignedIds);
                $users = $users->merge($newResignedUsers);
            }
        } else {
            $resignations = collect()->keyBy('employee_id');
        }

        // Ambil data mutations dari employee_movements
        $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
        $mutations = collect();
        $mutationMap = [];

        if ($outletName) {
            $mutations = $this->collectMutationsForPayrollOutlet(
                (int) $outletId,
                $outletName,
                $start,
                $end,
                $gajian2Start,
                $gajian2End
            );
            $mutationMap = $this->buildPayrollMutationMap($mutations, (int) $outletId, $outletName);
            $users = $this->mergeMutatedUsersIntoPayrollUsers($users, $mutations);
            $mutatedEmployeeIds = $mutations->pluck('employee_id')->toArray();
        }
        
        // TODO: Continue extracting the rest of the logic from index()
        // This is a work in progress - will be completed in next steps
        
        return [
            'payrollData' => $payrollData,
            'leaveTypes' => $leaveTypes,
            'totalMP' => $totalMP,
            'totalMPAktif' => $totalMPAktif,
            'totalMPResign' => $totalMPResign,
            'mutatedEmployeeIds' => $mutatedEmployeeIds,
        ];
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
            
            // Check-in di hari kalender ini (bukan scan OUT cross-day dari shift kemarin)
            $has_check_in = $attendanceInfo && ! empty($attendanceInfo['first_in']);

            // Check directly to att_log table to ensure accuracy
            // First check from attendanceInfo, if not found, check directly from att_log
            $has_scan = $has_check_in;
            if ($has_scan) {
                // sudah ada check-in
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
                    ->where('a.scan_date', '>=', $tanggal . ' 00:00:00')
                    ->where('a.scan_date', '<', date('Y-m-d', strtotime($tanggal . ' +1 day')) . ' 00:00:00')
                    ->count();
                
                // If no scans found with outlet_id, try alternative query
                if ($scanCount == 0) {
                    $scanCount = DB::table('att_log as a')
                        ->join('user_pins as up', 'a.pin', '=', 'up.pin')
                        ->join('users as u', 'up.user_id', '=', 'u.id')
                        ->join('tbl_data_outlet as o', 'up.outlet_id', '=', 'o.id_outlet')
                        ->where('u.id', $userId)
                        ->where('o.id_outlet', $outletId)
                        ->where('a.scan_date', '>=', $tanggal . ' 00:00:00')
                        ->where('a.scan_date', '<', date('Y-m-d', strtotime($tanggal . ' +1 day')) . ' 00:00:00')
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
                'has_check_in' => $has_check_in,
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
     * Get attendance data for a specific period (without outlet filter)
     * Used for calculating uang makan and gaji lembur for mutated employees in gajian 2 period
     */
    private function getAttendanceDataForPeriod($userId, $outletId, $startDate, $endDate)
    {
        // Use AttendanceController method to get attendance data (same logic as getAttendanceData)
        $attendanceController = new AttendanceController();
        $attendanceDataWithFirstInLastOut = $attendanceController->getAttendanceDataWithFirstInLastOut($userId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
        
        // Get approved absent requests for the date range
        $approvedAbsentsGrouped = $this->getApprovedAbsentRequests($startDate->format('Y-m-d'), $endDate->format('Y-m-d'), $userId);
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
            
            // Get shift data - jika outletId null, coba ambil dari semua outlet
            $shift = null;
            if ($outletId) {
                $shift = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->where('us.user_id', $userId)
                    ->where('us.tanggal', $tanggal)
                    ->where('us.outlet_id', $outletId)
                    ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->first();
            } else {
                // Jika outletId null, ambil shift dari outlet manapun (untuk karyawan mutasi)
                $shift = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->where('us.user_id', $userId)
                    ->where('us.tanggal', $tanggal)
                    ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->first();
            }
            
            $is_off = false;
            if ($shift) {
                if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) {
                    $is_off = true;
                }
            } else {
                // Jika tidak ada shift, anggap sebagai off day
                $is_off = true;
            }
            
            // Check if user has scan (attendance tidak berdasarkan outlet)
            $has_scan = false;
            if ($attendanceInfo && isset($attendanceInfo['first_in']) && $attendanceInfo['first_in']) {
                $has_scan = true;
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
            if (!$is_off && $shift && !$has_scan && !$is_approved_absent) {
                if (is_string($tanggal) && strlen($tanggal) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                    if ($tanggal < $today) {
                        $is_alpha = true;
                    }
                } else {
                    $tanggalTimestamp = strtotime($tanggal);
                    $todayTimestamp = strtotime($today);
                    if ($tanggalTimestamp !== false && $todayTimestamp !== false && $tanggalTimestamp < $todayTimestamp) {
                        $is_alpha = true;
                    }
                }
            }
            
            // Hanya tambahkan jika ada scan (hari kerja)
            if ($has_scan) {
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
        }
        
        return $rows;
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
            $startStr = $startDate instanceof Carbon
                ? $startDate->format('Y-m-d')
                : Carbon::parse($startDate)->format('Y-m-d');
            $endStr = $endDate instanceof Carbon
                ? $endDate->format('Y-m-d')
                : Carbon::parse($endDate)->format('Y-m-d');

            // Get all overtime transactions from Extra Off system for the date range
            $overtimeTransactions = DB::table('extra_off_transactions')
                ->where('user_id', $userId)
                ->where('source_type', 'overtime_work')
                ->where('transaction_type', 'earned')
                ->where('status', 'approved') // Only count approved transactions
                ->whereBetween('source_date', [$startStr, $endStr])
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
                'start_date' => $startDate instanceof Carbon ? $startDate->format('Y-m-d') : (string) $startDate,
                'end_date' => $endDate instanceof Carbon ? $endDate->format('Y-m-d') : (string) $endDate,
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
        $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
        $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        // Ambil data seperti di index() - SAMA PERSIS
        $users = User::where('status', 'A')
            ->where('id_outlet', $outletId)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);

        // Ambil data resignation untuk periode tersebut (status approved dan resignation_date dalam periode)
        // HANYA karyawan yang resign di periode ini yang akan muncul
        // PERBAIKAN: Ambil semua resignations yang approved dalam periode, lalu filter berdasarkan outlet user
        // TIDAK memfilter berdasarkan created_at atau approved_at, HANYA berdasarkan resignation_date
        $allResignations = $this->queryApprovedResignationsForPayroll($start, $end, (int) $year, (int) $month);
        
        // Filter resignations berdasarkan outlet user (bukan outlet di resignation)
        // Ambil employee_id dari resignations yang memiliki user dengan outlet yang sesuai
        // TIDAK memfilter status user (bisa 'A' atau 'N')
        $resignedEmployeeIds = $allResignations->pluck('employee_id')->toArray();
        if (!empty($resignedEmployeeIds)) {
            // Ambil user yang resign dengan outlet yang sesuai - TIDAK memfilter status
            $resignedUsers = User::whereIn('id', $resignedEmployeeIds)
                ->where('id_outlet', $outletId)
                ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);
            
            // Buat collection resignations yang sudah difilter berdasarkan outlet user
            $resignedUserIds = $resignedUsers->pluck('id')->toArray();
            $resignations = $allResignations->whereIn('employee_id', $resignedUserIds)
            ->keyBy('employee_id');

        // Tambahkan karyawan yang resign di periode ini ke list users (jika belum ada)
            $existingUserIds = $users->pluck('id')->toArray();
            $newResignedIds = array_diff($resignedUserIds, $existingUserIds);
            if (!empty($newResignedIds)) {
                $newResignedUsers = $resignedUsers->whereIn('id', $newResignedIds);
                $users = $users->merge($newResignedUsers);
            }
        } else {
            $resignations = collect()->keyBy('employee_id');
        }

        $jabatans = DB::table('tbl_data_jabatan')->pluck('nama_jabatan', 'id_jabatan');
        $divisions = DB::table('tbl_data_divisi')->pluck('nama_divisi', 'id');
        $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');

        // ========== MUTATION HANDLING (employee_movements, sama dengan index()) ==========
        $mutations = collect();
        $mutationMap = [];
        $mutatedEmployeeIds = [];

        if ($outletName) {
            $mutations = $this->collectMutationsForPayrollOutlet(
                (int) $outletId,
                $outletName,
                $start,
                $end,
                $gajian2Start,
                $gajian2End
            );
            $mutationMap = $this->buildPayrollMutationMap($mutations, (int) $outletId, $outletName);
            $users = $this->mergeMutatedUsersIntoPayrollUsers($users, $mutations);
            $users = $this->filterPayrollUsersForMutationEffectiveDate(
                $users,
                $mutationMap,
                $startDate,
                $endDate,
                $gajian2End,
                (int) $outletId,
                $outletName
            );
            $mutatedEmployeeIds = $mutations->pluck('employee_id')->toArray();

            \Log::info('Export - Mutations found and mapped', [
                'outlet_id' => $outletId,
                'outlet_name' => $outletName,
                'start_date' => $start,
                'end_date' => $end,
                'mutations_count' => $mutations->count(),
                'mutation_map' => $mutationMap
            ]);
        }
        // ========== END MUTATION HANDLING ==========

        // Ambil data master payroll per outlet + divisi (sama seperti Master Payroll)
        $payrollMaster = $this->buildPayrollMasterLookup($outletId);

        // Ambil data level dari jabatan dan point
        $jabatanLevels = DB::table('tbl_data_jabatan')->pluck('id_level', 'id_jabatan');
        $levelPoints = DB::table('tbl_data_level')->pluck('nilai_point', 'id');
        $divisiNominalLembur = DB::table('tbl_data_divisi')->pluck('nominal_lembur', 'id');
        $divisiNominalUangMakan = DB::table('tbl_data_divisi')->pluck('nominal_uang_makan', 'id');
        $dataLevelRowsById = DB::table('tbl_data_level')->get()->keyBy('id');
        $levelBpjsKategoriId = DB::table('tbl_data_level')->pluck('id_bpjs_kategori', 'id');
        $bpjsKategoriById = DB::table('tbl_bpjs_kategori')->where('status', 'A')->get()->keyBy('id');

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
                'u.division_id',
                'o.id_outlet as outlet_id'
            )
            ->where('a.scan_date', '>=', $start . ' 00:00:00')
            ->where('a.scan_date', '<', $gajian2End->copy()->addDay()->format('Y-m-d') . ' 00:00:00');

        if (! empty($outletId)) {
            $mutatedEmployeeIdsForFilter = $mutatedEmployeeIds ?? [];
            if (! empty($mutatedEmployeeIdsForFilter)) {
                $sub->where(function ($q) use ($outletId, $mutatedEmployeeIdsForFilter) {
                    $q->where('u.id_outlet', $outletId)
                        ->orWhereIn('u.id', $mutatedEmployeeIdsForFilter);
                });
            } else {
                $sub->where('u.id_outlet', $outletId);
            }
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
                'inoutmode' => $scan->inoutmode,
                'outlet_id' => $scan->outlet_id ?? null,
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
                $row->lembur = floor(app(\App\Services\AttendanceWorkTimelineService::class)->calculateOvertimeHours(
                    (int) ($row->work_minutes ?? 0),
                    $shiftData->time_start,
                    $shiftData->time_end
                ));
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
            $shiftKey = $row->user_id . '_' . $row->tanggal;
            $shift = $allShiftData->get($shiftKey, collect())->first();
            $isOffDay = $this->attendanceReportHelper()->isShiftOff($shift);
            $telatLembur = $this->attendanceReportHelper()->calculateDailyTelatLembur($row, $shift, $row->tanggal, $isOffDay);
            $telat = $telatLembur['telat'];
            $lembur = $telatLembur['lembur'];

            $dayRow = (object) [
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
            ];
            $this->attendanceReportHelper()->enrichAttendanceDayRow($dayRow, $shift, $holidays);

            if (! $this->attendanceReportHelper()->shouldIncludeAttendanceSummaryRow($dayRow)) {
                continue;
            }

            $rows->push($dayRow);
        }

        // Group by employee - SAMA PERSIS dengan Employee Summary
        $employeeGroups = $rows->groupBy('user_id');

        // Step 1: Hitung semua data dasar untuk semua user - PERBAIKAN: Loop berdasarkan $users, bukan $employeeGroups
        // Ini memastikan semua user (termasuk yang tidak punya data attendance) masuk ke export
        $userData = [];
        foreach ($users as $user) {
            $userId = $user->id;
            $allEmployeeRows = $employeeGroups->get($userId, collect());
            $employeeRows = $this->filterAttendanceRowsForDateRange($allEmployeeRows, $startDate, $endDate);
            
            // Jika user tidak punya data absensi, buat employeeRows kosong
            if ($employeeRows->isEmpty()) {
                // User tanpa data attendance tetap diproses
            }

            // Ambil data master payroll untuk user ini
            $masterData = $this->resolvePayrollMasterForUser($user, $payrollMaster, $outletId);

            $isMutatedEmployee = isset($mutationMap[$userId]);
            $mutationData = $isMutatedEmployee ? $mutationMap[$userId] : null;
            $mutationRole = null;
            $mutationEffectiveDate = null;
            $hariKerjaGajian2 = 0;
            $hariKerjaOutletLama = 0;
            $hariKerjaOutletBaru = 0;
            $mutationGajian1Ratio = 1.0;
            $mutationGajian2Ratio = 1.0;
            $gajian1SegmentStart = $startDate->copy();
            $gajian1SegmentEnd = $endDate->copy();

            if ($isMutatedEmployee && $mutationData) {
                $mutCtx = $this->resolveMutationPayrollContext(
                    $mutationData,
                    $startDate,
                    $endDate,
                    $gajian2Start,
                    $gajian2End
                );
                $mutationRole = $mutCtx['mutationRole'];
                $mutationEffectiveDate = $mutCtx['mutationEffectiveDate'];
                $hariKerjaGajian2 = $mutCtx['hariKerjaGajian2'];
                $hariKerjaOutletLama = $mutCtx['hariKerjaOutletLama'];
                $hariKerjaOutletBaru = $mutCtx['hariKerjaOutletBaru'];
                $ratios = $this->buildMutationPayrollRatios(
                    $mutCtx['hariKerjaGajian1'],
                    $hariKerjaGajian2,
                    $startDate,
                    $endDate,
                    $gajian2Start,
                    $gajian2End
                );
                $mutationGajian1Ratio = $ratios['gajian1'];
                $mutationGajian2Ratio = $ratios['gajian2'];

                $segment = PayrollSplitPoolCalculator::resolveMutationDateSegment(
                    $mutationEffectiveDate,
                    $mutationRole,
                    $startDate,
                    $endDate
                );
                if ($segment) {
                    $gajian1SegmentStart = $segment['start'];
                    $gajian1SegmentEnd = $segment['end'];
                    $employeeRows = $this->filterAttendanceRowsForMutationSegment(
                        $employeeRows,
                        $mutationEffectiveDate,
                        $mutationRole
                    );
                } else {
                    $employeeRows = collect();
                }
            }

            $gajian1SegmentStartStr = $gajian1SegmentStart->format('Y-m-d');
            $gajian1SegmentEndStr = $gajian1SegmentEnd->format('Y-m-d');

            if ($employeeRows->isEmpty()) {
                $totalTelat = 0;
                $totalLembur = 0;
                $hariKerjaAttendance = 0;
            } else {
                $totalTelat = $this->attendanceReportHelper()->sumTelatFromAttendanceRows($employeeRows);
                $extraOffOvertimeTotal = floor($this->getExtraOffOvertimeHoursForPeriod(
                    $userId,
                    $gajian1SegmentStart,
                    $gajian1SegmentEnd
                ));
                $totalLemburRegular = floor($employeeRows->sum('lembur'));
                $totalLembur = floor($totalLemburRegular + $extraOffOvertimeTotal);
                $hariKerjaAttendance = $this->attendanceReportHelper()->countHariKerjaFromRows($employeeRows);
            }

            $hariKerjaMutationSc = ($isMutatedEmployee && $mutationRole)
                ? $this->countMutationSegmentScDays(
                    $userId,
                    (int) $outletId,
                    $gajian1SegmentStart,
                    $gajian1SegmentEnd,
                    $mutationRole
                )
                : null;
            $hariKerja = $this->resolveHariKerjaForPayrollSegment(
                $isMutatedEmployee,
                $mutCtx ?? null,
                $hariKerjaAttendance,
                $hariKerjaMutationSc
            );

            $totalAlpha = $this->calculateAlpaDays($userId, $outletId, $gajian1SegmentStartStr, $gajian1SegmentEndStr);
            $leaveData = $this->calculateLeaveData($userId, $gajian1SegmentStartStr, $gajian1SegmentEndStr);

            $izinCutiBreakdown = [];
            $totalIzinCuti = 0;
            foreach ($leaveData as $key => $value) {
                if (strpos($key, '_days') !== false && $key !== 'extra_off_days') {
                    $izinCutiBreakdown[$key] = $value;
                    $totalIzinCuti += $value;
                }
            }

            $phBonus = $this->calculatePHBonus($userId, $gajian1SegmentStartStr, $gajian1SegmentEndStr);

            $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
            $userPoint = $userLevel ? ($levelPoints[$userLevel] ?? 0) : 0;

            // Cek apakah karyawan baru (tanggal_masuk dalam periode payroll) - HARUS DILAKUKAN DI STEP 1
            $isNewEmployee = false;
            $hariKerjaKaryawanBaru = $hariKerja; // Default: hari kerja normal
            $tanggalMasuk = null;
            if ($user->tanggal_masuk) {
                $tanggalMasuk = Carbon::parse($user->tanggal_masuk);
                $isNewEmployee = $tanggalMasuk->greaterThanOrEqualTo($startDate) && $tanggalMasuk->lessThanOrEqualTo($endDate);
                
                // Jika karyawan baru, hitung hari kerja dari tanggal masuk sampai akhir periode
                if ($isNewEmployee) {
                    // Hitung hari kerja dari tanggal masuk sampai akhir periode (hitung hari kalender)
                    $hariKerjaKaryawanBaru = $tanggalMasuk->diffInDays($endDate) + 1; // +1 untuk include tanggal masuk dan tanggal akhir
                }
            }
            
            // Cek apakah karyawan resign (resignation_date dalam periode gajian 1 atau gajian 2)
            $resignation = $resignations->get($user->id);
            $resignCtx = $this->resolveResignationPayrollContext(
                $resignation,
                $hariKerja,
                $startDate,
                $endDate,
                $gajian2Start,
                $gajian2End
            );
            $isResignedEmployee = $resignCtx['isResignedEmployee'];
            $affectsGajian2 = $resignCtx['affectsGajian2'];
            $resignationDate = $resignCtx['resignationDate'];
            $hariKerjaKaryawanResign = $resignCtx['hariKerjaKaryawanResign'];

            if ($isNewEmployee && $isResignedEmployee && $tanggalMasuk && $resignationDate) {
                $hariKerjaProrateGajian1 = $tanggalMasuk->diffInDays($resignationDate) + 1;
            } elseif ($isNewEmployee) {
                $hariKerjaProrateGajian1 = $hariKerjaKaryawanBaru;
            } elseif ($isResignedEmployee) {
                $hariKerjaProrateGajian1 = $hariKerjaKaryawanResign;
            } else {
                $hariKerjaProrateGajian1 = $hariKerja;
            }
            
            $hariKerjaGajian2Attendance = $this->countHariKerjaGajian2Attendance(
                $allEmployeeRows,
                $gajian2Start,
                $gajian2End,
                $affectsGajian2 ? $resignationDate : null,
                $this->resolveTanggalMasukForGajian2Pool($tanggalMasuk, $gajian2Start),
                $isMutatedEmployee ? $mutationEffectiveDate : null,
                $isMutatedEmployee ? $mutationRole : null
            );

            $hariKerjaUntukServiceCharge = 0;

            if ($isMutatedEmployee && $hariKerja <= 0) {
                $hariKerjaGajian2 = 0;
                $hariKerjaProrateGajian1 = 0;
            } else {
                $this->syncGajian1ProrateDaysWithAttendance(
                    $hariKerja,
                    $hariKerjaKaryawanBaru,
                    $hariKerjaKaryawanResign,
                    $isNewEmployee,
                    $isResignedEmployee
                );
                if ($isNewEmployee) {
                    $hariKerjaProrateGajian1 = $hariKerjaKaryawanBaru;
                } elseif ($isResignedEmployee) {
                    $hariKerjaProrateGajian1 = $hariKerjaKaryawanResign;
                }
                $hariKerjaUntukServiceCharge = PayrollSplitPoolCalculator::resolveGajian1PoolDays($hariKerja);
            }

            $mutationOutletFrom = $isMutatedEmployee ? ($mutationData['outlet_from_name'] ?? null) : null;
            $mutationOutletTo = $isMutatedEmployee ? ($mutationData['outlet_to_name'] ?? null) : null;

            // Simpan data user untuk perhitungan service charge
            $userData[$user->id] = [
                'user' => $user,
                'masterData' => $masterData,
                'employeeRows' => $employeeRows,
                'totalTelat' => $totalTelat,
                'totalLembur' => $totalLembur,
                'hariKerja' => $hariKerja,
                'hariKerjaKaryawanBaru' => $hariKerjaKaryawanBaru,
                'hariKerjaKaryawanResign' => $hariKerjaKaryawanResign,
                'hariKerjaUntukServiceCharge' => $hariKerjaUntukServiceCharge,
                'hariKerjaProrateGajian1' => $hariKerjaProrateGajian1,
                'hariKerjaGajian2' => $hariKerjaGajian2,
                'hariKerjaGajian2Attendance' => $hariKerjaGajian2Attendance,
                'mutationGajian1Ratio' => $mutationGajian1Ratio,
                'mutationGajian2Ratio' => $mutationGajian2Ratio,
                'isNewEmployee' => $isNewEmployee,
                'isResignedEmployee' => $isResignedEmployee,
                'affectsGajian2' => $affectsGajian2,
                'resignationDate' => $resignationDate,
                'tanggalMasuk' => $tanggalMasuk,
                'totalAlpha' => $totalAlpha,
                'totalIzinCuti' => $totalIzinCuti,
                'izinCutiBreakdown' => $izinCutiBreakdown,
                'leaveData' => $leaveData,
                'phBonus' => $phBonus,
                'userPoint' => $userPoint,
                'isMutatedEmployee' => $isMutatedEmployee,
                'mutationRole' => $mutationRole,
                'mutationEffectiveDate' => $mutationEffectiveDate,
                'mutationOutletFrom' => $mutationOutletFrom,
                'mutationOutletTo' => $mutationOutletTo,
                'mutationData' => $mutationData,
                'hariKerjaOutletLama' => $hariKerjaOutletLama,
                'hariKerjaOutletBaru' => $hariKerjaOutletBaru,
            ];
        }
        
        // Debug: Log jumlah user yang diproses
        \Log::info('Export - User Data Count', [
            'total_users' => $users->count(),
            'user_data_count' => count($userData),
            'employee_groups_count' => $employeeGroups->count(),
            'missing_users' => $users->count() - count($userData),
            'mutated_employees_count' => count($mutationMap)
        ]);

        $kasbonServiceExport = app(PayrollKasbonService::class);
        $kasbonEligibleByUserExport = $kasbonServiceExport->loadEligibleByUserIds(
            array_keys($userData),
            (int) $outletId
        );

        // Step 2–3: Pool & rate service charge + deduction (50% by point + 50% pro rate)
        $poolTotals = PayrollSplitPoolCalculator::calculatePoolTotals($userData);
        $totalPointHariKerja = $poolTotals['totalPointHariKerja'];
        $totalHariKerja = $poolTotals['totalHariKerja'];
        $scRates = PayrollSplitPoolCalculator::calculateRates((float) $serviceCharge, $totalPointHariKerja, $totalHariKerja);
        $rateByPoint = $scRates['rateByPoint'];
        $rateProRate = $scRates['rateProRate'];

        $lbRates = PayrollSplitPoolCalculator::calculateRates((float) $lbAmount, $totalPointHariKerja, $totalHariKerja);
        $rateLBByPoint = $lbRates['rateByPoint'];
        $rateLBProRate = $lbRates['rateProRate'];

        $deviasiRates = PayrollSplitPoolCalculator::calculateRates((float) $deviasiAmount, $totalPointHariKerja, $totalHariKerja);
        $rateDeviasiByPoint = $deviasiRates['rateByPoint'];
        $rateDeviasiProRate = $deviasiRates['rateProRate'];

        $cityLedgerRates = PayrollSplitPoolCalculator::calculateRates((float) $cityLedgerAmount, $totalPointHariKerja, $totalHariKerja);
        $rateCityLedgerByPoint = $cityLedgerRates['rateByPoint'];
        $rateCityLedgerProRate = $cityLedgerRates['rateProRate'];

        // Cek apakah payroll sudah di-generate
        $payrollGenerated = DB::table('payroll_generated')
            ->where('outlet_id', $outletId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();
        
        $payrollGeneratedDetails = collect();
        if ($payrollGenerated) {
            // PERBAIKAN: Pastikan mengambil semua kolom termasuk payment_method
            $payrollGeneratedDetails = DB::table('payroll_generated_details')
                ->where('payroll_generated_id', $payrollGenerated->id)
                ->select('*') // Explicitly select all columns to ensure payment_method is included
                ->get()
                ->keyBy('user_id');
            
            // Debug: Log sample payment_method values
            \Log::info('Export - Payroll Generated Details', [
                'payroll_generated_id' => $payrollGenerated->id,
                'total_details' => $payrollGeneratedDetails->count(),
                'sample_payment_methods' => $payrollGeneratedDetails->take(5)->map(function($item) {
                    return [
                        'user_id' => $item->user_id,
                        'payment_method' => $item->payment_method ?? 'null'
                    ];
                })->values()->toArray()
            ]);
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
            $hariKerjaKaryawanResign = $data['hariKerjaKaryawanResign'] ?? $hariKerja; // Hari kerja untuk karyawan resign
            $hariKerjaUntukServiceCharge = $data['hariKerjaUntukServiceCharge'] ?? $hariKerja; // Hari kerja pool gajian 2
            $hariKerjaProrateGajian1 = $data['hariKerjaProrateGajian1'] ?? $hariKerja; // Hari prorate gajian 1
            $isNewEmployee = $data['isNewEmployee'] ?? false; // Flag apakah karyawan baru
            $isResignedEmployee = $data['isResignedEmployee'] ?? false; // Flag apakah karyawan resign
            $affectsGajian2 = $data['affectsGajian2'] ?? false;
            $resignationDate = $data['resignationDate'] ?? null;
            $tanggalMasuk = $data['tanggalMasuk'] ?? null;
            $isMutatedEmployee = $data['isMutatedEmployee'] ?? false;
            $mutationEffectiveDate = $data['mutationEffectiveDate'] ?? null;
            $mutationRole = $data['mutationRole'] ?? null;
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

            // Hitung service charge (mirror index())
            $serviceChargeByPointAmount = 0;
            $serviceChargeProRateAmount = 0;
            $serviceChargeTotal = 0;

            if ($masterData->sc == 1 && $serviceCharge > 0) {
                $scAmounts = PayrollSplitPoolCalculator::calculateUserAmount(
                    true,
                    (float) $serviceCharge,
                    $rateByPoint,
                    $rateProRate,
                    (float) $userPoint,
                    (float) $hariKerjaUntukServiceCharge,
                    $isMutatedEmployee,
                    $mutationEffectiveDate,
                    (int) $year,
                    (int) $month,
                    $affectsGajian2,
                    $resignationDate,
                    $tanggalMasuk,
                    $isNewEmployee,
                    $mutationRole
                );
                $serviceChargeByPointAmount = $scAmounts['by_point'];
                $serviceChargeProRateAmount = $scAmounts['pro_rate'];
                $serviceChargeTotal = $scAmounts['total'];
            }

            // Hitung BPJS JKN & TK (karyawan) + rincian perusahaan (informasi)
            $bpjsJKN = 0;
            $bpjsTK = 0;
            $bpjsPerusahaanDetail = null;
            if ($masterData->bpjs_jkn == 1 || $masterData->bpjs_tk == 1) {
                $userLevel = $jabatanLevels[$user->id_jabatan] ?? null;
                $levelRow = $userLevel ? $dataLevelRowsById->get($userLevel) : null;
                $dasarBpjs = PayrollBpjsCalculator::resolveDasarFromLevel($levelRow);
                $katId = $userLevel ? ($levelBpjsKategoriId[$userLevel] ?? null) : null;
                $katRow = ($katId && $bpjsKategoriById->has($katId)) ? $bpjsKategoriById->get($katId) : null;
                $bpjsCalc = PayrollBpjsCalculator::calculate(
                    $masterData,
                    $dasarBpjs['kesehatan'],
                    $dasarBpjs['ketenagakerjaan'],
                    $katRow,
                    (int) ($user->id_outlet ?? 0)
                );
                $bpjsJKN = $bpjsCalc['bpjs_jkn'];
                $bpjsTK = $bpjsCalc['bpjs_tk'];
                $bpjsPerusahaanDetail = $bpjsCalc['perusahaan_detail'];

                if ($isMutatedEmployee) {
                    $bpjsProrated = $this->prorateBpjsForMutationSegment(
                        $bpjsJKN,
                        $bpjsTK,
                        $bpjsPerusahaanDetail,
                        (float) ($data['mutationGajian1Ratio'] ?? 0)
                    );
                    $bpjsJKN = $bpjsProrated['bpjs_jkn'];
                    $bpjsTK = $bpjsProrated['bpjs_tk'];
                    $bpjsPerusahaanDetail = $bpjsProrated['perusahaan_detail'];
                }
            }

            // Hitung L & B (mirror service charge)
            $lbByPointAmount = 0;
            $lbProRateAmount = 0;
            $lbTotal = 0;

            if ($masterData->lb == 1 && $lbAmount > 0) {
                $lbAmounts = PayrollSplitPoolCalculator::calculateUserAmount(
                    true,
                    (float) $lbAmount,
                    $rateLBByPoint,
                    $rateLBProRate,
                    (float) $userPoint,
                    (float) $hariKerjaUntukServiceCharge,
                    $isMutatedEmployee,
                    $mutationEffectiveDate,
                    (int) $year,
                    (int) $month,
                    $affectsGajian2,
                    $resignationDate,
                    $tanggalMasuk,
                    $isNewEmployee,
                    $mutationRole
                );
                $lbByPointAmount = $lbAmounts['by_point'];
                $lbProRateAmount = $lbAmounts['pro_rate'];
                $lbTotal = $lbAmounts['total'];
            }

            // Hitung Deviasi (mirror service charge)
            $deviasiByPointAmount = 0;
            $deviasiProRateAmount = 0;
            $deviasiTotal = 0;

            if ($masterData->deviasi == 1 && $deviasiAmount > 0) {
                $deviasiAmounts = PayrollSplitPoolCalculator::calculateUserAmount(
                    true,
                    (float) $deviasiAmount,
                    $rateDeviasiByPoint,
                    $rateDeviasiProRate,
                    (float) $userPoint,
                    (float) $hariKerjaUntukServiceCharge,
                    $isMutatedEmployee,
                    $mutationEffectiveDate,
                    (int) $year,
                    (int) $month,
                    $affectsGajian2,
                    $resignationDate,
                    $tanggalMasuk,
                    $isNewEmployee,
                    $mutationRole
                );
                $deviasiByPointAmount = $deviasiAmounts['by_point'];
                $deviasiProRateAmount = $deviasiAmounts['pro_rate'];
                $deviasiTotal = $deviasiAmounts['total'];
            }

            // Hitung City Ledger (mirror service charge)
            $cityLedgerByPointAmount = 0;
            $cityLedgerProRateAmount = 0;
            $cityLedgerTotal = 0;

            if ($masterData->city_ledger == 1 && $cityLedgerAmount > 0) {
                $cityLedgerAmounts = PayrollSplitPoolCalculator::calculateUserAmount(
                    true,
                    (float) $cityLedgerAmount,
                    $rateCityLedgerByPoint,
                    $rateCityLedgerProRate,
                    (float) $userPoint,
                    (float) $hariKerjaUntukServiceCharge,
                    $isMutatedEmployee,
                    $mutationEffectiveDate,
                    (int) $year,
                    (int) $month,
                    $affectsGajian2,
                    $resignationDate,
                    $tanggalMasuk,
                    $isNewEmployee,
                    $mutationRole
                );
                $cityLedgerByPointAmount = $cityLedgerAmounts['by_point'];
                $cityLedgerProRateAmount = $cityLedgerAmounts['pro_rate'];
                $cityLedgerTotal = $cityLedgerAmounts['total'];
            }

            // Hitung potongan telat (flat rate Rp 500 per menit)
            $potonganTelat = 0;
            $gajiPerMenit = 500; // Flat rate Rp 500 per menit
            if ($totalTelat > 0) {
                $potonganTelat = $totalTelat * $gajiPerMenit;
            }

            // Hitung gaji pokok dan tunjangan (pro rate untuk karyawan baru dan karyawan resign)
            // PENTING: Gunakan hari kerja yang sama dengan service charge prorate (proporsi yang sama)
            // Service charge prorate menggunakan: rate × hari kerja untuk service charge
            // Dimana rate = total service charge prorate / total hari kerja semua karyawan
            // Jadi untuk gaji pokok dan tunjangan, gunakan proporsi yang sama: (hari kerja untuk service charge / total hari kerja standar)
            $gajiPokokFinal = $masterData->gaji;
            $tunjanganFinal = $masterData->tunjangan;
            
            // Hitung total hari kerja standar dalam periode payroll (dari tanggal 26 bulan sebelumnya sampai 25 bulan yang dipilih)
            // Ini adalah total hari kalender dalam periode, bukan total hari kerja karyawan
            $totalHariKalenderPeriode = $startDate->diffInDays($endDate) + 1; // +1 untuk include tanggal awal dan akhir
            
            if ($hariKerja <= 0) {
                $gajiPokokFinal = 0;
                $tunjanganFinal = 0;
            } elseif ($isNewEmployee === true && $isResignedEmployee === true && $hariKerjaProrateGajian1 > 0) {
                // Kasus khusus: Karyawan baru yang resign dalam periode yang sama
                // Pro rate menggunakan proporsi yang sama dengan service charge prorate
                // Gaji pokok prorate = gaji pokok × (hari kerja untuk service charge / total hari kalender dalam periode)
                // Hari kerja untuk service charge sudah dihitung dari tanggal masuk sampai tanggal resign
                $gajiPokokFinal = $masterData->gaji * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                // Tunjangan prorate = tunjangan × (hari kerja untuk service charge / total hari kalender dalam periode)
                $tunjanganFinal = $masterData->tunjangan * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                
                $resignation = $resignations->get($user->id);
                \Log::info('Karyawan baru yang resign - Pro rate calculation (Export)', [
                    'user_id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'tanggal_masuk' => $user->tanggal_masuk,
                    'resignation_date' => $resignation ? $resignation->resignation_date : null,
                    'is_new_employee' => $isNewEmployee,
                    'is_resigned_employee' => $isResignedEmployee,
                    'hari_kerja_dari_masuk_ke_resign' => $hariKerjaProrateGajian1,
                    'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                    'gaji_pokok_original' => $masterData->gaji,
                    'tunjangan_original' => $masterData->tunjangan,
                    'gaji_pokok_pro_rate' => $gajiPokokFinal,
                    'tunjangan_pro_rate' => $tunjanganFinal,
                    'formula_gaji' => "{$masterData->gaji} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$gajiPokokFinal}",
                    'formula_tunjangan' => "{$masterData->tunjangan} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$tunjanganFinal}"
                ]);
            } elseif ($isNewEmployee === true && $hariKerjaProrateGajian1 > 0) {
                // Karyawan baru saja (tidak resign)
                // Pro rate menggunakan proporsi yang sama dengan service charge prorate
                // Gaji pokok prorate = gaji pokok × (hari kerja untuk service charge / total hari kalender dalam periode)
                // Ini sama dengan proporsi yang digunakan service charge prorate
                $gajiPokokFinal = $masterData->gaji * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                // Tunjangan prorate = tunjangan × (hari kerja untuk service charge / total hari kalender dalam periode)
                $tunjanganFinal = $masterData->tunjangan * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                
                \Log::info('Karyawan baru - Pro rate calculation (Export)', [
                    'user_id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'tanggal_masuk' => $user->tanggal_masuk,
                    'is_new_employee' => $isNewEmployee,
                    'hari_kerja_karyawan_baru' => $hariKerjaKaryawanBaru,
                    'hari_kerja_prorate_gajian1' => $hariKerjaProrateGajian1,
                    'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                    'gaji_pokok_original' => $masterData->gaji,
                    'tunjangan_original' => $masterData->tunjangan,
                    'gaji_pokok_pro_rate' => $gajiPokokFinal,
                    'tunjangan_pro_rate' => $tunjanganFinal,
                    'formula_gaji' => "{$masterData->gaji} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$gajiPokokFinal}",
                    'formula_tunjangan' => "{$masterData->tunjangan} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$tunjanganFinal}"
                ]);
            } elseif ($isResignedEmployee === true && $hariKerjaProrateGajian1 > 0) {
                // Karyawan resign saja (bukan baru)
                // Untuk karyawan resign, hitung prorate dari awal periode sampai tanggal resign
                // Gaji pokok prorate = gaji pokok × (hari kerja untuk service charge / total hari kalender dalam periode)
                $gajiPokokFinal = $masterData->gaji * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                // Tunjangan prorate = tunjangan × (hari kerja untuk service charge / total hari kalender dalam periode)
                $tunjanganFinal = $masterData->tunjangan * ($hariKerjaProrateGajian1 / $totalHariKalenderPeriode);
                
                $resignation = $resignations->get($user->id);
                \Log::info('Karyawan resign - Pro rate calculation (Export)', [
                    'user_id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'is_resigned_employee' => $isResignedEmployee,
                    'resignation_date' => $resignation ? $resignation->resignation_date : null,
                    'hari_kerja_karyawan_resign' => $hariKerjaKaryawanResign,
                    'hari_kerja_prorate_gajian1' => $hariKerjaProrateGajian1,
                    'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                    'gaji_pokok_original' => $masterData->gaji,
                    'tunjangan_original' => $masterData->tunjangan,
                    'gaji_pokok_pro_rate' => $gajiPokokFinal,
                    'tunjangan_pro_rate' => $tunjanganFinal,
                    'formula_gaji' => "{$masterData->gaji} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$gajiPokokFinal}",
                    'formula_tunjangan' => "{$masterData->tunjangan} × ({$hariKerjaProrateGajian1} / {$totalHariKalenderPeriode}) = {$tunjanganFinal}"
                ]);
            } elseif ($isMutatedEmployee === true && $hariKerja > 0) {
                $gajiPokokFinal = $masterData->gaji * ($hariKerja / $totalHariKalenderPeriode);
                $tunjanganFinal = $masterData->tunjangan * ($hariKerja / $totalHariKalenderPeriode);
            } else {
                // Karyawan biasa: tidak di-prorate (gaji pokok dan tunjangan full)
                \Log::info('Karyawan biasa - No pro rate (Export)', [
                    'user_id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'is_new_employee' => $isNewEmployee,
                    'is_resigned_employee' => $isResignedEmployee,
                    'hari_kerja_untuk_service_charge' => $hariKerjaUntukServiceCharge,
                    'total_hari_kalender_periode' => $totalHariKalenderPeriode,
                    'gaji_pokok_final' => $gajiPokokFinal,
                    'tunjangan_final' => $tunjanganFinal,
                ]);
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
            // PERBAIKAN: Pastikan payment_method diambil dengan benar dari payroll_generated_details
            if ($payrollDetail && isset($payrollDetail->payment_method) && !empty($payrollDetail->payment_method)) {
                $paymentMethod = $payrollDetail->payment_method;
            } else {
                $paymentMethod = 'transfer'; // Default jika belum di-generate atau belum di-set
            }
            
            // Debug: Log payment method untuk user tertentu
            if ($userId <= 10) { // Log untuk 10 user pertama saja untuk menghindari log terlalu banyak
                \Log::info('Export - Payment Method', [
                    'user_id' => $userId,
                    'nama_lengkap' => $user->nama_lengkap,
                    'payroll_detail_exists' => $payrollDetail ? true : false,
                    'payment_method' => $paymentMethod,
                    'payment_method_from_detail' => $payrollDetail->payment_method ?? 'null'
                ]);
            }
            
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
                $lbByPointAmount = $payrollDetail->lb_by_point ?? $lbByPointAmount;
                $lbProRateAmount = $payrollDetail->lb_pro_rate ?? $lbProRateAmount;
                $lbTotal = $payrollDetail->lb_total ?? $lbTotal;
                $deviasiByPointAmount = $payrollDetail->deviasi_by_point ?? $deviasiByPointAmount;
                $deviasiProRateAmount = $payrollDetail->deviasi_pro_rate ?? $deviasiProRateAmount;
                $deviasiTotal = $payrollDetail->deviasi_total ?? $deviasiTotal;
                $cityLedgerByPointAmount = $payrollDetail->city_ledger_by_point ?? $cityLedgerByPointAmount;
                $cityLedgerProRateAmount = $payrollDetail->city_ledger_pro_rate ?? $cityLedgerProRateAmount;
                $cityLedgerTotal = $payrollDetail->city_ledger_total ?? $cityLedgerTotal;
                $phBonus = $payrollDetail->ph_bonus ?? $phBonus;
                $hariKerja = $payrollDetail->hari_kerja ?? $hariKerja;
                // PERBAIKAN: Jangan timpa payment_method jika sudah di-set sebelumnya, atau jika payment_method dari detail tidak null
                if (isset($payrollDetail->payment_method) && !empty($payrollDetail->payment_method)) {
                    $paymentMethod = $payrollDetail->payment_method;
                }
                
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
            
            $potonganKasbonExport = 0;
            $kasbonPreviewExport = $kasbonServiceExport->previewForUser($user->id, $kasbonEligibleByUserExport);
            if ($kasbonPreviewExport) {
                $potonganKasbonExport = $kasbonPreviewExport['potongan_kasbon'];
            }

            // Hitung Gajian 1: Gaji Pokok + Tunjangan + Custom Earning (gajian1) - Custom Deduction (gajian1) - BPJS JKN - BPJS TK - Telat - Alpha - Unpaid Leave - Kasbon
            $totalGajian1 = $gajiPokokFinal + $tunjanganFinal + $customEarnings - $customDeductions - ($bpjsJKN ?? 0) - ($bpjsTK ?? 0) - $potonganTelat - $potonganAlpha - $potonganUnpaidLeave - $potonganKasbonExport;
            
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
                'Potongan Kasbon' => round($potonganKasbonExport),
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
        
        // Debug: Log tanggal yang diterima
        \Log::info('Attendance Detail - Received dates', [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_date_type' => gettype($startDate),
            'end_date_type' => gettype($endDate)
        ]);

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
                'u.division_id',
                'o.id_outlet as outlet_id'
            )
            ->where('u.id', $userId)
            ->where('a.scan_date', '>=', $startDate . ' 00:00:00')
            ->where('a.scan_date', '<', date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00')
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
                'inoutmode' => $scan->inoutmode,
                'outlet_id' => $scan->outlet_id ?? null,
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
                $row->lembur = floor(app(\App\Services\AttendanceWorkTimelineService::class)->calculateOvertimeHours(
                    (int) ($row->work_minutes ?? 0),
                    $shiftData->time_start,
                    $shiftData->time_end
                ));
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
        $payrollMasterRows = $this->buildPayrollMasterLookup($outletId);
        $masterData = $this->resolvePayrollMasterForUser($user, $payrollMasterRows, $outletId);

        // Get position, division data
        $jabatan = DB::table('tbl_data_jabatan')->where('id_jabatan', $user->id_jabatan)->value('nama_jabatan');
        $divisi = DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nama_divisi');

        // Hitung periode payroll untuk perhitungan alpha dan leave (harus didefinisikan sebelum digunakan)
        $startDate = Carbon::create($year, $month, 26)->subMonth();
        $endDate = Carbon::create($year, $month, 25);

        $bpjsPerusahaanDetail = null;

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
            $bpjsPerusahaanDetail = ! empty($payrollDetail->bpjs_perusahaan_detail)
                ? (json_decode($payrollDetail->bpjs_perusahaan_detail, true) ?: null)
                : null;
            $customEarnings = $payrollDetail->custom_earnings ?? 0;
            $customDeductions = $payrollDetail->custom_deductions ?? 0;
            $serviceChargeAmount = $payrollDetail->service_charge ?? 0;
            $totalGaji = $payrollDetail->total_gaji ?? 0;
            $hariKerja = $payrollDetail->hari_kerja ?? 0;
            $totalLembur = $payrollDetail->total_lembur ?? 0;
            $totalAlpha = $payrollDetail->total_alpha ?? 0;
            $potonganAlpha = $payrollDetail->potongan_alpha ?? 0;
            $potonganUnpaidLeave = $payrollDetail->potongan_unpaid_leave ?? 0;
            $potonganKasbon = $payrollDetail->potongan_kasbon ?? 0;
            
            // Get custom items dari JSON
            $customItems = collect([]);
            if ($payrollDetail->custom_items) {
                // Decode sebagai objects (false) agar bisa diakses dengan -> seperti di view
                $decodedItems = json_decode($payrollDetail->custom_items, false) ?? [];
                $customItems = collect($decodedItems);
            }

            $customSplit = $this->splitStoredPayrollCustomItems($payrollDetail, $customItems);
            $customItemsGajian1 = $customSplit['custom_items_gajian1'];
            $customItemsGajian2 = $customSplit['custom_items_gajian2'];
            $customEarningsGajian1 = $customSplit['custom_earnings_gajian1'];
            $customDeductionsGajian1 = $customSplit['custom_deductions_gajian1'];
            $customEarningsGajian2 = $customSplit['custom_earnings_gajian2'];
            $customDeductionsGajian2 = $customSplit['custom_deductions_gajian2'];
            $customEarnings = $customEarningsGajian1;
            $customDeductions = $customDeductionsGajian1;
            
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
            $hariKerja = $attendanceData->filter(function ($item) {
                return ! empty($item['has_check_in']) && empty($item['is_off']);
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

            // Hitung BPJS (karyawan + rincian perusahaan)
            $bpjsCalc = $this->calculateBpjsPayrollForSingleUser($user, $masterData);
            $bpjsJKN = $bpjsCalc['bpjs_jkn'];
            $bpjsTK = $bpjsCalc['bpjs_tk'];
            $bpjsPerusahaanDetail = $bpjsCalc['perusahaan_detail'];

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
        $periodeLabel = $this->formatPayrollPeriodMonthLabel((int) $month, (int) $year);
        
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
        
        // Hitung total gaji berdasarkan type (gunakan nilai periode + PayrollGajiSplitCalculator)
        $potonganKasbon = $potonganKasbon ?? 0;
        $totalGajiFinal = $this->resolveSlipPhaseTotal($type, [
            'gaji_pokok' => isset($gajiPokok) ? $gajiPokok : ($masterData->gaji ?? 0),
            'tunjangan' => isset($tunjangan) ? $tunjangan : ($masterData->tunjangan ?? 0),
            'custom_earnings_gajian1' => $customEarningsGajian1 ?? $customEarnings ?? 0,
            'custom_deductions_gajian1' => $customDeductionsGajian1 ?? $customDeductions ?? 0,
            'bpjs_jkn' => $bpjsJKN ?? 0,
            'bpjs_tk' => $bpjsTK ?? 0,
            'potongan_telat' => $potonganTelat ?? 0,
            'potongan_alpha' => $potonganAlpha ?? 0,
            'potongan_unpaid_leave' => $potonganUnpaidLeave ?? 0,
            'potongan_kasbon' => $potonganKasbon,
            'service_charge' => $serviceChargeAmount ?? 0,
            'uang_makan' => $uangMakan ?? 0,
            'gaji_lembur' => $gajiLembur ?? 0,
            'ph_bonus' => $phBonus ?? 0,
            'custom_earnings_gajian2' => $customEarningsGajian2 ?? 0,
            'custom_deductions_gajian2' => $customDeductionsGajian2 ?? 0,
            'lb_total' => $lbTotal ?? 0,
            'deviasi_total' => $deviasiTotal ?? 0,
            'city_ledger_total' => $cityLedgerTotal ?? 0,
        ]);

        // Check if download PDF is requested
        if ($request->has('download') && $request->download === 'pdf') {
            $pdf = \PDF::loadView('payroll.slip', [
                'user' => $user,
                'outlet' => $outlet,
                'jabatan' => $jabatan,
                'divisi' => $divisi,
                'periode' => $periode,
                'periode_label' => $periodeLabel,
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
                'bpjs_perusahaan_detail' => $bpjsPerusahaanDetail,
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
                'potongan_kasbon' => round($potonganKasbon),
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
            'periode_label' => $periodeLabel,
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
            'bpjs_perusahaan_detail' => $bpjsPerusahaanDetail,
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
            'potongan_kasbon' => round($potonganKasbon ?? 0),
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

        // Ambil data karyawan (tanpa filter outlet agar slip historis tetap bisa dicetak)
        $user = User::where('id', $userId)
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
        $payrollMasterRows = $this->buildPayrollMasterLookup($outletId);
        $masterData = $this->resolvePayrollMasterForUser($user, $payrollMasterRows, $outletId);

        $bpjsPerusahaanDetail = null;

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
            $bpjsPerusahaanDetail = ! empty($payrollDetail->bpjs_perusahaan_detail)
                ? (json_decode($payrollDetail->bpjs_perusahaan_detail, true) ?: null)
                : null;
            $customEarnings = $payrollDetail->custom_earnings ?? 0;
            $customDeductions = $payrollDetail->custom_deductions ?? 0;
            $serviceChargeAmount = $payrollDetail->service_charge ?? 0;
            $totalGaji = $payrollDetail->total_gaji ?? 0;
            $hariKerja = $payrollDetail->hari_kerja ?? 0;
            $totalLembur = $payrollDetail->total_lembur ?? 0;
            $totalAlpha = $payrollDetail->total_alpha ?? 0;
            $potonganAlpha = $payrollDetail->potongan_alpha ?? 0;
            $potonganUnpaidLeave = $payrollDetail->potongan_unpaid_leave ?? 0;
            $potonganKasbon = $payrollDetail->potongan_kasbon ?? 0;
            
            // Get custom items dari JSON
            $customItems = collect([]);
            if ($payrollDetail->custom_items) {
                // Decode sebagai objects (false) agar bisa diakses dengan -> seperti di view
                $decodedItems = json_decode($payrollDetail->custom_items, false) ?? [];
                $customItems = collect($decodedItems);
            }

            $customSplit = $this->splitStoredPayrollCustomItems($payrollDetail, $customItems);
            $customItemsGajian1 = $customSplit['custom_items_gajian1'];
            $customItemsGajian2 = $customSplit['custom_items_gajian2'];
            $customEarningsGajian1 = $customSplit['custom_earnings_gajian1'];
            $customDeductionsGajian1 = $customSplit['custom_deductions_gajian1'];
            $customEarningsGajian2 = $customSplit['custom_earnings_gajian2'];
            $customDeductionsGajian2 = $customSplit['custom_deductions_gajian2'];
            $customEarnings = $customEarningsGajian1;
            $customDeductions = $customDeductionsGajian1;
            
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
            $hariKerja = $attendanceData->filter(function ($item) {
                return ! empty($item['has_check_in']) && empty($item['is_off']);
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

            // Hitung BPJS (karyawan + rincian perusahaan)
            $bpjsCalc = $this->calculateBpjsPayrollForSingleUser($user, $masterData);
            $bpjsJKN = $bpjsCalc['bpjs_jkn'];
            $bpjsTK = $bpjsCalc['bpjs_tk'];
            $bpjsPerusahaanDetail = $bpjsCalc['perusahaan_detail'];

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
        $periodeLabel = $this->formatPayrollPeriodMonthLabel((int) $month, (int) $year);
        
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
        
        // Hitung total gaji berdasarkan type (gunakan nilai periode + PayrollGajiSplitCalculator)
        $potonganKasbon = $potonganKasbon ?? 0;
        $totalGajiFinal = $this->resolveSlipPhaseTotal($type, [
            'gaji_pokok' => isset($gajiPokok) ? $gajiPokok : ($masterData->gaji ?? 0),
            'tunjangan' => isset($tunjangan) ? $tunjangan : ($masterData->tunjangan ?? 0),
            'custom_earnings_gajian1' => $customEarningsGajian1 ?? $customEarnings ?? 0,
            'custom_deductions_gajian1' => $customDeductionsGajian1 ?? $customDeductions ?? 0,
            'bpjs_jkn' => $bpjsJKN ?? 0,
            'bpjs_tk' => $bpjsTK ?? 0,
            'potongan_telat' => $potonganTelat ?? 0,
            'potongan_alpha' => $potonganAlpha ?? 0,
            'potongan_unpaid_leave' => $potonganUnpaidLeave ?? 0,
            'potongan_kasbon' => $potonganKasbon,
            'service_charge' => $serviceChargeAmount ?? 0,
            'uang_makan' => $uangMakan ?? 0,
            'gaji_lembur' => $gajiLembur ?? 0,
            'ph_bonus' => $phBonus ?? 0,
            'custom_earnings_gajian2' => $customEarningsGajian2 ?? 0,
            'custom_deductions_gajian2' => $customDeductionsGajian2 ?? 0,
            'lb_total' => $lbTotal ?? 0,
            'deviasi_total' => $deviasiTotal ?? 0,
            'city_ledger_total' => $cityLedgerTotal ?? 0,
        ]);

        // Generate PDF
        $pdf = \PDF::loadView('payroll.slip', [
            'user' => $user,
            'jabatan' => $jabatan,
            'divisi' => $divisi,
            'outlet' => $outlet,
            'periode' => $periode,
            'periode_label' => $periodeLabel,
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
            'bpjs_perusahaan_detail' => $bpjsPerusahaanDetail,
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
            'potongan_kasbon' => round($potonganKasbon ?? 0),
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
            'gajian_type' => 'required|in:gajian1,gajian2',
            'outlet_id' => 'required|integer',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
            'service_charge' => 'nullable|numeric|min:0',
            'lb_amount' => 'nullable|numeric|min:0',
            'deviasi_amount' => 'nullable|numeric|min:0',
            'city_ledger_amount' => 'nullable|numeric|min:0',
            'payroll_data' => 'required|array',
        ]);

        return $this->persistPayrollPhase($request, 'generate');
    }

    // Edit Payroll - Update payroll yang sudah di-generate (per fase)
    public function editPayroll(Request $request)
    {
        $request->validate([
            'gajian_type' => 'required|in:gajian1,gajian2',
            'payroll_id' => 'required|integer',
            'service_charge' => 'nullable|numeric|min:0',
            'lb_amount' => 'nullable|numeric|min:0',
            'deviasi_amount' => 'nullable|numeric|min:0',
            'city_ledger_amount' => 'nullable|numeric|min:0',
            'payroll_data' => 'required|array',
        ]);

        return $this->persistPayrollPhase($request, 'edit');
    }

    private function persistPayrollPhase(Request $request, string $action)
    {
        try {
            DB::beginTransaction();

            $gajianType = $request->gajian_type;
            $phaseService = app(PayrollGeneratePhaseService::class);
            $kasbonService = app(PayrollKasbonService::class);
            $payrollData = $request->payroll_data;
            $amounts = [
                'service_charge' => $request->service_charge ?? 0,
                'lb_amount' => $request->lb_amount ?? 0,
                'deviasi_amount' => $request->deviasi_amount ?? 0,
                'city_ledger_amount' => $request->city_ledger_amount ?? 0,
            ];

            $statusColumn = $gajianType === PayrollGeneratePhaseService::GAJIAN2 ? 'gajian2_status' : 'gajian1_status';
            $generatedAtColumn = $gajianType === PayrollGeneratePhaseService::GAJIAN2 ? 'gajian2_generated_at' : 'gajian1_generated_at';
            $phaseLabel = $gajianType === PayrollGeneratePhaseService::GAJIAN2 ? 'Gajian 2 (Tanggal 8)' : 'Gajian 1 (Akhir Bulan)';

            if ($action === 'edit') {
                $payroll = DB::table('payroll_generated')->where('id', $request->payroll_id)->first();
                if (!$payroll) {
                    return response()->json(['success' => false, 'message' => 'Payroll tidak ditemukan'], 404);
                }
                $payrollId = (int) $payroll->id;
            } else {
                $payroll = DB::table('payroll_generated')
                    ->where('outlet_id', $request->outlet_id)
                    ->where('month', $request->month)
                    ->where('year', $request->year)
                    ->first();

                if ($payroll) {
                    $payrollId = (int) $payroll->id;
                } else {
                    $payrollId = DB::table('payroll_generated')->insertGetId([
                        'outlet_id' => $request->outlet_id,
                        'month' => $request->month,
                        'year' => $request->year,
                        'service_charge' => 0,
                        'lb_amount' => 0,
                        'deviasi_amount' => 0,
                        'city_ledger_amount' => 0,
                        'status' => 'draft',
                        'gajian1_status' => 'draft',
                        'gajian2_status' => 'draft',
                        'created_at' => now(),
                        'created_by' => auth()->id(),
                        'updated_at' => now(),
                        'updated_by' => auth()->id(),
                    ]);
                    $payroll = DB::table('payroll_generated')->where('id', $payrollId)->first();
                }
            }

            if ($locked = $phaseService->assertPhaseNotLocked($payroll, $gajianType)) {
                return response()->json($locked, $locked['code']);
            }

            if ($gajianType === PayrollGeneratePhaseService::GAJIAN2 && ($payroll->gajian1_status ?? 'draft') !== 'generated') {
                return response()->json([
                    'success' => false,
                    'message' => 'Generate Gajian 1 terlebih dahulu sebelum Generate Gajian 2',
                ], 422);
            }

            if ($gajianType === PayrollGeneratePhaseService::GAJIAN2) {
                $hasGajian2Values = collect($payrollData)->contains(function ($item) use ($amounts) {
                    $serviceCharge = (float) ($item['service_charge'] ?? 0);
                    $gajiLembur = (float) ($item['gaji_lembur'] ?? 0);
                    $uangMakan = (float) ($item['uang_makan'] ?? 0);
                    $lbTotal = (float) ($item['lb_total'] ?? 0);
                    $deviasiTotal = (float) ($item['deviasi_total'] ?? 0);
                    $cityLedgerTotal = (float) ($item['city_ledger_total'] ?? 0);
                    $phBonus = (float) ($item['ph_bonus'] ?? 0);

                    return $serviceCharge > 0
                        || $gajiLembur > 0
                        || $uangMakan > 0
                        || $lbTotal > 0
                        || $deviasiTotal > 0
                        || $cityLedgerTotal > 0
                        || $phBonus > 0;
                });

                $headerHasGajian2Input = ($amounts['service_charge'] ?? 0) > 0
                    || ($amounts['lb_amount'] ?? 0) > 0
                    || ($amounts['deviasi_amount'] ?? 0) > 0
                    || ($amounts['city_ledger_amount'] ?? 0) > 0;

                if (!$hasGajian2Values && $headerHasGajian2Input) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data Gajian 2 belum terhitung. Klik "Lihat Data" terlebih dahulu (pastikan Service Charge / L&B / Deviasi / City Ledger sudah diisi), baru Generate Gajian 2.',
                    ], 422);
                }
            }

            if ($gajianType === PayrollGeneratePhaseService::GAJIAN1) {
                $kasbonService->reversePayrollDeductions($payrollId);
            }

            $headerUpdate = array_merge(
                $phaseService->headerUpdatesForPhase($gajianType, $amounts),
                [
                    $statusColumn => 'generated',
                    $generatedAtColumn => now(),
                    'updated_at' => now(),
                    'updated_by' => auth()->id(),
                ]
            );

            DB::table('payroll_generated')->where('id', $payrollId)->update($headerUpdate);

            foreach ($payrollData as $item) {
                $userId = (int) ($item['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }

                $existing = DB::table('payroll_generated_details')
                    ->where('payroll_generated_id', $payrollId)
                    ->where('user_id', $userId)
                    ->first();

                $phaseFields = $phaseService->phaseFieldsFromItem($gajianType, $item);
                $baseFields = $phaseService->baseFieldsFromItem($item);
                $merged = $phaseService->mergeDetailRow($existing, $phaseFields, $baseFields);

                if ($existing) {
                    $updatePayload = $phaseService->detailInsertPayload($payrollId, $userId, $merged);
                    unset($updatePayload['created_at']);
                    DB::table('payroll_generated_details')
                        ->where('id', $existing->id)
                        ->update($updatePayload);
                } else {
                    DB::table('payroll_generated_details')->insert(
                        $phaseService->detailInsertPayload($payrollId, $userId, $merged)
                    );
                }
            }

            if ($gajianType === PayrollGeneratePhaseService::GAJIAN1) {
                $kasbonService->applyPayrollDeductions($payrollId, $payrollData);
            }

            $phaseService->syncOverallStatus($payrollId);

            DB::commit();

            $verb = $action === 'edit' ? 'di-update' : 'di-generate';

            return response()->json([
                'success' => true,
                'message' => "{$phaseLabel} berhasil {$verb} dan disimpan",
                'payroll_id' => $payrollId,
                'gajian_type' => $gajianType,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error {$action} payroll phase: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan payroll: ' . $e->getMessage(),
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

    // Rollback Payroll - Hapus payroll atau reset per fase
    public function rollbackPayroll(Request $request)
    {
        $request->validate([
            'payroll_id' => 'required|integer',
            'gajian_type' => 'nullable|in:gajian1,gajian2',
        ]);

        try {
            DB::beginTransaction();

            $payrollId = (int) $request->payroll_id;
            $gajianType = $request->gajian_type;
            $phaseService = app(PayrollGeneratePhaseService::class);
            $kasbonService = app(PayrollKasbonService::class);

            $payroll = DB::table('payroll_generated')->where('id', $payrollId)->first();

            if (!$payroll) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll tidak ditemukan',
                ], 404);
            }

            if ($payroll->status === 'locked') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payroll sudah di-lock dan tidak bisa di-rollback',
                ], 403);
            }

            if (!$gajianType) {
                $kasbonService->reversePayrollDeductions($payrollId);
                DB::table('payroll_generated_details')->where('payroll_generated_id', $payrollId)->delete();
                DB::table('payroll_generated')->where('id', $payrollId)->delete();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Payroll berhasil di-rollback (dihapus)',
                ]);
            }

            if ($locked = $phaseService->assertPhaseNotLocked($payroll, $gajianType)) {
                return response()->json($locked, $locked['code']);
            }

            $statusColumn = $gajianType === PayrollGeneratePhaseService::GAJIAN2 ? 'gajian2_status' : 'gajian1_status';
            $generatedAtColumn = $gajianType === PayrollGeneratePhaseService::GAJIAN2 ? 'gajian2_generated_at' : 'gajian1_generated_at';
            $phaseLabel = $gajianType === PayrollGeneratePhaseService::GAJIAN2 ? 'Gajian 2' : 'Gajian 1';

            if ($gajianType === PayrollGeneratePhaseService::GAJIAN1) {
                $kasbonService->reversePayrollDeductions($payrollId);
            }

            $clearFields = $phaseService->clearPhaseDetailFields($gajianType);
            $details = DB::table('payroll_generated_details')->where('payroll_generated_id', $payrollId)->get();

            foreach ($details as $detail) {
                $merged = array_merge((array) $detail, $clearFields);
                $merged['total_gaji'] = $phaseService->calculateTotalGaji($merged);

                $updatePayload = $phaseService->detailInsertPayload($payrollId, (int) $detail->user_id, $merged);
                unset($updatePayload['created_at'], $updatePayload['payroll_generated_id'], $updatePayload['user_id']);

                DB::table('payroll_generated_details')->where('id', $detail->id)->update($updatePayload);
            }

            $headerUpdate = [
                $statusColumn => 'draft',
                $generatedAtColumn => null,
                'updated_at' => now(),
                'updated_by' => auth()->id(),
            ];

            if ($gajianType === PayrollGeneratePhaseService::GAJIAN2) {
                $headerUpdate = array_merge($headerUpdate, [
                    'service_charge' => 0,
                    'lb_amount' => 0,
                    'deviasi_amount' => 0,
                    'city_ledger_amount' => 0,
                ]);
            }

            DB::table('payroll_generated')->where('id', $payrollId)->update($headerUpdate);

            $payroll = DB::table('payroll_generated')->where('id', $payrollId)->first();
            if (($payroll->gajian1_status ?? 'draft') === 'draft' && ($payroll->gajian2_status ?? 'draft') === 'draft') {
                DB::table('payroll_generated_details')->where('payroll_generated_id', $payrollId)->delete();
                DB::table('payroll_generated')->where('id', $payrollId)->delete();
            } else {
                $phaseService->syncOverallStatus($payrollId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$phaseLabel} berhasil di-rollback",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error rolling back payroll: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal rollback payroll: ' . $e->getMessage(),
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
                $gajian1Status = $payroll->gajian1_status ?? (($payroll->status ?? 'draft') === 'generated' ? 'generated' : 'draft');
                $gajian2Status = $payroll->gajian2_status ?? (($payroll->status ?? 'draft') === 'generated' ? 'generated' : 'draft');

                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'payroll_id' => $payroll->id,
                    'status' => $payroll->status,
                    'gajian1_status' => $gajian1Status,
                    'gajian2_status' => $gajian2Status,
                    'gajian1_generated_at' => $payroll->gajian1_generated_at ?? null,
                    'gajian2_generated_at' => $payroll->gajian2_generated_at ?? null,
                    'created_at' => $payroll->created_at,
                    'updated_at' => $payroll->updated_at,
                ]);
            }

            return response()->json([
                'success' => true,
                'exists' => false,
                'gajian1_status' => 'draft',
                'gajian2_status' => 'draft',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting payroll status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan status payroll: ' . $e->getMessage(),
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
            
            // Ambil data payroll yang sudah di-generate untuk user ini
            $payrollList = DB::table('payroll_generated_details as pgd')
                ->join('payroll_generated as pg', 'pgd.payroll_generated_id', '=', 'pg.id')
                ->leftJoin('tbl_data_outlet as o', 'pg.outlet_id', '=', 'o.id_outlet')
                ->where('pgd.user_id', $userId)
                ->select('pgd.*', 'pg.outlet_id', 'pg.month', 'pg.year', 'pg.status', 'o.nama_outlet as outlet_name', 'pg.created_at')
                ->orderBy('pg.year', 'desc')
                ->orderBy('pg.month', 'desc')
                ->orderBy('pg.created_at', 'desc')
                ->get();

            $result = [];
            foreach ($payrollList as $payroll) {
                $month = (int) $payroll->month;
                $year = (int) $payroll->year;
                $gajiSplit = $this->calculateDetailGajiSplit($payroll);

                $lastDayOfMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
                $gajian1Date = sprintf('%04d-%02d-%02d', $year, $month, $lastDayOfMonth);

                $nextMonth = $month + 1;
                $nextYear = $year;
                if ($nextMonth > 12) {
                    $nextMonth = 1;
                    $nextYear++;
                }
                $gajian2Date = sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, 8);

                $gajian1Available = $currentDate >= $gajian1Date;
                $gajian2Available = $currentDate >= $gajian2Date;

                $slips = [];
                if ($gajian1Available) {
                    $slips[] = [
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
                        'total_gaji' => $gajiSplit['total_gaji_akhir_bulan'],
                        'is_available' => true,
                        'status' => $payroll->status,
                    ];
                }
                if ($gajian2Available) {
                    $slips[] = [
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
                        'total_gaji' => $gajiSplit['total_gaji_tanggal_8'],
                        'is_available' => true,
                        'status' => $payroll->status,
                    ];
                }

                if (empty($slips)) {
                    continue;
                }

                $visibleTotal = 0;
                foreach ($slips as $slip) {
                    $visibleTotal += (float) ($slip['total_gaji'] ?? 0);
                }

                $result[] = [
                    'payroll_detail_id' => $payroll->id,
                    'user_id' => $payroll->user_id,
                    'outlet_id' => $payroll->outlet_id,
                    'outlet_name' => $payroll->outlet_name,
                    'month' => $month,
                    'year' => $year,
                    'periode' => $payroll->periode,
                    'periode_label' => $this->formatPayrollPeriodMonthLabel($month, $year),
                    'total_gaji' => round($visibleTotal),
                    'total_gajian1' => $gajiSplit['total_gaji_akhir_bulan'],
                    'total_gajian2' => $gajiSplit['total_gaji_tanggal_8'],
                    'total_gaji_full' => $gajiSplit['total_gaji'],
                    'gajian1_available' => $gajian1Available,
                    'gajian2_available' => $gajian2Available,
                    'status' => $payroll->status,
                    'slips' => $slips,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
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
                ->leftJoin('pr_kasbons as pk', 'pgd.pr_kasbon_id', '=', 'pk.id')
                ->where('pgd.id', $payrollDetailId)
                ->where('pgd.user_id', $userId)
                ->select('pgd.*', 'pg.month', 'pg.year', 'pg.outlet_id', 'o.nama_outlet as outlet_name', 'pk.pr_number as kasbon_pr_number')
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
                    'periode_label' => $this->formatPayrollPeriodMonthLabel(
                        (int) $payrollDetail->month,
                        (int) $payrollDetail->year
                    ),
                ]
            ];
            
            if ($type === 'gajian1') {
                $gajian1Custom = $this->parseSlipCustomItems($customItems, 'gajian1');

                $gajiSplit = PayrollGajiSplitCalculator::calculate([
                    'gaji_pokok' => $payrollDetail->gaji_pokok ?? 0,
                    'tunjangan' => $payrollDetail->tunjangan ?? 0,
                    'custom_earnings_gajian1' => $payrollDetail->custom_earnings ?? $gajian1Custom['custom_earnings'],
                    'custom_deductions_gajian1' => $payrollDetail->custom_deductions ?? $gajian1Custom['custom_deductions'],
                    'bpjs_jkn' => $payrollDetail->bpjs_jkn ?? 0,
                    'bpjs_tk' => $payrollDetail->bpjs_tk ?? 0,
                    'potongan_telat' => $payrollDetail->potongan_telat ?? 0,
                    'potongan_alpha' => $payrollDetail->potongan_alpha ?? 0,
                    'potongan_unpaid_leave' => $payrollDetail->potongan_unpaid_leave ?? 0,
                    'potongan_kasbon' => $payrollDetail->potongan_kasbon ?? 0,
                ]);

                $response['data']['gajian1'] = [
                    'gaji_pokok' => $payrollDetail->gaji_pokok ?? 0,
                    'tunjangan' => $payrollDetail->tunjangan ?? 0,
                    'custom_deductions' => $payrollDetail->custom_deductions ?? $gajian1Custom['custom_deductions'],
                    'custom_deduction_items' => $gajian1Custom['custom_deduction_items'],
                    'custom_earnings' => $payrollDetail->custom_earnings ?? $gajian1Custom['custom_earnings'],
                    'custom_earning_items' => $gajian1Custom['custom_earning_items'],
                    'bpjs_jkn' => $payrollDetail->bpjs_jkn ?? 0,
                    'bpjs_tk' => $payrollDetail->bpjs_tk ?? 0,
                    'potongan_telat' => $payrollDetail->potongan_telat ?? 0,
                    'total_telat' => $payrollDetail->total_telat ?? 0,
                    'gaji_per_menit' => $payrollDetail->gaji_per_menit ?? 500,
                    'total_alpha' => $payrollDetail->total_alpha ?? 0,
                    'potongan_alpha' => $payrollDetail->potongan_alpha ?? 0,
                    'potongan_unpaid_leave' => $payrollDetail->potongan_unpaid_leave ?? 0,
                    'potongan_kasbon' => $payrollDetail->potongan_kasbon ?? 0,
                    'kasbon_cicilan_ke' => $payrollDetail->kasbon_cicilan_ke ?? null,
                    'kasbon_pr_number' => $payrollDetail->kasbon_pr_number ?? null,
                    'leave_data' => $leaveData,
                    'leave_types' => $leaveTypes,
                    'total_gaji_gajian1' => $gajiSplit['total_gaji_akhir_bulan'],
                ];
            } else {
                $gajian2Custom = $this->parseSlipCustomItems($customItems, 'gajian2');

                $gajiSplit = PayrollGajiSplitCalculator::calculate([
                    'service_charge' => $payrollDetail->service_charge ?? 0,
                    'uang_makan' => $payrollDetail->uang_makan ?? 0,
                    'gaji_lembur' => $payrollDetail->gaji_lembur ?? 0,
                    'ph_bonus' => $payrollDetail->ph_bonus ?? 0,
                    'custom_earnings_gajian2' => $gajian2Custom['custom_earnings'],
                    'custom_deductions_gajian2' => $gajian2Custom['custom_deductions'],
                    'lb_total' => $payrollDetail->lb_total ?? 0,
                    'deviasi_total' => $payrollDetail->deviasi_total ?? 0,
                    'city_ledger_total' => $payrollDetail->city_ledger_total ?? 0,
                ]);

                $response['data']['gajian2'] = [
                    'service_charge_by_point' => $payrollDetail->service_charge_by_point ?? 0,
                    'service_charge_pro_rate' => $payrollDetail->service_charge_pro_rate ?? 0,
                    'service_charge' => $payrollDetail->service_charge ?? 0,
                    'uang_makan' => $payrollDetail->uang_makan ?? 0,
                    'nominal_uang_makan' => $payrollDetail->nominal_uang_makan ?? 0,
                    'hari_kerja' => $payrollDetail->hari_kerja ?? 0,
                    'total_lembur' => $payrollDetail->total_lembur ?? 0,
                    'nominal_lembur_per_jam' => $payrollDetail->nominal_lembur_per_jam ?? 0,
                    'gaji_lembur' => $payrollDetail->gaji_lembur ?? 0,
                    'custom_earnings' => $gajian2Custom['custom_earnings'],
                    'custom_earning_items' => $gajian2Custom['custom_earning_items'],
                    'custom_deductions' => $gajian2Custom['custom_deductions'],
                    'custom_deduction_items' => $gajian2Custom['custom_deduction_items'],
                    'lb_total' => $payrollDetail->lb_total ?? 0,
                    'deviasi_total' => $payrollDetail->deviasi_total ?? 0,
                    'city_ledger_total' => $payrollDetail->city_ledger_total ?? 0,
                    'ph_bonus' => $payrollDetail->ph_bonus ?? 0,
                    'total_gaji_gajian2' => $gajiSplit['total_gaji_tanggal_8'],
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
     * Download slip gaji PDF untuk karyawan (ymsoftapp self-service).
     */
    public function downloadUserPayrollSlipPdf(Request $request)
    {
        try {
            $userId = auth()->id();
            $payrollDetailId = $request->input('payroll_detail_id');
            $type = $request->input('type');

            if (!$payrollDetailId || !in_array($type, ['gajian1', 'gajian2', 'combined'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak lengkap',
                ], 400);
            }

            $row = DB::table('payroll_generated_details as pgd')
                ->join('payroll_generated as pg', 'pgd.payroll_generated_id', '=', 'pg.id')
                ->where('pgd.id', $payrollDetailId)
                ->where('pgd.user_id', $userId)
                ->select(
                    'pgd.user_id',
                    'pg.outlet_id',
                    'pg.month',
                    'pg.year',
                    'pg.service_charge'
                )
                ->first();

            if (!$row) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data payroll tidak ditemukan',
                ], 404);
            }

            if ($type === 'combined') {
                $context = $this->buildUserPayrollCombinedSlipContext((int) $payrollDetailId, $userId);
                if (!$context) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data payroll tidak ditemukan',
                    ], 404);
                }

                $pdf = \PDF::loadView('payroll.slip_combined', $context);
                $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $context['user']->nama_lengkap ?? 'karyawan');
                $filename = "Slip_Gaji_{$safeName}_{$context['month']}_{$context['year']}.pdf";

                return $pdf->download($filename);
            }

            return $this->printPayroll(new Request([
                'user_id' => $row->user_id,
                'outlet_id' => $row->outlet_id,
                'month' => $row->month,
                'year' => $row->year,
                'type' => $type,
                'service_charge' => $row->service_charge ?? 0,
            ]));
        } catch (\Exception $e) {
            \Log::error('Error downloading user payroll slip PDF: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengunduh slip gaji',
            ], 500);
        }
    }

    /**
     * Download slip gaji gabungan (Gajian 1 + Gajian 2) untuk karyawan.
     */
    public function downloadUserPayrollCombinedSlipPdf(Request $request)
    {
        try {
            $userId = auth()->id();
            $payrollDetailId = $request->input('payroll_detail_id');

            if (!$payrollDetailId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak lengkap',
                ], 400);
            }

            $context = $this->buildUserPayrollCombinedSlipContext((int) $payrollDetailId, $userId);
            if (!$context) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data payroll tidak ditemukan',
                ], 404);
            }

            $pdf = \PDF::loadView('payroll.slip_combined', $context);
            $filename = "Slip_Gaji_Gabungan_{$context['user']->nama_lengkap}_{$context['month']}_{$context['year']}.pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('Error downloading combined payroll slip PDF: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengunduh slip gaji gabungan',
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * Label periode untuk tampilan (mis. "April 2026").
     */
    private function formatPayrollPeriodMonthLabel(int $month, int $year): string
    {
        if ($month < 1 || $month > 12 || $year <= 0) {
            return 'Periode';
        }

        return Carbon::createFromDate($year, $month, 1)
            ->locale('id')
            ->translatedFormat('F Y');
    }

    private function buildUserPayrollCombinedSlipContext(int $payrollDetailId, int $userId): ?array
    {
        $payrollDetail = DB::table('payroll_generated_details as pgd')
            ->join('payroll_generated as pg', 'pgd.payroll_generated_id', '=', 'pg.id')
            ->where('pgd.id', $payrollDetailId)
            ->where('pgd.user_id', $userId)
            ->select('pgd.*', 'pg.month', 'pg.year', 'pg.outlet_id')
            ->first();

        if (!$payrollDetail) {
            return null;
        }

        $user = User::where('id', $userId)->where('status', 'A')->first();
        if (!$user) {
            return null;
        }

        $payrollMasterRows = $this->buildPayrollMasterLookup($payrollDetail->outlet_id);
        $masterData = $this->resolvePayrollMasterForUser($user, $payrollMasterRows, $payrollDetail->outlet_id);

        $customItems = collect([]);
        if ($payrollDetail->custom_items) {
            $customItems = collect(json_decode($payrollDetail->custom_items, false) ?? []);
        }
        $customSplit = $this->splitStoredPayrollCustomItems($payrollDetail, $customItems);

        $gajiSplit = $this->calculateDetailGajiSplit($payrollDetail);

        $jabatan = $payrollDetail->jabatan
            ?: DB::table('tbl_data_jabatan')->where('id_jabatan', $user->id_jabatan)->value('nama_jabatan');
        $divisi = $payrollDetail->divisi
            ?: DB::table('tbl_data_divisi')->where('id', $user->division_id)->value('nama_divisi');
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $payrollDetail->outlet_id)->value('nama_outlet');

        $imagePath = public_path('images/logojustusgroup.png');
        $logoBase64 = '';
        if (file_exists($imagePath) && is_readable($imagePath)) {
            $imageContent = file_get_contents($imagePath);
            if ($imageContent !== false) {
                $logoBase64 = base64_encode($imageContent);
            }
        }

        $leaveData = $payrollDetail->leave_data ? json_decode($payrollDetail->leave_data, true) : [];

        return [
            'user' => $user,
            'jabatan' => $jabatan,
            'divisi' => $divisi,
            'outlet' => $outlet,
            'periode' => $payrollDetail->periode,
            'periode_label' => $this->formatPayrollPeriodMonthLabel(
                (int) $payrollDetail->month,
                (int) $payrollDetail->year
            ),
            'month' => $payrollDetail->month,
            'year' => $payrollDetail->year,
            'hari_kerja' => $payrollDetail->hari_kerja ?? 0,
            'gaji_pokok' => $payrollDetail->gaji_pokok ?? 0,
            'tunjangan' => $payrollDetail->tunjangan ?? 0,
            'total_telat' => $payrollDetail->total_telat ?? 0,
            'potongan_telat' => $payrollDetail->potongan_telat ?? 0,
            'gaji_per_menit' => $payrollDetail->gaji_per_menit ?? 500,
            'total_alpha' => $payrollDetail->total_alpha ?? 0,
            'potongan_alpha' => $payrollDetail->potongan_alpha ?? 0,
            'potongan_unpaid_leave' => $payrollDetail->potongan_unpaid_leave ?? 0,
            'potongan_kasbon' => $payrollDetail->potongan_kasbon ?? 0,
            'bpjs_jkn' => $payrollDetail->bpjs_jkn ?? 0,
            'bpjs_tk' => $payrollDetail->bpjs_tk ?? 0,
            'custom_earnings' => $customSplit['custom_earnings_gajian1'],
            'custom_deductions' => $customSplit['custom_deductions_gajian1'],
            'custom_items' => $customItems,
            'custom_earnings_gajian1' => $customSplit['custom_earnings_gajian1'],
            'custom_deductions_gajian1' => $customSplit['custom_deductions_gajian1'],
            'custom_items_gajian1' => $customSplit['custom_items_gajian1'],
            'custom_earnings_gajian2' => $customSplit['custom_earnings_gajian2'],
            'custom_deductions_gajian2' => $customSplit['custom_deductions_gajian2'],
            'custom_items_gajian2' => $customSplit['custom_items_gajian2'],
            'service_charge_by_point' => $payrollDetail->service_charge_by_point ?? 0,
            'service_charge_pro_rate' => $payrollDetail->service_charge_pro_rate ?? 0,
            'uang_makan' => $payrollDetail->uang_makan ?? 0,
            'nominal_uang_makan' => $payrollDetail->nominal_uang_makan ?? 0,
            'total_lembur' => $payrollDetail->total_lembur ?? 0,
            'nominal_lembur_per_jam' => $payrollDetail->nominal_lembur_per_jam ?? 0,
            'gaji_lembur' => $payrollDetail->gaji_lembur ?? 0,
            'lb_total' => $payrollDetail->lb_total ?? 0,
            'deviasi_total' => $payrollDetail->deviasi_total ?? 0,
            'city_ledger_total' => $payrollDetail->city_ledger_total ?? 0,
            'ph_bonus' => $payrollDetail->ph_bonus ?? 0,
            'leave_data' => $leaveData,
            'master_data' => $masterData,
            'logo_base64' => $logoBase64,
            'total_gajian1' => $gajiSplit['total_gaji_akhir_bulan'],
            'total_gajian2' => $gajiSplit['total_gaji_tanggal_8'],
            'total_gaji_combined' => $gajiSplit['total_gaji'],
        ];
    }

    /**
     * @return array{total_gaji_akhir_bulan: float, total_gaji_tanggal_8: float, total_gaji: float}
     */
    private function calculateDetailGajiSplit(object $payrollDetail): array
    {
        $customItems = $payrollDetail->custom_items ? json_decode($payrollDetail->custom_items, true) : [];
        $gajian1Custom = $this->parseSlipCustomItems($customItems, 'gajian1');
        $gajian2Custom = $this->parseSlipCustomItems($customItems, 'gajian2');

        return PayrollGajiSplitCalculator::calculate([
            'gaji_pokok' => $payrollDetail->gaji_pokok ?? 0,
            'tunjangan' => $payrollDetail->tunjangan ?? 0,
            'custom_earnings_gajian1' => $payrollDetail->custom_earnings ?? $gajian1Custom['custom_earnings'],
            'custom_deductions_gajian1' => $payrollDetail->custom_deductions ?? $gajian1Custom['custom_deductions'],
            'bpjs_jkn' => $payrollDetail->bpjs_jkn ?? 0,
            'bpjs_tk' => $payrollDetail->bpjs_tk ?? 0,
            'potongan_telat' => $payrollDetail->potongan_telat ?? 0,
            'potongan_alpha' => $payrollDetail->potongan_alpha ?? 0,
            'potongan_unpaid_leave' => $payrollDetail->potongan_unpaid_leave ?? 0,
            'potongan_kasbon' => $payrollDetail->potongan_kasbon ?? 0,
            'service_charge' => $payrollDetail->service_charge ?? 0,
            'uang_makan' => $payrollDetail->uang_makan ?? 0,
            'gaji_lembur' => $payrollDetail->gaji_lembur ?? 0,
            'ph_bonus' => $payrollDetail->ph_bonus ?? 0,
            'custom_earnings_gajian2' => $gajian2Custom['custom_earnings'],
            'custom_deductions_gajian2' => $gajian2Custom['custom_deductions'],
            'lb_total' => $payrollDetail->lb_total ?? 0,
            'deviasi_total' => $payrollDetail->deviasi_total ?? 0,
            'city_ledger_total' => $payrollDetail->city_ledger_total ?? 0,
        ]);
    }

    /**
     * Total slip per fase — selaras dengan PayrollGajiSplitCalculator & tampilan app.
     *
     * @param  array<string, float|int>  $components
     */
    private function resolveSlipPhaseTotal(string $type, array $components): float
    {
        $split = PayrollGajiSplitCalculator::calculate($components);

        return $type === 'gajian2'
            ? $split['total_gaji_tanggal_8']
            : $split['total_gaji_akhir_bulan'];
    }

    /**
     * Pisahkan custom items tersimpan (JSON payroll_generated_details) per fase gajian.
     *
     * @return array{
     *     custom_earnings_gajian1: float,
     *     custom_deductions_gajian1: float,
     *     custom_items_gajian1: \Illuminate\Support\Collection,
     *     custom_earnings_gajian2: float,
     *     custom_deductions_gajian2: float,
     *     custom_items_gajian2: \Illuminate\Support\Collection
     * }
     */
    private function splitStoredPayrollCustomItems(object $payrollDetail, $customItems): array
    {
        $decodedArray = $payrollDetail->custom_items
            ? (json_decode($payrollDetail->custom_items, true) ?? [])
            : [];

        $gajian1Custom = $this->parseSlipCustomItems($decodedArray, 'gajian1');
        $gajian2Custom = $this->parseSlipCustomItems($decodedArray, 'gajian2');

        $customItemsGajian1 = $customItems->filter(function ($item) {
            $gajianType = is_object($item) ? ($item->gajian_type ?? null) : ($item['gajian_type'] ?? null);

            return ! isset($gajianType) || $gajianType === null || $gajianType === 'gajian1';
        });

        $customItemsGajian2 = $customItems->filter(function ($item) {
            $gajianType = is_object($item) ? ($item->gajian_type ?? null) : ($item['gajian_type'] ?? null);

            return $gajianType === 'gajian2';
        });

        return [
            'custom_earnings_gajian1' => (float) ($payrollDetail->custom_earnings ?? $gajian1Custom['custom_earnings']),
            'custom_deductions_gajian1' => (float) ($payrollDetail->custom_deductions ?? $gajian1Custom['custom_deductions']),
            'custom_items_gajian1' => $customItemsGajian1,
            'custom_earnings_gajian2' => (float) $gajian2Custom['custom_earnings'],
            'custom_deductions_gajian2' => (float) $gajian2Custom['custom_deductions'],
            'custom_items_gajian2' => $customItemsGajian2,
        ];
    }

    /**
     * @return array{
     *     custom_earnings: float,
     *     custom_deductions: float,
     *     custom_earning_items: array<int, array<string, mixed>>,
     *     custom_deduction_items: array<int, array<string, mixed>>
     * }
     */
    private function parseSlipCustomItems(array $customItems, string $gajianType): array
    {
        $customEarnings = 0.0;
        $customDeductions = 0.0;
        $customEarningItems = [];
        $customDeductionItems = [];

        foreach ($customItems as $item) {
            $itemGajianType = is_array($item) ? ($item['gajian_type'] ?? null) : ($item->gajian_type ?? null);
            $isGajian1Item = ! isset($itemGajianType) || $itemGajianType === null || $itemGajianType === 'gajian1';
            $isGajian2Item = $itemGajianType === 'gajian2';

            if ($gajianType === 'gajian1' && ! $isGajian1Item) {
                continue;
            }
            if ($gajianType === 'gajian2' && ! $isGajian2Item) {
                continue;
            }

            $itemType = is_array($item) ? ($item['item_type'] ?? $item['type'] ?? null) : ($item->item_type ?? $item->type ?? null);
            $itemAmount = (float) (is_array($item) ? ($item['item_amount'] ?? $item['amount'] ?? 0) : ($item->item_amount ?? $item->amount ?? 0));
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
            } elseif ($itemType === 'earn') {
                $customEarnings += $itemAmount;
                $customEarningItems[] = $itemData;
            }
        }

        return [
            'custom_earnings' => round($customEarnings),
            'custom_deductions' => round($customDeductions),
            'custom_earning_items' => $customEarningItems,
            'custom_deduction_items' => $customDeductionItems,
        ];
    }

    /**
     * Calculate PH Bonus (Public Holiday bonus only, not extra_off)
     */
    private function calculatePHBonus($userId, $startDate, $endDate)
    {
        // Debug: Log masuk ke method
        \Log::info('calculatePHBonus - Method called', [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_type' => gettype($startDate),
            'end_type' => gettype($endDate)
        ]);
        
        // Get holiday attendance compensations for this user in the period
        // Only count bonus type, not extra_off
        // PERBAIKAN: Convert Carbon to string format 'Y-m-d' untuk whereBetween (sama seperti calculatePHData di AttendanceReportController)
        $startDateStr = $startDate instanceof \Carbon\Carbon ? $startDate->format('Y-m-d') : $startDate;
        $endDateStr = $endDate instanceof \Carbon\Carbon ? $endDate->format('Y-m-d') : $endDate;
        
        // Pastikan format tanggal benar (Y-m-d) - handle jika sudah string
        if (is_string($startDateStr) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateStr)) {
            $startDateStr = date('Y-m-d', strtotime($startDateStr));
        }
        if (is_string($endDateStr) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateStr)) {
            $endDateStr = date('Y-m-d', strtotime($endDateStr));
        }
        
        // Debug: Log query parameters
        \Log::info('calculatePHBonus - Query parameters', [
            'user_id' => $userId,
            'start_date_str' => $startDateStr,
            'end_date_str' => $endDateStr,
            'start_date_original' => $startDate,
            'end_date_original' => $endDate
        ]);
        
        $compensations = DB::table('holiday_attendance_compensations')
            ->where('user_id', $userId)
            ->whereBetween('holiday_date', [$startDateStr, $endDateStr])
            ->where('compensation_type', 'bonus') // Only bonus, not extra_off
            ->whereIn('status', ['approved', 'used']) // Only count approved or used compensations
            ->get();
        
        // Sum all bonus amounts
        $phBonus = 0;
        foreach ($compensations as $compensation) {
            $phBonus += $compensation->compensation_amount ?? 0;
        }
        
        // Debug logging untuk memastikan query benar (log semua, termasuk yang 0)
        \Log::info('calculatePHBonus - Query result', [
            'user_id' => $userId,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'ph_bonus' => $phBonus,
            'compensations_count' => $compensations->count(),
            'compensations' => $compensations->map(function($c) {
                return [
                    'id' => $c->id,
                    'holiday_date' => $c->holiday_date,
                    'compensation_amount' => $c->compensation_amount,
                    'status' => $c->status
                ];
            })->toArray()
        ]);
        
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
            ->where('a.scan_date', '>=', $startDate . ' 00:00:00')
            ->where('a.scan_date', '<', date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00')
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
                'inoutmode' => $scan->inoutmode,
                'outlet_id' => $scan->outlet_id ?? null,
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
        return app(\App\Services\AttendanceWorkTimelineService::class)->processDay($data, $allProcessedData);
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

    /**
     * Get service charge from orders for selected outlet and month
     * Returns 80% of total service charge
     */
    public function getServiceCharge(Request $request)
    {
        try {
            $outletId = $request->input('outlet_id');
            $month = $request->input('month');
            $year = $request->input('year');

            if (!$outletId || !$month || !$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet, bulan, dan tahun harus diisi',
                    'service_charge' => 0
                ]);
            }

            // Periode service charge: 1-31 dari bulan yang dipilih
            $serviceChargeStart = Carbon::create($year, $month, 1)->startOfDay();
            $serviceChargeEnd = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
            
            // Get outlet qr_code untuk filter orders
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->where('status', 'A')
                ->first(['qr_code']);
            
            $serviceCharge = 0;
            if ($outlet && $outlet->qr_code) {
                // Sum service charge dari orders
                $serviceChargeResult = DB::table('orders')
                    ->where('kode_outlet', $outlet->qr_code)
                    ->whereBetween('created_at', [$serviceChargeStart, $serviceChargeEnd])
                    ->where('status', '!=', 'cancelled') // Exclude cancelled orders
                    ->sum('service');
                
                // Ambil 80% dari total service charge
                $serviceCharge = (float)($serviceChargeResult ?? 0) * 0.8;
            }

            return response()->json([
                'success' => true,
                'service_charge' => $serviceCharge
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting service charge: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil service charge',
                'service_charge' => 0
            ]);
        }
    }

    /**
     * Get city ledger amount from orders for selected outlet and month
     * Sum grand_total from orders with mode='City Ledger'
     */
    public function getCityLedgerAmount(Request $request)
    {
        try {
            $outletId = $request->input('outlet_id');
            $month = $request->input('month');
            $year = $request->input('year');

            if (!$outletId || !$month || !$year) {
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet, bulan, dan tahun harus diisi',
                    'city_ledger_amount' => 0
                ]);
            }

            // Periode city ledger: 1-31 dari bulan yang dipilih (sama dengan service charge)
            $cityLedgerStart = Carbon::create($year, $month, 1)->startOfDay();
            $cityLedgerEnd = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
            
            // Get outlet qr_code untuk filter orders
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->where('status', 'A')
                ->first(['qr_code']);
            
            $cityLedgerAmount = 0;
            if ($outlet && $outlet->qr_code) {
                // Sum grand_total dari orders dengan mode='City Ledger'
                $cityLedgerResult = DB::table('orders')
                    ->where('kode_outlet', $outlet->qr_code)
                    ->where('mode', 'City Ledger')
                    ->whereBetween('created_at', [$cityLedgerStart, $cityLedgerEnd])
                    ->where('status', '!=', 'cancelled') // Exclude cancelled orders
                    ->sum('grand_total');
                
                $cityLedgerAmount = (float)($cityLedgerResult ?? 0);
            }
            
            // Tambahkan sum MAC dari menu category cost outlet dengan type='wrong_maker'
            // Periode: 1-31 dari bulan yang dipilih (sama dengan service charge)
            $wrongMakerMacTotal = 0;
            if ($outletId) {
                // Ambil semua header wrong_maker yang sudah approved untuk periode tersebut
                $wrongMakerHeaders = DB::table('outlet_internal_use_waste_headers as h')
                    ->where('h.outlet_id', $outletId)
                    ->where('h.type', 'wrong_maker')
                    ->where('h.status', 'APPROVED')
                    ->whereBetween('h.date', [$cityLedgerStart, $cityLedgerEnd])
                    ->select('h.id', 'h.date', 'h.warehouse_outlet_id')
                    ->get();
                
                // Loop setiap header untuk hitung total MAC
                foreach ($wrongMakerHeaders as $header) {
                    // Ambil warehouse_outlet_id dari header
                    $warehouseOutletId = $header->warehouse_outlet_id;
                    
                    // Ambil details untuk header ini
                    $details = DB::table('outlet_internal_use_waste_details as d')
                        ->join('items as item', 'd.item_id', '=', 'item.id')
                        ->leftJoin('outlet_food_inventory_items as fi', 'item.id', '=', 'fi.item_id')
                        ->where('d.header_id', $header->id)
                        ->select(
                            'd.qty',
                            'd.unit_id',
                            'fi.id as inventory_item_id',
                            'item.small_unit_id',
                            'item.medium_unit_id',
                            'item.large_unit_id',
                            'item.small_conversion_qty',
                            'item.medium_conversion_qty'
                        )
                        ->get();
                    
                    foreach ($details as $detail) {
                        // Ambil MAC dari cost history pada tanggal transaksi
                        $mac = 0;
                        if ($detail->inventory_item_id) {
                            $macRow = DB::table('outlet_food_inventory_cost_histories')
                                ->where('inventory_item_id', $detail->inventory_item_id)
                                ->where('id_outlet', $outletId)
                                ->where('warehouse_outlet_id', $warehouseOutletId)
                                ->where('date', '<=', $header->date)
                                ->orderByDesc('date')
                                ->orderByDesc('id')
                                ->select('mac')
                                ->first();
                            
                            if ($macRow) {
                                $mac = (float)($macRow->mac ?? 0);
                            } else {
                                // Fallback ke last_cost_small dari stock
                                $stockRow = DB::table('outlet_food_inventory_stocks')
                                    ->where('inventory_item_id', $detail->inventory_item_id)
                                    ->where('id_outlet', $outletId)
                                    ->where('warehouse_outlet_id', $warehouseOutletId)
                                    ->select('last_cost_small')
                                    ->first();
                                
                                if ($stockRow) {
                                    $mac = (float)($stockRow->last_cost_small ?? 0);
                                }
                            }
                        }
                        
                        // Konversi qty ke small unit
                        $qty = (float)$detail->qty;
                        $smallConv = (float)($detail->small_conversion_qty ?: 1);
                        $mediumConv = (float)($detail->medium_conversion_qty ?: 1);
                        
                        $qtySmall = 0;
                        if ($detail->unit_id == $detail->small_unit_id) {
                            $qtySmall = $qty;
                        } elseif ($detail->unit_id == $detail->medium_unit_id) {
                            $qtySmall = $qty * $smallConv;
                        } elseif ($detail->unit_id == $detail->large_unit_id) {
                            $qtySmall = $qty * $mediumConv * $smallConv;
                        }
                        
                        // Hitung subtotal MAC dan tambahkan ke total
                        $subtotalMac = $qtySmall * $mac;
                        $wrongMakerMacTotal += $subtotalMac;
                    }
                }
            }
            
            // Total city ledger amount = sum grand_total dari orders + sum MAC dari wrong_maker
            $cityLedgerAmount = $cityLedgerAmount + $wrongMakerMacTotal;

            return response()->json([
                'success' => true,
                'city_ledger_amount' => $cityLedgerAmount
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting city ledger amount: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil city ledger amount',
                'city_ledger_amount' => 0
            ]);
        }
    }

    /**
     * Mutasi outlet dalam periode payroll dari employee_movements (unit_property_change = true).
     */
    private function collectMutationsForPayrollOutlet(
        int $outletId,
        string $outletName,
        string $start,
        string $end,
        Carbon $gajian2Start,
        Carbon $gajian2End
    ) {
        $periodFilter = function ($query) use ($start, $end, $gajian2Start, $gajian2End) {
            $query->where(function ($q) use ($start, $end, $gajian2Start, $gajian2End) {
                $q->whereBetween('employment_effective_date', [$start, $end])
                    ->orWhereBetween('employment_effective_date', [
                        $gajian2Start->toDateString(),
                        $gajian2End->toDateString(),
                    ]);
            })->where('employment_effective_date', '>', $start);
        };

        $baseSelect = [
            'id', 'employee_id', 'employee_name', 'employee_unit_property', 'unit_property_change',
            'unit_property_from', 'unit_property_to', 'employment_effective_date', 'status',
        ];

        $fromMutations = DB::table('employee_movements')
            ->where('employment_type', 'mutation')
            ->where('unit_property_change', '>', 0)
            ->whereNotNull('employment_effective_date')
            ->where(function ($q) use ($outletId, $outletName) {
                $this->applyOutletMovementPropertyScope($q, 'unit_property_from', $outletId, $outletName);
                $q->orWhere(function ($q2) use ($outletId, $outletName) {
                    $this->applyOutletMovementPropertyScope($q2, 'employee_unit_property', $outletId, $outletName);
                });
            })
            ->where($periodFilter)
            ->whereIn('status', ['executed', 'approved', 'pending'])
            ->select($baseSelect)
            ->get();

        $toMutations = DB::table('employee_movements')
            ->where('employment_type', 'mutation')
            ->where('unit_property_change', '>', 0)
            ->whereNotNull('employment_effective_date')
            ->where(function ($q) use ($outletId, $outletName) {
                $this->applyOutletMovementPropertyScope($q, 'unit_property_to', $outletId, $outletName);
            })
            ->where($periodFilter)
            ->whereIn('status', ['executed', 'approved', 'pending'])
            ->select($baseSelect)
            ->get();

        return $fromMutations->merge($toMutations)->unique('id')->values();
    }

    private function applyOutletMovementPropertyScope($query, string $column, int $outletId, string $outletName): void
    {
        $query->where(function ($q) use ($column, $outletId, $outletName) {
            $q->where($column, $outletName)
                ->orWhere($column, (string) $outletId);
        });
    }

    private function outletMovementPropertyMatches(int $outletId, string $outletName, ?string $property): bool
    {
        if ($property === null || $property === '') {
            return false;
        }

        return $property === $outletName || $property === (string) $outletId;
    }

    private function resolveOutletIdFromMovementProperty(?string $property): ?int
    {
        if ($property === null || $property === '') {
            return null;
        }

        if (ctype_digit((string) $property)) {
            return (int) $property;
        }

        $id = DB::table('tbl_data_outlet')->where('nama_outlet', $property)->value('id_outlet');

        return $id ? (int) $id : null;
    }

    private function resolveOutletNameFromMovementProperty(?string $property): ?string
    {
        if ($property === null || $property === '') {
            return null;
        }

        if (ctype_digit((string) $property)) {
            $name = DB::table('tbl_data_outlet')
                ->where('id_outlet', (int) $property)
                ->value('nama_outlet');

            return $name ?: null;
        }

        return $property;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $mutations
     * @return array<int, array<string, mixed>>
     */
    private function buildPayrollMutationMap($mutations, int $outletId, string $outletName): array
    {
        $mutationMap = [];

        foreach ($mutations as $m) {
            if ((int) ($m->unit_property_change ?? 0) <= 0) {
                continue;
            }

            $outletToId = $this->resolveOutletIdFromMovementProperty($m->unit_property_to);
            $outletFromId = $this->resolveOutletIdFromMovementProperty($m->unit_property_from);
            $outletFromMatches = $this->outletMovementPropertyMatches($outletId, $outletName, $m->unit_property_from);
            $outletToMatches = $this->outletMovementPropertyMatches($outletId, $outletName, $m->unit_property_to);

            // Outlet tujuan: hari kerja sejak employment_effective_date
            if ($outletToMatches) {
                $mutationMap[$m->employee_id] = [
                    'effective_date' => $m->employment_effective_date,
                    'outlet_from_id' => $outletFromId,
                    'outlet_to_id' => $outletId,
                    'outlet_from_name' => $this->resolveOutletNameFromMovementProperty($m->unit_property_from),
                    'outlet_to_name' => $outletName,
                    'employee_name' => $m->employee_name,
                    'role' => 'to',
                ];
            } elseif ($outletFromMatches) {
                // Outlet asal: hari kerja sampai sebelum employment_effective_date
                $mutationMap[$m->employee_id] = [
                    'effective_date' => $m->employment_effective_date,
                    'outlet_from_id' => $outletId,
                    'outlet_to_id' => $outletToId,
                    'outlet_from_name' => $outletName,
                    'outlet_to_name' => $this->resolveOutletNameFromMovementProperty($m->unit_property_to),
                    'employee_name' => $m->employee_name,
                    'role' => 'from',
                ];
            }
        }

        return $mutationMap;
    }

    /**
     * Karyawan mutasi: tampil di outlet asal (sebelum effective date) dan outlet tujuan (sejak effective date).
     * Karyawan yang belum efektif pindah ke outlet ini di-exclude dari payroll outlet tujuan.
     */
    private function filterPayrollUsersForMutationEffectiveDate(
        $users,
        array $mutationMap,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $gajian2End,
        int $outletId,
        ?string $outletName
    ) {
        $notYetArrivedIds = $this->resolveEmployeeIdsNotYetArrivedAtOutlet($outletId, $outletName, $gajian2End);

        return $users->filter(function ($user) use ($mutationMap, $startDate, $gajian2End, $notYetArrivedIds) {
            $userId = $user->id;

            if (in_array($userId, $notYetArrivedIds, true) && ! isset($mutationMap[$userId])) {
                return false;
            }

            if (! isset($mutationMap[$userId])) {
                return true;
            }

            $mut = $mutationMap[$userId];
            $effective = Carbon::parse($mut['effective_date'])->startOfDay();
            $role = $mut['role'] ?? 'from';

            if ($role === 'to' && $effective->gt($gajian2End->copy()->startOfDay())) {
                return false;
            }

            if ($role === 'from' && $effective->lte($startDate->copy()->startOfDay())) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * @return list<int>
     */
    private function resolveEmployeeIdsNotYetArrivedAtOutlet(int $outletId, ?string $outletName, Carbon $gajian2End): array
    {
        if (! $outletName) {
            return [];
        }

        return DB::table('employee_movements')
            ->where('employment_type', 'mutation')
            ->where('unit_property_change', '>', 0)
            ->whereNotNull('employment_effective_date')
            ->whereIn('status', ['executed', 'approved', 'pending'])
            ->where(function ($q) use ($outletId, $outletName) {
                $this->applyOutletMovementPropertyScope($q, 'unit_property_to', $outletId, $outletName);
            })
            ->where('employment_effective_date', '>', $gajian2End->toDateString())
            ->pluck('employee_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Hari kerja gajian 1 — tampilan, uang makan, dan pool SC memakai sumber yang sama.
     * Karyawan mutasi: formula SC mutasi (OFF transisi + hadir). Lainnya: absensi.
     *
     * @param  array<string, mixed>|null  $mutCtx
     */
    private function resolveHariKerjaForPayrollSegment(
        bool $isMutatedEmployee,
        ?array $mutCtx,
        int $hariKerjaAttendance,
        ?int $hariKerjaMutationSc = null
    ): int {
        if (! $isMutatedEmployee || $mutCtx === null) {
            return $hariKerjaAttendance;
        }

        if (($mutCtx['hariKerjaGajian1'] ?? 0) <= 0) {
            return 0;
        }

        if ($hariKerjaMutationSc !== null && $hariKerjaMutationSc > 0) {
            return $hariKerjaMutationSc;
        }

        return $hariKerjaAttendance;
    }

    /**
     * Hari kerja SC mutasi outlet — selaras Excel: hitung dari effective date, termasuk OFF
     * sebelum karyawan mulai kerja di outlet tujuan (atau OFF setelah hari kerja terakhir di outlet asal).
     */
    private function countMutationSegmentScDays(
        int $userId,
        int $outletId,
        Carbon $segmentStart,
        Carbon $segmentEnd,
        string $mutationRole
    ): int {
        $attendanceRows = $this->getAttendanceData($userId, $outletId, $segmentStart, $segmentEnd);

        $workDates = [];
        foreach ($attendanceRows as $row) {
            if (! empty($row['has_check_in']) && empty($row['is_off'])) {
                $workDates[] = $row['tanggal'];
            }
        }

        if (empty($workDates)) {
            return $attendanceRows->filter(fn ($row) => ! empty($row['is_off']))->count();
        }

        $firstWorkDate = $workDates[0];
        $lastWorkDate = $workDates[count($workDates) - 1];
        $count = 0;

        foreach ($attendanceRows as $row) {
            $tanggal = $row['tanggal'];
            $isOff = ! empty($row['is_off']);
            $hasWork = ! empty($row['has_check_in']) && ! $isOff;

            if ($hasWork) {
                $count++;

                continue;
            }

            if (! $isOff) {
                continue;
            }

            if ($mutationRole === 'to' && $tanggal < $firstWorkDate) {
                $count++;
            } elseif ($mutationRole === 'from' && $tanggal > $lastWorkDate) {
                $count++;
            }
        }

        return $count;
    }

    private function mergeMutatedUsersIntoPayrollUsers($users, $mutations)
    {
        $mutatedEmployeeIds = $mutations->pluck('employee_id')->unique()->toArray();
        if (empty($mutatedEmployeeIds)) {
            return $users;
        }

        $mutatedUsers = User::whereIn('id', $mutatedEmployeeIds)
            ->get(['id', 'nama_lengkap', 'nik', 'id_jabatan', 'division_id', 'id_outlet', 'no_rekening', 'tanggal_masuk', 'status']);

        $existingUserIds = $users->pluck('id')->toArray();
        $newMutatedIds = array_diff($mutatedEmployeeIds, $existingUserIds);
        if (! empty($newMutatedIds)) {
            $users = $users->merge($mutatedUsers->whereIn('id', $newMutatedIds));
        }

        return $users;
    }

    /**
     * Resign approved dalam periode gajian 1 (26–25) ATAU gajian 2 (1–akhir bulan).
     */
    private function queryApprovedResignationsForPayroll(string $start, string $end, int $year, int $month)
    {
        $gajian2Start = Carbon::create($year, $month, 1)->startOfDay();
        $gajian2End = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        return EmployeeResignation::where('status', 'approved')
            ->where(function ($query) use ($start, $end, $gajian2Start, $gajian2End) {
                $query->whereBetween('resignation_date', [$start, $end])
                    ->orWhereBetween('resignation_date', [$gajian2Start, $gajian2End]);
            })
            ->get(['employee_id', 'resignation_date', 'outlet_id']);
    }

    /**
     * @return array{gajian1: float, gajian2: float}
     */
    private function buildMutationPayrollRatios(
        int $hariKerjaGajian1,
        int $hariKerjaGajian2,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $gajian2Start,
        Carbon $gajian2End
    ): array {
        $totalGajian1 = max(1, $startDate->diffInDays($endDate) + 1);
        $totalGajian2 = max(1, $gajian2Start->diffInDays($gajian2End) + 1);

        return [
            'gajian1' => $hariKerjaGajian1 / $totalGajian1,
            'gajian2' => $hariKerjaGajian2 / $totalGajian2,
        ];
    }

    private function filterAttendanceRowsForDateRange($rows, Carbon $start, Carbon $end)
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        return $rows->filter(function ($row) use ($start, $end) {
            $d = Carbon::parse($row->tanggal)->startOfDay();

            return $d->gte($start) && $d->lte($end);
        })->values();
    }

    private function countHariKerjaGajian2Attendance(
        $rows,
        Carbon $gajian2Start,
        Carbon $gajian2End,
        ?Carbon $resignationDate = null,
        ?Carbon $tanggalMasukAfterGajian2Start = null,
        ?Carbon $mutationEffectiveDate = null,
        ?string $mutationRole = null
    ): int {
        $filtered = $this->filterAttendanceRowsForDateRange($rows, $gajian2Start, $gajian2End);

        if ($mutationEffectiveDate && $mutationRole) {
            $filtered = $this->filterAttendanceRowsForMutationSegment(
                $filtered,
                $mutationEffectiveDate,
                $mutationRole
            );
        }

        if ($resignationDate) {
            $resignDay = $resignationDate->copy()->startOfDay();
            $filtered = $filtered->filter(function ($row) use ($resignDay) {
                return Carbon::parse($row->tanggal)->startOfDay()->lte($resignDay);
            });
        }

        if ($tanggalMasukAfterGajian2Start) {
            $masuk = $tanggalMasukAfterGajian2Start->copy()->startOfDay();
            $filtered = $filtered->filter(function ($row) use ($masuk) {
                return Carbon::parse($row->tanggal)->startOfDay()->gte($masuk);
            });
        }

        return $this->attendanceReportHelper()->countHariKerjaFromRows($filtered->values());
    }

    private function resolveTanggalMasukForGajian2Pool(?Carbon $tanggalMasuk, Carbon $gajian2Start): ?Carbon
    {
        if (! $tanggalMasuk) {
            return null;
        }

        $masuk = $tanggalMasuk->copy()->startOfDay();

        return $masuk->gt($gajian2Start) ? $masuk : null;
    }

    private function filterAttendanceRowsForMutationSegment($rows, Carbon $effectiveDate, string $role)
    {
        return $rows->filter(function ($row) use ($effectiveDate, $role) {
            $rowDate = Carbon::parse($row->tanggal)->startOfDay();

            return $role === 'from'
                ? $rowDate->lt($effectiveDate)
                : $rowDate->gte($effectiveDate);
        })->values();
    }

    /**
     * @return array{bpjs_jkn: float, bpjs_tk: float, perusahaan_detail: array|null}
     */
    private function prorateBpjsForMutationSegment(
        float $bpjsJkn,
        float $bpjsTk,
        ?array $perusahaanDetail,
        float $ratio
    ): array {
        $ratio = max(0.0, min(1.0, $ratio));
        $detail = $perusahaanDetail;
        if (is_array($detail)) {
            if (isset($detail['lines']) && is_array($detail['lines'])) {
                foreach ($detail['lines'] as $i => $line) {
                    if (isset($line['amount'])) {
                        $detail['lines'][$i]['amount'] = round((float) $line['amount'] * $ratio, 2);
                    }
                }
            }
            if (isset($detail['total_perusahaan'])) {
                $detail['total_perusahaan'] = round((float) $detail['total_perusahaan'] * $ratio, 2);
            }
        }

        return [
            'bpjs_jkn' => round($bpjsJkn * $ratio, 2),
            'bpjs_tk' => round($bpjsTk * $ratio, 2),
            'perusahaan_detail' => $detail,
        ];
    }

    /**
     * Hari kerja mutasi outlet per payroll outlet: gajian 1 (26–25) dan gajian 2 (1–akhir bulan) dari effective date.
     *
     * @return array{
     *     mutationRole: string,
     *     mutationEffectiveDate: Carbon,
     *     hariKerjaGajian1: int,
     *     hariKerjaGajian2: int,
     *     hariKerjaOutletLama: int,
     *     hariKerjaOutletBaru: int
     * }
     */
    private function resolveMutationPayrollContext(
        array $mutationData,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $gajian2Start,
        Carbon $gajian2End
    ): array {
        $effective = Carbon::parse($mutationData['effective_date'])->startOfDay();
        $role = $mutationData['role'] ?? 'from';

        $hariGajian1 = PayrollSplitPoolCalculator::calculateMutationDaysInPeriod(
            $effective,
            $startDate,
            $endDate,
            $role
        );
        $hariGajian2 = PayrollSplitPoolCalculator::calculateMutationDaysInPeriod(
            $effective,
            $gajian2Start,
            $gajian2End,
            $role
        );

        return [
            'mutationRole' => $role,
            'mutationEffectiveDate' => $effective,
            'hariKerjaGajian1' => $hariGajian1,
            'hariKerjaGajian2' => $hariGajian2,
            'hariKerjaOutletLama' => $role === 'from' ? $hariGajian2 : 0,
            'hariKerjaOutletBaru' => $role === 'to' ? $hariGajian2 : 0,
        ];
    }

    /**
     * @return array{
     *     isResignedEmployee: bool,
     *     affectsGajian2: bool,
     *     resignationDate: ?Carbon,
     *     hariKerjaKaryawanResign: int
     * }
     */
    private function resolveResignationPayrollContext(
        $resignation,
        int $hariKerjaDefault,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $gajian2Start,
        Carbon $gajian2End
    ): array {
        $isResignedEmployee = false;
        $affectsGajian2 = false;
        $resignationDate = null;
        $hariKerjaKaryawanResign = $hariKerjaDefault;

        if ($resignation && $resignation->resignation_date) {
            $resignationDate = Carbon::parse($resignation->resignation_date)->startOfDay();
            $isResignedEmployee = $resignationDate->greaterThanOrEqualTo($startDate)
                && $resignationDate->lessThanOrEqualTo($endDate);
            $affectsGajian2 = $resignationDate->greaterThanOrEqualTo($gajian2Start)
                && $resignationDate->lessThanOrEqualTo($gajian2End);

            if ($isResignedEmployee) {
                $hariKerjaKaryawanResign = $startDate->diffInDays($resignationDate) + 1;
            }
        }

        return [
            'isResignedEmployee' => $isResignedEmployee,
            'affectsGajian2' => $affectsGajian2,
            'resignationDate' => $resignationDate,
            'hariKerjaKaryawanResign' => $hariKerjaKaryawanResign,
        ];
    }

    /**
     * Selaraskan hari prorate gajian 1 (gaji pokok/tunjangan) dengan absensi gajian 1.
     */
    private function syncGajian1ProrateDaysWithAttendance(
        int $hariKerja,
        int &$hariKerjaKaryawanBaru,
        int &$hariKerjaKaryawanResign,
        bool $isNewEmployee,
        bool $isResignedEmployee
    ): void {
        if ($hariKerja <= 0) {
            $hariKerjaKaryawanBaru = 0;
            $hariKerjaKaryawanResign = 0;

            return;
        }

        if ($isResignedEmployee && $hariKerjaKaryawanResign > $hariKerja) {
            $hariKerjaKaryawanResign = $hariKerja;
        }

        if ($isNewEmployee && $hariKerjaKaryawanBaru > $hariKerja) {
            $hariKerjaKaryawanBaru = min($hariKerjaKaryawanBaru, $hariKerja);
        }
    }

    /**
     * BPJS untuk satu user (slip/preview): dasar dari Data Level, % dari Kategori BPJS bila ada.
     *
     * @return array{bpjs_jkn: float, bpjs_tk: float, perusahaan_detail: array|null}
     */
    private function calculateBpjsPayrollForSingleUser(object $user, object $masterData): array
    {
        $userLevel = DB::table('tbl_data_jabatan')
            ->where('id_jabatan', $user->id_jabatan)
            ->value('id_level');

        $dasarBpjs = ['kesehatan' => 0.0, 'ketenagakerjaan' => 0.0];
        $kategori = null;
        if ($userLevel) {
            $levelRow = DB::table('tbl_data_level')
                ->where('id', $userLevel)
                ->first([
                    'nilai_dasar_potongan_bpjs',
                    'nilai_dasar_potongan_bpjs_kesehatan',
                    'nilai_dasar_potongan_bpjs_ketenagakerjaan',
                    'id_bpjs_kategori',
                ]);
            if ($levelRow) {
                $dasarBpjs = PayrollBpjsCalculator::resolveDasarFromLevel($levelRow);
                if (! empty($levelRow->id_bpjs_kategori)) {
                    $kategori = DB::table('tbl_bpjs_kategori')
                        ->where('id', $levelRow->id_bpjs_kategori)
                        ->where('status', 'A')
                        ->first();
                }
            }
        }

        return PayrollBpjsCalculator::calculate(
            $masterData,
            $dasarBpjs['kesehatan'],
            $dasarBpjs['ketenagakerjaan'],
            $kategori,
            (int) ($user->id_outlet ?? 0)
        );
    }
}
