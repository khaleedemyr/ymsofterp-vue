<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\AIDatabaseHelper;
use Carbon\Carbon;

class AIAnalyticsService
{
    private $apiKey;
    private $model;
    private $apiUrl;
    private $dbHelper;
    
    public function __construct(AIDatabaseHelper $dbHelper)
    {
        $this->apiKey = config('ai.gemini.api_key');
        $this->model = config('ai.gemini.model', 'gemini-2.5-flash');
        $this->dbHelper = $dbHelper;
        
        if (!$this->apiKey) {
            throw new \Exception('Google Gemini API key not configured');
        }
        
        // Build API URL - use v1beta with gemini-2.5-flash
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent';
    }
    
    /**
     * Generate auto insight dari dashboard data
     */
    public function generateAutoInsight($dashboardData)
    {
        // Cache untuk 1 jam (data tidak berubah cepat)
        $cacheKey = 'ai_insight_' . md5(json_encode($dashboardData));
        
        return Cache::remember($cacheKey, 3600, function() use ($dashboardData) {
            try {
                $prompt = $this->buildInsightPrompt($dashboardData);
                
                // Call Google Gemini API via HTTP
                // Use gemini-2.5-flash (available model from API)
                $model = config('ai.gemini.model', 'gemini-2.5-flash');
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;
                
                $requestBody = [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1000,
                    ]
                ];
                
                Log::info('AI API Request', [
                    'url' => $url,
                    'prompt_length' => strlen($prompt),
                    'body' => $requestBody
                ]);
                
                // Setup HTTP client
                $httpClient = Http::timeout(30)
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
        
        $prompt .= "\n=== INSTRUKSI ===\n";
        $prompt .= "Berikan insight dalam format:\n";
        $prompt .= "- Gunakan emoji untuk setiap poin (✅ untuk positif, ⚠️ untuk warning, 💡 untuk rekomendasi, 📊 untuk data)\n";
        $prompt .= "- Maksimal 5 poin insight\n";
        $prompt .= "- Bahasa Indonesia yang mudah dipahami\n";
        $prompt .= "- Fokus pada insight yang actionable (bisa ditindaklanjuti)\n";
        $prompt .= "- Sertakan angka/data spesifik jika relevan\n";
        $prompt .= "- Format: Bullet point dengan emoji di awal\n";
        
        return $prompt;
    }
    
    /**
     * Q&A tentang dashboard data dengan akses database dinamis
     */
    public function answerQuestion($question, $dashboardData, $dateFrom = null, $dateTo = null)
    {
        try {
            // Analisis pertanyaan untuk menentukan data tambahan yang diperlukan
            $additionalData = $this->analyzeQuestionAndFetchData($question, $dateFrom, $dateTo);
            
            $prompt = $this->buildQAPrompt($question, $dashboardData, $additionalData);
            
            // Call Google Gemini API via HTTP
            // Use gemini-2.5-flash (available model from API)
            $model = config('ai.gemini.model', 'gemini-2.5-flash');
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->apiKey;
            
            $requestBody = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 2000, // Lebih banyak untuk jawaban detail
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
     * Build prompt untuk Q&A
     */
    private function buildQAPrompt($question, $data, $additionalData = [])
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
        $prompt .= "Pertanyaan user: {$question}\n\n";
        $prompt .= "Data dashboard yang tersedia:\n\n";
        
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
        
        $prompt .= "\n=== INSTRUKSI ===\n";
        $prompt .= "Jawab pertanyaan user berdasarkan data di atas dengan:\n";
        $prompt .= "- Bahasa Indonesia yang mudah dipahami\n";
        $prompt .= "- GUNAKAN DATA AKTUAL dari database yang sudah disediakan, JANGAN menghitung estimasi atau reverse calculation\n";
        $prompt .= "- Jika ada data periode sebelumnya, gunakan data tersebut untuk perbandingan, bukan estimasi\n";
        $prompt .= "- PRIORITASKAN data tambahan dari database jika tersedia\n";
        $prompt .= "- Sertakan angka/data spesifik dari data yang tersedia\n";
        $prompt .= "- Jika pertanyaan tidak bisa dijawab dengan data yang ada, jelaskan dengan sopan\n";
        $prompt .= "- Format jawaban: Paragraf yang jelas dan informatif\n";
        $prompt .= "- Jika relevan, sertakan rekomendasi atau insight tambahan\n";
        
        return $prompt;
    }
}
