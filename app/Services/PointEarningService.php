<?php

namespace App\Services;

use App\Models\MemberAppsMember;
use App\Models\MemberAppsPointTransaction;
use App\Models\MemberAppsPointEarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PointEarningService
{
    /**
     * Get earning rate based on member level
     */
    private function getEarningRate($memberLevel)
    {
        switch (strtolower($memberLevel)) {
            case 'loyal':
                return 1.0; // 1 point per Rp10,000
            case 'elite':
                return 1.5; // 1.5 point per Rp10,000
            case 'prestige':
                return 2.0; // 2 point per Rp10,000
            default:
                return 1.0; // Default to loyal rate
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
     * Earn points for member from order transaction
     */
    public function earnPointsFromOrder($memberId, $orderId, $transactionAmount, $transactionDate, $channel = 'pos')
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

            // Get member level (default to 'loyal' if not set)
            $memberLevel = $member->member_level ?? 'loyal';

            // Calculate points
            $pointAmount = $this->calculatePoints($transactionAmount, $memberLevel);
            $earningRate = $this->getEarningRate($memberLevel);

            if ($pointAmount <= 0) {
                Log::info('Point amount is 0 or negative, skipping point earning', [
                    'member_id' => $member->id,
                    'order_id' => $orderId,
                    'transaction_amount' => $transactionAmount,
                    'member_level' => $memberLevel
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

            // Create point transaction
            $pointTransaction = MemberAppsPointTransaction::create([
                'member_id' => $member->id,
                'transaction_type' => 'earning',
                'transaction_date' => $transactionDateObj->format('Y-m-d'),
                'point_amount' => $pointAmount,
                'transaction_amount' => $transactionAmount,
                'earning_rate' => $earningRate,
                'channel' => $channel,
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

            DB::commit();

            Log::info('Points earned successfully', [
                'member_id' => $member->id,
                'member_level' => $memberLevel,
                'order_id' => $orderId,
                'transaction_amount' => $transactionAmount,
                'point_amount' => $pointAmount,
                'earning_rate' => $earningRate,
                'expires_at' => $expiresAt->format('Y-m-d')
            ]);

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
}

