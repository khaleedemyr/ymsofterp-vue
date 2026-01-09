# 🔍 Cara Cek Slow Query Log

## 📊 **LANGKAH 1: Check Slow Query Log File**

### **A. Cari Lokasi Slow Query Log**

```bash
# Login MySQL
mysql -u root -p

# Check lokasi slow query log
SHOW VARIABLES LIKE 'slow_query_log_file';
```

**Output biasanya:**
```
/var/lib/mysql/YMServer-slow.log
```

### **B. Check Apakah Ada Slow Queries Baru**

```bash
# Check total slow queries yang sudah tercatat
wc -l /var/lib/mysql/YMServer-slow.log

# Check slow queries terbaru (top 10)
tail -100 /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time" | head -50

# Atau check semua slow queries
cat /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time" | head -100
```

---

## 📋 **LANGKAH 2: Analisis dengan mysqldumpslow**

### **A. Top 10 Slow Queries by Time**

```bash
# Top 10 query yang paling lama
mysqldumpslow -s t -t 10 /var/lib/mysql/YMServer-slow.log
```

### **B. Top 10 Slow Queries by Count (Paling Sering)**

```bash
# Top 10 query yang paling sering muncul
mysqldumpslow -s c -t 10 /var/lib/mysql/YMServer-slow.log
```

### **C. Top 10 Slow Queries by Average Time**

```bash
# Top 10 query dengan average time tertinggi
mysqldumpslow -s at -t 10 /var/lib/mysql/YMServer-slow.log
```

---

## 🔍 **LANGKAH 3: Check Query Delivery Orders**

### **A. Cari Query Delivery Orders di Slow Log**

```bash
# Cari query yang mengandung delivery_orders
grep -i "delivery_orders" /var/lib/mysql/YMServer-slow.log | head -20

# Atau dengan context (5 baris setelah match)
grep -i -A 5 "delivery_orders" /var/lib/mysql/YMServer-slow.log | head -50
```

### **B. Cari Query dengan Rows_examined Tinggi**

```bash
# Cari query yang examine banyak rows (> 10000)
grep "Rows_examined: [0-9][0-9][0-9][0-9][0-9]" /var/lib/mysql/YMServer-slow.log | head -20

# Atau lebih spesifik (> 100000)
grep "Rows_examined: [0-9][0-9][0-9][0-9][0-9][0-9]" /var/lib/mysql/YMServer-slow.log | head -20
```

### **C. Cari Query COUNT dengan Subquery**

```bash
# Cari query COUNT dengan subquery
grep -i "COUNT.*FROM.*SELECT" /var/lib/mysql/YMServer-slow.log | head -20
```

---

## 📊 **LANGKAH 4: Check MySQL Status**

### **A. Check Total Slow Queries**

```bash
# Login MySQL
mysql -u root -p

# Check total slow queries
SHOW STATUS LIKE 'Slow_queries';
```

**Expected:** Jumlah slow queries yang sudah tercatat sejak slow query log di-enable.

### **B. Check Queries yang Sedang Berjalan**

```sql
-- Check queries yang sedang running (> 5 detik)
SELECT 
    id,
    user,
    host,
    db,
    command,
    time,
    state,
    LEFT(info, 100) as query
FROM information_schema.processlist
WHERE time > 5
AND command != 'Sleep'
ORDER BY time DESC;
```

---

## 🔧 **LANGKAH 5: Monitor Real-Time**

### **A. Monitor Slow Query Log Real-Time**

```bash
# Monitor slow queries real-time (akan muncul query baru yang slow)
tail -f /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time"
```

**Cara pakai:**
1. Jalankan command di atas
2. Buka aplikasi dan test fitur Delivery Order
3. Jika ada query slow, akan muncul di terminal

### **B. Monitor dengan Watch (Update Setiap 5 Detik)**

```bash
# Monitor slow queries dengan update setiap 5 detik
watch -n 5 "tail -20 /var/lib/mysql/YMServer-slow.log | grep -A 3 'Query_time'"
```

