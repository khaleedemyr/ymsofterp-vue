<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\ExtraOffBalance;
use App\Models\ExtraOffTransaction;

class ExtraOffService
{
    /**
     * Get user's extra off balance
     * 
     * @param int $userId
     * @return ExtraOffBalance|null
     */
    public function getUserBalance($userId)
    {
        return ExtraOffBalance::where('user_id', $userId)->first();
    }

    /**
     * Get user's extra off transactions
     * 
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserTransactions($userId, $limit = 10)
    {
        return ExtraOffTransaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Detect employees who worked without scheduled shift
     * 
     * @param string $date Date in Y-m-d format (optional, defaults to yesterday)
     * @return array
     */
    public function detectUnscheduledWork($date = null)
    {
        if (!$date) {
            $date = Carbon::yesterday()->format('Y-m-d');
        }

        $results = [
            'date' => $date,
            'detected' => 0,
            'processed' => 0,
            'overtime_processed' => 0,
            'errors' => []
        ];

        try {
            // Query to detect employees who worked without shift
            // Kriteria: TIDAK ADA SHIFT tapi ADA ATTENDANCE
            $unscheduledWorkers = DB::select("
                SELECT 
                    u.id as user_id,
                    u.nama_lengkap,
                    u.nik,
                    DATE(a.scan_date) as work_date,
                    COUNT(*) as attendance_count
                FROM att_log a
                INNER JOIN tbl_data_outlet o ON a.sn = o.sn
                INNER JOIN user_pins up ON a.pin = up.pin AND o.id_outlet = up.outlet_id
                INNER JOIN users u ON up.user_id = u.id
                LEFT JOIN user_shifts us ON u.id = us.user_id 
                    AND DATE(a.scan_date) = DATE(us.tanggal)
                WHERE 
                    a.inoutmode = 1
                    AND DATE(a.scan_date) = ?
                    AND (us.id IS NULL OR us.shift_id IS NULL)  -- TIDAK ADA SHIFT atau SHIFT = NULL (OFF)
                    AND u.status = 'A'
                    AND NOT EXISTS (
                        SELECT 1 FROM extra_off_transactions eot 
                        WHERE eot.user_id = u.id 
                        AND eot.source_date = DATE(a.scan_date)
                        AND eot.source_type IN ('unscheduled_work', 'overtime_work')
                        AND eot.transaction_type = 'earned'
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM holiday_attendance_compensations hac 
                        WHERE hac.user_id = u.id 
                        AND hac.holiday_date = DATE(a.scan_date)
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM tbl_kalender_perusahaan kp 
                        WHERE kp.tgl_libur = DATE(a.scan_date)
                    )
                GROUP BY u.id, DATE(a.scan_date)
                ORDER BY DATE(a.scan_date) DESC, u.id
            ", [$date]);

            $results['detected'] = count($unscheduledWorkers);
            
            // Log detection results for debugging
            \Log::info('Extra Off Detection Results', [
                'date' => $date,
                'detected_count' => $results['detected'],
                'detected_workers' => $unscheduledWorkers
            ]);

            // Process each detected worker
            foreach ($unscheduledWorkers as $worker) {
                try {
                    // Calculate work hours and time details for this employee on this date
                    $workDetails = $this->calculateWorkHoursWithDetails($worker->user_id, $worker->work_date);
                    
                    if ($workDetails['hours'] > 8) {
                        // Extra off: work > 8 hours
                        $this->giveExtraOffForUnscheduledWork(
                            $worker->user_id,
                            $worker->work_date,
                            $worker->nama_lengkap,
                            $workDetails['hours'],
                            $workDetails['checkin_time'],
                            $workDetails['checkout_time']
                        );
                        $results['processed']++;
                    } else if ($workDetails['hours'] > 0) {
                        // Overtime: work <= 8 hours
                        $this->giveOvertimeForUnscheduledWork(
                            $worker->user_id,
                            $worker->work_date,
                            $worker->nama_lengkap,
                            $workDetails['hours'],
                            $workDetails['checkin_time'],
                            $workDetails['checkout_time']
                        );
                        $results['overtime_processed']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'user_id' => $worker->user_id,
                        'nama' => $worker->nama_lengkap,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = [
                'error' => 'System error: ' . $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Update existing transaction descriptions to include work time details
     * This method should be run once to update existing transactions
     */
    public function updateExistingTransactionDescriptions()
    {
        $results = [
            'updated' => 0,
            'errors' => []
        ];

        try {
            // Get all unscheduled work transactions that need updating
            $transactions = DB::table('extra_off_transactions')
                ->where('source_type', 'unscheduled_work')
                ->where('transaction_type', 'earned')
                ->where('description', 'like', '%kerja tanpa shift di tanggal%')
                ->where('description', 'not like', '%jam % - %')
                ->get();

            foreach ($transactions as $transaction) {
                try {
                    // Extract date from description
                    if (preg_match('/kerja tanpa shift di tanggal (\d{4}-\d{2}-\d{2})/', $transaction->description, $matches)) {
                        $workDate = $matches[1];
                        
                        // Get work details for this date
                        $workDetails = $this->calculateWorkHoursWithDetails($transaction->user_id, $workDate);
                        
                        if ($workDetails['hours'] > 0) {
                            // Format new description
                            $timeInfo = '';
                            if ($workDetails['checkin_time'] && $workDetails['checkout_time']) {
                                $timeInfo = " (jam {$workDetails['checkin_time']} - {$workDetails['checkout_time']}, " . number_format(floor($workDetails['hours'] * 100) / 100, 2) . " jam)";
                            } else {
                                $timeInfo = " (" . number_format(floor($workDetails['hours'] * 100) / 100, 2) . " jam)";
                            }

                            // Update transaction description
                            DB::table('extra_off_transactions')
                                ->where('id', $transaction->id)
                                ->update([
                                    'description' => "Extra off dari kerja tanpa shift di tanggal {$workDate}{$timeInfo}",
                                    'updated_at' => now()
                                ]);

                            $results['updated']++;
                        }
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Also update overtime transactions
            $overtimeTransactions = DB::table('extra_off_transactions')
                ->where('source_type', 'overtime_work')
                ->where('transaction_type', 'earned')
                ->where('description', 'like', '%kerja tanpa shift di tanggal%')
                ->where('description', 'not like', '%jam % - %')
                ->get();

            foreach ($overtimeTransactions as $transaction) {
                try {
                    // Extract date from description
                    if (preg_match('/kerja tanpa shift di tanggal (\d{4}-\d{2}-\d{2})/', $transaction->description, $matches)) {
                        $workDate = $matches[1];
                        
                        // Get work details for this date
                        $workDetails = $this->calculateWorkHoursWithDetails($transaction->user_id, $workDate);
                        
                        if ($workDetails['hours'] > 0) {
                            // Format new description
                            $timeInfo = '';
                            if ($workDetails['checkin_time'] && $workDetails['checkout_time']) {
                                $timeInfo = " (jam {$workDetails['checkin_time']} - {$workDetails['checkout_time']}, " . number_format(floor($workDetails['hours'] * 100) / 100, 2) . " jam)";
                            } else {
                                $timeInfo = " (" . number_format(floor($workDetails['hours'] * 100) / 100, 2) . " jam)";
                            }

                            // Update transaction description
                            DB::table('extra_off_transactions')
                                ->where('id', $transaction->id)
                                ->update([
                                    'description' => "Lembur dari kerja tanpa shift di tanggal {$workDate}{$timeInfo}",
                                    'updated_at' => now()
                                ]);

                            $results['updated']++;
                        }
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = [
                'error' => 'System error: ' . $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Calculate work hours for an employee on a specific date
     * Using the same logic as AttendanceReportController
     * 
     * @param int $userId
     * @param string $workDate
     * @return float Work hours (decimal)
     */
    public function calculateWorkHours($userId, $workDate)
    {
        // Get all attendance data for this user on this date
        $attendanceData = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->where('up.user_id', $userId)
            ->where(DB::raw('DATE(a.scan_date)'), $workDate)
            ->select('a.scan_date', 'a.inoutmode')
            ->orderBy('a.scan_date')
            ->get();

        if ($attendanceData->isEmpty()) {
            return 0;
        }

        // Process scans to determine first checkin and last checkout
        $scans = $attendanceData->sortBy('scan_date');
        $inScans = $scans->where('inoutmode', 1);
        $outScans = $scans->where('inoutmode', 2);

        $firstCheckin = $inScans->first();
        if (!$firstCheckin) {
            return 0; // No checkin, no work hours
        }

        $lastCheckout = null;
        $isCrossDay = false;

        // Find last checkout on the same day
        $sameDayOuts = $outScans->where('scan_date', '>', $firstCheckin->scan_date);
        if ($sameDayOuts->isNotEmpty()) {
            $lastCheckout = $sameDayOuts->last();
            $isCrossDay = false;
        } else {
            // Check for checkout on next day (cross-day work)
            $nextDay = date('Y-m-d', strtotime($workDate . ' +1 day'));
            $nextDayAttendance = DB::table('att_log as a')
                ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
                ->join('user_pins as up', function($q) {
                    $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                })
                ->where('up.user_id', $userId)
                ->where(DB::raw('DATE(a.scan_date)'), $nextDay)
                ->where('a.inoutmode', 2)
                ->select('a.scan_date')
                ->orderBy('a.scan_date')
                ->first();

            if ($nextDayAttendance) {
                $lastCheckout = (object)['scan_date' => $nextDayAttendance->scan_date];
                $isCrossDay = true;
            }
        }

        if (!$lastCheckout) {
            return 0; // No checkout, no work hours
        }

        // Calculate work hours
        $checkinTime = strtotime($firstCheckin->scan_date);
        $checkoutTime = strtotime($lastCheckout->scan_date);
        $workSeconds = $checkoutTime - $checkinTime;
        $workHours = $workSeconds / 3600; // Convert to hours

        \Log::info('Work hours calculation', [
            'user_id' => $userId,
            'work_date' => $workDate,
            'first_checkin' => $firstCheckin->scan_date,
            'last_checkout' => $lastCheckout->scan_date,
            'is_cross_day' => $isCrossDay,
            'work_hours' => $workHours
        ]);

        return $workHours;
    }

    /**
     * Calculate work hours with detailed time information for an employee on a specific date
     * 
     * @param int $userId
     * @param string $workDate
     * @return array Work details with hours, checkin_time, and checkout_time
     */
    public function calculateWorkHoursWithDetails($userId, $workDate)
    {
        // Get all attendance data for this user on this date
        $attendanceData = DB::table('att_log as a')
            ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
            ->join('user_pins as up', function($q) {
                $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
            })
            ->where('up.user_id', $userId)
            ->where(DB::raw('DATE(a.scan_date)'), $workDate)
            ->select('a.scan_date', 'a.inoutmode')
            ->orderBy('a.scan_date')
            ->get();

        if ($attendanceData->isEmpty()) {
            return [
                'hours' => 0,
                'checkin_time' => null,
                'checkout_time' => null
            ];
        }

        // Process scans to determine first checkin and last checkout
        $scans = $attendanceData->sortBy('scan_date');
        $inScans = $scans->where('inoutmode', 1);
        $outScans = $scans->where('inoutmode', 2);

        $firstCheckin = $inScans->first();
        if (!$firstCheckin) {
            return [
                'hours' => 0,
                'checkin_time' => null,
                'checkout_time' => null
            ];
        }

        $lastCheckout = null;
        $isCrossDay = false;

        // Find last checkout on the same day
        $sameDayOuts = $outScans->where('scan_date', '>', $firstCheckin->scan_date);
        if ($sameDayOuts->isNotEmpty()) {
            $lastCheckout = $sameDayOuts->last();
            $isCrossDay = false;
        } else {
            // Check for checkout on next day (cross-day work)
            $nextDay = date('Y-m-d', strtotime($workDate . ' +1 day'));
            $nextDayAttendance = DB::table('att_log as a')
                ->join('tbl_data_outlet as o', 'a.sn', '=', 'o.sn')
                ->join('user_pins as up', function($q) {
                    $q->on('a.pin', '=', 'up.pin')->on('o.id_outlet', '=', 'up.outlet_id');
                })
                ->where('up.user_id', $userId)
                ->where(DB::raw('DATE(a.scan_date)'), $nextDay)
                ->where('a.inoutmode', 2)
                ->select('a.scan_date')
                ->orderBy('a.scan_date')
                ->first();

            if ($nextDayAttendance) {
                $lastCheckout = (object)['scan_date' => $nextDayAttendance->scan_date];
                $isCrossDay = true;
            }
        }

        if (!$lastCheckout) {
            return [
                'hours' => 0,
                'checkin_time' => date('H:i', strtotime($firstCheckin->scan_date)),
                'checkout_time' => null
            ];
        }

        // Calculate work hours
        $checkinTime = strtotime($firstCheckin->scan_date);
        $checkoutTime = strtotime($lastCheckout->scan_date);
        $workSeconds = $checkoutTime - $checkinTime;
        $workHours = $workSeconds / 3600; // Convert to hours

        \Log::info('Work hours calculation with details', [
            'user_id' => $userId,
            'work_date' => $workDate,
            'first_checkin' => $firstCheckin->scan_date,
            'last_checkout' => $lastCheckout->scan_date,
            'is_cross_day' => $isCrossDay,
            'work_hours' => $workHours
        ]);

        return [
            'hours' => $workHours,
            'checkin_time' => date('H:i', strtotime($firstCheckin->scan_date)),
            'checkout_time' => date('H:i', strtotime($lastCheckout->scan_date))
        ];
    }

    /**
     * Give extra off for unscheduled work (>8 hours)
     * 
     * @param int $userId
     * @param string $workDate
     * @param string $employeeName
     * @param float $workHours
     * @param string $checkinTime
     * @param string $checkoutTime
     * @return void
     */
    public function giveExtraOffForUnscheduledWork($userId, $workDate, $employeeName, $workHours = null, $checkinTime = null, $checkoutTime = null)
    {
        // Format time information
        $timeInfo = '';
        if ($checkinTime && $checkoutTime) {
            $timeInfo = " (jam {$checkinTime} - {$checkoutTime}, " . number_format(floor($workHours * 100) / 100, 2) . " jam)";
        } elseif ($workHours) {
            $timeInfo = " (" . number_format(floor($workHours * 100) / 100, 2) . " jam)";
        }

        // Create transaction record
        $transaction = ExtraOffTransaction::create([
            'user_id' => $userId,
            'transaction_type' => 'earned',
            'amount' => 1,
            'source_type' => 'unscheduled_work',
            'source_date' => $workDate,
            'description' => "Extra off dari kerja tanpa shift di tanggal {$workDate}{$timeInfo}",
            'status' => 'approved'
        ]);

        // Update or create balance
        $balance = ExtraOffBalance::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0]
        );
        
        $balance->increment('balance', 1);

        // Log the action
        \Log::info("Extra off given for unscheduled work", [
            'user_id' => $userId,
            'employee_name' => $employeeName,
            'work_date' => $workDate,
            'work_hours' => $workHours,
            'transaction_id' => $transaction->id
        ]);
    }

    /**
     * Give overtime for unscheduled work (≤8 hours)
     * 
     * @param int $userId
     * @param string $workDate
     * @param string $employeeName
     * @param float $workHours
     * @return void
     */
    public function giveOvertimeForUnscheduledWork($userId, $workDate, $employeeName, $workHours, $checkinTime = null, $checkoutTime = null)
    {
        // Format time information
        $timeInfo = '';
        if ($checkinTime && $checkoutTime) {
            $timeInfo = " (jam {$checkinTime} - {$checkoutTime}, " . number_format(floor($workHours * 100) / 100, 2) . " jam)";
        } else {
            $timeInfo = " (" . number_format(floor($workHours * 100) / 100, 2) . " jam)";
        }

        // Create transaction record for overtime (not extra off)
        $transaction = ExtraOffTransaction::create([
            'user_id' => $userId,
            'transaction_type' => 'earned',
            'amount' => 0, // No extra off balance, just record the overtime
            'source_type' => 'overtime_work',
            'source_date' => $workDate,
            'description' => "Lembur dari kerja tanpa shift di tanggal {$workDate}{$timeInfo}",
            'status' => 'approved'
        ]);

        // Log the action
        \Log::info("Overtime recorded for unscheduled work", [
            'user_id' => $userId,
            'employee_name' => $employeeName,
            'work_date' => $workDate,
            'work_hours' => $workHours,
            'transaction_id' => $transaction->id
        ]);
    }

    /**
     * Use extra off day
     * 
     * @param int $userId
     * @param string $useDate
     * @param string $reason
     * @return bool
     */
    public function useExtraOffDay($userId, $useDate, $reason = null)
    {
        $balance = $this->getUserBalance($userId);
        
        if (!$balance || !$balance->hasBalance()) {
            throw new \Exception('Insufficient extra off balance');
        }

        // Create transaction record
        $transaction = ExtraOffTransaction::create([
            'user_id' => $userId,
            'transaction_type' => 'used',
            'amount' => -1,
            'source_type' => 'manual_adjustment',
            'used_date' => $useDate,
            'description' => $reason ?? "Extra off digunakan pada tanggal {$useDate}",
            'status' => 'approved'
        ]);

        // Update balance
        $balance->decrement('balance', 1);

        \Log::info("Extra off day used", [
            'user_id' => $userId,
            'use_date' => $useDate,
            'transaction_id' => $transaction->id,
            'reason' => $reason
        ]);

        return true;
    }

    /**
     * Manual adjustment of extra off balance
     * 
     * @param int $userId
     * @param int $amount
     * @param string $reason
     * @param int $approvedBy
     * @return bool
     */
    public function adjustBalance($userId, $amount, $reason, $approvedBy = null)
    {
        if ($amount == 0) {
            throw new \Exception('Amount cannot be zero');
        }

        $balance = ExtraOffBalance::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0]
        );

        $transactionType = $amount > 0 ? 'earned' : 'used';
        $description = $amount > 0 
            ? "Penambahan {$amount} hari extra off: {$reason}"
            : "Pengurangan " . abs($amount) . " hari extra off: {$reason}";

        // Create transaction record
        $transaction = ExtraOffTransaction::create([
            'user_id' => $userId,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'source_type' => 'manual_adjustment',
            'description' => $description,
            'status' => 'approved',
            'approved_by' => $approvedBy ?? auth()->id()
        ]);

        // Update balance
        $balance->increment('balance', $amount);

        \Log::info("Extra off balance adjusted", [
            'user_id' => $userId,
            'amount' => $amount,
            'reason' => $reason,
            'approved_by' => $approvedBy,
            'transaction_id' => $transaction->id
        ]);

        return true;
    }

    /**
     * Get extra off statistics
     * 
     * @return array
     */
    public function getStatistics()
    {
        $totalUsers = ExtraOffBalance::count();
        $totalBalance = ExtraOffBalance::sum('balance');
        $totalTransactions = ExtraOffTransaction::count();
        $pendingTransactions = ExtraOffTransaction::where('status', 'pending')->count();

        return [
            'total_users' => $totalUsers,
            'total_balance' => $totalBalance,
            'total_transactions' => $totalTransactions,
            'pending_transactions' => $pendingTransactions,
            'average_balance' => $totalUsers > 0 ? round($totalBalance / $totalUsers, 2) : 0
        ];
    }

    /**
     * Initialize balance for all active users
     * 
     * @return int Number of users initialized
     */
    public function initializeBalances()
    {
        $activeUsers = User::where('status', 'A')->get();
        $initialized = 0;

        foreach ($activeUsers as $user) {
            ExtraOffBalance::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );
            $initialized++;
        }

        return $initialized;
    }
}
