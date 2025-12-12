# Fix: Push Notification Terkirim Berulang

## Masalah
Push notification ke web dikirim berulang dengan notifikasi yang sama.

## Penyebab Kemungkinan

1. **Multiple Device Tokens**: User memiliki beberapa device tokens (browser/tab berbeda)
   - Setiap device token akan menerima notification terpisah
   - Ini behavior yang benar (satu notification per device)

2. **Duplicate Device Tokens**: Ada duplikasi device token yang sama di database
   - Token yang sama terdaftar beberapa kali
   - Akan menyebabkan notification terkirim berulang

3. **Observer Dipanggil Berulang**: Observer terpicu beberapa kali untuk notification yang sama
   - Mungkin karena event dipanggil beberapa kali

## Perbaikan yang Sudah Dilakukan

1. ✅ **Deduplication Device Tokens**:
   - Web device tokens: `->unique()` untuk remove duplicates
   - Employee device tokens: `->unique('device_token')` untuk remove duplicates

2. ✅ **Protection dari Duplicate Processing**:
   - Track processed notifications untuk mencegah observer dipanggil berulang
   - Skip jika notification sudah diproses

3. ✅ **Enhanced Logging**:
   - Log device token count dan preview untuk debugging

## Cek Duplikasi di Database

Jalankan query untuk cek apakah ada duplikasi:

```sql
-- Cek duplicate web device tokens
SELECT 
    user_id,
    device_token,
    COUNT(*) as duplicate_count
FROM web_device_tokens
WHERE is_active = 1
GROUP BY user_id, device_token
HAVING COUNT(*) > 1;

-- Cek total vs unique tokens per user
SELECT 
    user_id,
    COUNT(*) as total_tokens,
    COUNT(DISTINCT device_token) as unique_tokens
FROM web_device_tokens
WHERE is_active = 1
GROUP BY user_id
HAVING COUNT(*) != COUNT(DISTINCT device_token);
```

## Cleanup Duplikasi (Jika Ada)

Jika ada duplikasi, jalankan query cleanup (HATI-HATI - backup dulu!):

```sql
-- Backup dulu!
CREATE TABLE web_device_tokens_backup AS SELECT * FROM web_device_tokens;

-- Cleanup duplicate web device tokens (keep the latest one)
DELETE w1 FROM web_device_tokens w1
INNER JOIN web_device_tokens w2 
WHERE w1.id < w2.id 
AND w1.user_id = w2.user_id 
AND w1.device_token = w2.device_token
AND w1.is_active = 1 
AND w2.is_active = 1;
```

## Catatan Penting

1. **Multiple Browsers/Tabs adalah Normal**:
   - Jika user buka web di Chrome, Firefox, dan Edge, akan ada 3 device tokens
   - Setiap browser akan menerima notification (ini benar)
   - Jika user buka beberapa tab di browser yang sama, biasanya token sama (tidak duplikat)

2. **Deduplication Sudah Ditambahkan**:
   - Jika ada token yang sama, hanya akan dikirim sekali
   - Protection dari duplicate processing juga sudah ditambahkan

3. **Jika Masih Berulang**:
   - Cek apakah notification dibuat beberapa kali (bukan device token)
   - Cek log untuk melihat berapa kali observer dipanggil
   - Cek apakah ada multiple notification records dengan content yang sama

## Testing

Setelah fix:
1. Test dengan membuat notification baru
2. Cek log: "NotificationObserver: Sending FCM notification" - harus hanya muncul sekali per notification
3. Cek apakah notification masih terkirim berulang

Jika masih berulang, kemungkinan:
- Notification dibuat beberapa kali (bukan masalah device token)
- Ada multiple notification records dengan content yang sama

