<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MemberHistoryController extends Controller
{
    // ... existing methods ...

    /**
     * Get member vouchers
     */
    public function getMemberVouchers(Request $request)
    {
        try {
            $search = $request->input('search'); // member_id or phone
            
            if (!$search) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter search (member_id/phone) required'
                ], 400);
            }

            // Find member
            $member = DB::table('member_apps_members')
                ->where(function($q) use ($search) {
                    $q->where('member_id', $search)
                      ->orWhere('mobile_phone', $search);
                })
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member tidak ditemukan'
                ], 404);
            }

            // Get vouchers
            $vouchers = DB::table('member_apps_member_vouchers as mav')
                ->join('member_apps_vouchers as av', 'mav.voucher_id', '=', 'av.id')
                ->where('mav.member_id', $member->id)
                ->select([
                    'mav.id',
                    'mav.serial_code',
                    'mav.voucher_code',
                    'mav.status',
                    'mav.expires_at',
                    'mav.used_at',
                    'mav.created_at',
                    'av.name as title',
                    'av.description',
                    'av.voucher_type',
                    'av.discount_percentage',
                    'av.discount_amount',
                    'av.min_purchase',
                    'av.max_discount',
                    'av.image'
                ])
                ->orderBy('mav.created_at', 'desc')
                ->get();

            $vouchers = $vouchers->map(function($v) {
                $isExpired = $v->status === 'expired' || ($v->expires_at && Carbon::parse($v->expires_at)->isPast());
                $isUsed = $v->status === 'used' || $v->used_at;
                $daysLeft = 0;
                $isExpiringSoon = false;

                if ($v->expires_at && !$isExpired && !$isUsed) {
                    $expiryDate = Carbon::parse($v->expires_at);
                    $daysLeft = Carbon::now()->diffInDays($expiryDate, false);
                    $isExpiringSoon = $daysLeft <= 7 && $daysLeft > 0;
                }

                // Determine final status
                $finalStatus = 'active';
                if ($isUsed) {
                    $finalStatus = 'used';
                } elseif ($isExpired) {
                    $finalStatus = 'expired';
                } elseif ($isExpiringSoon) {
                    $finalStatus = 'expiring_soon';
                }

                return [
                    'id' => $v->id,
                    'code' => $v->serial_code ?? $v->voucher_code,
                    'title' => $v->title,
                    'description' => $v->description,
                    'voucher_type' => $v->voucher_type,
                    'discount_percentage' => $v->discount_percentage,
                    'discount_amount' => $v->discount_amount,
                    'min_purchase' => $v->min_purchase,
                    'max_discount' => $v->max_discount,
                    'status' => $finalStatus,
                    'expires_at' => $v->expires_at,
                    'used_at' => $v->used_at,
                    'created_at' => $v->created_at,
                    'days_left' => max(0, $daysLeft),
                    'image' => $v->image,
                ];
            });

            return response()->json([
                'success' => true,
                'vouchers' => $vouchers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member challenges with progress
     */
    public function getMemberChallenges(Request $request)
    {
        try {
            $search = $request->input('search'); // member_id or phone
            
            if (!$search) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter search (member_id/phone) required'
                ], 400);
            }

            // Find member
            $member = DB::table('member_apps_members')
                ->where(function($q) use ($search) {
                    $q->where('member_id', $search)
                      ->orWhere('mobile_phone', $search);
                })
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member tidak ditemukan'
                ], 404);
            }

            // Get active challenges
            $challenges = DB::table('member_apps_challenges as c')
                ->leftJoin('member_apps_challenge_progress as cp', function($join) use ($member) {
                    $join->on('c.id', '=', 'cp.challenge_id')
                         ->where('cp.member_id', '=', $member->id);
                })
                ->where('c.is_active', true)
                ->where('c.end_date', '>=', Carbon::now())
                ->select([
                    'c.id',
                    'c.name as title',
                    'c.description',
                    'c.challenge_type as type',
                    'c.target_value as target',
                    'c.reward_type',
                    'c.reward_value',
                    'c.reward_description as reward',
                    'c.start_date',
                    'c.end_date',
                    'cp.current_value as progress',
                    'cp.status as progress_status',
                    'cp.completed_at'
                ])
                ->get();

            $challenges = $challenges->map(function($ch) {
                $progress = $ch->progress ?? 0;
                $target = $ch->target ?? 1;
                $daysLeft = Carbon::now()->diffInDays(Carbon::parse($ch->end_date), false);

                return [
                    'id' => $ch->id,
                    'title' => $ch->title,
                    'description' => $ch->description,
                    'type' => $ch->type,
                    'progress' => $progress,
                    'target' => $target,
                    'reward' => $ch->reward ?? $ch->reward_value . ' Points',
                    'end_date' => $ch->end_date,
                    'days_left' => max(0, $daysLeft),
                    'status' => $ch->progress_status ?? 'in_progress',
                    'completed_at' => $ch->completed_at,
                ];
            });

            return response()->json([
                'success' => true,
                'challenges' => $challenges
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
