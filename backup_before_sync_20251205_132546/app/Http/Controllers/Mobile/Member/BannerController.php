<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsBanner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * Get all active banners
     */
    public function index()
    {
        try {
            $banners = MemberAppsBanner::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get()
                ->map(function ($banner) {
                    // Build full URL for image (same format as RewardController)
                    $imageUrl = null;
                    if ($banner->image) {
                        // If image path already contains full URL, use it as is
                        if (str_starts_with($banner->image, 'http://') || str_starts_with($banner->image, 'https://')) {
                            $imageUrl = $banner->image;
                        } else {
                            // Build full URL: https://ymsofterp.com/storage/{path}
                            $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($banner->image, '/');
                        }
                    }

                    return [
                        'id' => $banner->id,
                        'title' => $banner->title,
                        'image' => $imageUrl,
                        'description' => $banner->description,
                        'sort_order' => $banner->sort_order,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $banners,
                'message' => 'Banners retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve banners: ' . $e->getMessage()
            ], 500);
        }
    }
}

