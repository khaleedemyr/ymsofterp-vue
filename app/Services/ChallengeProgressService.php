<?php

namespace App\Services;

use App\Models\MemberAppsChallenge;
use App\Models\MemberAppsChallengeProgress;
use App\Models\MemberAppsMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ChallengeProgressService
{
    /**
     * Update challenge progress based on member transactions
     */
    public function updateProgressFromTransaction($memberId, $orderId = null)
    {
        try {
            // Get all active challenges for this member
            $activeProgresses = MemberAppsChallengeProgress::where('member_id', $memberId)
                ->where('is_completed', false)
                ->with('challenge')
                ->get();

            if ($activeProgresses->isEmpty()) {
                return;
            }

            // Get member data
            $member = MemberAppsMember::find($memberId);
            if (!$member) {
                return;
            }

            foreach ($activeProgresses as $progress) {
                $challenge = $progress->challenge;
                if (!$challenge || !$challenge->is_active) {
                    continue;
                }

                // Check if challenge hasn't ended
                if ($challenge->end_date && $challenge->end_date->isPast()) {
                    continue;
                }

                // Update progress based on challenge type
                $this->updateProgressForChallenge($progress, $challenge, $member, $orderId);
            }
        } catch (\Exception $e) {
            Log::error('Error updating challenge progress from transaction', [
                'member_id' => $memberId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Update progress for a specific challenge
     */
    private function updateProgressForChallenge($progress, $challenge, $member, $orderId = null)
    {
        $rules = $challenge->rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true) ?? [];
        }

        if (empty($rules)) {
            return;
        }

        $challengeType = $challenge->challenge_type_id;
        $currentProgress = $progress->progress_data ?? [];
        $updated = false;

        if ($challengeType === 'spending') {
            // Spending-based challenge
            $updated = $this->updateSpendingProgress($progress, $challenge, $member, $rules, $currentProgress, $orderId);
        } elseif ($challengeType === 'product') {
            // Product-based challenge
            $updated = $this->updateProductProgress($progress, $challenge, $member, $rules, $currentProgress, $orderId);
        }

        if ($updated) {
            // Check if challenge is completed
            $this->checkAndCompleteChallenge($progress, $challenge, $rules);
        }
    }

    /**
     * Update spending-based challenge progress
     */
    private function updateSpendingProgress($progress, $challenge, $member, $rules, $currentProgress, $orderId = null)
    {
        $minAmount = $rules['min_amount'] ?? 0;
        if ($minAmount <= 0) {
            return false;
        }

        // Get total spending from orders since challenge started
        $startedAt = $progress->started_at;
        
        // Use member_id from member_apps_members table (bisa id atau member_id tergantung struktur)
        $memberIdentifier = $member->member_id ?? $member->id;
        
        $query = DB::table('orders')
            ->where('member_id', $memberIdentifier)
            ->where('status', 'paid')
            ->where('created_at', '>=', $startedAt);

        // Filter by outlet if specified in rules
        if (isset($rules['outlet_ids']) && is_array($rules['outlet_ids']) && !empty($rules['outlet_ids'])) {
            $outletCodes = DB::table('tbl_data_outlet')
                ->whereIn('id_outlet', $rules['outlet_ids'])
                ->pluck('qr_code')
                ->toArray();
            
            if (!empty($outletCodes)) {
                $query->whereIn('kode_outlet', $outletCodes);
            }
        }

        $totalSpending = $query->sum('grand_total') ?? 0;

        // Update progress
        $currentProgress['spending'] = $totalSpending;
        $currentProgress['last_updated'] = now()->toDateTimeString();
        
        if ($orderId) {
            $currentProgress['last_order_id'] = $orderId;
        }

        $progress->progress_data = $currentProgress;
        $progress->save();

        return true;
    }

    /**
     * Update product-based challenge progress
     */
    private function updateProductProgress($progress, $challenge, $member, $rules, $currentProgress, $orderId = null)
    {
        $requiredProducts = $rules['products'] ?? [];
        $quantityRequired = $rules['quantity_required'] ?? 1;
        
        if (empty($requiredProducts)) {
            return false;
        }

        // Get product purchases from order_items since challenge started
        $startedAt = $progress->started_at;
        
        // Use member_id from member_apps_members table
        $memberIdentifier = $member->member_id ?? $member->id;
        
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.member_id', $memberIdentifier)
            ->where('orders.status', 'paid')
            ->where('orders.created_at', '>=', $startedAt)
            ->whereIn('order_items.item_id', $requiredProducts);

        // Filter by outlet if specified in rules
        if (isset($rules['outlet_ids']) && is_array($rules['outlet_ids']) && !empty($rules['outlet_ids'])) {
            $outletCodes = DB::table('tbl_data_outlet')
                ->whereIn('id_outlet', $rules['outlet_ids'])
                ->pluck('qr_code')
                ->toArray();
            
            if (!empty($outletCodes)) {
                $query->whereIn('orders.kode_outlet', $outletCodes);
            }
        } elseif (isset($rules['all_outlets']) && $rules['all_outlets'] == false) {
            // If all_outlets is false but no outlet_ids specified, skip
            return false;
        }

        $totalQuantity = $query->sum('order_items.qty') ?? 0;

        // Track quantity per product
        $productQuantities = [];
        foreach ($requiredProducts as $productId) {
            $productQty = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.member_id', $memberIdentifier)
                ->where('orders.status', 'paid')
                ->where('orders.created_at', '>=', $startedAt)
                ->where('order_items.item_id', $productId)
                ->sum('order_items.qty') ?? 0;
            
            $productQuantities[$productId] = $productQty;
        }

        // Update progress
        $currentProgress['total_quantity'] = $totalQuantity;
        $currentProgress['product_quantities'] = $productQuantities;
        $currentProgress['last_updated'] = now()->toDateTimeString();
        
        if ($orderId) {
            $currentProgress['last_order_id'] = $orderId;
        }

        $progress->progress_data = $currentProgress;
        $progress->save();

        return true;
    }

    /**
     * Check if challenge is completed and mark as completed
     */
    private function checkAndCompleteChallenge($progress, $challenge, $rules)
    {
        $challengeType = $challenge->challenge_type_id;
        $currentProgress = $progress->progress_data ?? [];
        $isCompleted = false;

        if ($challengeType === 'spending') {
            $minAmount = $rules['min_amount'] ?? 0;
            $currentSpending = $currentProgress['spending'] ?? 0;
            $isCompleted = $currentSpending >= $minAmount;
        } elseif ($challengeType === 'product') {
            $quantityRequired = $rules['quantity_required'] ?? 1;
            $totalQuantity = $currentProgress['total_quantity'] ?? 0;
            $isCompleted = $totalQuantity >= $quantityRequired;
        }

        if ($isCompleted && !$progress->is_completed) {
            // Mark as completed
            $progress->is_completed = true;
            $progress->completed_at = now();

            // Calculate reward expiry date
            if ($challenge->validity_period_days) {
                $progress->reward_expires_at = now()->addDays($challenge->validity_period_days);
            }

            $progress->save();

            Log::info('Challenge completed', [
                'member_id' => $progress->member_id,
                'challenge_id' => $challenge->id,
                'progress_id' => $progress->id,
                'completed_at' => $progress->completed_at,
                'reward_expires_at' => $progress->reward_expires_at
            ]);

            // TODO: Trigger reward distribution if immediate reward
            // if (isset($rules['immediate']) && $rules['immediate'] == true) {
            //     $this->distributeReward($progress, $challenge, $rules);
            // }
        }
    }

    /**
     * Get member transactions for a specific period
     */
    public function getMemberTransactions($memberId, $startDate = null, $endDate = null, $outletIds = null)
    {
        // memberId bisa berupa member_id dari member_apps_members atau id
        $query = DB::table('orders')
            ->where('member_id', $memberId)
            ->where('status', 'paid');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        if ($outletIds && is_array($outletIds) && !empty($outletIds)) {
            $outletCodes = DB::table('tbl_data_outlet')
                ->whereIn('id_outlet', $outletIds)
                ->pluck('qr_code')
                ->toArray();
            
            if (!empty($outletCodes)) {
                $query->whereIn('kode_outlet', $outletCodes);
            }
        }

        return $query->get();
    }

    /**
     * Get member order items for a specific period
     */
    public function getMemberOrderItems($memberId, $startDate = null, $endDate = null, $itemIds = null, $outletIds = null)
    {
        // memberId bisa berupa member_id dari member_apps_members atau id
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.member_id', $memberId)
            ->where('orders.status', 'paid');

        if ($startDate) {
            $query->where('orders.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('orders.created_at', '<=', $endDate);
        }

        if ($itemIds && is_array($itemIds) && !empty($itemIds)) {
            $query->whereIn('order_items.item_id', $itemIds);
        }

        if ($outletIds && is_array($outletIds) && !empty($outletIds)) {
            $outletCodes = DB::table('tbl_data_outlet')
                ->whereIn('id_outlet', $outletIds)
                ->pluck('qr_code')
                ->toArray();
            
            if (!empty($outletCodes)) {
                $query->whereIn('orders.kode_outlet', $outletCodes);
            }
        }

        return $query->select('order_items.*', 'orders.created_at as order_date', 'orders.kode_outlet')
            ->get();
    }
}

