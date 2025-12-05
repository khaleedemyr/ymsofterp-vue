<?php

namespace App\Http\Controllers\Mobile\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberAppsFaq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Get all active FAQs
     */
    public function index()
    {
        try {
            $faqs = MemberAppsFaq::where('is_active', true)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($faq) {
                    return [
                        'id' => $faq->id,
                        'question' => $faq->question,
                        'answer' => $faq->answer,
                        'is_active' => $faq->is_active,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $faqs,
                'message' => 'FAQs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve FAQs: ' . $e->getMessage()
            ], 500);
        }
    }
}

