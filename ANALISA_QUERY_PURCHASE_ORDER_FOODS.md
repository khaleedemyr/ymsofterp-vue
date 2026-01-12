# Analisa Slow Query: purchase_order_foods

## 🔴 Query yang Bermasalah

Dari monitoring dashboard, ditemukan query yang sangat lambat:

```sql
SELECT DISTINCT 'po'.'id' 
FROM 'purchase_order_foods' AS 'po' 
LEFT JOIN 'food_...' 
-- (query terpotong)
```

**Detail:**
- **Time**: 24 detik (sangat lambat!)
- **State**: Sending data
- **Command**: Execute
- **Process ID**: 3478641

---

## ⚠️ Klasifikasi Slow Query

### Kriteria Slow Query:
- **< 1 detik**: Normal ✅
- **1-5 detik**: Agak lambat ⚠️
- **5-10 detik**: Slow query 🔴
- **10-30 detik**: Sangat lambat 🔴🔴
- **> 30 detik**: Critical 🔴🔴🔴

**Query ini (24 detik) termasuk kategori "Sangat Lambat" dan perlu segera dioptimasi!**

---

## 🔍 Analisa Query

### Masalah yang Teridentifikasi:

1. **SELECT DISTINCT**
   - DISTINCT memerlukan sorting dan deduplication
   - Bisa sangat lambat pada tabel besar
   - Perlu cek apakah benar-benar diperlukan

2. **LEFT JOIN**
   - JOIN bisa lambat jika tidak ada index yang tepat
   - Perlu cek apakah ada index di foreign key

3. **Tabel purchase_order_foods**
   - Kemungkinan tabel besar
   - Perlu cek jumlah records
   - Perlu cek index yang ada

4. **State: Sending data**
   - Query sudah selesai execute, tapi masih mengirim data ke client
   - Bisa jadi hasil query sangat besar
   - Atau network lambat

---

## ✅ Langkah Optimasi

### Step 1: Analisa Query Lengkap

Jalankan query ini untuk melihat query lengkap:

```sql
-- Cek query lengkap dari process yang running
SELECT 
    id,
    user,
    db,
    command,
    time,
    state,
    info as full_query
FROM information_schema.processlist
WHERE id = 3478641;
```

### Step 2: Cek Struktur Tabel dan Index

```sql
-- Cek struktur tabel
DESCRIBE purchase_order_foods;

-- Cek index yang ada
SHOW INDEX FROM purchase_order_foods;

-- Cek jumlah records
SELECT COUNT(*) as total_records FROM purchase_order_foods;
```

### Step 3: Jalankan EXPLAIN

Setelah dapat query lengkap, jalankan EXPLAIN:

```sql
EXPLAIN SELECT DISTINCT po.id 
FROM purchase_order_foods po 
LEFT JOIN ... 
-- (query lengkap)
```

**Perhatikan:**
- `type`: Harus `ref` atau `range` (bukan `ALL`)
- `key`: Harus ada index yang digunakan
- `rows`: Harus kecil (bukan semua records)

### Step 4: Optimasi Query

Berdasarkan hasil EXPLAIN, lakukan optimasi:

#### A. Jika DISTINCT tidak diperlukan:
```sql
-- Ganti dari:
SELECT DISTINCT po.id FROM ...

-- Menjadi:
SELECT po.id FROM ...
GROUP BY po.id
-- Atau hapus DISTINCT jika tidak perlu
```

#### B. Tambahkan Index:
```sql
-- Index untuk kolom yang di-join
CREATE INDEX idx_purchase_order_foods_xxx 
ON purchase_order_foods(xxx);

-- Index untuk kolom yang di-filter
CREATE INDEX idx_purchase_order_foods_yyy 
ON purchase_order_foods(yyy);
```

#### C. Optimasi JOIN:
- Pastikan ada index di foreign key
- Pertimbangkan INNER JOIN jika LEFT JOIN tidak diperlukan
- Pastikan join condition menggunakan indexed columns

#### D. Limit Results:
```sql
-- Jika tidak perlu semua data, tambahkan LIMIT
SELECT DISTINCT po.id 
FROM purchase_order_foods po 
LEFT JOIN ...
LIMIT 100;
```

---

## 🚀 Quick Fix

### 1. Kill Process yang Stuck

Jika query sudah running terlalu lama dan tidak penting:

```sql
KILL 3478641;
```

Atau dari dashboard monitoring, klik tombol **Kill**.

### 2. Cek Query di Code

Cari di codebase query yang menggunakan `purchase_order_foods` dengan `DISTINCT`:

```bash
# Cari di Laravel
grep -r "purchase_order_foods" app/
grep -r "DISTINCT.*purchase_order_foods" app/
grep -r "PurchaseOrderFood" app/
```

### 3. Identifikasi Controller/Model

Setelah menemukan query di code, analisa:
- Apakah DISTINCT benar-benar diperlukan?
- Apakah ada filter yang bisa ditambahkan?
- Apakah bisa menggunakan pagination?

---

## 📊 Monitoring

Setelah optimasi, monitor dengan:

1. **Dashboard Monitoring**
   - Cek apakah query masih muncul di slow queries
   - Cek apakah time sudah turun

2. **Slow Query Log**
   ```sql
   SELECT * FROM mysql.slow_log 
   WHERE sql_text LIKE '%purchase_order_foods%'
   ORDER BY query_time DESC
   LIMIT 10;
   ```

3. **EXPLAIN Query**
   - Pastikan type bukan 'ALL'
   - Pastikan key menggunakan index
   - Pastikan rows kecil

---

## 🎯 Rekomendasi Prioritas

1. **CRITICAL**: Kill process yang stuck sekarang (24 detik)
2. **HIGH**: Identifikasi query lengkap dan analisa dengan EXPLAIN
3. **HIGH**: Tambahkan index yang diperlukan
4. **MEDIUM**: Optimasi query (hapus DISTINCT jika tidak perlu, tambahkan filter)
5. **LOW**: Monitor setelah optimasi

---

## ⚠️ Catatan Penting

1. **24 detik adalah sangat lambat** - perlu segera dioptimasi
2. **State "Sending data"** - query sudah selesai, tapi masih transfer data
3. **DISTINCT** - bisa sangat lambat, pertimbangkan alternatif
4. **LEFT JOIN** - pastikan ada index di foreign key

---

## 📝 Checklist

- [ ] Kill process yang stuck (jika tidak penting)
- [ ] Dapatkan query lengkap dari processlist
- [ ] Jalankan EXPLAIN untuk analisa
- [ ] Cek index yang ada di purchase_order_foods
- [ ] Identifikasi query di codebase
- [ ] Optimasi query (hapus DISTINCT, tambahkan index, dll)
- [ ] Test query setelah optimasi
- [ ] Monitor di dashboard untuk verifikasi improvement
