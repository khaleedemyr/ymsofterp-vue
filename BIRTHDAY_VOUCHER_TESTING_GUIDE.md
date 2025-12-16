# Panduan Testing Fitur Birthday Voucher Distribution

Panduan lengkap untuk menjalankan dan menguji fitur distribusi voucher ulang tahun secara manual.

## 📋 Informasi Command

- **Command Signature:** `vouchers:distribute-birthday`
- **Command Class:** `App\Console\Commands\DistributeBirthdayVouchers`
- **File:** `app/Console/Commands/DistributeBirthdayVouchers.php`
- **Scheduled:** Daily at 01:00 AM (via Laravel Scheduler)

---

## 🚀 Cara Menjalankan Command

### 1. Jalankan Secara Manual

```bash
cd D:\Gawean\web\ymsofterp
php artisan vouchers:distribute-birthday
```

### 2. Dengan Output Detail

```bash
php artisan vouchers:distribute-birthday -v
```

### 3. Cek Log

Log ditulis ke: `storage/logs/birthday-vouchers-distribution.log`

```bash
tail -f storage/logs/birthday-vouchers-distribution.log
```

---

## 🧪 Cara Testing

### Opsi 1: Test dengan Member yang Ulang Tahunnya Hari Ini (Recommended)

**Langkah 1: Cek Member yang Ulang Tahunnya Hari Ini**

```sql
-- Cek member yang ulang tahunnya hari ini
SELECT 
    id,
    member_id,
    nama_lengkap,
    tanggal_lahir,
    is_active
FROM member_apps_members
WHERE is_active = 1
  AND tanggal_lahir IS NOT NULL
  AND MONTH(tanggal_lahir) = MONTH(CURDATE())
  AND DAY(tanggal_lahir) = DAY(CURDATE());
```

**Langkah 2: Pastikan Ada Birthday Voucher Aktif**

```sql
-- Cek birthday voucher yang aktif
SELECT 
    id,
    name,
    is_birthday_voucher,
    is_active,
    points_cost,
    valid_until
FROM member_apps_vouchers
WHERE is_birthday_voucher = 1
  AND is_active = 1;
```

**Langkah 3: Jalankan Command**

```bash
php artisan vouchers:distribute-birthday
```

**Langkah 4: Verifikasi Hasil**

```sql
-- Cek voucher yang baru didistribusikan hari ini
SELECT 
    mvv.id,
    mvv.member_id,
    m.member_id as member_code,
    m.nama_lengkap,
    v.name as voucher_name,
    mvv.serial_code,
    mvv.status,
    mvv.expires_at,
    mvv.created_at
FROM member_apps_member_vouchers mvv
JOIN member_apps_members m ON mvv.member_id = m.id
JOIN member_apps_vouchers v ON mvv.voucher_id = v.id
WHERE DATE(mvv.created_at) = CURDATE()
  AND v.is_birthday_voucher = 1
ORDER BY mvv.created_at DESC;
```

---

### Opsi 2: Test dengan Member Test (Ubah Tanggal Lahir)

**Langkah 1: Buat/Update Member Test**

```sql
-- Update tanggal lahir member test menjadi hari ini
UPDATE member_apps_members
SET tanggal_lahir = CURDATE()
WHERE member_id = 'TEST001'  -- Ganti dengan member_id yang ingin di-test
  AND is_active = 1;

-- Atau buat member test baru
INSERT INTO member_apps_members (
    member_id,
    nama_lengkap,
    tanggal_lahir,
    is_active,
    created_at,
    updated_at
) VALUES (
    'TEST_BIRTHDAY',
    'Test Birthday Member',
    CURDATE(),  -- Ulang tahun hari ini
    1,
    NOW(),
    NOW()
);
```

**Langkah 2: Pastikan Ada Birthday Voucher**

```sql
-- Jika belum ada, buat birthday voucher test
INSERT INTO member_apps_vouchers (
    name,
    description,
    is_birthday_voucher,
    is_active,
    points_cost,
    discount_percentage,
    max_discount,
    created_at,
    updated_at
) VALUES (
    'Test Birthday Voucher',
    'Voucher test untuk ulang tahun',
    1,  -- is_birthday_voucher
    1,  -- is_active
    0,  -- points_cost (gratis)
    10, -- discount_percentage
    50000, -- max_discount
    NOW(),
    NOW()
);
```

