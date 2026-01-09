<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsPointEarning;
use App\Models\MemberAppsMonthlySpending;
use App\Models\PaymentType;
use App\Models\BankBook;
use App\Services\MemberTierService;
use App\Services\BankBookService;
use App\Events\PointEarned;
use App\Events\PointReturned;
use Carbon\Carbon;

class PosOrderController extends Controller
{
    /**
     * Sync order from POS to server pusat
     * This endpoint receives order data from POS and saves it to database
     */
    public function syncOrder(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order' => 'required|array',
                'order.id' => 'required|string',
                'order.nomor' => 'required|string',
                'kode_outlet' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $orderData = $request->input('order');
            $kodeOutlet = $request->input('kode_outlet');

            Log::info('POS Order Sync Request', [
                'order_id' => $orderData['id'] ?? null,
                'kode_outlet' => $kodeOutlet
            ]);

            DB::beginTransaction();

            try {
                // Helper function untuk konversi datetime ke format MySQL
                $convertDateTime = function($dateTime) {
                    if (!$dateTime) {
                        return now()->format('Y-m-d H:i:s');
                    }
                    
                    // Jika sudah format MySQL (YYYY-MM-DD HH:MM:SS), return langsung
                    if (is_string($dateTime) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateTime)) {
                        return $dateTime;
                    }
                    
                    // Jika format ISO 8601 atau format lain, coba parse dengan Carbon
                    try {
                        return Carbon::parse($dateTime)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        Log::warning('Error parsing datetime', [
                            'datetime' => $dateTime,
                            'error' => $e->getMessage()
                        ]);
                        return now()->format('Y-m-d H:i:s');
                    }
                };
                
                // 1. Insert/Update order
                $orderInsert = [
                    'id' => $orderData['id'],
                    'nomor' => $orderData['nomor'],
                    'table' => $orderData['table'] ?? '-',
                    'paid_number' => $orderData['paid_number'] ?? null,
                    'waiters' => $orderData['waiters'] ?? '-',
                    'member_id' => $orderData['member_id'] ?? '',
                    'member_name' => $orderData['member_name'] ?? '',
                    'mode' => $orderData['mode'] ?? null,
                    'pax' => $orderData['pax'] ?? null,
                    'total' => $orderData['total'] ?? 0,
                    'discount' => $orderData['discount'] ?? 0,
                    'cashback' => $orderData['cashback'] ?? 0,
                    'dpp' => $orderData['dpp'] ?? 0,
                    'pb1' => $orderData['pb1'] ?? 0,
                    'service' => $orderData['service'] ?? 0,
                    'grand_total' => $orderData['grand_total'] ?? 0,
                    'status' => $orderData['status'] ?? 'paid',
                    'created_at' => $convertDateTime($orderData['created_at'] ?? null),
                    'updated_at' => $convertDateTime($orderData['updated_at'] ?? null),
                    'joined_tables' => $orderData['joined_tables'] ?? null,
                    'promo_ids' => $orderData['promo_ids'] ?? null,
                    'commfee' => $orderData['commfee'] ?? 0,
                    'rounding' => $orderData['rounding'] ?? 0,
                    'sales_lead' => $orderData['sales_lead'] ?? null,
                    'redeem_amount' => $orderData['redeem_amount'] ?? 0,
                    'manual_discount_amount' => $orderData['manual_discount_amount'] ?? 0,
                    'manual_discount_reason' => $orderData['manual_discount_reason'] ?? null,
                    'voucher_info' => $orderData['voucher_info'] ?? null,
                    'inactive_promo_items' => $orderData['inactive_promo_items'] ?? null,
                    'promo_discount_info' => $orderData['promo_discount_info'] ?? null,
                    'issync' => 1,
                    'kode_outlet' => $kodeOutlet,
                ];

                // Use upsert to handle existing orders (insert or update)
                $updateColumns = [
                    'nomor', 'table', 'paid_number', 'waiters', 'member_id', 'member_name',
                    'mode', 'pax', 'total', 'discount', 'cashback', 'dpp', 'pb1', 'service',
                    'grand_total', 'status', 'updated_at', 'joined_tables', 'promo_ids',
                    'commfee', 'rounding', 'sales_lead', 'redeem_amount',
                    'manual_discount_amount', 'manual_discount_reason', 
                    'voucher_info', 'inactive_promo_items', 'promo_discount_info',
                    'issync', 'kode_outlet'
                ];
                
                DB::table('orders')->upsert(
                    [$orderInsert],
                    ['id'], // Unique key
                    $updateColumns
                );

                Log::info('Order synced', ['order_id' => $orderData['id']]);

                // 2. Insert/Update order_items
                if (isset($orderData['items']) && is_array($orderData['items'])) {
                    // Delete existing items first (to handle updates)
                    DB::table('order_items')->where('order_id', $orderData['id'])->delete();

                    foreach ($orderData['items'] as $item) {
                        DB::table('order_items')->insert([
                            'id' => $item['id'] ?? null,
                            'order_id' => $orderData['id'],
                            'item_id' => $item['item_id'] ?? null,
                            'item_name' => $item['item_name'] ?? '',
                            'qty' => $item['qty'] ?? 0,
                            'price' => $item['price'] ?? 0,
                            'tally' => $item['tally'] ?? null,
                            'modifiers' => $item['modifiers'] ?? null,
                            'notes' => $item['notes'] ?? null,
                            'subtotal' => $item['subtotal'] ?? 0,
                            'created_at' => $convertDateTime($item['created_at'] ?? null),
                            'kode_outlet' => $kodeOutlet,
                        ]);
                    }
                    Log::info('Order items synced', [
                        'order_id' => $orderData['id'],
                        'items_count' => count($orderData['items'])
                    ]);
                }

                // 3. Insert/Update order_promos
                if (isset($orderData['promos']) && is_array($orderData['promos'])) {
                    // Delete existing promos first
                    DB::table('order_promos')->where('order_id', $orderData['id'])->delete();

                    foreach ($orderData['promos'] as $promo) {
                        DB::table('order_promos')->insert([
                            'order_id' => $orderData['id'],
                            'promo_id' => $promo['promo_id'] ?? null,
                            'status' => $promo['status'] ?? 'active', // Include status (active/inactive)
                            'created_at' => $convertDateTime($promo['created_at'] ?? null),
                            'kode_outlet' => $kodeOutlet,
                        ]);
                    }
                    Log::info('Order promos synced', [
                        'order_id' => $orderData['id'],
                        'promos_count' => count($orderData['promos'])
                    ]);
                }

                // 4. Insert/Update order_payment
                if (isset($orderData['payments']) && is_array($orderData['payments'])) {
                    // Delete existing payments first
                    DB::table('order_payment')->where('order_id', $orderData['id'])->delete();

                    // Get outlet_id from kode_outlet (qr_code)
                    $outletId = null;
                    if (!empty($kodeOutlet)) {
                        $outlet = DB::table('tbl_data_outlet')
                            ->where('qr_code', $kodeOutlet)
                            ->first();
                        $outletId = $outlet->id_outlet ?? null;
                    }

                    foreach ($orderData['payments'] as $payment) {
                        // Auto-fill bank_id based on payment_code and outlet
                        $bankId = null;
                        if (!empty($payment['payment_code'])) {
                            // Get payment type by code
                            $paymentType = PaymentType::where('code', $payment['payment_code'])
                                ->where('status', 'active')
                                ->where('is_bank', true)
                                ->first();
                            
                            if ($paymentType) {
                                // Get bank account for this payment type and outlet
                                // Priority: outlet-specific bank > Head Office bank
                                $bankAccount = DB::table('bank_account_payment_type')
                                    ->where('payment_type_id', $paymentType->id)
                                    ->join('bank_accounts', 'bank_account_payment_type.bank_account_id', '=', 'bank_accounts.id')
                                    ->where('bank_accounts.is_active', 1)
                                    ->where(function($query) use ($outletId) {
                                        if ($outletId) {
                                            $query->where('bank_account_payment_type.outlet_id', $outletId)
                                                  ->orWhereNull('bank_account_payment_type.outlet_id');
                                        } else {
                                            $query->whereNull('bank_account_payment_type.outlet_id');
                                        }
                                    })
                                    ->select('bank_accounts.id')
                                    ->orderByRaw('CASE WHEN bank_account_payment_type.outlet_id = ? THEN 0 ELSE 1 END', [$outletId])
                                    ->first();
                                
                                if ($bankAccount) {
                                    $bankId = $bankAccount->id;
                                }
                            }
                        }

                        $paymentDate = $convertDateTime($payment['created_at'] ?? null);
                        
                        // Insert order_payment
                        DB::table('order_payment')->insert([
                            'id' => $payment['id'] ?? null,
                            'order_id' => $orderData['id'],
                            'paid_number' => $payment['paid_number'] ?? null,
                            'payment_type' => $payment['payment_type'] ?? null,
                            'payment_code' => $payment['payment_code'] ?? null,
                            'bank_id' => $bankId,
                            'amount' => $payment['amount'] ?? 0,
                            'card_first4' => $payment['card_first4'] ?? null,
                            'card_last4' => $payment['card_last4'] ?? null,
                            'approval_code' => $payment['approval_code'] ?? null,
                            'created_at' => $paymentDate,
                            'kasir' => $payment['kasir'] ?? '-',
                            'note' => $payment['note'] ?? null,
                            'change' => $payment['change'] ?? 0,
                            'kode_outlet' => $kodeOutlet,
                        ]);

                        // Insert to bank_books if bank_id exists
                        if ($bankId) {
                            try {
                                // Check if already exists to avoid duplicate
                                $existingEntry = BankBook::where('reference_type', 'order_payment')
                                    ->where('reference_id', $payment['id'] ?? null)
                                    ->where('bank_account_id', $bankId)
                                    ->first();
                                
                                if (!$existingEntry) {
                                    // Use BankBookService to create entry (it will handle balance calculation)
                                    // But since we're in a transaction, we'll do it manually
                                    $transactionDate = date('Y-m-d', strtotime($paymentDate));
                                    
                                    // Get last balance for this bank account (before this transaction date)
                                    $lastEntry = BankBook::where('bank_account_id', $bankId)
                                        ->whereDate('transaction_date', '<', $transactionDate)
                                        ->orderBy('transaction_date', 'desc')
                                        ->orderBy('id', 'desc')
                                        ->first();
                                    
                                    // Also check entries on the same date to get the latest balance
                                    $sameDateEntry = BankBook::where('bank_account_id', $bankId)
                                        ->whereDate('transaction_date', $transactionDate)
                                        ->orderBy('id', 'desc')
                                        ->first();
                                    
                                    $currentBalance = $sameDateEntry ? $sameDateEntry->balance : ($lastEntry ? $lastEntry->balance : 0);
                                    $amount = $payment['amount'] ?? 0;
                                    $newBalance = $currentBalance + $amount; // Credit = money coming in
                                    
                                    // Create bank book entry
                                    BankBook::create([
                                        'bank_account_id' => $bankId,
                                        'transaction_date' => $transactionDate,
                                        'transaction_type' => 'credit', // Order payment is money coming in
                                        'amount' => $amount,
                                        'description' => "Order Payment: {$orderData['nomor']} - {$payment['payment_type']}" . 
                                            ($payment['note'] ? " - {$payment['note']}" : ''),
                                        'reference_type' => 'order_payment',
                                        'reference_id' => $payment['id'] ?? null,
                                        'balance' => $newBalance,
                                    ]);
                                    
                                    // Recalculate balance for all entries on the same date and after
                                    BankBook::recalculateBalance($bankId, $transactionDate);
                                }
                            } catch (\Exception $e) {
                                // Log error but don't fail the order sync
                                Log::warning('Failed to create bank book entry for order payment', [
                                    'order_id' => $orderData['id'],
                                    'payment_id' => $payment['id'] ?? null,
                                    'bank_id' => $bankId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                    Log::info('Order payments synced', [
                        'order_id' => $orderData['id'],
                        'payments_count' => count($orderData['payments'])
                    ]);
                }

                // 5. Handle member spending and points (if member exists)
                if (!empty($orderData['member_id'])) {
                    $member = MemberAppsMember::where('member_id', $orderData['member_id'])
                        ->orWhere('id', $orderData['member_id'])
                        ->first();
                    
                    if ($member) {
                        $grandTotal = $orderData['grand_total'] ?? 0;
                        $transactionDate = Carbon::parse($orderData['created_at'] ?? now());
                        $voucherInfo = $orderData['voucher_info'] ?? null;
                        
                        // Check if voucher was used (voucher_info is not null)
                        $hasVoucher = !empty($voucherInfo);
                        
                        // Always record transaction for monthly spending and total spending lifetime
                        // This ensures tier can still increase even with voucher
                        MemberTierService::recordTransaction($member->id, $grandTotal, $transactionDate);
                        
                        Log::info('Member transaction recorded', [
                            'member_id' => $member->id,
                            'grand_total' => $grandTotal,
                            'has_voucher' => $hasVoucher,
                            'transaction_date' => $transactionDate->format('Y-m-d H:i:s')
                        ]);
                        
                        // Only insert point if NO voucher was used
                        if (!$hasVoucher && $grandTotal > 0) {
                            // Calculate points based on member tier
                            $tier = strtolower($member->member_level ?? 'silver');
                            $pointsEarned = 0;
                            
                            // Point earning rate based on tier
                            // Silver: 1 point per Rp 10,000
                            // Loyal: 1.5 points per Rp 10,000
                            // Elite: 2 points per Rp 10,000
                            $earningRate = 1.00; // Default: 1 point per Rp 10,000
                            if ($tier === 'loyal') {
                                $earningRate = 1.50; // 1.5 points per Rp 10,000
                            } elseif ($tier === 'elite') {
                                $earningRate = 2.00; // 2 points per Rp 10,000
                            }
                            
                            // Calculate points: (transaction_amount / 10000) * earning_rate
                            // Handle fractional points for rate 1.5 (Loyal tier)
                            $calculatedPoints = ($grandTotal / 10000) * $earningRate;
                            $pointsEarned = floor($calculatedPoints);
                            $remainder = $calculatedPoints - $pointsEarned;
                            
                            // Get current remainder (if field exists, otherwise 0)
                            $currentRemainder = $member->point_remainder ?? 0;
                            
                            // Add current remainder to new remainder
                            $totalRemainder = $currentRemainder + $remainder;
                            
                            // If total remainder >= 1.0, convert to integer points
                            if ($totalRemainder >= 1.0) {
                                $extraPoints = floor($totalRemainder);
                                $pointsEarned += $extraPoints;
                                $totalRemainder -= $extraPoints;
                            }
                            
                            // Always update point remainder (even if pointsEarned = 0)
                            $member->point_remainder = $totalRemainder;
                            
                            if ($pointsEarned > 0) {
                                // Add points to member
                                $member->just_points = ($member->just_points ?? 0) + $pointsEarned;
                                
                                // Calculate expires_at: 1 year from transaction date
                                $expiresAt = $transactionDate->copy()->addYear();
                                
                                // Create point transaction
                                $pointTransaction = MemberAppsPointTransaction::create([
                                    'member_id' => $member->id,
                                    'transaction_type' => 'earn',
                                    'point_amount' => $pointsEarned,
                                    'transaction_amount' => $grandTotal,
                                    'reference_id' => $orderData['id'],
                                    'channel' => 'dine-in',
                                    'transaction_date' => $transactionDate->toDateString(),
                                    'description' => "Point earning from order {$orderData['nomor']}",
                                    'earning_rate' => $earningRate,
                                    'expires_at' => $expiresAt->toDateString(),
                                    'is_expired' => false,
                                    'created_at' => $transactionDate,
                                    'updated_at' => $transactionDate
                                ]);
                                
                                // Create point earning record (required for expiry tracking)
                                MemberAppsPointEarning::create([
                                    'member_id' => $member->id,
                                    'point_transaction_id' => $pointTransaction->id,
                                    'point_amount' => $pointsEarned,
                                    'remaining_points' => $pointsEarned, // Initially all points are remaining
                                    'earned_at' => $transactionDate->toDateString(),
                                    'expires_at' => $expiresAt->toDateString(),
                                    'is_expired' => false,
                                    'is_fully_redeemed' => false,
                                ]);
                            }
                            
                            // Save member (to update point_remainder, and just_points if pointsEarned > 0)
                            $member->save();
                            
                            if ($pointsEarned > 0) {
                                Log::info('Points earned for member', [
                                    'member_id' => $member->id,
                                    'points_earned' => $pointsEarned,
                                    'tier' => $tier,
                                    'earning_rate' => $earningRate,
                                    'grand_total' => $grandTotal,
                                    'calculated_points' => $calculatedPoints,
                                    'remainder' => $remainder,
                                    'point_remainder_after' => $totalRemainder,
                                    'transaction_id' => $pointTransaction->id ?? null,
                                    'expires_at' => $expiresAt->toDateString() ?? null
                                ]);
                                
                                // Dispatch event for push notification
                                try {
                                    // Get outlet name for notification
                                    $outletName = 'Outlet';
                                    if (!empty($kodeOutlet)) {
                                        try {
                                            $outletData = DB::selectOne(
                                                "SELECT nama_outlet FROM tbl_data_outlet WHERE qr_code = ? LIMIT 1",
                                                [$kodeOutlet]
                                            );
                                            if ($outletData) {
                                                $outletName = $outletData->nama_outlet;
                                            }
                                        } catch (\Exception $e) {
                                            Log::warning('Error getting outlet name for notification in POS', [
                                                'kode_outlet' => $kodeOutlet,
                                                'error' => $e->getMessage()
                                            ]);
                                        }
                                    }
                                    
                                    Log::info('Dispatching PointEarned event from POS', [
                                        'member_id' => $member->id,
                                        'points' => $pointsEarned,
                                        'order_id' => $orderData['id'],
                                        'outlet_name' => $outletName,
                                    ]);
                                    
                                    event(new PointEarned(
                                        $member,
                                        $pointTransaction,
                                        $pointsEarned,
                                        'transaction',
                                        [
                                            'order_id' => $orderData['id'],
                                            'outlet_name' => $outletName,
                                        ]
                                    ));
                                    
                                    Log::info('PointEarned event dispatched successfully from POS', [
                                        'member_id' => $member->id,
                                    ]);
                                } catch (\Exception $e) {
                                    // Log error but don't fail the point earning
                                    Log::error('Error dispatching PointEarned event from POS', [
                                        'member_id' => $member->id,
                                        'error' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString()
                                    ]);
                                }
                            }
                        } else {
                            Log::info('Points not earned - voucher used', [
                                'member_id' => $member->id,
                                'has_voucher' => $hasVoucher,
                                'voucher_info' => $voucherInfo ? 'exists' : 'null'
                            ]);
                        }
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Order synced successfully',
                    'data' => [
                        'order_id' => $orderData['id'],
                        'kode_outlet' => $kodeOutlet
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error syncing order', [
                    'order_id' => $orderData['id'] ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('POS Order Sync Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback member transaction when order is voided
     * This endpoint rolls back:
     * - Total spending
     * - Point transactions
     * - Monthly spending
     * - Challenge progress (if any)
     */
    public function rollbackMemberTransaction(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|string',
                'order_nomor' => 'required|string',
                'member_id' => 'required|string',
                'grand_total' => 'required|numeric',
                'transaction_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $orderId = $request->input('order_id');
            $orderNomor = $request->input('order_nomor');
            $memberId = $request->input('member_id');
            $grandTotal = $request->input('grand_total');
            $transactionDate = $request->input('transaction_date');

            Log::info('Rollback Member Transaction', [
                'order_id' => $orderId,
                'order_nomor' => $orderNomor,
                'member_id' => $memberId,
                'grand_total' => $grandTotal,
                'transaction_date' => $transactionDate
            ]);

            DB::beginTransaction();

            try {
                // Find member
                $member = MemberAppsMember::where('member_id', $memberId)
                    ->orWhere('id', $memberId)
                    ->first();

                if (!$member) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Member not found'
                    ], 404);
                }

                // 1. Rollback total spending (lifetime)
                if ($member->total_spending && $member->total_spending >= $grandTotal) {
                    $member->total_spending = $member->total_spending - $grandTotal;
                    $member->save();
                    Log::info('Rollback total spending', [
                        'member_id' => $memberId,
                        'old_total' => $member->total_spending + $grandTotal,
                        'new_total' => $member->total_spending
                    ]);
                }

                // 2. Rollback point transactions (delete or mark as voided)
                // PENTING: reference_id bisa dalam format:
                // - "order_id" atau "order_nomor" untuk transaction type 'earn'
                // - "serial_code" atau "serial_code|order_id" untuk transaction type 'redeem'
                // CATATAN: Kita tidak menggunakan filter waktu karena bisa terlalu ketat dan melewatkan transaksi
                // Sebagai gantinya, kita hanya mengandalkan reference_id yang sudah cukup spesifik
                
                // Query untuk earn transactions (reference_id = order_id atau order_nomor)
                $earnTransactions = MemberAppsPointTransaction::where('member_id', $member->id)
                    ->where('transaction_type', 'earn')
                    ->where(function($query) use ($orderId, $orderNomor) {
                        $query->where('reference_id', $orderId)
                              ->orWhere('reference_id', $orderNomor);
                    })
                    ->get();
                
                // Query untuk redeem transactions (reference_id bisa "serial_code|order_id" atau "serial_code")
                // Untuk redeem, kita perlu mencari yang mengandung order_id atau order_nomor di akhir reference_id
                $redeemTransactions = MemberAppsPointTransaction::where('member_id', $member->id)
                    ->where('transaction_type', 'redeem')
                    ->where(function($query) use ($orderId, $orderNomor) {
                        // Format: "serial_code|order_id" atau "serial_code|order_nomor"
                        $query->where('reference_id', 'LIKE', '%|' . $orderId)
                              ->orWhere('reference_id', 'LIKE', '%|' . $orderNomor)
                              ->orWhere('reference_id', 'LIKE', '%|' . (string)$orderId)
                              ->orWhere('reference_id', 'LIKE', '%|' . (string)$orderNomor);
                    })
                    ->get();
                
                // Gabungkan semua transaksi
                $pointTransactions = $earnTransactions->merge($redeemTransactions);
                
                Log::info('Found point transactions for rollback', [
                    'member_id' => $member->id,
                    'order_id' => $orderId,
                    'order_id_type' => gettype($orderId),
                    'order_nomor' => $orderNomor,
                    'order_nomor_type' => gettype($orderNomor),
                    'earn_transactions_count' => $earnTransactions->count(),
                    'redeem_transactions_count' => $redeemTransactions->count(),
                    'total_transactions_count' => $pointTransactions->count(),
                    'earn_transactions' => $earnTransactions->map(function($t) {
                        return [
                            'id' => $t->id,
                            'transaction_type' => $t->transaction_type,
                            'point_amount' => $t->point_amount,
                            'reference_id' => $t->reference_id,
                            'created_at' => $t->created_at ? $t->created_at->format('Y-m-d H:i:s') : null,
                        ];
                    })->toArray(),
                    'redeem_transactions' => $redeemTransactions->map(function($t) {
                        return [
                            'id' => $t->id,
                            'transaction_type' => $t->transaction_type,
                            'point_amount' => $t->point_amount,
                            'reference_id' => $t->reference_id,
                            'created_at' => $t->created_at ? $t->created_at->format('Y-m-d H:i:s') : null,
                        ];
                    })->toArray(),
                ]);

                $totalPointsToAddBack = 0; // Points to add back to member (from redemptions)
                $totalPointsToDeduct = 0; // Points to deduct from member (from earnings)
                
                foreach ($pointTransactions as $transaction) {
                    Log::info('Processing point transaction for rollback', [
                        'transaction_id' => $transaction->id,
                        'transaction_type' => $transaction->transaction_type,
                        'point_amount' => $transaction->point_amount,
                        'reference_id' => $transaction->reference_id,
                    ]);
                    
                    // Rollback point redemption from earnings if this is a redemption transaction
                    if ($transaction->transaction_type === 'redeem') {
                        try {
                            // PENTING: point_amount untuk redeem adalah negatif (misal -500)
                            // Kita perlu mengembalikan nilai absolutnya ke just_points member
                            $pointsToReturn = abs($transaction->point_amount);
                            
                            Log::info('Processing redeem transaction rollback', [
                                'point_transaction_id' => $transaction->id,
                                'point_amount' => $transaction->point_amount,
                                'points_to_return' => $pointsToReturn,
                                'member_current_points' => $member->just_points ?? 0,
                            ]);
                            
                            $pointEarningService = new \App\Services\PointEarningService();
                            $rollbackResult = $pointEarningService->rollbackPointRedemptionFromEarnings($transaction->id);
                            
                            if (!$rollbackResult) {
                                Log::warning('Failed to rollback point earnings for redemption in void order', [
                                    'point_transaction_id' => $transaction->id,
                                    'member_id' => $member->id,
                                    'order_id' => $orderId,
                                ]);
                                // Tetap kembalikan point ke member meskipun rollback earnings gagal
                                $totalPointsToAddBack += $pointsToReturn;
                            } else {
                                // PENTING: Kembalikan point ke just_points member karena point sudah dikembalikan ke remaining_points
                                $totalPointsToAddBack += $pointsToReturn;
                                
                                Log::info('Point earnings rolled back for void order', [
                                    'point_transaction_id' => $transaction->id,
                                    'points_returned_to_earnings' => $rollbackResult['points_returned'] ?? 0,
                                    'points_to_return_to_member' => $pointsToReturn,
                                    'details_count' => $rollbackResult['details_count'] ?? 0,
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Error rolling back point earnings for redemption in void order', [
                                'point_transaction_id' => $transaction->id,
                                'member_id' => $member->id,
                                'order_id' => $orderId,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            // Continue with rollback anyway, but still try to return points
                            $pointsToReturn = abs($transaction->point_amount);
                            $totalPointsToAddBack += $pointsToReturn;
                            Log::info('Added points to rollback despite error', [
                                'points_to_return' => $pointsToReturn,
                                'total_points_to_add_back' => $totalPointsToAddBack,
                            ]);
                        }
                    } elseif ($transaction->transaction_type === 'earn') {
                        // For point earning, delete the point earning record
                        try {
                            $pointEarning = MemberAppsPointEarning::where('point_transaction_id', $transaction->id)->first();
                            if ($pointEarning) {
                                $pointEarning->delete();
                                Log::info('Point earning record deleted for void order', [
                                    'point_transaction_id' => $transaction->id,
                                    'point_earning_id' => $pointEarning->id,
                                    'member_id' => $member->id,
                                    'order_id' => $orderId,
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Error deleting point earning record for void order', [
                                'point_transaction_id' => $transaction->id,
                                'member_id' => $member->id,
                                'order_id' => $orderId,
                                'error' => $e->getMessage(),
                            ]);
                            // Continue with rollback anyway
                        }
                        
                        // Untuk earn, point harus dikurangi dari member karena point earning sudah dihapus
                        if ($transaction->point_amount > 0) {
                            $totalPointsToDeduct += $transaction->point_amount;
                        }
                        
                        // Rollback point_remainder yang ditambahkan dari transaksi ini
                        // Hitung ulang remainder dari transaksi yang di-void
                        try {
                            $transactionAmount = $transaction->transaction_amount ?? 0;
                            $earningRate = $transaction->earning_rate ?? 1.00;
                            
                            // Hitung ulang calculated points dan remainder
                            $calculatedPoints = ($transactionAmount / 10000) * $earningRate;
                            $pointsEarned = floor($calculatedPoints);
                            $remainderFromTransaction = $calculatedPoints - $pointsEarned;
                            
                            // Refresh member untuk dapat point_remainder terbaru
                            $member->refresh();
                            $currentRemainder = $member->point_remainder ?? 0;
                            
                            // Kurangi remainder dari point_remainder
                            $newRemainder = $currentRemainder - $remainderFromTransaction;
                            
                            // Jika newRemainder negatif, berarti ada konversi yang terjadi di transaksi berikutnya
                            // Kita perlu kurangi just_points juga
                            if ($newRemainder < 0) {
                                // Ada konversi yang terjadi, kurangi just_points
                                $pointsToDeductFromConversion = abs(floor($newRemainder));
                                $totalPointsToDeduct += $pointsToDeductFromConversion;
                                $newRemainder = $newRemainder + $pointsToDeductFromConversion; // Setelah floor, sisa decimal
                                
                                Log::info('Point remainder rollback resulted in negative, deducting from just_points', [
                                    'member_id' => $member->id,
                                    'transaction_id' => $transaction->id,
                                    'remainder_from_transaction' => $remainderFromTransaction,
                                    'current_remainder' => $currentRemainder,
                                    'new_remainder_before_adjustment' => $currentRemainder - $remainderFromTransaction,
                                    'points_to_deduct_from_conversion' => $pointsToDeductFromConversion,
                                    'new_remainder_after_adjustment' => $newRemainder,
                                ]);
                            }
                            
                            // Update point_remainder (akan disimpan nanti)
                            $member->point_remainder = max(0, $newRemainder); // Pastikan tidak negatif
                            
                            Log::info('Point remainder rolled back for void order', [
                                'member_id' => $member->id,
                                'transaction_id' => $transaction->id,
                                'transaction_amount' => $transactionAmount,
                                'earning_rate' => $earningRate,
                                'calculated_points' => $calculatedPoints,
                                'points_earned' => $pointsEarned,
                                'remainder_from_transaction' => $remainderFromTransaction,
                                'current_remainder' => $currentRemainder,
                                'new_remainder' => $member->point_remainder,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error rolling back point remainder for void order', [
                                'point_transaction_id' => $transaction->id,
                                'member_id' => $member->id,
                                'order_id' => $orderId,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            // Continue with rollback anyway
                        }
                    }
                    
                    // Delete the transaction
                    $transaction->delete();
                }

                // Update member points: add back redemption points, deduct earned points
                // Note: point_remainder sudah di-update di loop sebelumnya untuk setiap earn transaction
                $pointsChanged = false;
                
                // Kembalikan point dari redemption ke member
                if ($totalPointsToAddBack > 0) {
                    $member->just_points = ($member->just_points ?? 0) + $totalPointsToAddBack;
                    $pointsChanged = true;
                    Log::info('Returning redemption points to member', [
                        'member_id' => $memberId,
                        'points_returned' => $totalPointsToAddBack,
                        'new_points' => $member->just_points
                    ]);
                }
                
                // Kurangi point dari earning yang sudah dihapus
                if ($totalPointsToDeduct > 0 && ($member->just_points ?? 0) >= $totalPointsToDeduct) {
                    $member->just_points = ($member->just_points ?? 0) - $totalPointsToDeduct;
                    $pointsChanged = true;
                    Log::info('Deducting earned points from member', [
                        'member_id' => $memberId,
                        'points_deducted' => $totalPointsToDeduct,
                        'new_points' => $member->just_points
                    ]);
                }
                
                // Save member (point_remainder sudah di-update di loop sebelumnya)
                if ($pointsChanged || isset($member->point_remainder)) {
                    $member->save();
                    Log::info('Member points updated after rollback', [
                        'member_id' => $memberId,
                        'points_added_back' => $totalPointsToAddBack,
                        'points_deducted' => $totalPointsToDeduct,
                        'final_points' => $member->just_points,
                        'point_remainder' => $member->point_remainder ?? 0
                    ]);
                    
                    // Send push notification to member about point return
                    try {
                        Log::info('Dispatching PointReturned event from rollback', [
                            'member_id' => $member->id,
                            'points_returned' => $totalPointsToAddBack,
                            'points_deducted' => $totalPointsToDeduct,
                            'order_id' => $orderId,
                            'order_nomor' => $orderNomor,
                        ]);
                        
                        event(new PointReturned(
                            $member,
                            $totalPointsToAddBack,
                            $totalPointsToDeduct,
                            'void_transaction',
                            [
                                'order_id' => $orderId,
                                'order_nomor' => $orderNomor,
                            ]
                        ));
                        
                        Log::info('PointReturned event dispatched successfully from rollback', [
                            'member_id' => $member->id,
                        ]);
                    } catch (\Exception $e) {
                        // Log error but don't fail the rollback
                        Log::error('Error dispatching PointReturned event from rollback', [
                            'member_id' => $member->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                // 3. Rollback monthly spending
                $transactionDateObj = Carbon::parse($transactionDate);
                $year = (int) $transactionDateObj->format('Y');
                $month = (int) $transactionDateObj->format('m');

                $monthlySpending = MemberAppsMonthlySpending::where('member_id', $member->id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->first();

                if ($monthlySpending && $monthlySpending->total_spending >= $grandTotal) {
                    $monthlySpending->total_spending = $monthlySpending->total_spending - $grandTotal;
                    $monthlySpending->transaction_count = max(0, $monthlySpending->transaction_count - 1);
                    $monthlySpending->save();
                    Log::info('Rollback monthly spending', [
                        'member_id' => $memberId,
                        'year' => $year,
                        'month' => $month,
                        'old_spending' => $monthlySpending->total_spending + $grandTotal,
                        'new_spending' => $monthlySpending->total_spending
                    ]);
                }

                // 4. Recalculate tier based on new rolling 12-month spending
                MemberTierService::updateMemberTier($member->id, $transactionDateObj);

                // 5. Rollback challenge progress (if any)
                // Use ChallengeProgressService to properly rollback and recalculate
                Log::info('Starting challenge progress rollback from PosOrderController', [
                    'member_id' => $memberId,
                    'member_db_id' => $member->id,
                    'order_id' => $orderId,
                    'order_nomor' => $orderNomor,
                    'grand_total' => $grandTotal
                ]);
                
                try {
                    $challengeProgressService = new \App\Services\ChallengeProgressService();
                    $rollbackResult = $challengeProgressService->rollbackProgressFromOrder(
                        $member->id,
                        $orderId,
                        $orderNomor,
                        $grandTotal
                    );
                    
                    Log::info('Challenge progress rollback result', [
                        'member_id' => $memberId,
                        'order_id' => $orderId,
                        'rollback_result' => $rollbackResult
                    ]);
                    
                    if ($rollbackResult['rolled_back'] ?? false) {
                        Log::info('Challenge progress rolled back successfully', [
                            'member_id' => $memberId,
                            'order_id' => $orderId,
                            'challenges_affected' => $rollbackResult['challenges_affected'] ?? 0,
                            'rewards_rolled_back' => $rollbackResult['rewards_rolled_back'] ?? []
                            ]);
                    } else {
                        Log::info('No challenge progress rolled back', [
                            'member_id' => $memberId,
                            'order_id' => $orderId,
                            'reason' => $rollbackResult['error'] ?? 'No progress found'
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error rolling back challenge progress', [
                        'member_id' => $memberId,
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                        ]);
                    // Continue with rollback even if challenge rollback fails
                }

                DB::commit();

                // Calculate total points rolled back (net change)
                $totalPointsRolledBack = $totalPointsToAddBack - $totalPointsToDeduct;

                return response()->json([
                    'success' => true,
                    'message' => 'Member transaction rolled back successfully',
                    'data' => [
                        'order_id' => $orderId,
                        'member_id' => $memberId,
                        'points_added_back' => $totalPointsToAddBack,
                        'points_deducted' => $totalPointsToDeduct,
                        'points_rolled_back' => $totalPointsRolledBack,
                        'spending_rolled_back' => $grandTotal
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error rolling back member transaction', [
                    'order_id' => $orderId,
                    'member_id' => $memberId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Rollback Member Transaction Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback member transaction: ' . $e->getMessage()
            ], 500);
        }
    }
}
