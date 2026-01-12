# Panduan Lengkap: Cek dan Analisa Slow Query MySQL

## 🔍 Cara Cek Slow Query

### Metode 1: Menggunakan Slow Query Log (Recommended)

#### Step 1: Enable Slow Query Log
```sql
-- Login ke MySQL
mysql -u root -p

-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';

-- Set threshold (query > 1 detik akan di-log)
SET GLOBAL long_query_time = 1;

-- Log queries yang tidak menggunakan index
SET GLOBAL log_queries_not_using_indexes = 'ON';

-- Cek lokasi file log
SHOW VARIABLES LIKE 'slow_query_log_file';
```

#### Step 2: Cek File Log
```bash
# Linux
tail -f /var/log/mysql/slow-query.log

# Windows PowerShell
Get-Content -Path "C:\path\to\slow-query.log" -Tail 50 -Wait

# Atau gunakan notepad/editor untuk buka file
```

#### Step 3: Analisa Slow Query Log
```bash
# Install mysqldumpslow (jika belum ada)
# Linux: biasanya sudah included dengan MySQL
# Windows: download dari MySQL website

# Analisa slow query log
mysqldumpslow /var/log/mysql/slow-query.log

# Top 10 slowest queries
mysqldumpslow -t 10 /var/log/mysql/slow-query.log

# Queries dengan execution count tertinggi
mysqldumpslow -s c -t 10 /var/log/mysql/slow-query.log

# Queries dengan average time tertinggi
mysqldumpslow -s at -t 10 /var/log/mysql/slow-query.log
```

### Metode 2: Menggunakan Performance Schema (Jika Enabled)

```sql
-- Cek apakah performance_schema enabled
SHOW VARIABLES LIKE 'performance_schema';

-- Top 20 slowest queries
SELECT 
    DIGEST_TEXT as query,
    COUNT_STAR as execution_count,
    SUM_TIMER_WAIT/1000000000000 as total_time_seconds,
    AVG_TIMER_WAIT/1000000000000 as avg_time_seconds,
    MAX_TIMER_WAIT/1000000000000 as max_time_seconds,
    SUM_ROWS_EXAMINED as total_rows_examined,
    SUM_ROWS_SENT as total_rows_sent
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
  AND AVG_TIMER_WAIT/1000000000000 > 1
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 20;
```

### Metode 3: Menggunakan SHOW PROCESSLIST (Real-time)

```sql
-- Cek queries yang sedang berjalan
SHOW PROCESSLIST;

-- Cek queries yang lama (> 5 detik)
SELECT 
    ID,
    USER,
    HOST,
    DB,
    COMMAND,
    TIME,
    STATE,
    LEFT(INFO, 200) as QUERY
FROM information_schema.PROCESSLIST
WHERE COMMAND != 'Sleep'
  AND TIME > 5
ORDER BY TIME DESC;
```

## 📊 Analisa Slow Query

### 1. Identifikasi Query yang Lambat
```sql
-- Query yang paling lambat (average time)
SELECT 
    DIGEST_TEXT as query,
    COUNT_STAR as execution_count,
    AVG_TIMER_WAIT/1000000000000 as avg_time_seconds,
    MAX_TIMER_WAIT/1000000000000 as max_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 10;
```

### 2. Identifikasi Query yang Sering Dieksekusi
```sql
-- Query yang paling sering dieksekusi
SELECT 
    DIGEST_TEXT as query,
    COUNT_STAR as execution_count,
    AVG_TIMER_WAIT/1000000000000 as avg_time_seconds,
    SUM_TIMER_WAIT/1000000000000 as total_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
ORDER BY COUNT_STAR DESC
LIMIT 10;
```

### 3. Identifikasi Query yang Scan Banyak Rows
```sql
-- Query yang scan banyak rows (tidak efisien)
SELECT 
    DIGEST_TEXT as query,
    COUNT_STAR as execution_count,
    SUM_ROWS_EXAMINED as total_rows_examined,
    SUM_ROWS_SENT as total_rows_sent,
    (SUM_ROWS_EXAMINED / NULLIF(SUM_ROWS_SENT, 0)) as rows_examined_per_row_sent,
    AVG_TIMER_WAIT/1000000000000 as avg_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
  AND SUM_ROWS_EXAMINED > 10000
ORDER BY SUM_ROWS_EXAMINED DESC
LIMIT 10;
```

### 4. Analisa Query dengan EXPLAIN
```sql
-- Copy query dari slow query log, lalu jalankan EXPLAIN
EXPLAIN SELECT ... -- paste query di sini

-- Atau untuk query yang kompleks
EXPLAIN FORMAT=JSON SELECT ... -- untuk detail lebih lengkap
```

## 🛠️ Tools untuk Analisa Slow Query