**Langkah 3: Jalankan Command**

```bash
php artisan vouchers:distribute-birthday
```

**Langkah 4: Verifikasi**

```sql
-- Cek apakah member test mendapat voucher
SELECT 
    m.member_id,
    m.nama_lengkap,
    v.name as voucher_name,
    mvv.serial_code,
    mvv.status,
    mvv.expires_at
FROM member_apps_member_vouchers mvv
JOIN member_apps_members m ON mvv.member_id = m.id
JOIN member_apps_vouchers v ON mvv.voucher_id = v.id
WHERE m.member_id = 'TEST_BIRTHDAY'  -- Ganti dengan member_id test
  AND DATE(mvv.created_at) = CURDATE();
```

---

### Opsi 3: Test dengan Tanggal Tertentu (Modify Command Temporarily)

**Langkah 1: Backup Command File**

```bash
cp app/Console/Commands/DistributeBirthdayVouchers.php app/Console/Commands/DistributeBirthdayVouchers.php.backup
```

**Langkah 2: Modify Command untuk Test Tanggal Tertentu**

Edit `app/Console/Commands/DistributeBirthdayVouchers.php`:

```php
// Ganti baris ini (sekitar line 42):
$today = now();

// Dengan ini (untuk test tanggal tertentu):
$today = Carbon::parse('2025-01-20'); // Ganti dengan tanggal yang diinginkan
```

**Langkah 3: Update Tanggal Lahir Member Test**

```sql
-- Update tanggal lahir menjadi tanggal yang sama dengan $today di command
UPDATE member_apps_members
SET tanggal_lahir = '2025-01-20'  -- Ganti dengan tanggal yang sama dengan $today
WHERE member_id = 'TEST_BIRTHDAY';
```

**Langkah 4: Jalankan Command**

```bash
php artisan vouchers:distribute-birthday
```

**Langkah 5: Restore Command File**

```bash
cp app/Console/Commands/DistributeBirthdayVouchers.php.backup app/Console/Commands/DistributeBirthdayVouchers.php
```

---

## ✅ Checklist Testing

### Sebelum Testing
- [ ] Ada member dengan `tanggal_lahir` = hari ini (atau tanggal test)
- [ ] Member memiliki `is_active = 1`
- [ ] Ada voucher dengan `is_birthday_voucher = 1` dan `is_active = 1`
- [ ] Database connection berfungsi

### Setelah Testing
- [ ] Command berhasil dijalankan tanpa error
- [ ] Member menerima voucher (cek di `member_apps_member_vouchers`)
- [ ] Voucher memiliki `serial_code` yang unik
- [ ] Voucher memiliki `expires_at` = 1 minggu dari hari ini
- [ ] Voucher memiliki `status = 'active'`
- [ ] Member menerima notification (cek di `member_apps_notifications`)
- [ ] Member menerima push notification (jika FCM sudah setup)
- [ ] Member menerima birthday bonus points (jika ada)

---

## 🔍 Verifikasi Hasil

### 1. Cek Voucher yang Didistribusikan

```sql
SELECT 
    m.member_id,
    m.nama_lengkap,
    m.tanggal_lahir,
    v.name as voucher_name,
    mvv.serial_code,
    mvv.status,
    mvv.expires_at,
    mvv.created_at
FROM member_apps_member_vouchers mvv
JOIN member_apps_members m ON mvv.member_id = m.id
JOIN member_apps_vouchers v ON mvv.voucher_id = v.id
WHERE DATE(mvv.created_at) = CURDATE()
  AND v.is_birthday_voucher = 1
ORDER BY mvv.created_at DESC;
```

### 2. Cek Notifications

```sql
SELECT 
    m.member_id,
    m.nama_lengkap,
    n.type,
    n.title,
    n.message,
    n.is_read,
    n.created_at
FROM member_apps_notifications n
JOIN member_apps_members m ON n.member_id = m.id
WHERE DATE(n.created_at) = CURDATE()
  AND n.type LIKE '%birthday%'
ORDER BY n.created_at DESC;
```

