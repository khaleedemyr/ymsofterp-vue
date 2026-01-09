# Troubleshooting Mobile App Lemot (10 Menit untuk Buka 1 Fitur)

## 🔴 Masalah
- Mobile app sangat lemot
- Buka 1 fitur bisa 10 menit
- PHP-FPM sudah dioptimasi (Max Children: 80) tapi masih lemot

## 🔍 Diagnosis

### 1. Check API Response Time

**Test dari server:**
```bash
# Test API endpoint yang sering digunakan mobile app
time curl -H "Authorization: Bearer TOKEN" http://localhost/api/endpoint

# Atau test dengan browser DevTools Network tab
# Check Time column untuk setiap API call
```

**Expected:**
- ✅ < 500ms: Excellent
- ✅ 500ms - 2s: Acceptable
- ⚠️ 2s - 5s: Slow
- 🔴 > 5s: **SANGAT LEMOT** - Perlu optimasi

### 2. Check Database Queries

**Enable Query Logging:**
```bash
# Check slow queries di Laravel log
tail -f storage/logs/laravel.log | grep "Slow query"

# Atau check MySQL slow query log
tail -f /var/log/mysql/slow-query.log
```

**Check N+1 Queries:**
```bash
# Install Laravel Debugbar atau Telescope untuk identify N+1 queries
# Atau check log untuk queries yang berulang
```

### 3. Check API Response Size

**Test API response size:**
```bash
curl -H "Authorization: Bearer TOKEN" http://localhost/api/endpoint | wc -c

# Atau check di browser DevTools Network tab
# Check Size column
```

**Masalah:**
- Response size > 1MB = terlalu besar
- Perlu pagination atau filter data

### 4. Check Network Latency

**Test dari mobile device:**
```bash
# Ping server dari mobile device
ping ymsofterp.com

# Test API dari mobile device
time curl -H "Authorization: Bearer TOKEN" https://ymsofterp.com/api/endpoint
```

**Masalah:**
- Network latency > 200ms = masalah
- Perlu CDN atau optimize network

## ✅ Solusi Immediate

### 1. Enable Query Logging (PRIORITAS TINGGI)

**File: `app/Providers/AppServiceProvider.php`**

Sudah ada, tapi pastikan enabled:
```php
// Pastikan LOG_SLOW_QUERIES=true di .env
// Atau set threshold lebih rendah untuk catch semua slow queries
```

**Check log:**
```bash
# Check slow queries
grep "Slow query" storage/logs/laravel.log | tail -20

# Check queries > 100ms
grep "Slow query" storage/logs/laravel.log | grep -E "[0-9]{3,}ms"
```

### 2. Optimize API Endpoints

**Check API endpoints yang sering digunakan mobile app:**
- `/api/approval/pending` - List pending approvals
- `/api/notifications` - List notifications
- `/api/attendance/data` - Attendance data
- `/api/approval/my-requests` - My requests

**Optimasi yang perlu dilakukan:**
1. **Add Eager Loading** - Prevent N+1 queries
2. **Add Pagination** - Limit response size
3. **Add Caching** - Cache frequently accessed data
4. **Add Database Indexes** - Speed up queries

### 3. Add Response Caching

**Setup Redis untuk API caching:**
```php
// Di API Controller
public function index()
{
    return Cache::remember('api.endpoint.key', 300, function () {
        return $this->getData();
    });
}
```

### 4. Optimize Database Queries

**Check dan fix N+1 queries:**
```php
// BAD: N+1 queries
$items = Item::all();
foreach ($items as $item) {
    $item->category; // Query setiap loop
}

// GOOD: Eager loading
$items = Item::with('category')->get();
```

**Add database indexes:**
```sql
-- Check missing indexes
SHOW INDEX FROM table_name;

-- Add indexes untuk columns yang sering di-query
CREATE INDEX idx_user_id ON table_name(user_id);
CREATE INDEX idx_created_at ON table_name(created_at);
```

### 5. Reduce Response Size

**Add pagination:**
```php
// Di API Controller
public function index(Request $request)
{
    $perPage = $request->get('per_page', 20);
    return Model::paginate($perPage);
}
```

**Filter data yang tidak perlu:**
```php
// Hanya return data yang diperlukan
return Model::select('id', 'name', 'email')->get();
```

## 🎯 Action Plan

### Priority 1: Immediate (Sekarang)

1. **Enable Query Logging** ✅ (Sudah ada)
2. **Check Slow Queries** - Identify bottleneck
3. **Check API Response Time** - Test dari mobile device
4. **Check Response Size** - Pastikan tidak terlalu besar

### Priority 2: Short Term (1-2 hari)

1. **Fix N+1 Queries** - Add eager loading
2. **Add Database Indexes** - Speed up queries
3. **Add Pagination** - Reduce response size
4. **Add Response Caching** - Cache API responses

### Priority 3: Long Term (1 minggu)

1. **Optimize API Endpoints** - Review semua API
2. **Setup CDN** - Reduce network latency
3. **Database Optimization** - Review semua queries
4. **Code Optimization** - Review application code

## 📊 Monitoring

### Check API Performance

```bash
# Monitor API response time
watch -n 5 'curl -w "@-" -o /dev/null -s http://localhost/api/endpoint <<< "time_total: %{time_total}\n"'

# Check database queries per request
# Install Laravel Debugbar atau Telescope
```

### Check Server Resources

```bash
# Check CPU
top

# Check Memory
free -h

# Check PHP-FPM processes
ps aux | grep php-fpm | wc -l

# Check MySQL connections
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"
```

## 🔍 Common Issues

### 1. N+1 Queries
**Symptom:** Banyak queries ke database untuk 1 request
**Solution:** Add eager loading dengan `with()`

### 2. Missing Indexes
**Symptom:** Queries lambat meskipun data sedikit
**Solution:** Add indexes untuk columns yang sering di-query

### 3. Large Response Size
**Symptom:** Response > 1MB, download lama
**Solution:** Add pagination, filter data, atau compress response

### 4. No Caching
**Symptom:** Setiap request query database
**Solution:** Add Redis caching untuk frequently accessed data

### 5. Network Latency
**Symptom:** Ping > 200ms dari mobile device
**Solution:** Setup CDN atau optimize network

## 📋 Checklist

- [ ] Enable query logging
- [ ] Check slow queries (> 100ms)
- [ ] Test API response time dari mobile device
- [ ] Check API response size
- [ ] Identify N+1 queries
- [ ] Add eager loading
- [ ] Add database indexes
- [ ] Add pagination
- [ ] Add response caching
- [ ] Monitor performance setelah optimasi

## 🚀 Quick Wins

### 1. Add Eager Loading (5 menit)
```php
// Di Controller
$data = Model::with(['relation1', 'relation2'])->get();
```

### 2. Add Pagination (5 menit)
```php
// Di Controller
return Model::paginate(20);
```

### 3. Add Caching (10 menit)
```php
// Di Controller
return Cache::remember('key', 300, function () {
    return Model::all();
});
```

### 4. Add Database Index (2 menit)
```sql
CREATE INDEX idx_column ON table_name(column_name);
```

## ⚠️ Catatan Penting

1. **Test dari mobile device** - Jangan hanya test dari server
2. **Monitor network latency** - Bisa jadi masalah network, bukan server
3. **Check response size** - Response besar = download lama
4. **Identify bottleneck** - Bisa database, network, atau code

---

**Mulai dengan check slow queries dan API response time dari mobile device!**
