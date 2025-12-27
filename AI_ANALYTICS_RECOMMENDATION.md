# 🎯 Rekomendasi Fitur AI untuk Sales Outlet Dashboard

## ⭐ REKOMENDASI PRIORITAS

### 🥇 **PRIORITAS 1: Auto Insight (WAJIB DIPASANG DULU)**
**Kenapa paling bagus?**
- ✅ **Paling berguna**: Insight otomatis setiap kali buka dashboard
- ✅ **Paling murah**: Hanya **Rp 0.035 per analisa** (Rp 5-10/bulan)
- ✅ **Paling mudah implementasi**: Cukup 1 endpoint, 1 component
- ✅ **ROI tinggi**: User langsung dapat value tanpa perlu belajar
- ✅ **Auto refresh**: Update otomatis saat data berubah

**Contoh Output:**
```
📊 Insight Hari Ini (15 Jan 2025):
✅ Revenue meningkat 15% dibanding kemarin (Rp 125M → Rp 143M)
✅ Item "Nasi Goreng" adalah best seller dengan 120 pcs terjual
✅ Peak hour: 19:00-20:00 dengan 45 orders (naik 20%)
⚠️ Item "Ayam Goreng" turun 30% penjualan, perlu investigasi
💡 Rekomendasi: Tambah stok untuk item terlaris di jam sibuk
```

**Biaya**: Rp 5-10/bulan (dengan caching)

---

### 🥈 **PRIORITAS 2: Q&A Dashboard (SANGAT DISARANKAN)**
**Kenapa bagus?**
- ✅ **Interaktif**: User bisa explore data dengan bertanya
- ✅ **User-friendly**: Natural language, tidak perlu belajar query
- ✅ **Fleksibel**: Bisa tanya apapun tentang data
- ✅ **Biaya terjangkau**: Rp 0.062 per pertanyaan

**Contoh Pertanyaan:**
- "Kenapa revenue hari ini turun?"
- "Item apa yang paling menguntungkan?"
- "Kapan jam paling sibuk?"
- "Outlet mana yang performanya terbaik?"
- "Berapa rata-rata order value hari ini?"

**Biaya**: Rp 3-5/bulan (asumsi 50-80 pertanyaan/bulan)

---

### 🥉 **PRIORITAS 3: Revenue Prediction (OPSIONAL)**
**Kenapa bagus?**
- ✅ **Berguna untuk planning**: Prediksi revenue minggu/bulan depan
- ✅ **Membantu budgeting**: Estimasi untuk perencanaan
- ✅ **Biaya murah**: Rp 0.05 per prediksi

**Kapan digunakan:**
- Saat planning budget bulanan
- Saat perlu estimasi untuk meeting
- Saat perlu forecast untuk investor

**Biaya**: Rp 1-2/bulan (asumsi 20-40 prediksi/bulan)

---

### 4. **Promo Recommendation (OPSIONAL)**
**Kenapa bagus?**
- ✅ **Membantu marketing**: Rekomendasi promo berdasarkan data
- ✅ **Data-driven**: Berdasarkan penjualan aktual
- ✅ **Biaya murah**: Rp 0.041 per rekomendasi

**Kapan digunakan:**
- Saat planning promo bulanan
- Saat ada item yang perlu boost
- Saat ada event khusus

**Biaya**: Rp 1-2/bulan (asumsi 25-50 rekomendasi/bulan)

---

### 5. **Anomaly Alert (OPSIONAL - UNTUK MONITORING)**
**Kenapa bagus?**
- ✅ **Early warning**: Deteksi masalah lebih cepat
- ✅ **Otomatis**: Tidak perlu manual check
- ✅ **Paling murah**: Rp 0.026 per alert

**Kapan digunakan:**
- Untuk monitoring real-time
- Untuk deteksi fraud/error
- Untuk quality control

**Biaya**: Rp 2-5/bulan (asumsi 100-200 alert/bulan)

---

## 🚀 REKOMENDASI IMPLEMENTASI (STEP BY STEP)

### **FASE 1: Mulai dengan Auto Insight (1-2 hari kerja)**