### 3. Cek Birthday Bonus Points

```sql
SELECT 
    m.member_id,
    m.nama_lengkap,
    pt.type,
    pt.points,
    pt.description,
    pt.created_at
FROM member_apps_point_transactions pt
JOIN member_apps_members m ON pt.member_id = m.id
WHERE DATE(pt.created_at) = CURDATE()
  AND pt.type = 'birthday'
ORDER BY pt.created_at DESC;
```

### 4. Cek Log

```bash
# Lihat log terakhir
tail -n 50 storage/logs/birthday-vouchers-distribution.log

# Atau cek Laravel log
tail -n 100 storage/logs/laravel.log | grep -i birthday
```

---

## 🐛 Troubleshooting

### Error: "No members have their birthday today"

**Penyebab:**
- Tidak ada member dengan `tanggal_lahir` = hari ini
- Member tidak aktif (`is_active = 0`)

**Solusi:**
- Update `tanggal_lahir` member menjadi hari ini
- Pastikan `is_active = 1`

### Error: "No active birthday vouchers found"

**Penyebab:**
- Tidak ada voucher dengan `is_birthday_voucher = 1` dan `is_active = 1`

**Solusi:**
- Buat atau aktifkan birthday voucher
- Pastikan `is_birthday_voucher = 1` dan `is_active = 1`

### Error: "Member already has this voucher today"

**Penyebab:**
- Member sudah menerima voucher yang sama hari ini (duplicate prevention)

**Solusi:**
- Ini normal, command akan skip member yang sudah punya voucher
- Untuk test ulang, hapus voucher yang sudah ada atau tunggu besok

### Voucher Tidak Muncul di App

**Penyebab:**
- Voucher belum di-sync ke app
- Member belum refresh app
- Voucher sudah expired

**Solusi:**
- Pastikan `status = 'active'` dan `expires_at` belum lewat
- Minta member refresh app atau logout/login ulang

---

## 📝 Catatan Penting

1. **Duplicate Prevention:** Member tidak akan menerima voucher yang sama dua kali dalam satu hari
2. **Expiration:** Birthday voucher expire 1 minggu setelah diterima (bukan dari tanggal lahir)
3. **Points:** Jika voucher memiliki `points_cost > 0`, member harus punya cukup points
4. **Notifications:** Member akan menerima notification dan push notification (jika FCM setup)
5. **Birthday Points:** Member juga akan menerima birthday bonus points (jika ada)

---

## 🔄 Reset untuk Test Ulang

Jika ingin test ulang dengan member yang sama:

```sql
-- Hapus voucher yang sudah didistribusikan hari ini
DELETE mvv FROM member_apps_member_vouchers mvv
JOIN member_apps_vouchers v ON mvv.voucher_id = v.id
WHERE DATE(mvv.created_at) = CURDATE()
  AND v.is_birthday_voucher = 1
  AND mvv.member_id = (SELECT id FROM member_apps_members WHERE member_id = 'TEST_BIRTHDAY');

-- Hapus notifications
DELETE FROM member_apps_notifications
WHERE DATE(created_at) = CURDATE()
  AND type LIKE '%birthday%'
  AND member_id = (SELECT id FROM member_apps_members WHERE member_id = 'TEST_BIRTHDAY');

-- Hapus birthday points (opsional)
DELETE FROM member_apps_point_transactions
WHERE DATE(created_at) = CURDATE()
  AND type = 'birthday'
  AND member_id = (SELECT id FROM member_apps_members WHERE member_id = 'TEST_BIRTHDAY');
```

---

## 📚 Referensi

- **Command File:** `app/Console/Commands/DistributeBirthdayVouchers.php`
- **Scheduler:** `app/Console/Kernel.php` (line 82-88)
- **Log File:** `storage/logs/birthday-vouchers-distribution.log`
- **Model:** `App\Models\MemberAppsVoucher`, `App\Models\MemberAppsMember`, `App\Models\MemberAppsMemberVoucher`

---

**Last Updated:** 2025-01-16

