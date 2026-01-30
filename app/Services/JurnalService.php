<?php

namespace App\Services;

use App\Models\Jurnal;
use App\Models\JurnalGlobal;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JurnalService
{
    /**
     * Create jurnal from POS order
     * 
     * @param object $order Order data
     * @param \Illuminate\Support\Collection|array $payments Collection of payment data
     * @param int|null $outletId Outlet ID
     * @return array Array of created Jurnal entries
     * @throws \Exception
     */
    public function createFromPosOrder($order, $payments, $outletId = null)
    {
        // Validasi: Total payment harus = grand_total (dengan toleransi 0.01 untuk rounding)
        $totalPayment = collect($payments)->sum('amount');
        $grandTotal = $order->grand_total ?? 0;
        
        if (abs($totalPayment - $grandTotal) > 0.01) {
            Log::warning('Total payment tidak sama dengan grand_total', [
                'order_id' => $order->id ?? null,
                'order_nomor' => $order->nomor ?? null,
                'total_payment' => $totalPayment,
                'grand_total' => $grandTotal,
                'difference' => abs($totalPayment - $grandTotal)
            ]);
            // Tidak throw exception, hanya log warning (karena mungkin ada rounding)
        }
        
        // Check if jurnal already exists untuk order ini
        $existingJurnal = Jurnal::where('reference_type', 'pos_order')
            ->where('reference_id', $order->id ?? null)
            ->first();
        
        if ($existingJurnal) {
            Log::info('Jurnal already exists for POS order, skipping', [
                'order_id' => $order->id ?? null,
                'existing_jurnal_id' => $existingJurnal->id
            ]);
            return []; // Return empty array, jurnal sudah ada
        }
        
        // Generate no jurnal
        $noJurnal = Jurnal::generateNoJurnal();
        $tanggal = isset($order->created_at) ? date('Y-m-d', strtotime($order->created_at)) : date('Y-m-d');
        $keterangan = "Order POS: " . ($order->nomor ?? $order->id ?? 'N/A');
        
        // Get COA Pendapatan (kredit)
        $coaPendapatan = $this->getCoaPendapatan();
        
        if (!$coaPendapatan) {
            throw new \Exception("COA Pendapatan Penjualan tidak ditemukan. Pastikan COA sudah dibuat.");
        }
        
        // Wrap dalam transaction agar insert ke jurnal dan jurnal_global atomic
        return DB::transaction(function() use ($order, $payments, $outletId, $noJurnal, $tanggal, $keterangan, $coaPendapatan, $grandTotal) {
            // Create multiple debit entries (1 per payment)
            $jurnalEntries = [];
            $totalDebit = 0;
            
            foreach ($payments as $payment) {
                $coaDebit = $this->getCoaDebitFromPayment($payment);
                
                if (!$coaDebit) {
                    Log::error('COA Debit tidak ditemukan untuk payment', [
                        'payment_id' => $payment->id ?? null,
                        'payment_code' => $payment->payment_code ?? null,
                        'payment_type' => $payment->payment_type ?? null,
                        'bank_id' => $payment->bank_id ?? null
                    ]);
                    throw new \Exception("COA Debit tidak ditemukan untuk payment type: " . ($payment->payment_code ?? 'UNKNOWN'));
                }
                
                $amount = $payment->amount ?? 0;
                $totalDebit += $amount;
                
                // Create jurnal entry
                $jurnal = Jurnal::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $tanggal,
                    'keterangan' => $keterangan,
                    'coa_debit_id' => $coaDebit,
                    'coa_kredit_id' => $coaPendapatan,
                    'jumlah_debit' => $amount,
                    'jumlah_kredit' => $amount, // Balance dengan debit (double entry)
                    'outlet_id' => $outletId,
                    'reference_type' => 'pos_order',
                    'reference_id' => $order->id ?? null,
                    'status' => 'posted', // Auto-post karena order sudah paid
                    'created_by' => auth()->id() ?? 1,
                ]);
                
                $jurnalEntries[] = $jurnal;
                
                // Insert ke jurnal_global juga (harus berhasil, jika gagal akan rollback semua)
                JurnalGlobal::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $tanggal,
                    'keterangan' => $keterangan,
                    'coa_debit_id' => $coaDebit,
                    'coa_kredit_id' => $coaPendapatan,
                    'jumlah_debit' => $amount,
                    'jumlah_kredit' => $amount,
                    'outlet_id' => $outletId,
                    'source_module' => 'pos_order',
                    'source_id' => $order->id ?? null,
                    'reference_type' => 'pos_order',
                    'reference_id' => $order->id ?? null,
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id() ?? 1,
                    'created_by' => auth()->id() ?? 1,
                ]);
            }
            
            // Validasi: Total debit harus = grand_total (dengan toleransi)
            if (abs($totalDebit - $grandTotal) > 0.01) {
                Log::warning('Total debit jurnal tidak sama dengan grand_total', [
                    'order_id' => $order->id ?? null,
                    'total_debit' => $totalDebit,
                    'grand_total' => $grandTotal,
                    'difference' => abs($totalDebit - $grandTotal)
                ]);
            }
            
            Log::info('Jurnal created successfully for POS order', [
                'order_id' => $order->id ?? null,
                'order_nomor' => $order->nomor ?? null,
                'no_jurnal' => $noJurnal,
                'jurnal_entries_count' => count($jurnalEntries),
                'total_debit' => $totalDebit,
                'grand_total' => $grandTotal
            ]);
            
            return $jurnalEntries;
        });
    }
    
    /**
     * Get COA Debit berdasarkan payment type
     * 
     * @param object $payment Payment data
     * @return int|null COA ID
     */
    private function getCoaDebitFromPayment($payment)
    {
        // 1. Check payment_code untuk determine type
        $paymentCode = strtoupper($payment->payment_code ?? '');
        $paymentType = strtoupper($payment->payment_type ?? '');
        
        // 2. Cash/Tunai
        if (in_array($paymentCode, ['CASH', 'TUNAI', 'KAS']) || 
            in_array($paymentType, ['CASH', 'TUNAI', 'KAS'])) {
            return $this->getCoaKasTunai();
        }
        
        // 3. Bank (jika ada bank_id, ambil dari bank_accounts.coa_id)
        if (!empty($payment->bank_id)) {
            $bankAccount = BankAccount::find($payment->bank_id);
            if ($bankAccount && $bankAccount->coa_id) {
                return $bankAccount->coa_id;
            }
            
            // Jika bank account tidak punya coa_id, log warning
            Log::warning('Bank account tidak punya coa_id', [
                'bank_id' => $payment->bank_id,
                'payment_code' => $paymentCode
            ]);
        }
        
        // 4. Perjamuan
        if (stripos($paymentCode, 'PERJAMUAN') !== false || 
            stripos($paymentCode, 'ENTERTAINMENT') !== false ||
            stripos($paymentType, 'PERJAMUAN') !== false) {
            return $this->getCoaPerjamuan();
        }
        
        // 5. Guest Satisfaction
        if (stripos($paymentCode, 'GUEST') !== false || 
            stripos($paymentCode, 'SATISFACTION') !== false ||
            stripos($paymentCode, 'COMPLIMENTARY') !== false ||
            stripos($paymentCode, 'COMP') !== false ||
            stripos($paymentType, 'GUEST') !== false) {
            return $this->getCoaGuestSatisfaction();
        }
        
        // 6. Officer Check
        if (stripos($paymentCode, 'OFFICER') !== false || 
            stripos($paymentCode, 'OC') !== false ||
            stripos($paymentType, 'OFFICER') !== false) {
            return $this->getCoaPiutangOfficerCheck();
        }
        
        // 7. Fallback: Kas Tunai jika tidak dikenali
        Log::warning('Payment code tidak dikenali, menggunakan COA Kas Tunai', [
            'payment_code' => $paymentCode,
            'payment_type' => $paymentType,
            'payment_id' => $payment->id ?? null,
            'bank_id' => $payment->bank_id ?? null
        ]);
        return $this->getCoaKasTunai();
    }
    
    /**
     * Get COA Kas Tunai
     * 
     * @return int|null COA ID
     */
    private function getCoaKasTunai()
    {
        // Cari by name (karena code bisa berubah)
        $coa = ChartOfAccount::where(function($query) {
                $query->where('name', 'LIKE', '%Kas Tunai%')
                      ->orWhere('name', 'LIKE', '%Kas%Tunai%')
                      ->orWhere('name', 'LIKE', '%Cash%');
            })
            ->where('type', 'Asset')
            ->where('is_active', 1)
            ->first();
        
        if (!$coa) {
            Log::error('COA Kas Tunai tidak ditemukan. Pastikan COA dengan name mengandung "Kas Tunai" sudah dibuat.');
        }
        
        return $coa->id ?? null;
    }
    
    /**
     * Get COA Perjamuan
     * 
     * @return int|null COA ID
     */
    private function getCoaPerjamuan()
    {
        // Cari by name (karena code bisa berubah, bisa Asset atau Expense)
        $coa = ChartOfAccount::where(function($query) {
                $query->where('name', 'LIKE', '%Perjamuan%')
                      ->orWhere('name', 'LIKE', '%Entertainment%');
            })
            ->whereIn('type', ['Asset', 'Expense'])
            ->where('is_active', 1)
            ->first();
        
        if (!$coa) {
            Log::error('COA Perjamuan tidak ditemukan. Pastikan COA dengan name mengandung "Perjamuan" atau "Entertainment" sudah dibuat.');
        }
        
        return $coa->id ?? null;
    }
    
    /**
     * Get COA Guest Satisfaction
     * 
     * @return int|null COA ID
     */
    private function getCoaGuestSatisfaction()
    {
        // Cari by name (karena code bisa berubah, bisa Asset atau Expense)
        $coa = ChartOfAccount::where(function($query) {
                $query->where('name', 'LIKE', '%Guest Satisfaction%')
                      ->orWhere('name', 'LIKE', '%Complimentary%')
                      ->orWhere('name', 'LIKE', '%Guest%Satisfaction%');
            })
            ->whereIn('type', ['Asset', 'Expense'])
            ->where('is_active', 1)
            ->first();
        
        if (!$coa) {
            Log::error('COA Guest Satisfaction tidak ditemukan. Pastikan COA dengan name mengandung "Guest Satisfaction" atau "Complimentary" sudah dibuat.');
        }
        
        return $coa->id ?? null;
    }
    
    /**
     * Get COA Piutang Officer Check
     * 
     * @return int|null COA ID
     */
    private function getCoaPiutangOfficerCheck()
    {
        // Cari by name (karena code bisa berubah)
        $coa = ChartOfAccount::where(function($query) {
                $query->where('name', 'LIKE', '%Piutang Officer%')
                      ->orWhere('name', 'LIKE', '%Officer Check%');
            })
            ->where('type', 'Asset')
            ->where('is_active', 1)
            ->first();
        
        if (!$coa) {
            Log::error('COA Piutang Officer Check tidak ditemukan. Pastikan COA dengan name mengandung "Piutang Officer" atau "Officer Check" sudah dibuat.');
        }
        
        return $coa->id ?? null;
    }
    
    /**
     * Get COA Pendapatan Penjualan
     * 
     * @return int|null COA ID
     */
    private function getCoaPendapatan()
    {
        // Cari by code 4001 atau name (prioritas: code dulu)
        $coa = ChartOfAccount::where(function($query) {
                $query->where('code', '4001')  // Code utama: 4001
                      ->orWhere('code', '4.1.1.01')  // Fallback: code lama
                      ->orWhere('name', 'LIKE', '%Pendapatan Penjualan%')
                      ->orWhere('name', 'LIKE', '%Revenue%')
                      ->orWhere('name', 'LIKE', '%Penjualan POS%');
            })
            ->where('type', 'Revenue')
            ->where('is_active', 1)
            ->orderByRaw("CASE WHEN code = '4001' THEN 0 ELSE 1 END")  // Prioritaskan code 4001
            ->first();
        
        if (!$coa) {
            Log::error('COA Pendapatan Penjualan tidak ditemukan. Pastikan COA dengan code 4001 sudah dibuat.');
        }
        
        return $coa->id ?? null;
    }
    
    /**
     * Rollback jurnal saat void order
     * 
     * @param string $orderId Order ID
     * @return bool Success status
     */
    public function rollbackFromPosOrder($orderId)
    {
        // Find all jurnal entries dengan reference ke order ini
        $jurnals = Jurnal::where('reference_type', 'pos_order')
            ->where('reference_id', $orderId)
            ->get();
        
        if ($jurnals->isEmpty()) {
            Log::info('Tidak ada jurnal untuk di-rollback', [
                'order_id' => $orderId
            ]);
            return false; // Tidak ada jurnal untuk di-rollback
        }
        
        DB::beginTransaction();
        try {
            // Delete dari jurnal_global juga
            $deletedGlobal = JurnalGlobal::where('reference_type', 'pos_order')
                ->where('reference_id', $orderId)
                ->delete();
            
            // Delete dari jurnal
            $deletedJurnal = Jurnal::where('reference_type', 'pos_order')
                ->where('reference_id', $orderId)
                ->delete();
            
            DB::commit();
            
            Log::info('Jurnal rolled back successfully', [
                'order_id' => $orderId,
                'deleted_jurnal_count' => $deletedJurnal,
                'deleted_global_count' => $deletedGlobal
            ]);
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rolling back jurnal from POS order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
