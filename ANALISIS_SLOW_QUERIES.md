# 🔍 Analisis Slow Queries - Database Performance

## 🎯 **TUJUAN**

Menganalisis dan mengoptimasi slow queries yang menyebabkan server lemot di jam-jam tertentu dengan ratusan user.

---

## 📊 **LANGKAH 1: Enable Slow Query Log**

### **A. Check Apakah Slow Query Log Sudah Enabled**

```bash
# Login ke MySQL
mysql -u root -p

# Check slow query log status
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';
SHOW VARIABLES LIKE 'slow_query_log_file';
```

### **B. Enable Slow Query Log (Jika Belum)**

```sql
-- Set long_query_time (query > 1 detik dianggap slow)
SET GLOBAL long_query_time = 1;

-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';

-- Check lokasi log file
SHOW VARIABLES LIKE 'slow_query_log_file';
-- Biasanya: /var/lib/mysql/server1-slow.log atau /var/log/mysql/slow-query.log
```

### **C. Enable Slow Query Log Permanen (di my.cnf)**

```bash
# Edit MySQL config
nano /etc/my.cnf
# atau
nano /etc/mysql/my.cnf

# Tambahkan di bagian [mysqld]:
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 1
log_queries_not_using_indexes = 1

# Restart MySQL
systemctl restart mysql
# atau
systemctl restart mariadb
```

---

## 🔍 **LANGKAH 2: Check Queries yang Sedang Running**

### **A. Check Process List (Queries yang Sedang Berjalan)**

```bash
# Login MySQL dan check process list
mysql -u root -p -e "SHOW PROCESSLIST;" | head -20

# Atau dengan format yang lebih readable
mysql -u root -p -e "SHOW FULL PROCESSLIST\G" | head -50
```

### **B. Check Queries yang Lama Berjalan (> 5 detik)**

```sql
-- Login MySQL
mysql -u root -p

-- Check queries yang lama
SELECT 
    id,
    user,
    host,
    db,
    command,
    time,
    state,
    info
FROM information_schema.processlist
WHERE time > 5
ORDER BY time DESC;
```

### **C. Check Queries yang Lock Table**

```sql
-- Check queries yang lock table
SELECT 
    id,
    user,
    host,
    db,
    command,
    time,
    state,
    info
FROM information_schema.processlist
WHERE state LIKE '%lock%'
ORDER BY time DESC;
```

---

## 📋 **LANGKAH 3: Analisis Slow Query Log**

### **A. Check Slow Query Log File**

```bash
# Cari lokasi slow query log
mysql -u root -p -e "SHOW VARIABLES LIKE 'slow_query_log_file';"

# Check slow queries (top 20)
tail -100 /var/log/mysql/slow-query.log | grep -A 5 "Query_time"

# Atau pakai mysqldumpslow (jika tersedia)
mysqldumpslow -s t -t 20 /var/log/mysql/slow-query.log
```

### **B. Analisis dengan mysqldumpslow**

```bash
# Install mysqldumpslow (jika belum ada)
# Biasanya sudah include dengan MySQL

# Top 10 slow queries by time
mysqldumpslow -s t -t 10 /var/log/mysql/slow-query.log

# Top 10 slow queries by count
mysqldumpslow -s c -t 10 /var/log/mysql/slow-query.log

# Top 10 slow queries by average time
mysqldumpslow -s at -t 10 /var/log/mysql/slow-query.log
```

### **C. Analisis Manual dari Log**

```bash
# Check slow query log
tail -f /var/log/mysql/slow-query.log

# Atau check file lengkap
cat /var/log/mysql/slow-query.log | grep -A 10 "Query_time"
```

---

## 🔧 **LANGKAH 4: Optimasi Slow Queries**

### **A. Check Indexes yang Ada**

```sql
-- Login MySQL
mysql -u root -p

-- Pilih database
USE justusku_cms;

-- Check indexes untuk table tertentu
SHOW INDEX FROM nama_table;

-- Check semua indexes
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'justusku_cms'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

### **B. Check Queries Tanpa Index**

```sql
-- Check queries yang tidak pakai index
SELECT 
    TABLE_SCHEMA,
    TABLE_NAME,
    INDEX_NAME,
    CARDINALITY
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'justusku_cms'
AND INDEX_NAME = 'PRIMARY'
ORDER BY CARDINALITY DESC;
```

### **C. Analisis Query dengan EXPLAIN**

```sql
-- Untuk query yang slow, gunakan EXPLAIN
EXPLAIN SELECT * FROM table_name WHERE column = 'value';

