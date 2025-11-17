<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsReward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    /**
     * Get all active rewards dengan join ke items dan item_images
     */
    public function index()
    {
        try {
            // Ambil rewards dengan items, categories, dan sub_categories
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

            // Ambil gambar pertama untuk setiap item
            $rewards = $rewards->map(function ($reward) {
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
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $rewards,
                'message' => 'Rewards retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rewards: ' . $e->getMessage(),
                'error' => $e->getTraceAsString()
            ], 500);
        }
    }
}

