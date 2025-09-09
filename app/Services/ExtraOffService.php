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
            'errors' => []
        ];

        try {
            // Query to detect employees who worked without shift
            $unscheduledWorkers = DB::select("
                SELECT 
                    u.id as user_id,
                    u.nama_lengkap,
                    u.nik,
                    DATE(a.checktime) as work_date,
                    COUNT(*) as attendance_count
                FROM att_log a
                INNER JOIN users u ON a.userid = u.id
                LEFT JOIN user_shifts us ON u.id = us.user_id 
                    AND DATE(a.checktime) = DATE(us.shift_date)
                    AND us.status = 'active'
                WHERE 
                    a.inoutmode = 1
                    AND DATE(a.checktime) = ?
                    AND us.id IS NULL
                    AND u.status = 'A'
                    AND NOT EXISTS (
                        SELECT 1 FROM extra_off_transactions eot 
                        WHERE eot.user_id = u.id 
                        AND eot.source_date = DATE(a.checktime)
                        AND eot.source_type = 'unscheduled_work'
                        AND eot.transaction_type = 'earned'
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM tbl_kalender_perusahaan kp 
                        WHERE kp.tgl_libur = DATE(a.checktime)
                    )
                GROUP BY u.id, DATE(a.checktime)
                ORDER BY a.checktime DESC
            ", [$date]);

            $results['detected'] = count($unscheduledWorkers);

            // Process each detected worker
            foreach ($unscheduledWorkers as $worker) {
                try {
                    $this->giveExtraOffForUnscheduledWork(
                        $worker->user_id,
                        $worker->work_date,
                        $worker->nama_lengkap
                    );
                    $results['processed']++;
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
     * Give extra off for unscheduled work
     * 
     * @param int $userId
     * @param string $workDate
     * @param string $employeeName
     * @return void
     */
    public function giveExtraOffForUnscheduledWork($userId, $workDate, $employeeName)
    {
        // Create transaction record
        $transaction = ExtraOffTransaction::create([
            'user_id' => $userId,
            'transaction_type' => 'earned',
            'amount' => 1,
            'source_type' => 'unscheduled_work',
            'source_date' => $workDate,
            'description' => "Extra off dari kerja tanpa shift di tanggal {$workDate}",
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
