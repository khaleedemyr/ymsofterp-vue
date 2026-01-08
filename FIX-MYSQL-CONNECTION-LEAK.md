# Fix MySQL Connection Leak

## 🔴 Masalah yang Teridentifikasi

Dari `SHOW PROCESSLIST`, ditemukan:
- **5+ connections idle > 1 jam** (Sleep 5270s, 4496s, 978s)
- **Connection leak** - connections tidak ditutup setelah digunakan
- **Memory waste** - setiap idle connection consume memory
- **Connection slots terpakai** - bisa mencapai max_connections

## ✅ Solusi

### 1. Kill Long-Running Idle Connections (Immediate)

```sql
-- Kill connections yang idle > 1 jam (3600 detik)
SELECT CONCAT('KILL ', id, ';') 
FROM information_schema.processlist 
WHERE command = 'Sleep' 
AND time > 3600 
AND user != 'root';

-- Execute hasil query di atas untuk kill connections
```

**Atau kill manual:**
```sql
KILL 6074615;  -- Sleep 5270s
KILL 6074644;  -- Sleep 4496s
KILL 6074991;  -- Sleep 4496s
KILL 6267259;  -- Sleep 4496s
KILL 711905;   -- Sleep 978s
```

### 2. Set MySQL Timeout (Permanent Fix)

**SSH ke database server dan edit MySQL config:**

```bash
# Edit MySQL config
nano /etc/my.cnf
# atau
nano /etc/mysql/my.cnf
```

**Tambahkan/update:**
```ini
[mysqld]
# Close idle connections setelah 5 menit (300 detik)
wait_timeout = 300
interactive_timeout = 300

# Max connections (sesuaikan dengan kebutuhan)
max_connections = 500

# Connection timeout
connect_timeout = 10
```

**Restart MySQL:**
```bash
systemctl restart mysql
# atau
systemctl restart mariadb
```

### 3. Update Laravel Database Config

**File: `config/database.php`**

Pastikan connections ditutup dengan benar. Laravel sudah handle ini, tapi pastikan:

```php
'mysql' => [
    // ... existing config ...
    
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // JANGAN gunakan persistent connection jika ada connection leak
        // PDO::ATTR_PERSISTENT => false, // Pastikan false
    ]) : [],
    
    'sticky' => true,
],
```

### 4. Monitor Connections

**Check connection count:**
```sql
-- Total connections
SHOW STATUS LIKE 'Threads_connected';

-- Max connections
SHOW VARIABLES LIKE 'max_connections';

-- Idle connections > 5 menit
SELECT COUNT(*) as idle_connections
FROM information_schema.processlist 
WHERE command = 'Sleep' 
AND time > 300;
```

**Setup monitoring script:**
```bash
# Check connections setiap 5 menit
watch -n 300 'mysql -u root -p -e "SHOW STATUS LIKE \"Threads_connected\";"'
```

## 🎯 Expected Results

Setelah fix:
- ✅ Idle connections akan auto-close setelah 5 menit
- ✅ Connection count lebih stabil
- ✅ Memory usage turun
- ✅ Tidak ada connection leak

## ⚠️ Catatan Penting

1. **Jangan kill connections yang sedang active query**
2. **Test di non-production dulu** jika mengubah wait_timeout
3. **Monitor connection count** setelah perubahan
4. **Pastikan max_connections cukup** untuk peak traffic

## 📋 Checklist

- [ ] Kill long-running idle connections (immediate)
- [ ] Set wait_timeout = 300 di MySQL config
- [ ] Set interactive_timeout = 300 di MySQL config
- [ ] Restart MySQL
- [ ] Monitor connection count
- [ ] Check apakah connection leak sudah teratasi
