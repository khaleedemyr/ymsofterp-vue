<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsWhatsOn;
use App\Models\MemberAppsWhatsOnCategory;
use Illuminate\Http\Request;

class WhatsOnController extends Controller
{
    /**
     * Get all active What's On items grouped by category
     * Featured items appear first in each category
     */
    public function index()
    {
        try {
            // Get all active categories
            $categories = MemberAppsWhatsOnCategory::where('is_active', true)
                ->orderBy('name', 'asc')
                ->get();

            // Get all active What's On items with category
            $whatsOnItems = MemberAppsWhatsOn::where('is_active', true)
                ->with('category')
                ->orderBy('published_at', 'desc')
                ->get();

            // Group by category
            $groupedData = [];
            
            foreach ($categories as $category) {
                // Get items for this category
                $categoryItems = $whatsOnItems->where('category_id', $category->id);
                
                if ($categoryItems->isEmpty()) {
                    continue; // Skip empty categories
                }

                // Separate featured and non-featured
                $featuredItems = $categoryItems->where('is_featured', true)->values();
                $nonFeaturedItems = $categoryItems->where('is_featured', false)->values();

                // Combine: featured first, then non-featured
                $allItems = $featuredItems->concat($nonFeaturedItems);

                $groupedData[] = [
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                    ],
                    'items' => $allItems->map(function ($item) {
                        $imageUrl = null;
                        if ($item->image) {
                            // Build full URL
                            if (str_starts_with($item->image, 'http://') || str_starts_with($item->image, 'https://')) {
                                $imageUrl = $item->image;
                            } else {
                                $imageUrl = 'https://ymsofterp.com/storage/' . ltrim($item->image, '/');
                            }
                        }

                        return [
                            'id' => $item->id,
                            'title' => $item->title,
                            'content' => $item->content,
                            'image' => $imageUrl,
                            'is_featured' => $item->is_featured,
                            'published_at' => $item->published_at ? $item->published_at->format('Y-m-d H:i:s') : null,
                            'category_id' => $item->category_id,
                        ];
                    })->toArray(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $groupedData,
                'message' => 'What\'s On items retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve What\'s On items: ' . $e->getMessage()
            ], 500);
        }
    }
}

