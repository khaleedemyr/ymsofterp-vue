<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Exports\UserShiftCalendarExport;

class UserShiftController extends Controller
{
    // Halaman input shift mingguan
    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $startDate = $request->input('start_date'); // Senin minggu tsb

        // Dropdown
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get()
            ->map(fn($o) => ['id' => $o->id_outlet, 'name' => $o->nama_outlet]);
        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->select('id', 'nama_divisi')
            ->orderBy('nama_divisi')
            ->get()
            ->map(fn($d) => ['id' => $d->id, 'name' => $d->nama_divisi]);

        $users = collect();
        $shifts = collect();
        $userShifts = collect();
        $dates = [];
        if ($outletId && $divisionId && $startDate) {
            // Ambil karyawan sesuai outlet & divisi dengan jabatan
            $users = DB::table('users as u')
                ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
                ->where('u.id_outlet', $outletId)
                ->where('u.division_id', $divisionId)
                ->where('u.status', 'A')
                ->select('u.*', 'j.nama_jabatan as jabatan')
                ->orderBy('u.nama_lengkap')
                ->get()
                ->map(function($user) {
                    return (object) [
                        'id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'jabatan' => $user->jabatan ?? '-',
                        'id_outlet' => $user->id_outlet,
                        'division_id' => $user->division_id,
                        'status' => $user->status,
                    ];
                });
            // Ambil shift sesuai divisi
            $shifts = Shift::where('division_id', $divisionId)->orderBy('shift_name')->get();
            // Generate tanggal mulai (apapun hari yang dipilih user)
            $start = date('Y-m-d', strtotime($startDate));
            for ($i = 0; $i < 7; $i++) {
                $dates[] = date('Y-m-d', strtotime("$start +$i day"));
            }
            // Ambil data shift yang sudah ada
            $userIds = $users->pluck('id');
            $userShifts = DB::table('user_shifts')
                ->whereIn('user_id', $userIds)
                ->whereIn('tanggal', $dates)
                ->get();
        }
        $holidays = collect();
        $approvedAbsents = collect();
        if (!empty($dates)) {
            $holidays = DB::table('tbl_kalender_perusahaan')
                ->whereIn('tgl_libur', $dates)
                ->select('tgl_libur as date', 'keterangan as name')
                ->get();
            
            // Get approved absents for the selected users and date range
            if ($users->isNotEmpty()) {
                $userIds = $users->pluck('id');
                $startDate = min($dates);
                $endDate = max($dates);
                
                $approvedAbsents = DB::table('absent_requests')
                    ->leftJoin('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
                    ->whereIn('absent_requests.user_id', $userIds)
                    ->where('absent_requests.status', 'approved')
                    ->where(function($query) use ($startDate, $endDate) {
                        $query->whereBetween('absent_requests.date_from', [$startDate, $endDate])
                              ->orWhereBetween('absent_requests.date_to', [$startDate, $endDate])
                              ->orWhere(function($q) use ($startDate, $endDate) {
                                  $q->where('absent_requests.date_from', '<=', $startDate)
                                    ->where('absent_requests.date_to', '>=', $endDate);
                              });
                    })
                    ->select('absent_requests.user_id', 'absent_requests.date_from', 'absent_requests.date_to', 'leave_types.name as leave_type_name', 'absent_requests.reason')
                    ->get();
            }
        }
        \Log::info('USERSHIFT_INDEX_DATES', $dates);
        \Log::info('USERSHIFT_INDEX_HOLIDAYS', $holidays->toArray());
        \Log::info('USERSHIFT_INDEX_APPROVED_ABSENTS', $approvedAbsents->toArray());
        
        // For API requests (not Inertia), return JSON response
        // Inertia requests have X-Inertia header, so we skip JSON for those
        if (($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'outlets' => $outlets,
                'divisions' => $divisions,
                'users' => $users,
                'shifts' => $shifts,
                'dates' => $dates,
                'userShifts' => $userShifts,
                'holidays' => $holidays,
                'approvedAbsents' => $approvedAbsents,
                'filter' => [
                    'outlet_id' => $outletId,
                    'division_id' => $divisionId,
                    'start_date' => $startDate,
                ],
            ]);
        }
        
