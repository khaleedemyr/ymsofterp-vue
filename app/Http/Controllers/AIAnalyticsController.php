<?php

namespace App\Http\Controllers;

use App\Services\AIAnalyticsService;
use App\Http\Controllers\SalesOutletDashboardController;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AIAnalyticsController extends Controller
{
    private $aiService;
    private $dashboardController;
    
    public function __construct(
        AIAnalyticsService $aiService,
        SalesOutletDashboardController $dashboardController
    ) {
        $this->aiService = $aiService;
        $this->dashboardController = $dashboardController;
    }
    
    /**
     * Get auto insight untuk dashboard
     */
    public function getAutoInsight(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
            
            // Get dashboard data
            $dashboardData = $this->dashboardController->getDashboardDataPublic($dateFrom, $dateTo, 'daily');
            
            // Generate insight
            $insight = $this->aiService->generateAutoInsight($dashboardData);
            
            return response()->json([
                'success' => true,
                'insight' => $insight,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
        } catch (\Exception $e) {
            Log::error('AI Analytics Controller Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghasilkan insight: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Q&A tentang dashboard
     */
    public function askQuestion(Request $request)
    {
        try {
            $question = $request->get('question');
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
            
            if (!$question || trim($question) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pertanyaan tidak boleh kosong'
                ], 400);
            }
            
            // Get dashboard data
            $dashboardData = $this->dashboardController->getDashboardDataPublic($dateFrom, $dateTo, 'daily');
            
            // Get answer dengan akses database dinamis
            $answer = $this->aiService->answerQuestion($question, $dashboardData, $dateFrom, $dateTo);
            
            return response()->json([
                'success' => true,
                'answer' => $answer,
                'question' => $question,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
        } catch (\Exception $e) {
            Log::error('AI Q&A Controller Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjawab pertanyaan: ' . $e->getMessage()
            ], 500);
        }
    }
}

