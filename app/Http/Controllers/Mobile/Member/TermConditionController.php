<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsTermCondition;
use Illuminate\Http\Request;

class TermConditionController extends Controller
{
    /**
     * Get active Terms & Conditions
     */
    public function index()
    {
        try {
            $termCondition = MemberAppsTermCondition::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$termCondition) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Terms & Conditions found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $termCondition->id,
                    'title' => $termCondition->title,
                    'content' => $termCondition->content,
                    'is_active' => $termCondition->is_active,
                ],
                'message' => 'Terms & Conditions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Terms & Conditions: ' . $e->getMessage()
            ], 500);
        }
    }
}

