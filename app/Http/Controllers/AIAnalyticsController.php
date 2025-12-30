<?php

namespace App\Http\Controllers;

use App\Services\AIAnalyticsService;
use App\Http\Controllers\SalesOutletDashboardController;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
     * Q&A tentang dashboard dengan chat history
     */
    public function askQuestion(Request $request)
    {
        // Log method untuk debugging
        if ($request->method() !== 'POST') {
            Log::warning('AI Analytics: Invalid method in controller', [
                'method' => $request->method(),
                'intended_method' => $request->header('X-HTTP-Method-Override'),
                'url' => $request->fullUrl(),
                'referer' => $request->header('referer'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'content_type' => $request->header('content-type'),
                'all_headers' => $request->headers->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Method ' . $request->method() . ' tidak didukung. Silakan gunakan POST method untuk mengirim pertanyaan. Pastikan request menggunakan method POST.'
            ], 405);
        }
        
        try {
            $question = $request->get('question');
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
            $sessionId = $request->get('session_id', $this->getOrCreateSessionId($request));
            
            if (!$question || trim($question) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pertanyaan tidak boleh kosong'
                ], 400);
            }
            
            // Get chat history untuk context (dikurangi untuk performa)
            $chatHistory = $this->getChatHistoryForContext($sessionId, 3); // Ambil 3 chat terakhir saja untuk performa lebih cepat
            
            // Get dashboard data dengan cache untuk performa lebih cepat (cache 5 menit)
            $cacheKey = 'dashboard_data_ai_' . md5($dateFrom . '_' . $dateTo);
            $dashboardData = Cache::remember($cacheKey, 300, function() use ($dateFrom, $dateTo) {
                return $this->dashboardController->getDashboardDataPublic($dateFrom, $dateTo, 'daily');
            });
            
            // Detect context type dari pertanyaan untuk tracking
            $contextType = $this->detectContextType($question);
            
            // Get answer dengan akses database dinamis dan chat history
            $answer = $this->aiService->answerQuestion($question, $dashboardData, $dateFrom, $dateTo, $chatHistory);
            
            // Validate answer - pastikan tidak null atau empty
            if (empty($answer) || trim($answer) === '') {
                $answer = "Maaf, tidak dapat menjawab pertanyaan saat ini. Silakan coba lagi atau hubungi administrator jika masalah berlanjut.";
            }
            
            // Ensure answer is a string
            $answer = (string) $answer;
            
            // Save chat history dengan tracking user
            // Log untuk debugging race condition
            Log::info('AI Analytics: Saving chat history', [
                'session_id' => $sessionId,
                'question_length' => strlen($question),
                'answer_length' => strlen($answer),
                'method' => $request->method()
            ]);
            
            $user = auth()->user();
            $userId = $user ? $user->id : null;
            
            // Get user info untuk tracking
            $userInfo = null;
            if ($user) {
                $userInfo = [
                    'id' => $user->id,
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null,
                    'username' => $user->username ?? null,
                ];
            }
            
            try {
                $chatId = DB::table('ai_chat_history')->insertGetId([
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'question' => $question,
                    'answer' => $answer,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'metadata' => json_encode([
                        'provider' => config('ai.provider', 'gemini'),
                        'context_type' => $contextType,
                        'user_info' => $userInfo,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'created_at' => Carbon::now()->toDateTimeString()
                    ]),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
                
                Log::info('AI Analytics: Chat history saved successfully', [
                    'chat_id' => $chatId,
                    'session_id' => $sessionId
                ]);
            } catch (\Exception $e) {
                Log::error('AI Analytics: Failed to save chat history', [
                    'error' => $e->getMessage(),
                    'session_id' => $sessionId,
                    'question_length' => strlen($question)
                ]);
                // Continue anyway - jangan gagalkan response karena error save history
                $chatId = null;
            }
            
            return response()->json([
                'success' => true,
                'answer' => $answer,
                'question' => $question,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'session_id' => $sessionId,
                'chat_id' => $chatId
            ]);
        } catch (\Exception $e) {
            Log::error('AI Q&A Controller Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjawab pertanyaan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get chat history untuk session
     * Superadmin bisa lihat semua chat, user lain hanya chat miliknya sendiri
     */
    public function getChatHistory(Request $request)
    {
        // Log untuk debugging race condition
        Log::info('AI Analytics: getChatHistory called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'session_id_param' => $request->get('session_id')
        ]);
        
        try {
            $user = auth()->user();
            $isSuperadmin = $user && $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $requestedSessionId = $request->get('session_id');
            $limit = $request->get('limit', 50);
            
            // Jika session_id tidak ada, coba cari session_id terbaru dari database
            if (!$requestedSessionId || $requestedSessionId === '') {
                if ($isSuperadmin) {
                    // Superadmin: ambil session_id terbaru
                    $latestSession = DB::table('ai_chat_history')
                        ->orderBy('created_at', 'desc')
                        ->value('session_id');
                } else {
                    // User biasa: ambil session_id terbaru miliknya
                    $latestSession = DB::table('ai_chat_history')
                        ->where('user_id', $user->id)
                        ->orderBy('created_at', 'desc')
                        ->value('session_id');
                }
                $sessionId = $latestSession ?: $this->getOrCreateSessionId($request);
            } else {
                $sessionId = $requestedSessionId;
            }
            
            // Build query dengan filter yang lebih sederhana
            $query = DB::table('ai_chat_history')
                ->leftJoin('users', 'ai_chat_history.user_id', '=', 'users.id');
            
            // Filter berdasarkan role
            if ($isSuperadmin) {
                // Superadmin bisa lihat semua chat
                // Jika ada session_id, filter by session, jika tidak tampilkan semua
                if ($sessionId && $sessionId !== '' && $sessionId !== null) {
                    $query->where('ai_chat_history.session_id', $sessionId);
                }
            } else {
                // User biasa hanya bisa lihat chat miliknya sendiri
                $query->where('ai_chat_history.user_id', $user->id);
                
                // Jika session_id ada dan valid, filter by session
                // Tapi jika tidak ada hasil, fallback ke semua chat user tersebut
                if ($sessionId && $sessionId !== '' && $sessionId !== null) {
                    // Cek dulu apakah ada chat dengan session_id ini untuk user ini
                    $sessionExists = DB::table('ai_chat_history')
                        ->where('user_id', $user->id)
                        ->where('session_id', $sessionId)
                        ->exists();
                    
                    if ($sessionExists) {
                        $query->where('ai_chat_history.session_id', $sessionId);
                    }
                }
            }
            
            $history = $query->select(
                    'ai_chat_history.*',
                    'users.nama_lengkap as user_name',
                    'users.email as user_email',
                    'users.nik as user_username'
                )
                ->orderBy('ai_chat_history.created_at', 'asc')
                ->limit($limit)
                ->get();
            
            Log::info('Chat History Query Result', [
                'raw_count' => $history->count(),
                'first_item' => $history->first()
            ]);
            
            $historyArray = $history->map(function($item) {
                    $metadata = json_decode($item->metadata, true) ?? [];
                    return [
                        'id' => $item->id,
                        'question' => $item->question,
                        'answer' => $item->answer,
                        'date_from' => $item->date_from,
                        'date_to' => $item->date_to,
                        'session_id' => $item->session_id,
                        'user_id' => $item->user_id,
                        'user_name' => $item->user_name,
                        'user_email' => $item->user_email,
                        'user_username' => $item->user_username,
                        'created_at' => $item->created_at,
                        'created_at_formatted' => Carbon::parse($item->created_at)->format('d M Y, H:i')
                    ];
                })
                ->values()
                ->toArray();
            
            return response()->json([
                'success' => true,
                'history' => $historyArray,
                'session_id' => $sessionId,
                'is_superadmin' => $isSuperadmin,
                'total_count' => count($historyArray)
            ]);
        } catch (\Exception $e) {
            Log::error('Get Chat History Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat chat history: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get AI usage statistics by user (untuk admin tracking)
     * Superadmin bisa lihat semua statistik, user lain hanya statistik miliknya sendiri
     */
    public function getUsageStatistics(Request $request)
    {
        try {
            $user = auth()->user();
            $isSuperadmin = $user && $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
            
            // Base query untuk statistics by user
            $statsQuery = DB::table('ai_chat_history')
                ->leftJoin('users', 'ai_chat_history.user_id', '=', 'users.id')
                ->whereBetween('ai_chat_history.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
            
            // Filter berdasarkan role
            if (!$isSuperadmin) {
                // User biasa hanya bisa lihat statistik miliknya sendiri
                $statsQuery->where('ai_chat_history.user_id', $user->id);
            }
            
            $statsByUser = $statsQuery->select(
                    'ai_chat_history.user_id',
                    'users.nama_lengkap as user_name',
                    'users.email as user_email',
                    'users.nik as user_username',
                    DB::raw('COUNT(*) as total_questions'),
                    DB::raw('MIN(ai_chat_history.created_at) as first_question'),
                    DB::raw('MAX(ai_chat_history.created_at) as last_question')
                )
                ->groupBy('ai_chat_history.user_id', 'users.nama_lengkap', 'users.email', 'users.nik')
                ->orderBy('total_questions', 'desc')
                ->get();
            
            // Base query untuk total statistics
            $totalStatsQuery = DB::table('ai_chat_history')
                ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
            
            // Filter berdasarkan role
            if (!$isSuperadmin) {
                // User biasa hanya bisa lihat statistik miliknya sendiri
                $totalStatsQuery->where('user_id', $user->id);
            }
            
            $totalStats = $totalStatsQuery->select(
                    DB::raw('COUNT(*) as total_questions'),
                    DB::raw('COUNT(DISTINCT user_id) as total_users'),
                    DB::raw('COUNT(DISTINCT session_id) as total_sessions')
                )
                ->first();
            
            return response()->json([
                'success' => true,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_stats' => $totalStats,
                'stats_by_user' => $statsByUser,
                'is_superadmin' => $isSuperadmin
            ]);
        } catch (\Exception $e) {
            Log::error('Get Usage Statistics Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat statistik: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Clear chat history untuk session
     * Superadmin bisa hapus semua chat, user lain hanya bisa hapus chat miliknya sendiri
     */
    public function clearChatHistory(Request $request)
    {
        try {
            $user = auth()->user();
            $isSuperadmin = $user && $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $sessionId = $request->get('session_id');
            
            if (!$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session ID tidak boleh kosong'
                ], 400);
            }
            
            $query = DB::table('ai_chat_history')
                ->where('session_id', $sessionId);
            
            // Filter berdasarkan role
            if (!$isSuperadmin) {
                // User biasa hanya bisa hapus chat miliknya sendiri
                $query->where('user_id', $user->id);
            }
            
            $deleted = $query->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Chat history berhasil dihapus',
                'deleted_count' => $deleted
            ]);
        } catch (\Exception $e) {
            Log::error('Clear Chat History Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus chat history: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all chat history (hanya untuk superadmin)
     */
    public function getAllChatHistory(Request $request)
    {
        try {
            $user = auth()->user();
            $isSuperadmin = $user && $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            // Hanya superadmin yang bisa akses
            if (!$isSuperadmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya superadmin yang dapat melihat semua chat.'
                ], 403);
            }
            
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $userId = $request->get('user_id'); // Filter by user jika ada
            $limit = $request->get('limit', 100);
            
            $query = DB::table('ai_chat_history')
                ->leftJoin('users', 'ai_chat_history.user_id', '=', 'users.id');
            
            // Filter by date range jika ada
            if ($dateFrom && $dateTo) {
                $query->whereBetween('ai_chat_history.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
            }
            
            // Filter by user jika ada
            if ($userId) {
                $query->where('ai_chat_history.user_id', $userId);
            }
            
            $history = $query->select(
                    'ai_chat_history.*',
                    'users.nama_lengkap as user_name',
                    'users.email as user_email',
                    'users.nik as user_username'
                )
                ->orderBy('ai_chat_history.created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($item) {
                    $metadata = json_decode($item->metadata, true) ?? [];
                    return [
                        'id' => $item->id,
                        'question' => $item->question,
                        'answer' => $item->answer,
                        'date_from' => $item->date_from,
                        'date_to' => $item->date_to,
                        'session_id' => $item->session_id,
                        'user_id' => $item->user_id,
                        'user_name' => $item->user_name,
                        'user_email' => $item->user_email,
                        'user_username' => $item->user_username,
                        'ip_address' => $metadata['ip_address'] ?? null,
                        'created_at' => $item->created_at,
                        'created_at_formatted' => Carbon::parse($item->created_at)->format('d M Y, H:i')
                    ];
                });
            
            return response()->json([
                'success' => true,
                'history' => $history,
                'total' => $history->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Get All Chat History Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat chat history: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get or create session ID
     */
    private function getOrCreateSessionId(Request $request)
    {
        $sessionId = $request->cookie('ai_chat_session_id');
        
        if (!$sessionId) {
            $sessionId = 'ai_chat_' . Str::random(32) . '_' . time();
        }
        
        return $sessionId;
    }
    
    /**
     * Get chat history untuk context (internal)
     */
    private function getChatHistoryForContext($sessionId, $limit = 10)
    {
        $user = auth()->user();
        $userId = $user ? $user->id : null;
        
        $query = DB::table('ai_chat_history');
        
        // Filter by user_id jika ada
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        // Filter by session_id jika ada
        if ($sessionId && $sessionId !== '') {
            $query->where('session_id', $sessionId);
        }
        
        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(function($item) {
                return [
                    'question' => $item->question,
                    'answer' => $item->answer
                ];
            })
            ->toArray();
    }
    
    /**
     * Detect context type dari pertanyaan untuk tracking
     * 
     * @param string $question
     * @return string
     */
    private function detectContextType($question)
    {
        $questionLower = strtolower($question);
        
        // Check for BOM queries
        if (preg_match('/(bom|bill of materials|bahan baku|material|composed|produksi|production)/i', $question)) {
            return 'bom';
        }
        
        // Check for inventory queries
        if (preg_match('/(inventory|stock|stok|persediaan|gudang|warehouse)/i', $question)) {
            // Check if also mentions sales
            if (preg_match('/(sales|penjualan|revenue|order)/i', $question)) {
                return 'cross';
            }
            return 'inventory';
        }
        
        // Check for correlation queries
        if (preg_match('/(korelasi|correlation|hubungan|dampak|impact|stock.*sales|sales.*stock|overstock|stockout|habis)/i', $question)) {
            return 'cross';
        }
        
        // Default to sales
        return 'sales';
    }
}

