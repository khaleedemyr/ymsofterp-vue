# 🧠 AI Analytics untuk Sales Outlet Dashboard - Implementasi & Biaya

## 📊 Overview

Implementasi AI untuk analisa otomatis di Sales Outlet Dashboard menggunakan **Google Gemini Pro** (paling murah) dengan biaya sangat terjangkau.

---

## 💰 ESTIMASI BIAYA (Dalam Rupiah)

**Kurs USD ke IDR: Rp 15,500** (perhitungan konservatif)

### Google Gemini Pro Pricing:
- **Input**: $0.50 per 1 juta tokens = **Rp 7,750 per 1 juta tokens**
- **Output**: $1.50 per 1 juta tokens = **Rp 23,250 per 1 juta tokens**

### Estimasi Biaya Per Analisa:

#### 1. **Auto Insight (Analisa Otomatis)**
- **Input tokens**: ~3,000 tokens (data dashboard + prompt)
- **Output tokens**: ~500 tokens (insight singkat)
- **Biaya per analisa**: 
  - Input: (3,000 / 1,000,000) × Rp 7,750 = **Rp 0.023**
  - Output: (500 / 1,000,000) × Rp 23,250 = **Rp 0.012**
  - **Total: Rp 0.035 per analisa** ✅

#### 2. **Revenue Prediction (Prediksi Revenue)**
- **Input tokens**: ~4,000 tokens (data historis + prompt)
- **Output tokens**: ~800 tokens (prediksi + penjelasan)
- **Biaya per analisa**:
  - Input: (4,000 / 1,000,000) × Rp 7,750 = **Rp 0.031**
  - Output: (800 / 1,000,000) × Rp 23,250 = **Rp 0.019**
  - **Total: Rp 0.05 per analisa** ✅

#### 3. **Promo Recommendation (Rekomendasi Promo)**
- **Input tokens**: ~3,500 tokens (data item + sales + prompt)
- **Output tokens**: ~600 tokens (rekomendasi)
- **Biaya per analisa**:
  - Input: (3,500 / 1,000,000) × Rp 7,750 = **Rp 0.027**
  - Output: (600 / 1,000,000) × Rp 23,250 = **Rp 0.014**
  - **Total: Rp 0.041 per analisa** ✅

#### 4. **Anomaly Alert (Alert Otomatis)**
- **Input tokens**: ~2,500 tokens (data real-time + prompt)
- **Output tokens**: ~300 tokens (alert singkat)
- **Biaya per analisa**:
  - Input: (2,500 / 1,000,000) × Rp 7,750 = **Rp 0.019**
  - Output: (300 / 1,000,000) × Rp 23,250 = **Rp 0.007**
  - **Total: Rp 0.026 per analisa** ✅

#### 5. **Q&A Dashboard (Tanya Jawab)**
- **Input tokens**: ~5,000 tokens (data + pertanyaan user)
- **Output tokens**: ~1,000 tokens (jawaban detail)
- **Biaya per analisa**:
  - Input: (5,000 / 1,000,000) × Rp 7,750 = **Rp 0.039**
  - Output: (1,000 / 1,000,000) × Rp 23,250 = **Rp 0.023**
  - **Total: Rp 0.062 per analisa** ✅

---

## 📈 ESTIMASI BIAYA BULANAN

### Skenario 1: Penggunaan Ringan
- **10 analisa/hari** = 300 analisa/bulan
- **Mix**: 50% Auto Insight, 30% Revenue Prediction, 20% Promo Recommendation
- **Biaya**:
  - Auto Insight: 150 × Rp 0.035 = **Rp 5.25**
  - Revenue Prediction: 90 × Rp 0.05 = **Rp 4.50**
  - Promo Recommendation: 60 × Rp 0.041 = **Rp 2.46**
  - **Total: Rp 12.21/bulan** ✅✅✅

