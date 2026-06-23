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
    public function burnPreviousYearLeave($currentYear = null, $force = false)
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
                    // Cegah burning ganda untuk user+tahun yang sama (kecuali force)
                    if (!$force) {
                        $alreadyBurned = LeaveTransaction::where('user_id', $user->id)
                            ->where('transaction_type', 'burning')
                            ->where('year', $currentYear)
                            ->exists();

                        if ($alreadyBurned) {
                            Log::info("User {$user->nama_lengkap} already burned for year {$currentYear}");
                            continue;
                        }
                    }

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
            ->whereIn('transaction_type', ['monthly_credit', 'initial_balance'])
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
     * Replace saldo cuti ke nilai pasti (bukan delta).
     */
    public function replaceLeaveBalance($userId, $newBalance, $description, $createdBy = null)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($userId);
            $oldBalance = (float) ($user->cuti ?? 0);
            $newBalance = round((float) $newBalance, 2);

            if ($oldBalance === $newBalance) {
                DB::commit();

                return [
                    'success' => true,
                    'skipped' => true,
                    'new_balance' => $newBalance,
                ];
            }

            $user->update(['cuti' => $newBalance]);

            LeaveTransaction::create([
                'user_id' => $userId,
                'transaction_type' => 'manual_adjustment',
                'year' => (int) date('Y'),
                'month' => (int) date('n'),
                'amount' => round($newBalance - $oldBalance, 2),
                'balance_after' => $newBalance,
                'description' => $description,
                'created_by' => $createdBy,
            ]);

            DB::commit();

            return [
                'success' => true,
                'skipped' => false,
                'new_balance' => $newBalance,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error replacing leave balance', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Import replace saldo cuti dari baris Excel (Nama, Outlet, Saldo Cuti).
     */
    public function importBalanceReplace(array $rows, $createdBy = null): array
    {
        if (empty($rows)) {
            return [
                'success' => false,
                'error' => 'File kosong atau tidak dapat dibaca',
                'failed_rows' => [],
                'total_rows' => 0,
                'success_count' => 0,
                'skipped_count' => 0,
                'error_count' => 0,
            ];
        }

        $headerRow = array_shift($rows);
        $columnMap = $this->mapLeaveBalanceImportColumns($headerRow);

        if ($columnMap['nama'] === null || $columnMap['outlet'] === null || $columnMap['saldo'] === null) {
            return [
                'success' => false,
                'error' => 'Header wajib memuat kolom Nama, Outlet, dan Saldo Cuti',
                'failed_rows' => [],
                'total_rows' => 0,
                'success_count' => 0,
                'skipped_count' => 0,
                'error_count' => 0,
            ];
        }

        $lookup = $this->buildActiveUserOutletLookup();
        $successCount = 0;
        $skippedCount = 0;
        $failedRows = [];
        $totalRows = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $nama = trim((string) ($row[$columnMap['nama']] ?? ''));
            $outlet = trim((string) ($row[$columnMap['outlet']] ?? ''));
            $saldoRaw = $row[$columnMap['saldo']] ?? null;

            if ($nama === '' && $outlet === '' && ($saldoRaw === null || $saldoRaw === '')) {
                continue;
            }

            $totalRows++;

            if ($nama === '' || $outlet === '') {
                $this->pushBalanceImportFailure($failedRows, $line, $nama, $outlet, $saldoRaw, 'Nama dan Outlet wajib diisi');
                continue;
            }

            if ($saldoRaw === null || $saldoRaw === '') {
                $this->pushBalanceImportFailure($failedRows, $line, $nama, $outlet, $saldoRaw, 'Saldo Cuti wajib diisi');
                continue;
            }

            if (!is_numeric($saldoRaw)) {
                $this->pushBalanceImportFailure($failedRows, $line, $nama, $outlet, $saldoRaw, 'Saldo Cuti harus angka');
                continue;
            }

            $saldo = round((float) $saldoRaw, 2);
            if ($saldo < 0) {
                $this->pushBalanceImportFailure($failedRows, $line, $nama, $outlet, $saldo, 'Saldo Cuti tidak boleh negatif');
                continue;
            }

            $key = $this->normalizeLeaveBalanceLookupKey($nama, $outlet);
            if (!isset($lookup[$key])) {
                $this->pushBalanceImportFailure(
                    $failedRows,
                    $line,
                    $nama,
                    $outlet,
                    $saldo,
                    "Karyawan tidak ditemukan di sistem (cek ejaan Nama & Outlet)"
                );
                continue;
            }

            if (count($lookup[$key]) > 1) {
                $this->pushBalanceImportFailure(
                    $failedRows,
                    $line,
                    $nama,
                    $outlet,
                    $saldo,
                    'Data karyawan ganda di outlet yang sama, perbaiki master data karyawan'
                );
                continue;
            }

            $userId = $lookup[$key][0];
            $result = $this->replaceLeaveBalance(
                $userId,
                $saldo,
                "Replace saldo cuti via import Excel (baris {$line})",
                $createdBy
            );

            if (!$result['success']) {
                $this->pushBalanceImportFailure(
                    $failedRows,
                    $line,
                    $nama,
                    $outlet,
                    $saldo,
                    $result['error'] ?? 'Gagal memperbarui saldo'
                );
                continue;
            }

            if (!empty($result['skipped'])) {
                $skippedCount++;
            } else {
                $successCount++;
            }
        }

        $errorCount = count($failedRows);
        $hasChanges = $successCount > 0 || $skippedCount > 0;

        return [
            'success' => $hasChanges,
            'success_count' => $successCount,
            'skipped_count' => $skippedCount,
            'error_count' => $errorCount,
            'total_rows' => $totalRows,
            'failed_rows' => $failedRows,
            'errors' => array_map(
                fn ($row) => "Baris {$row['line']}: {$row['reason']}",
                $failedRows
            ),
            'message' => $successCount > 0
                ? "Berhasil memperbarui {$successCount} saldo cuti"
                : ($skippedCount > 0
                    ? "Tidak ada perubahan saldo ({$skippedCount} baris sudah sama)"
                    : 'Tidak ada saldo cuti yang berhasil diperbarui'),
        ];
    }

    private function pushBalanceImportFailure(
        array &$failedRows,
        int $line,
        string $nama,
        string $outlet,
        $saldo,
        string $reason
    ): void {
        $failedRows[] = [
            'line' => $line,
            'nama' => $nama !== '' ? $nama : '-',
            'outlet' => $outlet !== '' ? $outlet : '-',
            'saldo_cuti' => ($saldo === null || $saldo === '') ? '-' : (string) $saldo,
            'reason' => $reason,
        ];
    }

    public function getActiveUsersForBalanceTemplate()
    {
        return User::where('users.status', 'A')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select(
                'users.nama_lengkap',
                'tbl_data_outlet.nama_outlet',
                'users.cuti'
            )
            ->orderBy('tbl_data_outlet.nama_outlet')
            ->orderBy('users.nama_lengkap')
            ->get()
            ->map(function ($user) {
                return [
                    'nama' => $user->nama_lengkap,
                    'outlet' => $user->nama_outlet ?? '',
                    'saldo_cuti' => (float) ($user->cuti ?? 0),
                ];
            });
    }

    private function buildActiveUserOutletLookup(): array
    {
        $users = User::where('users.status', 'A')
            ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
            ->select('users.id', 'users.nama_lengkap', 'tbl_data_outlet.nama_outlet')
            ->get();

        $lookup = [];
        foreach ($users as $user) {
            $key = $this->normalizeLeaveBalanceLookupKey(
                $user->nama_lengkap,
                $user->nama_outlet ?? ''
            );
            $lookup[$key][] = $user->id;
        }

        return $lookup;
    }

    private function normalizeLeaveBalanceLookupKey(string $nama, string $outlet): string
    {
        return mb_strtolower(trim($nama)) . '|' . mb_strtolower(trim($outlet));
    }

    private function mapLeaveBalanceImportColumns(array $headerRow): array
    {
        $map = ['nama' => null, 'outlet' => null, 'saldo' => null];

        foreach ($headerRow as $index => $header) {
            $normalized = mb_strtolower(trim((string) $header));
            $normalized = str_replace(['_', '-'], ' ', $normalized);
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            if (in_array($normalized, ['nama', 'nama lengkap', 'name'], true)) {
                $map['nama'] = $index;
            } elseif (in_array($normalized, ['outlet', 'nama outlet'], true)) {
                $map['outlet'] = $index;
            } elseif (in_array($normalized, ['saldo cuti', 'saldo', 'cuti', 'balance'], true)) {
                $map['saldo'] = $index;
            }
        }

        return $map;
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
