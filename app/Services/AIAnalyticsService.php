<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\AIDatabaseHelper;
use App\Services\AIBudgetService;
use Carbon\Carbon;

class AIAnalyticsService
{
    private $apiKey;
    private $model;
    private $apiUrl;
    private $dbHelper;
    
    private $provider;
    private $openaiKey;
    private $claudeKey;
    private $budgetService;
    
    public function __construct(AIDatabaseHelper $dbHelper, AIBudgetService $budgetService)
    {
        $this->provider = config('ai.provider', 'gemini');
        $this->apiKey = config('ai.gemini.api_key');
        $this->openaiKey = config('ai.openai.api_key');
        $this->claudeKey = config('ai.claude.api_key');
        $this->model = config('ai.gemini.model', 'gemini-1.5-pro');
        $this->dbHelper = $dbHelper;
        $this->budgetService = $budgetService;
        
        // Validate API key based on provider
        if ($this->provider === 'gemini' && !$this->apiKey) {
            throw new \Exception('Google Gemini API key not configured');
        } elseif ($this->provider === 'openai' && !$this->openaiKey) {
            throw new \Exception('OpenAI API key not configured');
        } elseif ($this->provider === 'claude' && !$this->claudeKey) {
            throw new \Exception('Anthropic Claude API key not configured');
        }
        
        // Build API URL based on provider
        if ($this->provider === 'gemini') {
            $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent';
        }
    }
    
    /**
     * Generate auto insight dari dashboard data
     */
    public function generateAutoInsight($dashboardData)
    {
        // Check budget limit jika menggunakan Claude
        if ($this->provider === 'claude') {
            if ($this->budgetService->isBudgetExceeded()) {
                $currentUsage = $this->budgetService->getCurrentMonthUsage();
                $budgetLimit = $this->budgetService->getBudgetLimit();
                return "⚠️ Budget AI untuk bulan ini sudah habis. " .
                       "Penggunaan: Rp " . number_format($currentUsage, 0, ',', '.') . 
                       " dari limit Rp " . number_format($budgetLimit, 0, ',', '.') . ". " .
                       "Silakan gunakan kembali bulan depan.";
            }
        }
        
        // Cache untuk 1 jam (data tidak berubah cepat)
        $cacheKey = 'ai_insight_' . md5(json_encode($dashboardData));
        
        return Cache::remember($cacheKey, 3600, function() use ($dashboardData) {
            try {
                $prompt = $this->buildInsightPrompt($dashboardData);
                
                // Call Google Gemini API via HTTP
                // Use gemini-2.5-flash (available model from API)
                $model = config('ai.gemini.model', 'gemini-2.5-flash');
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;
                
                // Enhanced prompt dengan context eksternal
                $enhancedPrompt = $this->enhancePromptWithExternalContext($prompt, $dashboardData);
                
                // Call API berdasarkan provider
                if ($this->provider === 'claude') {
                    // Claude API implementation
                    // Try multiple model names in order of preference
                    $modelConfig = config('ai.claude.model', 'claude-sonnet-4-5-20250929');
                    
                    // List of valid Claude models to try (based on Anthropic console)
                    $validModels = [
                        'claude-sonnet-4-5-20250929',   // Claude Sonnet 4.5 (latest, recommended)
                        'claude-opus-4-5-20251101',     // Claude Opus 4.5 (most powerful)
                        'claude-haiku-4-5-20251001',     // Claude Haiku 4.5 (fastest, cheapest)
                        'claude-opus-4-1-20250805',     // Claude Opus 4.1
                        'claude-3-5-haiku-20241022',    // Claude 3.5 Haiku (fallback)
                        'claude-3-sonnet-20240229',     // Claude 3 Sonnet (fallback)
                    ];
                    
                    // Use configured model if valid, otherwise use first valid model
                    $model = in_array($modelConfig, $validModels) ? $modelConfig : $validModels[0];
                    
                    $url = 'https://api.anthropic.com/v1/messages';
                    
                    $requestBody = [
                        'model' => $model,
                        'max_tokens' => config('ai.claude.max_tokens', 8192),
                        'temperature' => config('ai.claude.temperature', 0.7),
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $enhancedPrompt
                            ]
                        ]
                    ];
                    
                    // Setup HTTP client untuk Claude dengan timeout lebih lama (120 detik untuk prompt panjang)
                    $httpClient = Http::timeout(180)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                            'x-api-key' => $this->claudeKey,
                            'anthropic-version' => '2023-06-01',
                        ]);
                    
                    // Disable SSL verification hanya untuk development
                    if (config('app.env') === 'local' || config('app.debug')) {
                        $httpClient = $httpClient->withoutVerifying();
                    }
                    
                    // Try multiple models if first one fails
                    $modelsToTry = [$model];
                    // Add fallback models
                    $fallbackModels = [
                        'claude-haiku-4-5-20251001',     // Fastest, cheapest
                        'claude-3-5-haiku-20241022',     // Older version fallback
                        'claude-3-sonnet-20240229',      // Older version fallback
                    ];
                    foreach ($fallbackModels as $fallback) {
                        if (!in_array($fallback, $modelsToTry)) {
                            $modelsToTry[] = $fallback;
                        }
                    }
                    
                    $lastError = null;
                    $maxRetries = 2; // Retry maksimal 2 kali per model
                    
                    foreach ($modelsToTry as $tryModel) {
                        $requestBody['model'] = $tryModel;
                        
                        // Retry logic dengan exponential backoff untuk handle timeout
                        $retryCount = 0;
                        $response = null;
                        $requestSuccessful = false;
                        
                        while ($retryCount <= $maxRetries && !$requestSuccessful) {
                            try {
                                $response = $httpClient->post($url, $requestBody);
                                
                                // Jika berhasil, keluar dari retry loop
                                if ($response->successful()) {
                                    $requestSuccessful = true;
                                    break;
                                }
                                
                                // Jika error tapi bukan timeout, langsung lanjut ke model berikutnya
                                if ($response->status() !== 408 && $response->status() !== 504) {
                                    $lastError = $response->json() ?? $response->body();
                                    Log::warning('Claude model failed (non-timeout) for insight', [
                                        'model' => $tryModel,
                                        'status' => $response->status(),
                                        'error' => $lastError
                                    ]);
                                    break; // Keluar dari retry loop, lanjut ke model berikutnya
                                }
                                
                                // Jika timeout, retry dengan delay
                                if ($retryCount < $maxRetries) {
                                    $retryCount++;
                                    $delay = pow(2, $retryCount) * 2; // Exponential backoff: 4s, 8s
                                    Log::warning('Claude API timeout for insight, retrying', [
                                        'model' => $tryModel,
                                        'retry' => $retryCount,
                                        'delay' => $delay
                                    ]);
                                    sleep($delay);
                                    continue;
                                } else {
                                    // Sudah retry maksimal, simpan error dan lanjut ke model berikutnya
                                    $lastError = $response->json() ?? ['error' => 'Request timeout after ' . ($maxRetries + 1) . ' attempts'];
                                    Log::error('Claude API timeout after max retries for insight', [
                                        'model' => $tryModel,
                                        'retries' => $maxRetries
                                    ]);
                                    break;
                                }
                            } catch (\Exception $e) {
                                // Handle connection timeout atau error lainnya
                                if (strpos($e->getMessage(), 'timeout') !== false || strpos($e->getMessage(), 'timed out') !== false) {
                                    if ($retryCount < $maxRetries) {
                                        $retryCount++;
                                        $delay = pow(2, $retryCount) * 2;
                                        Log::warning('Claude API connection timeout for insight, retrying', [
                                            'model' => $tryModel,
                                            'retry' => $retryCount,
                                            'delay' => $delay,
                                            'error' => $e->getMessage()
                                        ]);
                                        sleep($delay);
                                        continue;
                                    } else {
                                        // Sudah retry maksimal
                                        $lastError = ['error' => ['message' => 'Connection timeout after ' . ($maxRetries + 1) . ' attempts: ' . $e->getMessage()]];
                                        Log::error('Claude API connection timeout after max retries for insight', [
                                            'model' => $tryModel,
                                            'retries' => $maxRetries,
                                            'error' => $e->getMessage()
                                        ]);
                                        break; // Keluar dari retry loop, lanjut ke model berikutnya
                                    }
                                }
                                // Jika bukan timeout error, simpan error dan lanjut ke model berikutnya
                                $lastError = ['error' => ['message' => $e->getMessage()]];
                                Log::error('Claude API error for insight', [
                                    'model' => $tryModel,
                                    'error' => $e->getMessage()
                                ]);
                                break;
                            }
                        }
                        
                        if ($requestSuccessful && $response && $response->successful()) {
                            $data = $response->json();
                            
                            // Extract text from Claude response
                            if (isset($data['content'][0]['text'])) {
                                $text = $data['content'][0]['text'];
                                
                                // Log usage untuk Claude
                                $inputTokens = $data['usage']['input_tokens'] ?? (int)(strlen($enhancedPrompt) / 4);
                                $outputTokens = $data['usage']['output_tokens'] ?? (int)(strlen($text) / 4);
                                
                                $cost = $this->budgetService->calculateCost('claude', $inputTokens, $outputTokens);
                                $this->budgetService->logUsage('claude', 'insight', $inputTokens, $outputTokens, $cost['total_cost_usd'], $cost['total_cost_rupiah']);
                                
                                return trim($text);
                            } else {
                                Log::error('Claude Response format unexpected', ['response' => $data]);
                                return "Maaf, format response tidak sesuai. Silakan coba lagi.";
                            }
                        } else {
                            // Request tidak berhasil atau timeout setelah retry
                            if ($response) {
                                $errorBody = $response->json() ?? $response->body();
                                $lastError = $errorBody;
                                Log::warning('Claude model failed for insight, trying next', [
                                    'model' => $tryModel,
                                    'status' => $response->status(),
                                    'error' => $errorBody
                                ]);
                            } else {
                                // Response null karena exception
                                Log::warning('Claude model failed for insight (no response), trying next', [
                                    'model' => $tryModel,
                                    'error' => $lastError ?? 'Unknown error'
                                ]);
                            }
                            
                            // Continue to next model
                            continue;
                        }
                    }
                    
                    // All models failed
                    $errorMsg = is_array($lastError) && isset($lastError['error']['message']) 
                        ? $lastError['error']['message'] 
                        : 'Semua model Claude gagal. Pastikan API key valid dan memiliki akses ke model Claude.';
                    
                    Log::error('Claude API Error - All models failed for insight', [
                        'models_tried' => $modelsToTry,
                        'last_error' => $lastError,
                    ]);
                    
                    return "Maaf, terjadi kesalahan saat memanggil Claude API. " . $errorMsg . 
                           " Silakan cek API key di Anthropic console atau hubungi administrator.";
                } else {
                    // Gemini API implementation
                    $model = config('ai.gemini.model', 'gemini-1.5-pro');
                    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;
                    
                    $requestBody = [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => $enhancedPrompt
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => config('ai.gemini.max_tokens', 4000),
                        ],
                        'tools' => [
                            [
                                'function_declarations' => $this->getFunctionDeclarations()
                            ]
                        ]
                    ];
                    