### Skenario 2: Penggunaan Sedang
- **50 analisa/hari** = 1,500 analisa/bulan
- **Mix**: 40% Auto Insight, 30% Revenue Prediction, 20% Promo Recommendation, 10% Q&A
- **Biaya**:
  - Auto Insight: 600 × Rp 0.035 = **Rp 21**
  - Revenue Prediction: 450 × Rp 0.05 = **Rp 22.50**
  - Promo Recommendation: 300 × Rp 0.041 = **Rp 12.30**
  - Q&A: 150 × Rp 0.062 = **Rp 9.30**
  - **Total: Rp 65.10/bulan** ✅✅

### Skenario 3: Penggunaan Berat
- **200 analisa/hari** = 6,000 analisa/bulan
- **Mix**: 30% Auto Insight, 25% Revenue Prediction, 25% Promo Recommendation, 20% Q&A
- **Biaya**:
  - Auto Insight: 1,800 × Rp 0.035 = **Rp 63**
  - Revenue Prediction: 1,500 × Rp 0.05 = **Rp 75**
  - Promo Recommendation: 1,500 × Rp 0.041 = **Rp 61.50**
  - Q&A: 1,200 × Rp 0.062 = **Rp 74.40**
  - **Total: Rp 273.90/bulan** ✅

### Skenario 4: Penggunaan Ekstrem (Real-time Monitoring)
- **500 analisa/hari** = 15,000 analisa/bulan
- **Mix**: 50% Auto Insight, 30% Anomaly Alert, 20% Revenue Prediction
- **Biaya**:
  - Auto Insight: 7,500 × Rp 0.035 = **Rp 262.50**
  - Anomaly Alert: 4,500 × Rp 0.026 = **Rp 117**
  - Revenue Prediction: 3,000 × Rp 0.05 = **Rp 150**
  - **Total: Rp 529.50/bulan** ✅

---

## 🎯 FITUR-FITUR YANG BISA DITAMBAHKAN

### 1. **Auto Insight (Analisa Otomatis)**
**Fungsi**: Analisa otomatis saat data dashboard berubah
**Biaya**: Rp 0.035 per analisa
**Contoh Output**:
```
📊 Insight Hari Ini (15 Jan 2025):
- Revenue meningkat 15% dibanding kemarin
- Item "Nasi Goreng" adalah best seller dengan 120 pcs terjual
- Peak hour: 19:00-20:00 dengan 45 orders
- Rekomendasi: Tambah stok untuk item terlaris di jam sibuk
```

### 2. **Revenue Prediction (Prediksi Revenue)**
**Fungsi**: Prediksi revenue untuk periode berikutnya
**Biaya**: Rp 0.05 per prediksi
**Contoh Output**:
```
📈 Prediksi Revenue Minggu Depan:
- Estimasi: Rp 125,000,000 - Rp 135,000,000
- Confidence: 85%
- Faktor: Weekend akan lebih tinggi 20% dari weekday
- Rekomendasi: Siapkan stok ekstra untuk weekend
```

### 3. **Promo Recommendation (Rekomendasi Promo)**
**Fungsi**: Rekomendasi promo berdasarkan data sales
**Biaya**: Rp 0.041 per rekomendasi
**Contoh Output**:
```
🎁 Rekomendasi Promo:
- Item "Ayam Goreng" turun 30% penjualan, rekomendasikan promo 20% off
- Item "Es Jeruk" naik 50%, pertimbangkan bundle dengan makanan
- Weekend revenue tinggi, pertimbangkan promo "Weekend Special"
```

### 4. **Anomaly Alert (Alert Otomatis)**
**Fungsi**: Notifikasi jika ada anomali dalam data
**Biaya**: Rp 0.026 per alert
**Contoh Output**:
```
⚠️ Alert: Anomali Terdeteksi!
- Revenue turun drastis 40% dibanding hari sebelumnya
- Item "Nasi Goreng" tidak ada penjualan (biasanya 50+ pcs/hari)
- Payment method "Cash" turun 60% (biasanya 40% dari total)
```

