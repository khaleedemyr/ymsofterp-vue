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
                'channel' => 'nullable|string|in:pos,online,mobile',
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

            // Check if points already earned for this order (prevent duplicate)
            $existingTransaction = \App\Models\MemberAppsPointTransaction::where('reference_id', $orderId)
                ->where('transaction_type', 'earning')
                ->where('channel', $channel)
                ->first();

            if ($existingTransaction) {
                \Log::info('Points already earned for this order', [
                    'order_id' => $orderId,
                    'existing_transaction_id' => $existingTransaction->id
                ]);
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
                $channel
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

            // Get point transactions with outlet and brand info
            $transactions = MemberAppsPointTransaction::where('member_id', $member->id)
                ->where('transaction_type', 'earning')
                ->leftJoin('orders', function($join) {
                    $join->on('member_apps_point_transactions.reference_id', '=', 'orders.id');
                })
                ->leftJoin('tbl_data_outlet', function($join) {
                    $join->on('orders.kode_outlet', '=', 'tbl_data_outlet.qr_code');
                })
                ->select(
                    'member_apps_point_transactions.*',
                    'tbl_data_outlet.nama_outlet as outlet_name',
                    'tbl_data_outlet.qr_code as outlet_code',
                    'tbl_data_outlet.id_outlet as outlet_id'
                )
                ->orderBy('member_apps_point_transactions.transaction_date', 'desc')
                ->orderBy('member_apps_point_transactions.created_at', 'desc')
                ->get();

            $result = $transactions->map(function($transaction) {
                // Get brand data from member_apps_brands using outlet_id
                $brandData = null;
                if ($transaction->outlet_id) {
                    $brandData = MemberAppsBrand::where('outlet_id', $transaction->outlet_id)
                        ->where('is_active', true)
                        ->first();
                }

                // Build logo URL
                $logoUrl = null;
                if ($brandData && $brandData->logo) {
                    $logoUrl = str_starts_with($brandData->logo, 'http') ? 
                        $brandData->logo : 
                        'https://ymsofterp.com/storage/' . ltrim($brandData->logo, '/');
                }

                // Format transaction date with time from created_at
                $transactionDateTime = $transaction->created_at ? 
                    $transaction->created_at->format('Y-m-d H:i:s') : 
                    $transaction->transaction_date->format('Y-m-d') . ' 00:00:00';

                return [
                    'id' => $transaction->id,
                    'member_id' => $transaction->member_id,
                    'transaction_type' => $transaction->transaction_type,
                    'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                    'transaction_datetime' => $transactionDateTime,
                    'point_amount' => $transaction->point_amount,
                    'transaction_amount' => $transaction->transaction_amount,
                    'earning_rate' => $transaction->earning_rate,
                    'channel' => $transaction->channel,
                    'reference_id' => $transaction->reference_id,
                    'description' => $transaction->description,
                    'expires_at' => $transaction->expires_at ? $transaction->expires_at->format('Y-m-d') : null,
                    'is_expired' => $transaction->is_expired,
                    'expired_at' => $transaction->expired_at ? $transaction->expired_at->format('Y-m-d H:i:s') : null,
                    'outlet_name' => $transaction->outlet_name,
                    'outlet_code' => $transaction->outlet_code,
                    'brand_id' => $brandData ? $brandData->id : null,
                    'brand_name' => $brandData ? $brandData->name : null,
                    'brand_logo' => $logoUrl,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Point history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting point history', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get point history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get points expiring soon for authenticated member
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
            $expiringDate = now()->addDays(30)->format('Y-m-d');
            
            $expiringEarnings = MemberAppsPointEarning::where('member_id', $member->id)
                ->where('is_expired', false)
                ->where('is_fully_redeemed', false)
                ->where('expires_at', '<=', $expiringDate)
                ->where('expires_at', '>=', now()->format('Y-m-d'))
                ->orderBy('expires_at', 'asc')
                ->get();

            // Group by expiration date
            $groupedByDate = $expiringEarnings->groupBy(function($earning) {
                return $earning->expires_at->format('Y-m-d');
            });

            // Get the nearest expiration date
            $nearestExpiration = $groupedByDate->keys()->first();
            $totalExpiringPoints = $expiringEarnings->sum('remaining_points');

            if (!$nearestExpiration) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_points' => 0,
                        'expires_at' => null,
                        'expires_at_formatted' => null,
                        'days_until_expiry' => null
                    ],
                    'message' => 'No points expiring soon'
                ]);
            }

            $nearestDate = \Carbon\Carbon::parse($nearestExpiration);
            $daysUntilExpiry = now()->diffInDays($nearestDate, false);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_points' => $totalExpiringPoints,
                    'expires_at' => $nearestExpiration,
                    'expires_at_formatted' => $nearestDate->format('d M Y'),
                    'days_until_expiry' => $daysUntilExpiry
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
     * Get detailed list of points expiring soon (minimal 1 month from today)
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

            // Get points that will expire in the next 30 days (minimal 1 month)
            $expiringDate = now()->addDays(30)->format('Y-m-d');
            
            $expiringEarnings = MemberAppsPointEarning::where('member_id', $member->id)
                ->where('is_fully_redeemed', false)
                ->where('expires_at', '<=', $expiringDate)
                ->where('expires_at', '>=', now()->format('Y-m-d'))
                ->orderBy('expires_at', 'asc')
                ->get();

            $result = $expiringEarnings->map(function($earning) {
                // Calculate days until expiry
                $expiryDate = \Carbon\Carbon::parse($earning->expires_at);
                $now = now();
                $daysUntilExpiry = $now->diffInDays($expiryDate, false);
                
                return [
                    'id' => $earning->id,
                    'member_id' => $earning->member_id,
                    'point_transaction_id' => $earning->point_transaction_id,
                    'point_amount' => $earning->point_amount,
                    'remaining_points' => $earning->remaining_points,
                    'earned_at' => $earning->earned_at ? $earning->earned_at->format('Y-m-d') : null,
                    'expires_at' => $earning->expires_at->format('Y-m-d'),
                    'is_expired' => $earning->is_expired,
                    'expired_at' => $earning->expired_at ? $earning->expired_at->format('Y-m-d H:i:s') : null,
                    'is_fully_redeemed' => $earning->is_fully_redeemed,
                    'days_until_expiry' => $daysUntilExpiry >= 0 ? $daysUntilExpiry : 0,
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
}