                    // Setup HTTP client
                    $httpClient = Http::timeout(60)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                        ]);
                    
                    // Disable SSL verification hanya untuk development (Laragon issue)
                    // Di production, SSL verification harus aktif untuk security
                    if (config('app.env') === 'local' || config('app.debug')) {
                        $httpClient = $httpClient->withoutVerifying();
                    }
                    
                    $response = $httpClient->post($url, $requestBody);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        
                        // Extract text from response
                        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                            $text = $data['candidates'][0]['content']['parts'][0]['text'];
                            return trim($text);
                        } else {
                            Log::error('AI Response format unexpected', ['response' => $data]);
                            return "Maaf, format response tidak sesuai. Silakan coba lagi.";
                        }
                    } else {
                        $errorBody = $response->json() ?? $response->body();
                        Log::error('AI API Error', [
                            'status' => $response->status(),
                            'body' => $errorBody,
                            'url' => $this->apiUrl
                        ]);
                        // Return more detailed error for debugging
                        $errorMsg = is_array($errorBody) && isset($errorBody['error']['message']) 
                            ? $errorBody['error']['message'] 
                            : 'API Error: ' . $response->status();
                        return "Maaf, terjadi kesalahan saat memanggil AI. " . $errorMsg . " (Status: " . $response->status() . ")";
                    }
                }
            } catch (\Exception $e) {
                Log::error('AI Insight Error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                // Return more detailed error for debugging (remove in production)
                return "Maaf, tidak dapat menghasilkan insight saat ini. Error: " . $e->getMessage() . ". Silakan cek log untuk detail.";
            }
        });
    }
    
    /**
     * Helper: Convert object to array recursively
     */
    private function toArray($data)
    {
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->toArray($value);
            }
        }
        return $data;
    }
    
    /**
     * Build prompt untuk insight
     */
    private function buildInsightPrompt($data)
    {
        // Convert object to array if needed (recursive)
        $data = $this->toArray($data);
        
        $overview = $data['overview'] ?? [];
        $topItems = array_slice($data['topItems'] ?? [], 0, 5);
        $hourlySales = $data['hourlySales'] ?? [];
        $paymentMethods = $data['paymentMethods'] ?? [];
        $salesTrend = $data['salesTrend'] ?? [];
        
        $prompt = "Analisa data dashboard penjualan outlet berikut dan berikan insight singkat (maksimal 5 poin) dalam bahasa Indonesia:\n\n";
        
        $prompt .= "=== OVERVIEW ===\n";
        $prompt .= "Total Orders: " . ($overview['total_orders'] ?? 0) . "\n";
        $prompt .= "Total Revenue: Rp " . number_format($overview['total_revenue'] ?? 0, 0, ',', '.') . "\n";
        $prompt .= "Average Order Value: Rp " . number_format($overview['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
        $prompt .= "Total Customers: " . ($overview['total_customers'] ?? 0) . "\n";
        
        if (isset($overview['orders_growth'])) {
            $growthIcon = $overview['orders_growth'] >= 0 ? '✅' : '⚠️';
            $prompt .= "Growth Orders: {$growthIcon} " . number_format($overview['orders_growth'], 1) . "%\n";
        }
        if (isset($overview['revenue_growth'])) {
            $growthIcon = $overview['revenue_growth'] >= 0 ? '✅' : '⚠️';
            $prompt .= "Growth Revenue: {$growthIcon} " . number_format($overview['revenue_growth'], 1) . "%\n";
        }
        
        if (!empty($topItems)) {
            $prompt .= "\n=== TOP 5 ITEMS ===\n";
            foreach ($topItems as $index => $item) {
                $item = $this->toArray($item);
                $prompt .= ($index + 1) . ". " . ($item['item_name'] ?? 'N/A') . " - Qty: " . ($item['total_qty'] ?? 0) . ", Revenue: Rp " . number_format($item['total_revenue'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        // Find peak hour
        if (!empty($hourlySales)) {
            $peakHour = collect($hourlySales)->sortByDesc(function($item) {
                $item = $this->toArray($item);
                return $item['orders'] ?? 0;
            })->first();
            if ($peakHour) {
                $peakHour = $this->toArray($peakHour);
                $prompt .= "\n=== PEAK HOUR ===\n";
                $prompt .= "Jam: " . ($peakHour['hour'] ?? 'N/A') . ":00 dengan " . ($peakHour['orders'] ?? 0) . " orders, Revenue: Rp " . number_format($peakHour['revenue'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        // Payment methods summary
        if (!empty($paymentMethods)) {
            $topPayment = collect($paymentMethods)->sortByDesc(function($item) {
                $item = $this->toArray($item);
                return $item['total_amount'] ?? 0;
            })->first();
            if ($topPayment) {
                $topPayment = $this->toArray($topPayment);
                $prompt .= "\n=== PAYMENT METHOD ===\n";
                $prompt .= "Metode pembayaran terpopuler: " . ($topPayment['payment_code'] ?? 'N/A') . " dengan " . number_format($topPayment['total_amount'] ?? 0, 0, ',', '.') . " transaksi\n";
            }
        }
        
        // Sales trend per hari (DAILY SALES DATA)
        if (!empty($salesTrend)) {
            $prompt .= "\n=== SALES TREND PER HARI (DAILY SALES) ===\n";
            $prompt .= "Data penjualan harian untuk analisis trend dan perbandingan tanggal:\n";
            // Show summary: best day, worst day, average
            $revenues = array_map(function($day) {
                $day = $this->toArray($day);
                return ['date' => $day['period'] ?? 'N/A', 'revenue' => $day['revenue'] ?? 0, 'orders' => $day['orders'] ?? 0];
            }, $salesTrend);
            if (!empty($revenues)) {
                $bestDay = collect($revenues)->sortByDesc('revenue')->first();
                $worstDay = collect($revenues)->sortBy('revenue')->first();
                $avgRevenue = collect($revenues)->avg('revenue');
                $prompt .= "Best Day: {$bestDay['date']} - Revenue Rp " . number_format($bestDay['revenue'], 0, ',', '.') . ", Orders: {$bestDay['orders']}\n";
                $prompt .= "Worst Day: {$worstDay['date']} - Revenue Rp " . number_format($worstDay['revenue'], 0, ',', '.') . ", Orders: {$worstDay['orders']}\n";
                $prompt .= "Average Daily Revenue: Rp " . number_format($avgRevenue, 0, ',', '.') . "\n";
            }
            foreach (array_slice($salesTrend, 0, 7) as $day) {
                $day = $this->toArray($day);
                $date = $day['period'] ?? 'N/A';
                $orders = $day['orders'] ?? 0;
                $revenue = $day['revenue'] ?? 0;
                $customers = $day['customers'] ?? 0;
                $avgOrderValue = $day['avg_order_value'] ?? 0;
                $prompt .= "Tanggal: {$date} - Orders: {$orders}, Revenue: Rp " . number_format($revenue, 0, ',', '.') . ", Customers: {$customers}, Avg Order Value: Rp " . number_format($avgOrderValue, 0, ',', '.') . "\n";
            }
        }
        
        // Revenue per outlet
        $revenuePerOutlet = $data['revenuePerOutlet'] ?? [];
        if (!empty($revenuePerOutlet)) {
            $prompt .= "\n=== REVENUE PER OUTLET ===\n";
            $prompt .= "Data penjualan per outlet untuk analisis performa outlet:\n";
            foreach ($revenuePerOutlet as $regionName => $regionData) {
                $regionData = $this->toArray($regionData);
                $prompt .= "\nRegion: {$regionName}\n";
                $prompt .= "  Total Revenue: Rp " . number_format($regionData['total_revenue'] ?? 0, 0, ',', '.') . "\n";
                $prompt .= "  Total Orders: " . ($regionData['total_orders'] ?? 0) . "\n";
                if (isset($regionData['outlets']) && is_array($regionData['outlets'])) {
                    foreach (array_slice($regionData['outlets'], 0, 5) as $outlet) {
                        $outlet = $this->toArray($outlet);
                        $prompt .= "  - Outlet: " . ($outlet['outlet_name'] ?? 'N/A') . " (" . ($outlet['outlet_code'] ?? 'N/A') . ")\n";
                        $prompt .= "    Revenue: Rp " . number_format($outlet['total_revenue'] ?? 0, 0, ',', '.') . ", Orders: " . ($outlet['order_count'] ?? 0) . ", Customers: " . ($outlet['total_pax'] ?? 0) . "\n";
                    }
                }
            }
        }
        
        // Lunch/Dinner Orders
        $lunchDinnerOrders = $data['lunchDinnerOrders'] ?? [];
        if (!empty($lunchDinnerOrders)) {
            $lunchDinnerOrders = $this->toArray($lunchDinnerOrders);
            $prompt .= "\n=== LUNCH vs DINNER ===\n";
            if (isset($lunchDinnerOrders['lunch'])) {
                $lunch = $lunchDinnerOrders['lunch'];
                $prompt .= "Lunch: Orders " . ($lunch['order_count'] ?? 0) . ", Revenue Rp " . number_format($lunch['total_revenue'] ?? 0, 0, ',', '.') . "\n";
            }
            if (isset($lunchDinnerOrders['dinner'])) {
                $dinner = $lunchDinnerOrders['dinner'];
                $prompt .= "Dinner: Orders " . ($dinner['order_count'] ?? 0) . ", Revenue Rp " . number_format($dinner['total_revenue'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        // Weekday/Weekend Revenue
        $weekdayWeekendRevenue = $data['weekdayWeekendRevenue'] ?? [];
        if (!empty($weekdayWeekendRevenue)) {
            $weekdayWeekendRevenue = $this->toArray($weekdayWeekendRevenue);
            $prompt .= "\n=== WEEKDAY vs WEEKEND ===\n";
            if (isset($weekdayWeekendRevenue['weekday'])) {
                $weekday = $weekdayWeekendRevenue['weekday'];
                $prompt .= "Weekday: Orders " . ($weekday['order_count'] ?? 0) . ", Revenue Rp " . number_format($weekday['total_revenue'] ?? 0, 0, ',', '.') . "\n";
            }
            if (isset($weekdayWeekendRevenue['weekend'])) {
                $weekend = $weekdayWeekendRevenue['weekend'];
                $prompt .= "Weekend: Orders " . ($weekend['order_count'] ?? 0) . ", Revenue Rp " . number_format($weekend['total_revenue'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        // Promo Usage
        $promoUsage = $data['promoUsage'] ?? [];
        if (!empty($promoUsage)) {
            $promoUsage = $this->toArray($promoUsage);
            $prompt .= "\n=== PROMO USAGE ===\n";
            $prompt .= "Orders with Promo: " . ($promoUsage['orders_with_promo'] ?? 0) . " (" . number_format($promoUsage['promo_usage_percentage'] ?? 0, 2) . "%)\n";
        }
        
        $prompt .= "\n=== INSTRUKSI INSIGHT YANG SANGAT PINTAR ===\n";
        $prompt .= "Berikan insight yang DEEP dan ACTIONABLE dengan:\n\n";
        
        $prompt .= "1. ANALISIS MULTI-DIMENSIONAL:\n";
        $prompt .= "   - Jangan hanya lihat satu metrik - analisis KORELASI antar metrik\n";
        $prompt .= "   - Bandingkan dengan periode sebelumnya, outlet lain, atau benchmark\n";
        $prompt .= "   - Identifikasi ANOMALI dan jelaskan kemungkinan penyebabnya\n";
        $prompt .= "   - Gunakan data Lunch/Dinner, Weekday/Weekend untuk memberikan konteks\n\n";
        
        $prompt .= "2. KEDALAMAN INSIGHT:\n";
        $prompt .= "   - JANGAN hanya memberikan angka - BERIKAN INTERPRETASI\n";
        $prompt .= "   - Identifikasi TREND (naik/turun/stabil) dan berikan alasan\n";
        $prompt .= "   - Highlight OUTLIER atau ANOMALI yang perlu perhatian\n";
        $prompt .= "   - Berikan CONTEXT - mengapa insight ini penting?\n\n";
        
        $prompt .= "3. REKOMENDASI YANG ACTIONABLE:\n";
        $prompt .= "   - Setiap insight harus diikuti dengan REKOMENDASI yang spesifik\n";
        $prompt .= "   - Rekomendasi harus BISA DITINDAKLANJUTI dengan jelas\n";
        $prompt .=   "   - Prioritaskan berdasarkan IMPACT dan URGENCY\n\n";
        
        $prompt .= "4. FORMAT:\n";
        $prompt .= "   - Gunakan emoji: ✅ untuk positif, ⚠️ untuk warning, 💡 untuk rekomendasi, 📊 untuk data, 🔍 untuk analisis\n";
        $prompt .= "   - Maksimal 5 poin insight (pilih yang paling penting dan impactful)\n";
        $prompt .= "   - Bahasa Indonesia yang mudah dipahami\n";
        $prompt .= "   - Format: [Emoji] [Judul Insight] - [Penjelasan] - [Rekomendasi]\n";
        $prompt .= "   - Sertakan ANGKA SPESIFIK dari data yang tersedia\n\n";
        
        $prompt .= "5. CONTOH INSIGHT YANG BAIK:\n";
        $prompt .= "   '📊 Revenue periode ini Rp X, naik Y% dari periode sebelumnya. Analisis menunjukkan:\n";
        $prompt .= "   - Growth didorong oleh peningkatan orders (A ke B, +C%) dan avg order value (Rp D ke Rp E, +F%)\n";
        $prompt .= "   - Outlet X menjadi kontributor terbesar dengan revenue Rp G (H% dari total)\n";
        $prompt .= "   - Weekend menunjukkan performa lebih baik (revenue Rp I vs weekday Rp J, +K%)\n";
        $prompt .= "   💡 Rekomendasi: Fokus pada strategi weekend untuk outlet Y dan Z yang masih underperform'\n";
        
        return $prompt;
    }
    
    /**
     * Enhance prompt dengan context eksternal (cuaca, event, tren pasar)
     */
    private function enhancePromptWithExternalContext($prompt, $dashboardData)
    {
        if (!config('ai.external_data.enabled', true)) {
            return $prompt;
        }
        
        $externalContext = "\n\n=== CONTEXT EKSTERNAL (untuk analisis yang lebih mendalam) ===\n";
        
        // Ambil data cuaca jika relevan
        $weatherData = $this->getWeatherContext($dashboardData);
        if ($weatherData) {
            $externalContext .= "\n--- DATA CUACA ---\n";
            $externalContext .= $weatherData . "\n";
        }
        
        // Ambil data event/holiday jika relevan
        $eventData = $this->getEventContext($dashboardData);
        if ($eventData) {
            $externalContext .= "\n--- EVENT/HOLIDAY ---\n";
            $externalContext .= $eventData . "\n";
        }
        
        // Tambahkan tren pasar umum
        $externalContext .= "\n--- INSTRUKSI ANALISIS EKSTERNAL ---\n";
        $externalContext .= "- Gunakan data eksternal untuk memberikan konteks yang lebih kaya\n";
        $externalContext .= "- Analisis bagaimana faktor eksternal (cuaca, event, tren) mempengaruhi penjualan\n";
        $externalContext .= "- Berikan rekomendasi strategis berdasarkan analisis internal + eksternal\n";
        $externalContext .= "- Sertakan prediksi dan forecasting jika relevan\n";
        
        return $prompt . $externalContext;
    }
    
    /**
     * Get weather context untuk analisis
     */
    private function getWeatherContext($dashboardData)
    {
        try {
            $apiKey = config('ai.external_data.weather_api_key');
            if (!$apiKey) {
                return null;
            }
            
            // Ambil lokasi outlet dari data (asumsi ada di dashboardData)
            // Untuk sekarang, kita bisa hardcode atau ambil dari config
            $city = 'Jakarta'; // Default, bisa diambil dari outlet data
            
            $url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}&units=metric&lang=id";
            
            $response = Http::timeout(10)->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                $weather = $data['weather'][0]['description'] ?? 'tidak tersedia';
                $temp = $data['main']['temp'] ?? 'N/A';
                $humidity = $data['main']['humidity'] ?? 'N/A';
                
                return "Cuaca saat ini: {$weather}, Suhu: {$temp}°C, Kelembaban: {$humidity}%. " .
                       "Cuaca dapat mempengaruhi penjualan - cuaca buruk biasanya mengurangi kunjungan pelanggan.";
            }
        } catch (\Exception $e) {
            Log::warning('Weather API Error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Get event/holiday context
     */
    private function getEventContext($dashboardData)
    {
        try {
            $dateFrom = $dashboardData['date_from'] ?? Carbon::now()->format('Y-m-d');
            $dateTo = $dashboardData['date_to'] ?? Carbon::now()->format('Y-m-d');
            
            $events = [];
            
            // Check if there are holidays in the period
            $startDate = Carbon::parse($dateFrom);
            $endDate = Carbon::parse($dateTo);
            
            // Indonesian holidays (simplified - bisa diambil dari API)
            $holidays = [
                '01-01' => 'Tahun Baru',
                '17-08' => 'Hari Kemerdekaan',
                '25-12' => 'Natal',
            ];
            
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $dateKey = $currentDate->format('m-d');
                if (isset($holidays[$dateKey])) {
                    $events[] = $currentDate->format('Y-m-d') . ': ' . $holidays[$dateKey];
                }
                $currentDate->addDay();
            }
            
            if (!empty($events)) {
                return "Event/Holiday dalam periode ini:\n" . implode("\n", $events) . 
                       "\n\nEvent dan holiday biasanya meningkatkan penjualan karena lebih banyak orang berlibur atau merayakan.";
            }
            
            return "Tidak ada event/holiday khusus dalam periode ini. " .
                   "Periode normal biasanya menunjukkan pola penjualan yang stabil.";
        } catch (\Exception $e) {
            Log::warning('Event Context Error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Get function declarations untuk function calling
     */
    private function getFunctionDeclarations()
    {
        return [
            [
                'name' => 'get_market_trends',
                'description' => 'Mendapatkan tren pasar dan analisis kompetitor untuk industri F&B',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'industry' => [
                            'type' => 'string',
                            'description' => 'Industri yang dianalisis (default: F&B/Restaurant)'
                        ],
                        'region' => [
                            'type' => 'string',
                            'description' => 'Region/lokasi untuk analisis tren'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'get_advanced_analytics',
                'description' => 'Mendapatkan analisis lanjutan seperti forecasting, prediksi, dan pattern recognition',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => [
                            'type' => 'string',
                            'description' => 'Metric yang dianalisis (revenue, orders, customers, etc)'
                        ],
                        'period' => [
                            'type' => 'string',
                            'description' => 'Periode untuk forecasting (7d, 30d, 90d)'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'get_customer_insights',
                'description' => 'Mendapatkan insight tentang perilaku customer, segmentasi, dan lifetime value',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'segment' => [
                            'type' => 'string',
                            'description' => 'Segment customer yang dianalisis'
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Q&A tentang dashboard data dengan akses database dinamis dan chat history
     */
    public function answerQuestion($question, $dashboardData, $dateFrom = null, $dateTo = null, $chatHistory = [])
    {
        try {
            // Check budget limit jika menggunakan Claude
            if ($this->provider === 'claude') {
                if ($this->budgetService->isBudgetExceeded()) {
                    $currentUsage = $this->budgetService->getCurrentMonthUsage();
                    $budgetLimit = $this->budgetService->getBudgetLimit();
                    return "Maaf, budget AI untuk bulan ini sudah habis. " .
                           "Penggunaan saat ini: Rp " . number_format($currentUsage, 0, ',', '.') . 
                           " dari limit Rp " . number_format($budgetLimit, 0, ',', '.') . ". " .
                           "Silakan gunakan kembali bulan depan atau hubungi administrator untuk meningkatkan limit.";
                }
            }
            
            // Analisis pertanyaan untuk menentukan data tambahan yang diperlukan
            $additionalData = $this->analyzeQuestionAndFetchData($question, $dateFrom, $dateTo);
            
            $prompt = $this->buildQAPrompt($question, $dashboardData, $additionalData, $chatHistory, $dateFrom, $dateTo);
            
            // Enhanced prompt dengan context eksternal dan analisis kompleks
            $enhancedPrompt = $this->enhancePromptWithExternalContext($prompt, array_merge($dashboardData, [
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]));
            
            // Enhanced prompt dengan instruksi analisis kompleks
            $enhancedPrompt .= "\n\n=== INSTRUKSI ANALISIS KOMPLEKS ===\n";
            $enhancedPrompt .= "- Lakukan analisis mendalam dengan mempertimbangkan multiple faktor\n";
            $enhancedPrompt .= "- Gunakan teknik analisis seperti:\n";
            $enhancedPrompt .= "  * Trend Analysis: Identifikasi pola jangka panjang\n";
            $enhancedPrompt .= "  * Comparative Analysis: Bandingkan dengan periode sebelumnya\n";
            $enhancedPrompt .= "  * Correlation Analysis: Cari korelasi antar variabel\n";
            $enhancedPrompt .= "  * Predictive Analysis: Berikan prediksi berdasarkan pola historis\n";
            $enhancedPrompt .= "  * Root Cause Analysis: Identifikasi penyebab mendalam dari tren\n";
            $enhancedPrompt .= "- Sertakan rekomendasi strategis yang actionable\n";
            $enhancedPrompt .= "- Berikan insight yang tidak hanya deskriptif tapi juga preskriptif\n";
            
            // Call API berdasarkan provider
            if ($this->provider === 'claude') {
                // Claude API implementation
                // Try multiple model names in order of preference
                $modelConfig = config('ai.claude.model', 'claude-sonnet-4-5-20250929');
                
                // List of valid Claude models to try (based on Anthropic console)
                $validModels = [
                    'claude-sonnet-4-5-20250929',   // Claude Sonnet 4.5 (latest, recommended)
                    'claude-opus-4-5-20251101',     // Claude Opus 4.5 (most powerful)
                    'claude-haiku-4-5-20251001',     // Claude Haiku 4.5 (fastest, cheapest)
                    'claude-opus-4-1-20250805',     // Claude Opus 4.1
                    'claude-3-5-haiku-20241022',    // Claude 3.5 Haiku (fallback)
                    'claude-3-sonnet-20240229',     // Claude 3 Sonnet (fallback)
                ];
                
                // Use configured model if valid, otherwise use first valid model
                $model = in_array($modelConfig, $validModels) ? $modelConfig : $validModels[0];
                
                $url = 'https://api.anthropic.com/v1/messages';
                
                $requestBody = [
                    'model' => $model,
                    'max_tokens' => config('ai.claude.max_tokens', 8192),
                    'temperature' => config('ai.claude.temperature', 0.7),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $enhancedPrompt
                        ]
                    ]
                ];
                
                // Setup HTTP client untuk Claude dengan timeout lebih lama (180 detik untuk prompt panjang dan analisis kompleks)
                $httpClient = Http::timeout(180)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-api-key' => $this->claudeKey,
                        'anthropic-version' => '2023-06-01',
                    ]);
                
                // Disable SSL verification hanya untuk development
                if (config('app.env') === 'local' || config('app.debug')) {
                    $httpClient = $httpClient->withoutVerifying();
                }
                
                // Try multiple models if first one fails
                // Prioritaskan model yang lebih cepat untuk performa optimal
                // Untuk Q&A, gunakan Haiku dulu (lebih cepat), Sonnet hanya untuk analisis kompleks
                $modelsToTry = [
                    'claude-haiku-4-5-20251001',     // Fastest, cheapest - SELALU coba dulu untuk performa optimal
                ];
                
                // Tambahkan model utama hanya jika berbeda dari Haiku
                if ($model !== 'claude-haiku-4-5-20251001') {
                    $modelsToTry[] = $model;
                }
                
                // Add fallback models (prioritaskan yang cepat)
                $fallbackModels = [
                    'claude-3-5-haiku-20241022',     // Older version fallback (cepat)
                    'claude-3-sonnet-20240229',      // Older version fallback (jika haiku gagal)
                ];
                foreach ($fallbackModels as $fallback) {
                    if (!in_array($fallback, $modelsToTry)) {
                        $modelsToTry[] = $fallback;
                    }
                }
                // Remove duplicates
                $modelsToTry = array_unique($modelsToTry);
                $modelsToTry = array_values($modelsToTry);
                
                $lastError = null;
                $maxRetries = 2; // Retry maksimal 2 kali per model
                
                foreach ($modelsToTry as $tryModel) {
                    $requestBody['model'] = $tryModel;
                    
                    // Retry logic dengan exponential backoff untuk handle timeout
                    $retryCount = 0;
                    $response = null;
                    $requestSuccessful = false;
                    
                    while ($retryCount <= $maxRetries && !$requestSuccessful) {
                        try {
                            $response = $httpClient->post($url, $requestBody);
                            
                            // Jika berhasil, keluar dari retry loop
                            if ($response->successful()) {
                                $requestSuccessful = true;
                                break;
                            }
                            
                            // Jika error tapi bukan timeout, langsung lanjut ke model berikutnya
                            if ($response->status() !== 408 && $response->status() !== 504) {
                                $lastError = $response->json() ?? $response->body();
                                Log::warning('Claude model failed (non-timeout)', [
                                    'model' => $tryModel,
                                    'status' => $response->status(),
                                    'error' => $lastError
                                ]);
                                break; // Keluar dari retry loop, lanjut ke model berikutnya
                            }
                            
                            // Jika timeout, retry dengan delay
                            if ($retryCount < $maxRetries) {
                                $retryCount++;
                                $delay = pow(2, $retryCount) * 2; // Exponential backoff: 4s, 8s
                                Log::warning('Claude API timeout, retrying', [
                                    'model' => $tryModel,
                                    'retry' => $retryCount,
                                    'delay' => $delay
                                ]);
                                sleep($delay);
                                continue;
                            } else {
                                // Sudah retry maksimal, simpan error dan lanjut ke model berikutnya
                                $lastError = $response->json() ?? ['error' => 'Request timeout after ' . ($maxRetries + 1) . ' attempts'];
                                Log::error('Claude API timeout after max retries', [
                                    'model' => $tryModel,
                                    'retries' => $maxRetries
                                ]);
                                break;
                            }
                        } catch (\Exception $e) {
                            // Handle connection timeout atau error lainnya
                            if (strpos($e->getMessage(), 'timeout') !== false || strpos($e->getMessage(), 'timed out') !== false) {
                                if ($retryCount < $maxRetries) {
                                    $retryCount++;
                                    $delay = pow(2, $retryCount) * 2;
                                    Log::warning('Claude API connection timeout, retrying', [
                                        'model' => $tryModel,
                                        'retry' => $retryCount,
                                        'delay' => $delay,
                                        'error' => $e->getMessage()
                                    ]);
                                    sleep($delay);
                                    continue;
                                } else {
                                    // Sudah retry maksimal
                                    $lastError = ['error' => ['message' => 'Connection timeout after ' . ($maxRetries + 1) . ' attempts: ' . $e->getMessage()]];
                                    Log::error('Claude API connection timeout after max retries', [
                                        'model' => $tryModel,
                                        'retries' => $maxRetries,
                                        'error' => $e->getMessage()
                                    ]);
                                    break; // Keluar dari retry loop, lanjut ke model berikutnya
                                }
                            }
                            // Jika bukan timeout error, simpan error dan lanjut ke model berikutnya
                            $lastError = ['error' => ['message' => $e->getMessage()]];
                            Log::error('Claude API error', [
                                'model' => $tryModel,
                                'error' => $e->getMessage()
                            ]);
                            break;
                        }
                    }
                    
                    if ($requestSuccessful && $response && $response->successful()) {
                        $data = $response->json();
                        
                        // Extract text from Claude response
                        if (isset($data['content'][0]['text'])) {
                            $text = $data['content'][0]['text'];
                            
                            // Log usage untuk Claude
                            $inputTokens = $data['usage']['input_tokens'] ?? (int)(strlen($enhancedPrompt) / 4);
                            $outputTokens = $data['usage']['output_tokens'] ?? (int)(strlen($text) / 4);
                            
                            $cost = $this->budgetService->calculateCost('claude', $inputTokens, $outputTokens);
                            $this->budgetService->logUsage('claude', 'qa', $inputTokens, $outputTokens, $cost['total_cost_usd'], $cost['total_cost_rupiah']);
                            
                            return trim($text);
                        } else {
                            Log::error('Claude Q&A Response format unexpected', ['response' => $data]);
                            return "Maaf, format response tidak sesuai. Silakan coba lagi.";
                        }
                    } else {
                        // Request tidak berhasil atau timeout setelah retry
                        if ($response) {
                            $errorBody = $response->json() ?? $response->body();
                            $lastError = $errorBody;
                            Log::warning('Claude model failed, trying next', [
                                'model' => $tryModel,
                                'status' => $response->status(),
                                'error' => $errorBody
                            ]);
                        } else {
                            // Response null karena exception
                            Log::warning('Claude model failed (no response), trying next', [
                                'model' => $tryModel,
                                'error' => $lastError ?? 'Unknown error'
                            ]);
                        }
                        
                        // Continue to next model
                        continue;
                    }
                }
                
                // All models failed
                $errorMsg = is_array($lastError) && isset($lastError['error']['message']) 
                    ? $lastError['error']['message'] 
                    : 'Semua model Claude gagal. Pastikan API key valid dan memiliki akses ke model Claude.';
                
                Log::error('Claude Q&A API Error - All models failed', [
                    'models_tried' => $modelsToTry,
                    'last_error' => $lastError,
                ]);
                
                return "Maaf, terjadi kesalahan saat memanggil Claude API. " . $errorMsg . 
                       " Silakan cek API key di Anthropic console atau hubungi administrator.";
            } elseif ($this->provider === 'gemini') {
                // Gemini API implementation
                $model = config('ai.gemini.model', 'gemini-1.5-pro');
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;
                
                $requestBody = [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $enhancedPrompt
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => config('ai.gemini.max_tokens', 4000),
                    ],
                    'tools' => [
                        [
                            'function_declarations' => $this->getFunctionDeclarations()
                        ]
                    ]
                ];
                
                // Setup HTTP client
                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ]);
                
                // Disable SSL verification hanya untuk development
                if (config('app.env') === 'local' || config('app.debug')) {
                    $httpClient = $httpClient->withoutVerifying();
                }
                
                $response = $httpClient->post($url, $requestBody);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Extract text from response
                    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                        $text = $data['candidates'][0]['content']['parts'][0]['text'];
                        return trim($text);
                    } else {
                        Log::error('AI Q&A Response format unexpected', ['response' => $data]);
                        return "Maaf, format response tidak sesuai. Silakan coba lagi.";
                    }
                } else {
                    $errorBody = $response->json() ?? $response->body();
                    Log::error('AI Q&A API Error', [
                        'status' => $response->status(),
                        'body' => $errorBody,
                    ]);
                    $errorMsg = is_array($errorBody) && isset($errorBody['error']['message']) 
                        ? $errorBody['error']['message'] 
                        : 'API Error: ' . $response->status();
                    return "Maaf, terjadi kesalahan saat memanggil AI. " . $errorMsg;
                }
            }
        } catch (\Exception $e) {
            Log::error('AI Q&A Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return "Maaf, tidak dapat menjawab pertanyaan saat ini. Silakan coba lagi nanti.";
        }
    }
    
    /**
     * Analisis pertanyaan dan ambil data tambahan dari database jika diperlukan
     */
    private function analyzeQuestionAndFetchData($question, $dateFrom, $dateTo)
    {
        $additionalData = [];
        $questionLower = strtolower($question);
        
        // Jika pertanyaan tentang perbandingan periode
        if (preg_match('/(banding|compare|perbandingan|bulan lalu|minggu lalu|hari lalu|sebelumnya)/i', $question)) {
            if ($dateFrom && $dateTo) {
                // Hitung periode sebelumnya
                $daysDiff = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo));
                $prevDateFrom = Carbon::parse($dateFrom)->subDays($daysDiff + 1)->format('Y-m-d');
                $prevDateTo = Carbon::parse($dateFrom)->subDay()->format('Y-m-d');
                
                $additionalData['previous_period_comparison'] = $this->dbHelper->getRevenueComparison(
                    $dateFrom, $dateTo, $prevDateFrom, $prevDateTo
                );
            }
        }
        
        // Jika pertanyaan tentang item spesifik
        if (preg_match('/item\s+([^?]+)|menu\s+([^?]+)|produk\s+([^?]+)/i', $question, $matches)) {
            $itemName = trim($matches[1] ?? $matches[2] ?? $matches[3] ?? '');
            if ($itemName && $dateFrom && $dateTo) {
                $additionalData['item_detail'] = $this->dbHelper->getItemSalesDetail(
                    $itemName, $dateFrom, $dateTo
                );
            }
        }
        
        // Jika pertanyaan tentang outlet
        if (preg_match('/outlet\s+([^?]+)|restoran\s+([^?]+)/i', $question, $matches)) {
            $outletName = trim($matches[1] ?? $matches[2] ?? '');
            if ($dateFrom && $dateTo) {
                $additionalData['outlet_performance'] = $this->dbHelper->getOutletPerformance(
                    $dateFrom, $dateTo, 20
                );
            }
        }
        
        // Jika pertanyaan tentang jam sibuk atau hourly
        if (preg_match('/(jam|hour|pukul|waktu|peak|sibuk)/i', $question)) {
            if ($dateFrom && $dateTo) {
                $additionalData['hourly_sales'] = $this->dbHelper->getHourlySales($dateFrom, $dateTo);
            }
        }
        
        // Jika pertanyaan tentang payment method
        if (preg_match('/(payment|pembayaran|metode pembayaran|cash|card|transfer)/i', $question)) {
            if ($dateFrom && $dateTo) {
                $additionalData['payment_methods'] = $this->dbHelper->getPaymentMethods($dateFrom, $dateTo);
            }
        }
        
        // Jika pertanyaan tentang top items
        if (preg_match('/(top|terlaris|paling laris|best|terbaik|ranking)/i', $question)) {
            if ($dateFrom && $dateTo) {
                $additionalData['top_items'] = $this->dbHelper->getTopItems($dateFrom, $dateTo, 20);
            }
        }
        
        return $additionalData;
    }
    
    /**
     * Build prompt untuk Q&A dengan chat history
     */
    private function buildQAPrompt($question, $data, $additionalData = [], $chatHistory = [], $dateFrom = null, $dateTo = null)
    {
        // Convert object to array if needed (recursive)
        $data = $this->toArray($data);
        
        $overview = $data['overview'] ?? [];
        $topItems = $data['topItems'] ?? [];
        $hourlySales = $data['hourlySales'] ?? [];
        $paymentMethods = $data['paymentMethods'] ?? [];
        $salesTrend = $data['salesTrend'] ?? [];
        $recentOrders = $data['recentOrders'] ?? [];
        $peakHours = $data['peakHours'] ?? [];
        
        $prompt = "Anda adalah asisten AI yang membantu menganalisa data dashboard penjualan outlet.\n\n";
        $prompt .= "=== PENTING: AKSES DATA ===\n";
        $prompt .= "Database memiliki data penjualan LENGKAP untuk SETAHUN TERAKHIR atau lebih.\n";
        $prompt .= "Jika user meminta analisis untuk periode yang lebih panjang (3 bulan, 6 bulan, setahun), ";
        $prompt .= "data tersebut TERSEDIA di database dan bisa diakses dengan mengubah filter date_from dan date_to.\n";
        $prompt .= "JANGAN katakan bahwa data tidak tersedia - data SETAHUN TERSEDIA di database.\n";
        $prompt .= "Jika user meminta analisis periode panjang, sarankan untuk menggunakan filter tanggal yang lebih panjang.\n\n";
        
        // Tambahkan chat history sebagai context jika ada (dibatasi untuk performa)
        if (!empty($chatHistory) && is_array($chatHistory)) {
            $prompt .= "=== CONTEXT: PERCAKAPAN SEBELUMNYA ===\n";
            $prompt .= "Berikut adalah percakapan sebelumnya untuk memberikan konteks (maksimal 3 percakapan terakhir):\n\n";
            // Batasi hanya 3 chat terakhir untuk performa
            $recentChats = array_slice($chatHistory, -3);
            foreach ($recentChats as $index => $chat) {
                $chatNum = $index + 1;
                // Batasi panjang answer yang dikirim (maksimal 500 karakter per answer)
                $answer = $chat['answer'] ?? 'N/A';
                if (strlen($answer) > 500) {
                    $answer = substr($answer, 0, 500) . '... (truncated)';
                }
                $prompt .= "Percakapan #{$chatNum}:\n";
                $prompt .= "User: " . ($chat['question'] ?? 'N/A') . "\n";
                $prompt .= "AI: " . $answer . "\n\n";
            }
            $prompt .= "Gunakan context percakapan sebelumnya untuk memberikan jawaban yang lebih relevan dan konsisten.\n";
            $prompt .= "Jika pertanyaan user mengacu pada percakapan sebelumnya, gunakan informasi dari percakapan tersebut.\n\n";
        }
        
        $prompt .= "=== PERTANYAAN SAAT INI ===\n";
        $prompt .= "Pertanyaan user: {$question}\n\n";
        $prompt .= "=== DATA DASHBOARD YANG TERSEDIA ===\n";
        $prompt .= "Periode data saat ini: {$dateFrom} sampai {$dateTo}\n";
        $prompt .= "CATATAN: Data di database tersedia untuk SETAHUN TERAKHIR atau lebih. ";
        $prompt .= "Jika diperlukan analisis periode lebih panjang, data bisa diakses dengan mengubah filter tanggal.\n\n";
        
        $prompt .= "=== OVERVIEW (PERIODE SAAT INI) ===\n";
        $prompt .= "Total Orders: " . ($overview['total_orders'] ?? 0) . "\n";
        $prompt .= "Total Revenue: Rp " . number_format($overview['total_revenue'] ?? 0, 0, ',', '.') . "\n";
        $prompt .= "Average Order Value: Rp " . number_format($overview['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
        $prompt .= "Total Customers: " . ($overview['total_customers'] ?? 0) . "\n";
        
        if (isset($overview['orders_growth'])) {
            $prompt .= "Growth Orders: " . number_format($overview['orders_growth'], 1) . "%\n";
        }
        if (isset($overview['revenue_growth'])) {
            $prompt .= "Growth Revenue: " . number_format($overview['revenue_growth'], 1) . "%\n";
        }
        
        // Tambahkan data periode sebelumnya jika ada
        if (isset($overview['previous_period'])) {
            $prev = $overview['previous_period'];
            $prompt .= "\n=== OVERVIEW (PERIODE SEBELUMNYA) ===\n";
            $prompt .= "Periode: " . ($prev['date_from'] ?? 'N/A') . " sampai " . ($prev['date_to'] ?? 'N/A') . "\n";
            $prompt .= "Total Orders: " . ($prev['total_orders'] ?? 0) . "\n";
            $prompt .= "Total Revenue: Rp " . number_format($prev['total_revenue'] ?? 0, 0, ',', '.') . "\n";
        }
        
        if (!empty($topItems)) {
            $prompt .= "\n=== TOP ITEMS (Top 10) ===\n";
            foreach (array_slice($topItems, 0, 10) as $index => $item) {
                $item = $this->toArray($item);
                $prompt .= ($index + 1) . ". " . ($item['item_name'] ?? 'N/A') . " - Qty: " . ($item['total_qty'] ?? 0) . ", Revenue: Rp " . number_format($item['total_revenue'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        if (!empty($hourlySales)) {
            $prompt .= "\n=== HOURLY SALES ===\n";
            foreach (array_slice($hourlySales, 0, 10) as $hour) {
                $hour = $this->toArray($hour);
                $prompt .= "Jam " . ($hour['hour'] ?? 'N/A') . ":00 - Orders: " . ($hour['orders'] ?? 0) . ", Revenue: Rp " . number_format($hour['revenue'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        if (!empty($paymentMethods)) {
            $prompt .= "\n=== PAYMENT METHODS ===\n";
            foreach (array_slice($paymentMethods, 0, 5) as $payment) {
                $payment = $this->toArray($payment);
                $prompt .= ($payment['payment_code'] ?? 'N/A') . " - " . ($payment['transaction_count'] ?? 0) . " transaksi, Total: Rp " . number_format($payment['total_amount'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        if (!empty($peakHours)) {
            $prompt .= "\n=== PEAK HOURS (Top 5) ===\n";
            foreach (array_slice($peakHours, 0, 5) as $index => $peak) {
                $peak = $this->toArray($peak);
                $prompt .= ($index + 1) . ". Jam " . ($peak['hour'] ?? 'N/A') . ":00 - Orders: " . ($peak['orders'] ?? 0) . ", Revenue: Rp " . number_format($peak['revenue'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        // Sales trend per hari (DAILY SALES DATA) - Dibatasi untuk performa
        if (!empty($salesTrend)) {
            $prompt .= "\n=== SALES TREND PER HARI (DAILY SALES DATA) ===\n";
            $prompt .= "Data penjualan harian (dibatasi untuk performa, jika perlu detail lebih bisa request data spesifik):\n";
            
            // Batasi data yang dikirim: maksimal 30 hari atau summary jika lebih dari 30 hari
            $salesTrendCount = count($salesTrend);
            if ($salesTrendCount > 30) {
                // Jika lebih dari 30 hari, kirim summary + beberapa hari terakhir
                $prompt .= "Total hari: {$salesTrendCount} hari\n";
                
                // Summary statistik
                $totalRevenue = array_sum(array_column($salesTrend, 'revenue'));
                $totalOrders = array_sum(array_column($salesTrend, 'orders'));
                $avgDailyRevenue = $totalRevenue / $salesTrendCount;
                $prompt .= "Summary: Total Revenue: Rp " . number_format($totalRevenue, 0, ',', '.') . ", Total Orders: {$totalOrders}, Average Daily Revenue: Rp " . number_format($avgDailyRevenue, 0, ',', '.') . "\n\n";
                
                // Tampilkan 7 hari terakhir
                $prompt .= "7 Hari Terakhir:\n";
                foreach (array_slice($salesTrend, -7) as $day) {
                    $day = $this->toArray($day);
                    $date = $day['period'] ?? 'N/A';
                    $orders = $day['orders'] ?? 0;
                    $revenue = $day['revenue'] ?? 0;
                    $prompt .= "Tanggal: {$date} - Orders: {$orders}, Revenue: Rp " . number_format($revenue, 0, ',', '.') . "\n";
                }
            } else {
                // Jika <= 30 hari, kirim semua
                foreach ($salesTrend as $day) {
                    $day = $this->toArray($day);
                    $date = $day['period'] ?? 'N/A';
                    $orders = $day['orders'] ?? 0;
                    $revenue = $day['revenue'] ?? 0;
                    $customers = $day['customers'] ?? 0;
                    $avgOrderValue = $day['avg_order_value'] ?? 0;
                    $prompt .= "Tanggal: {$date} - Orders: {$orders}, Revenue: Rp " . number_format($revenue, 0, ',', '.') . ", Customers: {$customers}, Avg Order Value: Rp " . number_format($avgOrderValue, 0, ',', '.') . "\n";
                }
            }
            $prompt .= "\nCATATAN: Gunakan data ini untuk menjawab pertanyaan tentang perbandingan revenue antar tanggal, trend harian, atau analisis spesifik per tanggal.\n";
        }
        
        // Revenue per outlet - Dibatasi untuk performa
        $revenuePerOutlet = $data['revenuePerOutlet'] ?? [];
        if (!empty($revenuePerOutlet)) {
            $prompt .= "\n=== REVENUE PER OUTLET ===\n";
            $prompt .= "Data penjualan per outlet (summary untuk performa lebih cepat):\n";
            foreach ($revenuePerOutlet as $regionName => $regionData) {
                $regionData = $this->toArray($regionData);
                $prompt .= "\nRegion: {$regionName} (Code: " . ($regionData['region_code'] ?? 'N/A') . ")\n";
                $prompt .= "  Total Revenue: Rp " . number_format($regionData['total_revenue'] ?? 0, 0, ',', '.') . "\n";
                $prompt .= "  Total Orders: " . ($regionData['total_orders'] ?? 0) . "\n";
                $prompt .= "  Total Customers: " . ($regionData['total_pax'] ?? 0) . "\n";
                if (isset($regionData['outlets']) && is_array($regionData['outlets'])) {
                    // Batasi outlet yang ditampilkan: top 5 per region untuk performa
                    $outlets = array_slice($regionData['outlets'], 0, 5);
                    foreach ($outlets as $outlet) {
                        $outlet = $this->toArray($outlet);
                        $prompt .= "  - Outlet: " . ($outlet['outlet_name'] ?? 'N/A') . " (Code: " . ($outlet['outlet_code'] ?? 'N/A') . ")\n";
                        $prompt .= "    Revenue: Rp " . number_format($outlet['total_revenue'] ?? 0, 0, ',', '.') . ", Orders: " . ($outlet['order_count'] ?? 0) . ", Customers: " . ($outlet['total_pax'] ?? 0) . ", Avg Order Value: Rp " . number_format($outlet['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
                    }
                    if (count($regionData['outlets']) > 5) {
                        $prompt .= "  ... dan " . (count($regionData['outlets']) - 5) . " outlet lainnya\n";
                    }
                }
            }
            $prompt .= "\nCATATAN: Gunakan data ini untuk menjawab pertanyaan tentang performa outlet, perbandingan antar outlet, atau analisis spesifik per outlet.\n";
        }
        
        // Overview metrics tambahan
        if (isset($overview['avg_pax_per_order'])) {
            $prompt .= "\n=== METRICS TAMBAHAN ===\n";
            $prompt .= "Average Pax per Order: " . number_format($overview['avg_pax_per_order'], 2) . "\n";
            $prompt .= "Average Check (Revenue per Customer): Rp " . number_format($overview['avg_check'] ?? 0, 0, ',', '.') . "\n";
            $prompt .= "Total Discount: Rp " . number_format($overview['total_discount'] ?? 0, 0, ',', '.') . "\n";
            $prompt .= "Total Service Charge: Rp " . number_format($overview['total_service_charge'] ?? 0, 0, ',', '.') . "\n";
            $prompt .= "Total Commission Fee: Rp " . number_format($overview['total_commission_fee'] ?? 0, 0, ',', '.') . "\n";
            $prompt .= "Total Manual Discount: Rp " . number_format($overview['total_manual_discount'] ?? 0, 0, ',', '.') . "\n";
        }
        
        // Average Order Value Detail
        $avgOrderValue = $data['avgOrderValue'] ?? null;
        if ($avgOrderValue) {
            $avgOrderValue = $this->toArray($avgOrderValue);
            $prompt .= "\n=== AVERAGE ORDER VALUE DETAIL ===\n";
            $prompt .= "Average: Rp " . number_format($avgOrderValue['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
            $prompt .= "Minimum: Rp " . number_format($avgOrderValue['min_order_value'] ?? 0, 0, ',', '.') . "\n";
            $prompt .= "Maximum: Rp " . number_format($avgOrderValue['max_order_value'] ?? 0, 0, ',', '.') . "\n";
        }
        
        // Promo Usage
        $promoUsage = $data['promoUsage'] ?? [];
        if (!empty($promoUsage)) {
            $promoUsage = $this->toArray($promoUsage);
            $prompt .= "\n=== PROMO USAGE ===\n";
            $prompt .= "Orders with Promo: " . ($promoUsage['orders_with_promo'] ?? 0) . "\n";
            $prompt .= "Total Promo Usage: " . ($promoUsage['total_promo_usage'] ?? 0) . "\n";
            $prompt .= "Promo Usage Percentage: " . number_format($promoUsage['promo_usage_percentage'] ?? 0, 2) . "%\n";
        }
        
        // Bank Promo Discount
        $bankPromoDiscount = $data['bankPromoDiscount'] ?? [];
        if (!empty($bankPromoDiscount)) {
            $bankPromoDiscount = $this->toArray($bankPromoDiscount);
            $prompt .= "\n=== BANK PROMO DISCOUNT ===\n";
            $prompt .= "Orders with Bank Promo: " . ($bankPromoDiscount['orders_with_bank_promo'] ?? 0) . "\n";
            $prompt .= "Total Bank Discount Amount: Rp " . number_format($bankPromoDiscount['total_bank_discount_amount'] ?? 0, 0, ',', '.') . "\n";
            $prompt .= "Average Bank Discount Amount: Rp " . number_format($bankPromoDiscount['avg_bank_discount_amount'] ?? 0, 0, ',', '.') . "\n";
            $prompt .= "Bank Promo Percentage: " . number_format($bankPromoDiscount['bank_promo_percentage'] ?? 0, 2) . "%\n";
        }
        
        // Lunch/Dinner Orders
        $lunchDinnerOrders = $data['lunchDinnerOrders'] ?? [];
        if (!empty($lunchDinnerOrders)) {
            $lunchDinnerOrders = $this->toArray($lunchDinnerOrders);
            $prompt .= "\n=== LUNCH vs DINNER ORDERS ===\n";
            if (isset($lunchDinnerOrders['lunch'])) {
                $lunch = $lunchDinnerOrders['lunch'];
                $prompt .= "LUNCH:\n";
                $prompt .= "  Orders: " . ($lunch['order_count'] ?? 0) . ", Revenue: Rp " . number_format($lunch['total_revenue'] ?? 0, 0, ',', '.') . ", Customers: " . ($lunch['total_pax'] ?? 0) . ", Avg Order Value: Rp " . number_format($lunch['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
            }
            if (isset($lunchDinnerOrders['dinner'])) {
                $dinner = $lunchDinnerOrders['dinner'];
                $prompt .= "DINNER:\n";
                $prompt .= "  Orders: " . ($dinner['order_count'] ?? 0) . ", Revenue: Rp " . number_format($dinner['total_revenue'] ?? 0, 0, ',', '.') . ", Customers: " . ($dinner['total_pax'] ?? 0) . ", Avg Order Value: Rp " . number_format($dinner['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        // Weekday/Weekend Revenue
        $weekdayWeekendRevenue = $data['weekdayWeekendRevenue'] ?? [];
        if (!empty($weekdayWeekendRevenue)) {
            $weekdayWeekendRevenue = $this->toArray($weekdayWeekendRevenue);
            $prompt .= "\n=== WEEKDAY vs WEEKEND REVENUE ===\n";
            if (isset($weekdayWeekendRevenue['weekday'])) {
                $weekday = $weekdayWeekendRevenue['weekday'];
                $prompt .= "WEEKDAY:\n";
                $prompt .= "  Orders: " . ($weekday['order_count'] ?? 0) . ", Revenue: Rp " . number_format($weekday['total_revenue'] ?? 0, 0, ',', '.') . ", Customers: " . ($weekday['total_pax'] ?? 0) . ", Avg Order Value: Rp " . number_format($weekday['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
            }
            if (isset($weekdayWeekendRevenue['weekend'])) {
                $weekend = $weekdayWeekendRevenue['weekend'];
                $prompt .= "WEEKEND:\n";
                $prompt .= "  Orders: " . ($weekend['order_count'] ?? 0) . ", Revenue: Rp " . number_format($weekend['total_revenue'] ?? 0, 0, ',', '.') . ", Customers: " . ($weekend['total_pax'] ?? 0) . ", Avg Order Value: Rp " . number_format($weekend['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
            }
        }
        
        // Revenue per Outlet - Lunch/Dinner Breakdown
        $revenuePerOutletLunchDinner = $data['revenuePerOutletLunchDinner'] ?? [];
        if (!empty($revenuePerOutletLunchDinner)) {
            $prompt .= "\n=== REVENUE PER OUTLET - LUNCH/DINNER BREAKDOWN ===\n";
            foreach ($revenuePerOutletLunchDinner as $regionName => $regionData) {
                $regionData = $this->toArray($regionData);
                $prompt .= "\nRegion: {$regionName}\n";
                if (isset($regionData['lunch'])) {
                    $prompt .= "  LUNCH - Revenue: Rp " . number_format($regionData['lunch']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders: " . ($regionData['lunch']['total_orders'] ?? 0) . ", Customers: " . ($regionData['lunch']['total_pax'] ?? 0) . "\n";
                }
                if (isset($regionData['dinner'])) {
                    $prompt .= "  DINNER - Revenue: Rp " . number_format($regionData['dinner']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders: " . ($regionData['dinner']['total_orders'] ?? 0) . ", Customers: " . ($regionData['dinner']['total_pax'] ?? 0) . "\n";
                }
                if (isset($regionData['outlets']) && is_array($regionData['outlets'])) {
                    foreach (array_slice($regionData['outlets'], 0, 5) as $outlet) {
                        $outlet = $this->toArray($outlet);
                        $prompt .= "  - Outlet: " . ($outlet['outlet_name'] ?? 'N/A') . "\n";
                        if (isset($outlet['lunch'])) {
                            $prompt .= "    Lunch: Revenue Rp " . number_format($outlet['lunch']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders " . ($outlet['lunch']['order_count'] ?? 0) . "\n";
                        }
                        if (isset($outlet['dinner'])) {
                            $prompt .= "    Dinner: Revenue Rp " . number_format($outlet['dinner']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders " . ($outlet['dinner']['order_count'] ?? 0) . "\n";
                        }
                    }
                }
            }
        }
        
        // Revenue per Outlet - Weekday/Weekend Breakdown
        $revenuePerOutletWeekendWeekday = $data['revenuePerOutletWeekendWeekday'] ?? [];
        if (!empty($revenuePerOutletWeekendWeekday)) {
            $prompt .= "\n=== REVENUE PER OUTLET - WEEKDAY/WEEKEND BREAKDOWN ===\n";
            foreach ($revenuePerOutletWeekendWeekday as $regionName => $regionData) {
                $regionData = $this->toArray($regionData);
                $prompt .= "\nRegion: {$regionName}\n";
                if (isset($regionData['weekday'])) {
                    $prompt .= "  WEEKDAY - Revenue: Rp " . number_format($regionData['weekday']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders: " . ($regionData['weekday']['total_orders'] ?? 0) . ", Customers: " . ($regionData['weekday']['total_pax'] ?? 0) . "\n";
                }
                if (isset($regionData['weekend'])) {
                    $prompt .= "  WEEKEND - Revenue: Rp " . number_format($regionData['weekend']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders: " . ($regionData['weekend']['total_orders'] ?? 0) . ", Customers: " . ($regionData['weekend']['total_pax'] ?? 0) . "\n";
                }
                if (isset($regionData['outlets']) && is_array($regionData['outlets'])) {
                    foreach (array_slice($regionData['outlets'], 0, 5) as $outlet) {
                        $outlet = $this->toArray($outlet);
                        $prompt .= "  - Outlet: " . ($outlet['outlet_name'] ?? 'N/A') . "\n";
                        if (isset($outlet['weekday'])) {
                            $prompt .= "    Weekday: Revenue Rp " . number_format($outlet['weekday']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders " . ($outlet['weekday']['order_count'] ?? 0) . "\n";
                        }
                        if (isset($outlet['weekend'])) {
                            $prompt .= "    Weekend: Revenue Rp " . number_format($outlet['weekend']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders " . ($outlet['weekend']['order_count'] ?? 0) . "\n";
                        }
                    }
                }
            }
        }
        
        // Revenue per Region
        $revenuePerRegion = $data['revenuePerRegion'] ?? [];
        if (!empty($revenuePerRegion)) {
            $revenuePerRegion = $this->toArray($revenuePerRegion);
            $prompt .= "\n=== REVENUE PER REGION ===\n";
            if (isset($revenuePerRegion['total_revenue']) && is_array($revenuePerRegion['total_revenue'])) {
                $prompt .= "Total Revenue per Region:\n";
                foreach ($revenuePerRegion['total_revenue'] as $region) {
                    $region = $this->toArray($region);
                    $prompt .= "  - " . ($region['region_name'] ?? 'N/A') . ": Revenue Rp " . number_format($region['total_revenue'] ?? 0, 0, ',', '.') . ", Orders: " . ($region['total_orders'] ?? 0) . ", Customers: " . ($region['total_pax'] ?? 0) . "\n";
                }
            }
            if (isset($revenuePerRegion['lunch_dinner']) && is_array($revenuePerRegion['lunch_dinner'])) {
                $prompt .= "\nLunch/Dinner per Region:\n";
                foreach ($revenuePerRegion['lunch_dinner'] as $regionName => $regionData) {
                    $regionData = $this->toArray($regionData);
                    $prompt .= "  - {$regionName}:\n";
                    if (isset($regionData['lunch'])) {
                        $prompt .= "    Lunch: Revenue Rp " . number_format($regionData['lunch']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders " . ($regionData['lunch']['order_count'] ?? 0) . "\n";
                    }
                    if (isset($regionData['dinner'])) {
                        $prompt .= "    Dinner: Revenue Rp " . number_format($regionData['dinner']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders " . ($regionData['dinner']['order_count'] ?? 0) . "\n";
                    }
                }
            }
            if (isset($revenuePerRegion['weekday_weekend']) && is_array($revenuePerRegion['weekday_weekend'])) {
                $prompt .= "\nWeekday/Weekend per Region:\n";
                foreach ($revenuePerRegion['weekday_weekend'] as $regionName => $regionData) {
                    $regionData = $this->toArray($regionData);
                    $prompt .= "  - {$regionName}:\n";
                    if (isset($regionData['weekday'])) {
                        $prompt .= "    Weekday: Revenue Rp " . number_format($regionData['weekday']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders " . ($regionData['weekday']['order_count'] ?? 0) . "\n";
                    }
                    if (isset($regionData['weekend'])) {
                        $prompt .= "    Weekend: Revenue Rp " . number_format($regionData['weekend']['total_revenue'] ?? 0, 0, ',', '.') . ", Orders " . ($regionData['weekend']['order_count'] ?? 0) . "\n";
                    }
                }
            }
        }
        
        // Payment Methods Detail
        if (!empty($paymentMethods)) {
            $prompt .= "\n=== PAYMENT METHODS DETAIL ===\n";
            foreach (array_slice($paymentMethods, 0, 10) as $payment) {
                $payment = $this->toArray($payment);
                $prompt .= ($payment['payment_code'] ?? 'N/A') . ":\n";
                $prompt .= "  Total Transactions: " . ($payment['transaction_count'] ?? 0) . "\n";
                $prompt .= "  Total Amount: Rp " . number_format($payment['total_amount'] ?? 0, 0, ',', '.') . "\n";
                $prompt .= "  Average Amount: Rp " . number_format($payment['avg_amount'] ?? 0, 0, ',', '.') . "\n";
                if (isset($payment['details']) && is_array($payment['details']) && !empty($payment['details'])) {
                    $prompt .= "  Breakdown by Type:\n";
                    foreach (array_slice($payment['details'], 0, 3) as $detail) {
                        $detail = $this->toArray($detail);
                        $prompt .= "    - " . ($detail['payment_type'] ?? 'N/A') . ": " . ($detail['transaction_count'] ?? 0) . " transaksi, Total Rp " . number_format($detail['total_amount'] ?? 0, 0, ',', '.') . "\n";
                    }
                }
            }
        }
        
        // Tambahkan data tambahan jika ada
        if (!empty($additionalData)) {
            $prompt .= "\n=== DATA TAMBAHAN DARI DATABASE ===\n";
            
            if (isset($additionalData['previous_period_comparison'])) {
                $prompt .= "\n--- PERBANDINGAN PERIODE ---\n";
                foreach ($additionalData['previous_period_comparison'] as $comp) {
                    $comp = $this->toArray($comp);
                    $prompt .= "Periode: " . ($comp['period'] ?? 'N/A') . "\n";
                    $prompt .= "  - Total Orders: " . ($comp['total_orders'] ?? 0) . "\n";
                    $prompt .= "  - Total Revenue: Rp " . number_format($comp['total_revenue'] ?? 0, 0, ',', '.') . "\n";
                    $prompt .= "  - Total Customers: " . ($comp['total_customers'] ?? 0) . "\n";
                    $prompt .= "  - Avg Order Value: Rp " . number_format($comp['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
                }
            }
            
            if (isset($additionalData['item_detail'])) {
                $prompt .= "\n--- DETAIL ITEM ---\n";
                foreach (array_slice($additionalData['item_detail'], 0, 10) as $item) {
                    $item = $this->toArray($item);
                    $prompt .= "Tanggal: " . ($item['date'] ?? 'N/A') . ", Outlet: " . ($item['outlet_name'] ?? 'N/A') . "\n";
                    $prompt .= "  - Qty: " . ($item['total_qty'] ?? 0) . ", Revenue: Rp " . number_format($item['total_revenue'] ?? 0, 0, ',', '.') . "\n";
                }
            }
            
            if (isset($additionalData['outlet_performance'])) {
                $prompt .= "\n--- PERFORMANCE OUTLET ---\n";
                foreach (array_slice($additionalData['outlet_performance'], 0, 10) as $outlet) {
                    $outlet = $this->toArray($outlet);
                    $prompt .= ($outlet['outlet_name'] ?? 'N/A') . " (" . ($outlet['region_name'] ?? 'N/A') . ")\n";
                    $prompt .= "  - Orders: " . ($outlet['total_orders'] ?? 0) . ", Revenue: Rp " . number_format($outlet['total_revenue'] ?? 0, 0, ',', '.') . "\n";
                }
            }
            
            if (isset($additionalData['hourly_sales'])) {
                $prompt .= "\n--- PENJUALAN PER JAM ---\n";
                foreach ($additionalData['hourly_sales'] as $hour) {
                    $hour = $this->toArray($hour);
                    $prompt .= "Jam " . ($hour['hour'] ?? 'N/A') . ":00 - Orders: " . ($hour['orders'] ?? 0) . ", Revenue: Rp " . number_format($hour['revenue'] ?? 0, 0, ',', '.') . "\n";
                }
            }
            
            if (isset($additionalData['payment_methods'])) {
                $prompt .= "\n--- METODE PEMBAYARAN ---\n";
                foreach (array_slice($additionalData['payment_methods'], 0, 10) as $payment) {
                    $payment = $this->toArray($payment);
                    $prompt .= ($payment['payment_code'] ?? 'N/A') . " (" . ($payment['payment_type'] ?? 'N/A') . ")\n";
                    $prompt .= "  - Transaksi: " . ($payment['transaction_count'] ?? 0) . ", Total: Rp " . number_format($payment['total_amount'] ?? 0, 0, ',', '.') . "\n";
                }
            }
            
            if (isset($additionalData['top_items'])) {
                $prompt .= "\n--- TOP ITEMS (DARI DATABASE) ---\n";
                foreach (array_slice($additionalData['top_items'], 0, 15) as $index => $item) {
                    $item = $this->toArray($item);
                    $prompt .= ($index + 1) . ". " . ($item['item_name'] ?? 'N/A') . "\n";
                    $prompt .= "   - Qty: " . ($item['total_qty'] ?? 0) . ", Revenue: Rp " . number_format($item['total_revenue'] ?? 0, 0, ',', '.') . "\n";
                }
            }
        }
        
        $prompt .= "\n=== INSTRUKSI ANALISIS YANG SANGAT PINTAR ===\n";
        $prompt .= "Anda adalah AI ANALYST yang sangat berpengalaman dalam analisis bisnis F&B. Jawab pertanyaan user dengan:\n\n";
        
        $prompt .= "1. GUNAKAN SEMUA DATA YANG TERSEDIA:\n";
        $prompt .= "   - DATA PER HARI (Sales Trend): Gunakan untuk perbandingan tanggal, trend harian, identifikasi anomali, pola musiman\n";
        $prompt .= "   - DATA PER OUTLET: Gunakan untuk perbandingan performa outlet, identifikasi outlet terbaik/terburuk, analisis regional\n";
        $prompt .= "   - DATA LUNCH/DINNER: Gunakan untuk analisis pola makan, perbandingan meal period, strategi operasional\n";
        $prompt .= "   - DATA WEEKDAY/WEEKEND: Gunakan untuk analisis pola mingguan, perbandingan hari kerja vs libur\n";
        $prompt .= "   - DATA PAYMENT METHODS: Gunakan untuk analisis preferensi pembayaran, strategi payment\n";
        $prompt .= "   - DATA PROMO: Gunakan untuk analisis efektivitas promo, impact promo terhadap revenue\n";
        $prompt .= "   - DATA HOURLY: Gunakan untuk analisis peak hours, staffing optimization, operational efficiency\n";
        $prompt .= "   - DATA TOP ITEMS: Gunakan untuk analisis menu performance, best sellers, revenue contribution\n\n";
        
        $prompt .= "2. TEKNIK ANALISIS YANG HARUS DIGUNAKAN:\n";
        $prompt .= "   a. TREND ANALYSIS: Identifikasi pola jangka panjang, trend naik/turun, seasonality\n";
        $prompt .= "   b. COMPARATIVE ANALYSIS: Bandingkan dengan periode sebelumnya, outlet lain, region lain, meal period, weekday/weekend\n";
        $prompt .= "   c. CORRELATION ANALYSIS: Cari korelasi antar variabel (contoh: promo vs revenue, lunch vs dinner, weekday vs weekend)\n";
        $prompt .= "   d. ROOT CAUSE ANALYSIS: Identifikasi penyebab mendalam dari tren atau anomali (jangan hanya deskriptif)\n";
        $prompt .= "   e. PREDICTIVE ANALYSIS: Berikan prediksi berdasarkan pola historis jika relevan\n";
        $prompt .= "   f. BENCHMARKING: Bandingkan performa dengan rata-rata, best performer, worst performer\n";
        $prompt .= "   g. SEGMENTATION ANALYSIS: Analisis berdasarkan segmentasi (outlet, region, meal period, day type)\n\n";
        
        $prompt .= "3. KEDALAMAN ANALISIS:\n";
        $prompt .= "   - JANGAN hanya memberikan angka/data mentah - BERIKAN INTERPRETASI dan INSIGHT\n";
        $prompt .= "   - Identifikasi ANOMALI dan jelaskan kemungkinan penyebabnya\n";
        $prompt .= "   - Berikan CONTEXT - mengapa angka ini penting? Apa artinya untuk bisnis?\n";
        $prompt .= "   - Lakukan MULTI-DIMENSIONAL ANALYSIS - jangan hanya lihat satu aspek\n";
        $prompt .= "   - Gunakan PERBANDINGAN untuk memberikan perspektif (vs periode lalu, vs outlet lain, vs rata-rata)\n\n";
        
        $prompt .= "4. REKOMENDASI YANG ACTIONABLE:\n";
        $prompt .= "   - Berikan rekomendasi yang SPESIFIK dan BISA DITINDAKLANJUTI\n";
        $prompt .= "   - Prioritaskan rekomendasi berdasarkan IMPACT dan FEASIBILITY\n";
        $prompt .= "   - Sertakan TARGET atau METRIC untuk mengukur keberhasilan rekomendasi\n\n";
        
        $prompt .= "5. FORMAT JAWABAN:\n";
        $prompt .= "   - Bahasa Indonesia yang mudah dipahami\n";
        $prompt .= "   - Struktur: Executive Summary → Detailed Analysis → Key Insights → Recommendations\n";
        $prompt .= "   - Sertakan ANGKA SPESIFIK dari data yang tersedia (jangan generalisasi)\n";
        $prompt .= "   - Gunakan BULLET POINTS untuk key insights dan recommendations\n";
        $prompt .= "   - Jika ada perbandingan, tampilkan dalam format yang jelas (misalnya: Tanggal 26 revenue Rp X vs Tanggal 25 revenue Rp Y, turun Z%)\n\n";
        
        $prompt .= "6. KETEPATAN DATA:\n";
        $prompt .= "   - GUNAKAN DATA AKTUAL dari database yang sudah disediakan\n";
        $prompt .= "   - JANGAN menghitung estimasi atau reverse calculation jika data aktual tersedia\n";
        $prompt .= "   - Jika data tidak tersedia untuk menjawab pertanyaan, jelaskan dengan sopan dan sarankan data apa yang diperlukan\n";
        $prompt .= "   - PRIORITASKAN data tambahan dari database jika tersedia\n\n";
        
        $prompt .= "7. CONTOH ANALISIS YANG BAIK:\n";
        $prompt .= "   - 'Revenue tanggal 26 adalah Rp X, turun Y% dari tanggal 25 (Rp Z). Analisis menunjukkan:\n";
        $prompt .= "     * Jumlah orders turun dari A ke B (penurunan C%)\n";
        $prompt .= "     * Average order value turun dari Rp D ke Rp E\n";
        $prompt .= "     * Outlet X mengalami penurunan terbesar (F%), sementara outlet Y relatif stabil\n";
        $prompt .= "     * Kemungkinan penyebab: [analisis berdasarkan data yang tersedia]\n";
        $prompt .= "     * Rekomendasi: [spesifik dan actionable]'\n";
        
        return $prompt;
    }
}
