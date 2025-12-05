<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsBenefits;
use Illuminate\Http\Request;

class BenefitsController extends Controller
{
    public function index()
    {
        try {
            $benefits = MemberAppsBenefits::where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $benefits,
                'message' => 'Benefits retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve benefits: ' . $e->getMessage()
            ], 500);
        }
    }
}

