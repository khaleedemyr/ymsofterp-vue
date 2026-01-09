# Quick Fix Mobile App Lemot

## 🚨 Immediate Actions (Lakukan Sekarang)

### 1. Check Slow Queries (5 menit)

```bash
# Check Laravel log untuk slow queries
tail -100 storage/logs/laravel.log | grep -i "slow\|query" | tail -20

# Atau check MySQL slow query log
tail -50 /var/log/mysql/slow-query.log
```

**Cari:**
- Queries yang > 1 detik
- Queries yang berulang-ulang (N+1)
- Queries tanpa index

### 2. Test API dari Mobile Device (5 menit)

**Gunakan browser di mobile atau Postman:**
```
GET https://ymsofterp.com/api/approval/pending
GET https://ymsofterp.com/api/notifications
GET https://ymsofterp.com/api/attendance/data
```

**Check:**
- Response time (harusnya < 2 detik)
- Response size (harusnya < 500KB)
- Number of requests (harusnya minimal)

### 3. Enable Query Logging dengan Threshold Rendah (2 menit)

**File: `.env`**
```env
LOG_SLOW_QUERIES=true
SLOW_QUERY_THRESHOLD=50  # Log queries > 50ms (bukan 100ms)
```

**Restart application:**
```bash
php artisan config:clear
```

### 4. Check Database Indexes (5 menit)

```sql
-- Check indexes untuk tables yang sering di-query
SHOW INDEX FROM users;
SHOW INDEX FROM purchase_requisitions;
SHOW INDEX FROM approvals;
SHOW INDEX FROM notifications;

-- Check slow queries tanpa index
SELECT * FROM mysql.slow_log 
WHERE sql_text NOT LIKE '%INDEX%' 
ORDER BY start_time DESC 
LIMIT 10;
```

## 🔧 Quick Fixes (15-30 menit)

### Fix 1: Add Eager Loading di API Controllers

**File: `app/Http/Controllers/ApprovalController.php`**

```php
// BAD
public function getPendingApprovals()
{
    $approvals = Approval::where('status', 'pending')->get();
    return $approvals; // N+1 queries untuk relationships
}

// GOOD
public function getPendingApprovals()
{
    $approvals = Approval::with([
        'user',
        'approver',
        'purchaseRequisition.category',
        'purchaseRequisition.outlet'
    ])->where('status', 'pending')->get();
    return $approvals;
}
```

### Fix 2: Add Pagination

```php
// BAD
public function index()
{
    return Model::all(); // Return semua data
}

// GOOD
public function index(Request $request)
{
    $perPage = $request->get('per_page', 20);
    return Model::paginate($perPage); // Return hanya 20 items
}
```

### Fix 3: Add Response Caching

```php
// Di Controller
public function index()
{
    return Cache::remember('api.approvals.pending', 60, function () {
        return Approval::with(['user', 'approver'])
            ->where('status', 'pending')
            ->paginate(20);
    });
}
```

### Fix 4: Add Database Indexes

```sql
-- Add indexes untuk columns yang sering di-query
CREATE INDEX idx_status ON approvals(status);
CREATE INDEX idx_user_id ON approvals(user_id);
CREATE INDEX idx_created_at ON approvals(created_at);
CREATE INDEX idx_status_created ON approvals(status, created_at);
```

## 📊 Monitoring Commands

```bash
# Monitor API response time
watch -n 5 'time curl -s http://localhost/api/approval/pending > /dev/null'

# Monitor slow queries real-time
tail -f storage/logs/laravel.log | grep "Slow query"

# Monitor PHP-FPM processes
watch -n 5 'ps aux | grep php-fpm | wc -l'

# Monitor MySQL connections
watch -n 5 'mysql -u root -p -e "SHOW STATUS LIKE \"Threads_connected\";"'
```

## 🎯 Expected Results

Setelah quick fixes:
- ✅ API response time: < 2 detik (dari < 10 menit)
- ✅ Database queries: < 10 queries per request
- ✅ Response size: < 500KB
- ✅ Mobile app: Buka fitur < 5 detik

## ⚠️ Jika Masih Lemot

1. **Check network latency** dari mobile device ke server
2. **Check apakah ada background jobs** yang running
3. **Check database server performance** (CPU, memory, disk I/O)
4. **Check apakah ada infinite loops** atau memory leaks
5. **Consider upgrade server** jika semua sudah dioptimasi

---

**Mulai dengan check slow queries dan test API dari mobile device!**
