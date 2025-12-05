<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Services\PointEarningService;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsPointEarning;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PointController extends Controller
{
    protected $pointEarningService;

    public function __construct(PointEarningService $pointEarningService)
    {
        $this->pointEarningService = $pointEarningService;
    }

    /**
     * Earn points from POS order
     */
    public function earn(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_id' => 'required|string',
                'order_id' => 'required|string',
                'transaction_amount' => 'required|numeric|min:0',
                'transaction_date' => 'required|date',
                'channel' => 'nullable|string|in:dine-in,take-away,delivery-restaurant,gift-voucher,e-commerce,pos',
                'is_gift_voucher_payment' => 'nullable|boolean',
                'is_ecommerce_order' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $memberId = $request->input('member_id');
            $orderId = $request->input('order_id');
            $transactionAmount = $request->input('transaction_amount');
            $transactionDate = $request->input('transaction_date');
            $channel = $request->input('channel', 'pos');
            $isGiftVoucherPayment = $request->input('is_gift_voucher_payment', false);
            $isEcommerceOrder = $request->input('is_ecommerce_order', false);

            // Check if points already earned for this order (prevent duplicate)
            // Note: We check by order_id only, not by channel, to prevent duplicate earning
            $existingTransaction = \App\Models\MemberAppsPointTransaction::where('reference_id', $orderId)
                ->where('transaction_type', 'earn')
                ->first();

            if ($existingTransaction) {
                \Log::info('Points already earned for this order', [
                    'order_id' => $orderId,
                    'existing_transaction_id' => $existingTransaction->id
                ]);
                
                // Even though points already earned, we should still send notification
                // if this is a retry (e.g., user clicked earn again)
                // But to avoid duplicate notifications, we'll skip it for now
                // If you want to send notification on retry, uncomment below:
                /*
                try {
                    $member = \App\Models\MemberAppsMember::where('member_id', $memberId)
                        ->orWhere('id', $memberId)
                        ->first();
                    
                    if ($member && $member->allow_notification) {
                        event(new \App\Events\PointEarned(
                            $member,
                            $existingTransaction,
                            $existingTransaction->point_amount,
                            'transaction',
                            [
                                'order_id' => $orderId,
                                'outlet_name' => 'Outlet',
                            ]
                        ));
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error sending notification for existing transaction', [
                        'error' => $e->getMessage()
                    ]);
                }
                */
                
                return response()->json([
                    'success' => true,
                    'message' => 'Points already earned for this order',
                    'data' => [
                        'transaction_id' => $existingTransaction->id,
                        'points_earned' => $existingTransaction->point_amount
                    ]
                ]);
            }

            // Earn points
            $result = $this->pointEarningService->earnPointsFromOrder(
                $memberId,
                $orderId,
                $transactionAmount,
                $transactionDate,
                $channel,
                $isGiftVoucherPayment,
                $isEcommerceOrder
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'No points earned (amount too low or member not found)'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Points earned successfully',
                'data' => [
                    'transaction_id' => $result['transaction']->id,
                    'earning_id' => $result['earning']->id,
                    'points_earned' => $result['points_earned'],
                    'total_points' => $result['total_points'],
                    'expires_at' => $result['transaction']->expires_at
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in point earning endpoint', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to earn points: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get point history for authenticated member
     */
    public function history(Request $request)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get pagination parameters
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 5);
            $offset = ($page - 1) * $limit;

            // Get total count for pagination
            $totalCount = MemberAppsPointTransaction::where('member_id', $member->id)
                ->whereIn('transaction_type', ['earn', 'redeem', 'expired', 'bonus', 'adjustment'])
                ->count();

            // Get point transactions with pagination (avoid collation issue)
            // Include all transaction types: 'earn', 'redeem', 'expired', 'bonus', 'adjustment'
            $pointTransactions = MemberAppsPointTransaction::where('member_id', $member->id)
                ->whereIn('transaction_type', ['earn', 'redeem', 'expired', 'bonus', 'adjustment'])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            // Get outlet info separately to avoid collation issues
            $transactions = $pointTransactions->map(function($transaction) {
                $outletName = null;
                $outletCode = null;
                $outletId = null;
                
                // Try to get order and outlet info using raw query to avoid collation issues
                // Only for 'earn' transaction type (from orders)
                // For other types (bonus, redeem, expired, adjustment), skip outlet lookup
                if ($transaction->reference_id && $transaction->transaction_type === 'earn') {
                    try {
                        // Use direct comparison without casting to avoid collation issues
                        // If reference_id is numeric, compare as integer; if string, compare as string
                        $referenceId = $transaction->reference_id;
                        $isNumeric = is_numeric($referenceId);
                        
                        \Log::info('Getting outlet info for transaction', [
                            'transaction_id' => $transaction->id,
                            'reference_id' => $referenceId,
                            'is_numeric' => $isNumeric
                        ]);
                        
                        if ($isNumeric) {
                            // Compare as integer
                            $orderData = DB::selectOne(
                                "SELECT kode_outlet 
                                 FROM orders 
                                 WHERE id = ? 
                                 LIMIT 1",
                                [(int)$referenceId]
                            );
                        } else {
                            // Compare as string (using BINARY to avoid collation)
                            $orderData = DB::selectOne(
                                "SELECT kode_outlet 
                                 FROM orders 
                                 WHERE BINARY CAST(id AS CHAR) = BINARY ? 
                                 LIMIT 1",
                                [(string)$referenceId]
                            );
                        }
                        
                        \Log::info('Order data retrieved', [
                            'reference_id' => $referenceId,
                            'order_found' => $orderData !== null,
                            'kode_outlet' => $orderData ? $orderData->kode_outlet : null
                        ]);
                        
                        if ($orderData && $orderData->kode_outlet) {
                            // Try case-sensitive first
                            $outletData = DB::selectOne(
                                "SELECT nama_outlet, qr_code, id_outlet 
                                 FROM tbl_data_outlet 
                                 WHERE BINARY qr_code = BINARY ? 
                                 LIMIT 1",
                                [$orderData->kode_outlet]
                            );
                            
                            // If not found, try case-insensitive
                            if (!$outletData) {
                                $outletData = DB::selectOne(
                                    "SELECT nama_outlet, qr_code, id_outlet 
                                     FROM tbl_data_outlet 
                                     WHERE LOWER(qr_code) = LOWER(?)
                                     LIMIT 1",
                                    [$orderData->kode_outlet]
                                );
                            }
                            
                            \Log::info('Outlet data retrieved', [
                                'kode_outlet' => $orderData->kode_outlet,
                                'outlet_found' => $outletData !== null,
                                'outlet_id' => $outletData ? $outletData->id_outlet : null,
                                'qr_code' => $outletData ? $outletData->qr_code : null
                            ]);
                            
                            if ($outletData) {
                                $outletName = $outletData->nama_outlet;
                                $outletCode = $outletData->qr_code;
                                $outletId = $outletData->id_outlet;
                            } else {
                                \Log::warning('Outlet not found for kode_outlet', [
                                    'kode_outlet' => $orderData->kode_outlet,
                                    'reference_id' => $referenceId
                                ]);
                                
                                // Try to find similar qr_code for debugging
                                $similarCodes = DB::select(
                                    "SELECT qr_code, nama_outlet 
                                     FROM tbl_data_outlet 
                                     WHERE qr_code LIKE ? OR LOWER(qr_code) LIKE LOWER(?)
                                     LIMIT 5",
                                    ['%' . $orderData->kode_outlet . '%', '%' . $orderData->kode_outlet . '%']
                                );
                                \Log::info('Similar qr_codes found', [
                                    'kode_outlet' => $orderData->kode_outlet,
                                    'similar_codes' => $similarCodes
                                ]);
                            }
                        } else {
                            \Log::warning('Order found but kode_outlet is null or empty', [
                                'reference_id' => $referenceId,
                                'order_data' => $orderData ? (array)$orderData : null
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error getting outlet info for transaction', [
                            'transaction_id' => $transaction->id,
                            'reference_id' => $transaction->reference_id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                } else {
                    // For other transaction types (bonus, redeem, expired, adjustment), set default values
                    // Outlet info will be null, which is fine
                    $outletName = null;
                    $outletCode = null;
                    $outletId = null;
                }
                
                // Add outlet info to transaction object
                $transaction->outlet_name = $outletName;
                $transaction->outlet_code = $outletCode;
                $transaction->outlet_id = $outletId;
                
                return $transaction;
            });

            \Log::info('Point history query', [
                'member_id' => $member->id,
                'transaction_count' => $transactions->count()
            ]);

            $result = $transactions->map(function($transaction) {
                try {
                    // Get brand logo and outlet name by joining orders -> tbl_data_outlet -> member_apps_brands
                    // This is the same logo shown in all brands screen
                    $logoUrl = null;
                    $brandId = null;
                    $brandName = null;
                    $outletNameFromJoin = null;
                    
                    // Only try to get logo for 'earn' transactions with reference_id (order ID)
                    // For 'redeem' transactions, reference_id might be in format "serial_code|order_id"
                    if ($transaction->reference_id && ($transaction->transaction_type === 'earn' || $transaction->transaction_type === 'redeem')) {
                        try {
                            $referenceId = $transaction->reference_id;
                            
                            // For redeem transactions, reference_id might be in format "serial_code|order_id"
                            // Extract order_id if present
                            if ($transaction->transaction_type === 'redeem' && strpos($referenceId, '|') !== false) {
                                $parts = explode('|', $referenceId, 2);
                                $referenceId = $parts[1]; // Use order_id part
                                \Log::info('Parsed reference_id for redeem transaction', [
                                    'original_reference_id' => $transaction->reference_id,
                                    'serial_code' => $parts[0],
                                    'order_id' => $referenceId
                                ]);
                            }
                            
                            $isNumeric = is_numeric($referenceId);
                            
                            \Log::info('Attempting join query for transaction', [
                                'transaction_id' => $transaction->id,
                                'reference_id' => $referenceId,
                                'is_numeric' => $isNumeric,
                                'transaction_type' => $transaction->transaction_type
                            ]);
                            
                            // First, check if order exists
                            $orderCheck = null;
                            if ($isNumeric) {
                                $orderCheck = DB::selectOne(
                                    "SELECT id, kode_outlet FROM orders WHERE id = ? LIMIT 1",
                                    [(int)$referenceId]
                                );
                            } else {
                                $orderCheck = DB::selectOne(
                                    "SELECT id, kode_outlet FROM orders WHERE BINARY CAST(id AS CHAR) = BINARY ? LIMIT 1",
                                    [(string)$referenceId]
                                );
                            }
                            
                            \Log::info('Order check result', [
                                'reference_id' => $referenceId,
                                'order_found' => $orderCheck !== null,
                                'kode_outlet' => $orderCheck ? $orderCheck->kode_outlet : null
                            ]);
                            
                            // Join orders -> tbl_data_outlet -> member_apps_brands to get logo, brand_id, brand_name, and outlet name
                            // Try case-sensitive first, then case-insensitive
                            if ($isNumeric) {
                                $brandData = DB::selectOne(
                                    "SELECT mb.id as brand_id, mb.name as brand_name, mb.logo, tdo.nama_outlet 
                                     FROM orders o
                                     INNER JOIN tbl_data_outlet tdo ON BINARY tdo.qr_code = BINARY o.kode_outlet
                                     INNER JOIN member_apps_brands mb ON mb.outlet_id = tdo.id_outlet AND mb.is_active = 1
                                     WHERE o.id = ?
                                     LIMIT 1",
                                    [(int)$referenceId]
                                );
                                
                                // If not found, try case-insensitive
                                if (!$brandData) {
                                    $brandData = DB::selectOne(
                                        "SELECT mb.id as brand_id, mb.name as brand_name, mb.logo, tdo.nama_outlet 
                                         FROM orders o
                                         INNER JOIN tbl_data_outlet tdo ON LOWER(tdo.qr_code) = LOWER(o.kode_outlet)
                                         INNER JOIN member_apps_brands mb ON mb.outlet_id = tdo.id_outlet AND mb.is_active = 1
                                         WHERE o.id = ?
                                         LIMIT 1",
                                        [(int)$referenceId]
                                    );
                                }
                            } else {
                                $brandData = DB::selectOne(
                                    "SELECT mb.id as brand_id, mb.name as brand_name, mb.logo, tdo.nama_outlet 
                                     FROM orders o
                                     INNER JOIN tbl_data_outlet tdo ON BINARY tdo.qr_code = BINARY o.kode_outlet
                                     INNER JOIN member_apps_brands mb ON mb.outlet_id = tdo.id_outlet AND mb.is_active = 1
                                     WHERE BINARY CAST(o.id AS CHAR) = BINARY ?
                                     LIMIT 1",
                                    [(string)$referenceId]
                                );
                                
                                // If not found, try case-insensitive
                                if (!$brandData) {
                                    $brandData = DB::selectOne(
                                        "SELECT mb.id as brand_id, mb.name as brand_name, mb.logo, tdo.nama_outlet 
                                         FROM orders o
                                         INNER JOIN tbl_data_outlet tdo ON LOWER(tdo.qr_code) = LOWER(o.kode_outlet)
                                         INNER JOIN member_apps_brands mb ON mb.outlet_id = tdo.id_outlet AND mb.is_active = 1
                                         WHERE BINARY CAST(o.id AS CHAR) = BINARY ?
                                         LIMIT 1",
                                        [(string)$referenceId]
                                    );
                                }
                            }
                            
                            \Log::info('Join query result for transaction', [
                                'transaction_id' => $transaction->id,
                                'reference_id' => $referenceId,
                                'brand_data_found' => $brandData !== null,
                                'outlet_name' => $brandData ? ($brandData->nama_outlet ?? null) : null,
                                'brand_id' => $brandData ? ($brandData->brand_id ?? null) : null,
                                'brand_name' => $brandData ? ($brandData->brand_name ?? null) : null,
                                'has_logo' => $brandData && $brandData->logo ? true : false,
                                'logo_value' => $brandData && $brandData->logo ? $brandData->logo : null
                            ]);
                            
                            if ($brandData) {
                                if ($brandData->logo) {
                                    $logo = $brandData->logo;
                                    // Build logo URL (same as all brands screen)
                                    $logoUrl = (substr($logo, 0, 4) === 'http') ? 
                                        $logo : 
                                        'https://ymsofterp.com/storage/' . ltrim($logo, '/');
                                    
                                    \Log::info('Logo URL built successfully', [
                                        'original_logo' => $logo,
                                        'logo_url' => $logoUrl
                                    ]);
                                }
                                $brandId = $brandData->brand_id ?? null;
                                $brandName = $brandData->brand_name ?? null;
                                $outletNameFromJoin = $brandData->nama_outlet ?? null;
                            } else {
                                \Log::warning('Join query returned no data', [
                                    'transaction_id' => $transaction->id,
                                    'reference_id' => $referenceId,
                                    'order_exists' => $orderCheck !== null,
                                    'kode_outlet' => $orderCheck ? $orderCheck->kode_outlet : null
                                ]);
                            }
                        } catch (\Exception $e) {
                            \Log::error('Error getting logo from join query', [
                                'transaction_id' => $transaction->id,
                                'reference_id' => $transaction->reference_id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                    
                    // Also try using outlet_id if available (fallback)
                    if (!$logoUrl && $transaction->outlet_id) {
                        try {
                            $brandDataModel = MemberAppsBrand::where('outlet_id', $transaction->outlet_id)
                                ->where('is_active', true)
                                ->first();
                            
                            if ($brandDataModel) {
                                if ($brandDataModel->logo) {
                                    $logo = $brandDataModel->logo;
                                    $logoUrl = (substr($logo, 0, 4) === 'http') ? 
                                        $logo : 
                                        'https://ymsofterp.com/storage/' . ltrim($logo, '/');
                                }
                                $brandId = $brandDataModel->id ?? null;
                                $brandName = $brandDataModel->name ?? null;
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Error getting brand data from outlet_id', [
                                'outlet_id' => $transaction->outlet_id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    // Format transaction date with time from created_at
                    $transactionDateTime = null;
                    try {
                        if ($transaction->created_at) {
                            $transactionDateTime = $transaction->created_at->format('Y-m-d H:i:s');
                        } else if ($transaction->transaction_date) {
                            $transactionDateTime = $transaction->transaction_date->format('Y-m-d') . ' 00:00:00';
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error formatting transaction date', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage()
                        ]);
                    }

                    // For redeem transactions, point_amount is negative, but we keep it as is
                    // Frontend can handle the display (e.g., show "-" prefix or different color)
                    $pointAmount = $transaction->point_amount ?? 0;
                    
                    // Generate location/description for transactions without outlet
                    // Use outlet name from join query if available, otherwise use from transaction object
                    $locationDescription = $outletNameFromJoin ?? $transaction->outlet_name;
                    if (!$locationDescription) {
                        // For transactions without outlet, provide informative description
                        $transactionType = $transaction->transaction_type ?? null;
                        $channel = $transaction->channel ?? null;
                        $referenceId = $transaction->reference_id ?? null;
                        $description = $transaction->description ?? null;
                        
                        if ($transactionType === 'bonus' && $channel === 'challenge_reward' && $referenceId) {
                            // Extract challenge ID from reference_id (format: CHALLENGE-{challengeId}-{progressId})
                            if (preg_match('/CHALLENGE-(\d+)/', $referenceId, $matches)) {
                                $challengeId = $matches[1];
                                try {
                                    $challenge = DB::table('member_apps_challenges')
                                        ->where('id', $challengeId)
                                        ->select('title')
                                        ->first();
                                    
                                    if ($challenge) {
                                        $locationDescription = "Menyelesaikan Challenge: {$challenge->title}";
                                    } else {
                                        $locationDescription = $description ?: "Point dari Challenge";
                                    }
                                } catch (\Exception $e) {
                                    $locationDescription = $description ?: "Point dari Challenge";
                                }
                            } else {
                                $locationDescription = $description ?: "Point Bonus";
                            }
                        } elseif ($transactionType === 'adjustment') {
                            $locationDescription = $description ?: "Point Adjustment Manual";
                        } elseif ($transactionType === 'expired') {
                            $locationDescription = $description ?: "Point Expired";
                        } elseif ($transactionType === 'redeem') {
                            $locationDescription = $description ?: "Redeem Reward";
                        } elseif ($transactionType === 'bonus') {
                            // Other bonus types (registration, birthday, referral, campaign)
                            $locationDescription = $description ?: "Point Bonus";
                        } else {
                            $locationDescription = $description ?: "Transaksi Point";
                        }
                    }
                    
                    return [
                        'id' => $transaction->id ?? null,
                        'member_id' => $transaction->member_id ?? null,
                        'transaction_type' => $transaction->transaction_type ?? null,
                        'transaction_date' => ($transaction->transaction_date ?? null) ? $transaction->transaction_date->format('Y-m-d') : null,
                        'transaction_datetime' => $transactionDateTime,
                        'point_amount' => $pointAmount, // Can be negative for redeem, positive for earn/bonus
                        'transaction_amount' => $transaction->transaction_amount ?? 0,
                        'earning_rate' => $transaction->earning_rate ?? null,
                        'channel' => $transaction->channel ?? null,
                        'reference_id' => $transaction->reference_id ?? null,
                        'description' => $transaction->description ?? null,
                        'expires_at' => ($transaction->expires_at ?? null) ? $transaction->expires_at->format('Y-m-d') : null,
                        'is_expired' => $transaction->is_expired ?? false,
                        'expired_at' => ($transaction->expired_at ?? null) ? $transaction->expired_at->format('Y-m-d H:i:s') : null,
                        'outlet_name' => $locationDescription, // Use location description instead of null
                        'outlet_code' => $transaction->outlet_code ?? null,
                        'brand_id' => $brandId,
                        'brand_name' => $brandName,
                        'brand_logo' => $logoUrl,
                    ];
                } catch (\Exception $e) {
                    \Log::error('Error mapping transaction', [
                        'transaction_id' => $transaction->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Return minimal data if mapping fails
                    // Generate location description for transactions without outlet
                    $locationDescription = $transaction->outlet_name;
                    if (!$locationDescription) {
                        $transactionType = $transaction->transaction_type ?? null;
                        $channel = $transaction->channel ?? null;
                        $referenceId = $transaction->reference_id ?? null;
                        $description = $transaction->description ?? null;
                        
                        if ($transactionType === 'bonus' && $channel === 'challenge_reward' && $referenceId) {
                            if (preg_match('/CHALLENGE-(\d+)/', $referenceId, $matches)) {
                                $challengeId = $matches[1];
                                try {
                                    $challenge = DB::table('member_apps_challenges')
                                        ->where('id', $challengeId)
                                        ->select('title')
                                        ->first();
                                    
                                    if ($challenge) {
                                        $locationDescription = "Menyelesaikan Challenge: {$challenge->title}";
                                    } else {
                                        $locationDescription = $description ?: "Point dari Challenge";
                                    }
                                } catch (\Exception $e) {
                                    $locationDescription = $description ?: "Point dari Challenge";
                                }
                            } else {
                                $locationDescription = $description ?: "Point Bonus";
                            }
                        } elseif ($transactionType === 'adjustment') {
                            $locationDescription = $description ?: "Point Adjustment Manual";
                        } elseif ($transactionType === 'expired') {
                            $locationDescription = $description ?: "Point Expired";
                        } elseif ($transactionType === 'redeem') {
                            $locationDescription = $description ?: "Redeem Reward";
                        } elseif ($transactionType === 'bonus') {
                            $locationDescription = $description ?: "Point Bonus";
                        } else {
                            $locationDescription = $description ?: "Transaksi Point";
                        }
                    }
                    
                    return [
                        'id' => $transaction->id ?? null,
                        'member_id' => $transaction->member_id ?? null,
                        'transaction_type' => $transaction->transaction_type ?? null,
                        'transaction_date' => $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : null,
                        'transaction_datetime' => $transaction->created_at ? $transaction->created_at->format('Y-m-d H:i:s') : null,
                        'point_amount' => $transaction->point_amount ?? 0,
                        'transaction_amount' => $transaction->transaction_amount ?? 0,
                        'earning_rate' => $transaction->earning_rate ?? null,
                        'channel' => $transaction->channel ?? null,
                        'reference_id' => $transaction->reference_id ?? null,
                        'description' => $transaction->description ?? null,
                        'expires_at' => null,
                        'is_expired' => $transaction->is_expired ?? false,
                        'expired_at' => null,
                        'outlet_name' => $locationDescription, // Use location description instead of null
                        'outlet_code' => $transaction->outlet_code ?? null,
                        'brand_id' => null,
                        'brand_name' => null,
                        'brand_logo' => null,
                    ];
                }
            });

            // Calculate pagination info
            $hasMore = ($offset + $limit) < $totalCount;
            $totalPages = ceil($totalCount / $limit);

            return response()->json([
                'success' => true,
                'data' => $result,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_more' => $hasMore
                ],
                'message' => 'Point history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting point history', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get point history: ' . $e->getMessage(),
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Get transaction detail with order items and modifiers
     */
    public function transactionDetail(Request $request, $transactionId)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get point transaction
            $transaction = MemberAppsPointTransaction::where('id', $transactionId)
                ->where('member_id', $member->id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // Get order items and modifiers
            $orderItems = [];
            if ($transaction->reference_id) {
                try {
                    // Get order items from order_items table
                    $items = DB::select("
                        SELECT 
                            oi.id,
                            oi.order_id,
                            oi.item_id,
                            oi.item_name,
                            oi.qty,
                            oi.price,
                            oi.subtotal,
                            oi.notes,
                            oi.modifiers,
                            (SELECT path FROM item_images WHERE item_id = oi.item_id LIMIT 1) as item_image
                        FROM order_items oi
                        WHERE oi.order_id = ?
                        ORDER BY oi.id ASC
                    ", [$transaction->reference_id]);

                    // Process each item and parse modifiers
                    foreach ($items as $item) {
                        // Parse modifiers from JSON
                        $modifiersList = [];
                        if ($item->modifiers) {
                            try {
                                $modifiersJson = json_decode($item->modifiers, true);
                                if (is_array($modifiersJson)) {
                                    // Format: {"Potato":{"Mashed Potato":1},"Saus":{"Mushroom":1,"Blackpepper":1}}
                                    foreach ($modifiersJson as $category => $modifierItems) {
                                        if (is_array($modifierItems)) {
                                            foreach ($modifierItems as $modifierName => $qty) {
                                                $modifiersList[] = [
                                                    'category' => $category,
                                                    'modifier_name' => $modifierName,
                                                    'qty' => (int)$qty,
                                                ];
                                            }
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Error parsing modifiers JSON', [
                                    'item_id' => $item->id,
                                    'modifiers' => $item->modifiers,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }

                        $orderItems[] = [
                            'id' => $item->id,
                            'item_id' => $item->item_id,
                            'item_name' => $item->item_name,
                            'qty' => (int)$item->qty,
                            'price' => (float)$item->price,
                            'subtotal' => (float)$item->subtotal,
                            'note' => $item->notes ?? null,
                            'item_image' => $item->item_image ? 'https://ymsofterp.com/storage/' . ltrim($item->item_image, '/') : null,
                            'modifiers' => $modifiersList,
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error getting order items', [
                        'transaction_id' => $transactionId,
                        'reference_id' => $transaction->reference_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Get brand logo
            $brandLogo = null;
            if ($transaction->reference_id) {
                try {
                    // Get outlet_id from order
                    $orderData = DB::selectOne(
                        "SELECT kode_outlet FROM orders WHERE id = ? LIMIT 1",
                        [$transaction->reference_id]
                    );
                    
                    if ($orderData && $orderData->kode_outlet) {
                        $outletData = DB::selectOne(
                            "SELECT id_outlet FROM tbl_data_outlet WHERE qr_code = ? LIMIT 1",
                            [$orderData->kode_outlet]
                        );
                        
                        if ($outletData && $outletData->id_outlet) {
                            $brandData = MemberAppsBrand::where('outlet_id', $outletData->id_outlet)
                                ->where('is_active', true)
                                ->first();
                            
                            if ($brandData && $brandData->logo) {
                                $brandLogo = str_starts_with($brandData->logo, 'http') ? 
                                    $brandData->logo : 
                                    'https://ymsofterp.com/storage/' . ltrim($brandData->logo, '/');
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error getting brand logo', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                        'transaction_datetime' => $transaction->created_at ? $transaction->created_at->format('Y-m-d H:i:s') : null,
                        'point_amount' => $transaction->point_amount,
                        'transaction_amount' => $transaction->transaction_amount,
                        'earning_rate' => $transaction->earning_rate,
                        'channel' => $transaction->channel,
                        'reference_id' => $transaction->reference_id,
                        'description' => $transaction->description,
                    ],
                    'order_items' => $orderItems,
                    'brand_logo' => $brandLogo,
                ],
                'message' => 'Transaction detail retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting transaction detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get transaction detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get points expiring soon for authenticated member
     * Returns 1 point earning that will expire soonest
     */
    public function expiringSoon(Request $request)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get points that will expire in the next 30 days
            $today = now()->format('Y-m-d');
            $expiringDate = now()->addDays(30)->format('Y-m-d');
            
            \Log::info('Fetching expiring points', [
                'member_id' => $member->id,
                'today' => $today,
                'expiring_date' => $expiringDate
            ]);
            
            // Get the nearest expiring point earning (1 record only)
            // Filter: not expired, not fully redeemed, has remaining points, expires within 30 days
            $nearestExpiringEarning = MemberAppsPointEarning::where('member_id', $member->id)
                ->where('is_fully_redeemed', false)
                ->where('remaining_points', '>', 0)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $expiringDate)
                ->where('expires_at', '>=', $today)
                ->orderBy('expires_at', 'asc')
                ->orderBy('earned_at', 'asc')
                ->first();

            // Log query result for debugging
            if (!$nearestExpiringEarning) {
                // Check if there are any point earnings at all
                $totalEarnings = MemberAppsPointEarning::where('member_id', $member->id)->count();
                $availableEarnings = MemberAppsPointEarning::where('member_id', $member->id)
                    ->where('is_fully_redeemed', false)
                    ->where('remaining_points', '>', 0)
                    ->whereNotNull('expires_at')
                    ->count();
                
                \Log::info('No expiring points found', [
                    'member_id' => $member->id,
                    'total_earnings' => $totalEarnings,
                    'available_earnings' => $availableEarnings,
                    'filter_range' => "$today to $expiringDate"
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_points' => 0,
                        'expires_at' => null,
                        'expires_at_formatted' => null,
                        'days_until_expiry' => null,
                        'point_amount' => 0,
                        'remaining_points' => 0,
                        'earned_at' => null
                    ],
                    'message' => 'No points expiring soon'
                ]);
            }

            $expiryDate = \Carbon\Carbon::parse($nearestExpiringEarning->expires_at);
            $daysUntilExpiry = now()->diffInDays($expiryDate, false);

            \Log::info('Expiring point found', [
                'member_id' => $member->id,
                'earning_id' => $nearestExpiringEarning->id,
                'remaining_points' => $nearestExpiringEarning->remaining_points,
                'expires_at' => $nearestExpiringEarning->expires_at,
                'days_until_expiry' => $daysUntilExpiry
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_points' => (int) $nearestExpiringEarning->remaining_points,
                    'point_amount' => (int) $nearestExpiringEarning->point_amount,
                    'remaining_points' => (int) $nearestExpiringEarning->remaining_points,
                    'earned_at' => $nearestExpiringEarning->earned_at ? $nearestExpiringEarning->earned_at->format('Y-m-d') : null,
                    'expires_at' => $nearestExpiringEarning->expires_at->format('Y-m-d'),
                    'expires_at_formatted' => $expiryDate->format('d M Y'),
                    'days_until_expiry' => (int) ($daysUntilExpiry >= 0 ? $daysUntilExpiry : 0)
                ],
                'message' => 'Expiring points retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting expiring points', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get expiring points: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed list of ALL points with expiration date
     * Sorted by expiration date (nearest first)
     */
    public function expiringDetail(Request $request)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Get ALL point earnings (not just expiring soon, but all with expiration date)
            // Include both expired and non-expired, but exclude fully redeemed
            $allEarnings = MemberAppsPointEarning::where('member_id', $member->id)
                ->where('is_fully_redeemed', false)
                ->whereNotNull('expires_at')
                ->orderBy('expires_at', 'asc') // Nearest expiry first
                ->get();

            $result = $allEarnings->map(function($earning) {
                // Calculate days until expiry
                $expiryDate = \Carbon\Carbon::parse($earning->expires_at);
                $now = now();
                $daysUntilExpiry = $now->diffInDays($expiryDate, false);
                
                // Check if expired
                $isExpired = $earning->is_expired || $expiryDate->isPast();
                
                return [
                    'id' => (int) $earning->id,
                    'member_id' => (int) $earning->member_id,
                    'point_transaction_id' => (int) $earning->point_transaction_id,
                    'point_amount' => (int) $earning->point_amount,
                    'remaining_points' => (int) $earning->remaining_points,
                    'earned_at' => $earning->earned_at ? $earning->earned_at->format('Y-m-d') : null,
                    'expires_at' => $earning->expires_at->format('Y-m-d'),
                    'is_expired' => (bool) $isExpired,
                    'expired_at' => $earning->expired_at ? $earning->expired_at->format('Y-m-d H:i:s') : null,
                    'is_fully_redeemed' => (bool) $earning->is_fully_redeemed,
                    'days_until_expiry' => (int) ($daysUntilExpiry >= 0 ? $daysUntilExpiry : 0),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Expiring points detail retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting expiring points detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get expiring points detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Earn bonus points (for registration, referral, campaign)
     */
    public function earnBonus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_id' => 'required|string',
                'bonus_type' => 'required|string|in:registration,birthday,referral,campaign',
                'point_amount' => 'nullable|integer|min:1',
                'validity_days' => 'nullable|integer|min:1',
                'reference_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $memberId = $request->input('member_id');
            $bonusType = $request->input('bonus_type');
            $pointAmount = $request->input('point_amount');
            $validityDays = $request->input('validity_days');
            $referenceId = $request->input('reference_id');

            // Find member
            $member = \App\Models\MemberAppsMember::where('member_id', $memberId)
                ->orWhere('id', $memberId)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }

            // Earn bonus points
            $result = $this->pointEarningService->earnBonusPoints(
                $member->id,
                $bonusType,
                $pointAmount,
                $validityDays,
                $referenceId
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to earn bonus points (may already be given)'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bonus points earned successfully',
                'data' => [
                    'transaction_id' => $result['transaction']->id,
                    'earning_id' => $result['earning']->id,
                    'points_earned' => $result['points_earned'],
                    'total_points' => $result['total_points'],
                    'expires_at' => $result['expires_at']
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in bonus point earning endpoint', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to earn bonus points: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback point redemption (untuk void transaction)
     * Mengembalikan point ke member dan menghapus/update point transaction
     */
    public function rollbackPointRedemption(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'serial_code' => 'required|string',
                'member_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $serialCode = $request->input('serial_code');
            $memberId = $request->input('member_id');

            \Log::info('Rollback point redemption request', [
                'serial_code' => $serialCode,
                'member_id' => $memberId
            ]);

            // Cari member
            $member = MemberAppsMember::where('member_id', $memberId)->first();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }

            // Cari point transaction dengan reference_id yang mengandung serial_code
            // Format reference_id: "serial_code" atau "serial_code|order_id"
            $pointTransaction = MemberAppsPointTransaction::where('member_id', $member->id)
                ->where('transaction_type', 'redeem')
                ->where(function($query) use ($serialCode) {
                    $query->where('reference_id', $serialCode)
                          ->orWhere('reference_id', 'LIKE', $serialCode . '|%');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$pointTransaction) {
                \Log::warning('Point transaction not found for rollback', [
                    'serial_code' => $serialCode,
                    'member_id' => $memberId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Point transaction not found for this serial code'
                ], 404);
            }

            // Pastikan ini adalah transaksi redeem (bukan earn)
            if ($pointTransaction->transaction_type !== 'redeem') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction is not a redemption transaction'
                ], 400);
            }

            // Ambil point_amount yang harus dikembalikan
            $pointsToReturn = abs($pointTransaction->point_amount); // Pastikan positif

            \Log::info('Rolling back point redemption', [
                'transaction_id' => $pointTransaction->id,
                'points_to_return' => $pointsToReturn,
                'member_id' => $member->id,
                'current_points' => $member->just_points
            ]);

            DB::beginTransaction();

            try {
                // Rollback point redemption from earnings (update remaining_points and is_fully_redeemed)
                try {
                    $pointEarningService = new \App\Services\PointEarningService();
                    $rollbackResult = $pointEarningService->rollbackPointRedemptionFromEarnings($pointTransaction->id);
                    
                    if (!$rollbackResult) {
                        \Log::warning('Failed to rollback point earnings for redemption', [
                            'point_transaction_id' => $pointTransaction->id,
                            'member_id' => $member->id,
                        ]);
                        // Continue with rollback anyway
                    }
                } catch (\Exception $e) {
                    \Log::error('Error rolling back point earnings for redemption', [
                        'point_transaction_id' => $pointTransaction->id,
                        'member_id' => $member->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with rollback anyway
                }

                // Kembalikan point ke member
                $member->just_points = ($member->just_points ?? 0) + $pointsToReturn;
                $member->save();

                // Hapus point transaction (atau bisa juga di-update status menjadi 'voided')
                $pointTransaction->delete();

                DB::commit();

                \Log::info('Point redemption rolled back successfully', [
                    'transaction_id' => $pointTransaction->id,
                    'points_returned' => $pointsToReturn,
                    'member_id' => $member->id,
                    'new_points' => $member->just_points
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Point redemption rolled back successfully',
                    'data' => [
                        'points_returned' => $pointsToReturn,
                        'member_points' => $member->just_points
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            \Log::error('Error rolling back point redemption', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback point redemption: ' . $e->getMessage()
            ], 500);
        }
    }
}

