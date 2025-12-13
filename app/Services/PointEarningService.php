<?php

namespace App\Services;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsPointEarning;
use App\Events\PointEarned;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PointEarningService
{
    /**
     * Get earning rate based on member level
     * EARNING RATE:
     * - Silver: 1 poin untuk setiap Rp10.000 yang dibelanjakan
     * - Loyal: 1,5 poin untuk setiap Rp10.000 yang dibelanjakan
     * - Elite: 2 poin untuk setiap Rp10.000 yang dibelanjakan
     */
    private function getEarningRate($memberLevel)
    {
        switch (strtolower($memberLevel)) {
            case 'silver':
                return 1.0; // 1 point per Rp10,000
            case 'loyal':
                return 1.5; // 1.5 point per Rp10,000
            case 'elite':
                return 2.0; // 2 point per Rp10,000
            default:
                return 1.0; // Default to Silver rate
        }
    }

    /**
     * Calculate points based on transaction amount and member level
     */
    public function calculatePoints($transactionAmount, $memberLevel)
    {
        $earningRate = $this->getEarningRate($memberLevel);
        // Calculate: (transaction_amount / 10000) * earning_rate
        $points = ($transactionAmount / 10000) * $earningRate;
        return (int) floor($points); // Round down to integer
    }

    /**
     * Check if channel is valid for point earning
     * Valid channels: dine-in, take-away, delivery-restaurant
     * Invalid channels: gift-voucher, e-commerce (go-food, grab-food)
     */
    private function isValidChannel($channel)
    {
        $validChannels = ['dine-in', 'take-away', 'delivery-restaurant'];
        $invalidChannels = ['gift-voucher', 'e-commerce'];
        
        // Check if channel is explicitly invalid
        if (in_array(strtolower($channel), $invalidChannels)) {
            return false;
        }
        
        // Check if channel is valid
        if (in_array(strtolower($channel), $validChannels)) {
            return true;
        }
        
        // Default: if channel is 'pos' or not specified, assume valid (for backward compatibility)
        // But log a warning
        if (strtolower($channel) === 'pos' || empty($channel)) {
            Log::warning('Channel not specified or using legacy "pos" value, assuming valid', [
                'channel' => $channel
            ]);
            return true;
        }
        
        // Unknown channel - default to valid but log warning
        Log::warning('Unknown channel, assuming valid for point earning', [
            'channel' => $channel
        ]);
        return true;
    }

    /**
     * Earn points for member from order transaction
     * 
     * @param string $memberId Member ID
     * @param string $orderId Order ID
     * @param float $transactionAmount Transaction amount
     * @param string|Carbon $transactionDate Transaction date
     * @param string $channel Channel type: 'dine-in', 'take-away', 'delivery-restaurant', 'gift-voucher', 'e-commerce'
     * @param bool $isGiftVoucherPayment Whether payment was made using gift voucher
     * @param bool $isEcommerceOrder Whether order is from e-commerce (Go-Food/Grab Food)
     * @return array|null
     */
    public function earnPointsFromOrder($memberId, $orderId, $transactionAmount, $transactionDate, $channel = 'pos', $isGiftVoucherPayment = false, $isEcommerceOrder = false)
    {
        try {
            DB::beginTransaction();

            // Find member
            $member = MemberAppsMember::where('member_id', $memberId)
                ->orWhere('id', $memberId)
                ->first();

            if (!$member) {
                throw new \Exception('Member not found');
            }

            // Validate channel - check if point earning is allowed for this channel
            // If payment uses gift voucher or order is from e-commerce, skip point earning
            if ($isGiftVoucherPayment) {
                Log::info('Point earning skipped: Payment made with gift voucher', [
                    'member_id' => $member->id,
                    'order_id' => $orderId,
                    'transaction_amount' => $transactionAmount
                ]);
                DB::rollBack();
                return null;
            }

            if ($isEcommerceOrder) {
                Log::info('Point earning skipped: Order from e-commerce platform (Go-Food/Grab Food)', [
                    'member_id' => $member->id,
                    'order_id' => $orderId,
                    'transaction_amount' => $transactionAmount
                ]);
                DB::rollBack();
                return null;
            }

            // Validate channel type
            if (!$this->isValidChannel($channel)) {
                Log::info('Point earning skipped: Invalid channel', [
                    'member_id' => $member->id,
                    'order_id' => $orderId,
                    'channel' => $channel,
                    'transaction_amount' => $transactionAmount
                ]);
                DB::rollBack();
                return null;
            }

            // Get member level (default to 'silver' if not set)
            $memberLevel = $member->member_level ?? 'silver';

            // Calculate points
            $pointAmount = $this->calculatePoints($transactionAmount, $memberLevel);
            $earningRate = $this->getEarningRate($memberLevel);

            // Log calculation details for debugging
            Log::info('Point calculation details', [
                'member_id' => $member->id,
                'order_id' => $orderId,
                'transaction_amount' => $transactionAmount,
                'member_level' => $memberLevel,
                'earning_rate' => $earningRate,
                'calculated_points' => ($transactionAmount / 10000) * $earningRate,
                'final_point_amount' => $pointAmount
            ]);

            if ($pointAmount <= 0) {
                Log::info('Point amount is 0 or negative, skipping point earning', [
                    'member_id' => $member->id,
                    'order_id' => $orderId,
                    'transaction_amount' => $transactionAmount,
                    'member_level' => $memberLevel,
                    'calculated_points_before_floor' => ($transactionAmount / 10000) * $earningRate
                ]);
                DB::rollBack();
                return null;
            }

            // Parse transaction date
            if ($transactionDate instanceof \DateTime) {
                $transactionDateObj = Carbon::instance($transactionDate);
            } elseif ($transactionDate instanceof Carbon) {
                $transactionDateObj = $transactionDate->copy();
            } else {
                $transactionDateObj = Carbon::parse($transactionDate);
            }

            // Calculate expiration date (1 year from transaction date)
            $expiresAt = $transactionDateObj->copy()->addYear();

            // Normalize channel name for database
            $normalizedChannel = $this->normalizeChannel($channel);

            // Ensure transaction_type is 'earn' (not 'earning')
            $transactionType = 'earn';
            
            Log::info('Creating point transaction', [
                'member_id' => $member->id,
                'transaction_type' => $transactionType,
                'order_id' => $orderId,
                'point_amount' => $pointAmount
            ]);

            // Create point transaction
            $pointTransaction = MemberAppsPointTransaction::create([
                'member_id' => $member->id,
                'transaction_type' => $transactionType,
                'transaction_date' => $transactionDateObj->format('Y-m-d'),
                'point_amount' => $pointAmount,
                'transaction_amount' => $transactionAmount,
                'earning_rate' => $earningRate,
                'channel' => $normalizedChannel,
                'reference_id' => $orderId,
                'description' => "Point earning from order {$orderId}",
                'expires_at' => $expiresAt->format('Y-m-d'),
                'is_expired' => false,
            ]);

            // Create point earning record
            $pointEarning = MemberAppsPointEarning::create([
                'member_id' => $member->id,
                'point_transaction_id' => $pointTransaction->id,
                'point_amount' => $pointAmount,
                'remaining_points' => $pointAmount, // Initially all points are remaining
                'earned_at' => $transactionDateObj->format('Y-m-d'),
                'expires_at' => $expiresAt->format('Y-m-d'),
                'is_expired' => false,
                'is_fully_redeemed' => false,
            ]);

            // Update member's total points (just_points)
            $member->increment('just_points', $pointAmount);

            // Update monthly spending and tier (for rolling 12-month calculation)
            try {
                \App\Services\MemberTierService::recordTransaction(
                    $member->id,
                    $transactionAmount,
                    $transactionDateObj
                );
            } catch (\Exception $e) {
                // Log error but don't fail point earning
                Log::warning('Failed to update monthly spending and tier', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Check and award birthday bonus if applicable
            try {
                $this->checkAndAwardBirthdayBonus($member->id, $transactionDateObj);
            } catch (\Exception $e) {
                // Log error but don't fail point earning
                Log::warning('Failed to check birthday bonus', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            // Refresh member to get updated points
            $member->refresh();

            Log::info('Points earned successfully', [
                'member_id' => $member->id,
                'member_level' => $memberLevel,
                'order_id' => $orderId,
                'transaction_amount' => $transactionAmount,
                'point_amount' => $pointAmount,
                'earning_rate' => $earningRate,
                'channel' => $normalizedChannel,
                'expires_at' => $expiresAt->format('Y-m-d')
            ]);

            // Get outlet name for notification
            $outletName = 'Outlet';
            try {
                $orderData = DB::selectOne(
                    "SELECT kode_outlet FROM orders WHERE id = ? LIMIT 1",
                    [$orderId]
                );
                
                if ($orderData && $orderData->kode_outlet) {
                    $outletData = DB::selectOne(
                        "SELECT nama_outlet FROM tbl_data_outlet WHERE qr_code = ? LIMIT 1",
                        [$orderData->kode_outlet]
                    );
                    
                    if ($outletData) {
                        $outletName = $outletData->nama_outlet;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error getting outlet name for notification', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ]);
            }

            // Dispatch event for push notification
            try {
                Log::info('Dispatching PointEarned event', [
                    'member_id' => $member->id,
                    'points' => $pointAmount,
                    'order_id' => $orderId,
                    'outlet_name' => $outletName,
                ]);
                
                event(new PointEarned(
                    $member,
                    $pointTransaction,
                    $pointAmount,
                    'transaction',
                    [
                        'order_id' => $orderId,
                        'outlet_name' => $outletName,
                    ]
                ));
                
                Log::info('PointEarned event dispatched successfully', [
                    'member_id' => $member->id,
                ]);
            } catch (\Exception $e) {
                // Log error but don't fail the point earning
                Log::error('Error dispatching PointEarned event', [
                    'member_id' => $member->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return [
                'transaction' => $pointTransaction,
                'earning' => $pointEarning,
                'points_earned' => $pointAmount,
                'total_points' => $member->just_points
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error earning points', [
                'member_id' => $memberId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Normalize channel name to match database enum values
     */
    private function normalizeChannel($channel)
    {
        $channel = strtolower(trim($channel));
        
        // Map common variations to database enum values
        $channelMap = [
            'pos' => 'dine-in', // Default legacy value
            'dinein' => 'dine-in',
            'dine_in' => 'dine-in',
            'takeaway' => 'take-away',
            'take_away' => 'take-away',
            'delivery' => 'delivery-restaurant',
            'delivery_restaurant' => 'delivery-restaurant',
            'ojol' => 'e-commerce', // Ojol = online delivery (Go-Food/Grab Food)
        ];
        
        if (isset($channelMap[$channel])) {
            return $channelMap[$channel];
        }
        
        // If already matches enum values, return as is
        $validEnums = ['dine-in', 'take-away', 'delivery-restaurant', 'gift-voucher', 'e-commerce', 'campaign', 'registration', 'birthday', 'referral', 'redemption', 'adjustment', 'voucher-purchase', 'challenge_reward'];
        if (in_array($channel, $validEnums)) {
            return $channel;
        }
        
        // Default to dine-in for unknown channels
        return 'dine-in';
    }

    /**
     * Earn bonus points for member
     * 
     * @param int $memberId Member ID
     * @param string $bonusType Type of bonus: 'registration', 'birthday', 'referral', 'campaign'
     * @param int $pointAmount Point amount (for campaign, can be calculated)
     * @param int $validityDays Validity in days (default based on bonus type)
     * @param string|null $referenceId Reference ID (e.g., referral code, campaign ID)
     * @return array|null
     */
    public function earnBonusPoints($memberId, $bonusType, $pointAmount = null, $validityDays = null, $referenceId = null)
    {
        try {
            DB::beginTransaction();

            // Find member - try both id and member_id fields
            $member = MemberAppsMember::where('id', $memberId)
                ->orWhere('member_id', $memberId)
                ->first();
            if (!$member) {
                Log::error('Member not found in earnBonusPoints', [
                    'member_id' => $memberId,
                    'type' => gettype($memberId)
                ]);
                throw new \Exception('Member not found with ID: ' . $memberId);
            }
            
            Log::info('Member found in earnBonusPoints', [
                'member_id_param' => $memberId,
                'member_db_id' => $member->id,
                'member_db_member_id' => $member->member_id ?? null,
                'current_points' => $member->just_points ?? 0
            ]);

            // Set default point amount and validity based on bonus type
            $bonusConfig = $this->getBonusConfig($bonusType);
            $finalPointAmount = $pointAmount ?? $bonusConfig['points'];
            $finalValidityDays = $validityDays ?? $bonusConfig['validity_days'];

            if ($finalPointAmount <= 0) {
                Log::info('Bonus point amount is 0 or negative, skipping', [
                    'member_id' => $memberId,
                    'bonus_type' => $bonusType
                ]);
                DB::rollBack();
                return null;
            }

            // Check if bonus already given (prevent duplicate)
            if ($referenceId) {
                $existingBonus = MemberAppsPointTransaction::where('member_id', $memberId)
                    ->where('transaction_type', 'bonus')
                    ->where('channel', $bonusType)
                    ->where('reference_id', $referenceId)
                    ->first();

                if ($existingBonus) {
                    Log::info('Bonus points already given for this reference', [
                        'member_id' => $memberId,
                        'bonus_type' => $bonusType,
                        'reference_id' => $referenceId
                    ]);
                    DB::rollBack();
                    return null;
                }
            }

            $now = Carbon::now();
            $expiresAt = $now->copy()->addDays($finalValidityDays);

            // Create point transaction
            $pointTransaction = MemberAppsPointTransaction::create([
                'member_id' => $member->id,
                'transaction_type' => 'bonus',
                'transaction_date' => $now->format('Y-m-d'),
                'point_amount' => $finalPointAmount,
                'transaction_amount' => null, // Bonus points don't have transaction amount
                'earning_rate' => null, // Bonus points don't have earning rate
                'channel' => $bonusType,
                'reference_id' => $referenceId,
                'description' => $this->getBonusDescription($bonusType, $referenceId),
                'expires_at' => $expiresAt->format('Y-m-d'),
                'is_expired' => false,
            ]);

            // Create point earning record
            $pointEarning = MemberAppsPointEarning::create([
                'member_id' => $member->id,
                'point_transaction_id' => $pointTransaction->id,
                'point_amount' => $finalPointAmount,
                'remaining_points' => $finalPointAmount,
                'earned_at' => $now->format('Y-m-d'),
                'expires_at' => $expiresAt->format('Y-m-d'),
                'is_expired' => false,
                'is_fully_redeemed' => false,
            ]);

            // Update member's total points
            $member->increment('just_points', $finalPointAmount);

            DB::commit();

            Log::info('Bonus points earned successfully', [
                'member_id' => $member->id,
                'bonus_type' => $bonusType,
                'point_amount' => $finalPointAmount,
                'validity_days' => $finalValidityDays,
                'expires_at' => $expiresAt->format('Y-m-d')
            ]);

            return [
                'transaction' => $pointTransaction,
                'earning' => $pointEarning,
                'points_earned' => $finalPointAmount,
                'total_points' => $member->just_points,
                'expires_at' => $expiresAt->format('Y-m-d')
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error earning bonus points', [
                'member_id' => $memberId,
                'bonus_type' => $bonusType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get bonus configuration based on bonus type
     */
    private function getBonusConfig($bonusType)
    {
        $configs = [
            'registration' => [
                'points' => 100,
                'validity_days' => 365, // 1 year (same as regular points)
            ],
            'birthday' => [
                'points' => 100,
                'validity_days' => 5, // Valid for 5 days
            ],
            'referral' => [
                'points' => 50,
                'validity_days' => 365, // Valid for 1 year (same as regular points)
            ],
            'campaign' => [
                'points' => 0, // Will be set by campaign
                'validity_days' => 365, // Default 1 year
            ],
        ];

        return $configs[$bonusType] ?? ['points' => 0, 'validity_days' => 365];
    }

    /**
     * Get bonus description
     */
    private function getBonusDescription($bonusType, $referenceId = null)
    {
        // Handle challenge_reward with challenge title
        if ($bonusType === 'challenge_reward' && $referenceId) {
            // Extract challenge ID from reference_id (format: CHALLENGE-{challengeId}-{progressId} or CHALLENGE-{challengeId}-{progressId}-LEGACY)
            if (preg_match('/CHALLENGE-(\d+)/', $referenceId, $matches)) {
                $challengeId = $matches[1];
                try {
                    $challenge = \DB::table('member_apps_challenges')
                        ->where('id', $challengeId)
                        ->select('title')
                        ->first();
                    
                    if ($challenge) {
                        return "Point reward dari challenge: {$challenge->title}";
                    }
                } catch (\Exception $e) {
                    Log::warning('Error getting challenge title for bonus description', [
                        'challenge_id' => $challengeId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            return 'Point reward dari challenge';
        }
        
        $descriptions = [
            'registration' => 'Bonus point untuk member baru',
            'birthday' => 'Bonus point ulang tahun',
            'referral' => $referenceId ? "Bonus point referral (Code: {$referenceId})" : 'Bonus point referral',
            'campaign' => $referenceId ? "Bonus point campaign (ID: {$referenceId})" : 'Bonus point campaign',
        ];

        return $descriptions[$bonusType] ?? 'Bonus point';
    }

    /**
     * Check and award birthday bonus if applicable
     * Should be called when member makes a transaction
     */
    public function checkAndAwardBirthdayBonus($memberId, $transactionDate = null)
    {
        try {
            $member = MemberAppsMember::find($memberId);
            if (!$member || !$member->tanggal_lahir) {
                return null;
            }

            $now = $transactionDate ? Carbon::parse($transactionDate) : Carbon::now();
            $birthday = Carbon::parse($member->tanggal_lahir);
            
            // Check if today is within 5 days of birthday
            $birthdayThisYear = Carbon::create($now->year, $birthday->month, $birthday->day);
            $birthdayNextYear = Carbon::create($now->year + 1, $birthday->month, $birthday->day);
            
            // Check if within 5 days before or after birthday
            $daysDiff = $now->diffInDays($birthdayThisYear, false);
            $daysDiffNext = $now->diffInDays($birthdayNextYear, false);
            
            $isWithinBirthdayPeriod = ($daysDiff >= -5 && $daysDiff <= 5) || ($daysDiffNext >= -5 && $daysDiffNext <= 5);
            
            if (!$isWithinBirthdayPeriod) {
                return null;
            }

            // Check if birthday bonus already given this year
            $yearStart = $now->copy()->startOfYear();
            $yearEnd = $now->copy()->endOfYear();
            
            $existingBonus = MemberAppsPointTransaction::where('member_id', $memberId)
                ->where('transaction_type', 'bonus')
                ->where('channel', 'birthday')
                ->whereBetween('transaction_date', [$yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d')])
                ->first();

            if ($existingBonus) {
                Log::info('Birthday bonus already given this year', [
                    'member_id' => $memberId,
                    'year' => $now->year
                ]);
                return null;
            }

            // Award birthday bonus
            return $this->earnBonusPoints($memberId, 'birthday');

        } catch (\Exception $e) {
            Log::error('Error checking birthday bonus', [
                'member_id' => $memberId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Redeem points from member's point earnings (FIFO - oldest expiry first)
     * Updates remaining_points and is_fully_redeemed in member_apps_point_earnings
     * Creates records in member_apps_point_redemptions and member_apps_point_redemption_details
     * 
     * @param int $memberId Member ID
     * @param int $pointTransactionId Point transaction ID (from member_apps_point_transactions)
     * @param int $pointsToRedeem Total points to redeem
     * @param string $redemptionType Type of redemption: 'product', 'discount-voucher', 'cash', 'voucher-purchase' (must match ENUM in database)
     * @param array $additionalData Additional data for redemption record
     * @return array|null
     */
    public function redeemPointsFromEarnings($memberId, $pointTransactionId, $pointsToRedeem, $redemptionType = 'product', $additionalData = [])
    {
        try {
            DB::beginTransaction();

            // Validate redemption_type (must be one of the ENUM values)
            $validRedemptionTypes = ['product', 'discount-voucher', 'cash', 'voucher-purchase'];
            if (!in_array($redemptionType, $validRedemptionTypes)) {
                Log::error('Invalid redemption_type', [
                    'member_id' => $memberId,
                    'redemption_type' => $redemptionType,
                    'valid_types' => $validRedemptionTypes
                ]);
                DB::rollBack();
                return null;
            }

            if ($pointsToRedeem <= 0) {
                Log::warning('Invalid points to redeem', [
                    'member_id' => $memberId,
                    'points_to_redeem' => $pointsToRedeem
                ]);
                DB::rollBack();
                return null;
            }

            // Get point earnings that have remaining points, ordered by expiry date (FIFO - oldest first)
            // Note: We don't filter by is_expired because we want to redeem from all available points
            // The is_expired flag is set by the cron job, but points can still be redeemed if they haven't been marked as expired yet
            $pointEarnings = MemberAppsPointEarning::where('member_id', $memberId)
                ->where('is_fully_redeemed', false)
                ->where('remaining_points', '>', 0)
                ->whereNotNull('expires_at')
                ->orderBy('expires_at', 'asc') // Oldest expiry first (FIFO)
                ->orderBy('earned_at', 'asc') // If same expiry, oldest earned first
                ->get();

            Log::info('Point earnings query result', [
                'member_id' => $memberId,
                'points_to_redeem' => $pointsToRedeem,
                'earnings_count' => $pointEarnings->count(),
                'earnings_ids' => $pointEarnings->pluck('id')->toArray(),
                'total_remaining' => $pointEarnings->sum('remaining_points'),
            ]);

            if ($pointEarnings->isEmpty()) {
                // Check what earnings exist for debugging
                $allEarnings = MemberAppsPointEarning::where('member_id', $memberId)->get();
                Log::warning('No available point earnings to redeem', [
                    'member_id' => $memberId,
                    'points_to_redeem' => $pointsToRedeem,
                    'total_earnings_count' => $allEarnings->count(),
                    'earnings_details' => $allEarnings->map(function($e) {
                        return [
                            'id' => $e->id,
                            'remaining_points' => $e->remaining_points,
                            'is_fully_redeemed' => $e->is_fully_redeemed,
                            'is_expired' => $e->is_expired,
                            'expires_at' => $e->expires_at,
                        ];
                    })->toArray(),
                ]);
                DB::rollBack();
                return null;
            }

            // Calculate total available points
            $totalAvailablePoints = $pointEarnings->sum('remaining_points');
            if ($totalAvailablePoints < $pointsToRedeem) {
                Log::warning('Insufficient available points in earnings', [
                    'member_id' => $memberId,
                    'points_to_redeem' => $pointsToRedeem,
                    'total_available' => $totalAvailablePoints
                ]);
                DB::rollBack();
                return null;
            }

            // Create redemption record
            $redemption = \App\Models\MemberAppsPointRedemption::create([
                'member_id' => $memberId,
                'point_transaction_id' => $pointTransactionId,
                'redemption_type' => $redemptionType,
                'redemption_date' => now()->toDateString(),
                'point_amount' => $pointsToRedeem,
                'cash_value' => $additionalData['cash_value'] ?? null,
                'product_id' => $additionalData['product_id'] ?? null,
                'product_name' => $additionalData['product_name'] ?? null,
                'product_price' => $additionalData['product_price'] ?? null,
                'discount_voucher_type' => $additionalData['discount_voucher_type'] ?? null,
                'discount_voucher_points' => $additionalData['discount_voucher_points'] ?? null,
                'discount_voucher_code' => $additionalData['discount_voucher_code'] ?? null,
                'discount_voucher_expires_at' => $additionalData['discount_voucher_expires_at'] ?? null,
                'discount_voucher_used_at' => $additionalData['discount_voucher_used_at'] ?? null,
                'reference_id' => $additionalData['reference_id'] ?? null,
                'status' => $additionalData['status'] ?? 'completed',
            ]);

            $remainingPointsToRedeem = $pointsToRedeem;
            $redemptionDetails = [];

            // Redeem points from earnings (FIFO)
            foreach ($pointEarnings as $earning) {
                if ($remainingPointsToRedeem <= 0) {
                    break;
                }

                $pointsFromThisEarning = min($remainingPointsToRedeem, $earning->remaining_points);
                
                Log::info('Processing earning for redemption', [
                    'earning_id' => $earning->id,
                    'current_remaining_points' => $earning->remaining_points,
                    'points_from_this_earning' => $pointsFromThisEarning,
                    'remaining_points_to_redeem' => $remainingPointsToRedeem,
                ]);
                
                // Update remaining_points
                $oldRemainingPoints = $earning->remaining_points;
                $earning->remaining_points = $earning->remaining_points - $pointsFromThisEarning;
                
                // Update is_fully_redeemed if remaining_points becomes 0
                if ($earning->remaining_points == 0) {
                    $earning->is_fully_redeemed = true;
                }
                
                $saved = $earning->save();
                
                // Refresh to verify the save
                $earning->refresh();
                
                Log::info('Earning updated after redemption', [
                    'earning_id' => $earning->id,
                    'old_remaining_points' => $oldRemainingPoints,
                    'new_remaining_points' => $earning->remaining_points,
                    'is_fully_redeemed' => $earning->is_fully_redeemed,
                    'save_result' => $saved,
                ]);

                // Create redemption detail record
                $redemptionDetail = \App\Models\MemberAppsPointRedemptionDetail::create([
                    'redemption_id' => $redemption->id,
                    'point_earning_id' => $earning->id,
                    'point_amount' => $pointsFromThisEarning,
                ]);

                $redemptionDetails[] = $redemptionDetail;
                $remainingPointsToRedeem -= $pointsFromThisEarning;

                Log::info('Points redeemed from earning', [
                    'member_id' => $memberId,
                    'earning_id' => $earning->id,
                    'points_redeemed' => $pointsFromThisEarning,
                    'remaining_in_earning' => $earning->remaining_points,
                    'is_fully_redeemed' => $earning->is_fully_redeemed,
                ]);
            }

            if ($remainingPointsToRedeem > 0) {
                Log::error('Not all points were redeemed', [
                    'member_id' => $memberId,
                    'points_to_redeem' => $pointsToRedeem,
                    'remaining_points_to_redeem' => $remainingPointsToRedeem,
                ]);
                DB::rollBack();
                return null;
            }

            DB::commit();

            Log::info('Points redeemed successfully from earnings', [
                'member_id' => $memberId,
                'point_transaction_id' => $pointTransactionId,
                'points_redeemed' => $pointsToRedeem,
                'redemption_id' => $redemption->id,
                'details_count' => count($redemptionDetails),
            ]);

            return [
                'redemption' => $redemption,
                'redemption_details' => $redemptionDetails,
                'points_redeemed' => $pointsToRedeem,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error redeeming points from earnings', [
                'member_id' => $memberId,
                'point_transaction_id' => $pointTransactionId,
                'points_to_redeem' => $pointsToRedeem,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Rollback point redemption - return points to earnings
     * 
     * @param int $pointTransactionId Point transaction ID to rollback
     * @return array|null
     */
    public function rollbackPointRedemptionFromEarnings($pointTransactionId)
    {
        try {
            DB::beginTransaction();

            // Find redemption record
            $redemption = \App\Models\MemberAppsPointRedemption::where('point_transaction_id', $pointTransactionId)
                ->first();

            if (!$redemption) {
                Log::warning('Redemption record not found for rollback', [
                    'point_transaction_id' => $pointTransactionId
                ]);
                DB::rollBack();
                return null;
            }

            // Get redemption details
            $redemptionDetails = \App\Models\MemberAppsPointRedemptionDetail::where('redemption_id', $redemption->id)
                ->get();

            if ($redemptionDetails->isEmpty()) {
                Log::warning('No redemption details found for rollback', [
                    'redemption_id' => $redemption->id,
                    'point_transaction_id' => $pointTransactionId
                ]);
                DB::rollBack();
                return null;
            }

            $totalPointsReturned = 0;

            // Return points to earnings
            foreach ($redemptionDetails as $detail) {
                $earning = MemberAppsPointEarning::find($detail->point_earning_id);
                
                if (!$earning) {
                    Log::warning('Point earning not found for rollback', [
                        'point_earning_id' => $detail->point_earning_id,
                        'redemption_detail_id' => $detail->id
                    ]);
                    continue;
                }

                // Return points to earning
                $earning->remaining_points = $earning->remaining_points + $detail->point_amount;
                
                // Update is_fully_redeemed if points are returned
                if ($earning->remaining_points > 0 && $earning->is_fully_redeemed) {
                    $earning->is_fully_redeemed = false;
                }
                
                $earning->save();
                $totalPointsReturned += $detail->point_amount;

                Log::info('Points returned to earning', [
                    'earning_id' => $earning->id,
                    'points_returned' => $detail->point_amount,
                    'new_remaining_points' => $earning->remaining_points,
                    'is_fully_redeemed' => $earning->is_fully_redeemed,
                ]);
            }

            // Delete redemption details
            \App\Models\MemberAppsPointRedemptionDetail::where('redemption_id', $redemption->id)->delete();

            // Delete redemption record
            $redemption->delete();

            DB::commit();

            Log::info('Point redemption rolled back from earnings', [
                'point_transaction_id' => $pointTransactionId,
                'redemption_id' => $redemption->id,
                'total_points_returned' => $totalPointsReturned,
                'details_count' => $redemptionDetails->count(),
            ]);

            return [
                'points_returned' => $totalPointsReturned,
                'details_count' => $redemptionDetails->count(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rolling back point redemption from earnings', [
                'point_transaction_id' => $pointTransactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

