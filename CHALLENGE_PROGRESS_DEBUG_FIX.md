# Challenge Progress Update Fix

## Masalah yang Ditemukan

1. **Missing Import**: `MemberAppsMember` tidak di-import di `ChallengeController.php`
2. **Missing Validation**: Service tidak memvalidasi bahwa challenge sudah di-start (`started_at` tidak null)
3. **Kurang Logging**: Tidak ada logging yang cukup untuk debugging

## Perbaikan yang Dilakukan

### 1. Fix Missing Import
**File**: `app/Http/Controllers/Mobile/Member/ChallengeController.php`
- Menambahkan `use App\Models\MemberAppsMember;`

### 2. Validasi Challenge Started
**File**: `app/Services/ChallengeProgressService.php`
- Menambahkan filter `whereNotNull('started_at')` untuk memastikan hanya challenge yang sudah di-start yang di-update
- Menambahkan logging ketika tidak ada active progress

### 3. Enhanced Logging
**File**: `app/Http/Controllers/Mobile/Member/ChallengeController.php`
- Log ketika request diterima
- Log ketika member ditemukan/tidak ditemukan
- Log detail member yang ditemukan

**File**: `app/Services/ChallengeProgressService.php`
- Log ketika mulai update progress
- Log member identifier yang digunakan
- Log jumlah active progresses
- Log detail perhitungan spending/product progress

## Cara Debugging

### 1. Cek Log Laravel
Setelah transaksi di POS, cek log Laravel di `storage/logs/laravel.log`:

```bash
tail -f storage/logs/laravel.log | grep -i challenge
```

Atau cari dengan:
```bash
grep -i "challenge progress" storage/logs/laravel.log
```

### 2. Cek Request dari POS
Pastikan request sampai ke API:
- Cek log untuk `Challenge progress update request received`
- Cek apakah member ditemukan: `Member found for challenge progress update`
- Cek apakah ada active progresses: `Updating challenge progress from transaction`

### 3. Cek Database
```sql
-- Cek apakah challenge sudah di-start
SELECT * FROM member_apps_challenge_progress 
WHERE member_id = [MEMBER_ID] 
AND challenge_id = [CHALLENGE_ID];

-- Pastikan started_at tidak null
SELECT * FROM member_apps_challenge_progress 
WHERE member_id = [MEMBER_ID] 
AND started_at IS NOT NULL 
AND is_completed = 0;

-- Cek orders untuk member
SELECT * FROM orders 
WHERE member_id = [MEMBER_IDENTIFIER] 
AND status = 'paid' 
AND created_at >= [STARTED_AT];
```

### 4. Cek Member Identifier
Masalah umum: `member_id` di orders table mungkin berbeda format dengan `member_id` di `member_apps_members`.

**Cek format member_id di orders:**
```sql
SELECT DISTINCT o.member_id, m.id, m.member_id 
FROM orders o
LEFT JOIN member_apps_members m ON (o.member_id = m.member_id OR o.member_id = m.id)
WHERE o.member_id IS NOT NULL AND o.member_id != ''
LIMIT 10;
```

**Service menggunakan:**
- `$memberIdentifier = $member->member_id ?? $member->id;`
- Ini akan menggunakan `member_id` field jika ada, jika tidak akan menggunakan `id` (primary key)

## Testing

1. **Start Challenge di Member App**
   - Pastikan challenge sudah di-start
   - Cek `member_apps_challenge_progress` table, pastikan `started_at` tidak null

2. **Lakukan Transaksi di POS**
   - Pastikan order menggunakan member yang sama
   - Pastikan order status = 'paid'
   - Pastikan order sudah sync ke pusat

3. **Cek Log**
   - Cek apakah API dipanggil dari POS
   - Cek apakah member ditemukan
   - Cek apakah progress di-update

4. **Cek Progress**
   - Refresh challenge di member app
   - Atau cek langsung di database: `member_apps_challenge_progress.progress_data`

## Troubleshooting

### Progress tidak update

1. **Cek apakah challenge sudah di-start**
   ```sql
   SELECT * FROM member_apps_challenge_progress 
   WHERE member_id = [MEMBER_ID] 
   AND started_at IS NOT NULL;
   ```

2. **Cek apakah member_id match**
   - Cek log untuk `member_identifier` yang digunakan
   - Bandingkan dengan `member_id` di orders table

3. **Cek apakah order sudah sync**
   - Pastikan order sudah ada di database pusat
   - Pastikan `status = 'paid'`
   - Pastikan `created_at >= started_at`

4. **Cek outlet filter**
   - Jika challenge punya outlet filter, pastikan order dari outlet yang sesuai
   - Cek log untuk outlet codes yang digunakan

### Member tidak ditemukan

1. **Cek format member_id dari POS**
   - POS mengirim `member_id` dari orders table
   - Controller mencari di `member_apps_members` dengan:
     - `where('member_id', $memberId)` atau
     - `where('id', $memberId)`

2. **Jika masih tidak ditemukan**
   - Cek apakah member_id di orders table sesuai dengan member_apps_members
   - Mungkin perlu mapping atau transformasi

## API Endpoint

**URL**: `POST /api/mobile/member/challenges/update-progress`

**Request**:
```json
{
  "member_id": "12345",
  "order_id": "ORD123456"
}
```

**Response Success**:
```json
{
  "success": true,
  "message": "Challenge progress updated successfully"
}
```

**Response Error**:
```json
{
  "success": false,
  "message": "Member not found"
}
```

## Catatan Penting

1. Progress hanya di-update untuk challenge yang:
   - Sudah di-start (`started_at` tidak null)
   - Belum completed (`is_completed = false`)
   - Challenge masih active (`is_active = true`)
   - Challenge belum ended (`end_date` belum lewat)

2. Member identifier yang digunakan untuk match dengan orders:
   - `$member->member_id` jika ada
   - `$member->id` jika `member_id` null

3. Update progress tidak akan gagalkan payment jika error
   - Error hanya di-log
   - Payment tetap berhasil
