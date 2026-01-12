# Fix: Member Register Query yang Sangat Lambat

## 🔴 Masalah yang Ditemukan

### Query Register yang Bermasalah

Di file `app/Http/Controllers/Mobile/Member/AuthController.php` baris 65-68:

```php
// Check normalized match (remove common formatting characters)
// Get all members and check normalized mobile phones
$allMembers = MemberAppsMember::select('id', 'mobile_phone')
    ->whereNotNull('mobile_phone')
    ->where('mobile_phone', '!=', '')
    ->get(); // ⚠️ MENGAMBIL SEMUA 92RB+ DATA!

foreach ($allMembers as $existingMember) {
    $existingMobileNormalized = preg_replace('/[^0-9+]/', '', trim($existingMember->mobile_phone ?? ''));
    if ($existingMobileNormalized === $normalizedMobile && $normalizedMobile !== '') {
        // Return error...
    }
}
```

### Mengapa Ini Sangat Lambat?

1. **Load Semua Data**: `get()` mengambil **semua 92rb+ records** dari database ke memory PHP
2. **Full Table Scan**: Query `WHERE mobile_phone IS NOT NULL AND mobile_phone != ''` akan scan semua data
3. **Loop di PHP**: Normalize mobile phone dilakukan di PHP dengan loop semua data
4. **Memory Usage**: 92rb+ records × ~50 bytes = ~4.6MB per request (belum termasuk overhead)
5. **Concurrent Requests**: Jika 10 user register bersamaan = 46MB+ memory usage

### Dampak

- **Setiap register** akan load semua 92rb+ data
- **Memory usage** sangat tinggi
- **Query time** bisa 5-15 detik
- **CPU usage** tinggi karena normalize di PHP
- Dengan banyak user register bersamaan, server bisa crash

---

## ✅ Solusi

### Opsi 1: Normalize di Database (RECOMMENDED - Tapi Perlu Ubah Code)

**Idealnya**, ubah code menjadi:

```php
// Normalize mobile phone yang diinput
$normalizedMobile = preg_replace('/[^0-9+]/', '', trim($request->mobile_phone));

// Cek dengan query yang sudah normalized
$existingMobile = MemberAppsMember::where('mobile_phone', $normalizedMobile)
    ->whereNotNull('mobile_phone')
    ->where('mobile_phone', '!=', '')
    ->exists();

if ($existingMobile) {
    return response()->json([
        'success' => false,
        'message' => 'Nomor HP ini sudah terdaftar...',
        // ...
    ], 422);
}
```

**Keuntungan:**
- Hanya 1 query dengan index lookup (sangat cepat)
- Tidak load data ke memory
- Bisa menggunakan index `idx_member_apps_members_mobile_phone`

**Tapi**: Perlu pastikan mobile_phone di database sudah normalized saat insert/update.

### Opsi 2: Tambahkan Kolom normalized_mobile_phone (BEST SOLUTION)

**Tambah kolom baru** di database untuk menyimpan normalized mobile phone:

```sql
-- Tambah kolom
ALTER TABLE member_apps_members 
ADD COLUMN normalized_mobile_phone VARCHAR(20) NULL AFTER mobile_phone;

-- Buat index
CREATE INDEX idx_member_apps_members_normalized_mobile 
ON member_apps_members(normalized_mobile_phone);

-- Update data existing (normalize semua mobile_phone yang ada)
UPDATE member_apps_members 
SET normalized_mobile_phone = REGEXP_REPLACE(mobile_phone, '[^0-9+]', '')
WHERE mobile_phone IS NOT NULL AND mobile_phone != '';

-- Ubah code untuk menggunakan normalized_mobile_phone
$normalizedMobile = preg_replace('/[^0-9+]/', '', trim($request->mobile_phone));
$existingMobile = MemberAppsMember::where('normalized_mobile_phone', $normalizedMobile)->exists();
```

**Keuntungan:**
- Query sangat cepat (index lookup)
- Tidak perlu normalize di PHP
- Bisa digunakan untuk semua query yang perlu cek mobile phone

### Opsi 3: Gunakan Raw Query dengan REGEXP (TEMPORARY FIX)

Jika tidak bisa ubah code, bisa coba optimasi dengan raw query:

```php
// Normalize mobile phone yang diinput
$normalizedMobile = preg_replace('/[^0-9+]/', '', trim($request->mobile_phone));

// Gunakan raw query dengan REGEXP untuk normalize di database
$existingMobile = DB::table('member_apps_members')
    ->whereRaw("REGEXP_REPLACE(mobile_phone, '[^0-9+]', '') = ?", [$normalizedMobile])
    ->whereNotNull('mobile_phone')
    ->where('mobile_phone', '!=', '')
    ->exists();
```