-- Check apakah pakai index
-- Jika "type" = "ALL", berarti full table scan (SANGAT LAMBAT!)
-- Jika "type" = "ref" atau "eq_ref", berarti pakai index (BAIK)
```

---

## 📊 **LANGKAH 5: Check Table yang Sering Diakses**

### **A. Check Table Size**

```sql
-- Check ukuran table
SELECT 
    TABLE_NAME,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size (MB)',
    TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'justusku_cms'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
LIMIT 20;
```

### **B. Check Table yang Sering Di-Update**

```sql
-- Check table yang sering di-update (perlu optimasi)
SELECT 
    TABLE_SCHEMA,
    TABLE_NAME,
    UPDATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'justusku_cms'
AND UPDATE_TIME IS NOT NULL
ORDER BY UPDATE_TIME DESC
LIMIT 20;
```

---

## 🚀 **LANGKAH 6: Optimasi dengan Indexes**

### **A. Check Missing Indexes**

```sql
-- Check queries yang tidak pakai index (dari slow query log)
-- Identifikasi column yang sering di-WHERE tapi tidak ada index

-- Contoh: Jika ada query seperti ini di slow log:
-- SELECT * FROM users WHERE email = 'xxx';
-- Tapi tidak ada index di column email, maka perlu tambah index
```

### **B. Tambah Indexes**

```sql
-- Tambah index untuk column yang sering di-WHERE
CREATE INDEX idx_email ON users(email);

-- Tambah composite index untuk multiple columns
CREATE INDEX idx_user_status ON users(user_id, status);

-- Tambah index untuk column yang sering di-JOIN
CREATE INDEX idx_order_user ON orders(user_id);
```

### **C. Check Indexes yang Sudah Ada**

```bash
# Check file SQL yang sudah ada untuk indexes
cat CHECK_EXISTING_INDEXES.sql
cat CREATE_MISSING_INDEXES.sql
```

---

## 📋 **LANGKAH 7: Monitoring Real-Time**

### **A. Monitor Queries yang Sedang Berjalan**

```bash
# Script untuk monitor queries
watch -n 2 "mysql -u root -p'password' -e \"SELECT id, user, host, db, command, time, state, LEFT(info, 50) as query FROM information_schema.processlist WHERE command != 'Sleep' AND time > 0 ORDER BY time DESC;\""
```

### **B. Monitor Slow Queries Real-Time**

```bash
# Monitor slow query log real-time
tail -f /var/log/mysql/slow-query.log | grep -A 5 "Query_time"
```

---

## 🔧 **COMMAND CEPAT**

### **Check Semua Status Sekaligus**

```bash
# 1. Check slow query log status
mysql -u root -p -e "SHOW VARIABLES LIKE 'slow_query%'; SHOW VARIABLES LIKE 'long_query_time';"

# 2. Check queries yang sedang running (> 5 detik)
mysql -u root -p -e "SELECT id, user, db, command, time, state, LEFT(info, 100) as query FROM information_schema.processlist WHERE time > 5 ORDER BY time DESC;"

# 3. Check slow query log (top 10)
mysqldumpslow -s t -t 10 /var/log/mysql/slow-query.log 2>/dev/null || tail -50 /var/log/mysql/slow-query.log | grep -A 5 "Query_time"

# 4. Check table size
mysql -u root -p -e "SELECT TABLE_NAME, ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'justusku_cms' ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC LIMIT 10;"
```

---

## 📊 **EXPECTED RESULTS**

Setelah optimasi:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **Slow Queries** | Banyak | Sedikit |
| **Query Time** | > 1 detik | < 0.1 detik |
| **Full Table Scan** | Banyak | Sedikit |
| **Index Usage** | Rendah | Tinggi |

---

## ⚠️ **CATATAN PENTING**

1. **Enable slow query log dulu** sebelum analisis
2. **Monitor selama 1-2 jam** untuk dapat data yang representatif
3. **Jangan tambah index sembarangan** - terlalu banyak index juga bisa lambat
4. **Optimasi query yang paling sering dipanggil** dulu (dari slow query log)
5. **Test di staging** sebelum apply ke production

---

## 🔍 **TROUBLESHOOTING**

### **Slow Query Log Tidak Ada?**

```bash
# Check permission file
ls -la /var/log/mysql/slow-query.log

# Check MySQL error log
tail -50 /var/log/mysql/error.log

# Check apakah MySQL bisa write ke log directory
touch /var/log/mysql/test.log
rm /var/log/mysql/test.log
```

### **Tidak Bisa Enable Slow Query Log?**

```bash
# Check MySQL version
mysql --version

# Check config file
cat /etc/my.cnf | grep -A 5 "\[mysqld\]"

# Restart MySQL
systemctl restart mysql
```

---

**Mulai dengan Langkah 1: Enable Slow Query Log, lalu monitor selama 1-2 jam!** ✅
