# 🚀 AI Analytics - Production Deployment Guide

## ✅ Code Sudah Siap untuk Production

Code sudah di-update untuk:
- **Development (Local)**: Disable SSL verification (untuk Laragon)
- **Production (Server)**: Enable SSL verification (untuk security)

Code akan otomatis detect environment dari `APP_ENV` di `.env`.

---

## 📋 Checklist Sebelum Upload ke Server

### 1. **Pastikan `.env` di Server Sudah Benar**

```env
APP_ENV=production
APP_DEBUG=false
GOOGLE_GEMINI_API_KEY=AIzaSyCMNGsLJ7RPH-2b9oK_pFjJmYHUx-KXX1k
```

### 2. **File yang Perlu Di-upload**

- ✅ `app/Services/AIAnalyticsService.php`
- ✅ `app/Http/Controllers/AIAnalyticsController.php`
- ✅ `config/ai.php`
- ✅ `routes/web.php` (route sudah ditambahkan)
- ✅ `resources/js/Pages/SalesOutletDashboard/Components/AIAnalytics.vue`
- ✅ `resources/js/Pages/SalesOutletDashboard/Index.vue` (sudah di-integrate)

### 3. **Setelah Upload**

```bash
# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild frontend (jika ada perubahan)
npm run build
# atau
npm run dev
```

---

## 🔒 SSL Certificate di Server

### **Opsi 1: Server Sudah Punya Certificate (Recommended)**

Jika server sudah punya SSL certificate yang valid, **tidak perlu setup apapun**. Code akan otomatis menggunakan SSL verification.

### **Opsi 2: Server Tidak Punya Certificate (Temporary Fix)**

Jika server tidak punya certificate dan butuh temporary fix:

1. **Download certificate:**
```bash
wget https://curl.se/ca/cacert.pem -O /path/to/cacert.pem
```

2. **Set di `php.ini`:**
```ini
curl.cainfo = "/path/to/cacert.pem"
```

3. **Atau set di `.env` (jika menggunakan custom HTTP client):**
```env
CURL_CAINFO=/path/to/cacert.pem
```

### **Opsi 3: Disable SSL Verification (TIDAK DISARANKAN untuk Production)**

**Hanya untuk testing sementara!** Edit `app/Services/AIAnalyticsService.php`:

```php
// Hapus conditional, langsung tanpa verifying
$httpClient = $httpClient->withoutVerifying();
```

**⚠️ WARNING**: Ini tidak aman untuk production!

---

## 🧪 Test di Server

### 1. **Test API Endpoint**

```bash
curl -X GET "https://your-domain.com/sales-outlet-dashboard/ai/insight?date_from=2025-01-01&date_to=2025-01-31" \
  -H "Cookie: laravel_session=YOUR_SESSION_COOKIE" \
  -H "Accept: application/json"
```

### 2. **Cek Log**

```bash
tail -f storage/logs/laravel.log | grep "AI"
```

### 3. **Test di Browser**

1. Login ke aplikasi
2. Buka: `/sales-outlet-dashboard`
3. Cek apakah AI Insight muncul
4. Klik "Refresh" untuk test

---

## 🔧 Troubleshooting di Server

### **Error: "cURL error 77"**

**Solusi**: 
- Pastikan `APP_ENV=production` di `.env`
- Atau setup SSL certificate (Opsi 2 di atas)

### **Error: "Unauthenticated"**

**Solusi**: 
- Pastikan user sudah login
- Cek session cookie
- Pastikan route di `web.php` bukan `api.php`

### **Error: "API key not configured"**

**Solusi**: 
- Pastikan `GOOGLE_GEMINI_API_KEY` ada di `.env`
- Clear config: `php artisan config:clear`

### **Error: "Quota exceeded"**

**Solusi**: 
- Setup billing di Google AI Studio (lihat `GOOGLE_GEMINI_BILLING_SETUP.md`)
- Atau tunggu reset quota (free tier: 1,500 requests/hari)

---

## 📊 Monitor Biaya di Server

1. Buka: https://makersuite.google.com/app/apikey
2. Klik pada API key
3. Lihat usage & billing
4. Monitor biaya harian/bulanan

**Estimasi**: Rp 5-10/bulan untuk penggunaan normal

---

## ✅ Checklist Production

- [ ] `.env` sudah di-set dengan `APP_ENV=production`
- [ ] `GOOGLE_GEMINI_API_KEY` sudah di-set di `.env`
- [ ] File sudah di-upload ke server
- [ ] Cache sudah di-clear
- [ ] Frontend sudah di-build (jika ada perubahan)
- [ ] Test API endpoint berhasil
- [ ] Test di browser berhasil
- [ ] SSL certificate sudah setup (jika diperlukan)
- [ ] Monitor biaya sudah di-setup

---

## 🎯 Kesimpulan

**Code sudah siap untuk production!**

- ✅ Otomatis detect environment
- ✅ SSL verification aktif di production
- ✅ SSL verification nonaktif di development
- ✅ Error handling sudah lengkap
- ✅ Logging sudah detail

**Tinggal upload file dan set `.env` di server!** 🚀

---

## 📞 Support

Jika ada masalah di server:
1. Cek log: `storage/logs/laravel.log`
2. Cek `.env` sudah benar
3. Test API endpoint langsung
4. Pastikan SSL certificate sudah setup