**Catatan**: 
- REGEXP_REPLACE tidak bisa menggunakan index (masih akan scan)
- Tapi lebih baik daripada load semua data ke memory
- Masih lambat untuk 92rb+ data, tapi lebih efisien dari opsi saat ini

### Opsi 4: Cache Normalized Mobile Phones (TEMPORARY FIX)

Cache semua normalized mobile phones di Redis/Memcached:

```php
// Ambil dari cache dulu
$cacheKey = 'member_apps_members:normalized_mobile_phones';
$normalizedMobiles = Cache::remember($cacheKey, 3600, function () {
    return MemberAppsMember::select('mobile_phone')
        ->whereNotNull('mobile_phone')
        ->where('mobile_phone', '!=', '')
        ->get()
        ->map(function ($member) {
            return preg_replace('/[^0-9+]/', '', trim($member->mobile_phone ?? ''));
        })
        ->filter()
        ->unique()
        ->values()
        ->toArray();
});

if (in_array($normalizedMobile, $normalizedMobiles)) {
    // Mobile phone sudah terdaftar
}
```

**Keuntungan:**
- Data di-cache, tidak perlu query setiap kali
- Lebih cepat daripada query setiap request

**Kekurangan:**
- Perlu update cache saat ada member baru
- Masih perlu query pertama kali (atau saat cache expire)

---

## 📊 Perkiraan Peningkatan Performa

### Sebelum Fix:
- **Query Time**: 5-15 detik (load semua 92rb+ data)
- **Memory Usage**: ~4.6MB per request
- **CPU Usage**: Tinggi (normalize di PHP)
- **Concurrent Users**: Sangat terbatas

### Setelah Fix (Opsi 1 atau 2):
- **Query Time**: <50ms (index lookup)
- **Memory Usage**: <1KB per request
- **CPU Usage**: Normal
- **Concurrent Users**: Bisa handle ratusan user

**Peningkatan: 100-300x lebih cepat!**

---

## 🚀 Langkah Implementasi

### Jika Bisa Ubah Code (RECOMMENDED):

1. **Normalize mobile_phone saat insert/update** (pastikan semua mobile_phone di database sudah normalized)
2. **Ubah query register** menjadi query dengan WHERE clause yang tepat
3. **Test** dengan beberapa mobile phone yang sudah terdaftar

### Jika Tidak Bisa Ubah Code:

1. **Tambahkan kolom `normalized_mobile_phone`** di database
2. **Buat index** untuk kolom tersebut
3. **Update data existing** untuk populate kolom baru
4. **Minta developer** untuk ubah code menggunakan kolom baru

### Temporary Fix (Tanpa Ubah Code):

1. **Tambahkan cache** untuk normalized mobile phones
2. **Monitor** memory usage dan query time
3. **Plan** untuk implementasi fix yang lebih permanent

---

## 🔍 Monitoring

Setelah fix, monitor dengan:

```sql
-- Cek query yang masih lambat
SELECT * FROM mysql.slow_log 
WHERE sql_text LIKE '%member_apps_members%'
  AND sql_text LIKE '%mobile_phone%'
ORDER BY query_time DESC
LIMIT 10;

-- Cek memory usage di PHP-FPM
# Di server, jalankan:
ps aux | grep php-fpm | awk '{sum+=$6} END {print sum/1024 " MB"}'
```

---

## ⚠️ Catatan Penting

1. **Query register ini lebih bermasalah** daripada query login
2. **Query login sudah optimal** (index_merge, 2 rows)
3. **Query register perlu fix segera** karena impact-nya lebih besar
4. **Solusi terbaik**: Tambah kolom `normalized_mobile_phone` + ubah code

---

## 📝 Checklist

- [ ] Identifikasi apakah bisa ubah code atau tidak
- [ ] Jika bisa: Implementasi Opsi 1 atau 2
- [ ] Jika tidak bisa: Implementasi Opsi 3 atau 4 (temporary)
- [ ] Test query register dengan berbagai mobile phone format
- [ ] Monitor memory usage dan query time setelah fix
- [ ] Update dokumentasi jika ada perubahan struktur database

---

## 🎯 Kesimpulan

**Masalah utama**: Query register melakukan full table scan dan load semua 92rb+ data ke memory PHP untuk normalize mobile phone.

**Solusi terbaik**: Tambah kolom `normalized_mobile_phone` + ubah code untuk menggunakan kolom tersebut.

**Solusi temporary**: Gunakan cache atau raw query dengan REGEXP (masih lambat tapi lebih baik dari sekarang).
