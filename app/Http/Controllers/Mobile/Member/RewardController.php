<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsReward;
use App\Models\MemberAppsChallengeProgress;
use App\Models\MemberAppsChallenge;
use App\Models\MemberAppsMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class RewardController extends Controller
{
    /**
     * Get all active rewards yang bisa di-redeem oleh member
     * Filter berdasarkan:
     * 1. Point member cukup untuk redeem
     * 2. Reward dari challenge yang sudah completed
     */
    public function index(Request $request)
    {
        try {
            $member = null;
            $memberPoints = 0;
            $memberId = null;
            
            // Try to authenticate user from token if provided
            $token = $request->bearerToken();
            if ($token) {
                // Find token in database
                $accessToken = PersonalAccessToken::findToken($token);
                if ($accessToken) {
                    // Get the user (member) associated with this token
                    $member = $accessToken->tokenable;
                    if ($member instanceof MemberAppsMember) {
                        $memberPoints = $member->just_points ?? 0;
                        $memberId = $member->id;
                    }
                }
            }
            
            // Log for debugging
            \Log::info('RewardController@index', [
                'has_member' => $member ? 'yes' : 'no',
                'member_id' => $memberId,
                'member_points' => $memberPoints,
                'has_token' => $token ? 'yes' : 'no',
            ]);

            // 1. Ambil rewards yang point member cukup
            // Jika member tidak authenticated atau point = 0, tidak tampilkan reward biasa (hanya challenge rewards)
            $rewards = collect();
            
            // Hanya query rewards jika member authenticated dan punya point > 0
            if ($member && $memberPoints > 0) {
                $rewards = DB::table('member_apps_rewards as rewards')
                    ->join('items', 'rewards.item_id', '=', 'items.id')
                    ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                    ->leftJoin('sub_categories', function($join) {
                        $join->on('items.sub_category_id', '=', 'sub_categories.id')
                             ->whereNotNull('items.sub_category_id');
                    })
                    ->where('rewards.is_active', 1)
                    ->where('rewards.points_required', '<=', $memberPoints)
                    ->select(
                        'rewards.id as reward_id',
                        'rewards.item_id',
                        'rewards.points_required',
                        'rewards.serial_code',
                        'items.name as item_name',
                        'items.sku',
                        'items.description',
                        'items.category_id',
                        'items.sub_category_id',
                        'categories.name as category_name',
                        'sub_categories.name as sub_category_name'
                    )
                    ->orderBy('rewards.id', 'asc')
                    ->get();
            }

            // 2. Ambil reward dari challenge yang sudah completed
            $challengeRewards = collect();
            if ($memberId) {
                $completedChallenges = MemberAppsChallengeProgress::where('member_id', $memberId)
                    ->where('is_completed', true)
                    ->where(function($query) {
                        // Reward belum expired
                        $query->whereNull('reward_expires_at')
                              ->orWhere('reward_expires_at', '>', now());
                    })
                    ->where('reward_claimed', false) // Belum di-claim
                    ->with('challenge')
                    ->get();

                foreach ($completedChallenges as $progress) {
                    $challenge = $progress->challenge;
                    if (!$challenge) continue;

                    $rules = $challenge->rules;
                    if (is_string($rules)) {
                        $rules = json_decode($rules, true);
                    }

                    // Check reward type
                    if (isset($rules['reward_type'])) {
                        if ($rules['reward_type'] === 'item' && isset($rules['reward_value'])) {
                            // Reward berupa item
                            $itemIds = is_array($rules['reward_value']) 
                                ? $rules['reward_value'] 
                                : [$rules['reward_value']];
                            
                            foreach ($itemIds as $itemId) {
                                // Get item details
                                $item = DB::table('items')
                                    ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                                    ->leftJoin('sub_categories', function($join) {
                                        $join->on('items.sub_category_id', '=', 'sub_categories.id')
                                             ->whereNotNull('items.sub_category_id');
                                    })
                                    ->where('items.id', $itemId)
                                    ->select(
                                        'items.id as item_id',
                                        'items.name as item_name',
                                        'items.sku',
                                        'items.description',
                                        'items.category_id',
                                        'items.sub_category_id',
                                        'categories.name as category_name',
                                        'sub_categories.name as sub_category_name'
                                    )
                                    ->first();

                                if ($item) {
                                    // Use negative ID to avoid conflict with regular rewards
                                    // Format: -{challenge_id}{item_id} (e.g., -1123 for challenge 1, item 123)
                                    $challengeRewardId = -(intval($progress->challenge_id) * 1000000 + intval($itemId));
                                    
                                    $challengeRewards->push([
                                        'id' => $challengeRewardId,
                                        'item_id' => $item->item_id,
                                        'item_name' => $item->item_name,
                                        'points_required' => 0, // Free from challenge
                                        'points_display' => 'FREE',
                                        'sku' => $item->sku,
                                        'description' => $item->description,
                                        'category_name' => $item->category_name,
                                        'sub_category_name' => $item->sub_category_name,
                                        'category_display' => trim(($item->category_name ?? '') . ' ' . ($item->sub_category_name ?? '')),
                                        'serial_code' => null,
                                        'is_challenge_reward' => true,
                                        'challenge_id' => $progress->challenge_id,
                                        'challenge_title' => $challenge->title,
                                    ]);
                                }
                            }
                        } elseif ($rules['reward_type'] === 'voucher' && isset($rules['reward_value'])) {
                            // Reward berupa voucher - skip for now as vouchers are handled separately
                            // TODO: Add voucher reward handling if needed
                        }
                    }
                }
            }

            // 3. Format rewards dengan gambar
            $formattedRewards = $rewards->map(function ($reward) {
                // Ambil gambar pertama dari item_images
                $firstImage = DB::table('item_images')
                    ->where('item_id', $reward->item_id)
                    ->orderBy('id', 'asc')
                    ->first();

                // Format category dan sub_category
                $categoryDisplay = $reward->category_name ?? '';
                if ($reward->sub_category_name) {
                    $categoryDisplay = $categoryDisplay . ' ' . $reward->sub_category_name;
                }

                return [
                    'id' => $reward->reward_id,
                    'item_id' => $reward->item_id,
                    'item_name' => $reward->item_name,
                    'points_required' => $reward->points_required,
                    'points_display' => number_format($reward->points_required) . ' JUST-POINT',
                    'image' => $firstImage && $firstImage->path
                        ? 'https://ymsofterp.com/storage/' . $firstImage->path
                        : null,
                    'sku' => $reward->sku,
                    'description' => $reward->description,
                    'category_name' => $reward->category_name,
                    'sub_category_name' => $reward->sub_category_name,
                    'category_display' => trim($categoryDisplay),
                    'serial_code' => $reward->serial_code,
                    'is_challenge_reward' => false,
                ];
            });

            // 4. Format challenge rewards dengan gambar
            $formattedChallengeRewards = $challengeRewards->map(function ($reward) {
                // Ambil gambar pertama dari item_images
                $firstImage = DB::table('item_images')
                    ->where('item_id', $reward['item_id'])
                    ->orderBy('id', 'asc')
                    ->first();

                return array_merge($reward, [
                    'image' => $firstImage && $firstImage->path
                        ? 'https://ymsofterp.com/storage/' . $firstImage->path
                        : null,
                ]);
            });

            // 5. Gabungkan semua rewards (challenge rewards di depan)
            $allRewards = $formattedChallengeRewards->concat($formattedRewards);

            return response()->json([
                'success' => true,
                'data' => $allRewards->values()->all(),
                'message' => 'Rewards retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in RewardController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rewards: ' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], 500);
        }
    }
}

