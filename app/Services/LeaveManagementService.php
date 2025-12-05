<?php

namespace App\Services;

use App\Models\User;
use App\Models\LeaveTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveManagementService
{
    /**
     * Memberikan cuti bulanan ke semua karyawan aktif
     */
    public function giveMonthlyLeave($year = null, $month = null)
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('n');
        
        Log::info("Starting monthly leave credit process", [
            'year' => $year,
            'month' => $month
        ]);

        try {
            DB::beginTransaction();

            // Ambil semua karyawan aktif
            $activeUsers = User::where('status', 'A')->get();
            
            $processedCount = 0;
            $errors = [];

            foreach ($activeUsers as $user) {
                try {
                    // Cek apakah sudah pernah diberikan cuti untuk bulan ini
                    $existingTransaction = LeaveTransaction::where('user_id', $user->id)
                        ->where('transaction_type', 'monthly_credit')
                        ->where('year', $year)
                        ->where('month', $month)
                        ->first();

                    if ($existingTransaction) {
                        Log::info("User {$user->nama_lengkap} already received monthly leave for {$year}-{$month}");
                        continue;
                    }

                    // Tambahkan 1 hari cuti
                    $newBalance = ($user->cuti ?? 0) + 1;
                    
                    // Update saldo cuti di tabel users
                    $user->update(['cuti' => $newBalance]);

                    // Buat transaksi record
                    LeaveTransaction::create([
                        'user_id' => $user->id,
                        'transaction_type' => 'monthly_credit',
                        'year' => $year,
                        'month' => $month,
                        'amount' => 1,
                        'balance_after' => $newBalance,
                        'description' => "Kredit cuti bulanan {$this->getMonthName($month)} {$year}",
                        'created_by' => null // Otomatis
                    ]);

                    $processedCount++;
                    Log::info("Monthly leave credited for user {$user->nama_lengkap}", [
                        'user_id' => $user->id,
                        'new_balance' => $newBalance
                    ]);

                } catch (\Exception $e) {
                    $errors[] = "Error processing user {$user->nama_lengkap}: " . $e->getMessage();
                    Log::error("Error processing monthly leave for user {$user->id}", [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id
                    ]);
                }
            }

            DB::commit();

            Log::info("Monthly leave credit process completed", [
                'processed_count' => $processedCount,
                'error_count' => count($errors)
            ]);

            return [
                'success' => true,
                'processed_count' => $processedCount,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in monthly leave credit process", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Burning cuti tahun sebelumnya di bulan Maret
     */
    public function burnPreviousYearLeave($currentYear = null)
    {
        $currentYear = $currentYear ?? date('Y');
        $previousYear = $currentYear - 1;
        
        Log::info("Starting leave burning process", [
            'current_year' => $currentYear,
            'previous_year' => $previousYear
        ]);

        try {
            DB::beginTransaction();

            // Ambil semua karyawan aktif
            $activeUsers = User::where('status', 'A')->get();
            
            $processedCount = 0;
            $totalBurned = 0;
            $errors = [];

            foreach ($activeUsers as $user) {
                try {
                    // Hitung sisa cuti tahun sebelumnya
                    $previousYearBalance = $this->calculatePreviousYearBalance($user->id, $previousYear);
                    
                    if ($previousYearBalance <= 0) {
                        Log::info("No previous year leave to burn for user {$user->nama_lengkap}");
                        continue;
                    }

                    // Kurangi saldo cuti
                    $newBalance = max(0, ($user->cuti ?? 0) - $previousYearBalance);
                    
                    // Update saldo cuti di tabel users
                    $user->update(['cuti' => $newBalance]);

                    // Buat transaksi record untuk burning
                    LeaveTransaction::create([
                        'user_id' => $user->id,
                        'transaction_type' => 'burning',
                        'year' => $currentYear,
                        'month' => null,
                        'amount' => -$previousYearBalance,
                        'balance_after' => $newBalance,
                        'description' => "Burning sisa cuti tahun {$previousYear} ({$previousYearBalance} hari)",
                        'created_by' => null // Otomatis
                    ]);

                    $processedCount++;
                    $totalBurned += $previousYearBalance;
                    
                    Log::info("Leave burned for user {$user->nama_lengkap}", [
                        'user_id' => $user->id,
                        'burned_amount' => $previousYearBalance,
                        'new_balance' => $newBalance
                    ]);

                } catch (\Exception $e) {
                    $errors[] = "Error burning leave for user {$user->nama_lengkap}: " . $e->getMessage();
                    Log::error("Error burning leave for user {$user->id}", [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id
                    ]);
                }
            }

            DB::commit();

            Log::info("Leave burning process completed", [
                'processed_count' => $processedCount,
                'total_burned' => $totalBurned,
                'error_count' => count($errors)
            ]);

            return [
                'success' => true,
                'processed_count' => $processedCount,
                'total_burned' => $totalBurned,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in leave burning process", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Hitung sisa cuti tahun sebelumnya
     */
    private function calculatePreviousYearBalance($userId, $previousYear)
    {
        // Hitung total kredit tahun sebelumnya
        $totalCredit = LeaveTransaction::where('user_id', $userId)
            ->where('transaction_type', 'monthly_credit')
            ->where('year', $previousYear)
            ->sum('amount');

        // Hitung total penggunaan tahun sebelumnya
        $totalUsage = LeaveTransaction::where('user_id', $userId)
            ->where('transaction_type', 'leave_usage')
            ->where('year', $previousYear)
            ->sum('amount');

        // Sisa = kredit - penggunaan (amount untuk usage adalah negatif)
        return $totalCredit + $totalUsage; // totalUsage sudah negatif
    }

    /**
     * Manual adjustment cuti
     */
    public function manualAdjustment($userId, $amount, $description, $createdBy = null)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $newBalance = ($user->cuti ?? 0) + $amount;

            // Update saldo cuti di tabel users
            $user->update(['cuti' => $newBalance]);

            // Buat transaksi record
            LeaveTransaction::create([
                'user_id' => $userId,
                'transaction_type' => 'manual_adjustment',
                'year' => date('Y'),
                'month' => date('n'),
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'created_by' => $createdBy
            ]);

            DB::commit();

            return [
                'success' => true,
                'new_balance' => $newBalance
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in manual leave adjustment", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Penggunaan cuti
     */
    public function useLeave($userId, $amount, $description, $createdBy = null)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $currentBalance = $user->cuti ?? 0;

            if ($currentBalance < $amount) {
                throw new \Exception("Saldo cuti tidak mencukupi. Saldo: {$currentBalance}, Dibutuhkan: {$amount}");
            }

            $newBalance = $currentBalance - $amount;

            // Update saldo cuti di tabel users
            $user->update(['cuti' => $newBalance]);

            // Buat transaksi record
            LeaveTransaction::create([
                'user_id' => $userId,
                'transaction_type' => 'leave_usage',
                'year' => date('Y'),
                'month' => date('n'),
                'amount' => -$amount, // Negatif untuk penggunaan
                'balance_after' => $newBalance,
                'description' => $description,
                'created_by' => $createdBy
            ]);

            DB::commit();

            return [
                'success' => true,
                'new_balance' => $newBalance
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in leave usage", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get leave history for user
     */
    public function getLeaveHistory($userId, $year = null)
    {
        $year = $year ?? date('Y');
        
        return LeaveTransaction::where('user_id', $userId)
            ->where('year', $year)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_date' => $transaction->created_at->format('Y-m-d'),
                    'type' => $this->mapTransactionType($transaction->transaction_type),
                    'amount' => $transaction->amount,
                    'current_balance' => $transaction->balance_after,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                    'transaction_type_original' => $transaction->transaction_type
                ];
            });
    }

    /**
     * Map transaction type to frontend format
     */
    private function mapTransactionType($type)
    {
        $mapping = [
            'monthly_credit' => 'credit',
            'leave_usage' => 'usage', 
            'manual_adjustment' => 'adjustment',
            'burning' => 'burning',
            'initial_balance' => 'credit'
        ];

        return $mapping[$type] ?? 'unknown';
    }

    /**
     * Get nama bulan
     */
    private function getMonthName($month)
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $months[$month] ?? 'Unknown';
    }
}