        // For web (Inertia) requests, return Inertia render as before
        return Inertia::render('UserShift/Index', [
            'outlets' => $outlets,
            'divisions' => $divisions,
            'users' => $users,
            'shifts' => $shifts,
            'dates' => $dates,
            'userShifts' => $userShifts,
            'holidays' => $holidays,
            'approvedAbsents' => $approvedAbsents,
            'filter' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'start_date' => $startDate,
            ],
        ]);
    }

    // Simpan data shift mingguan
    public function store(Request $request)
    {
        // Log payload mentah dari frontend (request body all)
        \Log::info('USER_SHIFT_RAW_REQUEST', $request->all());
        $data = $request->validate([
            'outlet_id' => 'required|integer',
            'division_id' => 'required|integer',
            'start_date' => 'required|date',
            'shifts' => 'required|array', // [user_id][tanggal] = shift_id/null
            'explicit_off' => 'sometimes|array', // optional: [user_id][tanggal] = true to force OFF
        ]);
        \Log::info('USER_SHIFT_VALIDATED', $data);
        $outletId = $data['outlet_id'];
        $divisionId = $data['division_id'];
        $startDate = $data['start_date'];
        $shiftsInput = $data['shifts'];
        $explicitOff = $data['explicit_off'] ?? [];
        $start = date('Y-m-d', strtotime($startDate));
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = date('Y-m-d', strtotime("$start +$i day"));
        }
        // Use transaction to prevent race condition when multiple users input simultaneously
        // Lock rows to prevent concurrent updates for the same outlet + division + date range
        DB::beginTransaction();
        try {
            // Lock rows for this outlet + division + date range to prevent concurrent updates
            DB::table('user_shifts')
                ->where('outlet_id', $outletId)
                ->where('division_id', $divisionId)
                ->whereIn('tanggal', $dates)
                ->lockForUpdate()
                ->get();
            
            // Simpan per user per hari
            foreach ($shiftsInput as $userId => $shiftPerDay) {
                foreach ($dates as $tanggal) {
                    $shiftId = $shiftPerDay[$tanggal] ?? null;
                    // Cek sudah ada, update/insert
                    $existing = DB::table('user_shifts')
                        ->where('user_id', $userId)
                        ->where('tanggal', $tanggal)
                        ->first();
                    if ($existing) {
                        // If incoming value is OFF (null) but existing already has a shift, skip turning it OFF
                        // unless explicitly forced by client for this cell
                        $forcedOff = isset($explicitOff[$userId]) && array_key_exists($tanggal, (array) $explicitOff[$userId]);
                        // Patch: parse value 'true' string as boolean
                        $explicitVal = $forcedOff ? $explicitOff[$userId][$tanggal] : null;
                        if ($explicitVal === 'true' || $explicitVal === 1 || $explicitVal === '1') $explicitVal = true;
                        $forcedOff = $forcedOff && ($explicitVal === true);
                        if (is_null($shiftId) && !is_null($existing->shift_id)) {
                            \Log::info('USER_SHIFT_DEBUG', ['user_id' => $userId, 'tanggal' => $tanggal, 'forcedOff' => $forcedOff, 'explicitVal' => $explicitVal, 'explicit_off' => $explicitOff[$userId][$tanggal] ?? null]);
                            if (!$forcedOff) {
                                continue;
                            }
                        }
                        DB::table('user_shifts')->where('id', $existing->id)->update([
                            'shift_id' => $shiftId,
                            'outlet_id' => $outletId,
                            'division_id' => $divisionId,
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('user_shifts')->insert([
                            'user_id' => $userId,
                            'shift_id' => $shiftId,
                            'outlet_id' => $outletId,
                            'division_id' => $divisionId,
                            'tanggal' => $tanggal,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            // For API requests (not Inertia), return JSON response
            // Inertia requests have X-Inertia header, so we skip JSON for those
            if (($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Jadwal shift berhasil disimpan!',
                ]);
            }
            
            // For web (Inertia) requests, return redirect as before
            return redirect()->back()->with('success', 'Jadwal shift berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('USER_SHIFT_STORE_ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // For API requests (not Inertia), return JSON error response
            // Inertia requests have X-Inertia header, so we skip JSON for those
            if (($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menyimpan jadwal shift: ' . $e->getMessage(),
                ], 500);
            }
            
            // For web (Inertia) requests, return redirect with errors as before
            return redirect()->back()->withErrors(['error' => 'Gagal menyimpan jadwal shift: ' . $e->getMessage()]);
        }
    }

    // Kalender jadwal shift
    public function calendarView(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $userId = $request->input('user_id');
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        // Dropdown
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get()
            ->map(fn($o) => ['id' => $o->id_outlet, 'name' => $o->nama_outlet]);
        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->select('id', 'nama_divisi')
            ->orderBy('nama_divisi')
            ->get()
            ->map(fn($d) => ['id' => $d->id, 'name' => $d->nama_divisi]);
        $users = collect();
        if ($outletId && $divisionId) {
            $users = User::where('id_outlet', $outletId)
                ->where('division_id', $divisionId)
                ->orderBy('nama_lengkap')
                ->get();
        }
        // Ambil data shift bulan ini
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        $shiftQuery = DB::table('user_shifts')
            ->leftJoin('users', 'user_shifts.user_id', '=', 'users.id')
            ->leftJoin('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
            ->select(
                'user_shifts.*',
                'users.nama_lengkap',
                'shifts.shift_name',
                'shifts.id as shift_id',
                'shifts.time_start',
                'shifts.time_end'
            )
            ->whereBetween('user_shifts.tanggal', [$startDate, $endDate]);
        if ($outletId) $shiftQuery->where('user_shifts.outlet_id', $outletId);
        if ($divisionId) $shiftQuery->where('user_shifts.division_id', $divisionId);
        if ($userId) $shiftQuery->where('user_shifts.user_id', $userId);
        $shiftData = $shiftQuery->get();
        // Data per hari
        $calendar = [];
        foreach ($shiftData as $row) {
            $calendar[$row->tanggal][$row->user_id] = [
                'user_id' => $row->user_id,
                'nama_lengkap' => $row->nama_lengkap,
                'shift_id' => $row->shift_id,
                'shift_name' => $row->shift_name,
                'time_start' => $row->time_start,
                'time_end' => $row->time_end,
            ];
        }
        // Ambil data libur nasional untuk bulan & tahun yang dipilih
        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$startDate, $endDate])
            ->select('tgl_libur as date', 'keterangan as name')
            ->get();
            
        // Ambil data absent untuk highlight
        $absentQuery = DB::table('absent_requests')
            ->leftJoin('users', 'absent_requests.user_id', '=', 'users.id')
            ->leftJoin('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->select([
                'absent_requests.user_id',
                'absent_requests.date_from',
                'absent_requests.date_to',
                'absent_requests.status',
                'absent_requests.reason',
                'users.nama_lengkap',
                'leave_types.name as leave_type_name'
            ])
            ->where('absent_requests.status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('absent_requests.date_from', [$startDate, $endDate])
                      ->orWhereBetween('absent_requests.date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('absent_requests.date_from', '<=', $startDate)
                            ->where('absent_requests.date_to', '>=', $endDate);
                      });
            });
            
        if ($outletId) {
            $absentQuery->where('users.id_outlet', $outletId);
        }
        if ($divisionId) {
            $absentQuery->where('users.division_id', $divisionId);
        }
        if ($userId) {
            $absentQuery->where('absent_requests.user_id', $userId);
        }
        
        $absentData = $absentQuery->get();
        
        // Format absent data untuk kalender
        $absentCalendar = [];
        foreach ($absentData as $absent) {
            $startDateAbsent = new \DateTime($absent->date_from);
            $endDateAbsent = new \DateTime($absent->date_to);
            
            // Generate all dates between start and end
            $currentDate = clone $startDateAbsent;
            while ($currentDate <= $endDateAbsent) {
                $dateStr = $currentDate->format('Y-m-d');
                if (!isset($absentCalendar[$dateStr])) {
                    $absentCalendar[$dateStr] = [];
                }
                $absentCalendar[$dateStr][] = [
                    'user_id' => $absent->user_id,
                    'nama_lengkap' => $absent->nama_lengkap,
                    'leave_type_name' => $absent->leave_type_name,
                    'reason' => $absent->reason,
                    'date_from' => $absent->date_from,
                    'date_to' => $absent->date_to
                ];
                $currentDate->add(new \DateInterval('P1D'));
            }
        }
        
        return Inertia::render('UserShift/Calendar', [
            'outlets' => $outlets,
            'divisions' => $divisions,
            'users' => $users,
            'calendar' => $calendar,
            'holidays' => $holidays,
            'absentCalendar' => $absentCalendar,
            'filter' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'user_id' => $userId,
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    public function exportCalendarExcel(Request $request)
    {
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        $outletName = $outletId ? DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet') : 'Semua Outlet';
        $divisionName = $divisionId ? DB::table('tbl_data_divisi')->where('id', $divisionId)->value('nama_divisi') : 'Semua Divisi';
        // Ambil user
        $users = DB::table('users')
            ->where('status', 'A')
            ->when($outletId, fn($q) => $q->where('id_outlet', $outletId))
            ->when($divisionId, fn($q) => $q->where('division_id', $divisionId))
            ->orderBy('nama_lengkap')
            ->get();
        // Ambil tanggal bulan tsb
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        $dates = [];
        $cur = strtotime($startDate);
        $end = strtotime($endDate);
        while ($cur <= $end) {
            $dates[] = date('Y-m-d', $cur);
            $cur = strtotime('+1 day', $cur);
        }
        // Ambil shift
        $shiftData = DB::table('user_shifts')
            ->leftJoin('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
            ->select('user_shifts.user_id', 'user_shifts.tanggal', 'shifts.shift_name')
            ->whereBetween('user_shifts.tanggal', [$startDate, $endDate])
            ->when($outletId, fn($q) => $q->where('user_shifts.outlet_id', $outletId))
            ->when($divisionId, fn($q) => $q->where('user_shifts.division_id', $divisionId))
            ->get();
        $shiftMap = [];
        foreach ($shiftData as $row) {
            $shiftMap[$row->user_id][$row->tanggal] = $row->shift_name ?: 'OFF';
        }
        // Build data array
        $headings = ['Nama Karyawan'];
        foreach ($dates as $d) {
            $headings[] = date('j', strtotime($d));
        }
        $data = [];
        foreach ($users as $user) {
            $row = [$user->nama_lengkap];
            foreach ($dates as $d) {
                $row[] = $shiftMap[$user->id][$d] ?? 'OFF';
            }
            $data[] = $row;
        }
        $meta = [
            'outlet' => $outletName,
            'divisi' => $divisionName,
            'bulan' => date('F', strtotime($startDate)),
            'tahun' => $year,
        ];
        return new UserShiftCalendarExport($data, $headings, $meta);
    }
} 