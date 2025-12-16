# Penjelasan Pemotongan Point dengan Point Remainder

## Masalah

Ketika point digunakan untuk redeem reward atau membeli voucher, sistem perlu mempertimbangkan `point_remainder` yang menyimpan fractional points (untuk earning rate 1.5).

## Solusi yang Diimplementasikan

### 1. Helper Function: `PointEarningService::deductPoints()`

Function baru yang menangani pemotongan point dengan memperhitungkan `point_remainder`:

**Fitur:**
- Cek apakah `just_points` cukup untuk pemotongan
- Jika tidak cukup, konversi `point_remainder` >= 1.0 menjadi integer points
- Kurangi `just_points` setelah konversi (jika perlu)
- Simpan `point_remainder` yang tersisa
- Return success/error dengan detail

**Contoh:**
```php
$pointEarningService = new \App\Services\PointEarningService();
$deductResult = $pointEarningService->deductPoints($member, 100);

if (!$deductResult['success']) {
    // Handle error
}
```

### 2. Update di VoucherController

**File:** `app/Http/Controllers/Mobile/Member/VoucherController.php`

**Perubahan:**
- Update `purchase()` function untuk menggunakan `deductPoints()`
- Update check `canPurchase` untuk consider `point_remainder`
- Sebelum: `$memberPoints >= $voucher->points_required`
- Sesudah: `($memberPoints + floor($memberRemainder)) >= $voucher->points_required`

### 3. Update di RewardController

**File:** `app/Http/Controllers/Mobile/Member/RewardController.php`

**Perubahan:**
- Update `redeemReward()` function untuk menggunakan `deductPoints()`
- Update check `canRedeem` untuk consider `point_remainder`
- Update semua tempat yang mengecek `memberPoints >= points_required`

## Cara Kerja

### Contoh 1: Member Loyal dengan Remainder

**Situasi:**
- `just_points`: 0
- `point_remainder`: 1.5
- Points required: 1

**Proses:**
1. Cek `just_points` (0) < required (1) → tidak cukup
2. Cek `point_remainder` (1.5) >= 1.0 → bisa dikonversi
3. Konversi: `floor(1.5)` = 1 point
4. `just_points` = 0 + 1 = 1
5. `point_remainder` = 1.5 - 1 = 0.5
6. Kurangi: `just_points` = 1 - 1 = 0
7. **Hasil:** Point berhasil dipotong, `point_remainder` = 0.5

### Contoh 2: Member Loyal dengan Remainder

**Situasi:**
- `just_points`: 0
- `point_remainder`: 0.5
- Points required: 1

**Proses:**
1. Cek `just_points` (0) < required (1) → tidak cukup
2. Cek `point_remainder` (0.5) < 1.0 → tidak bisa dikonversi
3. **Hasil:** Error - Insufficient points

### Contoh 3: Member Loyal dengan Remainder

**Situasi:**
- `just_points`: 50
- `point_remainder`: 0.8
- Points required: 50

**Proses:**
1. Cek `just_points` (50) >= required (50) → cukup
2. Kurangi: `just_points` = 50 - 50 = 0
3. `point_remainder` tetap 0.8 (tidak berubah)
4. **Hasil:** Point berhasil dipotong, `point_remainder` = 0.8

## File yang Diubah

1. **`app/Services/PointEarningService.php`**
   - Tambah function `deductPoints()`

2. **`app/Http/Controllers/Mobile/Member/VoucherController.php`**
   - Update `purchase()` function
   - Update check `canPurchase`

3. **`app/Http/Controllers/Mobile/Member/RewardController.php`**
   - Update `redeemReward()` function
   - Update check `canRedeem` di beberapa tempat
   - Update `getMemberRewardsForPos()` function

## Testing

Untuk test, pastikan:
1. Member dengan `point_remainder` > 0 bisa redeem jika `just_points + floor(remainder)` >= required
2. Member dengan `point_remainder` < 1.0 tidak bisa redeem jika `just_points` tidak cukup
3. `point_remainder` dikonversi otomatis saat diperlukan
4. `point_remainder` tersisa dengan benar setelah konversi

## Catatan Penting

- `point_remainder` hanya dikonversi jika >= 1.0
- `point_remainder` tidak pernah dikurangi langsung (hanya dikonversi ke integer)
- `just_points` selalu integer (tidak pernah fractional)
- Semua pemotongan point sekarang menggunakan `deductPoints()` untuk konsistensi

