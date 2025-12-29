# 🚀 AI Analytics Upgrade Guide - Analisis Kompleks & Data Eksternal

## 📋 Overview

AI Analytics telah diupgrade untuk memberikan analisis yang lebih kompleks dan mendalam dengan:
- ✅ Model AI yang lebih powerful (Gemini 1.5 Pro)
- ✅ Akses ke data eksternal (cuaca, event, tren pasar)
- ✅ Analisis kompleks (trend analysis, predictive analysis, root cause analysis)
- ✅ Function calling untuk akses data dinamis

## 🔧 Konfigurasi

### 1. Upgrade Model AI

Edit file `.env`:

```env
# Pilih provider AI (gemini, openai, atau claude)
AI_PROVIDER=gemini

# Untuk Gemini (Recommended untuk analisis kompleks)
GOOGLE_GEMINI_API_KEY=your_api_key_here
GEMINI_MODEL=gemini-1.5-pro  # atau gemini-1.5-flash untuk lebih cepat

# Alternatif: OpenAI (Lebih powerful tapi lebih mahal)
OPENAI_API_KEY=your_openai_key_here
OPENAI_MODEL=gpt-4o  # atau gpt-4o-mini untuk lebih murah

# Alternatif: Anthropic Claude (Sangat powerful untuk analisis)
ANTHROPIC_API_KEY=your_claude_key_here
CLAUDE_MODEL=claude-3-5-sonnet-20241022
```

### 2. Setup Data Eksternal (Optional tapi Recommended)

```env
# Enable data eksternal
AI_EXTERNAL_DATA_ENABLED=true

# Weather API (OpenWeatherMap) - Free tier available
WEATHER_API_KEY=your_weather_api_key

# News API (untuk tren pasar) - Free tier available
NEWS_API_KEY=your_news_api_key
```

## 🎯 Fitur Baru

### 1. Analisis Kompleks

AI sekarang dapat melakukan:
- **Trend Analysis**: Identifikasi pola jangka panjang
- **Comparative Analysis**: Perbandingan dengan periode sebelumnya
- **Correlation Analysis**: Korelasi antar variabel
- **Predictive Analysis**: Prediksi berdasarkan pola historis
- **Root Cause Analysis**: Identifikasi penyebab mendalam

### 2. Data Eksternal

AI dapat mengakses:
- **Data Cuaca**: Menganalisis pengaruh cuaca terhadap penjualan
- **Event/Holiday**: Menganalisis pengaruh event terhadap penjualan
- **Tren Pasar**: Analisis tren industri F&B

### 3. Function Calling

AI dapat memanggil fungsi untuk:
- `get_market_trends`: Mendapatkan tren pasar dan analisis kompetitor
- `get_advanced_analytics`: Forecasting dan prediksi
- `get_customer_insights`: Insight tentang perilaku customer

## 📊 Contoh Pertanyaan yang Bisa Dijawab

### Analisis Kompleks
- "Apa penyebab penurunan revenue bulan ini? Lakukan root cause analysis."
- "Bandingkan performa outlet A dengan outlet B, termasuk faktor eksternal."
- "Prediksi revenue untuk 30 hari ke depan berdasarkan pola historis."
- "Analisis korelasi antara cuaca, event, dan penjualan."

### Analisis Eksternal
- "Bagaimana cuaca mempengaruhi penjualan hari ini?"
- "Apakah ada event yang mempengaruhi penjualan minggu ini?"
- "Bagaimana tren pasar F&B saat ini mempengaruhi bisnis kita?"

### Analisis Prediktif
- "Forecast penjualan untuk bulan depan."
- "Prediksi item mana yang akan laris bulan depan."
- "Kapan waktu terbaik untuk promosi berdasarkan pola historis?"

## 💰 Biaya

### Gemini 1.5 Pro
- Input: $1.25 per 1M tokens
- Output: $5.00 per 1M tokens
- **Recommended** untuk analisis kompleks dengan biaya terjangkau

### Gemini 1.5 Flash
- Input: $0.075 per 1M tokens
- Output: $0.30 per 1M tokens
- Lebih cepat dan murah, cocok untuk analisis standar

### GPT-4o
- Input: $2.50 per 1M tokens
- Output: $10.00 per 1M tokens
- Sangat powerful tapi lebih mahal

### Claude 3.5 Sonnet
- Input: $3.00 per 1M tokens
- Output: $15.00 per 1M tokens
- Terbaik untuk analisis kompleks tapi paling mahal

## 🔍 Perbandingan Model

| Model | Kecepatan | Kualitas Analisis | Biaya | Recommended For |
|-------|-----------|-------------------|-------|----------------|
| Gemini 1.5 Flash | ⚡⚡⚡ | ⭐⭐⭐ | 💰 | Analisis standar |
| Gemini 1.5 Pro | ⚡⚡ | ⭐⭐⭐⭐ | 💰💰 | Analisis kompleks (Recommended) |
| GPT-4o | ⚡⚡ | ⭐⭐⭐⭐⭐ | 💰💰💰 | Analisis sangat kompleks |
| Claude 3.5 Sonnet | ⚡ | ⭐⭐⭐⭐⭐ | 💰💰💰💰 | Analisis enterprise |

## 🚀 Quick Start

1. **Upgrade Model** (di `.env`):
   ```env
   GEMINI_MODEL=gemini-1.5-pro
   ```

2. **Enable Data Eksternal** (optional):
   ```env
   AI_EXTERNAL_DATA_ENABLED=true
   WEATHER_API_KEY=your_key_here
   ```

3. **Test di Dashboard**:
   - Buka Sales Outlet Dashboard
   - Tanya AI: "Lakukan analisis kompleks tentang penjualan bulan ini, termasuk faktor eksternal"
   - Lihat hasil analisis yang lebih mendalam

## 📝 Catatan Penting

1. **Model Gemini 1.5 Pro** memerlukan API key yang valid
2. **Data eksternal** adalah optional tapi sangat recommended untuk analisis yang lebih kaya
3. **Function calling** akan diimplementasikan secara bertahap
4. **Biaya** akan meningkat dengan model yang lebih powerful, tapi hasil analisis jauh lebih baik

## 🐛 Troubleshooting

### AI masih memberikan analisis standar?
- Pastikan `GEMINI_MODEL=gemini-1.5-pro` di `.env`
- Clear cache: `php artisan cache:clear`
- Restart server

### Data eksternal tidak muncul?
- Pastikan `AI_EXTERNAL_DATA_ENABLED=true`
- Pastikan API key untuk weather/news sudah di-set
- Check log untuk error: `storage/logs/laravel.log`

### Error "API key not configured"?
- Pastikan API key sudah di-set di `.env`
- Run: `php artisan config:clear`
- Restart server

## 📚 Referensi

- [Google Gemini API Docs](https://ai.google.dev/docs)
- [OpenAI API Docs](https://platform.openai.com/docs)
- [Anthropic Claude API Docs](https://docs.anthropic.com)

