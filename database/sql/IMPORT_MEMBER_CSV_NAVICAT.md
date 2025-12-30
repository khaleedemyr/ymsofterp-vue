# Panduan Import CSV Member ke Navicat

## File CSV
File CSV yang di-export dari menu "Migrasi Data Member" sudah siap untuk diimport langsung ke Navicat.

## Langkah Import di Navicat

1. **Buka Navicat** dan connect ke database
2. **Pilih table** `member_apps_members`
3. **Klik kanan** pada table → **Import Wizard**
4. **Pilih file CSV** yang sudah di-download
5. **Settings Import:**
   - **File Type:** CSV
   - **Encoding:** UTF-8
   - **Field Separator:** Comma (,)
   - **Text Qualifier:** Double Quote (")
   - **First Row Contains Field Names:** ✅ Checked
   - **NULL Values:** Empty String (akan otomatis di-convert ke NULL)

6. **Field Mapping:**
   - Pastikan semua field ter-map dengan benar
   - Field `id` TIDAK perlu di-map (AUTO_INCREMENT)
   - Field nullable (member_id, photo, pekerjaan_id, mobile_verified_at, last_login_at) bisa kosong

7. **Import Options:**
   - **On Duplicate Key:** Skip atau Update (sesuai kebutuhan)
   - **Ignore Errors:** Uncheck (untuk melihat error jika ada)

8. **Klik Start** untuk mulai import

## Catatan Penting

1. **Password Default:** Semua member akan memiliki password default `default123` (sudah di-hash)
   - Setelah import, bisa update password manual atau biarkan user reset via aplikasi

2. **Duplicate Email/Mobile Phone:** 
   - Jika ada duplicate, import akan error
   - Pastikan tidak ada email atau mobile_phone yang duplicate di CSV

3. **Pekerjaan ID:**
   - Jika pekerjaan_id tidak valid, akan di-set NULL
   - Pastikan pekerjaan_id yang ada di CSV valid di table `member_apps_occupations`

4. **Format Data:**
   - Tanggal: YYYY-MM-DD
   - Timestamp: YYYY-MM-DD HH:MM:SS
   - Enum: 'Silver', 'Loyal', 'Elite', 'Prestige' (case sensitive)
   - Jenis Kelamin: 'L' atau 'P'

## Troubleshooting

### Error: Semua record error (Processed: X, Added: 0, Errors: X)

**Kemungkinan Penyebab:**
1. **Format NULL tidak benar** - Navicat tidak convert empty string ke NULL
2. **Urutan kolom tidak sesuai** - Pastikan urutan kolom sesuai dengan table
3. **Foreign key constraint** - pekerjaan_id dengan empty string error

**Solusi:**

#### Solusi 1: Setting Navicat Import
1. Di **Import Wizard** → **Options** tab
2. Pastikan **"Replace NULL with"** benar-benar **KOSONG** (tidak ada spasi atau karakter apapun)
3. **"On Duplicate Key"** → Pilih **"Skip"** atau **"Update"**
4. **"Ignore Errors"** → **Uncheck** (untuk melihat error detail)

#### Solusi 2: Pre-process CSV
Jika masih error, edit CSV manual:
1. Buka CSV dengan Excel atau text editor
2. Untuk kolom `pekerjaan_id` yang kosong, pastikan benar-benar kosong (bukan spasi)
3. Save sebagai CSV UTF-8

#### Solusi 3: Import dengan SQL langsung
Jika CSV masih error, gunakan SQL INSERT:
```sql
-- Contoh format SQL INSERT
INSERT INTO member_apps_members 
(member_id, photo, email, nama_lengkap, mobile_phone, tanggal_lahir, jenis_kelamin, pekerjaan_id, pin, password, is_exclusive_member, member_level, total_spending, just_points, point_remainder, is_active, allow_notification, email_verified_at, mobile_verified_at, last_login_at, created_at, updated_at)
VALUES
(NULL, NULL, 'email@example.com', 'Nama Lengkap', '081234567890', '1990-01-01', 'L', NULL, '$2y$10$...', '$2y$10$...', 0, 'Silver', 0.00, 0, 0.00, 1, 1, NOW(), NULL, NULL, NOW(), NOW());
```

### Error: Duplicate entry for key 'unique_email'
- **Solusi:** Hapus atau skip record dengan email yang sudah ada di table

### Error: Duplicate entry for key 'unique_mobile_phone'
- **Solusi:** Hapus atau skip record dengan mobile_phone yang sudah ada di table

### Error: Foreign key constraint fails (pekerjaan_id)
- **Solusi:** 
  - Pastikan pekerjaan_id yang ada di CSV valid di table `member_apps_occupations`
  - Atau set pekerjaan_id menjadi NULL/empty untuk record yang tidak valid
  - Jika masih error, edit CSV dan hapus nilai pekerjaan_id yang tidak valid

### Error: Invalid enum value for member_level
- **Solusi:** Pastikan member_level adalah 'Silver', 'Loyal', 'Elite', atau 'Prestige' (case sensitive)

### Error: Column count doesn't match
- **Solusi:** Pastikan jumlah kolom di CSV sesuai dengan jumlah kolom di table (22 kolom tanpa 'id')

