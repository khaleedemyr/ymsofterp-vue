<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\LeaveType;
use App\Services\NotificationService;

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
                'has_no_checkout' => $attendanceInfo['has_no_checkout'] ?? false,
            ];
        }
        
        // Get holidays for the date range
        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$startDate, $endDate])
            ->select('tgl_libur as date', 'keterangan as name')
            ->get();
        
        // Get approved absent requests for the date range
        $approvedAbsents = $this->getApprovedAbsentRequests($user->id, $startDate, $endDate);
        
        // Get user's leave requests (for cancel functionality)
        $userLeaveRequests = $this->getUserLeaveRequests($user->id, $startDate, $endDate);
        
        // Get active leave types
        $leaveTypes = LeaveType::active()
            ->select('id', 'name', 'max_days', 'requires_document', 'description')
            ->orderBy('name')
            ->get();
        
        // Get available approvers for leave request
        $availableApprovers = $this->getAvailableApprovers($user);
        
        // Get PH and Extra Off data for current month
        $phData = $this->getPHData($user->id, $startDate, $endDate);
        $extraOffData = $this->getExtraOffData($user->id, $startDate, $endDate);
        
        // Get correction requests for this user
        $correctionRequests = $this->getCorrectionRequests($user->id, $startDate, $endDate);
        
        return Inertia::render('Attendance/Index', [
            'workSchedules' => $workSchedules,
            'attendanceRecords' => $attendanceRecords,
            'attendanceSummary' => $attendanceSummary,
            'calendar' => $calendar,
            'holidays' => $holidays,
            'approvedAbsents' => $approvedAbsents,
            'userLeaveRequests' => $userLeaveRequests,
            'leaveTypes' => $leaveTypes,
            'availableApprovers' => $availableApprovers,
            'phData' => $phData,
            'extraOffData' => $extraOffData,
            'correctionRequests' => $correctionRequests,
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
                'cuti' => $user->cuti ?? 0, // ✅ FIX: Include cuti balance for saldo cuti
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
                'shifts.shift_name',
                'shifts.time_start as start_time',
                'shifts.time_end as end_time',
            ])
            ->orderBy('user_shifts.tanggal')
            ->orderBy('shifts.time_start')
            ->get();
            
        return $schedules;
    }
    
    private function getAttendanceRecords($userId, $startDate, $endDate)
    {
        // Use the same logic as getAttendanceDataWithFirstInLastOut for consistency
        $attendanceData = $this->getAttendanceDataWithFirstInLastOut($userId, $startDate, $endDate);
        
        // Convert to the format expected by the frontend
        $attendance = [];
        foreach ($attendanceData as $date => $data) {
            // Calculate work duration
            $workDurationMinutes = 0;
            if ($data['first_in'] && $data['last_out']) {
                $firstInTime = strtotime($date . ' ' . $data['first_in']);
                $lastOutTime = strtotime($date . ' ' . $data['last_out']);
                
                // If cross-day, add 24 hours to last out time
                if ($data['is_cross_day']) {
                    $lastOutTime = strtotime($date . ' ' . $data['last_out'] . ' +1 day');
                }
                
                $workDurationMinutes = ($lastOutTime - $firstInTime) / 60;
            }
            
            $attendance[] = (object) [
                'attendance_date' => $date,
                'check_in_time' => $data['first_in'] ? $date . ' ' . $data['first_in'] : null,
                'check_out_time' => $data['last_out'] ? $date . ' ' . $data['last_out'] : null,
                'shift_name' => null, // Will be filled by work schedule
                'start_time' => null, // Will be filled by work schedule
                'end_time' => null, // Will be filled by work schedule
                'shift_id' => null, // Will be filled by work schedule
                'work_duration_minutes' => $workDurationMinutes,
                'is_cross_day' => $data['is_cross_day'],
                'has_no_checkout' => $data['has_no_checkout']
            ];
        }
        
        $attendance = collect($attendance);
            
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
        
        // Get all shift dates in the period (only up to today)
        $today = date('Y-m-d');
        $shiftDates = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->whereBetween('us.tanggal', [$startDate, $endDate])
            ->where('us.tanggal', '<=', $today) // Only count shifts up to today
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
        $today = date('Y-m-d');
        
        foreach ($shiftDates as $shift) {
            // Skip future dates (hari yang belum berjalan)
            if ($shift->tanggal > $today) {
                continue;
            }
            
            $attendanceInfo = $attendanceData[$shift->tanggal] ?? null;
            
            if ($attendanceInfo && $attendanceInfo['first_in']) {
                $presentDays++;
            } else {
                $absentDays++;
            }
        }
        
        // Calculate total shifts (only up to today)
        $totalShifts = $shiftDates->count();
        
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
        
        // Get schedules first
        $schedules = DB::table('user_shifts as us')
            ->leftJoin('shifts as s', 'us.shift_id', '=', 's.id')
            ->where('us.user_id', $user->id)
            ->whereBetween('us.tanggal', [$startDate, $endDate])
            ->select([
                'us.tanggal as schedule_date',
                'us.shift_id',
                's.shift_name',
                's.time_start as start_time',
                's.time_end as end_time'
            ])
            ->orderBy('us.tanggal')
            ->orderBy('s.time_start')
            ->get();

        // Get attendance data using the same logic as getAttendanceRecords
        $attendanceData = $this->getAttendanceDataWithFirstInLastOut($user->id, $startDate, $endDate);
        
        // Combine schedules with attendance data
        $calendarData = [];
        foreach ($schedules as $schedule) {
            $attendanceInfo = $attendanceData[$schedule->schedule_date] ?? null;
            
            
            $calendarData[] = (object) [
                'schedule_date' => $schedule->schedule_date,
                'shift_id' => $schedule->shift_id,
                'shift_name' => $schedule->shift_name,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'check_in_time' => $attendanceInfo['first_in'] ?? null,
                'check_out_time' => $attendanceInfo['last_out'] ?? null,
                'attendance_status' => $attendanceInfo ? 'present' : 'absent',
                'has_no_checkout' => $attendanceInfo['has_no_checkout'] ?? false
            ];
        }
        
        $calendarData = collect($calendarData);
            
        // Group by date for calendar display
        $groupedData = $calendarData->groupBy('schedule_date');
        
        $formattedData = [];
        foreach ($groupedData as $date => $records) {
            $formattedData[$date] = $records->map(function ($record) {
                return [
                    'shift_name' => $record->shift_name,
                    'start_time' => $record->start_time,
                    'end_time' => $record->end_time,
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

        // Process data to handle cross-day correctly with multi-outlet support
        $processedData = [];
        
        // Step 1: Group scans by user and date - SAME AS AttendanceReportController
        foreach ($rawData as $scan) {
            $date = date('Y-m-d', strtotime($scan->scan_date));
            $key = $scan->user_id . '_' . $date;
            
            if (!isset($processedData[$key])) {
                $processedData[$key] = [
                    'tanggal' => $date,
                    'user_id' => $scan->user_id,
                    'nama_lengkap' => $scan->nama_lengkap,
                    'scans' => []
                ];
            }
            
            $processedData[$key]['scans'][] = [
                'scan_date' => $scan->scan_date,
                'inoutmode' => $scan->inoutmode
            ];
        }
        
        // Step 2: Process each group using smart cross-day processing - SAME AS AttendanceReportController
        $attendanceData = [];
        foreach ($processedData as $key => $data) {
            $result = $this->processSmartCrossDayAttendance($data, $processedData);
            
            $firstIn = $result['jam_masuk'];
            $lastOut = $result['jam_keluar'];
            $isCrossDay = $result['is_cross_day'];
            
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
                    
                    // Calculate lembur (overtime) - using improved logic from AttendanceReportController
                    if ($lastOut && $shift->time_end) {
                        $lembur = $this->calculateSimpleOvertime($lastOut, $shift->time_end);
                    }
                } else {
                    $firstIn = null;
                    $lastOut = null;
                    $telat = 0;
                    $lembur = 0;
                }
            }
            
            // Deteksi attendance tanpa checkout
            $has_no_checkout = false;
            if (!$is_off && $firstIn && !$lastOut) {
                $has_no_checkout = true;
            }
            
            
            // Store data for this date
            $attendanceData[$data['tanggal']] = [
                'first_in' => $firstIn ? date('H:i', strtotime($firstIn)) : null,
                'last_out' => $lastOut ? date('H:i', strtotime($lastOut)) : null,
                'is_cross_day' => $isCrossDay,
                'telat' => $telat,
                'lembur' => $lembur,
                'has_no_checkout' => $has_no_checkout
            ];
        }
        
        return $attendanceData;
    }
    
    /**
     * Perhitungan lembur yang MENANGANI CROSS-DAY dengan benar - FIXED
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
     * Calculate lateness for early checkout - SAME LOGIC AS AttendanceReportController
     */
    private function calculateEarlyCheckoutLateness($jamKeluar, $shiftEnd, $isCrossDay = false) {
        // Jika cross-day, tidak ada telat dari early checkout
        if ($isCrossDay) {
            return 0;
        }
        
        if (!$jamKeluar || !$shiftEnd) {
            return 0;
        }
        
        // Ambil jam saja (abaikan tanggal)
        $jamKeluarTime = date('H:i:s', strtotime($jamKeluar));
        
        // Konversi ke timestamp untuk perhitungan
        $keluarTimestamp = strtotime($jamKeluarTime);
        $shiftEndTimestamp = strtotime($shiftEnd);
        
        // Hitung selisih dalam detik
        $diffSeconds = $shiftEndTimestamp - $keluarTimestamp;
        
        // Jika checkout lebih awal dari shift end, hitung telat
        if ($diffSeconds > 0) {
            $latenessMinutes = round($diffSeconds / 60);
            return $latenessMinutes;
        }
        
        return 0;
    }
    
    /**
     * Smart cross-day attendance processing untuk multi-outlet scenarios
     * SAME LOGIC AS AttendanceReportController
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
    
    public function submitAbsentRequest(Request $request)
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'leave_type_id' => 'required|exists:leave_types,id',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'reason' => 'required|string|max:1000',
                'approver_id' => 'nullable|exists:users,id', // Optional for backward compatibility
                'approvers' => 'nullable|array', // New: multiple approvers (berjenjang)
                'approvers.*' => 'required|exists:users,id',
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
            // IMPROVED VALIDATION: More precise date overlap checking
            $existingRequest = DB::table('absent_requests')
                ->where('user_id', $user->id)
                ->where('date_from', '<=', $request->date_to) // Existing request starts before or on new request end
                ->where('date_to', '>=', $request->date_from) // Existing request ends after or on new request start
                ->whereIn('status', ['pending', 'approved', 'supervisor_approved'])
                ->exists();
            
            if ($existingRequest) {
                // Log the conflicting request for debugging
                $conflictingRequest = DB::table('absent_requests')
                    ->where('user_id', $user->id)
                    ->where('date_from', '<=', $request->date_to)
                    ->where('date_to', '>=', $request->date_from)
                    ->whereIn('status', ['pending', 'approved', 'supervisor_approved'])
                    ->first();
                
                \Log::warning('Leave request validation failed', [
                    'user_id' => $user->id,
                    'user_name' => $user->nama_lengkap,
                    'requested_from' => $request->date_from,
                    'requested_to' => $request->date_to,
                    'conflicting_request_id' => $conflictingRequest->id ?? null,
                    'conflicting_from' => $conflictingRequest->date_from ?? null,
                    'conflicting_to' => $conflictingRequest->date_to ?? null,
                    'conflicting_status' => $conflictingRequest->status ?? null
                ]);
                
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
            
            // Handle multiple approvers (new flow) or single approver (backward compatibility)
            $approvers = [];
            
            if (!empty($request->approvers) && is_array($request->approvers)) {
                // New flow: multiple approvers
                $approvers = $request->approvers;
            } elseif (!empty($request->approver_id)) {
                // Backward compatibility: single approver
                $approvers = [$request->approver_id];
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimal harus memilih 1 approver'
                ], 400);
            }
            
            // Validate all approvers exist and are active
            $validApprovers = DB::table('users')
                ->whereIn('id', $approvers)
                ->where('status', 'A')
                ->select('id', 'nama_lengkap', 'email')
                ->get();
                
            if ($validApprovers->count() !== count($approvers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salah satu atau lebih approver tidak valid atau tidak aktif'
                ], 400);
            }
            
            // Remove duplicates and maintain order
            $approvers = array_values(array_unique($approvers));
            
            // Find HRD approver (division_id=6 and status=A) - will be used after all approvers approve
            $hrdApprover = $this->findHrdApprover();
            
            // Create approval request (for backward compatibility with existing approval system)
            $firstApprover = $validApprovers->first();
            $approvalRequestId = DB::table('approval_requests')->insertGetId([
                'user_id' => $user->id,
                'approver_id' => $firstApprover->id, // First approver for backward compatibility
                'hrd_approver_id' => null, // Will be set after all approvers approve
                'leave_type_id' => $request->leave_type_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'reason' => $request->reason,
                'document_path' => $documentPath,
                'document_paths' => !empty($documentPaths) ? json_encode($documentPaths) : null,
                'status' => 'pending',
                'hrd_status' => null, // Will be set to 'pending' after all approvers approve
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Link absent request with approval request
            DB::table('absent_requests')
                ->where('id', $absentRequestId)
                ->update(['approval_request_id' => $approvalRequestId]);

            // Create approval flows for each approver (sequential approval)
            foreach ($approvers as $index => $approverId) {
                DB::table('absent_request_approval_flows')->insert([
                    'absent_request_id' => $absentRequestId,
                    'approver_id' => $approverId,
                    'approval_level' => $index + 1, // Level 1 = first, higher = later
                    'status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Send notification to first approver only (sequential flow)
            $firstApprover = $validApprovers->first();
            DB::table('notifications')->insert([
                'user_id' => $firstApprover->id,
                'type' => 'leave_approval_request',
                'message' => "Permohonan izin/cuti baru dari {$user->nama_lengkap} ({$leaveType->name}) untuk periode {$request->date_from} - {$request->date_to} membutuhkan persetujuan Anda (Level 1/" . count($approvers) . ").",
                'url' => config('app.url') . '/home',
                'is_read' => 0,
                'approval_id' => $approvalRequestId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
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
     * Get available approvers for leave request
     * Returns users from same outlet who are not the current user
     */
    private function getAvailableApprovers($user)
    {
        $approvers = DB::table('users as u')
            ->leftJoin('tbl_data_jabatan as j', 'u.id_jabatan', '=', 'j.id_jabatan')
            ->leftJoin('tbl_data_divisi as d', 'u.division_id', '=', 'd.id')
            ->where('u.id_outlet', $user->id_outlet) // Same outlet
            ->where('u.id', '!=', $user->id) // Not the current user
            ->where('u.status', 'A') // Active users only
            ->select([
                'u.id',
                'u.nama_lengkap',
                'u.email',
                'j.nama_jabatan',
                'd.nama_divisi'
            ])
            ->orderBy('j.nama_jabatan')
            ->orderBy('u.nama_lengkap')
            ->get();
            
        return $approvers;
    }
    
    /**
     * Get approvers for leave request with search functionality
     */
    public function getApprovers(Request $request)
    {
        try {
            $user = auth()->user();
            $search = $request->get('search', '');
            
            // Use same approach as Purchase Order Ops
            $users = \App\Models\User::where('users.status', 'A')
                ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
                ->where('users.id', '!=', $user->id) // Not the current user
                ->where(function($query) use ($search) {
                    $query->where('users.nama_lengkap', 'like', "%{$search}%")
                          ->orWhere('users.email', 'like', "%{$search}%")
                          ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
                })
                ->where(function($q) {
                    $q->whereNull('tbl_data_jabatan.id_level') // Include users without level
                      ->orWhereNotIn('tbl_data_jabatan.id_level', [7, 8, 13]); // Exclude specific levels
                })
                ->select('users.id', 'users.nama_lengkap', 'users.email', 'tbl_data_jabatan.nama_jabatan', 'tbl_data_divisi.nama_divisi', 'tbl_data_outlet.nama_outlet', 'tbl_data_jabatan.id_level')
                ->orderBy('users.nama_lengkap')
                ->limit(50)
                ->get();
            
            // Debug logging
            \Log::info('Approvers query result (with level filter)', [
                'total_count' => $users->count(),
                'user_outlet' => $user->id_outlet,
                'search_term' => $search,
                'user_id' => $user->id,
                'users' => $users->toArray(),
                'level_summary' => $users->groupBy('id_level')->map(function($group) {
                    return $group->count();
                })
            ]);
            
            return response()->json([
                'success' => true,
                'users' => $users
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error getting approvers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar atasan'
            ], 500);
        }
    }
    
    /**
     * Cancel leave request
     */
    public function cancelLeaveRequest(Request $request, $id)
    {
        try {
            $user = auth()->user();
            
            // Find the leave request
            $leaveRequest = DB::table('absent_requests')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$leaveRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan izin/cuti tidak ditemukan'
                ], 404);
            }
            
            // Check if request can be cancelled
            // Can cancel if status is pending, supervisor_approved, or approved (HRD approved)
            if (!in_array($leaveRequest->status, ['pending', 'supervisor_approved', 'approved'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan izin/cuti tidak dapat dibatalkan. Status: ' . $leaveRequest->status
                ], 400);
            }
            
            // Check if date_from has not passed (can cancel until the day of the leave)
            $startDate = new \DateTime($leaveRequest->date_from);
            $startDate->setTime(0, 0, 0); // Set to start of day
            
            $today = new \DateTime();
            $today->setTime(0, 0, 0); // Set to start of day
            
            // Cannot cancel if start date has passed
            if ($startDate < $today) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permohonan izin/cuti tidak dapat dibatalkan karena tanggal izin sudah terlewat'
                ], 400);
            }
            
            // Update the request status to rejected (cancelled by user)
            DB::table('absent_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'rejection_reason' => $request->get('reason', 'Dibatalkan oleh user'),
                    'updated_at' => now()
                ]);
            
            // Update approval request if exists
            if ($leaveRequest->approval_request_id) {
                DB::table('approval_requests')
                    ->where('id', $leaveRequest->approval_request_id)
                    ->update([
                        'status' => 'rejected',
                        'updated_at' => now()
                    ]);
            }
            
            // Send notification to approver(s) if request was approved
            if (in_array($leaveRequest->status, ['supervisor_approved', 'approved'])) {
                $approvalRequest = DB::table('approval_requests')
                    ->where('id', $leaveRequest->approval_request_id)
                    ->first();
                    
                // Notify supervisor approver
                if ($approvalRequest && $approvalRequest->approver_id) {
                    DB::table('notifications')->insert([
                        'user_id' => $approvalRequest->approver_id,
                        'type' => 'leave_cancelled',
                        'message' => "Permohonan izin/cuti dari {$user->nama_lengkap} untuk periode {$leaveRequest->date_from} - {$leaveRequest->date_to} telah dibatalkan.",
                        'url' => config('app.url') . '/home',
                        'is_read' => 0,
                        'approval_id' => $leaveRequest->approval_request_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                
                // Notify HRD approver if HRD approved
                if ($leaveRequest->status === 'approved' && $approvalRequest && $approvalRequest->hrd_approver_id) {
                    DB::table('notifications')->insert([
                        'user_id' => $approvalRequest->hrd_approver_id,
                        'type' => 'leave_cancelled',
                        'message' => "Permohonan izin/cuti dari {$user->nama_lengkap} untuk periode {$leaveRequest->date_from} - {$leaveRequest->date_to} telah dibatalkan.",
                        'url' => config('app.url') . '/home',
                        'is_read' => 0,
                        'approval_id' => $leaveRequest->approval_request_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            
            \Log::info('Leave request cancelled', [
                'user_id' => $user->id,
                'leave_request_id' => $id,
                'previous_status' => $leaveRequest->status,
                'cancellation_reason' => $request->get('reason', 'Dibatalkan oleh user')
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Permohonan izin/cuti berhasil dibatalkan'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error cancelling leave request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membatalkan permohonan'
            ], 500);
        }
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
     * Get user's leave requests for the date range
     */
    private function getUserLeaveRequests($userId, $startDate, $endDate)
    {
        $leaveRequests = DB::table('absent_requests')
            ->join('leave_types', 'absent_requests.leave_type_id', '=', 'leave_types.id')
            ->where('absent_requests.user_id', $userId)
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('absent_requests.date_from', [$startDate, $endDate])
                      ->orWhereBetween('absent_requests.date_to', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('absent_requests.date_from', '<=', $startDate)
                            ->where('absent_requests.date_to', '>=', $endDate);
                      });
            })
            // Show all statuses: pending, supervisor_approved, approved, rejected, cancelled
            ->whereIn('absent_requests.status', ['pending', 'supervisor_approved', 'approved', 'rejected', 'cancelled'])
            ->select([
                'absent_requests.id',
                'absent_requests.date_from',
                'absent_requests.date_to',
                'absent_requests.reason',
                'absent_requests.status',
                'absent_requests.created_at',
                'leave_types.name as leave_type_name'
            ])
            ->orderBy('absent_requests.created_at', 'desc')
            ->get();
            
        return $leaveRequests;
    }

    /**
     * Get PH (Public Holiday) data for user in date range
     */
    private function getPHData($userId, $startDate, $endDate)
    {
        // Get holiday attendance compensations for this user in the period
        $compensations = DB::table('holiday_attendance_compensations')
            ->leftJoin('tbl_kalender_perusahaan', 'holiday_attendance_compensations.holiday_date', '=', 'tbl_kalender_perusahaan.tgl_libur')
            ->where('holiday_attendance_compensations.user_id', $userId)
            ->whereBetween('holiday_attendance_compensations.holiday_date', [$startDate, $endDate])
            ->whereIn('holiday_attendance_compensations.status', ['approved', 'used'])
            ->select([
                'holiday_attendance_compensations.id',
                'holiday_attendance_compensations.holiday_date',
                'holiday_attendance_compensations.compensation_type',
                'holiday_attendance_compensations.compensation_amount',
                'holiday_attendance_compensations.compensation_description',
                'holiday_attendance_compensations.status',
                'holiday_attendance_compensations.created_at',
                'tbl_kalender_perusahaan.keterangan as holiday_name'
            ])
            ->orderBy('holiday_attendance_compensations.holiday_date', 'desc')
            ->get();

        // Calculate totals
        $totalDays = 0;
        $totalBonus = 0;
        $extraOffDays = 0;

        foreach ($compensations as $compensation) {
            if ($compensation->compensation_type === 'extra_off') {
                $extraOffDays += $compensation->compensation_amount;
                $totalDays += $compensation->compensation_amount;
            } else if ($compensation->compensation_type === 'bonus') {
                $totalBonus += $compensation->compensation_amount;
                $totalDays += 1; // Each bonus compensation counts as 1 PH day
            }
        }

        return [
            'compensations' => $compensations,
            'total_days' => $totalDays,
            'total_bonus' => $totalBonus,
            'extra_off_days' => $extraOffDays,
            'bonus_days' => $totalDays - $extraOffDays
        ];
    }

    /**
     * Get Extra Off data for user in date range
     */
    private function getExtraOffData($userId, $startDate, $endDate)
    {
        // Get current balance
        $balance = DB::table('extra_off_balance')
            ->where('user_id', $userId)
            ->first();

        // Get transactions for the period
        $transactions = DB::table('extra_off_transactions')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'approved')
            ->select([
                'id',
                'transaction_type',
                'amount',
                'source_type',
                'source_date',
                'description',
                'created_at'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate earned and used amounts for the period
        $earnedAmount = $transactions->where('transaction_type', 'earned')->sum('amount');
        $usedAmount = $transactions->where('transaction_type', 'used')->sum('amount');

        return [
            'current_balance' => $balance ? $balance->balance : 0,
            'transactions' => $transactions,
            'period_earned' => $earnedAmount,
            'period_used' => $usedAmount,
            'period_net' => $earnedAmount - $usedAmount
        ];
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
        
        // ✅ VALIDASI: Jika user bukan dari outlet 1 (head office), paksa outlet_id sesuai outlet user
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outletId = $user->id_outlet;
            \Log::info('User outlet restriction applied for absent report page', [
                'user_id' => $user->id,
                'user_outlet' => $user->id_outlet,
                'forced_outlet_id' => $outletId
            ]);
        }
        
        // Get outlets for filter
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->get();
            
        // Get divisions for filter
        $divisions = DB::table('tbl_data_divisi')
            ->where('status', 'A')
            ->orderBy('nama_divisi')
            ->get();
        
        return Inertia::render('Attendance/Report', [
            'outlets' => $outlets,
            'divisions' => $divisions,
            'user' => $user,
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
        
        // ✅ VALIDASI: Jika user bukan dari outlet 1 (head office), paksa outlet_id sesuai outlet user
        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outletId = $user->id_outlet;
            \Log::info('User outlet restriction applied for absent report', [
                'user_id' => $user->id,
                'user_outlet' => $user->id_outlet,
                'forced_outlet_id' => $outletId
            ]);
        }
        
        
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
                'tbl_data_outlet.nama_outlet as outlet_name',
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
        
        // ✅ VALIDASI: Jika user bukan dari outlet 1 (head office), paksa outlet_id sesuai outlet user
        $user = auth()->user();
        if ($user && $user->id_outlet && $user->id_outlet != 1) {
            $outletId = $user->id_outlet;
            \Log::info('User outlet restriction applied for absent report export', [
                'user_id' => $user->id,
                'user_outlet' => $user->id_outlet,
                'forced_outlet_id' => $outletId
            ]);
        }
        
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
                'tbl_data_outlet.nama_outlet as outlet_name',
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

    /**
     * Get correction requests for a specific user
     */
private function getCorrectionRequests($userId, $startDate, $endDate)
    {
        try {
            $corrections = DB::table('schedule_attendance_correction_approvals as saca')
                ->leftJoin('users as approver', 'saca.approved_by', '=', 'approver.id')
                ->leftJoin('users as requester', 'saca.requested_by', '=', 'requester.id')
                ->leftJoin('tbl_data_outlet', 'saca.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('saca.user_id', $userId) // user_id = user yang correction-nya diajukan untuk
                ->whereBetween('saca.tanggal', [$startDate, $endDate])
                ->select([
                    'saca.id',
                    'saca.type',
                    'saca.tanggal',
                    'saca.old_value',
                    'saca.new_value',
                    'saca.reason',
                    'saca.status',
                    'saca.created_at',
                    'saca.approved_at',
                    'saca.rejection_reason',
                    'approver.nama_lengkap as approved_by_name',
                    'requester.nama_lengkap as requested_by_name',
                    'tbl_data_outlet.nama_outlet'
                ])
                ->orderBy('saca.created_at', 'desc')
                ->get();

            // Clean data to prevent JSON parsing errors
            $corrections = $corrections->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'tanggal' => $item->tanggal,
                    'old_value' => $item->old_value ?: '',
                    'new_value' => $item->new_value ?: '',
                    'reason' => $item->reason ?: '',
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                    'approved_at' => $item->approved_at,
                    'rejection_reason' => $item->rejection_reason ?: '',
                    'approved_by_name' => $item->approved_by_name ?: '',
                    'requested_by_name' => $item->requested_by_name ?: '',
                    'nama_outlet' => $item->nama_outlet ?: ''
                ];
            });

            return $corrections;
        } catch (\Exception $e) {
            \Log::error('Error getting correction requests: ' . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Get attendance data for mobile app (API endpoint)
     */
    public function getAttendanceDataApi(Request $request)
    {
        $user = auth()->user();
        
        // Get date range from request or default to payroll period
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $startDate = date('Y-m-d', strtotime("$tahun-$bulan-26 -1 month"));
        $endDate = date('Y-m-d', strtotime("$tahun-$bulan-25"));
        
        // Get user's work schedule for the date range
        $workSchedules = $this->getWorkSchedules($user->id, $startDate, $endDate);
        
        // Get attendance summary
        $attendanceSummary = $this->getAttendanceSummary($user->id, $startDate, $endDate);
        
        // Get attendance data with first in and last out
        $attendanceData = $this->getAttendanceDataWithFirstInLastOut($user->id, $startDate, $endDate);
        
        // Format calendar data
        $calendar = [];
        foreach ($workSchedules as $schedule) {
            $attendanceInfo = $attendanceData[$schedule->schedule_date] ?? null;
            
            if (!isset($calendar[$schedule->schedule_date])) {
                $calendar[$schedule->schedule_date] = [];
            }
            
            $calendar[$schedule->schedule_date][] = [
                'user_id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'shift_id' => $schedule->shift_id,
                'shift_name' => $schedule->shift_name,
                'time_start' => $schedule->start_time,
                'time_end' => $schedule->end_time,
                'first_in' => $attendanceInfo['first_in'] ?? null,
                'last_out' => $attendanceInfo['last_out'] ?? null,
                'telat' => $attendanceInfo['telat'] ?? 0,
                'lembur' => $attendanceInfo['lembur'] ?? 0,
                'has_attendance' => $attendanceInfo ? true : false,
                'has_no_checkout' => $attendanceInfo['has_no_checkout'] ?? false,
            ];
        }
        
        // Get holidays for the date range
        $holidays = DB::table('tbl_kalender_perusahaan')
            ->whereBetween('tgl_libur', [$startDate, $endDate])
            ->select('tgl_libur as date', 'keterangan as name')
            ->get();
        
        // Get approved absent requests
        $approvedAbsents = $this->getApprovedAbsentRequests($user->id, $startDate, $endDate);
        
        // Get user's leave requests
        $userLeaveRequests = $this->getUserLeaveRequests($user->id, $startDate, $endDate);
        
        // Get active leave types
        $leaveTypes = LeaveType::active()
            ->select('id', 'name', 'max_days', 'requires_document', 'description')
            ->orderBy('name')
            ->get();
        
        // Get PH and Extra Off data
        $phData = $this->getPHData($user->id, $startDate, $endDate);
        $extraOffData = $this->getExtraOffData($user->id, $startDate, $endDate);
        
        // Get correction requests
        $correctionRequests = $this->getCorrectionRequests($user->id, $startDate, $endDate);
        
        return response()->json([
            'success' => true,
            'attendanceSummary' => $attendanceSummary,
            'calendar' => $calendar,
            'holidays' => $holidays,
            'approvedAbsents' => $approvedAbsents,
            'userLeaveRequests' => $userLeaveRequests,
            'leaveTypes' => $leaveTypes,
            'phData' => $phData,
            'extraOffData' => $extraOffData,
            'correctionRequests' => $correctionRequests,
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
                'cuti' => $user->cuti ?? 0,
            ]
        ]);
    }
}
