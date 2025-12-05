# Registration Bonus Point - Member Baru

## Overview
Member baru yang melakukan registrasi akan **otomatis mendapatkan 100 point** sebagai bonus registrasi.

## Implementasi

### 1. Kode yang Sudah Ada
Bonus point 100 sudah diimplementasikan di:
- **File**: `app/Http/Controllers/Mobile/Member/AuthController.php`
- **Method**: `register()`
- **Line**: 67-83

```php
// Award registration bonus points (100 points)
try {
    $pointEarningService = app(\App\Services\PointEarningService::class);
    $pointEarningService->earnBonusPoints(
        $member->id,
        'registration',
        null, // Use default 100 points
        null, // Use default validity (1 year)
        $member->member_id // Reference ID
    );
} catch (\Exception $e) {
    // Log error but don't fail registration
    \Log::error('Failed to award registration bonus points', [
        'member_id' => $member->id,
        'error' => $e->getMessage()
    ]);
}
```

### 2. Konfigurasi Bonus Point
Default point amount untuk registration adalah **100 points** dengan validity **365 hari (1 tahun)**.

**File**: `app/Services/PointEarningService.php`
**Method**: `getBonusConfig()`

```php
'registration' => [
    'points' => 100,
    'validity_days' => 365, // 1 year (same as regular points)
],
```

## Database Enum

### Tabel: `member_apps_point_transactions`

#### 1. Enum `transaction_type`
**Current Values**: `'earn', 'redeem', 'expired', 'bonus', 'adjustment'`

#### 2. Enum `channel`
**Current Values**: `'dine-in', 'take-away', 'delivery-restaurant', 'gift-voucher', 'e-commerce', 'campaign', 'registration', 'birthday', 'referral', 'redemption', 'adjustment'`

## Query Alternatif untuk Mengubah Enum

### ⚠️ PENTING: Backup Database Sebelum Menjalankan Query!

### 1. Menambah Value Baru ke Enum `transaction_type`

**Contoh**: Menambah value `'transfer'` ke enum `transaction_type`

```sql
-- Method 1: Menggunakan MODIFY COLUMN (Recommended)
ALTER TABLE `member_apps_point_transactions` 
MODIFY COLUMN `transaction_type` 
ENUM('earn', 'redeem', 'expired', 'bonus', 'adjustment', 'transfer') 
NOT NULL;

-- Method 2: Menggunakan ALTER COLUMN (Alternative)
ALTER TABLE `member_apps_point_transactions` 
ALTER COLUMN `transaction_type` 
SET DEFAULT 'earn',
MODIFY COLUMN `transaction_type` 
ENUM('earn', 'redeem', 'expired', 'bonus', 'adjustment', 'transfer') 
NOT NULL;
```

### 2. Menambah Value Baru ke Enum `channel`

**Contoh**: Menambah value `'online-order'` ke enum `channel`

```sql
-- Method 1: Menggunakan MODIFY COLUMN (Recommended)
ALTER TABLE `member_apps_point_transactions` 
MODIFY COLUMN `channel` 
ENUM('dine-in', 'take-away', 'delivery-restaurant', 'gift-voucher', 'e-commerce', 'campaign', 'registration', 'birthday', 'referral', 'redemption', 'adjustment', 'online-order') 
NULL DEFAULT NULL;

-- Method 2: Menggunakan ALTER COLUMN (Alternative)
ALTER TABLE `member_apps_point_transactions` 
ALTER COLUMN `channel` 
DROP DEFAULT,
MODIFY COLUMN `channel` 
ENUM('dine-in', 'take-away', 'delivery-restaurant', 'gift-voucher', 'e-commerce', 'campaign', 'registration', 'birthday', 'referral', 'redemption', 'adjustment', 'online-order') 
NULL DEFAULT NULL;
```

### 3. Menghapus Value dari Enum (HATI-HATI!)

**⚠️ WARNING**: Menghapus value dari enum bisa menyebabkan error jika ada data yang menggunakan value tersebut!