### 5. **Q&A Dashboard (Tanya Jawab)**
**Fungsi**: User bisa bertanya tentang data dashboard
**Biaya**: Rp 0.062 per pertanyaan
**Contoh Pertanyaan & Jawaban**:
```
Q: "Kenapa revenue hari ini turun?"
A: Revenue turun 15% karena:
   - Jumlah order turun 20% (dari 150 ke 120 orders)
   - Average order value tetap stabil di Rp 85,000
   - Item "Nasi Goreng" turun 30% penjualan
   - Rekomendasi: Cek apakah ada masalah operasional atau promosi

Q: "Item apa yang paling menguntungkan?"
A: Berdasarkan margin dan volume:
   1. "Ayam Goreng" - Margin tinggi, volume tinggi
   2. "Nasi Goreng" - Volume sangat tinggi
   3. "Es Jeruk" - Margin tinggi, volume sedang
```

---

## 🏗️ STRUKTUR IMPLEMENTASI

### 1. **Service Layer**
```
app/Services/AIAnalyticsService.php
```
- Handle komunikasi dengan Google Gemini API
- Format data dashboard ke format yang bisa dipahami AI
- Parse response dari AI ke format yang bisa ditampilkan

### 2. **Controller**
```
app/Http/Controllers/AIAnalyticsController.php
```
- Endpoint untuk request analisa AI
- Endpoint untuk Q&A
- Endpoint untuk auto insight

### 3. **Frontend Component**
```
resources/js/Pages/SalesOutletDashboard/Components/AIAnalytics.vue
```
- UI untuk menampilkan insight AI
- Form untuk Q&A
- Toggle untuk enable/disable auto insight

### 4. **Configuration**
```
config/ai.php
```
- API key Google Gemini
- Model configuration
- Rate limiting
- Caching strategy

---

## 🔧 DETAIL IMPLEMENTASI

### 1. Setup Google Gemini API

#### Step 1: Daftar Google AI Studio
- Buka: https://makersuite.google.com/app/apikey
- Buat API key (gratis)
- Copy API key

#### Step 2: Install Package
```bash
composer require google/generative-ai-php
```

#### Step 3: Setup Config
```php
// config/ai.php
return [
    'gemini' => [
        'api_key' => env('GOOGLE_GEMINI_API_KEY'),
        'model' => 'gemini-pro',
        'temperature' => 0.7,
        'max_tokens' => 1000,
    ],
];
```

### 2. Service Implementation

