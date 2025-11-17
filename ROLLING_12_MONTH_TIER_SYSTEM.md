# Rolling 12-Month Tier System Documentation

## Overview
Sistem tier member menggunakan **rolling 12-month window** untuk menghitung tier berdasarkan cumulative spending dalam 12 bulan terakhir, bukan total lifetime spending.

## Aturan Tier

### Kebijakan Perubahan Tier
- Perhitungan berdasarkan **cumulative nominal transaksi collected selama 12 bulan terakhir (rolling 12-month window)**
- Tier dihitung ulang setiap bulan berdasarkan window 12 bulan terakhir

### Reset atau Penurunan Tier
- Jika menggunakan rolling 12-month window, tier dapat **turun** jika transaksi di 12 bulan terakhir tidak mencapai nominal transaksi yang dibutuhkan
- Tier dapat **naik** jika transaksi di 12 bulan terakhir mencapai threshold tier yang lebih tinggi

## Tier Thresholds

| Tier | Spending Range (Rolling 12 Months) |
|------|-------------------------------------|
| **Silver** | Rp 0 (Default) |
| **Loyal** | Rp 1 - Rp 15.000.000 |
| **Elite** | Rp 15.000.001 - Rp 40.000.000 |
| **Prestige** | > Rp 40.000.001 |

## Database Structure

### Table: `member_apps_monthly_spending`
Menyimpan spending per bulan per member untuk perhitungan rolling window.

**Columns:**
- `id`: Primary key
- `member_id`: Foreign key ke `member_apps_members`
- `year`: Tahun (e.g., 2024)
- `month`: Bulan (1-12)
- `total_spending`: Total spending untuk bulan tersebut
- `transaction_count`: Jumlah transaksi di bulan tersebut
- `created_at`, `updated_at`: Timestamps

**Indexes:**
- Unique: `(member_id, year, month)` - Satu record per member per bulan
- Index: `member_id`, `year`, `month` untuk query performance

## Service: MemberTierService

### Methods

#### `calculateTier($rolling12MonthSpending)`
Menghitung tier berdasarkan rolling 12-month spending.

#### `updateMemberTier($memberId, $asOfDate = null)`
Update tier member berdasarkan rolling 12-month spending. Tier akan naik atau turun sesuai spending 12 bulan terakhir.

#### `recordTransaction($memberId, $amount, $transactionDate = null)`
Record transaksi baru:
1. Tambahkan ke monthly spending untuk bulan transaksi
2. Update `total_spending` (lifetime) untuk backward compatibility
3. Update tier berdasarkan rolling 12-month spending baru

#### `getTierProgress($memberId, $asOfDate = null)`
Mengembalikan informasi progress tier:
- Current tier
- Next tier
- Previous tier
- Rolling 12-month spending
- Progress percentage (0.0 - 1.0)
- Remaining amount to next tier

## Model: MemberAppsMonthlySpending

### Methods

#### `getRolling12MonthSpending($memberId, $asOfDate = null)`
Menghitung total spending dalam 12 bulan terakhir (rolling window).

**Logic:**
- Start date: 11 months ago + current month = 12 months
- End date: Current date
- Loop through each month in the window
- Sum all spending for those months

#### `addSpending($memberId, $year, $month, $amount)`
Menambahkan spending ke bulan tertentu. Jika record belum ada, dibuat baru. Jika sudah ada, di-update.

## Command: UpdateMemberTiers

### Usage
```bash
# Update all members
php artisan members:update-tiers

# Update specific member
php artisan members:update-tiers --member-id=1
```

### Scheduled Task
Command ini dijalankan otomatis setiap tanggal 1 setiap bulan pada pukul 00:00 untuk update tier semua member.

## API Endpoints

### GET `/api/mobile/member/spending/rolling-12-month`
Mengembalikan rolling 12-month spending dan tier progress untuk member yang sedang login.

### GET `/api/mobile/member/spending/monthly-history`
Mengembalikan history spending per bulan (12 bulan terakhir).

## Frontend Changes

### Profile Screen
- Progress bar dan progress text sekarang menggunakan `rolling_12_month_spending` bukan `total_spending`
- Data diambil dari API response `rolling_12_month_spending` atau `tier_progress.rolling_12_month_spending`

## Migration Steps

1. **Run SQL script:**
   ```sql
   source database/sql/create_member_apps_monthly_spending_table.sql;
   ```

2. **Migrate existing data (optional):**
   Jika ada data transaksi historis, perlu di-migrate ke `member_apps_monthly_spending` table.

3. **Update tiers for existing members:**
   ```bash
   php artisan members:update-tiers
   ```

4. **Setup scheduler:**
   Pastikan Laravel scheduler berjalan:
   ```bash
   php artisan schedule:work
   ```
   Atau setup cron job:
   ```
   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
   ```

## Example Scenarios

### Scenario 1: Member Naik Tier
- Member Silver dengan spending 12 bulan terakhir: 16 juta
- Tier di-update menjadi: **Loyal**

### Scenario 2: Member Turun Tier
- Member Elite dengan spending 12 bulan terakhir: 10 juta (turun dari 50 juta karena bulan ke-13 keluar dari window)
- Tier di-update menjadi: **Loyal**

### Scenario 3: Member Tetap Tier
- Member Loyal dengan spending 12 bulan terakhir: 8 juta
- Tier tetap: **Loyal**

## Notes

- `total_spending` di `member_apps_members` tetap dipertahankan untuk backward compatibility
- Tier calculation hanya menggunakan `rolling_12_month_spending`
- Tier di-update otomatis setiap bulan via scheduler
- Tier juga di-update real-time saat ada transaksi baru via `recordTransaction()`