**Langkah-langkah**:
1. **Cek data yang menggunakan value tersebut**:
```sql
SELECT COUNT(*) 
FROM `member_apps_point_transactions` 
WHERE `transaction_type` = 'value_yang_akan_dihapus';
-- atau
SELECT COUNT(*) 
FROM `member_apps_point_transactions` 
WHERE `channel` = 'value_yang_akan_dihapus';
```

2. **Update data yang menggunakan value tersebut** (jika ada):
```sql
UPDATE `member_apps_point_transactions` 
SET `transaction_type` = 'bonus' 
WHERE `transaction_type` = 'value_yang_akan_dihapus';
-- atau
UPDATE `member_apps_point_transactions` 
SET `channel` = 'campaign' 
WHERE `channel` = 'value_yang_akan_dihapus';
```

3. **Hapus value dari enum**:
```sql
ALTER TABLE `member_apps_point_transactions` 
MODIFY COLUMN `transaction_type` 
ENUM('earn', 'redeem', 'expired', 'bonus', 'adjustment') 
NOT NULL;
-- atau
ALTER TABLE `member_apps_point_transactions` 
MODIFY COLUMN `channel` 
ENUM('dine-in', 'take-away', 'delivery-restaurant', 'gift-voucher', 'e-commerce', 'campaign', 'registration', 'birthday', 'referral', 'redemption', 'adjustment') 
NULL DEFAULT NULL;
```

### 4. Mengubah Urutan Enum Values

**Catatan**: Urutan enum values tidak mempengaruhi fungsi, tapi bisa mempengaruhi tampilan di beberapa tools.

```sql
-- Urutkan ulang enum values (contoh: pindahkan 'bonus' ke posisi pertama)
ALTER TABLE `member_apps_point_transactions` 
MODIFY COLUMN `transaction_type` 
ENUM('bonus', 'earn', 'redeem', 'expired', 'adjustment') 
NOT NULL;
```

### 5. Query untuk Cek Struktur Enum Saat Ini

```sql
-- Cek struktur enum transaction_type
SHOW COLUMNS FROM `member_apps_point_transactions` 
WHERE Field = 'transaction_type';

-- Cek struktur enum channel
SHOW COLUMNS FROM `member_apps_point_transactions` 
WHERE Field = 'channel';

-- Atau menggunakan query yang lebih detail
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'member_apps_point_transactions'
    AND COLUMN_NAME IN ('transaction_type', 'channel');
```

## Testing

### Test Registrasi Member Baru
1. Registrasi member baru melalui API endpoint: `POST /api/mobile/member/auth/register`
2. Cek di database:
   ```sql
   SELECT * FROM `member_apps_point_transactions` 
   WHERE `member_id` = [member_id] 
   AND `transaction_type` = 'bonus' 
   AND `channel` = 'registration';
   ```
3. Pastikan:
   - `point_amount` = 100
   - `transaction_type` = 'bonus'
   - `channel` = 'registration'
   - `expires_at` = 1 tahun dari tanggal registrasi

## Troubleshooting

### Problem: Bonus point tidak diberikan saat registrasi
**Solution**: 
1. Cek log error di `storage/logs/laravel.log`
2. Pastikan `PointEarningService` berfungsi dengan baik
3. Pastikan tidak ada error di database constraint

### Problem: Error saat mengubah enum
**Solution**:
1. Pastikan sudah backup database
2. Pastikan tidak ada data yang menggunakan value yang akan dihapus
3. Pastikan query syntax benar
4. Cek apakah ada foreign key constraint yang terpengaruh

## Catatan Penting

1. **Backup Database**: Selalu backup database sebelum mengubah enum
2. **Test di Staging**: Test perubahan enum di staging environment terlebih dahulu
3. **Downtime**: Perubahan enum bisa menyebabkan table lock, lakukan saat low traffic
4. **Data Consistency**: Pastikan tidak ada data yang menggunakan value yang akan dihapus
5. **Application Code**: Update application code jika menambah/mengubah enum values

