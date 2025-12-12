<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Shift;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ScheduleAttendanceCorrectionController extends Controller
{
    /**
     * Calculate payroll period based on date
     */
    private function calculatePayrollPeriod($date)
    {
        $dateObj = new \DateTime($date);
        $day = $dateObj->format('d');
        
        if ($day >= 26) {
            // If day >= 26, period is current month 26 to next month 25
            $periodStart = $dateObj->format('Y-m-26');
            $periodEnd = $dateObj->modify('+1 month')->format('Y-m-25');
        } else {
            // If day < 26, period is previous month 26 to current month 25
            $periodStart = $dateObj->modify('-1 month')->format('Y-m-26');
            $periodEnd = $dateObj->modify('+1 month')->format('Y-m-25');
        }
        
        return [
            'start' => $periodStart,
            'end' => $periodEnd,
            'start_formatted' => date('d M Y', strtotime($periodStart)),
            'end_formatted' => date('d M Y', strtotime($periodEnd))
        ];
    }
    /**
     * Display the correction page
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Get filters from request
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $userId = $request->input('user_id');
        $correctionType = $request->input('correction_type', 'schedule'); // schedule or attendance
        
        // Dropdown data - Filter outlets based on user's outlet
        $outletsQuery = DB::table('tbl_data_outlet')
            ->where('status', 'A');
            
        // If user is not from Head Office (id_outlet != 1), only show their outlet
        if ($user->id_outlet != 1) {
            $outletsQuery->where('id_outlet', $user->id_outlet);
            // Auto-set outlet_id to user's outlet if not provided
            if (!$outletId) {
                $outletId = $user->id_outlet;
            }
        }
        
        $outlets = $outletsQuery
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
        $scheduleData = collect();
        $attendanceData = collect();
        
        if ($outletId && $startDate && $endDate) {
            // Get users based on filters
            $usersQuery = User::where('id_outlet', $outletId)
                ->where('status', 'A');
                
            // Add division filter if provided
            if ($divisionId) {
                $usersQuery->where('division_id', $divisionId);
            }
            
            $users = $usersQuery->orderBy('nama_lengkap')->get();
                
            // Get all shifts with division_id for filtering in frontend
            $shifts = Shift::select('id', 'shift_name', 'time_start', 'time_end', 'division_id')
                ->orderBy('shift_name')
                ->get();
                
            // Get schedule data if correction type is schedule
            if ($correctionType === 'schedule') {
                $allUserIds = $users->pluck('id');
                $scheduleData = DB::table('user_shifts')
                    ->leftJoin('users', 'user_shifts.user_id', '=', 'users.id')
                    ->leftJoin('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
                    ->whereIn('user_shifts.user_id', $allUserIds)
                    ->whereBetween('user_shifts.tanggal', [$startDate, $endDate])
                    ->when($userId, fn($q) => $q->where('user_shifts.user_id', $userId))
                    ->select([
                        'user_shifts.id',
                        'user_shifts.user_id',
                        'user_shifts.tanggal',
                        'user_shifts.shift_id',
                        'user_shifts.outlet_id',
                        'user_shifts.division_id',
                        'users.nama_lengkap',
                        'users.division_id as user_division_id',
                        'user_shifts.division_id as schedule_division_id',
                        'shifts.shift_name',
                        'shifts.time_start',
                        'shifts.time_end'
                    ])
                    ->orderBy('user_shifts.tanggal')
                    ->orderBy('user_shifts.user_id')
                    ->get();
            }
            
            // Get attendance data if correction type is attendance
            if ($correctionType === 'attendance') {
                $allUserIds = $users->pluck('id');
                $attendanceData = DB::table('att_log')
                    ->leftJoin('tbl_data_outlet', 'att_log.sn', '=', 'tbl_data_outlet.sn')
                    ->leftJoin('user_pins', function($join) {
                        $join->on('att_log.pin', '=', 'user_pins.pin')
                             ->on('tbl_data_outlet.id_outlet', '=', 'user_pins.outlet_id');
                    })
                    ->leftJoin('users', 'user_pins.user_id', '=', 'users.id')
                    ->whereIn('users.id', $allUserIds)
                    ->whereBetween(DB::raw('DATE(att_log.scan_date)'), [$startDate, $endDate])
                    ->when($userId, fn($q) => $q->where('users.id', $userId))
                    ->select([
                        'att_log.sn',
                        'att_log.pin',
                        'att_log.scan_date',
                        'att_log.inoutmode',
                        'att_log.verifymode',
                        'att_log.device_ip',
                        'users.id as user_id',
                        'users.nama_lengkap'
                    ])
                    ->orderBy('att_log.scan_date')
                    ->orderBy('users.nama_lengkap')
                    ->get();
            }
        }
        
        return Inertia::render('ScheduleAttendanceCorrection/Index', [
            'outlets' => $outlets,
            'divisions' => $divisions,
            'users' => $users,
            'shifts' => $shifts,
            'scheduleData' => $scheduleData,
            'attendanceData' => $attendanceData,
            'filters' => [
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user_id' => $userId,
                'correction_type' => $correctionType,
            ],
        ]);
    }
    
    /**
     * Update schedule correction (requires approval)
     */
    public function updateSchedule(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer',
            'shift_id' => 'nullable|integer',
            'reason' => 'required|string|max:500',
        ]);
        
        $scheduleId = $request->input('schedule_id');
        $shiftId = $request->input('shift_id');
        $reason = $request->input('reason');
        $userId = auth()->id();
        
        try {
            DB::beginTransaction();
            
            // Get schedule data
            $schedule = DB::table('user_shifts')
                ->leftJoin('users', 'user_shifts.user_id', '=', 'users.id')
                ->where('user_shifts.id', $scheduleId)
                ->select('user_shifts.*', 'users.nama_lengkap', 'users.division_id')
                ->first();
                
            if (!$schedule) {
                throw new \Exception('Schedule tidak ditemukan');
            }
            
            // Get old shift name
            $oldShift = DB::table('shifts')->where('id', $schedule->shift_id)->first();
            $newShift = DB::table('shifts')->where('id', $shiftId)->first();
            
            $oldValue = $oldShift ? $oldShift->shift_name : 'OFF';
            $newValue = $newShift ? $newShift->shift_name : 'OFF';
            
            // Insert approval request
            $approvalId = DB::table('schedule_attendance_correction_approvals')->insertGetId([
                'type' => 'schedule',
                'record_id' => $scheduleId,
                'user_id' => $schedule->user_id,
                'outlet_id' => $schedule->outlet_id,
                'division_id' => $schedule->division_id,
                'tanggal' => $schedule->tanggal,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
                'status' => 'pending',
                'requested_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Get all HRD users (division_id = 6)
            $hrdUsers = DB::table('users')
                ->where('division_id', 6)
                ->where('status', 'A')
                ->pluck('id');
            
            // Send notification to all HRD users
            foreach ($hrdUsers as $hrdUserId) {
                DB::table('notifications')->insert([
                    'user_id' => $hrdUserId,
                    'type' => 'schedule_correction_approval',
                    'message' => "Permohonan koreksi schedule untuk {$schedule->nama_lengkap} pada tanggal " . date('d/m/Y', strtotime($schedule->tanggal)) . " membutuhkan persetujuan Anda. Dari: {$oldValue} → Ke: {$newValue}",
                    'url' => '/schedule-attendance-correction',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan koreksi schedule telah dikirim untuk persetujuan!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim permohonan koreksi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check manual attendance limit for user
     */
    public function checkManualAttendanceLimit(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'scan_date' => 'required|date'
        ]);
        
        $scanDate = $request->scan_date;
        $period = $this->calculatePayrollPeriod($scanDate);
        $periodStart = $period['start'];
        $periodEnd = $period['end'];
        
        // Count existing manual attendance requests for this user in current period
        $existingCount = DB::table('schedule_attendance_correction_approvals')
            ->where('user_id', $request->user_id)
            ->where('type', 'manual_attendance')
            ->where('status', '!=', 'rejected') // Count approved and pending
            ->whereBetween('tanggal', [$periodStart, $periodEnd])
            ->count();
            
        $remaining = max(0, 5 - $existingCount);
        $canSubmit = $remaining > 0;
        
        return response()->json([
            'success' => true,
            'can_submit' => $canSubmit,
            'remaining' => $remaining,
            'used' => $existingCount,
            'period' => $period
        ]);
    }
    
    /**
     * Submit manual attendance request (requires approval)
     */
    public function submitManualAttendance(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'outlet_id' => 'required|integer',
            'scan_date' => 'required|date',
            'inoutmode' => 'required|in:1,2',
            'reason' => 'required|string|max:500'
        ]);
        
        $userId = auth()->id();
        
        try {
            DB::beginTransaction();
            
        // Check manual attendance limit (2x per period per user)
        $scanDate = $request->scan_date;
        $period = $this->calculatePayrollPeriod($scanDate);
        $periodStart = $period['start'];
        $periodEnd = $period['end'];
            
            // Count existing manual attendance requests for this user in current period
            $existingCount = DB::table('schedule_attendance_correction_approvals')
                ->where('user_id', $request->user_id)
                ->where('type', 'manual_attendance')
                ->where('status', '!=', 'rejected') // Count approved and pending
                ->whereBetween('tanggal', [$periodStart, $periodEnd])
                ->count();
                
            if ($existingCount >= 5) {
                throw new \Exception('User ini sudah mencapai batas maksimal 5x input absen manual dalam periode ini (' . $period['start_formatted'] . ' - ' . $period['end_formatted'] . '). Silakan coba lagi di periode berikutnya.');
            }
            
            // Get outlet SN
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', $request->outlet_id)
                ->first();
                
            if (!$outlet) {
                throw new \Exception('Outlet tidak ditemukan');
            }
            
            // Get user PIN for this outlet
            $userPin = DB::table('user_pins')
                ->where('user_id', $request->user_id)
                ->where('outlet_id', $request->outlet_id)
                ->where('is_active', 1)
                ->first();
                
            if (!$userPin) {
                throw new \Exception('PIN user tidak ditemukan untuk outlet ini');
            }
            
            // Get user info
            $user = DB::table('users')
                ->where('id', $request->user_id)
                ->first();
                
            if (!$user) {
                throw new \Exception('User tidak ditemukan');
            }
            
            // Prepare data for approval
            $manualAttendanceData = [
                'sn' => $outlet->sn,
                'pin' => $userPin->pin,
                'scan_date' => $request->scan_date,
                'inoutmode' => $request->inoutmode,
                'verifymode' => 1,
                'device_ip' => '127.0.0.1'
            ];
            
            // Insert approval request
            $approvalId = DB::table('schedule_attendance_correction_approvals')->insertGetId([
                'type' => 'manual_attendance',
                'record_id' => 0, // Manual attendance doesn't have existing record
                'user_id' => $request->user_id,
                'outlet_id' => $request->outlet_id,
                'division_id' => $user->division_id,
                'tanggal' => date('Y-m-d', strtotime($request->scan_date)),
                'old_value' => null, // No old value for manual attendance
                'new_value' => json_encode($manualAttendanceData),
                'reason' => $request->reason,
                'status' => 'pending',
                'requested_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Get all HRD users (division_id = 6)
            $hrdUsers = DB::table('users')
                ->where('division_id', 6)
                ->where('status', 'A')
                ->pluck('id');
            
            // Send notification to all HRD users
            foreach ($hrdUsers as $hrdUserId) {
                DB::table('notifications')->insert([
                    'user_id' => $hrdUserId,
                    'type' => 'manual_attendance_approval',
                    'message' => "Permohonan input absen manual untuk {$user->nama_lengkap} pada tanggal " . date('d/m/Y H:i', strtotime($request->scan_date)) . " membutuhkan persetujuan Anda.",
                    'url' => '/schedule-attendance-correction',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan input absen manual telah dikirim untuk persetujuan!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim permohonan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update attendance correction (requires approval)
     */
    public function updateAttendance(Request $request)
    {
        $request->validate([
            'sn' => 'required|string',
            'pin' => 'required|string',
            'scan_date' => 'required|date',
            'inoutmode' => 'required|in:1,2,3,4,5,"1","2","3","4","5"', // Accept both string and integer
            'old_scan_date' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);
        
        $sn = $request->input('sn');
        $pin = $request->input('pin');
        $newScanDate = $request->input('scan_date');
        $inoutmode = (int) $request->input('inoutmode'); // Convert to integer
        $oldScanDate = $request->input('old_scan_date');
        $reason = $request->input('reason');
        $userId = auth()->id();
        
        try {
            DB::beginTransaction();
            
            // Get old values for logging
            $oldRecord = DB::table('att_log')
                ->leftJoin('tbl_data_outlet', 'att_log.sn', '=', 'tbl_data_outlet.sn')
                ->leftJoin('user_pins', function($join) {
                    $join->on('att_log.pin', '=', 'user_pins.pin')
                         ->on('tbl_data_outlet.id_outlet', '=', 'user_pins.outlet_id');
                })
                ->leftJoin('users', 'user_pins.user_id', '=', 'users.id')
                ->where('att_log.sn', $sn)
                ->where('att_log.pin', $pin)
                ->where('att_log.scan_date', $oldScanDate)
                ->select('att_log.*', 'users.id as user_id', 'users.nama_lengkap', 'users.division_id', 'tbl_data_outlet.id_outlet')
                ->first();
            
            if (!$oldRecord) {
                throw new \Exception('Record attendance tidak ditemukan');
            }
            
            if (!$oldRecord->user_id) {
                throw new \Exception('User ID tidak ditemukan untuk PIN: ' . $pin);
            }
            
            $oldValue = date('d/m/Y H:i:s', strtotime($oldRecord->scan_date));
            $newValue = date('d/m/Y H:i:s', strtotime($newScanDate));
            
            // Insert approval request with additional data for attendance update
            $approvalId = DB::table('schedule_attendance_correction_approvals')->insertGetId([
                'type' => 'attendance',
                'record_id' => 0, // att_log doesn't have ID, use 0
                'user_id' => $oldRecord->user_id,
                'outlet_id' => $oldRecord->id_outlet,
                'division_id' => $oldRecord->division_id,
                'tanggal' => date('Y-m-d', strtotime($oldRecord->scan_date)),
                'old_value' => json_encode([
                    'sn' => $sn,
                    'pin' => $pin,
                    'scan_date' => $oldScanDate,
                    'inoutmode' => $inoutmode,
                    'verifymode' => $oldRecord->verifymode,
                    'device_ip' => $oldRecord->device_ip
                ]),
                'new_value' => json_encode([
                    'sn' => $sn,
                    'pin' => $pin,
                    'scan_date' => $newScanDate,
                    'inoutmode' => $inoutmode,
                    'verifymode' => $oldRecord->verifymode,
                    'device_ip' => $oldRecord->device_ip
                ]),
                'reason' => $reason,
                'status' => 'pending',
                'requested_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Get all HRD users (division_id = 6)
            $hrdUsers = DB::table('users')
                ->where('division_id', 6)
                ->where('status', 'A')
                ->pluck('id');
            
            // Send notification to all HRD users
            foreach ($hrdUsers as $hrdUserId) {
                DB::table('notifications')->insert([
                    'user_id' => $hrdUserId,
                    'type' => 'attendance_correction_approval',
                    'message' => "Permohonan koreksi attendance untuk {$oldRecord->nama_lengkap} pada tanggal " . date('d/m/Y', strtotime($oldRecord->scan_date)) . " membutuhkan persetujuan Anda. Dari: {$oldValue} → Ke: {$newValue}",
                    'url' => '/schedule-attendance-correction',
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan koreksi attendance telah dikirim untuk persetujuan!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim permohonan koreksi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get pending correction approvals for HRD
     */
    public function getPendingApprovals(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: User not authenticated'
            ], 401);
        }
        
        // Superadmin: user dengan id_role = '5af56935b011a' bisa melihat semua approval
        $isSuperadmin = $user->id_role === '5af56935b011a';
        
        \Log::info('Correction Approvals check', [
            'user_id' => $user->id,
            'id_role' => $user->id_role,
            'division_id' => $user->division_id,
            'isSuperadmin' => $isSuperadmin
        ]);
        
        // Only HRD users (division_id = 6) or superadmin can see pending approvals
        if (!$isSuperadmin && $user->division_id != 6) {
            \Log::warning('Correction Approvals: Access denied', [
                'user_id' => $user->id,
                'id_role' => $user->id_role,
                'division_id' => $user->division_id,
                'isSuperadmin' => $isSuperadmin
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        $approvals = DB::table('schedule_attendance_correction_approvals as saca')
            ->leftJoin('users as requester', 'saca.requested_by', '=', 'requester.id')
            ->leftJoin('users as employee', 'saca.user_id', '=', 'employee.id')
            ->leftJoin('tbl_data_outlet', 'saca.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->where('saca.status', 'pending')
            ->select([
                'saca.*',
                'requester.nama_lengkap as requested_by_name',
                'employee.nama_lengkap as employee_name',
                'tbl_data_outlet.nama_outlet'
            ])
            ->orderBy('saca.created_at', 'desc')
            ->get()
            ->map(function($approval) {
                // Get approver name - for corrections, get any HRD user
                $hrdUser = DB::table('users')
                    ->where('division_id', 6)
                    ->where('status', 'A')
                    ->select('nama_lengkap')
                    ->first();
                $approval->approver_name = $hrdUser ? $hrdUser->nama_lengkap : 'HRD';
                return $approval;
            });
            
        return response()->json([
            'success' => true,
            'approvals' => $approvals
        ]);
    }
    
    /**
     * Get correction approval detail
     */
    public function getApprovalDetail($id)
    {
        \Log::info('Correction Approval Detail Request', [
            'id' => $id,
            'user' => auth()->user() ? auth()->user()->id : 'not authenticated',
            'token' => request()->bearerToken() ? 'present' : 'missing',
        ]);
        
        try {
            $approval = DB::table('schedule_attendance_correction_approvals as saca')
                ->leftJoin('users as requester', 'saca.requested_by', '=', 'requester.id')
                ->leftJoin('users as employee', 'saca.user_id', '=', 'employee.id')
                ->leftJoin('users as approver', 'saca.approved_by', '=', 'approver.id')
                ->leftJoin('tbl_data_outlet', 'saca.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->leftJoin('tbl_data_divisi', 'saca.division_id', '=', 'tbl_data_divisi.id')
                ->leftJoin('shifts', function($join) {
                    $join->on('saca.old_value', '=', 'shifts.shift_name')
                         ->on('saca.division_id', '=', 'shifts.division_id');
                })
                ->where('saca.id', $id)
                ->select([
                    'saca.*',
                    'requester.nama_lengkap as requested_by_name',
                    'requester.id as requester_id',
                    'employee.nama_lengkap as employee_name',
                    'employee.id as employee_id',
                    'approver.nama_lengkap as approver_name',
                    'approver.id as approver_id',
                    'tbl_data_outlet.nama_outlet',
                    'tbl_data_outlet.id_outlet',
                    'tbl_data_divisi.nama_divisi',
                    'shifts.shift_name as old_shift_name',
                ])
                ->first();
            
            if (!$approval) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval tidak ditemukan'
                ], 404);
            }
            
            // Get approval flows (if table exists)
            $approvalFlows = collect();
            try {
                $approvalFlows = DB::table('schedule_attendance_correction_approval_flows as sacaf')
                    ->leftJoin('users', 'sacaf.approver_id', '=', 'users.id')
                    ->where('sacaf.approval_id', $id)
                    ->select([
                        'sacaf.*',
                        'users.nama_lengkap as approver_name',
                    ])
                    ->orderBy('sacaf.sequence')
                    ->get();
            } catch (\Exception $e) {
                // Table might not exist, use simple approval info instead
                if ($approval->approved_by) {
                    $approvalFlows = collect([
                        (object)[
                            'id' => null,
                            'sequence' => 1,
                            'status' => $approval->status,
                            'approved_at' => $approval->approved_at,
                            'comments' => $approval->rejection_reason ?? null,
                            'approver_id' => $approval->approved_by,
                            'approver_name' => $approval->approver_name,
                        ]
                    ]);
                }
            }
            
            // Transform approval data
            $correctionData = [
                'id' => $approval->id,
                'type' => $approval->type,
                'status' => $approval->status,
                'tanggal' => $approval->tanggal,
                'old_value' => $approval->old_value,
                'new_value' => $approval->new_value,
                'reason' => $approval->reason,
                'record_id' => $approval->record_id,
                'created_at' => $approval->created_at,
                'updated_at' => $approval->updated_at,
                'employee' => [
                    'id' => $approval->employee_id,
                    'nama_lengkap' => $approval->employee_name,
                ],
                'outlet' => [
                    'id_outlet' => $approval->id_outlet,
                    'nama_outlet' => $approval->nama_outlet,
                ],
                'division' => [
                    'id' => $approval->division_id,
                    'nama_divisi' => $approval->nama_divisi,
                ],
                'requester' => [
                    'id' => $approval->requester_id,
                    'nama_lengkap' => $approval->requested_by_name,
                ],
                'approval_flows' => $approvalFlows->map(function($flow) {
                    return [
                        'id' => $flow->id,
                        'sequence' => $flow->sequence,
                        'status' => $flow->status,
                        'approved_at' => $flow->approved_at,
                        'comments' => $flow->comments,
                        'approver' => [
                            'id' => $flow->approver_id,
                            'nama_lengkap' => $flow->approver_name,
                        ],
                    ];
                })->toArray(),
            ];
            
            return response()->json([
                'success' => true,
                'correction' => $correctionData
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting Correction approval detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Correction approval detail'
            ], 500);
        }
    }
    
    /**
     * Approve correction
     */
    public function approveCorrection(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        // Only HRD users (division_id = 6) or superadmin can approve
        $isSuperadmin = $user->id_role === '5af56935b011a';
        if (!$isSuperadmin && $user->division_id != 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Get approval data
            $approval = DB::table('schedule_attendance_correction_approvals')
                ->where('id', $id)
                ->where('status', 'pending')
                ->first();
                
            if (!$approval) {
                throw new \Exception('Approval tidak ditemukan atau sudah diproses');
            }
            
            // Update approval status
            DB::table('schedule_attendance_correction_approvals')
                ->where('id', $id)
                ->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);
            
            // Apply the correction based on type
            if ($approval->type === 'schedule') {
                // Update schedule
                $newShiftId = null;
                if ($approval->new_value !== 'OFF') {
                    $newShift = DB::table('shifts')
                        ->where('shift_name', $approval->new_value)
                        ->where('division_id', $approval->division_id)
                        ->first();
                    $newShiftId = $newShift ? $newShift->id : null;
                }
                
                DB::table('user_shifts')
                    ->where('id', $approval->record_id)
                    ->update([
                        'shift_id' => $newShiftId,
                        'updated_at' => now(),
                    ]);
                    
            } elseif ($approval->type === 'manual_attendance') {
                // Parse the new values from JSON for manual attendance
                $newData = json_decode($approval->new_value, true);
                
                // Check if record already exists (primary key: sn, pin, scan_date)
                $existingRecord = DB::table('att_log')
                    ->where('sn', $newData['sn'])
                    ->where('pin', $newData['pin'])
                    ->where('scan_date', $newData['scan_date'])
                    ->first();
                
                if ($existingRecord) {
                    // Update existing record
                    DB::table('att_log')
                        ->where('sn', $newData['sn'])
                        ->where('pin', $newData['pin'])
                        ->where('scan_date', $newData['scan_date'])
                        ->update([
                            'verifymode' => $newData['verifymode'] ?? $existingRecord->verifymode,
                            'inoutmode' => $newData['inoutmode'] ?? $existingRecord->inoutmode,
                            'device_ip' => $newData['device_ip'] ?? $existingRecord->device_ip,
                            'updated_at' => now()
                        ]);
                    
                    // Get the record ID if it exists, otherwise use composite key identifier
                    $attLogId = isset($existingRecord->id) ? $existingRecord->id : null;
                    
                    \Log::info('Manual attendance updated:', [
                        'att_log_id' => $attLogId,
                        'sn' => $newData['sn'],
                        'pin' => $newData['pin'],
                        'scan_date' => $newData['scan_date'],
                        'inoutmode' => $newData['inoutmode']
                    ]);
                } else {
                    // Insert new record to att_log
                    try {
                        $attLogId = DB::table('att_log')->insertGetId([
                            'sn' => $newData['sn'],
                            'pin' => $newData['pin'],
                            'scan_date' => $newData['scan_date'],
                            'verifymode' => $newData['verifymode'],
                            'inoutmode' => $newData['inoutmode'],
                            'device_ip' => $newData['device_ip'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    } catch (\Exception $e) {
                        // If insertGetId fails (e.g., composite primary key), set to null
                        $attLogId = null;
                        \Log::warning('Could not get ID from insertGetId, using null', [
                            'error' => $e->getMessage(),
                            'sn' => $newData['sn'],
                            'pin' => $newData['pin'],
                            'scan_date' => $newData['scan_date']
                        ]);
                    }
                    
                    \Log::info('Manual attendance inserted:', [
                        'att_log_id' => $attLogId,
                        'sn' => $newData['sn'],
                        'pin' => $newData['pin'],
                        'scan_date' => $newData['scan_date'],
                        'inoutmode' => $newData['inoutmode']
                    ]);
                }
                
                // Log the correction for audit trail
                // If attLogId is null (composite key table), use 0 or NULL
                // Store the composite key info in new_value instead
                $recordId = $attLogId ?? 0;
                
                DB::table('schedule_attendance_corrections')->insert([
                    'type' => 'manual_attendance',
                    'record_id' => $recordId,
                    'old_value' => null,
                    'new_value' => $approval->new_value,
                    'reason' => $approval->reason,
                    'corrected_by' => $user->id,
                    'corrected_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
            } elseif ($approval->type === 'attendance') {
                // Parse the old and new values from JSON
                $oldData = json_decode($approval->old_value, true);
                $newData = json_decode($approval->new_value, true);
                
                // Log the data for debugging
                \Log::info('Attendance correction data:', [
                    'approval_id' => $id,
                    'old_data' => $oldData,
                    'new_data' => $newData
                ]);
                
                // Find record by sn and pin only (as suggested by user)
                $existingRecord = DB::table('att_log')
                    ->where('sn', $oldData['sn'])
                    ->where('pin', $oldData['pin'])
                    ->where('inoutmode', $oldData['inoutmode'])
                    ->whereDate('scan_date', date('Y-m-d', strtotime($oldData['scan_date'])))
                    ->select('*')
                    ->first();
                
                \Log::info('Existing record check (simplified):', [
                    'found' => $existingRecord ? true : false,
                    'sn' => $oldData['sn'],
                    'pin' => $oldData['pin'],
                    'inoutmode' => $oldData['inoutmode'],
                    'date' => date('Y-m-d', strtotime($oldData['scan_date'])),
                    'record_data' => $existingRecord
                ]);
                
                // If still not found, try to find any record with same sn and pin on the same day
                if (!$existingRecord) {
                    $existingRecord = DB::table('att_log')
                        ->where('sn', $oldData['sn'])
                        ->where('pin', $oldData['pin'])
                        ->whereDate('scan_date', date('Y-m-d', strtotime($oldData['scan_date'])))
                        ->select('*')
                        ->first();
                    
                    \Log::info('Fallback record search:', [
                        'found' => $existingRecord ? true : false,
                        'record_data' => $existingRecord
                    ]);
                }
                
                // Check if the new scan_date already exists for this sn and pin
                $conflictingRecord = DB::table('att_log')
                    ->where('sn', $newData['sn'])
                    ->where('pin', $newData['pin'])
                    ->where('scan_date', $newData['scan_date'])
                    ->where('inoutmode', $newData['inoutmode'])
                    ->first();
                
                if ($conflictingRecord) {
                    \Log::warning('Conflicting record found with new scan_date:', [
                        'conflicting_record' => $conflictingRecord,
                        'new_scan_date' => $newData['scan_date'],
                        'sn' => $newData['sn'],
                        'pin' => $newData['pin']
                    ]);
                    
                    // Delete the conflicting record first
                    DB::table('att_log')->where('id', $conflictingRecord->id)->delete();
                    \Log::info('Deleted conflicting record:', ['id' => $conflictingRecord->id]);
                }
                
                if ($existingRecord && isset($existingRecord->id)) {
                    // Update the found record using the original conditions
                    $updated = DB::table('att_log')
                        ->where('sn', $oldData['sn'])
                        ->where('pin', $oldData['pin'])
                        ->where('scan_date', $oldData['scan_date'])
                        ->where('inoutmode', $oldData['inoutmode'])
                        ->update([
                            'scan_date' => $newData['scan_date'],
                            'verifymode' => $newData['verifymode'] ?? $oldData['verifymode'],
                            'device_ip' => $newData['device_ip'] ?? $oldData['device_ip']
                        ]);
                    
                    \Log::info('Attendance update result:', [
                        'updated_rows' => $updated,
                        'old_conditions' => [
                            'sn' => $oldData['sn'],
                            'pin' => $oldData['pin'],
                            'scan_date' => $oldData['scan_date'],
                            'inoutmode' => $oldData['inoutmode']
                        ]
                    ]);
                } else {
                    \Log::warning('No existing record found with original conditions:', [
                        'old_conditions' => [
                            'sn' => $oldData['sn'],
                            'pin' => $oldData['pin'],
                            'scan_date' => $oldData['scan_date'],
                            'inoutmode' => $oldData['inoutmode']
                        ]
                    ]);
                    
                    // Try alternative approach - find by record_id if available
                    if (isset($approval->record_id) && $approval->record_id > 0) {
                        $updated = DB::table('att_log')
                            ->where('id', $approval->record_id)
                            ->update([
                                'scan_date' => $newData['scan_date'],
                                'verifymode' => $newData['verifymode'] ?? $oldData['verifymode'],
                                'device_ip' => $newData['device_ip'] ?? $oldData['device_ip']
                            ]);
                        
                        \Log::info('Updated by record_id:', [
                            'updated_rows' => $updated,
                            'record_id' => $approval->record_id
                        ]);
                    } else {
                        $updated = 0;
                    }
                }
                
                if ($updated === 0) {
                    \Log::error('Failed to update attendance record', [
                        'approval_id' => $id,
                        'sn' => $oldData['sn'],
                        'pin' => $oldData['pin'],
                        'inoutmode' => $oldData['inoutmode'],
                        'old_scan_date' => $oldData['scan_date'],
                        'new_scan_date' => $newData['scan_date'],
                        'existing_record' => $existingRecord,
                        'approval_record_id' => $approval->record_id ?? 'not_provided'
                    ]);
                    
                    // Try one more time with original conditions
                    $finalAttempt = DB::table('att_log')
                        ->where('sn', $oldData['sn'])
                        ->where('pin', $oldData['pin'])
                        ->where('scan_date', $oldData['scan_date'])
                        ->where('inoutmode', $oldData['inoutmode'])
                        ->update([
                            'scan_date' => $newData['scan_date'],
                            'verifymode' => $newData['verifymode'] ?? $oldData['verifymode'],
                            'device_ip' => $newData['device_ip'] ?? $oldData['device_ip']
                        ]);
                    
                    if ($finalAttempt > 0) {
                        \Log::info('Successfully updated with final attempt:', [
                            'updated_rows' => $finalAttempt
                        ]);
                        $updated = $finalAttempt;
                    } else {
                        throw new \Exception('Gagal mengupdate data attendance: Record dengan SN ' . $oldData['sn'] . ' dan PIN ' . $oldData['pin'] . ' tidak ditemukan');
                    }
                }
                
                // Log the correction for audit trail
                DB::table('schedule_attendance_corrections')->insert([
                    'type' => 'attendance',
                    'record_id' => 0,
                    'old_value' => $approval->old_value,
                    'new_value' => $approval->new_value,
                    'reason' => $approval->reason,
                    'corrected_by' => $user->id,
                    'corrected_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Send notification to requester
            DB::table('notifications')->insert([
                'user_id' => $approval->requested_by,
                'type' => 'correction_approved',
                'message' => "Permohonan koreksi {$approval->type} Anda telah disetujui oleh HRD",
                'url' => '/schedule-attendance-correction',
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Koreksi berhasil disetujui dan diterapkan!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Error approving correction:', [
                'approval_id' => $id,
                'user_id' => $user->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui koreksi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reject correction
     */
    public function rejectCorrection(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        // Only HRD users (division_id = 6) or superadmin can reject
        $isSuperadmin = $user->id_role === '5af56935b011a';
        if (!$isSuperadmin && $user->division_id != 6) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }
        
        // Get rejection reason from request (support both 'reason' and 'rejection_reason')
        $rejectionReason = $request->input('reason') ?? $request->input('rejection_reason');
        
        if (!$rejectionReason || trim($rejectionReason) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Alasan penolakan harus diisi'
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Get approval data
            $approval = DB::table('schedule_attendance_correction_approvals')
                ->where('id', $id)
                ->where('status', 'pending')
                ->first();
                
            if (!$approval) {
                throw new \Exception('Approval tidak ditemukan atau sudah diproses');
            }
            
            // Update approval status
            DB::table('schedule_attendance_correction_approvals')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'rejection_reason' => $rejectionReason,
                    'updated_at' => now(),
                ]);
            
            // Send notification to requester
            DB::table('notifications')->insert([
                'user_id' => $approval->requested_by,
                'type' => 'correction_rejected',
                'message' => "Permohonan koreksi {$approval->type} Anda ditolak oleh HRD. Alasan: " . $rejectionReason,
                'url' => '/schedule-attendance-correction',
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Koreksi berhasil ditolak!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menolak koreksi: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get correction history
     */
    public function getCorrectionHistory(Request $request)
    {
        $recordId = $request->input('record_id');
        $type = $request->input('type'); // schedule or attendance
        
        $history = DB::table('schedule_attendance_corrections')
            ->leftJoin('users', 'schedule_attendance_corrections.corrected_by', '=', 'users.id')
            ->where('schedule_attendance_corrections.record_id', $recordId)
            ->where('schedule_attendance_corrections.type', $type)
            ->select([
                'schedule_attendance_corrections.*',
                'users.nama_lengkap as corrected_by_name'
            ])
            ->orderBy('schedule_attendance_corrections.corrected_at', 'desc')
            ->get();
            
        return response()->json($history);
    }
    
    /**
     * Report page
     */
    public function report()
    {
        $user = auth()->user();
        
        // Get outlets for filter
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get();
            
        // Get divisions for filter
        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->select('id as id_divisi', 'nama_divisi')
            ->orderBy('nama_divisi')
            ->get();
            
        return Inertia::render('ScheduleAttendanceCorrection/Report', [
            'outlets' => $outlets,
            'divisions' => $divisions,
            'user' => $user,
        ]);
    }
    
    
    /**
     * Get correction report data
     */
    public function getReportData(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $status = $request->input('status'); // pending, approved, rejected
        $type = $request->input('type'); // schedule, attendance
        
        // Debug logging
        \Log::info('Schedule Attendance Correction Report Data Request', [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'outlet_id' => $outletId,
            'division_id' => $divisionId,
            'status' => $status,
            'type' => $type,
            'all_inputs' => $request->all()
        ]);
        
        // ✅ VALIDASI: Jika user bukan dari outlet 1 (head office), paksa outlet_id sesuai outlet user
        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outletId = $user->id_outlet;
            \Log::info('User outlet restriction applied for schedule attendance correction report', [
                'user_id' => $user->id,
                'user_outlet' => $user->id_outlet,
                'forced_outlet_id' => $outletId
            ]);
        }
        
        // Get pagination parameters
        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);
        
        // First, let's get the base data without joins to debug
        $baseQuery = DB::table('schedule_attendance_correction_approvals as saca');
        
        // Apply filters - make them more flexible
        if ($startDate && $endDate) {
            $baseQuery->whereBetween('saca.tanggal', [$startDate, $endDate]);
        } elseif ($startDate) {
            $baseQuery->where('saca.tanggal', '>=', $startDate);
        } elseif ($endDate) {
            $baseQuery->where('saca.tanggal', '<=', $endDate);
        }
        
        if ($outletId) {
            $baseQuery->where('saca.outlet_id', $outletId);
        }
        
        if ($divisionId) {
            $baseQuery->where('saca.division_id', $divisionId);
        }
        
        if ($status) {
            $baseQuery->where('saca.status', $status);
        }
        
        if ($type) {
            $baseQuery->where('saca.type', $type);
        }
        
        // Get paginated data
        $data = $baseQuery->orderBy('saca.created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
        
        // Process each item to add related information
        $data->getCollection()->transform(function($item) {
            // Get related data manually
            $requester = DB::table('users')->where('id', $item->requested_by)->first();
            $employee = DB::table('users')->where('id', $item->user_id)->first();
            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $item->outlet_id)->first();
            $division = DB::table('tbl_data_divisi')->where('id', $item->division_id)->first();
            $approver = $item->approved_by ? DB::table('users')->where('id', $item->approved_by)->first() : null;
            
            return (object) [
                'id' => $item->id,
                'type' => $item->type,
                'record_id' => $item->record_id,
                'user_id' => $item->user_id,
                'outlet_id' => $item->outlet_id,
                'division_id' => $item->division_id,
                'tanggal' => $item->tanggal,
                'old_value' => $item->old_value,
                'new_value' => $item->new_value,
                'reason' => $item->reason,
                'status' => $item->status,
                'requested_by' => $item->requested_by,
                'approved_by' => $item->approved_by,
                'approved_at' => $item->approved_at,
                'rejection_reason' => $item->rejection_reason,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'requested_by_name' => $requester ? $requester->nama_lengkap : 'Unknown',
                'employee_name' => $employee ? $employee->nama_lengkap : 'Unknown',
                'nama_outlet' => $outlet ? $outlet->nama_outlet : 'Unknown',
                'nama_divisi' => $division ? $division->nama_divisi : 'Unknown',
                'approved_by_name' => $approver ? $approver->nama_lengkap : null
            ];
        });
        
        // Get summary statistics from all data (not just current page)
        $summaryQuery = DB::table('schedule_attendance_correction_approvals as saca');
        
        // Apply same filters for summary
        if ($startDate && $endDate) {
            $summaryQuery->whereBetween('saca.tanggal', [$startDate, $endDate]);
        } elseif ($startDate) {
            $summaryQuery->where('saca.tanggal', '>=', $startDate);
        } elseif ($endDate) {
            $summaryQuery->where('saca.tanggal', '<=', $endDate);
        }
        
        if ($outletId) {
            $summaryQuery->where('saca.outlet_id', $outletId);
        }
        
        if ($divisionId) {
            $summaryQuery->where('saca.division_id', $divisionId);
        }
        
        if ($status) {
            $summaryQuery->where('saca.status', $status);
        }
        
        if ($type) {
            $summaryQuery->where('saca.type', $type);
        }
        
        $allData = $summaryQuery->get();
        
        $summary = [
            'total' => $allData->count(),
            'pending' => $allData->where('status', 'pending')->count(),
            'approved' => $allData->where('status', 'approved')->count(),
            'rejected' => $allData->where('status', 'rejected')->count(),
            'schedule' => $allData->where('type', 'schedule')->count(),
            'attendance' => $allData->where('type', 'attendance')->count(),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'summary' => $summary,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'has_more_pages' => $data->hasMorePages()
            ]
        ]);
    }
    
    /**
     * Export correction report to Excel
     */
    public function exportReport(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $outletId = $request->input('outlet_id');
        $divisionId = $request->input('division_id');
        $status = $request->input('status');
        $type = $request->input('type');
        
        // Get base data first
        $baseQuery = DB::table('schedule_attendance_correction_approvals as saca');
        
        // Apply filters
        if ($startDate && $endDate) {
            $baseQuery->whereBetween('saca.tanggal', [$startDate, $endDate]);
        }
        
        if ($outletId) {
            $baseQuery->where('saca.outlet_id', $outletId);
        }
        
        if ($divisionId) {
            $baseQuery->where('saca.division_id', $divisionId);
        }
        
        if ($status) {
            $baseQuery->where('saca.status', $status);
        }
        
        if ($type) {
            $baseQuery->where('saca.type', $type);
        }
        
        $baseData = $baseQuery->orderBy('saca.created_at', 'desc')->get();
        
        // Add related data manually
        $data = $baseData->map(function($item) {
            $requester = DB::table('users')->where('id', $item->requested_by)->first();
            $employee = DB::table('users')->where('id', $item->user_id)->first();
            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $item->outlet_id)->first();
            $division = DB::table('tbl_data_divisi')->where('id', $item->division_id)->first();
            $approver = $item->approved_by ? DB::table('users')->where('id', $item->approved_by)->first() : null;
            
            return (object) [
                'id' => $item->id,
                'type' => $item->type,
                'tanggal' => $item->tanggal,
                'old_value' => $item->old_value,
                'new_value' => $item->new_value,
                'reason' => $item->reason,
                'status' => $item->status,
                'created_at' => $item->created_at,
                'approved_at' => $item->approved_at,
                'requested_by_name' => $requester ? $requester->nama_lengkap : 'Unknown',
                'employee_name' => $employee ? $employee->nama_lengkap : 'Unknown',
                'nama_outlet' => $outlet ? $outlet->nama_outlet : 'Unknown',
                'nama_divisi' => $division ? $division->nama_divisi : 'Unknown',
                'approved_by_name' => $approver ? $approver->nama_lengkap : null,
                'rejection_reason' => $item->rejection_reason
            ];
        });
        
        // Create Excel file
        $filename = 'Schedule_Attendance_Correction_Report_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new class($data) implements FromCollection, WithHeadings, WithMapping {
            private $data;
            
            public function __construct($data)
            {
                $this->data = $data;
            }
            
            public function collection()
            {
                return $this->data;
            }
            
            public function headings(): array
            {
                return [
                    'ID',
                    'Tipe',
                    'Tanggal',
                    'Nama Karyawan',
                    'Outlet',
                    'Divisi',
                    'Nilai Lama',
                    'Nilai Baru',
                    'Alasan Koreksi',
                    'Status',
                    'Diminta Oleh',
                    'Disetujui Oleh',
                    'Tanggal Permintaan',
                    'Tanggal Approval',
                    'Alasan Penolakan'
                ];
            }
            
            public function map($row): array
            {
                return [
                    $row->id,
                    $row->type === 'schedule' ? 'Schedule' : 'Attendance',
                    date('d/m/Y', strtotime($row->tanggal)),
                    $row->employee_name,
                    $row->nama_outlet,
                    $row->nama_divisi,
                    $row->old_value,
                    $row->new_value,
                    $row->reason,
                    $row->status === 'pending' ? 'Pending' : ($row->status === 'approved' ? 'Disetujui' : 'Ditolak'),
                    $row->requested_by_name,
                    $row->approved_by_name,
                    date('d/m/Y H:i', strtotime($row->created_at)),
                    $row->approved_at ? date('d/m/Y H:i', strtotime($row->approved_at)) : '-',
                    $row->rejection_reason ?? '-'
                ];
            }
        }, $filename);
    }
}