---

## 📋 **COMMAND CEPAT (Copy-Paste Semua)**

```bash
# 1. Check lokasi slow query log
mysql -u root -p -e "SHOW VARIABLES LIKE 'slow_query_log_file';"

# 2. Check total slow queries
wc -l /var/lib/mysql/YMServer-slow.log

# 3. Check slow queries terbaru (top 10)
tail -100 /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time" | head -50

# 4. Analisis dengan mysqldumpslow
mysqldumpslow -s t -t 10 /var/lib/mysql/YMServer-slow.log

# 5. Cari query delivery_orders
grep -i -A 5 "delivery_orders" /var/lib/mysql/YMServer-slow.log | head -50

# 6. Cari query dengan rows_examined tinggi
grep "Rows_examined: [0-9][0-9][0-9][0-9][0-9][0-9]" /var/lib/mysql/YMServer-slow.log | head -20

# 7. Check MySQL status
mysql -u root -p -e "SHOW STATUS LIKE 'Slow_queries';"
```

---

## 🎯 **VERIFIKASI SETELAH OPTIMASI**

### **A. Sebelum Optimasi (Expected)**
- Query delivery_orders dengan `Rows_examined: 1,469,699`
- Query time: 1.18 detik
- Tidak ada WHERE clause

### **B. Sesudah Optimasi (Expected)**
- Query delivery_orders dengan `Rows_examined: < 1000` (jika filter hari ini)
- Query time: < 0.1 detik
- Ada WHERE clause dengan tanggal

### **C. Check Apakah Masih Ada Query Lambat**

```bash
# Cari query delivery_orders yang masih lambat
grep -i -B 5 -A 30 "delivery_orders" /var/lib/mysql/YMServer-slow.log | grep -A 5 "Query_time" | head -50

# Check rows_examined
grep -i -A 5 "delivery_orders" /var/lib/mysql/YMServer-slow.log | grep "Rows_examined" | head -20
```

**Jika masih ada query dengan `Rows_examined > 10000`:**
- Mungkin masih ada query lain yang perlu dioptimasi
- Atau filter belum ter-apply dengan benar

---

## 🔧 **CLEAR SLOW QUERY LOG (Jika Perlu)**

Jika ingin reset log untuk monitoring fresh:

```bash
# Backup log lama dulu
cp /var/lib/mysql/YMServer-slow.log /var/lib/mysql/YMServer-slow.log.backup.$(date +%Y%m%d)

# Clear log (kosongkan file)
> /var/lib/mysql/YMServer-slow.log

# Atau hapus dan biarkan MySQL buat baru
rm /var/lib/mysql/YMServer-slow.log
touch /var/lib/mysql/YMServer-slow.log
chown mysql:mysql /var/lib/mysql/YMServer-slow.log
chmod 640 /var/lib/mysql/YMServer-slow.log
```

**Setelah clear, test aplikasi lagi dan monitor slow query log.**

---

## 📊 **EXPECTED RESULTS SETELAH OPTIMASI**

Setelah optimasi Delivery Order:

| Metric | Sebelum | Sesudah |
|--------|---------|---------|
| **Query delivery_orders di slow log** | Ada (1.18 detik) | Tidak ada atau < 0.1 detik |
| **Rows_examined** | 1,469,699 | < 1000 |
| **Query time** | 1.18 detik | < 0.1 detik |
| **WHERE clause** | Tidak ada | Ada (tanggal) |

---

## ⚠️ **CATATAN**

1. **Slow query log akan terus bertambah** - normal jika ada query baru yang slow
2. **Monitor selama 1-2 jam** untuk dapat data yang representatif
3. **Jika masih ada query lambat** - mungkin ada query lain yang perlu dioptimasi
4. **Clear log jika perlu** - untuk monitoring fresh setelah optimasi

---

**Jalankan command di atas untuk check slow query log!** ✅