### 1. mysqldumpslow (Command Line)
```bash
# Install (jika belum ada)
# Linux: biasanya sudah included
# Windows: download MySQL utilities

# Analisa slow query log
mysqldumpslow slow-query.log

# Options:
# -s: sort by (c=count, t=time, at=avg time, l=lock time, r=rows)
# -t: top N queries
# -g: grep pattern
# -a: don't abstract numbers

# Contoh:
mysqldumpslow -s at -t 10 slow-query.log  # Top 10 by avg time
mysqldumpslow -s c -t 10 slow-query.log   # Top 10 by count
mysqldumpslow -g "SELECT" slow-query.log  # Hanya SELECT queries
```

### 2. pt-query-digest (Percona Toolkit)
```bash
# Install Percona Toolkit
# Linux: yum install percona-toolkit
# Windows: download dari percona.com

# Analisa slow query log
pt-query-digest slow-query.log

# Output ke file
pt-query-digest slow-query.log > analysis.txt
```

### 3. MySQL Workbench (GUI)
- Buka MySQL Workbench
- Go to Performance → Performance Reports
- Pilih "Top Queries by Execution Time" atau "Top Queries by Frequency"

## 🔧 Optimasi Berdasarkan Slow Query

### 1. Tambahkan Index
```sql
-- Jika query scan banyak rows, tambahkan index
-- Contoh: query dengan WHERE clause
CREATE INDEX idx_column_name ON table_name(column_name);

-- Composite index untuk multiple columns
CREATE INDEX idx_col1_col2 ON table_name(column1, column2);
```

### 2. Optimasi Query
```sql
-- Gunakan EXPLAIN untuk melihat query plan
EXPLAIN SELECT ...;

-- Cek apakah menggunakan index
-- type: ALL = full table scan (BAD)
-- type: index, range, ref = menggunakan index (GOOD)

-- Optimasi tips:
-- 1. Gunakan LIMIT untuk membatasi hasil
-- 2. Hindari SELECT * (pilih kolom yang diperlukan saja)
-- 3. Gunakan WHERE clause dengan indexed columns
-- 4. Hindari functions di WHERE clause
-- 5. Gunakan JOIN yang efisien
```

### 3. Optimasi Laravel Code
```php
// BAD: N+1 Query
$users = User::all();
foreach ($users as $user) {
    echo $user->profile->name; // Query database setiap loop!
}

// GOOD: Eager Loading
$users = User::with('profile')->get();
foreach ($users as $user) {
    echo $user->profile->name; // No additional query
}

// BAD: Query tanpa index
User::where('email', 'like', '%@gmail.com')->get();

// GOOD: Query dengan index
User::where('email', 'like', 'user%@gmail.com')->get(); // Jika ada index prefix

// BAD: Query tanpa limit
User::all(); // Load semua data

// GOOD: Query dengan pagination
User::paginate(50); // Load 50 per page
```

## 📝 Checklist Troubleshooting Slow Query

- [ ] Enable slow query log
- [ ] Set long_query_time yang sesuai (1 detik untuk production)
- [ ] Monitor slow query log file size
- [ ] Analisa top 10 slowest queries
- [ ] Analisa top 10 most executed queries
- [ ] Identifikasi queries yang scan banyak rows
- [ ] Gunakan EXPLAIN untuk setiap slow query
- [ ] Tambahkan index yang diperlukan
- [ ] Optimasi query yang tidak efisien
- [ ] Optimasi Laravel code (eager loading, pagination)
- [ ] Monitor hasil setelah optimasi

## ⚠️ Catatan Penting

1. **Slow Query Log File Size**
   - File log akan terus bertambah
   - Setup log rotation untuk mencegah disk penuh
   - Monitor ukuran file secara berkala

2. **Performance Impact**
   - Slow query log sedikit impact performance
   - Performance schema lebih ringan tapi perlu enabled
   - Monitor impact setelah enable

3. **Security**
   - Slow query log bisa berisi sensitive data
   - Pastikan file log tidak accessible oleh public
   - Rotate dan archive log files secara berkala

4. **Permanent Settings**
   - Settings dengan SET GLOBAL hanya aktif sampai restart
   - Untuk permanent, edit my.cnf atau my.ini:
   ```ini
   [mysqld]
   slow_query_log = 1
   slow_query_log_file = /var/log/mysql/slow-query.log
   long_query_time = 1
   log_queries_not_using_indexes = 1
   ```

## 🚀 Quick Start

```sql
-- 1. Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
SET GLOBAL log_queries_not_using_indexes = 'ON';

-- 2. Tunggu beberapa saat (biarkan aplikasi berjalan)

-- 3. Cek slow queries
SELECT 
    DIGEST_TEXT as query,
    COUNT_STAR as execution_count,
    AVG_TIMER_WAIT/1000000000000 as avg_time_seconds
FROM performance_schema.events_statements_summary_by_digest
WHERE DIGEST_TEXT IS NOT NULL
  AND AVG_TIMER_WAIT/1000000000000 > 1
ORDER BY AVG_TIMER_WAIT DESC
LIMIT 20;
```
