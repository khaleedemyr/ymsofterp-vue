# Optimasi untuk MySQL Server Terpisah

## ⚠️ Masalah Utama

Dengan MySQL di server terpisah, faktor yang mempengaruhi performa:
1. **Network Latency** - Setiap query harus melalui network
2. **Connection Overhead** - Setup connection lebih lama
3. **PHP-FPM Processes Hang** - Processes menunggu database response
4. **CPU 100%** - Bisa karena menunggu I/O (waiting for database)

## 🔍 Diagnosis Pertama

### 1. Check Network Latency
```bash
# Ping database server
ping DATABASE_SERVER_IP

# Test connection time
time mysql -h DATABASE_SERVER_IP -u USERNAME -p -e "SELECT 1"
```

**Expected:**
- ✅ < 5ms: Excellent (local network)
- ✅ 5-50ms: Good (cloud, same region)
- ⚠️ > 50ms: Masalah! Perlu optimasi

### 2. Check Database Server Performance
```bash
# SSH ke database server
ssh database-server

# Check MySQL processes
mysql -u root -p -e "SHOW PROCESSLIST;"

# Check slow queries
mysql -u root -p -e "SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;"

# Check connections
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"
mysql -u root -p -e "SHOW VARIABLES LIKE 'max_connections';"
```

## ✅ Optimasi Database Connection

### Update `config/database.php`

Tambahkan optimasi untuk server terpisah:

```php
'mysql' => [
    'driver' => 'mysql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    
    // OPTIMASI UNTUK SERVER TERPISAH
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        // Persistent connection (reuse connections)
        PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
        // Connection timeout (5 detik)
        PDO::ATTR_TIMEOUT => 5,
        // Emulate prepares = false (better performance)
        PDO::ATTR_EMULATE_PREPARES => false,
        // Fetch mode
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]) : [],
    
    // Connection pool settings
    'sticky' => true,
],
```

### Tambahkan ke `.env`:
```env
# Database persistent connection (optional, test dulu)
DB_PERSISTENT=false
```

## 🚀 Optimasi Tambahan

### 1. Connection Pooling (Advanced)

**Opsi A: Gunakan ProxySQL** (Recommended untuk production)
- Setup ProxySQL di antara app server dan MySQL server
- Connection pooling otomatis
- Better connection management

**Opsi B: Laravel Connection Pooling** (Built-in)
- Laravel sudah handle connection pooling
- Pastikan `sticky => true` di config

### 2. Query Optimization

**Enable Query Logging:**
```php
// Di AppServiceProvider.php
public function boot()
{
    if (config('app.debug')) {
        DB::listen(function ($query) {
            if ($query->time > 100) { // Log queries > 100ms
                \Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                ]);
            }
        });
    }
}
```

**Check Slow Queries:**
```bash
# Check Laravel log
tail -f storage/logs/laravel.log | grep "Slow query"

# Check MySQL slow query log (di database server)
tail -f /var/log/mysql/slow-query.log
```

### 3. Use Caching (CRITICAL!)

**Setup Redis untuk Cache:**
```php
// Cache query results
$data = Cache::remember('key', 3600, function () {
    return DB::table('table')->get();
});

// Cache model
$user = Cache::remember("user.{$id}", 3600, function () use ($id) {
    return User::find($id);
});
```

### 4. Optimize Network Settings

**Di App Server:**
```bash
# Check network settings
sysctl net.core.somaxconn
sysctl net.ipv4.tcp_max_syn_backlog

# Optimize jika perlu
echo 'net.core.somaxconn = 1024' >> /etc/sysctl.conf
echo 'net.ipv4.tcp_max_syn_backlog = 2048' >> /etc/sysctl.conf
sysctl -p
```

## 📊 Monitoring

### Check Database Connection Time
```bash
# Test connection time
time php artisan tinker
```

```php
// Di tinker
$start = microtime(true);
DB::connection()->getPdo();
echo "Connection time: " . (microtime(true) - $start) * 1000 . "ms\n";
```

### Check Active Connections
```bash
# Check MySQL connections
mysql -h DATABASE_SERVER_IP -u root -p -e "SHOW PROCESSLIST;"
mysql -h DATABASE_SERVER_IP -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"
```

### Check Network Latency
```bash
# Continuous ping
ping -c 100 DATABASE_SERVER_IP | tail -1

# Check packet loss
ping -c 100 DATABASE_SERVER_IP | grep "packet loss"
```

## 🎯 Rekomendasi Prioritas

### Priority 1: Immediate (Lakukan Sekarang)
1. ✅ **Check network latency** ke database server
2. ✅ **Update database config** dengan timeout settings
3. ✅ **Enable query logging** untuk identify slow queries
4. ✅ **Check database server performance**

### Priority 2: Short Term (1-2 hari)
1. ✅ **Optimize slow queries** yang teridentifikasi
2. ✅ **Add database indexes** untuk queries yang sering
3. ✅ **Setup Redis caching** untuk reduce database queries
4. ✅ **Review N+1 queries** di application code

### Priority 3: Long Term (1 minggu)
1. ✅ **Setup ProxySQL** untuk connection pooling
2. ✅ **Optimize network** (private network jika cloud)
3. ✅ **Database server optimization** (jika perlu upgrade)

## ⚠️ Warning Signs

### Jika Network Latency > 50ms:
- ⚠️ Perlu optimasi network
- ⚠️ Consider move database ke server yang sama
- ⚠️ Atau setup private network

### Jika Database Server CPU 100%:
- ⚠️ Database server overload
- ⚠️ Perlu optimize queries
- ⚠️ Perlu upgrade database server

### Jika Banyak Connection Timeout:
- ⚠️ Max connections di MySQL terlalu kecil
- ⚠️ Perlu naikkan `max_connections`
- ⚠️ Atau setup connection pooling

## 📋 Checklist

- [ ] Check network latency ke database server
- [ ] Update `config/database.php` dengan timeout settings
- [ ] Enable query logging
- [ ] Check database server performance
- [ ] Identify slow queries
- [ ] Optimize slow queries
- [ ] Setup Redis caching
- [ ] Review N+1 queries
- [ ] Monitor connection count
- [ ] Monitor network latency
