# Cara Akses Dashboard Monitoring

## 🎯 URL Dashboard

Setelah setup selesai, Anda bisa akses dashboard monitoring di:

```
http://your-domain.com/monitoring/server-performance
```

Atau jika menggunakan route name:
```php
route('monitoring.server-performance')
```

---

## 📋 Fitur Dashboard

### 1. **Real-Time Monitoring**
- Auto-refresh setiap 2-30 detik (bisa diatur)
- Toggle Start/Stop monitoring
- Live indicator (hijau = aktif, abu-abu = paused)

### 2. **Stats Cards**
- **Threads Connected**: Jumlah koneksi MySQL aktif
- **Threads Running**: Jumlah query yang sedang running
- **Slow Queries**: Total slow queries sejak MySQL start
- **Total Questions**: Total queries yang sudah dieksekusi

### 3. **Tabs**

#### **Active Processes**
- Daftar semua MySQL processes yang sedang running
- Menampilkan: ID, User, DB, Command, Time, State, Query Preview
- Warna warning untuk query yang running lama:
  - **Merah**: >5 detik
  - **Kuning**: >2 detik
- Tombol **Kill** untuk process yang stuck (>5 detik)

#### **Slow Queries**
- Daftar recent slow queries dari slow query log
- Menampilkan: Query time, Rows examined, Rows sent, Query text
- Bisa pilih limit: 10, 20, atau 50 queries

#### **Summary**
- Summary slow queries yang paling sering dipanggil
- Menampilkan: Query text, Call count, Avg time, Max time, Avg rows
- Berguna untuk identifikasi query yang paling bermasalah

#### **Table Stats**
- Statistik akses per tabel
- Menampilkan: Table name, Access count, Avg time, Max time, Total rows examined
- Berguna untuk identifikasi tabel yang paling banyak diakses

---

## 🚀 Cara Menggunakan

### Step 1: Akses Dashboard

1. Login ke aplikasi
2. Akses URL: `/monitoring/server-performance`
3. Dashboard akan muncul

### Step 2: Start Monitoring

1. Klik tombol **"Start Monitoring"**
2. Pilih interval refresh (default: 5 detik)
3. Dashboard akan auto-refresh setiap interval

### Step 3: Analisa Data

1. **Cek Active Processes**:
   - Lihat query yang sedang running
   - Identifikasi query yang lama (>5 detik)
   - Kill process yang stuck jika perlu

2. **Cek Slow Queries**:
   - Lihat query yang paling lambat
   - Analisa query text untuk optimasi
   - Perhatikan rows examined (jika banyak = kemungkinan full table scan)

3. **Cek Summary**:
   - Identifikasi query yang paling sering dipanggil
   - Prioritaskan optimasi query yang sering + lambat

4. **Cek Table Stats**:
   - Identifikasi tabel yang paling banyak diakses
   - Tabel dengan access count tinggi + avg time tinggi = perlu optimasi

### Step 4: Kill Stuck Process

Jika ada process yang stuck:

1. Buka tab **Active Processes**
2. Cari process dengan time >5 detik (warna merah)
3. Klik tombol **Kill**
4. Konfirmasi
5. Process akan di-kill dan hilang dari list

---

## ⚠️ Catatan Penting

1. **Slow Query Log Harus Aktif**
   - Pastikan slow query log sudah di-enable
   - Jika tidak, tab Slow Queries dan Summary akan kosong
   - Jalankan `ENABLE_SLOW_QUERY_LOG.sql` untuk enable

2. **Permission**
   - User harus login untuk akses dashboard
   - Pastikan user punya akses ke `information_schema.processlist`
   - Pastikan user punya akses ke `mysql.slow_log`

3. **Performance Impact**
   - Monitoring akan query database setiap interval
   - Jangan set interval terlalu pendek (<2 detik) untuk menghindari overhead
   - Recommended: 5-10 detik

4. **Kill Process**
   - Hati-hati saat kill process
   - Pastikan process benar-benar stuck sebelum kill
   - Kill process bisa menyebabkan transaction rollback

---

## 🔧 Troubleshooting

### Dashboard Tidak Muncul

1. **Cek Route**:
   ```bash
   php artisan route:list | grep monitoring
   ```

2. **Cek Controller**:
   - Pastikan `MonitoringController.php` ada di `app/Http/Controllers/`
   - Pastikan method `index()` ada

3. **Cek Vue Component**:
   - Pastikan `Dashboard.vue` ada di `resources/js/Pages/Monitoring/`

### Data Tidak Muncul

1. **Cek Slow Query Log**:
   ```sql
   SHOW VARIABLES LIKE 'slow_query_log';
   ```
   - Harus `ON`

2. **Cek Permission**:
   - Pastikan user MySQL punya akses ke `information_schema`
   - Pastikan user MySQL punya akses ke `mysql.slow_log`

3. **Cek Error di Console**:
   - Buka browser console (F12)
   - Lihat error di Network tab
   - Cek error di Laravel log

### API Error

1. **Cek Laravel Log**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Cek Database Connection**:
   - Pastikan database connection OK
   - Pastikan MySQL service running

3. **Cek Permission**:
   - Pastikan user punya permission untuk query yang diperlukan

---

## 📊 Best Practices

1. **Monitor Saat Peak Hours**
   - Monitor saat banyak user aktif
   - Monitor saat scheduled tasks running
   - Monitor saat ada keluhan user

2. **Document Findings**
   - Catat query yang bermasalah
   - Catat waktu kejadian
   - Catat impact (berapa lama, berapa user affected)

3. **Prioritaskan Fix**
   - Fix query yang paling lambat + sering dipanggil
   - Fix query yang menyebabkan bottleneck
   - Fix query yang paling banyak rows examined

4. **Monitor Setelah Fix**
   - Monitor lagi setelah implementasi fix
   - Verifikasi improvement
   - Pastikan tidak ada regression

---

## 🎯 Expected Results

Setelah menggunakan dashboard monitoring, Anda akan bisa:

1. **Lihat query yang sedang running** secara real-time
2. **Identifikasi query yang lambat** saat terjadi
3. **Kill process yang stuck** dengan mudah
4. **Analisa slow queries** untuk optimasi
5. **Identifikasi tabel yang bermasalah** dengan cepat

Dengan informasi ini, Anda bisa fix masalah dengan lebih tepat dan cepat!
