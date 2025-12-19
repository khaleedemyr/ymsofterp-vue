<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsReward;
use App\Models\MemberAppsChallengeProgress;
use App\Models\MemberAppsChallenge;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsVoucher;
use App\Models\MemberAppsMemberVoucher;
use App\Events\PointEarned;
use App\Events\ChallengeCompleted;
use App\Events\VoucherReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

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
            
            // Check if this is for reward screen (filtered) or home screen (all rewards)
            $screen = $request->input('screen', 'home'); // 'home' or 'reward'
            $isRewardScreen = ($screen === 'reward');
            
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
                'screen' => $screen,
                'is_reward_screen' => $isRewardScreen,
                'has_member' => $member ? 'yes' : 'no',
                'member_id' => $memberId,
                'member_points' => $memberPoints,
                'has_token' => $token ? 'yes' : 'no',
            ]);

            // 1. Ambil semua rewards
            // For reward screen: we'll filter later based on can_redeem
            // For home screen: show all rewards
            $rewards = DB::table('member_apps_rewards as rewards')
                ->join('items', 'rewards.item_id', '=', 'items.id')
                ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                ->leftJoin('sub_categories', function($join) {
                    $join->on('items.sub_category_id', '=', 'sub_categories.id')
                         ->whereNotNull('items.sub_category_id');
                })
                ->where('rewards.is_active', 1)
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

            // Get pagination parameters for challenge rewards
            $challengeLimit = $request->input('challenge_limit', 5); // Default 5 challenge rewards per page
            $challengeOffset = $request->input('challenge_offset', 0); // Default start from 0
            $redeemableLimit = (int) $request->input('redeemable_limit', 4); // Default 4 redeemable rewards per page
            $redeemableOffset = (int) $request->input('redeemable_offset', 0); // Default start from 0
            
            \Log::info('Rewards API pagination parameters', [
                'challenge_limit' => $challengeLimit,
                'challenge_offset' => $challengeOffset,
                'redeemable_limit' => $redeemableLimit,
                'redeemable_offset' => $redeemableOffset,
                'screen' => $screen,
            ]);
            $totalChallengeCount = 0; // Initialize total count
            
            // 2. Ambil reward dari challenge yang sudah completed
            // Hanya untuk reward screen, skip untuk home screen
            $challengeRewards = collect();
            if ($memberId && $isRewardScreen) {
                // Get total count for pagination info - count only item rewards
                // We need to count challenges that have reward_type = 'item' and reward_value is set
                $totalChallengeCount = 0;
                $allCompletedChallenges = MemberAppsChallengeProgress::where('member_id', $memberId)
                    ->where('is_completed', true)
                    ->with('challenge')
                    ->get();
                
                foreach ($allCompletedChallenges as $progress) {
                    $challenge = $progress->challenge;
                    if (!$challenge) continue;
                    
                    $rules = $challenge->rules;
                    if (is_string($rules)) {
                        $rules = json_decode($rules, true);
                    }
                    
                    // Count only item rewards
                    if (isset($rules['reward_type']) && $rules['reward_type'] === 'item' && isset($rules['reward_value'])) {
                        $totalChallengeCount++;
                    }
                }
                
                // Include semua challenge rewards yang sudah completed
                // Termasuk yang sudah di-redeem, expired, dan yang belum di-claim
                // Serial code akan di-generate otomatis jika belum ada
                \Log::info('Challenge rewards pagination', [
                    'member_id' => $memberId,
                    'challenge_offset' => $challengeOffset,
                    'challenge_limit' => $challengeLimit,
                ]);
                
                $completedChallenges = MemberAppsChallengeProgress::where('member_id', $memberId)
                    ->where('is_completed', true)
                    ->with('challenge')
                    ->orderBy('updated_at', 'desc') // Order by updated_at untuk mendapatkan yang terbaru
                    ->skip($challengeOffset)
                    ->take($challengeLimit)
                    ->get();
                
                \Log::info('Challenge rewards query result', [
                    'member_id' => $memberId,
                    'offset' => $challengeOffset,
                    'limit' => $challengeLimit,
                    'returned_count' => $completedChallenges->count(),
                ]);
                
                // Log for debugging
                \Log::info('Challenge rewards query result', [
                    'member_id' => $memberId,
                    'completed_challenges_count' => $completedChallenges->count(),
                    'challenges' => $completedChallenges->map(function($p) {
                        return [
                            'challenge_id' => $p->challenge_id,
                            'reward_claimed' => $p->reward_claimed,
                            'serial_code' => $p->serial_code,
                            'has_challenge' => $p->challenge ? 'yes' : 'no'
                        ];
                    })->toArray()
                ]);

                foreach ($completedChallenges as $progress) {
                    $challenge = $progress->challenge;
                    if (!$challenge) {
                        \Log::warning('Challenge not found for progress', [
                            'progress_id' => $progress->id,
                            'challenge_id' => $progress->challenge_id
                        ]);
                        continue;
                    }

                    $rules = $challenge->rules;
                    if (is_string($rules)) {
                        $rules = json_decode($rules, true);
                    }

                    \Log::info('Processing challenge reward', [
                        'progress_id' => $progress->id,
                        'challenge_id' => $progress->challenge_id,
                        'reward_claimed' => $progress->reward_claimed,
                        'serial_code' => $progress->serial_code,
                        'reward_type' => $rules['reward_type'] ?? 'none',
                        'has_reward_value' => isset($rules['reward_value'])
                    ]);

                    // Process challenge rewards - show all (including expired/redeemed) with badges
                    if (isset($rules['reward_type']) && $rules['reward_type'] === 'item' && isset($rules['reward_value'])) {
                        // Reward berupa item
                        $itemIds = is_array($rules['reward_value']) 
                            ? $rules['reward_value'] 
                            : [$rules['reward_value']];
                        
                        // Check reward_item_selection: 'all' = semua items, 'single' = hanya 1 item
                        $rewardItemSelection = $rules['reward_item_selection'] ?? 'all'; // Default: all
                        
                        // If single selection, use selected_reward_item_id from progress_data
                        if ($rewardItemSelection === 'single' && count($itemIds) > 1) {
                            // Get selected item from progress_data
                            $progressData = $progress->progress_data ?? [];
                            $selectedItemId = $progressData['selected_reward_item_id'] ?? null;
                            
                            if ($selectedItemId && in_array($selectedItemId, $itemIds)) {
                                // Use the selected item from progress_data
                                $itemIds = [$selectedItemId];
                            } else {
                                // Fallback: use first item if selected_reward_item_id not found
                                $itemIds = [$itemIds[0]];
                            }
                        }
                        
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
                                
                                // Generate serial code if not already exists
                                // Serial code is generated when challenge is completed, not when claimed
                                // This allows us to show all completed rewards, even if not yet claimed
                                $serialCode = $progress->serial_code;
                                if (empty($serialCode)) {
                                    // Generate 8-character serial code for challenge reward
                                    // Format: CH + 6 char unique code = 8 chars total
                                    $serialCode = $this->generateUniqueChallengeSerialCode($progress->challenge_id, $memberId, $progress->id);
                                    
                                    // Update serial_code in database (but don't mark as claimed yet)
                                    $progress->serial_code = $serialCode;
                                    $progress->save();
                                }
                                
                                // Get reward outlet information from rules (reward outlet scope)
                                $rewardAllOutlets = true;
                                $rewardOutlets = [];
                                if (isset($rules['reward_all_outlets']) && $rules['reward_all_outlets'] === false && isset($rules['reward_outlet_ids']) && is_array($rules['reward_outlet_ids']) && !empty($rules['reward_outlet_ids'])) {
                                    $rewardAllOutlets = false;
                                    $rewardOutlets = DB::table('tbl_data_outlet')
                                        ->whereIn('id_outlet', $rules['reward_outlet_ids'])
                                        ->where('is_fc', 0)
                                        ->select('id_outlet as id', 'nama_outlet as name', 'qr_code as code')
                                        ->get()
                                        ->map(function($outlet) {
                                            return [
                                                'id' => $outlet->id,
                                                'name' => $outlet->name,
                                                'code' => $outlet->code,
                                            ];
                                        })->toArray();
                                }
                                
                                // Get redemption information if reward is already redeemed
                                $redeemedAt = null;
                                $redeemedOutletName = null;
                                if ($progress->reward_redeemed_at) {
                                    // Handle both Carbon instance and string
                                    if (is_string($progress->reward_redeemed_at)) {
                                        $redeemedAt = $progress->reward_redeemed_at;
                                    } else {
                                        $redeemedAt = $progress->reward_redeemed_at->format('Y-m-d H:i:s');
                                    }
                                    
                                    // Get outlet name from redeemed_outlet_id
                                    if ($progress->redeemed_outlet_id) {
                                        $outlet = DB::table('tbl_data_outlet')
                                            ->where('id_outlet', $progress->redeemed_outlet_id)
                                            ->select('nama_outlet')
                                            ->first();
                                        
                                        if ($outlet) {
                                            $redeemedOutletName = $outlet->nama_outlet;
                                        }
                                    }
                                }
                                
                                // Get expiration date
                                $rewardExpiresAt = null;
                                if ($progress->reward_expires_at) {
                                    // Handle both Carbon instance and string
                                    if (is_string($progress->reward_expires_at)) {
                                        $rewardExpiresAt = $progress->reward_expires_at;
                                    } else {
                                        $rewardExpiresAt = $progress->reward_expires_at->format('Y-m-d H:i:s');
                                    }
                                }
                                
                                // Check if reward is expired
                                $isExpired = false;
                                if ($progress->reward_expires_at) {
                                    $expiresAt = is_string($progress->reward_expires_at) 
                                        ? \Carbon\Carbon::parse($progress->reward_expires_at)
                                        : $progress->reward_expires_at;
                                    if ($expiresAt <= now()) {
                                        $isExpired = true;
                                    }
                                }
                                
                                \Log::info('Adding challenge reward item to collection', [
                                    'challenge_reward_id' => $challengeRewardId,
                                    'item_id' => $item->item_id,
                                    'item_name' => $item->item_name,
                                    'serial_code' => $serialCode,
                                    'reward_claimed' => $progress->reward_claimed ?? false,
                                    'reward_redeemed_at' => $redeemedAt,
                                    'reward_expires_at' => $rewardExpiresAt,
                                    'is_expired' => $isExpired,
                                    'progress_id' => $progress->id
                                ]);
                                
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
                                    'serial_code' => $serialCode, // Serial code generated when reward is available
                                    'can_redeem' => !$isExpired && !$progress->reward_redeemed_at, // Can redeem if not expired and not yet redeemed
                                    'reward_claimed' => $progress->reward_claimed ?? false,
                                    'is_challenge_reward' => true,
                                    'challenge_id' => $progress->challenge_id,
                                    'challenge_title' => $challenge->title,
                                    'all_outlets' => $rewardAllOutlets,
                                    'outlets' => $rewardOutlets,
                                    'reward_redeemed_at' => $redeemedAt, // When reward was redeemed
                                    'reward_expires_at' => $rewardExpiresAt, // When reward expires
                                    'is_expired' => $isExpired, // Whether reward is expired
                                    'redeemed_outlet_name' => $redeemedOutletName, // Outlet name where reward was redeemed
                                ]);
                            }
                        }
                    }
                    
                    // Skip legacy points_reward field - only show item rewards
                    // if ($challenge->points_reward && $challenge->points_reward > 0) {
                    //     // Skip point rewards - only show item rewards
                    // }
                }
            }

            // 3. Format rewards dengan gambar
            // For reward screen: only show rewards that can be redeemed (point cukup)
            // For home screen: show all rewards
            $formattedRewards = $rewards->map(function ($reward) use ($memberPoints, $isRewardScreen, $memberId, $member) {
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

                // Check if member can redeem (has enough points, considering point_remainder)
                $memberRemainder = $member->point_remainder ?? 0;
                $availablePoints = $memberPoints + floor($memberRemainder);
                $canRedeem = $availablePoints >= $reward->points_required;

                // For reward screen: filter only rewards that can be redeemed
                if ($isRewardScreen && !$canRedeem) {
                    return null; // Skip this reward
                }

                // Generate serial code if can redeem but serial_code is null
                $serialCode = $reward->serial_code;
                if ($canRedeem && empty($serialCode)) {
                    // Generate shorter serial code for regular reward (easier for POS input)
                    // Format: RW + 4 digit reward_id + 4 digit member_id + 4 char hash = 14 chars
                    $serialCode = 'RW' . 
                                 str_pad($reward->reward_id % 10000, 4, '0', STR_PAD_LEFT) . 
                                 str_pad(($memberId ?? 0) % 10000, 4, '0', STR_PAD_LEFT) . 
                                 strtoupper(substr(md5($reward->reward_id . ($memberId ?? 0) . now()), 0, 4));
                    
                    // Update serial_code in database
                    DB::table('member_apps_rewards')
                        ->where('id', $reward->reward_id)
                        ->update(['serial_code' => $serialCode]);
                }

                // Get outlet information for this reward
                // Use explicit casting to ensure type matching
                $outlets = DB::table('member_apps_reward_outlets as ro')
                    ->join('tbl_data_outlet as o', 'ro.outlet_id', '=', 'o.id_outlet')
                    ->where('ro.reward_id', (int)$reward->reward_id)
                    ->where('o.is_fc', 0)
                    ->select(
                        'o.id_outlet as id',
                        'o.nama_outlet as name',
                        'o.qr_code as code'
                    )
                    ->get();

                // Log for debugging
                \Log::info('Reward outlets query', [
                    'reward_id' => $reward->reward_id,
                    'outlets_count' => $outlets->count(),
                    'outlets' => $outlets->toArray()
                ]);

                // If no outlets found, it means reward is available at all outlets
                // Otherwise, it's only available at specific outlets
                $allOutlets = $outlets->isEmpty();
                $outletList = $allOutlets ? [] : $outlets->map(function($outlet) {
                    return [
                        'id' => $outlet->id,
                        'name' => $outlet->name,
                        'code' => $outlet->code
                    ];
                })->toArray();
                
                \Log::info('Reward outlet result', [
                    'reward_id' => $reward->reward_id,
                    'all_outlets' => $allOutlets,
                    'outlet_list_count' => count($outletList)
                ]);

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
                    'serial_code' => $canRedeem ? $serialCode : null, // Show serial code if can redeem
                    'can_redeem' => $canRedeem,
                    'is_challenge_reward' => false,
                    'all_outlets' => $allOutlets,
                    'outlets' => $outletList,
                ];
            })->filter(function($reward) {
                return $reward !== null; // Remove null values (filtered out rewards)
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

            // 5. Group rewards by type
            if ($isRewardScreen) {
                // For reward screen: return grouped structure
                // Only include challenge rewards and rewards that can be redeemed
                $challengeRewardsList = $formattedChallengeRewards->values()->all();
                
                // Filter only rewards that can be redeemed (point cukup)
                $allRedeemableRewards = $formattedRewards->filter(function($reward) {
                    return $reward !== null && isset($reward['can_redeem']) && $reward['can_redeem'] === true;
                })->values();
                
                // Apply pagination for redeemable rewards
                $totalRedeemableCount = $allRedeemableRewards->count();
                $redeemableRewardsList = $allRedeemableRewards->skip($redeemableOffset)->take($redeemableLimit)->values()->all();
                
                \Log::info('Redeemable rewards pagination applied', [
                    'total_count' => $totalRedeemableCount,
                    'offset' => $redeemableOffset,
                    'limit' => $redeemableLimit,
                    'returned_count' => count($redeemableRewardsList),
                    'has_more' => ($redeemableOffset + $redeemableLimit) < $totalRedeemableCount,
                ]);
                
                \Log::info('Reward screen filter', [
                    'challenge_rewards_count' => count($challengeRewardsList),
                    'challenge_rewards_raw_count' => $challengeRewards->count(),
                    'formatted_challenge_rewards_count' => $formattedChallengeRewards->count(),
                    'redeemable_rewards_total' => $totalRedeemableCount,
                    'redeemable_rewards_count' => count($redeemableRewardsList),
                    'redeemable_limit' => $redeemableLimit,
                    'redeemable_offset' => $redeemableOffset,
                    'total_formatted_rewards' => $formattedRewards->count(),
                    'member_points' => $memberPoints,
                    'challenge_rewards_details' => array_map(function($r) {
                        return [
                            'id' => $r['id'] ?? null,
                            'item_id' => $r['item_id'] ?? null,
                            'item_name' => $r['item_name'] ?? null,
                            'serial_code' => $r['serial_code'] ?? null,
                            'reward_claimed' => $r['reward_claimed'] ?? null
                        ];
                    }, $challengeRewardsList)
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'challenge_rewards' => $challengeRewardsList,
                        'redeemable_rewards' => $redeemableRewardsList,
                    ],
                    'pagination' => [
                        'challenge_rewards' => [
                            'total' => $totalChallengeCount,
                            'limit' => $challengeLimit,
                            'offset' => $challengeOffset,
                            'has_more' => ($challengeOffset + $challengeLimit) < $totalChallengeCount
                        ],
                        'redeemable_rewards' => [
                            'total' => $totalRedeemableCount,
                            'limit' => $redeemableLimit,
                            'offset' => $redeemableOffset,
                            'has_more' => ($redeemableOffset + $redeemableLimit) < $totalRedeemableCount
                        ]
                    ],
                    'message' => 'Rewards retrieved successfully'
                ]);
            } else {
                // For home screen: return only regular rewards from member_apps_rewards table
                // Don't include challenge rewards - only rewards from table
                $allRewards = $formattedRewards->filter(function($reward) {
                    return $reward !== null; // Remove null values (filtered out rewards)
                });
                
                \Log::info('Home screen rewards', [
                    'total_rewards' => $rewards->count(),
                    'formatted_rewards_count' => $formattedRewards->count(),
                    'filtered_rewards_count' => $allRewards->count(),
                    'member_points' => $memberPoints,
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $allRewards->values()->all(),
                    'message' => 'Rewards retrieved successfully'
                ]);
            }
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

    /**
     * Claim reward from challenge
     * Generate serial code and mark reward as claimed
     */
    public function claimChallengeReward(Request $request, $challengeId)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Find challenge progress
            $progress = MemberAppsChallengeProgress::where('member_id', $member->id)
                ->where('challenge_id', $challengeId)
                ->where('is_completed', true)
                ->where('reward_claimed', false)
                ->with('challenge')
                ->first();

            if (!$progress) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge not completed or reward already claimed'
                ], 400);
            }

            $challenge = $progress->challenge;
            if (!$challenge) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge not found'
                ], 404);
            }

            // Check if reward expired
            if ($progress->reward_expires_at && $progress->reward_expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reward has expired'
                ], 400);
            }

            // Get reward rules
            $rules = $challenge->rules;
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }

            $serialCode = null;
            $rewardType = null;
            $rewardData = [];
            $rewardProcessed = false; // Track if reward was successfully processed

            \Log::info('Processing challenge reward claim', [
                'member_id' => $member->id,
                'challenge_id' => $challengeId,
                'rules' => $rules,
                'has_reward_type' => isset($rules['reward_type']),
                'has_reward_value' => isset($rules['reward_value']),
                'points_reward' => $challenge->points_reward ?? null
            ]);

            // Handle different reward types
            if (isset($rules['reward_type'])) {
                $rewardType = $rules['reward_type'];
                
                if ($rewardType === 'item' && isset($rules['reward_value'])) {
                    // Item reward - generate 8-character unique serial code
                    $serialCode = $this->generateUniqueChallengeSerialCode($challengeId, $member->id, $progress->id);
                    
                    // Get item IDs
                    $itemIds = is_array($rules['reward_value']) 
                        ? $rules['reward_value'] 
                        : [$rules['reward_value']];
                    
                    // Check reward_item_selection: 'all' = semua items, 'single' = hanya 1 item
                    $rewardItemSelection = $rules['reward_item_selection'] ?? 'all'; // Default: all
                    
                    // If single selection, get selected item from request or pick random
                    $selectedItemId = null;
                    if ($rewardItemSelection === 'single' && count($itemIds) > 1) {
                        // Get selected item from request if provided
                        $requestSelectedItemId = $request->input('selected_item_id');
                        if ($requestSelectedItemId && in_array($requestSelectedItemId, $itemIds)) {
                            $selectedItemId = $requestSelectedItemId;
                        } else {
                            // Random pick one item if not provided
                            $selectedItemId = $itemIds[array_rand($itemIds)];
                        }
                        $itemIds = [$selectedItemId];
                    } else {
                        $selectedItemId = $itemIds[0];
                    }
                    
                    // Store all selected item IDs
                    $rewardData['item_ids'] = $itemIds;
                    $rewardData['item_id'] = $itemIds[0]; // First item for backward compatibility
                    $rewardData['reward_item_selection'] = $rewardItemSelection;
                    
                    // Store selected item ID in progress_data for single selection
                    $progressData = $progress->progress_data ?? [];
                    if ($rewardItemSelection === 'single') {
                        $progressData['selected_reward_item_id'] = $selectedItemId;
                        $progress->progress_data = $progressData;
                    }
                    
                    // Get item name for notification
                    $itemName = 'Free Item';
                    try {
                        $itemData = DB::selectOne(
                            "SELECT name FROM items WHERE id = ? LIMIT 1",
                            [$selectedItemId]
                        );
                        if ($itemData && $itemData->name) {
                            $itemName = $itemData->name;
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Error getting item name for challenge notification', [
                            'item_id' => $selectedItemId,
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                    // Dispatch event for push notification (free item reward)
                    try {
                        event(new ChallengeCompleted(
                            $member,
                            $challengeId,
                            $challenge->title,
                            'item',
                            [
                                'item_id' => $selectedItemId,
                                'item_name' => $itemName,
                                'serial_code' => $serialCode,
                            ]
                        ));
                    } catch (\Exception $e) {
                        \Log::error('Error dispatching ChallengeCompleted event for free item', [
                            'member_id' => $member->id,
                            'challenge_id' => $challengeId,
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                    // Item reward successfully processed
                    $rewardProcessed = true;
                    
                } elseif ($rewardType === 'point' || $rewardType === 'points') {
                    // Point reward - add points directly to member
                    if (!isset($rules['reward_value'])) {
                        \Log::warning('Challenge point reward has no reward_value', [
                            'member_id' => $member->id,
                            'challenge_id' => $challengeId,
                            'reward_type' => $rewardType,
                            'rules' => $rules
                        ]);
                        // Don't process if reward_value is missing
                        $rewardProcessed = false;
                    } else {
                    $pointAmount = is_array($rules['reward_value']) 
                        ? (int)($rules['reward_value'][0] ?? 0) 
                        : (int)$rules['reward_value'];
                    
                        if ($pointAmount <= 0) {
                            \Log::warning('Challenge point reward amount is invalid', [
                                'member_id' => $member->id,
                                'challenge_id' => $challengeId,
                                'point_amount' => $pointAmount,
                                'reward_value' => $rules['reward_value']
                            ]);
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid point reward amount. Please contact support.',
                                'data' => ['points_added' => 0]
                            ], 400);
                        }
                    
                    if ($pointAmount > 0) {
                        try {
                            \Log::info('Attempting to add challenge point reward', [
                                'member_id' => $member->id,
                                'member_model' => get_class($member),
                                'challenge_id' => $challengeId,
                                'points' => $pointAmount
                            ]);
                            
                            // Use PointEarningService to add points as bonus
                            $pointService = app(\App\Services\PointEarningService::class);
                            $result = $pointService->earnBonusPoints(
                                $member->id,
                                'challenge_reward',
                                $pointAmount,
                                365, // Points expire in 1 year (365 days)
                                "CHALLENGE-{$challengeId}-{$progress->id}" // Reference ID with progress ID to make it unique
                            );
                            
                            if ($result) {
                                $rewardData['points_added'] = $pointAmount;
                                $rewardData['points_earned'] = $result['points_earned'] ?? $pointAmount;
                                $rewardData['total_points'] = $result['total_points'] ?? null;
                                \Log::info('Challenge point reward added successfully', [
                                    'member_id' => $member->id,
                                    'challenge_id' => $challengeId,
                                    'points' => $pointAmount,
                                    'result' => $result
                                ]);

                                // Dispatch event for push notification (point reward)
                                try {
                                    if (isset($result['transaction'])) {
                                        // Dispatch PointEarned event (for backward compatibility)
                                        event(new PointEarned(
                                            $member,
                                            $result['transaction'],
                                            $pointAmount,
                                            'challenge',
                                            [
                                                'challenge_id' => $challengeId,
                                                'challenge_title' => $challenge->title,
                                            ]
                                        ));
                                        
                                        // Also dispatch ChallengeCompleted event for consistency
                                        event(new ChallengeCompleted(
                                            $member,
                                            $challengeId,
                                            $challenge->title,
                                            'point',
                                            [
                                                'points' => $pointAmount,
                                                'points_earned' => $result['points_earned'] ?? $pointAmount,
                                                'total_points' => $result['total_points'] ?? null,
                                            ]
                                        ));
                                    }
                                } catch (\Exception $e) {
                                    \Log::error('Error dispatching events for challenge point reward', [
                                        'member_id' => $member->id,
                                        'challenge_id' => $challengeId,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                                
                                // Point reward successfully processed
                                $rewardProcessed = true;
                            } else {
                                \Log::warning('Challenge point reward returned null - points were not added', [
                                    'member_id' => $member->id,
                                    'challenge_id' => $challengeId,
                                    'points' => $pointAmount,
                                    'reference_id' => "CHALLENGE-{$challengeId}-{$progress->id}"
                                ]);
                                // Points were not added - don't mark reward as claimed
                                $rewardData['points_added'] = 0;
                                $rewardData['points_error'] = 'Points may have already been added or failed to add';
                                $rewardProcessed = false;
                                
                                // Return error response instead of continuing
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Failed to add points. Points may have already been added for this challenge.',
                                    'data' => $rewardData
                                ], 400);
                            }
                        } catch (\Exception $e) {
                            \Log::error('Error adding challenge point reward', [
                                'member_id' => $member->id,
                                'challenge_id' => $challengeId,
                                'points' => $pointAmount,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            return response()->json([
                                'success' => false,
                                'message' => 'Failed to add points: ' . $e->getMessage()
                            ], 500);
                        }
                        }
                    }
                    
                } elseif ($rewardType === 'voucher' && isset($rules['reward_value'])) {
                    // Voucher reward - assign voucher to member
                    $voucherIds = is_array($rules['reward_value']) ? $rules['reward_value'] : [$rules['reward_value']];
                    
                    // Check reward_item_selection: 'all' = semua vouchers, 'single' = hanya 1 voucher
                    $rewardItemSelection = $rules['reward_item_selection'] ?? 'all'; // Default: all
                    
                    // If single selection, get selected voucher from request or pick first
                    $selectedVoucherId = null;
                    if ($rewardItemSelection === 'single' && count($voucherIds) > 1) {
                        // Get selected voucher from request if provided
                        $requestSelectedVoucherId = $request->input('selected_voucher_id');
                        if ($requestSelectedVoucherId && in_array($requestSelectedVoucherId, $voucherIds)) {
                            $selectedVoucherId = $requestSelectedVoucherId;
                        } else {
                            // Pick first voucher if not provided
                            $selectedVoucherId = $voucherIds[0];
                        }
                        $voucherIds = [$selectedVoucherId];
                    } else {
                        $selectedVoucherId = $voucherIds[0];
                    }
                    
                    // Store selected voucher ID in progress_data for single selection
                    $progressData = $progress->progress_data ?? [];
                    if ($rewardItemSelection === 'single') {
                        $progressData['selected_reward_voucher_id'] = $selectedVoucherId;
                        $progress->progress_data = $progressData;
                    }
                    
                    // Assign vouchers to member
                    $assignedVouchers = [];
                    foreach ($voucherIds as $voucherId) {
                        // Get voucher details
                        $voucher = MemberAppsVoucher::find($voucherId);
                        if (!$voucher || !$voucher->is_active) {
                            \Log::warning('Voucher not found or inactive for challenge reward', [
                                'voucher_id' => $voucherId,
                                'challenge_id' => $challengeId,
                                'member_id' => $member->id
                            ]);
                            continue;
                        }
                        
                        // Check if member already has this voucher (active)
                        $existingVoucher = MemberAppsMemberVoucher::where('member_id', $member->id)
                            ->where('voucher_id', $voucherId)
                            ->where('status', 'active')
                            ->first();
                        
                        if ($existingVoucher) {
                            \Log::info('Member already has active voucher from challenge', [
                                'member_id' => $member->id,
                                'voucher_id' => $voucherId,
                                'challenge_id' => $challengeId
                            ]);
                            $assignedVouchers[] = [
                                'voucher_id' => $voucherId,
                                'voucher_code' => $existingVoucher->voucher_code,
                                'serial_code' => $existingVoucher->serial_code,
                                'already_had' => true
                            ];
                            continue;
                        }
                        
                        // Generate unique voucher code and serial code (same format as distribution)
                        $voucherCode = $this->generateVoucherCode($voucherId, $member->id);
                        $serialCode = $this->generateVoucherSerialCode($voucherId, $member->id);
                        
                        // Create member voucher
                        $memberVoucher = MemberAppsMemberVoucher::create([
                            'voucher_id' => $voucherId,
                            'member_id' => $member->id,
                            'voucher_code' => $voucherCode,
                            'serial_code' => $serialCode,
                            'status' => 'active',
                            'expires_at' => $voucher->valid_until,
                        ]);
                        
                        // Refresh member and voucher relationships
                        $memberVoucher->load(['member', 'voucher']);
                        
                        // Dispatch event for push notification
                        try {
                            event(new VoucherReceived(
                                $memberVoucher->member,
                                $memberVoucher
                            ));
                        } catch (\Exception $e) {
                            \Log::error('Error dispatching VoucherReceived event for challenge reward voucher', [
                                'member_id' => $member->id,
                                'voucher_id' => $voucherId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                        
                        $assignedVouchers[] = [
                            'voucher_id' => $voucherId,
                            'voucher_name' => $voucher->name,
                            'voucher_code' => $voucherCode,
                            'serial_code' => $serialCode,
                            'already_had' => false
                        ];
                        
                        \Log::info('Voucher assigned to member from challenge reward', [
                            'member_id' => $member->id,
                            'voucher_id' => $voucherId,
                            'challenge_id' => $challengeId,
                            'voucher_code' => $voucherCode,
                            'serial_code' => $serialCode
                        ]);
                    }
                    
                    $rewardData['voucher_ids'] = $voucherIds;
                    $rewardData['voucher_id'] = $voucherIds[0]; // First voucher for backward compatibility
                    $rewardData['assigned_vouchers'] = $assignedVouchers;
                    $rewardData['reward_item_selection'] = $rewardItemSelection;
                    
                    // Voucher reward successfully processed (even if some vouchers already existed)
                    $rewardProcessed = true;
                    
                    // Get voucher name for notification (use first assigned voucher)
                    $voucherName = 'Voucher';
                    if (!empty($assignedVouchers)) {
                        $firstVoucher = $assignedVouchers[0];
                        $voucherName = $firstVoucher['voucher_name'] ?? 'Voucher';
                    }
                    
                    // Dispatch event for push notification (voucher reward)
                    try {
                        event(new ChallengeCompleted(
                            $member,
                            $challengeId,
                            $challenge->title,
                            'voucher',
                            [
                                'voucher_id' => $voucherIds[0],
                                'voucher_name' => $voucherName,
                                'voucher_code' => !empty($assignedVouchers) ? ($assignedVouchers[0]['voucher_code'] ?? null) : null,
                            ]
                        ));
                    } catch (\Exception $e) {
                        \Log::error('Error dispatching ChallengeCompleted event for voucher', [
                            'member_id' => $member->id,
                            'challenge_id' => $challengeId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Also check legacy points_reward field (only if not already handled by reward_type)
            // Skip legacy points if reward_type is already 'point' or 'points'
            // Only process legacy points if reward_type is not 'point'/'points' OR if reward_type is 'point'/'points' but reward_value is missing
            $shouldProcessLegacyPoints = false;
            if ($challenge->points_reward && $challenge->points_reward > 0) {
                if ($rewardType !== 'point' && $rewardType !== 'points') {
                    // No point reward type set, use legacy
                    $shouldProcessLegacyPoints = true;
                } elseif (($rewardType === 'point' || $rewardType === 'points') && !isset($rules['reward_value'])) {
                    // Point reward type set but no reward_value, fallback to legacy
                    \Log::info('Point reward type set but no reward_value, falling back to legacy points_reward', [
                        'member_id' => $member->id,
                        'challenge_id' => $challengeId,
                        'reward_type' => $rewardType,
                        'legacy_points' => $challenge->points_reward
                    ]);
                    $shouldProcessLegacyPoints = true;
                }
            }
            
            if ($shouldProcessLegacyPoints) {
                try {
                    \Log::info('Attempting to add challenge legacy point reward', [
                        'member_id' => $member->id,
                        'challenge_id' => $challengeId,
                        'points' => $challenge->points_reward
                    ]);
                    
                    $pointService = app(\App\Services\PointEarningService::class);
                    $result = $pointService->earnBonusPoints(
                        $member->id,
                        'challenge_reward',
                        $challenge->points_reward,
                        365, // Points expire in 1 year
                        "CHALLENGE-{$challengeId}-{$progress->id}-LEGACY" // Reference ID with progress ID
                    );
                    
                    if ($result) {
                        $rewardData['points_added'] = ($rewardData['points_added'] ?? 0) + $challenge->points_reward;
                        $rewardData['points_earned'] = $result['points_earned'] ?? $challenge->points_reward;
                        $rewardData['total_points'] = $result['total_points'] ?? null;
                        \Log::info('Challenge legacy point reward added successfully', [
                            'member_id' => $member->id,
                            'challenge_id' => $challengeId,
                            'points' => $challenge->points_reward,
                            'result' => $result
                        ]);

                        // Dispatch event for push notification (legacy challenge point reward)
                        try {
                            if (isset($result['transaction'])) {
                                // Dispatch PointEarned event (for backward compatibility)
                                event(new PointEarned(
                                    $member,
                                    $result['transaction'],
                                    $challenge->points_reward,
                                    'challenge',
                                    [
                                        'challenge_id' => $challengeId,
                                        'challenge_title' => $challenge->title,
                                    ]
                                ));
                                
                                // Also dispatch ChallengeCompleted event for consistency
                                event(new ChallengeCompleted(
                                    $member,
                                    $challengeId,
                                    $challenge->title,
                                    'point',
                                    [
                                        'points' => $challenge->points_reward,
                                        'points_earned' => $result['points_earned'] ?? $challenge->points_reward,
                                        'total_points' => $result['total_points'] ?? null,
                                    ]
                                ));
                            }
                        } catch (\Exception $e) {
                            \Log::error('Error dispatching events for legacy challenge point reward', [
                                'member_id' => $member->id,
                                'challenge_id' => $challengeId,
                                'error' => $e->getMessage()
                            ]);
                        }
                        
                        // Legacy point reward successfully processed
                        $rewardProcessed = true;
                    } else {
                        \Log::warning('Challenge legacy point reward returned null - points were not added', [
                            'member_id' => $member->id,
                            'challenge_id' => $challengeId,
                            'points' => $challenge->points_reward,
                            'reference_id' => "CHALLENGE-{$challengeId}-{$progress->id}-LEGACY",
                            'reward_type' => $rewardType
                        ]);
                        // If this is a fallback from point/points reward type, don't mark as processed
                        // If this is a true legacy challenge (no reward_type), also don't mark as processed if points failed
                        if ($rewardType === 'point' || $rewardType === 'points') {
                            // This was a fallback from point/points reward type, so don't mark as processed
                            $rewardProcessed = false;
                        } else {
                            // True legacy challenge - don't mark as processed if points failed
                            $rewardProcessed = false;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error adding challenge legacy point reward', [
                        'member_id' => $member->id,
                        'challenge_id' => $challengeId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Mark reward as claimed only if reward was successfully processed
            // For point rewards (both new and legacy), only mark as claimed if points were actually added
            // For other reward types (item, voucher), mark as claimed if rewardProcessed is true
            // If no reward_type is set but legacy points_reward exists, mark as claimed if legacy points were added
            $shouldMarkAsClaimed = false;
            
            \Log::info('Determining if reward should be marked as claimed', [
                'member_id' => $member->id,
                'challenge_id' => $challengeId,
                'reward_type' => $rewardType,
                'reward_processed' => $rewardProcessed,
                'has_legacy_points' => ($challenge->points_reward && $challenge->points_reward > 0),
                'legacy_points_value' => $challenge->points_reward ?? null
            ]);
            
            if ($rewardType === 'point' || $rewardType === 'points') {
                // For point rewards, only mark as claimed if points were successfully added
                $shouldMarkAsClaimed = $rewardProcessed;
                \Log::info('Point reward claim decision', [
                    'member_id' => $member->id,
                    'challenge_id' => $challengeId,
                    'reward_type' => $rewardType,
                    'reward_processed' => $rewardProcessed,
                    'should_mark_as_claimed' => $shouldMarkAsClaimed
                ]);
            } elseif ($rewardType === 'item' || $rewardType === 'voucher') {
                // For item/voucher rewards, mark as claimed if reward was processed
                $shouldMarkAsClaimed = $rewardProcessed;
            } elseif (!$rewardType && $challenge->points_reward && $challenge->points_reward > 0) {
                // Legacy challenge with points_reward but no reward_type - mark as claimed if legacy points were added
                $shouldMarkAsClaimed = $rewardProcessed;
            } else {
                // No reward type or unknown reward type - mark as claimed for backward compatibility
                // (some old challenges might not have reward_type set)
                // BUT: if reward_type is set to something unknown, don't mark as claimed if reward wasn't processed
                if ($rewardType) {
                    // Unknown reward type - don't mark as claimed if not processed
                    $shouldMarkAsClaimed = $rewardProcessed;
                } else {
                    // No reward type at all - mark as claimed for backward compatibility
                    $shouldMarkAsClaimed = true;
                }
            }
            
            \Log::info('Final claim decision', [
                'member_id' => $member->id,
                'challenge_id' => $challengeId,
                'should_mark_as_claimed' => $shouldMarkAsClaimed,
                'reward_type' => $rewardType,
                'reward_processed' => $rewardProcessed
            ]);
            
            if ($shouldMarkAsClaimed) {
            $progress->reward_claimed = true;
            $progress->reward_claimed_at = now();
            if ($serialCode) {
                $progress->serial_code = $serialCode;
            }
            $progress->save();

            \Log::info('Challenge reward claimed', [
                'member_id' => $member->id,
                'challenge_id' => $challengeId,
                'serial_code' => $serialCode,
                    'reward_type' => $rewardType,
                    'reward_processed' => $rewardProcessed
                ]);
            } else {
                \Log::warning('Challenge reward not marked as claimed - reward processing failed', [
                    'member_id' => $member->id,
                    'challenge_id' => $challengeId,
                    'reward_type' => $rewardType,
                    'reward_data' => $rewardData
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process reward. Points were not added. Please try again or contact support.',
                    'data' => $rewardData
                ], 400);
            }

            // Refresh member to get updated points
            $member->refresh();
            
            $responseData = [
                'serial_code' => $serialCode,
                'challenge_id' => $challengeId,
                'challenge_title' => $challenge->title,
                'reward_type' => $rewardType,
                'reward_data' => $rewardData,
                'claimed_at' => $progress->reward_claimed_at->format('Y-m-d H:i:s'),
                'total_points' => $member->just_points ?? 0, // Include updated total points in response
            ];
            
            // If voucher reward, include voucher details in response
            if ($rewardType === 'voucher' && isset($rewardData['assigned_vouchers'])) {
                $responseData['vouchers'] = $rewardData['assigned_vouchers'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Reward claimed successfully',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error claiming challenge reward: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to claim reward: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate and redeem reward by serial code (for POS)
     */
    public function validateSerialCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'serial_code' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $serialCode = $request->input('serial_code');
            
            // Check if serial code starts with 'CH' (challenge reward) or 'RW' (regular reward)
            if (substr($serialCode, 0, 2) === 'CH') {
                // Challenge reward
                $progress = MemberAppsChallengeProgress::where('serial_code', $serialCode)
                    ->with(['member', 'challenge'])
                    ->first();

                if (!$progress) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial code not found'
                    ], 404);
                }

                // Check if already redeemed (not just claimed)
                if ($progress->reward_redeemed_at) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reward already redeemed',
                        'data' => [
                            'serial_code' => $serialCode,
                            'redeemed_at' => $progress->reward_redeemed_at->format('Y-m-d H:i:s'),
                            'member_id' => $progress->member_id,
                            'member_name' => $progress->member ? $progress->member->nama_lengkap : null,
                        ]
                    ], 400);
                }
                
                // Check if reward is claimed (must be claimed before can be redeemed)
                if (!$progress->reward_claimed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reward not yet claimed. Please claim the reward first in the app.',
                    ], 400);
                }

                // Check if expired
                if ($progress->reward_expires_at && $progress->reward_expires_at->isPast()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reward has expired',
                        'data' => [
                            'serial_code' => $serialCode,
                            'expired_at' => $progress->reward_expires_at->format('Y-m-d H:i:s'),
                        ]
                    ], 400);
                }

                // Check if challenge is completed
                if (!$progress->is_completed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Challenge not completed yet'
                    ], 400);
                }

                // Get reward item details and calculate discount amount
                $challenge = $progress->challenge;
                if (!$challenge) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Challenge not found'
                    ], 404);
                }
                
                // Get item price for discount
                $discountAmount = 0;
                $rules = $challenge->rules;
                if (is_string($rules)) {
                    $rules = json_decode($rules, true);
                }
                
                if (isset($rules['reward_type']) && $rules['reward_type'] === 'item' && isset($rules['reward_value'])) {
                    $itemIds = is_array($rules['reward_value']) 
                        ? $rules['reward_value'] 
                        : [$rules['reward_value']];
                    
                    // Get selected item ID for single selection
                    $selectedItemId = null;
                    if (isset($rules['reward_item_selection']) && $rules['reward_item_selection'] === 'single' && count($itemIds) > 1) {
                        $progressData = $progress->progress_data ?? [];
                        $selectedItemId = $progressData['selected_reward_item_id'] ?? $itemIds[0];
                    } else {
                        $selectedItemId = $itemIds[0];
                    }
                    
                    // Get item price from item_prices (priority: all, since we don't have outlet_id at claim time)
                    $itemPrice = DB::table('item_prices')
                        ->where('item_id', $selectedItemId)
                        ->where('availability_price_type', 'all')
                        ->orderByDesc('id')
                        ->first();
                    
                    if ($itemPrice) {
                        $discountAmount = $itemPrice->price ?? 0;
                        // Round up to nearest 100 (same as ItemController)
                        $discountAmount = ceil($discountAmount / 100) * 100;
                    } else {
                        $discountAmount = 0;
                    }
                }

                $rules = $challenge->rules;
                if (is_string($rules)) {
                    $rules = json_decode($rules, true);
                }

                $rewardItems = [];
                if (isset($rules['reward_type']) && $rules['reward_type'] === 'item' && isset($rules['reward_value'])) {
                    $itemIds = is_array($rules['reward_value']) 
                        ? $rules['reward_value'] 
                        : [$rules['reward_value']];
                    
                    // Check reward_item_selection: 'all' = semua items, 'single' = hanya 1 item
                    $rewardItemSelection = $rules['reward_item_selection'] ?? 'all'; // Default: all
                    
                    // If single selection, get the selected item from progress_data
                    if ($rewardItemSelection === 'single' && count($itemIds) > 1) {
                        $progressData = $progress->progress_data ?? [];
                        $selectedItemId = $progressData['selected_reward_item_id'] ?? $itemIds[0];
                        $itemIds = [$selectedItemId];
                    }
                    
                    // Get all selected items
                    $items = DB::table('items')
                        ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                        ->whereIn('items.id', $itemIds)
                        ->select('items.*', 'categories.name as category_name')
                        ->get();
                    
                    foreach ($items as $item) {
                        $rewardItems[] = [
                            'id' => $item->id,
                            'name' => $item->name,
                            'sku' => $item->sku,
                            'description' => $item->description,
                            'category_name' => $item->category_name,
                        ];
                    }
                }
                
                // For backward compatibility, also include single item
                $rewardItem = !empty($rewardItems) ? $rewardItems[0] : null;

                return response()->json([
                    'success' => true,
                    'message' => 'Serial code valid',
                    'data' => [
                        'serial_code' => $serialCode,
                        'reward_type' => 'challenge',
                        'member_id' => $progress->member_id,
                        'member_name' => $progress->member ? $progress->member->nama_lengkap : null,
                        'member_phone' => $progress->member ? $progress->member->mobile_phone : null,
                        'challenge_id' => $progress->challenge_id,
                        'challenge_title' => $challenge->title,
                        'item' => $rewardItem, // Backward compatibility - first item
                        'items' => $rewardItems, // All items (for multiple rewards)
                        'reward_item_selection' => $rules['reward_item_selection'] ?? 'all',
                        'points_required' => 0,
                        'discount_amount' => $discountAmount, // Discount amount for POS
                        'is_claimed' => false,
                    ]
                ]);

            } elseif (substr($serialCode, 0, 3) === 'JTS') {
                // Reward point (format: JTS-...)
                $reward = MemberAppsReward::where('serial_code', $serialCode)
                    ->with('item')
                    ->first();

                if (!$reward) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial code not found'
                    ], 404);
                }

                // Check if reward is active
                if (!$reward->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reward is not active'
                    ], 400);
                }

                $item = $reward->item;
                $rewardItem = null;
                $discountAmount = 0;
                
                if ($item) {
                    $rewardItem = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'description' => $item->description,
                    ];
                    
                    // Get item price from item_prices (priority: all, since we don't have outlet_id at validation time)
                    $itemPrice = DB::table('item_prices')
                        ->where('item_id', $item->id)
                        ->where('availability_price_type', 'all')
                        ->orderByDesc('id')
                        ->first();
                    
                    if ($itemPrice) {
                        $discountAmount = $itemPrice->price ?? 0;
                        // Round up to nearest 100 (same as ItemController)
                        $discountAmount = ceil($discountAmount / 100) * 100;
                    } else {
                        $discountAmount = 0;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Serial code valid',
                    'data' => [
                        'serial_code' => $serialCode,
                        'reward_type' => 'point',
                        'reward_id' => $reward->id,
                        'item' => $rewardItem,
                        'item_id' => $item ? $item->id : null,
                        'item_name' => $item ? $item->name : null,
                        'points_required' => $reward->points_required,
                        'discount_amount' => $discountAmount, // Discount amount for POS
                        'is_claimed' => false,
                    ]
                ]);

            } elseif (substr($serialCode, 0, 2) === 'RW') {
                // Regular reward (legacy format)
                $reward = MemberAppsReward::where('serial_code', $serialCode)
                    ->with('item')
                    ->first();

                if (!$reward) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial code not found'
                    ], 404);
                }

                // Check if reward is active
                if (!$reward->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reward is not active'
                    ], 400);
                }

                $item = $reward->item;
                $rewardItem = null;
                $discountAmount = 0;
                
                if ($item) {
                    $rewardItem = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'description' => $item->description,
                    ];
                    
                    // Get item price from item_prices (priority: all, since we don't have outlet_id at validation time)
                    $itemPrice = DB::table('item_prices')
                        ->where('item_id', $item->id)
                        ->where('availability_price_type', 'all')
                        ->orderByDesc('id')
                        ->first();
                    
                    if ($itemPrice) {
                        $discountAmount = $itemPrice->price ?? 0;
                        // Round up to nearest 100 (same as ItemController)
                        $discountAmount = ceil($discountAmount / 100) * 100;
                    } else {
                        $discountAmount = 0;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Serial code valid',
                    'data' => [
                        'serial_code' => $serialCode,
                        'reward_type' => 'regular',
                        'reward_id' => $reward->id,
                        'item' => $rewardItem,
                        'points_required' => $reward->points_required,
                        'discount_amount' => $discountAmount, // Discount amount for POS
                        'is_claimed' => false,
                    ]
                ]);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid serial code format'
                ], 400);
            }

        } catch (\Exception $e) {
            \Log::error('Error validating serial code', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'serial_code' => $request->input('serial_code')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate serial code: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Redeem reward by serial code (for POS)
     */
    public function redeemBySerialCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'serial_code' => 'required|string',
                'member_id' => 'nullable|string', // Required for regular reward (RW), optional for challenge reward (CH)
                'outlet_id' => 'nullable|integer', // Optional: can use outlet_code instead
                'outlet_code' => 'nullable|string', // Optional: qr_code from tbl_data_outlet (alternative to outlet_id)
                'order_id' => 'nullable|string', // Optional: order/transaction ID from POS to track which transaction this redemption belongs to
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $serialCode = $request->input('serial_code');
            
            // Get outlet_id from outlet_id or outlet_code (qr_code)
            $outletId = $request->input('outlet_id');
            $outletCode = $request->input('outlet_code');
            
            if (!$outletId && $outletCode) {
                // Convert outlet_code (qr_code) to outlet_id
                $outlet = DB::table('tbl_data_outlet')
                    ->where('qr_code', $outletCode)
                    ->where('is_fc', 0)
                    ->select('id_outlet')
                    ->first();
                
                if ($outlet) {
                    $outletId = $outlet->id_outlet;
                    \Log::info('Outlet code converted to outlet_id', [
                        'outlet_code' => $outletCode,
                        'outlet_id' => $outletId
                    ]);
                } else {
                    \Log::warning('Outlet not found with code', [
                        'outlet_code' => $outletCode
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Outlet tidak ditemukan dengan kode: ' . $outletCode
                    ], 404);
                }
            }
            
            if (!$outletId) {
                \Log::warning('Outlet ID or code not provided', [
                    'outlet_id' => $request->input('outlet_id'),
                    'outlet_code' => $request->input('outlet_code')
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Outlet ID atau Outlet Code (qr_code) harus disediakan'
                ], 400);
            }
            
            \Log::info('Redeem reward with outlet', [
                'serial_code' => $serialCode,
                'outlet_id' => $outletId,
                'outlet_code' => $outletCode
            ]);
            
            // Check if serial code starts with 'CH' (challenge reward), 'JTS' (reward point), or 'RW' (regular reward)
            if (substr($serialCode, 0, 3) === 'JTS') {
                // Reward point (format: JTS-...)
                $reward = DB::table('member_apps_rewards as rewards')
                    ->join('items', 'rewards.item_id', '=', 'items.id')
                    ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                    ->where('rewards.serial_code', $serialCode)
                    ->where('rewards.is_active', 1)
                    ->select(
                        'rewards.id as reward_id',
                        'rewards.points_required',
                        'rewards.serial_code',
                        'items.id as item_id',
                        'items.name as item_name',
                        'items.sku',
                        'categories.name as category_name'
                    )
                    ->first();
                
                if (!$reward) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial code not found or reward is inactive'
                    ], 404);
                }
                
                // Get member_id from request
                $memberId = $request->input('member_id');
                
                if (!$memberId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member ID is required for reward point redemption. Please provide member_id in request.'
                    ], 400);
                }
                
                // Get member info
                $member = MemberAppsMember::where('id', $memberId)
                    ->orWhere('member_id', (string)$memberId)
                    ->first();
                
                if (!$member) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member not found'
                    ], 404);
                }
                
                // NOTE: Reward point (JTS-...) can be redeemed multiple times as long as member has enough points
                // No need to check if already redeemed - only check if member has enough points
                
                // Check if member has enough points (considering point_remainder)
                $memberPoints = $member->just_points ?? 0;
                $memberRemainder = $member->point_remainder ?? 0;
                $availablePoints = $memberPoints + floor($memberRemainder);
                
                if ($availablePoints < $reward->points_required) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient points',
                        'data' => [
                            'required_points' => $reward->points_required,
                            'current_points' => $memberPoints,
                            'available_points' => $availablePoints,
                            'shortage' => $reward->points_required - $availablePoints
                        ]
                    ], 400);
                }
                
                // Validate outlet for reward point
                $rewardOutlets = DB::table('member_apps_reward_outlets')
                    ->where('reward_id', $reward->reward_id)
                    ->pluck('outlet_id')
                    ->toArray();
                
                // If reward has specific outlets, validate
                if (!empty($rewardOutlets)) {
                    if (!in_array($outletId, $rewardOutlets)) {
                        // Get outlet names for error message
                        $allowedOutlets = DB::table('tbl_data_outlet')
                            ->whereIn('id_outlet', $rewardOutlets)
                            ->where('is_fc', 0)
                            ->pluck('nama_outlet')
                            ->toArray();
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Reward ini hanya bisa di-redeem di outlet tertentu',
                            'data' => [
                                'allowed_outlets' => $allowedOutlets,
                                'current_outlet_id' => $outletId
                            ]
                        ], 400);
                    }
                }
                
                // Get item price from item_prices with priority: outlet > region > all
                $regionId = null;
                if ($outletId) {
                    $outlet = DB::table('tbl_data_outlet')
                        ->where('id_outlet', $outletId)
                        ->select('region_id')
                        ->first();
                    if ($outlet && $outlet->region_id) {
                        $regionId = $outlet->region_id;
                    }
                }
                
                // Get price from item_prices with priority
                $itemPriceData = DB::table('item_prices')
                    ->where('item_id', $reward->item_id)
                    ->where(function($q) use ($regionId, $outletId) {
                        $q->where('availability_price_type', 'all');
                        if ($regionId) {
                            $q->orWhere(function($q2) use ($regionId) {
                                $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
                            });
                        }
                        if ($outletId) {
                            $q->orWhere(function($q2) use ($outletId) {
                                $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
                            });
                        }
                    })
                    ->orderByRaw("CASE 
                        WHEN availability_price_type = 'outlet' THEN 1
                        WHEN availability_price_type = 'region' THEN 2
                        ELSE 3 END")
                    ->orderByDesc('id')
                    ->first();
                
                $discountAmount = $itemPriceData ? ($itemPriceData->price ?? 0) : 0;
                // Round up to nearest 100
                $discountAmount = ceil($discountAmount / 100) * 100;
                
                // Deduct points from member (considering point_remainder)
                $pointEarningService = new \App\Services\PointEarningService();
                $deductResult = $pointEarningService->deductPoints($member, $reward->points_required);
                
                if (!$deductResult['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $deductResult['message'],
                    ], 400);
                }
                
                // Build reference_id: include order_id if provided, format: "serial_code|order_id" or just "serial_code"
                $orderId = $request->input('order_id');
                $referenceId = $serialCode;
                if ($orderId) {
                    $referenceId = $serialCode . '|' . $orderId;
                }
                
                // Create point transaction for redemption
                $pointTransaction = MemberAppsPointTransaction::create([
                    'member_id' => $member->id,
                    'transaction_type' => 'redeem',
                    'point_amount' => -$reward->points_required, // Negative for redemption
                    'reference_id' => $referenceId, // Format: "serial_code|order_id" or just "serial_code"
                    'channel' => 'redemption',
                    'transaction_date' => now()->toDateString(),
                    'description' => "Redeem reward: {$reward->item_name}",
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Update point earnings (remaining_points and is_fully_redeemed)
                // Using FIFO (First In First Out) - oldest expiry first
                try {
                    \Log::info('Starting point earnings redemption update', [
                        'member_id' => $member->id,
                        'point_transaction_id' => $pointTransaction->id,
                        'points_required' => $reward->points_required,
                    ]);

                    $pointEarningService = new \App\Services\PointEarningService();
                    $redemptionResult = $pointEarningService->redeemPointsFromEarnings(
                        $member->id,
                        $pointTransaction->id,
                        $reward->points_required,
                        'product', // redemption_type: 'product', 'discount-voucher', or 'cash'
                        [
                            'product_id' => $reward->item_id,
                            'product_name' => $reward->item_name,
                            'product_price' => $discountAmount,
                            'reference_id' => $referenceId,
                        ]
                    );

                    if (!$redemptionResult) {
                        \Log::warning('Failed to update point earnings for redemption - returned null', [
                            'member_id' => $member->id,
                            'point_transaction_id' => $pointTransaction->id,
                            'points_required' => $reward->points_required,
                        ]);
                        // Don't fail the redemption, just log warning
                    } else {
                        \Log::info('Point earnings redemption update successful', [
                            'member_id' => $member->id,
                            'point_transaction_id' => $pointTransaction->id,
                            'points_redeemed' => $redemptionResult['points_redeemed'] ?? null,
                            'redemption_id' => $redemptionResult['redemption']['id'] ?? null,
                            'details_count' => count($redemptionResult['redemption_details'] ?? []),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error updating point earnings for redemption', [
                        'member_id' => $member->id,
                        'point_transaction_id' => $pointTransaction->id,
                        'points_required' => $reward->points_required,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Don't fail the redemption, just log error
                }
                
                \Log::info('Reward point redeemed by POS', [
                    'serial_code' => $serialCode,
                    'member_id' => $member->id,
                    'reward_id' => $reward->reward_id,
                    'points_deducted' => $reward->points_required,
                    'new_balance' => $member->just_points
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Reward redeemed successfully',
                    'data' => [
                        'serial_code' => $serialCode,
                        'reward_type' => 'point',
                        'reward_id' => $reward->reward_id,
                        'item_id' => $reward->item_id,
                        'item_name' => $reward->item_name,
                        'points_required' => $reward->points_required,
                        'points_deducted' => $reward->points_required,
                        'member_points_after' => $member->just_points,
                        'discount_amount' => $discountAmount,
                    ]
                ]);
                
            } elseif (substr($serialCode, 0, 2) === 'CH') {
                // Challenge reward - mark as claimed
                $progress = MemberAppsChallengeProgress::where('serial_code', $serialCode)
                    ->first();

                if (!$progress) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial code not found'
                    ], 404);
                }

                // Check if already redeemed
                if ($progress->reward_redeemed_at) {
                    \Log::warning('Challenge reward already redeemed', [
                        'serial_code' => $serialCode,
                        'member_id' => $progress->member_id,
                        'challenge_id' => $progress->challenge_id,
                        'reward_redeemed_at' => $progress->reward_redeemed_at,
                        'reward_claimed' => $progress->reward_claimed,
                        'table' => 'member_apps_challenge_progress'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Reward already redeemed',
                        'data' => [
                            'serial_code' => $serialCode,
                            'redeemed_at' => $progress->reward_redeemed_at->format('Y-m-d H:i:s'),
                            'table' => 'member_apps_challenge_progress',
                            'column' => 'reward_redeemed_at'
                        ]
                    ], 400);
                }
                
                // Check if reward is claimed (must be claimed before can be redeemed)
                if (!$progress->reward_claimed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reward not yet claimed. Please claim the reward first in the app.'
                    ], 400);
                }

                // Validate outlet for challenge reward (outlet_id already resolved above)
                $challenge = $progress->challenge;
                if ($challenge) {
                    $rules = $challenge->rules;
                    if (is_string($rules)) {
                        $rules = json_decode($rules, true);
                    }
                    
                    // Check reward outlet scope
                    $rewardAllOutlets = $rules['reward_all_outlets'] ?? true;
                    $rewardOutletIds = $rules['reward_outlet_ids'] ?? [];
                    
                    if (!$rewardAllOutlets && !empty($rewardOutletIds)) {
                        // Reward can only be redeemed at specific outlets
                        if (!in_array($outletId, $rewardOutletIds)) {
                            // Get outlet names for error message
                            $allowedOutlets = DB::table('tbl_data_outlet')
                                ->whereIn('id_outlet', $rewardOutletIds)
                                ->where('is_fc', 0)
                                ->pluck('nama_outlet')
                                ->toArray();
                            
                            return response()->json([
                                'success' => false,
                                'message' => 'Reward ini hanya bisa di-redeem di outlet tertentu',
                                'data' => [
                                    'allowed_outlets' => $allowedOutlets,
                                    'current_outlet_id' => $outletId
                                ]
                            ], 400);
                        }
                    }
                }

                // Mark as redeemed (not claimed, because it's already claimed)
                $progress->reward_redeemed_at = now();
                $progress->redeemed_outlet_id = $outletId; // Store outlet ID where reward was redeemed
                $progress->save();

                \Log::info('Challenge reward redeemed by POS', [
                    'serial_code' => $serialCode,
                    'member_id' => $progress->member_id,
                    'challenge_id' => $progress->challenge_id,
                ]);

                // Get discount amount and item_id for challenge reward
                $discountAmount = 0;
                $rewardItemId = null;
                $rules = $challenge->rules ?? null;
                if ($rules) {
                    if (is_string($rules)) {
                        $rules = json_decode($rules, true);
                    }
                    
                    if (isset($rules['reward_type']) && $rules['reward_type'] === 'item' && isset($rules['reward_value'])) {
                        $itemIds = is_array($rules['reward_value']) 
                            ? $rules['reward_value'] 
                            : [$rules['reward_value']];
                        
                        // Get selected item ID for single selection
                        $selectedItemId = null;
                        if (isset($rules['reward_item_selection']) && $rules['reward_item_selection'] === 'single' && count($itemIds) > 1) {
                            $progressData = $progress->progress_data ?? [];
                            $selectedItemId = $progressData['selected_reward_item_id'] ?? $itemIds[0];
                        } else {
                            $selectedItemId = $itemIds[0];
                        }
                        
                        $rewardItemId = $selectedItemId;
                        
                        // Get item price from item_prices with priority: outlet > region > all
                        // Get region_id from outlet if available
                        $regionId = null;
                        if ($outletId) {
                            $outlet = DB::table('tbl_data_outlet')
                                ->where('id_outlet', $outletId)
                                ->select('region_id')
                                ->first();
                            if ($outlet && $outlet->region_id) {
                                $regionId = $outlet->region_id;
                            }
                        }
                        
                        // Get price from item_prices with priority
                        $itemPrice = DB::table('item_prices')
                            ->where('item_id', $selectedItemId)
                            ->where(function($q) use ($regionId, $outletId) {
                                $q->where('availability_price_type', 'all');
                                if ($regionId) {
                                    $q->orWhere(function($q2) use ($regionId) {
                                        $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
                                    });
                                }
                                if ($outletId) {
                                    $q->orWhere(function($q2) use ($outletId) {
                                        $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
                                    });
                                }
                            })
                            ->orderByRaw("CASE 
                                WHEN availability_price_type = 'outlet' THEN 1
                                WHEN availability_price_type = 'region' THEN 2
                                ELSE 3 END")
                            ->orderByDesc('id')
                            ->first();
                        
                        // Get item name
                        $item = DB::table('items')
                            ->where('id', $selectedItemId)
                            ->select('name')
                            ->first();
                        
                        if ($itemPrice) {
                            $discountAmount = $itemPrice->price ?? 0;
                            // Round up to nearest 100 (same as ItemController)
                            $discountAmount = ceil($discountAmount / 100) * 100;
                        } else {
                            $discountAmount = 0;
                        }
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Reward redeemed successfully',
                    'data' => [
                        'serial_code' => $serialCode,
                        'reward_type' => 'challenge',
                        'member_id' => $progress->member_id,
                        'item_id' => $rewardItemId, // Item ID for validation in POS
                        'discount_amount' => $discountAmount, // Discount amount for POS
                        'redeemed_at' => $progress->reward_redeemed_at->format('Y-m-d H:i:s'),
                    ]
                ]);

            } elseif (substr($serialCode, 0, 2) === 'RW') {
                // Regular reward - deduct points from member and provide discount
                // Serial code is stored in database, so we can query directly by serial_code
                
                // Get reward info by serial code (without price, will get from item_prices)
                $reward = DB::table('member_apps_rewards as rewards')
                    ->join('items', 'rewards.item_id', '=', 'items.id')
                    ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                    ->where('rewards.serial_code', $serialCode)
                    ->where('rewards.is_active', 1)
                    ->select(
                        'rewards.id as reward_id',
                        'rewards.item_id',
                        'rewards.points_required',
                        'rewards.serial_code',
                        'items.id as item_id',
                        'items.name as item_name',
                        'items.sku',
                        'categories.name as category_name'
                    )
                    ->first();
                
                if (!$reward) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial code not found or reward is inactive'
                    ], 404);
                }
                
                // Parse serial code to extract member_id if needed
                // Format: RW + 4 digit reward_id + 4 digit member_id + 4 char hash
                // But since we already have reward, we need to get member from request or parse from serial code
                // For now, we'll require member_id in request for regular rewards
                $memberId = $request->input('member_id');
                
                if (!$memberId) {
                    // Try to extract from serial code (if format is consistent)
                    if (strlen($serialCode) >= 10) {
                        $memberIdPart = substr($serialCode, 6, 4);
                        // Try to find member by ID (this is not reliable, better to require member_id in request)
                    }
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Member ID is required for regular reward redemption. Please provide member_id in request.'
                    ], 400);
                }
                
                // Get member info
                $member = MemberAppsMember::where('id', $memberId)
                    ->orWhere('member_id', (string)$memberId)
                    ->first();
                
                if (!$member) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member not found'
                    ], 404);
                }
                
                // Check if reward already redeemed (check if serial_code is used)
                // We can check by looking for point transaction with this serial_code as reference_id
                $existingRedemption = MemberAppsPointTransaction::where('member_id', $member->id)
                    ->where('transaction_type', 'redeem')
                    ->where('reference_id', $serialCode)
                    ->first();
                
                if ($existingRedemption) {
                    \Log::warning('Regular reward already redeemed', [
                        'serial_code' => $serialCode,
                        'member_id' => $member->id,
                        'reward_id' => $reward->reward_id,
                        'transaction_id' => $existingRedemption->id,
                        'transaction_date' => $existingRedemption->transaction_date,
                        'table' => 'member_apps_point_transactions'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Reward already redeemed',
                        'data' => [
                            'serial_code' => $serialCode,
                            'redeemed_at' => $existingRedemption->transaction_date->format('Y-m-d H:i:s'),
                            'table' => 'member_apps_point_transactions',
                            'transaction_id' => $existingRedemption->id,
                            'column' => 'reference_id'
                        ]
                    ], 400);
                }
                
                // Check if member has enough points (considering point_remainder)
                $memberPoints = $member->just_points ?? 0;
                $memberRemainder = $member->point_remainder ?? 0;
                $availablePoints = $memberPoints + floor($memberRemainder);
                
                if ($availablePoints < $reward->points_required) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient points',
                        'data' => [
                            'required_points' => $reward->points_required,
                            'current_points' => $memberPoints,
                            'available_points' => $availablePoints,
                            'shortage' => $reward->points_required - $availablePoints
                        ]
                    ], 400);
                }
                
                // Validate outlet for regular reward (outlet_id already resolved above)
                
                // Check reward outlet scope
                $rewardOutlets = DB::table('member_apps_reward_outlets')
                    ->where('reward_id', $reward->reward_id)
                    ->pluck('outlet_id')
                    ->toArray();
                
                // If reward has specific outlets, validate
                if (!empty($rewardOutlets)) {
                    if (!in_array($outletId, $rewardOutlets)) {
                        // Get outlet names for error message
                        $allowedOutlets = DB::table('tbl_data_outlet')
                            ->whereIn('id_outlet', $rewardOutlets)
                            ->where('is_fc', 0)
                            ->pluck('nama_outlet')
                            ->toArray();
                        
                        return response()->json([
                            'success' => false,
                            'message' => 'Reward ini hanya bisa di-redeem di outlet tertentu',
                            'data' => [
                                'allowed_outlets' => $allowedOutlets,
                                'current_outlet_id' => $outletId
                            ]
                        ], 400);
                    }
                }
                
                // Get item price from item_prices with priority: outlet > region > all
                // Get region_id from outlet if available
                $regionId = null;
                if ($outletId) {
                    $outlet = DB::table('tbl_data_outlet')
                        ->where('id_outlet', $outletId)
                        ->select('region_id')
                        ->first();
                    if ($outlet && $outlet->region_id) {
                        $regionId = $outlet->region_id;
                    }
                }
                
                // Get price from item_prices with priority
                $itemPriceData = DB::table('item_prices')
                    ->where('item_id', $reward->item_id)
                    ->where(function($q) use ($regionId, $outletId) {
                        $q->where('availability_price_type', 'all');
                        if ($regionId) {
                            $q->orWhere(function($q2) use ($regionId) {
                                $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
                            });
                        }
                        if ($outletId) {
                            $q->orWhere(function($q2) use ($outletId) {
                                $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
                            });
                        }
                    })
                    ->orderByRaw("CASE 
                        WHEN availability_price_type = 'outlet' THEN 1
                        WHEN availability_price_type = 'region' THEN 2
                        ELSE 3 END")
                    ->orderByDesc('id')
                    ->first();
                
                $itemPrice = $itemPriceData ? ($itemPriceData->price ?? 0) : 0;
                // Round up to nearest 100 (same as ItemController)
                $itemPrice = ceil($itemPrice / 100) * 100;
                
                // Deduct points from member
                DB::beginTransaction();
                try {
                    // Create point transaction for redemption
                    $pointTransaction = MemberAppsPointTransaction::create([
                        'member_id' => $member->id,
                        'transaction_type' => 'redeem',
                        'transaction_date' => now()->format('Y-m-d'),
                        'point_amount' => -$reward->points_required, // Negative for redemption
                        'transaction_amount' => null,
                        'earning_rate' => null,
                        'channel' => 'pos',
                        'reference_id' => $serialCode,
                        'description' => "Redeem reward: {$reward->item_name} (Serial: {$serialCode})",
                        'expires_at' => null,
                        'is_expired' => false,
                    ]);
                    
                    // Update member's total points (considering point_remainder)
                    $pointEarningService = new \App\Services\PointEarningService();
                    $deductResult = $pointEarningService->deductPoints($member, $reward->points_required);
                    
                    if (!$deductResult['success']) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => $deductResult['message'],
                        ], 400);
                    }
                    
                    // Mark serial code as used (we can clear it or add a flag)
                    // For now, we'll just rely on the point transaction to track redemption
                    
                    DB::commit();
                    
                    \Log::info('Regular reward redeemed by POS', [
                        'serial_code' => $serialCode,
                        'member_id' => $member->id,
                        'reward_id' => $reward->reward_id,
                        'item_id' => $reward->item_id,
                        'points_deducted' => $reward->points_required,
                        'discount_amount' => $itemPrice
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Reward redeemed successfully',
                        'data' => [
                            'serial_code' => $serialCode,
                            'reward_type' => 'regular',
                            'member_id' => $member->id,
                            'member_name' => $member->nama_lengkap ?? null,
                            'reward_id' => $reward->reward_id,
                            'item_id' => $reward->item_id,
                            'item_name' => $reward->item_name,
                            'item_sku' => $reward->sku,
                            'points_deducted' => $reward->points_required,
                            'remaining_points' => $member->just_points,
                            'discount_amount' => $itemPrice, // Discount amount for POS
                            'redeemed_at' => now()->format('Y-m-d H:i:s'),
                        ]
                    ]);
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    \Log::error('Error redeeming regular reward', [
                        'serial_code' => $serialCode,
                        'member_id' => $member->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to redeem reward: ' . $e->getMessage()
                    ], 500);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid serial code format'
                ], 400);
            }

        } catch (\Exception $e) {
            \Log::error('Error redeeming reward by serial code', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'serial_code' => $request->input('serial_code')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to redeem reward: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of brands from brands table for reward filtering
     */
    public function getBrandsForRewards()
    {
        try {
            $brands = DB::table('brands')
                ->orderBy('brand', 'asc')
                ->get()
                ->map(function ($brand) {
                    // Count outlets for this brand (excluding franchise outlets)
                    $outletCount = DB::table('tbl_data_outlet')
                        ->where('id_brand', $brand->id)
                        ->where('status', 'A')
                        ->where('is_outlet', 1)
                        ->where('is_fc', 0)
                        ->count();
                    
                    return [
                        'id' => $brand->id,
                        'name' => $brand->brand,
                        'logo' => $brand->logo ? 'https://ymsofterp.com/storage/' . ltrim($brand->logo, '/') : null,
                        'outlet_count' => $outletCount,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $brands,
                'message' => 'Brands retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting brands for rewards: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve brands: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get outlets per brand with redeemable rewards
     */
    public function getBrandOutletsWithRewards(Request $request, $brandId)
    {
        try {
            $member = null;
            $memberPoints = 0;
            $memberId = null;
            
            // Try to authenticate user from token if provided
            $token = $request->bearerToken();
            if ($token) {
                $accessToken = PersonalAccessToken::findToken($token);
                if ($accessToken) {
                    $member = $accessToken->tokenable;
                    if ($member instanceof MemberAppsMember) {
                        $memberPoints = $member->just_points ?? 0;
                        $memberId = $member->id;
                    }
                }
            }

            // Get brand info
            $brand = DB::table('brands')
                ->where('id', $brandId)
                ->first();

            if (!$brand) {
                return response()->json([
                    'success' => false,
                    'message' => 'Brand not found'
                ], 404);
            }

            // Get outlets for this brand (join tbl_data_outlet with brands via id_brand)
            $outlets = DB::table('tbl_data_outlet')
                ->where('id_brand', $brandId)
                ->where('status', 'A')
                ->where('is_outlet', 1)
                ->where('is_fc', 0)
                ->orderBy('nama_outlet', 'asc')
                ->get();

            $outletsWithRewards = [];

            \Log::info('Brand outlets with rewards - Starting', [
                'brand_id' => $brandId,
                'outlets_count' => $outlets->count(),
                'member_points' => $memberPoints,
            ]);

            foreach ($outlets as $outlet) {
                $outletId = $outlet->id_outlet;
                
                \Log::info('Processing outlet', [
                    'outlet_id' => $outletId,
                    'outlet_name' => $outlet->nama_outlet,
                ]);

                // Get rewards that are available at this outlet
                // Reward is available if:
                // 1. It's in member_apps_reward_outlets for this outlet, OR
                // 2. It's not in member_apps_reward_outlets at all (available at all outlets)
                
                // First, get all reward IDs that are available at this outlet
                $availableRewardIds = DB::table('member_apps_reward_outlets as ro')
                    ->join('tbl_data_outlet as o', 'ro.outlet_id', '=', 'o.id_outlet')
                    ->where('o.id_outlet', $outletId)
                    ->where('o.is_fc', 0)
                    ->pluck('ro.reward_id')
                    ->toArray();
                
                // Get all reward IDs that have specific outlets (not available at all)
                $rewardsWithSpecificOutlets = DB::table('member_apps_reward_outlets')
                    ->distinct()
                    ->pluck('reward_id')
                    ->toArray();
                
                \Log::info('Reward availability check', [
                    'outlet_id' => $outletId,
                    'available_reward_ids' => $availableRewardIds,
                    'rewards_with_specific_outlets' => $rewardsWithSpecificOutlets,
                ]);
                
                // Get rewards: either in availableRewardIds OR not in rewardsWithSpecificOutlets
                $rewards = DB::table('member_apps_rewards as rewards')
                    ->join('items', 'rewards.item_id', '=', 'items.id')
                    ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
                    ->leftJoin('sub_categories', function($join) {
                        $join->on('items.sub_category_id', '=', 'sub_categories.id')
                             ->whereNotNull('items.sub_category_id');
                    })
                    ->where('rewards.is_active', 1)
                    ->where(function ($query) use ($availableRewardIds, $rewardsWithSpecificOutlets) {
                        // Reward is in the available list for this outlet
                        if (!empty($availableRewardIds)) {
                            $query->whereIn('rewards.id', $availableRewardIds);
                        }
                        // OR reward is not in any specific outlet list (available at all outlets)
                        if (!empty($rewardsWithSpecificOutlets)) {
                            $query->orWhereNotIn('rewards.id', $rewardsWithSpecificOutlets);
                        } else {
                            // If no rewards have specific outlets, all rewards are available at all outlets
                            $query->orWhereRaw('1=1');
                        }
                    })
                    ->select(
                        'rewards.id as reward_id',
                        'rewards.item_id',
                        'rewards.points_required',
                        'rewards.serial_code',
                        'items.name as item_name',
                        'items.sku',
                        'items.description',
                        'categories.name as category_name',
                        'sub_categories.name as sub_category_name'
                    )
                    ->distinct()
                    ->get()
                    ->map(function ($reward) use ($memberPoints, $memberId, $member) {
                        $pointRequired = $reward->points_required ?? 0;
                        $memberRemainder = $member->point_remainder ?? 0;
                        $availablePoints = $memberPoints + floor($memberRemainder);
                        $canRedeem = $availablePoints >= $pointRequired;
                        
                        // Get item image
                        $itemImage = null;
                        $firstImage = DB::table('item_images')
                            ->where('item_id', $reward->item_id)
                            ->orderBy('id', 'asc')
                            ->first();
                        if ($firstImage && isset($firstImage->path)) {
                            $itemImage = 'https://ymsofterp.com/storage/' . ltrim($firstImage->path, '/');
                        } elseif ($firstImage && isset($firstImage->image)) {
                            $itemImage = 'https://ymsofterp.com/storage/' . ltrim($firstImage->image, '/');
                        }

                        // Generate serial code if can redeem and doesn't have one
                        $serialCode = $reward->serial_code;
                        if ($canRedeem && empty($serialCode)) {
                            // Generate shorter serial code for regular reward (easier for POS input)
                            // Format: RW + 4 digit reward_id + 4 digit member_id + 4 char hash = 14 chars
                            $serialCode = 'RW' . 
                                         str_pad($reward->reward_id % 10000, 4, '0', STR_PAD_LEFT) . 
                                         str_pad(($memberId ?? 0) % 10000, 4, '0', STR_PAD_LEFT) . 
                                         strtoupper(substr(md5($reward->reward_id . ($memberId ?? 0) . now()), 0, 4));
                            
                            // Update serial code in database
                            DB::table('member_apps_rewards')
                                ->where('id', $reward->reward_id)
                                ->update(['serial_code' => $serialCode]);
                        }

                        return [
                            'id' => $reward->reward_id,
                            'name' => $reward->item_name ?? 'Reward',
                            'description' => $reward->description ?? null,
                            'point_required' => $pointRequired,
                            'image' => $itemImage,
                            'can_redeem' => $canRedeem,
                            'serial_code' => $canRedeem ? $serialCode : null,
                        ];
                    })
                    ->values()
                    ->toArray();

                \Log::info('Rewards found for outlet', [
                    'outlet_id' => $outletId,
                    'rewards_count' => count($rewards),
                    'rewards' => $rewards,
                ]);

                // Include outlet even if no rewards (for consistency)
                // But only add to list if there are rewards
                if (count($rewards) > 0) {
                    $outletsWithRewards[] = [
                        'id' => $outletId,
                        'name' => $outlet->nama_outlet,
                        'address' => $outlet->lokasi ?? null,
                        'rewards' => $rewards,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'brand' => [
                        'id' => $brand->id,
                        'name' => $brand->brand,
                        'logo' => $brand->logo ? 'https://ymsofterp.com/storage/' . ltrim($brand->logo, '/') : null,
                    ],
                    'outlets' => $outletsWithRewards,
                ],
                'message' => 'Brand outlets with rewards retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve brand outlets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique voucher code (same format as distribution)
     */
    private function generateVoucherCode($voucherId, $memberId)
    {
        // Generate unique voucher code: VOUCHER_ID-MEMBER_ID-RANDOM
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        return "V{$voucherId}-M{$memberId}-{$random}";
    }

    /**
     * Generate unique 8-character alphanumeric serial code (same format as distribution)
     */
    private function generateVoucherSerialCode($voucherId, $memberId)
    {
        // Generate unique 8-character alphanumeric serial code
        // Characters: A-Z, 0-9 (36 possible characters)
        // Total possible combinations: 36^8 = 2,821,109,907,456 (very large, collision unlikely)
        
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 20; // Maximum attempts to generate unique code
        
        $attempts = 0;
        do {
            $serialCode = '';
            for ($i = 0; $i < 8; $i++) {
                $serialCode .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            // Check if serial code already exists
            $exists = MemberAppsMemberVoucher::where('serial_code', $serialCode)->exists();
            $attempts++;
            
            // If doesn't exist, we found a unique code
            if (!$exists) {
                return $serialCode;
            }
            
        } while ($attempts < $maxAttempts);
        
        // If we exhausted all attempts, throw exception
        throw new \Exception("Failed to generate unique serial code for voucher {$voucherId} and member {$memberId} after {$maxAttempts} attempts");
    }

    /**
     * Generate unique 8-character serial code for challenge reward
     * Format: CH + 6 alphanumeric characters
     * Ensures uniqueness by checking database
     */
    private function generateUniqueChallengeSerialCode($challengeId, $memberId, $progressId)
    {
        $maxAttempts = 100; // Prevent infinite loop
        $attempt = 0;
        
        // Characters to use: 0-9, A-Z (36 characters total)
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        
        do {
            // Generate 6-character code using combination of IDs, timestamp, and random
            $hash = md5($challengeId . $memberId . $progressId . now()->timestamp . $attempt . uniqid() . rand());
            
            // Convert hash to alphanumeric (take first 6 characters from hash, convert to uppercase)
            // Use hex characters (0-9, A-F) and map to 0-9, A-Z
            $uniquePart = '';
            for ($i = 0; $i < 6; $i++) {
                $hexChar = substr($hash, $i, 1);
                // Convert hex (0-f) to index (0-15), then map to 0-9, A-Z
                $hexValue = hexdec($hexChar);
                $uniquePart .= $chars[$hexValue % 36];
            }
            
            $serialCode = 'CH' . $uniquePart; // Total: 8 characters
            
            // Check if this serial code already exists
            $exists = MemberAppsChallengeProgress::where('serial_code', $serialCode)
                ->where('id', '!=', $progressId) // Exclude current progress record
                ->exists();
            
            $attempt++;
            
            // If not exists, return the code
            if (!$exists) {
                return $serialCode;
            }
            
        } while ($attempt < $maxAttempts);
        
        // Fallback: if all attempts failed, use timestamp + random approach
        // This should be extremely rare
        $timestamp = now()->timestamp;
        $random = rand(1000, 9999);
        $fallbackHash = md5($timestamp . $progressId . $random);
        $fallbackPart = '';
        for ($i = 0; $i < 6; $i++) {
            $hexChar = substr($fallbackHash, $i, 1);
            $hexValue = hexdec($hexChar);
            $fallbackPart .= $chars[$hexValue % 36];
        }
        $fallbackCode = 'CH' . $fallbackPart;
        
        \Log::warning('Generated fallback serial code after max attempts', [
            'challenge_id' => $challengeId,
            'member_id' => $memberId,
            'progress_id' => $progressId,
            'serial_code' => $fallbackCode,
            'attempts' => $attempt
        ]);
        
        return $fallbackCode;
    }

    /**
     * Generate serial code for reward
     */
    private function generateSerialCode($rewardId, $isChallenge = false)
    {
        if ($isChallenge) {
            // For challenge rewards: 8 characters (handled by generateUniqueChallengeSerialCode)
            return 'CH' . str_pad($rewardId, 6, '0', STR_PAD_LEFT) . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        } else {
            // For regular rewards: 14 characters
            return 'RW' . str_pad($rewardId, 6, '0', STR_PAD_LEFT) . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        }
    }

    /**
     * Reset redeemed_at untuk reward (untuk void/cancel order)
     */
    public function resetRedeemedAt(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'serial_code' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $serialCode = $request->input('serial_code');
            
            // Check if serial code starts with 'CH' (challenge reward) or 'RW' (regular reward)
            if (substr($serialCode, 0, 2) === 'CH') {
                // Challenge reward - reset reward_redeemed_at
                $progress = MemberAppsChallengeProgress::where('serial_code', $serialCode)->first();

                if (!$progress) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial code not found'
                    ], 404);
                }

                // Reset redeemed_at and redeemed_outlet_id
                $progress->reward_redeemed_at = null;
                $progress->redeemed_outlet_id = null;
                $progress->save();

                \Log::info('Challenge reward redeemed_at reset', [
                    'serial_code' => $serialCode,
                    'member_id' => $progress->member_id,
                    'challenge_id' => $progress->challenge_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Reward redeemed_at berhasil di-reset',
                    'data' => [
                        'serial_code' => $serialCode,
                        'type' => 'challenge'
                    ]
                ]);
            } else if (substr($serialCode, 0, 2) === 'RW') {
                // Regular reward - reset redeemed_at di member_apps_reward_redemptions
                $redemption = DB::table('member_apps_reward_redemptions')
                    ->where('serial_code', $serialCode)
                    ->first();

                if (!$redemption) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial code not found'
                    ], 404);
                }

                // Reset redeemed_at
                DB::table('member_apps_reward_redemptions')
                    ->where('serial_code', $serialCode)
                    ->update(['redeemed_at' => null]);

                \Log::info('Regular reward redeemed_at reset', [
                    'serial_code' => $serialCode,
                    'member_id' => $redemption->member_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Reward redeemed_at berhasil di-reset',
                    'data' => [
                        'serial_code' => $serialCode,
                        'type' => 'regular'
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid serial code format'
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Error resetting redeemed_at', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset redeemed_at: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all member rewards for POS (point rewards, challenge rewards, voucher items)
     * Grouped by: Reward Point, Reward Item Challenge, Voucher Item
     */
    public function getMemberRewardsForPos(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'member_id' => 'required',
                'outlet_id' => 'nullable|integer',
                'outlet_code' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $memberIdInput = $request->input('member_id');
            
            // Get outlet_id from outlet_id or outlet_code
            $outletId = $request->input('outlet_id');
            $outletCode = $request->input('outlet_code');
            
            if (!$outletId && $outletCode) {
                // Convert outlet_code (qr_code) to outlet_id
                $outlet = DB::table('tbl_data_outlet')
                    ->where('qr_code', $outletCode)
                    ->where('is_fc', 0)
                    ->select('id_outlet')
                    ->first();
                
                if ($outlet) {
                    $outletId = $outlet->id_outlet;
                }
            }
            
            // Get member data - bisa berupa integer ID atau string member_id (seperti JTS-2511-00001)
            $member = null;
            if (is_numeric($memberIdInput)) {
                // Jika numeric, cari berdasarkan ID (integer primary key)
                $member = MemberAppsMember::find((int)$memberIdInput);
            } else {
                // Jika string, cari berdasarkan member_id (field string seperti JTS-2511-00001)
                // Clean member ID variations (remove prefixes if any)
                $cleanMemberId = trim($memberIdInput);
                $cleanMemberIdNoJTS = preg_replace('/^JTS-/i', '', $cleanMemberId);
                $cleanMemberIdNoU = preg_replace('/^U/i', '', $cleanMemberId);
                $cleanMemberIdNoPrefix = preg_replace('/^(U|JTS-)/i', '', $cleanMemberId);
                
                // Try exact match first (case insensitive)
                $member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberId))])->first();
                
                // Try without JTS- prefix
                if (!$member && $cleanMemberIdNoJTS !== $cleanMemberId) {
                    $member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberIdNoJTS))])->first();
                }
                
                // Try without U prefix
                if (!$member && $cleanMemberIdNoU !== $cleanMemberId) {
                    $member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberIdNoU))])->first();
                }
                
                // Try without any prefix
                if (!$member && $cleanMemberIdNoPrefix !== $cleanMemberId) {
                    $member = MemberAppsMember::whereRaw('LOWER(TRIM(member_id)) = ?', [strtolower(trim($cleanMemberIdNoPrefix))])->first();
                }
                
                // Try exact match (case sensitive) as fallback
                if (!$member) {
                    $member = MemberAppsMember::where('member_id', $cleanMemberId)->first();
                }
            }
            
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }
            
            // Gunakan integer ID untuk query selanjutnya
            $memberId = $member->id;

            $memberPoints = $member->just_points ?? 0;

            // 1. REWARD POINT - Rewards yang bisa di-redeem dengan point
            $pointRewardsQuery = DB::table('member_apps_rewards as rewards')
                ->join('items', 'rewards.item_id', '=', 'items.id')
                ->where('rewards.is_active', 1)
                ->where('rewards.points_required', '<=', $memberPoints);
            
            // Filter by outlet if outlet_id is provided
            if ($outletId) {
                // Get rewards that are either:
                // 1. Not in member_apps_reward_outlets (all outlets - available everywhere)
                // 2. In member_apps_reward_outlets AND available for this outlet
                $pointRewardsQuery->where(function($q) use ($outletId) {
                    // Rewards not in member_apps_reward_outlets = all outlets
                    $q->whereNotIn('rewards.id', function($subQuery) {
                        $subQuery->select('reward_id')
                                ->from('member_apps_reward_outlets');
                    })
                    // OR rewards that are available for this specific outlet
                    ->orWhereIn('rewards.id', function($subQuery) use ($outletId) {
                        $subQuery->select('reward_id')
                                ->from('member_apps_reward_outlets')
                                ->where('outlet_id', $outletId);
                    });
                });
            }
            
            $pointRewards = $pointRewardsQuery
                ->select(
                    'rewards.id as reward_id',
                    'rewards.points_required',
                    'rewards.serial_code',
                    'items.name as item_name'
                )
                ->orderBy('rewards.points_required', 'asc')
                ->get()
                ->map(function($reward) {
                    return [
                        'id' => $reward->reward_id,
                        'name' => $reward->item_name,
                        'serial_code' => $reward->serial_code,
                        'points_required' => $reward->points_required,
                        'type' => 'point'
                    ];
                });

            // 2. REWARD ITEM CHALLENGE - Challenge rewards yang sudah completed
            $challengeRewards = collect();
            $completedChallenges = MemberAppsChallengeProgress::where('member_id', $memberId)
                ->where('is_completed', true)
                ->with('challenge')
                ->orderBy('updated_at', 'desc')
                ->get();

            foreach ($completedChallenges as $progress) {
                $challenge = $progress->challenge;
                if (!$challenge) continue;

                $rules = $challenge->rules;
                if (is_string($rules)) {
                    $rules = json_decode($rules, true);
                }

                if (!isset($rules['reward_type']) || $rules['reward_type'] !== 'item') {
                    continue;
                }
                
                // Filter by outlet if outlet_id is provided
                if ($outletId) {
                    $rewardAllOutlets = $rules['reward_all_outlets'] ?? true;
                    $rewardOutletIds = $rules['reward_outlet_ids'] ?? [];
                    
                    // If reward has specific outlets and not all outlets, check if current outlet is allowed
                    if (!$rewardAllOutlets && !empty($rewardOutletIds)) {
                        if (!in_array($outletId, $rewardOutletIds)) {
                            // Skip this challenge reward if outlet doesn't match
                            continue;
                        }
                    }
                }

                // Skip if reward is expired or already redeemed
                if ($progress->reward_expires_at) {
                    $expiresAt = is_string($progress->reward_expires_at) 
                        ? \Carbon\Carbon::parse($progress->reward_expires_at)
                        : $progress->reward_expires_at;
                    if ($expiresAt <= now()) {
                        // Reward expired, skip
                        continue;
                    }
                }
                
                // Skip if already redeemed
                if ($progress->reward_redeemed_at) {
                    // Already redeemed, skip
                    continue;
                }

                if (isset($rules['reward_value']) && is_array($rules['reward_value'])) {
                    $itemIds = $rules['reward_value'];
                } else if (isset($rules['reward_value'])) {
                    $itemIds = [$rules['reward_value']];
                } else {
                    continue;
                }

                // Check reward_item_selection: 'all' = semua items, 'single' = hanya 1 item
                $rewardItemSelection = $rules['reward_item_selection'] ?? 'all'; // Default: all
                
                // If single selection, use selected_reward_item_id from progress_data
                if ($rewardItemSelection === 'single' && count($itemIds) > 1) {
                    // Get selected item from progress_data
                    $progressData = $progress->progress_data ?? [];
                    if (is_string($progressData)) {
                        $progressData = json_decode($progressData, true);
                    }
                    $selectedItemId = $progressData['selected_reward_item_id'] ?? null;
                    
                    if ($selectedItemId && in_array($selectedItemId, $itemIds)) {
                        // Use the selected item from progress_data (hanya tampilkan yang sudah di-claim)
                        $itemIds = [$selectedItemId];
                    } else {
                        // Jika belum ada selected_reward_item_id, skip challenge ini (belum di-claim)
                        continue;
                    }
                }

                foreach ($itemIds as $itemId) {
                    $item = DB::table('items')
                        ->where('items.id', $itemId)
                        ->select('items.id as item_id', 'items.name as item_name')
                        ->first();

                    if ($item) {
                        // Generate serial code if not exists
                        $serialCode = $progress->serial_code;
                        if (empty($serialCode)) {
                            $serialCode = $this->generateUniqueChallengeSerialCode($progress->challenge_id, $memberId, $progress->id);
                            $progress->serial_code = $serialCode;
                            $progress->save();
                        }

                        $challengeRewards->push([
                            'id' => $progress->challenge_id,
                            'name' => $item->item_name,
                            'serial_code' => $serialCode,
                            'challenge_title' => $challenge->title,
                            'challenge_name' => $challenge->title,
                            'reward_type' => $rules['reward_type'] ?? 'item',
                            'type' => 'challenge'
                        ]);
                    }
                }
            }

            // 3. VOUCHER ITEM - Vouchers yang sudah di-redeem (punya serial code)
            // Filter: hanya voucher yang active, belum expire, dan belum di-redeem
            $voucherItemsQuery = DB::table('member_apps_member_vouchers as member_vouchers')
                ->join('member_apps_vouchers as vouchers', 'member_vouchers.voucher_id', '=', 'vouchers.id')
                ->where('member_vouchers.member_id', $memberId)
                ->whereNotNull('member_vouchers.serial_code')
                ->where('member_vouchers.serial_code', '!=', '')
                ->where('member_vouchers.status', 'active') // Hanya voucher yang masih active (belum used/expired)
                ->where(function($q) {
                    // Voucher belum expire (expires_at is null OR expires_at > now)
                    $q->whereNull('member_vouchers.expires_at')
                      ->orWhere('member_vouchers.expires_at', '>', now());
                });
                // Note: status='active' sudah menandakan belum di-redeem (redeemed voucher akan memiliki status='used')
            
            // Filter by outlet if outlet_id is provided
            if ($outletId) {
                // Get vouchers that are either:
                // 1. Not in member_apps_voucher_outlets (all outlets - available everywhere)
                // 2. In member_apps_voucher_outlets AND available for this outlet
                $voucherItemsQuery->where(function($q) use ($outletId) {
                    // Vouchers not in member_apps_voucher_outlets = all outlets
                    $q->whereNotIn('vouchers.id', function($subQuery) {
                        $subQuery->select('voucher_id')
                                ->from('member_apps_voucher_outlets');
                    })
                    // OR vouchers that are available for this specific outlet
                    ->orWhereIn('vouchers.id', function($subQuery) use ($outletId) {
                        $subQuery->select('voucher_id')
                                ->from('member_apps_voucher_outlets')
                                ->where('outlet_id', $outletId);
                    });
                });
            }
            
            $voucherItems = $voucherItemsQuery
                ->select(
                    'vouchers.id as voucher_id',
                    'vouchers.name as voucher_name',
                    'vouchers.voucher_type',
                    'member_vouchers.serial_code'
                )
                ->orderBy('member_vouchers.created_at', 'desc')
                ->get()
                ->map(function($voucher) {
                    return [
                        'id' => $voucher->voucher_id,
                        'name' => $voucher->voucher_name,
                        'voucher_name' => $voucher->voucher_name,
                        'voucher_type' => $voucher->voucher_type,
                        'serial_code' => $voucher->serial_code,
                        'type' => 'voucher'
                    ];
                });

            // Check if member has any rewards
            $hasRewards = $pointRewards->count() > 0 || $challengeRewards->count() > 0 || $voucherItems->count() > 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'has_rewards' => $hasRewards,
                    'member_points' => $memberPoints,
                    'reward_point' => $pointRewards->values(),
                    'reward_item_challenge' => $challengeRewards->values(),
                    'voucher_item' => $voucherItems->values()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting member rewards for POS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get member rewards: ' . $e->getMessage()
            ], 500);
        }
    }
}

