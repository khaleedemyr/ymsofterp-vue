# 📊 Monitor Slow Queries - Langkah Selanjutnya

## ✅ **STATUS SAAT INI**

- ✅ Slow query log: **ON**
- ✅ Long query time: **1 detik**
- ✅ Log file: `/var/lib/mysql/YMServer-slow.log`

---

## 🔍 **LANGKAH 1: Check Slow Queries yang Sudah Tercatat**

### **A. Check Slow Query Log File**

```bash
# Check apakah file log sudah ada
ls -lh /var/lib/mysql/YMServer-slow.log

# Check slow queries yang sudah tercatat
tail -50 /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time"

# Atau check semua slow queries
cat /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time" | head -50
```

### **B. Analisis dengan mysqldumpslow (Jika Tersedia)**

```bash
# Top 10 slow queries by time
mysqldumpslow -s t -t 10 /var/lib/mysql/YMServer-slow.log

# Top 10 slow queries by count (paling sering dipanggil)
mysqldumpslow -s c -t 10 /var/lib/mysql/YMServer-slow.log

# Top 10 slow queries by average time
mysqldumpslow -s at -t 10 /var/lib/mysql/YMServer-slow.log
```

### **C. Monitor Real-Time**

```bash
# Monitor slow queries real-time (akan muncul query baru yang slow)
tail -f /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time"
```

---

## 📋 **LANGKAH 2: Enable Permanent (Agar Tidak Hilang Setelah Restart)**

### **A. Edit MySQL Config File**

```bash
# Cari file config MySQL
find /etc -name "my.cnf" 2>/dev/null
# atau
find /etc -name "my.ini" 2>/dev/null
# atau
ls -la /etc/my.cnf
ls -la /etc/mysql/my.cnf

# Edit file config
nano /etc/my.cnf
# atau
nano /etc/mysql/my.cnf
```

### **B. Tambahkan Setting di [mysqld] Section**

```ini
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/lib/mysql/YMServer-slow.log
long_query_time = 1
log_queries_not_using_indexes = 1
```

**Catatan:** 
- Jika section `[mysqld]` sudah ada, tambahkan setting di bawahnya
- Jika belum ada, tambahkan section baru

### **C. Restart MySQL**

```bash
# Restart MySQL
systemctl restart mysql
# atau
systemctl restart mariadb

# Verifikasi setting masih aktif
mysql -u root -p -e "SHOW VARIABLES LIKE 'slow_query%'; SHOW VARIABLES LIKE 'long_query_time';"
```

---

## 🔍 **LANGKAH 3: Monitor Selama 1-2 Jam**

### **A. Biarkan Slow Query Log Berjalan**

Biarkan aplikasi berjalan normal selama 1-2 jam (terutama di jam sibuk), lalu analisis log.

### **B. Check Slow Queries Setelah 1-2 Jam**

```bash
# Check total slow queries
mysql -u root -p -e "SHOW STATUS LIKE 'Slow_queries';"

# Check slow query log
tail -200 /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time"

# Analisis dengan mysqldumpslow
mysqldumpslow -s t -t 20 /var/lib/mysql/YMServer-slow.log
```

---

## 📊 **LANGKAH 4: Analisis Slow Queries**

### **A. Identifikasi Query yang Paling Sering Slow**

```bash
# Query yang paling sering muncul (count)
mysqldumpslow -s c -t 10 /var/lib/mysql/YMServer-slow.log

# Query yang paling lama (time)
mysqldumpslow -s t -t 10 /var/lib/mysql/YMServer-slow.log
```

### **B. Check Query yang Tidak Pakai Index**

```bash
# Check log untuk query yang tidak pakai index
grep -A 10 "no good index" /var/lib/mysql/YMServer-slow.log

# Atau check semua query yang tidak pakai index
grep -B 5 "no good index" /var/lib/mysql/YMServer-slow.log | grep "SELECT\|UPDATE\|DELETE\|INSERT"
```

### **C. Identifikasi Table yang Sering Slow**

```bash
# Extract table names dari slow query log
grep -o "FROM \`[^`]*\`" /var/lib/mysql/YMServer-slow.log | sort | uniq -c | sort -rn | head -20

# Atau
grep -o "JOIN \`[^`]*\`" /var/lib/mysql/YMServer-slow.log | sort | uniq -c | sort -rn | head -20
```

---

## 🔧 **LANGKAH 5: Optimasi Berdasarkan Slow Queries**

### **A. Untuk Setiap Query yang Slow:**

1. **Copy query dari slow log**
2. **Gunakan EXPLAIN untuk analisis:**
   ```sql
   EXPLAIN SELECT ... (query dari slow log);
   ```
3. **Check apakah pakai index:**
   - Jika `type = ALL` → Full table scan (SANGAT LAMBAT!)
   - Jika `type = ref` atau `eq_ref` → Pakai index (BAIK)
4. **Tambah index jika perlu:**
   ```sql
   CREATE INDEX idx_column_name ON table_name(column_name);
   ```

### **B. Check Indexes yang Sudah Ada**

```bash
# Check file SQL yang sudah ada untuk indexes
cat CHECK_EXISTING_INDEXES.sql
cat CREATE_MISSING_INDEXES.sql
```

---

## 📋 **COMMAND CEPAT**

### **Check Slow Queries Sekarang**

```bash
# 1. Check apakah ada slow queries yang sudah tercatat
wc -l /var/lib/mysql/YMServer-slow.log

# 2. Check slow queries (top 10)
tail -100 /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time" | head -50

# 3. Check MySQL status
mysql -u root -p -e "SHOW STATUS LIKE 'Slow_queries';"
```

### **Monitor Real-Time**

```bash
# Monitor slow queries real-time
watch -n 5 "tail -20 /var/lib/mysql/YMServer-slow.log | grep -A 3 'Query_time'"

# Atau
tail -f /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time"
```

---

## ⚠️ **CATATAN PENTING**

1. **File log akan terus membesar** - pertimbangkan rotate log secara berkala
2. **Monitor selama 1-2 jam** untuk dapat data yang representatif
3. **Fokus ke query yang paling sering dipanggil** (bukan hanya yang paling lama)
4. **Jangan tambah index sembarangan** - terlalu banyak index juga bisa lambat
5. **Test di staging** sebelum apply optimasi ke production

---

## 🔄 **ROTATE SLOW QUERY LOG (Optional)**

Jika file log terlalu besar:

```bash
# Rotate log (backup dan kosongkan)
mv /var/lib/mysql/YMServer-slow.log /var/lib/mysql/YMServer-slow.log.$(date +%Y%m%d)
touch /var/lib/mysql/YMServer-slow.log
chown mysql:mysql /var/lib/mysql/YMServer-slow.log
chmod 640 /var/lib/mysql/YMServer-slow.log

# Atau setup logrotate
nano /etc/logrotate.d/mysql-slow
```

Isi file logrotate:
```
/var/lib/mysql/YMServer-slow.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 640 mysql mysql
}
```

---

## 📊 **EXPECTED RESULTS**

Setelah monitor 1-2 jam:

1. **Slow queries akan tercatat** di log file
2. **Identifikasi query yang paling sering slow**
3. **Analisis query dengan EXPLAIN**
4. **Tambah index untuk optimasi**
5. **Monitor lagi untuk verifikasi**

---

**Langkah selanjutnya:**
1. ✅ Enable slow query log (SUDAH)
2. ⏳ Monitor selama 1-2 jam
3. ⏳ Analisis slow queries
4. ⏳ Optimasi dengan indexes

**Biarkan slow query log berjalan selama 1-2 jam, lalu analisis hasilnya!** ✅
