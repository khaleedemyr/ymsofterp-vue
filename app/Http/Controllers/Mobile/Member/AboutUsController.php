<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsAboutUs;
use Illuminate\Http\Request;

class AboutUsController extends Controller
{
    /**
     * Get active About Us
     */
    public function index()
    {
        try {
            $aboutUs = MemberAppsAboutUs::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$aboutUs) {
                return response()->json([
                    'success' => false,
                    'message' => 'No About Us found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $aboutUs->id,
                    'title' => $aboutUs->title,
                    'content' => $aboutUs->content,
                    'is_active' => $aboutUs->is_active,
                ],
                'message' => 'About Us retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve About Us: ' . $e->getMessage()
            ], 500);
        }
    }
}

