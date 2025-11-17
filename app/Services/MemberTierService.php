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
     * - Silver: Default (0)
     * - Loyal: Rp 1 - Rp 15.000.000
     * - Elite: Rp 15.000.001 - Rp 40.000.000
     * - Prestige: > Rp 40.000.001
     */
    const TIER_THRESHOLDS = [
        'SILVER' => 0,           // Default tier, no minimum
        'LOYAL' => 1,             // Rp 1 - Rp 15.000.000
        'ELITE' => 15000001,      // Rp 15.000.001 - Rp 40.000.000
        'PRESTIGE' => 40000001,   // Rp 40.000.001+
    ];

    /**
     * Calculate tier based on rolling 12-month spending
     */
    public static function calculateTier($rolling12MonthSpending)
    {
        $spending = (float) $rolling12MonthSpending;

        if ($spending >= self::TIER_THRESHOLDS['PRESTIGE']) {
            return 'Prestige';
        } elseif ($spending >= self::TIER_THRESHOLDS['ELITE']) {
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

            // Update member tier if changed
            if ($member->member_level !== $newTier) {
                $oldTier = $member->member_level;
                $member->member_level = $newTier;
                $member->save();

                Log::info("Member tier updated", [
                    'member_id' => $memberId,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                    'rolling_12_month_spending' => $rollingSpending,
                ]);

                return [
                    'updated' => true,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                    'rolling_spending' => $rollingSpending,
                ];
            }

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
                $member->total_spending = ($member->total_spending ?? 0) + $amount;
                $member->save();
            }

            // Update tier based on new rolling 12-month spending
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
        $nextTier = self::getNextTier($currentTier);
        $previousTier = self::getPreviousTier($currentTier);

        // Calculate progress to next tier
        $currentThreshold = self::TIER_THRESHOLDS[strtoupper($currentTier)] ?? 0;
        $nextThreshold = self::TIER_THRESHOLDS[strtoupper($nextTier)] ?? 0;

        $progress = 0.0;
        $remaining = 0;

        // Special handling for each tier
        switch (strtoupper($currentTier)) {
            case 'SILVER':
                // Silver to Loyal: 0 to 15M
                $progress = min(1.0, max(0.0, $rollingSpending / 15000000));
                $remaining = max(0, 15000000 - $rollingSpending);
                break;
            case 'LOYAL':
                // Loyal to Elite: 15M to 40M
                // Loyal range is 1-15M, so if spending < 15M, progress is 0
                if ($rollingSpending < 15000000) {
                    $progress = 0.0;
                    $remaining = 40000000 - $rollingSpending; // Need to reach 40M for Elite
                } else {
                    // Spending is 15M+, calculate progress to Elite (40M)
                    $progress = min(1.0, max(0.0, ($rollingSpending - 15000000) / (40000000 - 15000000)));
                    $remaining = max(0, 40000000 - $rollingSpending);
                }
                break;
            case 'ELITE':
                // Elite to Prestige: 40M+
                if ($rollingSpending >= 40000001) {
                    $progress = 1.0;
                    $remaining = 0;
                } else {
                    $progress = 0.0;
                    $remaining = 40000001 - $rollingSpending;
                }
                break;
            case 'PRESTIGE':
                // Max tier
                $progress = 1.0;
                $remaining = 0;
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
            'current_tier' => $currentTier,
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
                return 'Prestige';
            case 'PRESTIGE':
            default:
                return 'Prestige';
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
            case 'PRESTIGE':
                return 'Elite';
            case 'SILVER':
            default:
                return 'Silver';
        }
    }
}

