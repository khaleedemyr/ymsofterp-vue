<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\LeaveType;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Get date range from request or default to payroll period (26 previous month - 25 current month)
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $startDate = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
        $endDate = date('Y-m-d', strtotime("$tahun-$bulan-25"));
        
        // Get user's work schedule for the date range
        $workSchedules = $this->getWorkSchedules($user->id, $startDate, $endDate);
        
        // Get user's attendance records for the date range
        $attendanceRecords = $this->getAttendanceRecords($user->id, $startDate, $endDate);
        
        // Get attendance summary
        $attendanceSummary = $this->getAttendanceSummary($user->id, $startDate, $endDate);
        
        // Get attendance data with first in and last out (following AttendanceReportController logic)
        $attendanceData = $this->getAttendanceDataWithFirstInLastOut($user->id, $startDate, $endDate);
        
        // Format calendar data similar to UserShiftController
        $calendar = [];
        foreach ($workSchedules as $schedule) {
            $attendanceInfo = $attendanceData[$schedule->schedule_date] ?? null;
            
            $calendar[$schedule->schedule_date][$user->id] = [
                'user_id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'shift_id' => $schedule->shift_id,
                'shift_name' => $schedule->shift_name,
                'time_start' => $schedule->start_time,
                'time_end' => $schedule->end_time,
                // Attendance info
                'first_in' => $attendanceInfo['first_in'] ?? null,
                'last_out' => $attendanceInfo['last_out'] ?? null,
                'telat' => $attendanceInfo['telat'] ?? 0,
                'lembur' => $attendanceInfo['lembur'] ?? 0,
                'has_attendance' => $attendanceInfo ? true : false,
            ];
        }
        
        // Get holidays for the date range
        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$startDate, $endDate])
            ->select('tgl_libur as date', 'keterangan as name')
            ->get();
        
        // Get approved absent requests for the date range
        $approvedAbsents = $this->getApprovedAbsentRequests($user->id, $startDate, $endDate);
        
        // Get active leave types
        $leaveTypes = LeaveType::active()
            ->select('id', 'name', 'max_days', 'requires_document', 'description')
            ->orderBy('name')
            ->get();
        
        return Inertia::render('Attendance/Index', [
            'workSchedules' => $workSchedules,
            'attendanceRecords' => $attendanceRecords,
            'attendanceSummary' => $attendanceSummary,
            'calendar' => $calendar,
            'holidays' => $holidays,
            'approvedAbsents' => $approvedAbsents,
            'leaveTypes' => $leaveTypes,
            'filters' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'user' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'id_outlet' => $user->id_outlet,
            ]
        ]);
    }
    
    private function getWorkSchedules($userId, $startDate, $endDate)
    {
        // Get work schedules from user_shifts table
        $schedules = DB::table('user_shifts')
            ->leftJoin('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
            ->leftJoin('tbl_data_outlet', 'user_shifts.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->where('user_shifts.user_id', $userId)
            ->whereBetween('user_shifts.tanggal', [$startDate, $endDate])
            ->select([
                'user_shifts.id',
                'user_shifts.tanggal as schedule_date',
                'user_shifts.shift_id',
                'user_shifts.outlet_id',
                'shifts.shift_name',
                'shifts.time_start as start_time',
                'shifts.time_end as end_time',
                'tbl_data_outlet.nama_outlet'
            ])
            ->orderBy('user_shifts.tanggal')
            ->orderBy('shifts.time_start')
            ->get();
            
        return $schedules;
    }
    
    private function getAttendanceRecords($userId, $startDate, $endDate)
    {
        // Get attendance records from att_log table (similar to AttendanceReportController)
        $attendance = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->leftJoin('user_shifts as us', function($q) {
                $q->on('u.id', '=', 'us.user_id')
                  ->on(DB::raw('DATE(a.scan_date)'), '=', 'us.tanggal')
                  ->on('o.id_outlet', '=', 'us.outlet_id');
            })
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('u.id', $userId)
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
            ->select([
                DB::raw('DATE(a.scan_date) as attendance_date'),
                DB::raw('MIN(CASE WHEN a.inoutmode = 1 THEN a.scan_date END) as check_in_time'),
                DB::raw('MAX(CASE WHEN a.inoutmode = 2 THEN a.scan_date END) as check_out_time'),
                's.shift_name',
                's.time_start as start_time',
                's.time_end as end_time',
                'o.nama_outlet',
                'us.shift_id',
                'o.id_outlet as outlet_id',
                DB::raw('TIMESTAMPDIFF(MINUTE, MIN(CASE WHEN a.inoutmode = 1 THEN a.scan_date END), MAX(CASE WHEN a.inoutmode = 2 THEN a.scan_date END)) as work_duration_minutes')
            ])
            ->groupBy(DB::raw('DATE(a.scan_date)'), 'u.id', 'o.id_outlet', 's.shift_name', 's.time_start', 's.time_end', 'o.nama_outlet', 'us.shift_id')
            ->orderBy(DB::raw('DATE(a.scan_date)'), 'desc')
            ->get();
            
        // Format work duration and add status
        $attendance->transform(function ($record) {
            if ($record->work_duration_minutes && $record->work_duration_minutes > 0) {
                $hours = floor($record->work_duration_minutes / 60);
                $minutes = $record->work_duration_minutes % 60;
                $record->work_duration = sprintf('%02d:%02d', $hours, $minutes);
            } else {
                $record->work_duration = '00:00';
            }
            
            // Determine status based on check in/out times
            if ($record->check_in_time && $record->check_out_time) {
                $record->status = 'present';
            } elseif ($record->check_in_time && !$record->check_out_time) {
                $record->status = 'half_day';
            } else {
                $record->status = 'absent';
            }
            
            $record->notes = null; // No notes in att_log
            $record->id = uniqid(); // Generate unique ID for frontend
            
            return $record;
        });
            
        return $attendance;
    }
    
    private function getAttendanceSummary($userId, $startDate, $endDate)
    {
        // Get attendance summary statistics from att_log
        $summary = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->join('users as u', 'up.user_id', '=', 'u.id')
            ->where('u.id', $userId)
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
            ->selectRaw('
                COUNT(DISTINCT DATE(a.scan_date)) as total_days,
                COUNT(DISTINCT CASE WHEN a.inoutmode = 0 THEN DATE(a.scan_date) END) as present_days,
                0 as late_days,
                COUNT(DISTINCT DATE(a.scan_date)) - COUNT(DISTINCT CASE WHEN a.inoutmode = 0 THEN DATE(a.scan_date) END) as absent_days,
                COUNT(DISTINCT CASE WHEN a.inoutmode = 0 AND NOT EXISTS (
                    SELECT 1 FROM att_log a2 
                    JOIN user_pins up2 ON a2.pin = up2.pin 
                    WHERE up2.user_id = u.id 
                    AND DATE(a2.scan_date) = DATE(a.scan_date) 
                    AND a2.inoutmode = 1
                ) THEN DATE(a.scan_date) END) as half_day_days,
                AVG(CASE WHEN a.inoutmode = 0 AND EXISTS (
                    SELECT 1 FROM att_log a2 
                    JOIN user_pins up2 ON a2.pin = up2.pin 
                    WHERE up2.user_id = u.id 
                    AND DATE(a2.scan_date) = DATE(a.scan_date) 
                    AND a2.inoutmode = 1
                ) THEN TIMESTAMPDIFF(MINUTE, a.scan_date, (
                    SELECT a2.scan_date FROM att_log a2 
                    JOIN user_pins up2 ON a2.pin = up2.pin 
                    WHERE up2.user_id = u.id 
                    AND DATE(a2.scan_date) = DATE(a.scan_date) 
                    AND a2.inoutmode = 1
                    ORDER BY a2.scan_date DESC LIMIT 1
                )) ELSE NULL END) as avg_work_duration_minutes
            ')
            ->first();
            
        // Format average work duration
        if ($summary->avg_work_duration_minutes) {
            $hours = floor($summary->avg_work_duration_minutes / 60);
            $minutes = $summary->avg_work_duration_minutes % 60;
            $summary->avg_work_duration = sprintf('%02d:%02d', $hours, $minutes);
        } else {
            $summary->avg_work_duration = '00:00';
        }
        
        // Get all shifts in the period (excluding OFF days)
        $totalShifts = DB::table('user_shifts')
            ->where('user_id', $userId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereNotNull('shift_id') // Only count shifts, not OFF days
            ->count();
        
        // Get attendance data with first in and last out
        $attendanceData = $this->getAttendanceDataWithFirstInLastOut($userId, $startDate, $endDate);
        
        $presentDays = 0;
        $totalLateMinutes = 0;
        $absentDays = 0;
        $totalLemburHours = 0;
        
        // Get all shift dates in the period
        $shiftDates = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->whereBetween('us.tanggal', [$startDate, $endDate])
            ->whereNotNull('us.shift_id') // Only count shifts, not OFF days
            ->select('us.tanggal', 's.time_start')
            ->get();
        
        // Calculate total lembur and telat from ALL attendance data (including cross-day)
        foreach ($attendanceData as $date => $attendanceInfo) {
            if ($attendanceInfo && $attendanceInfo['first_in']) {
                // Add lembur hours to total (from all attendance data)
                $totalLemburHours += $attendanceInfo['lembur'] ?? 0;
                
                // Add telat minutes to total (from all attendance data)
                $totalLateMinutes += $attendanceInfo['telat'] ?? 0;
            }
        }
        
        // Calculate present/absent days based on shift dates
        foreach ($shiftDates as $shift) {
            $attendanceInfo = $attendanceData[$shift->tanggal] ?? null;
            
            if ($attendanceInfo && $attendanceInfo['first_in']) {
                $presentDays++;
            } else {
                $absentDays++;
            }
        }
        
        // Calculate percentage
        $percentage = $totalShifts > 0 ? round(($presentDays / $totalShifts) * 100, 1) : 0;
        
        return (object) [
            'total_days' => $totalShifts,
            'present_days' => $presentDays,
            'total_late_minutes' => $totalLateMinutes,
            'absent_days' => $absentDays,
            'total_lembur_hours' => $totalLemburHours,
            'percentage' => $percentage
        ];
    }
    
    public function getCalendarData(Request $request)
    {
        $user = auth()->user();
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        
        // Get calendar data combining schedules and attendance
        $calendarData = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->leftJoin('tbl_data_outlet as o', 'us.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('att_log as a', function($join) use ($user) {
                $join->on(DB::raw('DATE(a.scan_date)'), '=', 'us.tanggal')
                     ->join('user_pins as up', function($q) {
                         $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                     })
                     ->where('up.user_id', '=', $user->id);
            })
            ->where('us.user_id', $user->id)
            ->whereBetween('us.tanggal', [$startDate, $endDate])
            ->select([
                'us.tanggal as schedule_date',
                'us.shift_id',
                'us.outlet_id',
                's.shift_name',
                's.time_start as start_time',
                's.time_end as end_time',
                'o.nama_outlet',
                DB::raw('MIN(CASE WHEN a.inoutmode = 1 THEN a.scan_date END) as check_in_time'),
                DB::raw('MAX(CASE WHEN a.inoutmode = 2 THEN a.scan_date END) as check_out_time'),
                DB::raw('CASE 
                    WHEN MIN(CASE WHEN a.inoutmode = 0 THEN a.scan_date END) IS NOT NULL 
                         AND MAX(CASE WHEN a.inoutmode = 1 THEN a.scan_date END) IS NOT NULL 
                    THEN "present"
                    WHEN MIN(CASE WHEN a.inoutmode = 0 THEN a.scan_date END) IS NOT NULL 
                         AND MAX(CASE WHEN a.inoutmode = 1 THEN a.scan_date END) IS NULL 
                    THEN "half_day"
                    ELSE "absent"
                END as attendance_status')
            ])
            ->groupBy('us.tanggal', 'us.shift_id', 'us.outlet_id', 's.shift_name', 's.time_start', 's.time_end', 'o.nama_outlet')
            ->orderBy('us.tanggal')
            ->orderBy('s.time_start')
            ->get();
            
        // Group by date for calendar display
        $groupedData = $calendarData->groupBy('schedule_date');
        
        $formattedData = [];
        foreach ($groupedData as $date => $records) {
            $formattedData[$date] = $records->map(function ($record) {
                return [
                    'shift_name' => $record->shift_name,
                    'start_time' => $record->start_time,
                    'end_time' => $record->end_time,
                    'outlet_name' => $record->nama_outlet,
                    'schedule_status' => 'scheduled',
                    'attendance_status' => $record->attendance_status,
                    'check_in_time' => $record->check_in_time,
                    'check_out_time' => $record->check_out_time,
                    'notes' => null,
                    'has_attendance' => !is_null($record->check_in_time)
                ];
            });
        }
        
        return response()->json($formattedData);
    }

    private function getAttendanceDataWithFirstInLastOut($userId, $startDate, $endDate)
    {
        // Following AttendanceReportController logic for first in and last out
        $rawData = DB::table('att_log as a')
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
            ->whereBetween(DB::raw('DATE(a.scan_date)'), [$startDate, $endDate])
            ->orderBy('a.scan_date')
            ->get();

        // Process data to handle cross-day correctly (following AttendanceReportController logic)
        $processedData = [];
        
        // Step 1: Group scans by user, outlet, and date
        foreach ($rawData as $scan) {
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
        
        // Step 2: Process each group to determine first in and last out
        $attendanceData = [];
        foreach ($processedData as $key => $data) {
            $scans = collect($data['scans'])->sortBy('scan_date');
            $inScans = $scans->where('inoutmode', 1);
            $outScans = $scans->where('inoutmode', 2);
            
            // Get first in scan
            $firstIn = $inScans->first()['scan_date'] ?? null;
            
            // Get last out scan (including cross-day)
            $lastOut = null;
            $isCrossDay = false;
            
            if ($firstIn) {
                // Look for first out on the same day (following AttendanceReportController logic)
                $sameDayOut = $outScans->where('scan_date', '>', $firstIn)->first();
                
                if ($sameDayOut) {
                    // Ada scan keluar di hari yang sama
                    $lastOut = $sameDayOut['scan_date'];
                    $isCrossDay = false;
                } else {
                    // Cari scan keluar di hari berikutnya
                    $nextDay = date('Y-m-d', strtotime($data['tanggal'] . ' +1 day'));
                    $nextDayKey = $data['user_id'] . '_' . $data['id_outlet'] . '_' . $nextDay;
                    
                    if (isset($processedData[$nextDayKey])) {
                        $nextDayScans = collect($processedData[$nextDayKey]['scans'])->sortBy('scan_date');
                        $nextDayOut = $nextDayScans->where('inoutmode', 2)->first();
                        
                        if ($nextDayOut) {
                            $lastOut = $nextDayOut['scan_date'];
                            $isCrossDay = true;
                            
                            // Hapus scan keluar ini dari hari berikutnya (following AttendanceReportController logic)
                            $processedData[$nextDayKey]['scans'] = $nextDayScans->where('inoutmode', '!=', 2)->values()->toArray();
                        }
                    }
                }
            }
            
            // Calculate telat and lembur following AttendanceReportController logic exactly
            $telat = 0;
            $lembur = 0;
            $is_off = false;
            
            if ($firstIn) {
                // Get shift data for this date
                $shift = DB::table('user_shifts as us')
                    ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
                    ->where('us.user_id', $data['user_id'])
                    ->where('us.tanggal', $data['tanggal'])
                    ->where('us.outlet_id', $data['id_outlet'])
                    ->select('s.time_start', 's.time_end', 's.shift_name', 'us.shift_id')
                    ->first();
                
                if ($shift) {
                    if (is_null($shift->shift_id) || (strtolower($shift->shift_name ?? '') === 'off')) {
                        $is_off = true;
                    }
                }
                
                if (!$is_off && $shift && $shift->time_start && $shift->shift_id) {
                    // Calculate telat (late arrival) - following AttendanceReportController logic exactly
                    $start = strtotime($shift->time_start);
                    $masuk = strtotime(date('H:i:s', strtotime($firstIn)));
                    $diff = $masuk - $start;
                    $telat = $diff > 0 ? round($diff/60) : 0;
                    
                    // Calculate lembur (overtime) - following AttendanceReportController logic exactlyHar
                    if ($lastOut && $shift->time_end) {
                        // Buat datetime lengkap untuk shift end
                        $shiftEndDateTime = date('Y-m-d', strtotime($data['tanggal'])) . ' ' . $shift->time_end;
                        
                        // Gunakan scan keluar yang sudah dalam format datetime lengkap
                        $scanOutDateTime = $lastOut;
                        
                        // Hitung selisih waktu
                        $end = strtotime($shiftEndDateTime);
                        $keluar = strtotime($scanOutDateTime);
                        $diff = $keluar - $end;
                        $lembur = $diff > 0 ? floor($diff/3600) : 0;
                    }
                } else {
                    $firstIn = null;
                    $lastOut = null;
                    $telat = 0;
                    $lembur = 0;
                }
            }
            
            $attendanceData[$data['tanggal']] = [
                'first_in' => $firstIn ? date('H:i', strtotime($firstIn)) : null,
                'last_out' => $lastOut ? date('H:i', strtotime($lastOut)) : null,
                'outlet_name' => $data['nama_outlet'],
                'is_cross_day' => $isCrossDay,
                'telat' => $telat,
                'lembur' => $lembur
            ];
        }
        
        return $attendanceData;
    }
    
    public function submitAbsentRequest(Request $request)
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'leave_type_id' => 'required|exists:leave_types,id',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'reason' => 'required|string|max:1000',
                'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
                'documents' => 'nullable|array',
                'documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120' // 5MB max per file
            ]);
            
            // Get leave type information
            $leaveType = LeaveType::findOrFail($request->leave_type_id);
            
            // Validate document requirement
            $hasDocument = $request->hasFile('document') || $request->hasFile('documents');
            if ($leaveType->requires_document && !$hasDocument) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen pendukung wajib diupload untuk jenis izin/cuti ini'
                ], 422);
            }
            
            // Check if user already has attendance data for any date in the range
            $hasAttendance = DB::table('att_log as a')
                ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
                ->join('user_pins as up', function($q) {
                    $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                })
                ->where('up.user_id', $user->id)
                ->whereBetween(DB::raw('DATE(a.scan_date)'), [$request->date_from, $request->date_to])
                ->exists();
            
            if ($hasAttendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah memiliki data kehadiran untuk salah satu tanggal dalam rentang ini'
                ], 400);
            }
            
            // Check if user already has absent request for any date in the range
            $existingRequest = DB::table('absent_requests')
                ->where('user_id', $user->id)
                ->where(function($query) use ($request) {
                    $query->whereBetween('date_from', [$request->date_from, $request->date_to])
                          ->orWhereBetween('date_to', [$request->date_from, $request->date_to])
                          ->orWhere(function($q) use ($request) {
                              $q->where('date_from', '<=', $request->date_from)
                                ->where('date_to', '>=', $request->date_to);
                          });
                })
                ->whereIn('status', ['pending', 'approved'])
                ->exists();
            
            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah mengajukan izin/cuti untuk salah satu tanggal dalam rentang ini'
                ], 400);
            }
            
            // Handle file upload
            $documentPaths = [];
            
            // Handle single document (legacy support)
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $documentPaths[] = $file->store('absent-documents', 'public');
            }
            
            // Handle multiple documents
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    if ($file) {
                        $documentPaths[] = $file->store('absent-documents', 'public');
                    }
                }
            }
            
            // Use first document path for backward compatibility
            $documentPath = !empty($documentPaths) ? $documentPaths[0] : null;
            
            // Insert absent request
            $absentRequestId = DB::table('absent_requests')->insertGetId([
                'user_id' => $user->id,
                'leave_type_id' => $request->leave_type_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'reason' => $request->reason,
                'document_path' => $documentPath,
                'document_paths' => !empty($documentPaths) ? json_encode($documentPaths) : null, // Store multiple paths as JSON
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Find approver (atasan) based on user's jabatan and outlet
            $approver = $this->findApprover($user);
            
            if ($approver) {
                // Find HRD approver
                $hrdApprover = $this->findHrdApprover();
                
                // Create approval request
                $approvalRequestId = DB::table('approval_requests')->insertGetId([
                    'user_id' => $user->id,
                    'approver_id' => $approver->id,
                    'hrd_approver_id' => $hrdApprover ? $hrdApprover->id : null,
                    'leave_type_id' => $request->leave_type_id,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'reason' => $request->reason,
                    'document_path' => $documentPath,
                    'document_paths' => !empty($documentPaths) ? json_encode($documentPaths) : null, // Store multiple paths as JSON
                    'status' => 'pending',
                    'hrd_status' => $hrdApprover ? 'pending' : null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Link absent request with approval request
                DB::table('absent_requests')
                    ->where('id', $absentRequestId)
                    ->update(['approval_request_id' => $approvalRequestId]);

                // Kirim notifikasi ke atasan
                DB::table('notifications')->insert([
                    'user_id' => $approver->id,
                    'type' => 'leave_approval_request',
                    'message' => "Permohonan izin/cuti baru dari {$user->nama_lengkap} ({$leaveType->name}) untuk periode {$request->date_from} - {$request->date_to} membutuhkan persetujuan Anda.",
                    'url' => config('app.url') . '/home',
                    'is_read' => 0,
                    'approval_id' => $approvalRequestId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin/cuti berhasil dikirim',
                'data' => [
                    'id' => $absentRequestId,
                    'leave_type_id' => $request->leave_type_id,
                    'leave_type_name' => $leaveType->name,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'status' => 'pending'
                ]
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error submitting absent request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengirim permohonan'
            ], 500);
        }
    }
    
    /**
     * Find approver (atasan) for a user based on jabatan hierarchy and outlet
     */
    private function findApprover($user)
    {
        // Get user's jabatan information
        $userJabatan = DB::table('tbl_data_jabatan')
            ->where('id_jabatan', $user->id_jabatan)
            ->first();
            
        if (!$userJabatan || !$userJabatan->id_atasan) {
            return null;
        }
        
        // Find user with id_atasan jabatan in the same outlet
        $approver = DB::table('users as u')
            ->join('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->where('j.id_jabatan', $userJabatan->id_atasan)
            ->where('u.id_outlet', $user->id_outlet)
            ->where('u.status', 'A')
            ->select('u.id', 'u.nama_lengkap', 'u.email', 'j.nama_jabatan')
            ->first();
            
        return $approver;
    }
    
    /**
     * Find HRD approver (user with division_id=6 and status=A)
     */
    private function findHrdApprover()
    {
        $hrdApprover = DB::table('users')
            ->where('division_id', 6)
            ->where('status', 'A')
            ->select('id', 'nama_lengkap', 'email')
            ->first();
            
        return $hrdApprover;
    }
    
    /**
     * Get approved absent requests for the user in the date range
     */
    private function getApprovedAbsentRequests($userId, $startDate, $endDate)
    {
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
                'absent_requests.id',
                'absent_requests.date_from',
                'absent_requests.date_to',
                'absent_requests.reason',
                'leave_types.name as leave_type_name',
                'absent_requests.approved_at',
                'absent_requests.hrd_approved_at'
            ])
            ->orderBy('absent_requests.date_from')
            ->get();
            
        return $approvedAbsents;
    }

    /**
     * Show absent report page
     */
    public function report(Request $request)
    {
        $user = auth()->user();
        
        // Get filter parameters
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $status = $request->get('status');
        $outletId = $request->get('outlet_id');
        $divisionId = $request->get('division_id');
        $employeeName = $request->get('employee_name');
        
        // Get outlets for filter
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get();
            
        // Get divisions for filter
        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->orderBy('nama_divisi')
            ->get();
        
        return Inertia::render('Attendance/Report', [
            'outlets' => $outlets,
            'divisions' => $divisions,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'outlet_id' => $outletId,
                'division_id' => $divisionId,
                'employee_name' => $employeeName
            ]
        ]);
    }

    /**
     * Get absent report data
     */
    public function getReportData(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $status = $request->get('status');
        $outletId = $request->get('outlet_id');
        $divisionId = $request->get('division_id');
        $employeeName = $request->get('employee_name');
        
        
        $query = DB::table('absent_requests')
            ->leftJoin('users', 'absent_requests.user_id', '=', 'users.id')
            ->leftJoin('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('users as approvers', 'absent_requests.approved_by', '=', 'approvers.id')
            ->leftJoin('users as hrd_approvers', 'absent_requests.hrd_approved_by', '=', 'hrd_approvers.id')
            ->select([
                'absent_requests.id',
                'absent_requests.date_from',
                'absent_requests.date_to',
                'absent_requests.reason',
                'absent_requests.status',
                'absent_requests.created_at',
                'absent_requests.approved_at',
                'absent_requests.hrd_approved_at',
                'absent_requests.rejection_reason',
                'absent_requests.document_path',
                'absent_requests.document_paths',
                'users.nama_lengkap as employee_name',
                'users.id as user_id',
                'leave_types.name as leave_type_name',
                'tbl_data_outlet.nama_outlet',
                'tbl_data_divisi.nama_divisi',
                'approvers.nama_lengkap as approver_name',
                'hrd_approvers.nama_lengkap as hrd_approver_name'
            ]);
        
        // Apply filters
        if ($startDate) {
            $query->where('absent_requests.date_from', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('absent_requests.date_to', '<=', $endDate);
        }
        
        if ($status) {
            $query->where('absent_requests.status', $status);
        }
        
        if ($outletId) {
            $query->where('users.id_outlet', $outletId);
        }
        
        if ($divisionId) {
            $query->where('users.division_id', $divisionId);
        }
        
        if ($employeeName) {
            $query->where('users.nama_lengkap', 'LIKE', '%' . $employeeName . '%');
        }
        
        // Get pagination parameters
        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);
        
        $data = $query->orderBy('absent_requests.created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
        
        
        // Process document_paths for each item
        $data->getCollection()->transform(function ($item) {
            if ($item->document_paths) {
                $item->document_paths = json_decode($item->document_paths, true) ?: [];
            } else {
                $item->document_paths = [];
            }
            return $item;
        });
        
        return response()->json([
            'success' => true,
            'data' => $data->items(),
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
     * Export absent report to Excel
     */
    public function exportReport(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $status = $request->get('status');
        $outletId = $request->get('outlet_id');
        $divisionId = $request->get('division_id');
        
        $query = DB::table('absent_requests')
            ->leftJoin('users', 'absent_requests.user_id', '=', 'users.id')
            ->leftJoin('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->leftJoin('users as approvers', 'absent_requests.approved_by', '=', 'approvers.id')
            ->leftJoin('users as hrd_approvers', 'absent_requests.hrd_approved_by', '=', 'hrd_approvers.id')
            ->select([
                'absent_requests.id',
                'absent_requests.date_from',
                'absent_requests.date_to',
                'absent_requests.reason',
                'absent_requests.status',
                'absent_requests.created_at',
                'absent_requests.approved_at',
                'absent_requests.hrd_approved_at',
                'absent_requests.rejection_reason',
                'users.nama_lengkap as employee_name',
                'leave_types.name as leave_type_name',
                'tbl_data_outlet.nama_outlet',
                'tbl_data_divisi.nama_divisi',
                'approvers.nama_lengkap as approver_name',
                'hrd_approvers.nama_lengkap as hrd_approver_name'
            ]);
        
        // Apply filters
        if ($startDate) {
            $query->where('absent_requests.date_from', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('absent_requests.date_to', '<=', $endDate);
        }
        
        if ($status) {
            $query->where('absent_requests.status', $status);
        }
        
        if ($outletId) {
            $query->where('users.id_outlet', $outletId);
        }
        
        if ($divisionId) {
            $query->where('users.division_id', $divisionId);
        }
        
        $data = $query->orderBy('absent_requests.created_at', 'desc')->get();
        
        // Create Excel export
        $export = new \App\Exports\AbsentReportExport($data);
        
        $filename = 'absent_report_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }
}