**Kenapa mulai dari sini?**
- Paling mudah dan cepat
- User langsung dapat value
- Biaya paling murah
- Bisa jadi proof of concept

**Estimasi waktu**: 4-6 jam
**Biaya bulanan**: Rp 5-10

---

### **FASE 2: Tambah Q&A Dashboard (2-3 hari kerja)**

**Kenapa tambah ini?**
- User sudah familiar dengan Auto Insight
- Q&A membuat dashboard lebih interaktif
- Biaya masih sangat terjangkau

**Estimasi waktu**: 6-8 jam
**Biaya bulanan tambahan**: Rp 3-5
**Total biaya**: Rp 8-15/bulan

---

### **FASE 3: Tambah Fitur Lain (Opsional)**

**Revenue Prediction**: Jika perlu forecasting
**Promo Recommendation**: Jika perlu bantuan marketing
**Anomaly Alert**: Jika perlu monitoring real-time

**Estimasi waktu**: 4-6 jam per fitur
**Biaya bulanan tambahan**: Rp 1-5 per fitur

---

## 📋 STEP-BY-STEP GUIDE: MULAI DENGAN AUTO INSIGHT

### **STEP 1: Setup Google Gemini API (5 menit)**

1. **Buka Google AI Studio**
   - Link: https://makersuite.google.com/app/apikey
   - Login dengan Google account

2. **Buat API Key**
   - Klik "Create API Key"
   - Pilih project (atau buat baru)
   - Copy API key yang di-generate

3. **Simpan API Key**
   - Tambahkan ke `.env`:
   ```env
   GOOGLE_GEMINI_API_KEY=your_api_key_here
   ```

---

### **STEP 2: Install Package (2 menit)**

```bash
cd D:\Gawean\web\ymsofterp
composer require google/generative-ai-php
```

---

### **STEP 3: Buat Config File (3 menit)**

Buat file `config/ai.php`:
```php
<?php

return [
    'gemini' => [
        'api_key' => env('GOOGLE_GEMINI_API_KEY'),
        'model' => 'gemini-pro',
        'temperature' => 0.7,
        'max_tokens' => 1000,
    ],
];
```

---

### **STEP 4: Buat Service (30 menit)**

Buat file `app/Services/AIAnalyticsService.php`:

```php
<?php

namespace App\Services;

use Google\GenerativeAI\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIAnalyticsService
{
    private $client;
    
    public function __construct()
    {
        $apiKey = config('ai.gemini.api_key');
        if (!$apiKey) {
            throw new \Exception('Google Gemini API key not configured');
        }
        $this->client = new Client($apiKey);
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
                
                $response = $this->client->models()->generateContent([
                    'model' => 'gemini-pro',
                    'contents' => $prompt,
                ]);
                
                return $this->parseInsightResponse($response);
            } catch (\Exception $e) {
                Log::error('AI Insight Error: ' . $e->getMessage());
                return "Maaf, tidak dapat menghasilkan insight saat ini. Silakan coba lagi nanti.";
            }
        });
    }
    
    /**
     * Build prompt untuk insight
     */
    private function buildInsightPrompt($data)
    {
        $overview = $data['overview'] ?? [];
        $topItems = array_slice($data['topItems'] ?? [], 0, 5);
        $hourlySales = $data['hourlySales'] ?? [];
        $paymentMethods = $data['paymentMethods'] ?? [];
        
        $prompt = "Analisa data dashboard penjualan outlet berikut dan berikan insight singkat (maksimal 5 poin) dalam bahasa Indonesia:\n\n";
        
        $prompt .= "OVERVIEW:\n";
        $prompt .= "- Total Orders: " . ($overview['total_orders'] ?? 0) . "\n";
        $prompt .= "- Total Revenue: Rp " . number_format($overview['total_revenue'] ?? 0, 0, ',', '.') . "\n";
        $prompt .= "- Average Order Value: Rp " . number_format($overview['avg_order_value'] ?? 0, 0, ',', '.') . "\n";
        $prompt .= "- Total Customers: " . ($overview['total_customers'] ?? 0) . "\n";
        
        if (isset($overview['orders_growth'])) {
            $prompt .= "- Growth Orders: " . number_format($overview['orders_growth'], 1) . "%\n";
        }
        if (isset($overview['revenue_growth'])) {
            $prompt .= "- Growth Revenue: " . number_format($overview['revenue_growth'], 1) . "%\n";
        }
        
        $prompt .= "\nTOP 5 ITEMS:\n";
        foreach ($topItems as $index => $item) {
            $prompt .= ($index + 1) . ". " . ($item['item_name'] ?? 'N/A') . " - Qty: " . ($item['total_qty'] ?? 0) . ", Revenue: Rp " . number_format($item['total_revenue'] ?? 0, 0, ',', '.') . "\n";
        }
        
        // Find peak hour
        if (!empty($hourlySales)) {
            $peakHour = collect($hourlySales)->sortByDesc('orders')->first();
            $prompt .= "\nPEAK HOUR: " . ($peakHour['hour'] ?? 'N/A') . ":00 dengan " . ($peakHour['orders'] ?? 0) . " orders\n";
        }
        
        $prompt .= "\nBerikan insight dalam format:\n";
        $prompt .= "- Gunakan emoji untuk setiap poin (✅ untuk positif, ⚠️ untuk warning, 💡 untuk rekomendasi)\n";
        $prompt .= "- Maksimal 5 poin\n";
        $prompt .= "- Bahasa Indonesia yang mudah dipahami\n";
        $prompt .= "- Fokus pada insight yang actionable\n";
        
        return $prompt;
    }
    
    /**
     * Parse response dari AI
     */
    private function parseInsightResponse($response)
    {
        // Extract text from response
        $text = '';
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $response['candidates'][0]['content']['parts'][0]['text'];
        } elseif (is_string($response)) {
            $text = $response;
        }
        
        return trim($text);
    }
}
```