```php
// app/Services/AIAnalyticsService.php
namespace App\Services;

use Google\GenerativeAI\Client;
use Illuminate\Support\Facades\Cache;

class AIAnalyticsService
{
    private $client;
    
    public function __construct()
    {
        $this->client = new Client(config('ai.gemini.api_key'));
    }
    
    /**
     * Generate auto insight dari dashboard data
     */
    public function generateAutoInsight($dashboardData)
    {
        // Cache untuk menghindari duplicate request
        $cacheKey = 'ai_insight_' . md5(json_encode($dashboardData));
        
        return Cache::remember($cacheKey, 3600, function() use ($dashboardData) {
            $prompt = $this->buildInsightPrompt($dashboardData);
            $response = $this->client->models()->generateContent([
                'model' => 'gemini-pro',
                'contents' => $prompt,
            ]);
            
            return $this->parseInsightResponse($response);
        });
    }
    
    /**
     * Prediksi revenue untuk periode berikutnya
     */
    public function predictRevenue($historicalData)
    {
        $prompt = $this->buildPredictionPrompt($historicalData);
        $response = $this->client->models()->generateContent([
            'model' => 'gemini-pro',
            'contents' => $prompt,
        ]);
        
        return $this->parsePredictionResponse($response);
    }
    
    /**
     * Rekomendasi promo berdasarkan data sales
     */
    public function recommendPromo($salesData)
    {
        $prompt = $this->buildPromoPrompt($salesData);
        $response = $this->client->models()->generateContent([
            'model' => 'gemini-pro',
            'contents' => $prompt,
        ]);
        
        return $this->parsePromoResponse($response);
    }
    
    /**
     * Q&A tentang dashboard data
     */
    public function answerQuestion($question, $dashboardData)
    {
        $prompt = $this->buildQAPrompt($question, $dashboardData);
        $response = $this->client->models()->generateContent([
            'model' => 'gemini-pro',
            'contents' => $prompt,
        ]);
        
        return $this->parseQAResponse($response);
    }
    
    /**
     * Deteksi anomali dalam data
     */
    public function detectAnomaly($currentData, $historicalData)
    {
        $prompt = $this->buildAnomalyPrompt($currentData, $historicalData);
        $response = $this->client->models()->generateContent([
            'model' => 'gemini-pro',
            'contents' => $prompt,
        ]);
        
        return $this->parseAnomalyResponse($response);
    }
    
    // Helper methods untuk build prompt...
    private function buildInsightPrompt($data) { /* ... */ }
    private function buildPredictionPrompt($data) { /* ... */ }
    private function buildPromoPrompt($data) { /* ... */ }
    private function buildQAPrompt($question, $data) { /* ... */ }
    private function buildAnomalyPrompt($current, $historical) { /* ... */ }
    
    // Helper methods untuk parse response...
    private function parseInsightResponse($response) { /* ... */ }
    private function parsePredictionResponse($response) { /* ... */ }
    private function parsePromoResponse($response) { /* ... */ }
    private function parseQAResponse($response) { /* ... */ }
    private function parseAnomalyResponse($response) { /* ... */ }
}
```

### 3. Controller Implementation

```php
// app/Http/Controllers/AIAnalyticsController.php
namespace App\Http\Controllers;

use App\Services\AIAnalyticsService;
use App\Http\Controllers\SalesOutletDashboardController;
use Illuminate\Http\Request;

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
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        // Get dashboard data
        $dashboardData = $this->dashboardController->getDashboardData(
            $dateFrom, 
            $dateTo, 
            'daily'
        );
        
        // Generate insight
        $insight = $this->aiService->generateAutoInsight($dashboardData);
        
        return response()->json([
            'success' => true,
            'insight' => $insight
        ]);
    }
    
    /**
     * Q&A tentang dashboard
     */
    public function askQuestion(Request $request)
    {
        $question = $request->get('question');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        // Get dashboard data
        $dashboardData = $this->dashboardController->getDashboardData(
            $dateFrom, 
            $dateTo, 
            'daily'
        );
        
        // Get answer
        $answer = $this->aiService->answerQuestion($question, $dashboardData);
        
        return response()->json([
            'success' => true,
            'answer' => $answer
        ]);
    }
    
    /**
     * Prediksi revenue
     */
    public function predictRevenue(Request $request)
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        // Get historical data (last 30 days)
        $historicalData = $this->dashboardController->getHistoricalData(
            Carbon::parse($dateTo)->subDays(30)->format('Y-m-d'),
            $dateTo
        );
        
        // Predict
        $prediction = $this->aiService->predictRevenue($historicalData);
        
        return response()->json([
            'success' => true,
            'prediction' => $prediction
        ]);
    }
}
```

### 4. Frontend Component

