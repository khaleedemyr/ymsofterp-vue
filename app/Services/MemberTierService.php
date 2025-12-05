<?php

namespace App\Services;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsMonthlySpending;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberTierService
{
    /**
     * Tier thresholds based on rolling 12-month spending
     * Based on rules:
     * - Silver: Rp 1 - Rp 15.000.000
     * - Loyal: Rp 15.000.001 - Rp 40.000.000
     * - Elite: >= Rp 40.000.001
     */
    const TIER_THRESHOLDS = [
        'SILVER' => 0,           // Default tier, 0 - 15,000,000
        'LOYAL' => 15000000,     // Rp 15.000.000 - Rp 40.000.000
        'ELITE' => 40000000,     // Rp 40.000.001+ (max tier)
    ];

    /**
     * Calculate tier based on rolling 12-month spending
     */
    public static function calculateTier($rolling12MonthSpending)
    {
        $spending = (float) $rolling12MonthSpending;

        if ($spending >= self::TIER_THRESHOLDS['ELITE']) {
            return 'Elite';
        } elseif ($spending >= self::TIER_THRESHOLDS['LOYAL']) {
            return 'Loyal';
        } else {
            return 'Silver';
        }
    }

    /**
     * Update member tier based on rolling 12-month spending
     */
    public static function updateMemberTier($memberId, $asOfDate = null)
    {
        try {
            $member = MemberAppsMember::find($memberId);
            if (!$member) {
                return false;
            }

            // Get rolling 12-month spending
            $rollingSpending = MemberAppsMonthlySpending::getRolling12MonthSpending($memberId, $asOfDate);

            // Calculate new tier
            $newTier = self::calculateTier($rollingSpending);

            // Normalize tier names for comparison (case-insensitive, trim whitespace)
            $currentTierNormalized = ucfirst(strtolower(trim($member->member_level ?? 'Silver')));
            $newTierNormalized = ucfirst(strtolower(trim($newTier)));

            // Update member tier only if actually changed (case-insensitive comparison)
            if ($currentTierNormalized !== $newTierNormalized) {
                $oldTier = $member->member_level;
                $member->member_level = $newTier;
                $member->save();

                Log::info("Member tier updated", [
                    'member_id' => $memberId,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                    'old_tier_normalized' => $currentTierNormalized,
                    'new_tier_normalized' => $newTierNormalized,
                    'rolling_12_month_spending' => $rollingSpending,
                ]);

                // Dispatch event for push notification
                try {
                    event(new \App\Events\MemberTierUpgraded(
                        $member,
                        $oldTier,
                        $newTier,
                        $rollingSpending
                    ));
                    
                    Log::info('MemberTierUpgraded event dispatched', [
                        'member_id' => $memberId,
                        'old_tier' => $oldTier,
                        'new_tier' => $newTier,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error dispatching MemberTierUpgraded event', [
                        'member_id' => $memberId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                return [
                    'updated' => true,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                    'rolling_spending' => $rollingSpending,
                ];
            }

            // Tier hasn't changed, log for debugging if needed
            Log::debug("Member tier unchanged", [
                'member_id' => $memberId,
                'current_tier' => $member->member_level,
                'calculated_tier' => $newTier,
                'rolling_12_month_spending' => $rollingSpending,
            ]);

            return [
                'updated' => false,
                'current_tier' => $member->member_level,
                'rolling_spending' => $rollingSpending,
            ];
        } catch (\Exception $e) {
            Log::error("Error updating member tier", [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Record a transaction and update monthly spending
     */
    public static function recordTransaction($memberId, $amount, $transactionDate = null)
    {
        try {
            if ($transactionDate === null) {
                $transactionDate = now();
            }

            $year = (int) $transactionDate->format('Y');
            $month = (int) $transactionDate->format('m');

            // Add to monthly spending
            MemberAppsMonthlySpending::addSpending($memberId, $year, $month, $amount);

            // Update total_spending (lifetime) for backward compatibility
            $member = MemberAppsMember::find($memberId);
            if ($member) {
                $oldTotalSpending = $member->total_spending ?? 0;
                $newTotalSpending = $oldTotalSpending + $amount;
                
                // Only update if value actually changed
                if ($member->total_spending != $newTotalSpending) {
                    $member->total_spending = $newTotalSpending;
                    // Save without triggering member_level update (it will be handled by updateMemberTier below)
                    $member->save();
                }
            }

            // Update tier based on new rolling 12-month spending
            // This will only update tier if it actually changed (normalized comparison)
            self::updateMemberTier($memberId, $transactionDate);

            return true;
        } catch (\Exception $e) {
            Log::error("Error recording transaction", [
                'member_id' => $memberId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get tier progress information
     */
    public static function getTierProgress($memberId, $asOfDate = null)
    {
        $member = MemberAppsMember::find($memberId);
        if (!$member) {
            return null;
        }

        $rollingSpending = MemberAppsMonthlySpending::getRolling12MonthSpending($memberId, $asOfDate);
        $currentTier = $member->member_level;
        
        // Normalize tier name (handle case variations: Silver, Loyal, Elite)
        $currentTierNormalized = ucfirst(strtolower(trim($currentTier)));
        
        $nextTier = self::getNextTier($currentTierNormalized);
        $previousTier = self::getPreviousTier($currentTierNormalized);
        
        \Log::info('Tier Progress Calculation', [
            'member_id' => $memberId,
            'original_tier' => $currentTier,
            'normalized_tier' => $currentTierNormalized,
            'next_tier' => $nextTier,
            'rolling_spending' => $rollingSpending,
        ]);

        // Calculate progress to next tier
        $currentThreshold = self::TIER_THRESHOLDS[strtoupper($currentTierNormalized)] ?? 0;
        $nextThreshold = self::TIER_THRESHOLDS[strtoupper($nextTier)] ?? 0;

        $progress = 0.0;
        $remaining = 0;

        // Special handling for each tier
        switch (strtoupper($currentTierNormalized)) {
            case 'SILVER':
                // Silver to Loyal: 0 to 15M
                // Progress = rolling_spending / 15M
                $progress = min(1.0, max(0.0, $rollingSpending / 15000000));
                $remaining = max(0, 15000000 - $rollingSpending);
                
                \Log::info('Silver Tier Progress Calculation', [
                    'member_id' => $memberId,
                    'rolling_12_month_spending' => $rollingSpending,
                    'progress_percentage' => $progress * 100,
                    'remaining_to_loyal' => $remaining,
                    'threshold_loyal' => 15000000,
                    'calculation' => "{$rollingSpending} / 15000000 = {$progress}"
                ]);
                break;
            case 'LOYAL':
                // Loyal to Elite: 15M to 40M
                // Calculate progress from 15M to 40M
                $progress = min(1.0, max(0.0, ($rollingSpending - 15000000) / (40000000 - 15000000)));
                $remaining = max(0, 40000000 - $rollingSpending);
                break;
            case 'ELITE':
                // Elite is max tier, no next tier
                $progress = 1.0;
                $remaining = 0;
                $nextTier = 'Elite'; // Next tier is same as current (max level)
                $nextThreshold = 40000000; // Same as current threshold (max tier)
                break;
            default:
                if ($nextThreshold > $currentThreshold) {
                    $range = $nextThreshold - $currentThreshold;
                    $progressAmount = $rollingSpending - $currentThreshold;
                    $progress = min(1.0, max(0.0, $progressAmount / $range));
                    $remaining = max(0, $nextThreshold - $rollingSpending);
                } else {
                    $progress = 1.0;
                    $remaining = 0;
                }
        }

        return [
            'current_tier' => $currentTierNormalized,
            'next_tier' => $nextTier,
            'previous_tier' => $previousTier,
            'rolling_12_month_spending' => $rollingSpending,
            'progress' => $progress,
            'remaining_to_next_tier' => $remaining,
            'current_threshold' => $currentThreshold,
            'next_threshold' => $nextThreshold,
        ];
    }

    /**
     * Get next tier
     */
    public static function getNextTier($currentTier)
    {
        switch (strtoupper($currentTier)) {
            case 'SILVER':
                return 'Loyal';
            case 'LOYAL':
                return 'Elite';
            case 'ELITE':
            default:
                return 'Elite'; // Max tier, no next tier
        }
    }

    /**
     * Get previous tier
     */
    public static function getPreviousTier($currentTier)
    {
        switch (strtoupper($currentTier)) {
            case 'LOYAL':
                return 'Silver';
            case 'ELITE':
                return 'Loyal';
            case 'SILVER':
            default:
                return 'Silver';
        }
    }
}