---

### **STEP 5: Buat Controller (20 menit)**

Buat file `app/Http/Controllers/AIAnalyticsController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\AIAnalyticsService;
use App\Http\Controllers\SalesOutletDashboardController;
use Illuminate\Http\Request;
use Carbon\Carbon;

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
            
            // Get dashboard data menggunakan reflection untuk akses private method
            $reflection = new \ReflectionClass($this->dashboardController);
            $method = $reflection->getMethod('getDashboardData');
            $method->setAccessible(true);
            
            $dashboardData = $method->invoke($this->dashboardController, $dateFrom, $dateTo, 'daily');
            
            // Generate insight
            $insight = $this->aiService->generateAutoInsight($dashboardData);
            
            return response()->json([
                'success' => true,
                'insight' => $insight,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghasilkan insight: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

**Atau lebih baik, buat method public di SalesOutletDashboardController:**

Tambahkan di `app/Http/Controllers/SalesOutletDashboardController.php`:

```php
/**
 * Get dashboard data (public method untuk AI service)
 */
public function getDashboardDataPublic($dateFrom, $dateTo, $period = 'daily')
{
    return $this->getDashboardData($dateFrom, $dateTo, $period);
}
```

Lalu update `AIAnalyticsController.php`:

```php
$dashboardData = $this->dashboardController->getDashboardDataPublic($dateFrom, $dateTo, 'daily');
```

---

### **STEP 6: Tambah Route (2 menit)**

Tambahkan di `routes/api.php`:

```php
Route::prefix('ai')->middleware(['auth'])->group(function () {
    Route::get('/insight', [App\Http\Controllers\AIAnalyticsController::class, 'getAutoInsight']);
});
```

---

### **STEP 7: Buat Frontend Component (1 jam)**

Buat file `resources/js/Pages/SalesOutletDashboard/Components/AIAnalytics.vue`:

```vue
<template>
  <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
        <span class="text-2xl">🤖</span>
        AI Insight
      </h3>
      <button
        @click="loadInsight"
        :disabled="loading"
        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
      >
        <i class="fa-solid fa-refresh mr-2" :class="{ 'fa-spin': loading }"></i>
        {{ loading ? 'Loading...' : 'Refresh' }}
      </button>
    </div>
    
    <div v-if="loading && !insight" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
      <p class="mt-2 text-gray-600">Menganalisa data...</p>
    </div>
    
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
      <p class="text-red-800">{{ error }}</p>
    </div>
    
    <div v-else-if="insight" class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 border-l-4 border-blue-500">
      <div class="prose prose-sm max-w-none">
        <div class="whitespace-pre-line text-gray-700 leading-relaxed">{{ insight }}</div>
      </div>
      <div class="mt-4 text-xs text-gray-500 flex items-center gap-2">
        <i class="fa-solid fa-clock"></i>
        Terakhir diupdate: {{ lastUpdated }}
      </div>
    </div>
    
    <div v-else class="text-center py-8 text-gray-500">
      <i class="fa-solid fa-robot text-4xl mb-2"></i>
      <p>Klik "Refresh" untuk mendapatkan insight AI</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  filters: {
    type: Object,
    required: true
  }
});