```vue
<!-- resources/js/Pages/SalesOutletDashboard/Components/AIAnalytics.vue -->
<template>
  <div class="ai-analytics-panel">
    <!-- Auto Insight -->
    <div class="insight-card">
      <h3>🤖 AI Insight</h3>
      <div v-if="loading" class="loading">Loading insight...</div>
      <div v-else-if="insight" class="insight-content">
        <p>{{ insight }}</p>
      </div>
      <button @click="loadInsight">Refresh Insight</button>
    </div>
    
    <!-- Q&A -->
    <div class="qa-card">
      <h3>💬 Tanya AI tentang Dashboard</h3>
      <input 
        v-model="question" 
        placeholder="Contoh: Kenapa revenue turun?"
        @keyup.enter="askQuestion"
      />
      <button @click="askQuestion">Tanya</button>
      <div v-if="answer" class="answer-content">
        <p>{{ answer }}</p>
      </div>
    </div>
    
    <!-- Revenue Prediction -->
    <div class="prediction-card">
      <h3>📈 Prediksi Revenue</h3>
      <button @click="predictRevenue">Prediksi Minggu Depan</button>
      <div v-if="prediction" class="prediction-content">
        <p>{{ prediction }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const insight = ref(null);
const answer = ref(null);
const prediction = ref(null);
const question = ref('');
const loading = ref(false);

const loadInsight = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/ai/insight', {
      params: {
        date_from: props.filters.date_from,
        date_to: props.filters.date_to
      }
    });
    insight.value = response.data.insight;
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const askQuestion = async () => {
  if (!question.value) return;
  
  loading.value = true;
  try {
    const response = await axios.post('/api/ai/ask', {
      question: question.value,
      date_from: props.filters.date_from,
      date_to: props.filters.date_to
    });
    answer.value = response.data.answer;
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const predictRevenue = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/ai/predict', {
      params: {
        date_from: props.filters.date_from,
        date_to: props.filters.date_to
      }
    });
    prediction.value = response.data.prediction;
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};
</script>
```

---

## 💡 OPTIMASI BIAYA

### 1. **Caching Strategy**
- Cache insight selama 1 jam (data tidak berubah cepat)
- Cache prediction selama 6 jam
- **Penghematan**: 70-80% dari total biaya

### 2. **Rate Limiting**
- Maksimal 10 request per menit per user
- Maksimal 100 request per jam per user
- **Penghematan**: Mencegah abuse

### 3. **Selective Loading**
- Auto insight hanya load saat user buka dashboard
- Q&A hanya load saat user bertanya
- **Penghematan**: Tidak ada request yang tidak perlu

### 4. **Data Compression**
- Hanya kirim data yang relevan ke AI
- Exclude data yang tidak diperlukan
- **Penghematan**: 30-40% dari input tokens

---

## 📊 RINGKASAN BIAYA

| Fitur | Biaya/Request | Penggunaan Ringan | Penggunaan Sedang | Penggunaan Berat |
|-------|---------------|-------------------|-------------------|-------------------|
| Auto Insight | Rp 0.035 | Rp 5.25/bulan | Rp 21/bulan | Rp 63/bulan |
| Revenue Prediction | Rp 0.05 | Rp 4.50/bulan | Rp 22.50/bulan | Rp 75/bulan |
| Promo Recommendation | Rp 0.041 | Rp 2.46/bulan | Rp 12.30/bulan | Rp 61.50/bulan |
| Anomaly Alert | Rp 0.026 | Rp 0/bulan | Rp 0/bulan | Rp 117/bulan |
| Q&A Dashboard | Rp 0.062 | Rp 0/bulan | Rp 9.30/bulan | Rp 74.40/bulan |
| **TOTAL** | - | **Rp 12.21/bulan** | **Rp 65.10/bulan** | **Rp 273.90/bulan** |

**Dengan optimasi caching**: Biaya bisa turun 70-80%!

---

## ✅ KESIMPULAN

1. **Biaya sangat murah**: Mulai dari **Rp 12/bulan** untuk penggunaan ringan
2. **Google Gemini Pro**: Paling murah dengan kualitas baik
3. **ROI tinggi**: Insight AI bisa membantu pengambilan keputusan lebih cepat
4. **Scalable**: Bisa ditambah fitur sesuai kebutuhan
5. **Mudah implementasi**: Hanya perlu setup API key dan service layer

**Rekomendasi**: Mulai dengan **Auto Insight** dan **Q&A Dashboard** dulu, biaya hanya **Rp 5-10/bulan** untuk penggunaan ringan! 🚀

