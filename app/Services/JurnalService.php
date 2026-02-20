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
     * Create jurnal from Non Food Payment
     *
     * @param \App\Models\NonFoodPayment $payment
     * @return array Array of created Jurnal entries
     * @throws \Exception
     */
    public function createFromNonFoodPayment($payment)
    {
        // Ensure payment is loaded
        if (!$payment) {
            throw new \Exception('Payment not provided');
        }

        // Credit COA should come from payment.coa_id if provided
        $coaCredit = $payment->coa_id ?? null;
        if (!$coaCredit) {
            Log::warning('NonFoodPayment missing coa_id, skipping jurnal creation', ['payment_id' => $payment->id]);
            return [];
        }

        // Build payments array: use outlet breakdown if available, otherwise single payment
        $payments = [];
        if ($payment->paymentOutlets && $payment->paymentOutlets->count() > 0) {
            foreach ($payment->paymentOutlets as $po) {
                $payments[] = (object)[
                    'amount' => $po->amount ?? 0,
                    'bank_id' => $po->bank_id ?? null,
                    'payment_type' => $payment->payment_method ?? null,
                    'payment_code' => strtoupper($payment->payment_method ?? ''),
                    'outlet_id' => $po->outlet_id ?? null,
                ];
            }
        } else {
            // Try to determine outlet for single-payment cases:
            $singleOutletId = null;
            // 1) If linked to a PR with outlet
            try {
                if ($payment->purchaseRequisition && isset($payment->purchaseRequisition->outlet_id)) {
                    $singleOutletId = $payment->purchaseRequisition->outlet_id;
                }
            } catch (\Exception $e) {
                // ignore
            }

            // 2) If not found, try PO items mapping (some systems store outlet per PO) - skip here

            // 3) If still not found and bank_id present, use bank's outlet
            if (empty($singleOutletId) && !empty($payment->bank_id)) {
                try {
                    $bank = BankAccount::find($payment->bank_id);
                    if ($bank && !empty($bank->outlet_id)) {
                        $singleOutletId = $bank->outlet_id;
                    }
                } catch (\Exception $e) {
                    // ignore
                }
            }

            $payments[] = (object)[
                'amount' => $payment->amount ?? 0,
                'bank_id' => $payment->bank_id ?? null,
                'payment_type' => $payment->payment_method ?? null,
                'payment_code' => strtoupper($payment->payment_method ?? ''),
                'outlet_id' => $singleOutletId,
            ];
        }

        $totalPayment = collect($payments)->sum('amount');
        $grandTotal = $payment->amount ?? 0;
        if (abs($totalPayment - $grandTotal) > 0.01) {
            Log::warning('Total payment parts not equal to payment amount', ['payment_id' => $payment->id, 'total_parts' => $totalPayment, 'amount' => $grandTotal]);
        }

        // Generate no jurnal
        $noJurnal = Jurnal::generateNoJurnal();
        $tanggal = $payment->payment_date ? date('Y-m-d', strtotime($payment->payment_date)) : date('Y-m-d');
        $keterangan = "Non Food Payment: " . ($payment->payment_number ?? $payment->id ?? 'N/A');

        return DB::transaction(function() use ($payment, $payments, $noJurnal, $tanggal, $keterangan, $coaCredit, $grandTotal) {
            $jurnalEntries = [];
            $totalDebit = 0;

            foreach ($payments as $p) {
                $coaDebit = $this->getCoaDebitFromPayment($p);
                if (!$coaDebit) {
                    Log::error('COA Debit not found for NonFoodPayment part', ['payment_id' => $payment->id, 'bank_id' => $p->bank_id, 'payment_type' => $p->payment_type]);
                    throw new \Exception('COA Debit not found for payment part');
                }

                $amount = $p->amount ?? 0;
                $totalDebit += $amount;
                // Determine outlet_id for this jurnal line: prefer payment part outlet_id
                $jurnalOutletId = $p->outlet_id ?? null;

                // If still null, try direct linked PR outlet
                if (empty($jurnalOutletId)) {
                    try {
                        if ($payment->purchaseRequisition && isset($payment->purchaseRequisition->outlet_id)) {
                            $jurnalOutletId = $payment->purchaseRequisition->outlet_id;
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                // If still null, try purchaseOrderOps -> source PR -> outlet
                if (empty($jurnalOutletId)) {
                    try {
                        if ($payment->purchaseOrderOps && $payment->purchaseOrderOps->source_type === 'purchase_requisition_ops' && $payment->purchaseOrderOps->source_id) {
                            $pr = \App\Models\PurchaseRequisition::find($payment->purchaseOrderOps->source_id);
                            if ($pr && isset($pr->outlet_id)) {
                                $jurnalOutletId = $pr->outlet_id;
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                // If still null and bank_id set, use bank's outlet
                if (empty($jurnalOutletId) && !empty($p->bank_id)) {
                    try {
                        $bank = BankAccount::find($p->bank_id);
                        if ($bank && !empty($bank->outlet_id)) {
                            $jurnalOutletId = $bank->outlet_id;
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                // If still null, fallback to default outlet (id_outlet = 1)
                if (empty($jurnalOutletId)) {
                    $jurnalOutletId = 1;
                    Log::warning('NonFoodPayment outlet not found, fallback to default outlet', [
                        'payment_id' => $payment->id,
                        'fallback_outlet_id' => $jurnalOutletId,
                        'payment_type' => $p->payment_type ?? null,
                    ]);
                }

                $jurnal = Jurnal::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $tanggal,
                    'keterangan' => $keterangan,
                    'coa_debit_id' => $coaDebit,
                    'coa_kredit_id' => $coaCredit,
                    'jumlah_debit' => $amount,
                    'jumlah_kredit' => $amount,
                    'outlet_id' => $jurnalOutletId,
                    'reference_type' => 'non_food_payment',
                    'reference_id' => $payment->id,
                    'status' => 'posted',
                    'created_by' => auth()->id() ?? 1,
                ]);

                // Log created jurnal entry for debugging
                Log::info('Jurnal entry created', [
                    'jurnal_id' => $jurnal->id,
                    'no_jurnal' => $noJurnal,
                    'coa_debit_id' => $jurnal->coa_debit_id,
                    'coa_kredit_id' => $jurnal->coa_kredit_id,
                    'jumlah_debit' => $jurnal->jumlah_debit,
                    'jumlah_kredit' => $jurnal->jumlah_kredit,
                    'outlet_id' => $jurnal->outlet_id,
                    'reference_id' => $jurnal->reference_id
                ]);

                $jurnalEntries[] = $jurnal;

                $jg = JurnalGlobal::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $tanggal,
                    'keterangan' => $keterangan,
                    'coa_debit_id' => $coaDebit,
                    'coa_kredit_id' => $coaCredit,
                    'jumlah_debit' => $amount,
                    'jumlah_kredit' => $amount,
                    'outlet_id' => $jurnalOutletId,
                    'source_module' => 'non_food_payment',
                    'source_id' => $payment->id,
                    'reference_type' => 'non_food_payment',
                    'reference_id' => $payment->id,
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id() ?? 1,
                    'created_by' => auth()->id() ?? 1,
                ]);

                // Log jurnal_global created
                Log::info('JurnalGlobal entry created', [
                    'jurnal_global_id' => $jg->id,
                    'no_jurnal' => $noJurnal,
                    'coa_debit_id' => $jg->coa_debit_id,
                    'coa_kredit_id' => $jg->coa_kredit_id,
                    'jumlah_debit' => $jg->jumlah_debit,
                    'jumlah_kredit' => $jg->jumlah_kredit,
                    'outlet_id' => $jg->outlet_id,
                    'reference_id' => $jg->reference_id
                ]);
            }

            if (abs($totalDebit - $grandTotal) > 0.01) {
                Log::warning('Total debit jurnal not equal to payment amount for NonFoodPayment', ['payment_id' => $payment->id, 'total_debit' => $totalDebit, 'amount' => $grandTotal]);
            }

            Log::info('Jurnal created for NonFoodPayment', ['payment_id' => $payment->id, 'no_jurnal' => $noJurnal, 'entries' => count($jurnalEntries)]);

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
        
        // 7. Investor (potong profit)
        if (stripos($paymentCode, 'INVESTOR') !== false || 
            stripos($paymentType, 'INVESTOR') !== false) {
            return $this->getCoaPiutangInvestor();
        }
        
        // 8. Outlet City Ledger (dibebankan ke karyawan, sama seperti Officer Check)
        if (stripos($paymentCode, 'CITY') !== false || 
            stripos($paymentCode, 'LEDGER') !== false ||
            stripos($paymentCode, 'CITY_LEDGER') !== false ||
            stripos($paymentType, 'CITY') !== false ||
            stripos($paymentType, 'LEDGER') !== false) {
            return $this->getCoaPiutangCityLedger();
        }
        
        // 9. Fallback: Kas Tunai jika tidak dikenali
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
     * Get COA Piutang Investor
     * 
     * @return int|null COA ID
     */
    private function getCoaPiutangInvestor()
    {
        // Cari by name (karena code bisa berubah)
        // Investor: potong profit, jadi ini adalah piutang yang akan dipotong dari profit investor
        $coa = ChartOfAccount::where(function($query) {
                $query->where('name', 'LIKE', '%Piutang Investor%')
                      ->orWhere('name', 'LIKE', '%Investor%')
                      ->orWhere('name', 'LIKE', '%Hutang Investor%');
            })
            ->whereIn('type', ['Asset', 'Liability']) // Bisa Asset atau Liability tergantung perspektif
            ->where('is_active', 1)
            ->first();
        
        if (!$coa) {
            Log::error('COA Piutang Investor tidak ditemukan. Pastikan COA dengan name mengandung "Piutang Investor" atau "Investor" sudah dibuat.');
        }
        
        return $coa->id ?? null;
    }
    
    /**
     * Get COA Piutang City Ledger
     * 
     * @return int|null COA ID
     */
    private function getCoaPiutangCityLedger()
    {
        // Cari by name (karena code bisa berubah)
        // City Ledger: dibebankan ke karyawan, sama seperti Officer Check
        $coa = ChartOfAccount::where(function($query) {
                $query->where('name', 'LIKE', '%Piutang City Ledger%')
                      ->orWhere('name', 'LIKE', '%City Ledger%')
                      ->orWhere('name', 'LIKE', '%Piutang Karyawan%');
            })
            ->where('type', 'Asset')
            ->where('is_active', 1)
            ->first();
        
        if (!$coa) {
            Log::error('COA Piutang City Ledger tidak ditemukan. Pastikan COA dengan name mengandung "Piutang City Ledger" atau "City Ledger" sudah dibuat.');
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
    
    /**
     * Create jurnal entries from Food Payment
     * 
     * @param \App\Models\FoodPayment $payment
     * @return array
     */
    public function createFromFoodPayment($payment)
    {
        // Ensure payment is loaded with outletPayments relationship
        if (!$payment) {
            throw new \Exception('Payment not provided');
        }

        // Load outlet payments if not already loaded
        if (!$payment->relationLoaded('paymentOutlets')) {
            $payment->load('paymentOutlets');
        }

        // Build payments array from outlet payments
        $payments = [];
        if ($payment->paymentOutlets && $payment->paymentOutlets->count() > 0) {
            foreach ($payment->paymentOutlets as $op) {
                $payments[] = (object)[
                    'amount' => $op->amount ?? 0,
                    'bank_id' => $op->bank_id ?? null,
                    'coa_id' => $op->coa_id ?? null,
                    'payment_type' => $payment->payment_type ?? null,
                    'payment_code' => strtoupper($payment->payment_type ?? ''),
                    'outlet_id' => $op->outlet_id ?? null,
                    'warehouse_id' => $op->warehouse_id ?? null,
                    'location_key' => $op->location_key ?? null,
                ];
            }
        } else {
            // Fallback to single payment if no outlet payments
            $payments[] = (object)[
                'amount' => $payment->total ?? 0,
                'bank_id' => $payment->bank_id ?? null,
                'coa_id' => null, // No COA for single payment
                'payment_type' => $payment->payment_type ?? null,
                'payment_code' => strtoupper($payment->payment_type ?? ''),
                'outlet_id' => null,
                'warehouse_id' => null,
                'location_key' => null,
            ];
        }

        $totalPayment = collect($payments)->sum('amount');
        $grandTotal = $payment->total ?? 0;
        if (abs($totalPayment - $grandTotal) > 0.01) {
            Log::warning('Total payment parts not equal to payment amount', [
                'payment_id' => $payment->id,
                'total_parts' => $totalPayment,
                'amount' => $grandTotal
            ]);
        }

        // Generate no jurnal
        $noJurnal = Jurnal::generateNoJurnal();
        $tanggal = $payment->date ? date('Y-m-d', strtotime($payment->date)) : date('Y-m-d');
        $keterangan = "Food Payment: " . ($payment->number ?? 'N/A');

        return DB::transaction(function() use ($payment, $payments, $noJurnal, $tanggal, $keterangan, $grandTotal) {
            $jurnalEntries = [];
            $totalDebit = 0;

            foreach ($payments as $p) {
                // Get COA Debit from payment type/bank
                $coaDebit = $this->getCoaDebitFromPayment($p);
                if (!$coaDebit) {
                    Log::error('COA Debit not found for FoodPayment part', [
                        'payment_id' => $payment->id,
                        'bank_id' => $p->bank_id,
                        'payment_type' => $p->payment_type
                    ]);
                    throw new \Exception('COA Debit not found for payment part');
                }

                // Get COA Credit - use per-location COA if provided, otherwise skip this entry
                $coaCredit = $p->coa_id ?? null;
                if (!$coaCredit) {
                    Log::warning('FoodPayment location missing coa_id, skipping jurnal for this location', [
                        'payment_id' => $payment->id,
                        'location_key' => $p->location_key
                    ]);
                    continue;
                }

                $amount = $p->amount ?? 0;
                $totalDebit += $amount;
                $jurnalOutletId = $p->outlet_id ?? null;
                $jurnalWarehouseId = $p->warehouse_id ?? null;

                // Create Jurnal entry
                $jurnal = Jurnal::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $tanggal,
                    'keterangan' => $keterangan,
                    'coa_debit_id' => $coaDebit,
                    'coa_kredit_id' => $coaCredit,
                    'jumlah_debit' => $amount,
                    'jumlah_kredit' => $amount,
                    'outlet_id' => $jurnalOutletId,
                    'warehouse_id' => $jurnalWarehouseId,
                    'reference_type' => 'food_payment',
                    'reference_id' => $payment->id,
                    'status' => 'posted',
                    'created_by' => auth()->id() ?? 1,
                ]);

                Log::info('Jurnal entry created for FoodPayment', [
                    'jurnal_id' => $jurnal->id,
                    'no_jurnal' => $noJurnal,
                    'coa_debit_id' => $jurnal->coa_debit_id,
                    'coa_kredit_id' => $jurnal->coa_kredit_id,
                    'jumlah_debit' => $jurnal->jumlah_debit,
                    'jumlah_kredit' => $jurnal->jumlah_kredit,
                    'outlet_id' => $jurnal->outlet_id,
                    'reference_id' => $jurnal->reference_id
                ]);

                $jurnalEntries[] = $jurnal;

                // Create JurnalGlobal entry
                $jg = JurnalGlobal::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $tanggal,
                    'keterangan' => $keterangan,
                    'coa_debit_id' => $coaDebit,
                    'coa_kredit_id' => $coaCredit,
                    'jumlah_debit' => $amount,
                    'jumlah_kredit' => $amount,
                    'outlet_id' => $jurnalOutletId,
                    'warehouse_id' => $jurnalWarehouseId,
                    'source_module' => 'food_payment',
                    'source_id' => $payment->id,
                    'reference_type' => 'food_payment',
                    'reference_id' => $payment->id,
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => auth()->id() ?? 1,
                    'created_by' => auth()->id() ?? 1,
                ]);

                Log::info('JurnalGlobal entry created for FoodPayment', [
                    'jurnal_global_id' => $jg->id,
                    'no_jurnal' => $noJurnal,
                    'coa_debit_id' => $jg->coa_debit_id,
                    'coa_kredit_id' => $jg->coa_kredit_id,
                    'jumlah_debit' => $jg->jumlah_debit,
                    'jumlah_kredit' => $jg->jumlah_kredit,
                    'outlet_id' => $jg->outlet_id,
                    'reference_id' => $jg->reference_id
                ]);
            }

            if (count($jurnalEntries) === 0) {
                Log::warning('No jurnal entries created for FoodPayment (all locations missing COA)', [
                    'payment_id' => $payment->id
                ]);
            }

            if (abs($totalDebit - $grandTotal) > 0.01 && count($jurnalEntries) > 0) {
                Log::warning('Total debit jurnal not equal to payment amount for FoodPayment', [
                    'payment_id' => $payment->id,
                    'total_debit' => $totalDebit,
                    'amount' => $grandTotal
                ]);
            }

            Log::info('Jurnal created for FoodPayment', [
                'payment_id' => $payment->id,
                'no_jurnal' => $noJurnal,
                'entries' => count($jurnalEntries)
            ]);

            return $jurnalEntries;
        });
    }
}
