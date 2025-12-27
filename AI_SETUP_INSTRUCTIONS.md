# 🚀 Setup AI Analytics - Instruksi Lengkap

## ✅ File yang Sudah Dibuat

1. ✅ `config/ai.php` - Configuration file (API key sudah di-set)
2. ✅ `app/Services/AIAnalyticsService.php` - Service untuk AI
3. ✅ `app/Http/Controllers/AIAnalyticsController.php` - Controller untuk API
4. ✅ `routes/api.php` - Route sudah ditambahkan
5. ✅ `resources/js/Pages/SalesOutletDashboard/Components/AIAnalytics.vue` - Component frontend
6. ✅ `resources/js/Pages/SalesOutletDashboard/Index.vue` - Sudah di-integrate

## 📋 LANGKAH SETUP

### STEP 1: Install Package (OPSIONAL - TIDAK PERLU!)

**Package tidak perlu diinstall!** Service sudah menggunakan HTTP client langsung yang sudah built-in di Laravel.

**Jika ingin coba install package** (tidak wajib):
```bash
cd D:\Gawean\web\ymsofterp
composer require google/generative-ai-php
```

**Jika error** (package tidak ditemukan), **TIDAK MASALAH** - kita sudah pakai HTTP client langsung!

---

### STEP 2: Setup .env (OPSIONAL - sudah ada di config)

**Catatan**: API key sudah di-set di `config/ai.php`, jadi langkah ini opsional.

API key sudah di-set di `config/ai.php`, tapi lebih baik tambahkan juga di `.env`:

```env
GOOGLE_GEMINI_API_KEY=AIzaSyCMNGsLJ7RPH-2b9oK_pFjJmYHUx-KXX1k
```

Lalu update `config/ai.php` untuk hanya menggunakan env:
```php
'api_key' => env('GOOGLE_GEMINI_API_KEY'),
```

---

### STEP 2: Clear Cache (LANGSUNG LANJUT KE SINI)

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

### STEP 3: Test di Browser

1. Buka: `http://your-domain/sales-outlet-dashboard`
2. Cek apakah **AI Insight** muncul di bagian atas
3. Klik **"Refresh"** untuk test

---

## 🔧 TROUBLESHOOTING

### Error: "Class 'Google\GenerativeAI\Client' not found"
**TIDAK PERLU KHAWATIR!** Service sudah menggunakan HTTP client langsung, tidak perlu package ini.

### Error: "API key not configured"
**Solusi**: 
- Cek apakah API key sudah di `.env` atau `config/ai.php`
- Clear config cache: `php artisan config:clear`

### Error: "Failed to initialize AI client"
**Solusi**: 
- Cek koneksi internet
- Cek apakah API key valid di https://makersuite.google.com/app/apikey
- Cek log: `storage/logs/laravel.log`

### Component tidak muncul
**Solusi**: 
- Cek console browser untuk error
- Pastikan component sudah di-import di `Index.vue`
- Rebuild frontend: `npm run build` atau `npm run dev`

### Insight tidak muncul / loading terus
**Solusi**: 
- Cek network tab di browser (apakah API call berhasil?)
- Cek log: `storage/logs/laravel.log`
- Test API langsung: `GET /api/ai/insight?date_from=2025-01-01&date_to=2025-01-31`

---

## 🧪 TEST API LANGSUNG

### Via Browser:
```
http://your-domain/api/ai/insight?date_from=2025-01-01&date_to=2025-01-31
```

### Via cURL:
```bash
curl -X GET "http://your-domain/api/ai/insight?date_from=2025-01-01&date_to=2025-01-31" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Via Postman:
- Method: GET
- URL: `http://your-domain/api/ai/insight`
- Params:
  - `date_from`: 2025-01-01
  - `date_to`: 2025-01-31
- Headers:
  - `Accept`: application/json
  - `Authorization`: Bearer YOUR_TOKEN

---

## 📊 MONITOR BIAYA

1. Buka: https://makersuite.google.com/app/apikey
2. Klik pada API key yang digunakan
3. Lihat usage & billing
4. Monitor biaya harian/bulanan

**Estimasi**: Rp 5-10/bulan untuk penggunaan normal

---

## ✅ CHECKLIST

- [x] Package `google/generative-ai-php` **TIDAK PERLU** (sudah pakai HTTP client)
- [ ] API key sudah di-set di `.env` atau `config/ai.php`
- [ ] Config cache sudah di-clear
- [ ] Route sudah terdaftar (cek: `php artisan route:list | grep ai`)
- [ ] Component sudah muncul di dashboard
- [ ] Test API berhasil
- [ ] Insight muncul dengan benar

---

## 🎉 SELESAI!

Jika semua checklist sudah ✅, AI Analytics sudah siap digunakan!

**Biaya**: Rp 5-10/bulan (sangat murah!)
**Fitur**: Auto Insight setiap buka dashboard

---

## 📞 SUPPORT

Jika ada masalah:
1. Cek log: `storage/logs/laravel.log`
2. Cek browser console untuk error frontend
3. Test API langsung untuk debug
4. Cek dokumentasi: `AI_ANALYTICS_RECOMMENDATION.md`

