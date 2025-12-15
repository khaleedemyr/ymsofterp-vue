<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsChallenge;
use App\Models\MemberAppsChallengeProgress;
use App\Models\MemberAppsMember;
use App\Models\Item;
use App\Models\MemberAppsVoucher;
use App\Services\ChallengeProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChallengeController extends Controller
{
    /**
     * Get all active challenges
     */
    public function index()
    {
        try {
            // Get all active challenges with outlets relationship
            // For now, show all active challenges regardless of date (for testing)
            // TODO: Add proper date filtering if needed
            $allChallenges = MemberAppsChallenge::where('is_active', true)
                ->with('outlets')
                ->orderBy('created_at', 'desc')
                ->get();
            
            \Log::info('Challenges API - Total active challenges: ' . $allChallenges->count());
            \Log::info('Current date: ' . now()->toDateString());
            
            // Filter by date: show challenges that haven't ended yet
            $challenges = $allChallenges->filter(function ($challenge) {
                // If end_date exists and is in the past, exclude it
                if ($challenge->end_date && $challenge->end_date->isPast()) {
                    return false;
                }
                // Include all others (no end_date, or end_date in future, or start_date in future)
                return true;
            });
            
            \Log::info('Challenges API - After date filter: ' . $challenges->count() . ' challenges');
            
            $challenges = $challenges
                ->map(function ($challenge) {
                    // Build full URL for image
                    $imageUrl = null;
                    if ($challenge->image) {
                        // If image path already contains full URL, use it as is
                        if (str_starts_with($challenge->image, 'http://') || str_starts_with($challenge->image, 'https://')) {
                            $imageUrl = $challenge->image;
                        } else {
                            // Build full URL: https://ymsofterp.com/storage/{path}
                            $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($challenge->image, '/');
                        }
                    }

                    // Format dates
                    $startDate = $challenge->start_date ? $challenge->start_date->format('Y-m-d') : null;
                    $endDate = $challenge->end_date ? $challenge->end_date->format('Y-m-d') : null;

                    // Format valid until text
                    $validUntil = null;
                    if ($endDate) {
                        $endDateObj = \Carbon\Carbon::parse($endDate);
                        $validUntil = 'VALID UNTIL ' . strtoupper($endDateObj->format('d F Y'));
                    }

                    // Decode rules if it's a JSON string
                    $rules = $challenge->rules;
                    if (is_string($rules)) {
                        $decodedRules = json_decode($rules, true);
                        $rules = $decodedRules !== null ? $decodedRules : [];
                    } elseif (is_array($rules)) {
                        // Already decoded
                        $rules = $rules;
                    } else {
                        $rules = null;
                    }

                    // Enrich rules with item/voucher names
                    $enrichedRules = $this->enrichRulesWithNames($rules);
                    
                    // Get challenge outlet information
                    $challengeOutlets = [];
                    $challengeAllOutlets = true;
                    if ($challenge->relationLoaded('outlets') && $challenge->outlets && $challenge->outlets->isNotEmpty()) {
                        $challengeAllOutlets = false;
                        $challengeOutlets = $challenge->outlets->map(function($outlet) {
                            return [
                                'id' => $outlet->id_outlet,
                                'name' => $outlet->nama_outlet,
                                'code' => $outlet->qr_code ?? null,
                            ];
                        })->toArray();
                    }

                    return [
                        'id' => $challenge->id,
                        'title' => $challenge->title,
                        'description' => $challenge->description,
                        'image' => $imageUrl,
                        'points_reward' => $challenge->points_reward,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'valid_until' => $validUntil,
                        'challenge_type_id' => $challenge->challenge_type_id,
                        'validity_period_days' => $challenge->validity_period_days,
                        'rules' => $enrichedRules,
                        'challenge_all_outlets' => $challengeAllOutlets,
                        'challenge_outlets' => $challengeOutlets,
                    ];
                });

            $result = $challenges->toArray();
            \Log::info('Challenges API - Returning ' . count($result) . ' challenges');
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Challenges retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve challenges: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get challenge detail by ID
     */
    public function show(Request $request, $id)
    {
        try {
            $challenge = MemberAppsChallenge::with('outlets')->findOrFail($id);

            // Build full URL for image
            $imageUrl = null;
            if ($challenge->image) {
                if (str_starts_with($challenge->image, 'http://') || str_starts_with($challenge->image, 'https://')) {
                    $imageUrl = $challenge->image;
                } else {
                    $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($challenge->image, '/');
                }
            }

            // Format dates
            $startDate = $challenge->start_date ? $challenge->start_date->format('Y-m-d') : null;
            $endDate = $challenge->end_date ? $challenge->end_date->format('Y-m-d') : null;

            // Format valid until text (this is for challenge completion deadline, not reward expiry)
            $validUntil = null;
            if ($endDate) {
                $endDateObj = \Carbon\Carbon::parse($endDate);
                $validUntil = 'VALID UNTIL ' . strtoupper($endDateObj->format('d F Y'));
            }

            // Decode rules if it's a JSON string
            $rules = $challenge->rules;
            if (is_string($rules)) {
                $decodedRules = json_decode($rules, true);
                $rules = $decodedRules !== null ? $decodedRules : [];
            } elseif (is_array($rules)) {
                // Already decoded
                $rules = $rules;
            } else {
                $rules = null;
            }

            // Enrich rules with item/voucher names
            $enrichedRules = $this->enrichRulesWithNames($rules);
            
            // Get challenge outlet information
            $challengeOutlets = [];
            $challengeAllOutlets = true;
            if ($challenge->outlets && $challenge->outlets->isNotEmpty()) {
                $challengeAllOutlets = false;
                $challengeOutlets = $challenge->outlets->map(function($outlet) {
                    return [
                        'id' => $outlet->id_outlet,
                        'name' => $outlet->nama_outlet,
                        'code' => $outlet->qr_code ?? null,
                    ];
                })->toArray();
            }

            // Get member progress if authenticated
            $progress = null;
            // Try to get user from request (works with or without auth middleware)
            $member = null;
            try {
                $member = auth('sanctum')->user();
            } catch (\Exception $e) {
                // If sanctum fails, try to get from request
                $member = $request->user();
            }
            
            if ($member) {
                $progressRecord = MemberAppsChallengeProgress::where('member_id', $member->id)
                    ->where('challenge_id', $challenge->id)
                    ->first();
                
                if ($progressRecord) {
                    // Refresh progress from transactions if not completed
                    if (!$progressRecord->is_completed) {
                        $progressService = new ChallengeProgressService();
                        $progressService->updateProgressFromTransaction($member->id);
                        
                        // Reload progress record
                        $progressRecord->refresh();
                    }
                    
                    $progress = [
                        'started_at' => $progressRecord->started_at ? $progressRecord->started_at->format('Y-m-d H:i:s') : null,
                        'is_completed' => $progressRecord->is_completed,
                        'completed_at' => $progressRecord->completed_at ? $progressRecord->completed_at->format('Y-m-d H:i:s') : null,
                        'reward_claimed' => $progressRecord->reward_claimed,
                        'reward_claimed_at' => $progressRecord->reward_claimed_at ? $progressRecord->reward_claimed_at->format('Y-m-d H:i:s') : null,
                        'reward_expires_at' => $progressRecord->reward_expires_at ? $progressRecord->reward_expires_at->format('Y-m-d H:i:s') : null,
                        'is_reward_expired' => $progressRecord->isRewardExpired(),
                        'can_claim_reward' => $progressRecord->canClaimReward(),
                        'progress_data' => $progressRecord->progress_data ?? [],
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'description' => $challenge->description,
                    'image' => $imageUrl,
                    'points_reward' => $challenge->points_reward,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'valid_until' => $validUntil,
                    'challenge_type_id' => $challenge->challenge_type_id,
                    'validity_period_days' => $challenge->validity_period_days,
                    'rules' => $enrichedRules,
                    'progress' => $progress,
                    'challenge_all_outlets' => $challengeAllOutlets,
                    'challenge_outlets' => $challengeOutlets,
                ],
                'message' => 'Challenge retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve challenge: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start a challenge
     */
    public function start(Request $request, $id)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $challenge = MemberAppsChallenge::findOrFail($id);

            // Check if challenge is active
            if (!$challenge->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge is not active'
                ], 400);
            }

            // Check if challenge hasn't ended
            if ($challenge->end_date && $challenge->end_date->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge has ended'
                ], 400);
            }

            // Check if member already started this challenge
            $existingProgress = MemberAppsChallengeProgress::where('member_id', $member->id)
                ->where('challenge_id', $challenge->id)
                ->first();

            if ($existingProgress) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge already started'
                ], 400);
            }

            // Create progress record
            $progress = MemberAppsChallengeProgress::create([
                'member_id' => $member->id,
                'challenge_id' => $challenge->id,
                'started_at' => now(),
                'progress_data' => [],
                'is_completed' => false,
            ]);

            \Log::info('Challenge started', [
                'member_id' => $member->id,
                'challenge_id' => $challenge->id,
                'progress_id' => $progress->id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $progress->id,
                    'started_at' => $progress->started_at->format('Y-m-d H:i:s'),
                    'is_completed' => false,
                ],
                'message' => 'Challenge started successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error starting challenge', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to start challenge: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update challenge progress from POS (called after order payment)
     */
    public function updateProgressFromPos(Request $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'member_id' => 'required|string',
                'order_id' => 'required|string',
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

            \Log::info('Challenge progress update request received', [
                'member_id_input' => $memberId,
                'order_id' => $orderId
            ]);

            // Find member by member_id (could be from member_apps_members table)
            // Try member_id field first, then id field
            $member = MemberAppsMember::where('member_id', $memberId)
                ->orWhere('id', $memberId)
                ->first();

            if (!$member) {
                \Log::warning('Member not found for challenge progress update', [
                    'member_id_input' => $memberId,
                    'order_id' => $orderId,
                    'searched_fields' => ['member_id', 'id']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Member not found'
                ], 404);
            }

            \Log::info('Member found for challenge progress update', [
                'member_id_input' => $memberId,
                'member_db_id' => $member->id,
                'member_member_id' => $member->member_id,
                'order_id' => $orderId
            ]);

            // Update challenge progress using service
            $progressService = new ChallengeProgressService();
            $progressService->updateProgressFromTransaction($member->id, $orderId);

            \Log::info('Challenge progress update completed from POS', [
                'member_id' => $member->id,
                'member_id_input' => $memberId,
                'member_identifier' => $member->member_id ?? $member->id,
                'order_id' => $orderId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Challenge progress updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating challenge progress from POS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update challenge progress: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh challenge progress from transactions
     */
    public function refresh(Request $request, $id)
    {
        try {
            $member = $request->user();
            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $challenge = MemberAppsChallenge::findOrFail($id);
            
            // Get progress record
            $progress = MemberAppsChallengeProgress::where('member_id', $member->id)
                ->where('challenge_id', $challenge->id)
                ->first();

            if (!$progress) {
                return response()->json([
                    'success' => false,
                    'message' => 'Challenge not started'
                ], 400);
            }

            // Update progress from transactions
            $progressService = new ChallengeProgressService();
            $progressService->updateProgressFromTransaction($member->id);

            // Reload progress
            $progress->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'started_at' => $progress->started_at ? $progress->started_at->format('Y-m-d H:i:s') : null,
                    'is_completed' => $progress->is_completed,
                    'completed_at' => $progress->completed_at ? $progress->completed_at->format('Y-m-d H:i:s') : null,
                    'reward_claimed' => $progress->reward_claimed,
                    'reward_expires_at' => $progress->reward_expires_at ? $progress->reward_expires_at->format('Y-m-d H:i:s') : null,
                    'is_reward_expired' => $progress->isRewardExpired(),
                    'can_claim_reward' => $progress->canClaimReward(),
                    'progress_data' => $progress->progress_data,
                ],
                'message' => 'Progress refreshed successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error refreshing challenge progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh progress: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enrich rules with item/voucher names
     */
    private function enrichRulesWithNames($rules)
    {
        if (!is_array($rules) || empty($rules)) {
            return $rules;
        }

        // Enrich reward_value for item reward type
        if (isset($rules['reward_type']) && $rules['reward_type'] === 'item' && isset($rules['reward_value'])) {
            $itemIds = is_array($rules['reward_value']) ? $rules['reward_value'] : [$rules['reward_value']];
            if (!empty($itemIds)) {
                $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
                $rewardItems = [];
                foreach ($itemIds as $itemId) {
                    if (isset($items[$itemId])) {
                        $rewardItems[] = [
                            'id' => $itemId,
                            'name' => $items[$itemId]->name
                        ];
                    } else {
                        $rewardItems[] = [
                            'id' => $itemId,
                            'name' => "Item ID: {$itemId}"
                        ];
                    }
                }
                $rules['reward_items'] = $rewardItems;
            }
        }

        // Enrich reward_value for voucher reward type
        if (isset($rules['reward_type']) && $rules['reward_type'] === 'voucher' && isset($rules['reward_value'])) {
            $voucherIds = is_array($rules['reward_value']) ? $rules['reward_value'] : [$rules['reward_value']];
            if (!empty($voucherIds)) {
                $vouchers = MemberAppsVoucher::whereIn('id', $voucherIds)->get()->keyBy('id');
                $rewardVouchers = [];
                foreach ($voucherIds as $voucherId) {
                    if (isset($vouchers[$voucherId])) {
                        $rewardVouchers[] = [
                            'id' => $voucherId,
                            'name' => $vouchers[$voucherId]->name
                        ];
                    } else {
                        $rewardVouchers[] = [
                            'id' => $voucherId,
                            'name' => "Voucher ID: {$voucherId}"
                        ];
                    }
                }
                $rules['reward_vouchers'] = $rewardVouchers;
            }
        }

        // Enrich products for product-based challenge
        if (isset($rules['products']) && is_array($rules['products']) && !empty($rules['products'])) {
            $items = Item::whereIn('id', $rules['products'])->get()->keyBy('id');
            $productItems = [];
            foreach ($rules['products'] as $productId) {
                if (isset($items[$productId])) {
                    $productItems[] = [
                        'id' => $productId,
                        'name' => $items[$productId]->name
                    ];
                } else {
                    $productItems[] = [
                        'id' => $productId,
                        'name' => "Product ID: {$productId}"
                    ];
                }
            }
            $rules['product_items'] = $productItems;
        }

        return $rules;
    }
}