const insight = ref(null);
const loading = ref(false);
const error = ref(null);
const lastUpdated = ref(null);

const loadInsight = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const response = await axios.get('/api/ai/insight', {
      params: {
        date_from: props.filters.date_from,
        date_to: props.filters.date_to
      }
    });
    
    if (response.data.success) {
      insight.value = response.data.insight;
      lastUpdated.value = new Date().toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } else {
      error.value = response.data.message || 'Gagal memuat insight';
    }
  } catch (err) {
    console.error('AI Insight Error:', err);
    error.value = 'Gagal memuat insight. Silakan coba lagi nanti.';
  } finally {
    loading.value = false;
  }
};

// Auto load saat component mount
onMounted(() => {
  loadInsight();
});
</script>

<style scoped>
.prose {
  font-size: 14px;
}
</style>
```

---

### **STEP 8: Integrate ke Dashboard (10 menit)**

Edit `resources/js/Pages/SalesOutletDashboard/Index.vue`:

1. **Import component** (di bagian `<script setup>`):
```javascript
import AIAnalytics from './Components/AIAnalytics.vue';
```

2. **Tambahkan component** (di bagian template, setelah filter atau di bagian atas):
```vue
<AIAnalytics :filters="filters" />
```

---

### **STEP 9: Test (5 menit)**

1. **Buka dashboard**: `/sales-outlet-dashboard`
2. **Cek apakah AI Insight muncul**
3. **Klik "Refresh"** untuk test
4. **Cek console** untuk error (jika ada)

---

## ✅ CHECKLIST IMPLEMENTASI

- [ ] Setup Google Gemini API key
- [ ] Install package `google/generative-ai-php`
- [ ] Buat config file `config/ai.php`
- [ ] Buat service `app/Services/AIAnalyticsService.php`
- [ ] Buat controller `app/Http/Controllers/AIAnalyticsController.php`
- [ ] Tambah route di `routes/api.php`
- [ ] Buat component `resources/js/Pages/SalesOutletDashboard/Components/AIAnalytics.vue`
- [ ] Integrate component ke dashboard
- [ ] Test di browser
- [ ] Monitor biaya di Google AI Studio

---

## 🎯 KESIMPULAN REKOMENDASI

### **Mulai dengan:**
1. ✅ **Auto Insight** (PRIORITAS 1)
   - Paling mudah
   - Paling murah (Rp 5-10/bulan)
   - User langsung dapat value

### **Tambah setelah itu:**
2. ✅ **Q&A Dashboard** (PRIORITAS 2)
   - Membuat dashboard lebih interaktif
   - Biaya masih terjangkau (Rp 3-5/bulan)

### **Total biaya untuk 2 fitur:**
- **Rp 8-15/bulan** (dengan caching)
- **Sangat terjangkau!** ✅

### **Estimasi waktu implementasi:**
- **Fase 1 (Auto Insight)**: 4-6 jam
- **Fase 2 (Q&A)**: 6-8 jam
- **Total**: 10-14 jam (1-2 hari kerja)

---

## 🚀 READY TO START?

Ikuti step-by-step guide di atas, mulai dari **STEP 1** sampai **STEP 9**. 

Jika ada pertanyaan atau error, cek:
1. API key sudah benar di `.env`
2. Package sudah terinstall
3. Route sudah terdaftar
4. Component sudah di-import dengan benar

**Good luck!** 🎉

