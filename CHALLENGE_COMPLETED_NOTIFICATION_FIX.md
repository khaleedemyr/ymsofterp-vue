# Challenge Completed Notification Fix

## Masalah yang Ditemukan

Ketika member menyelesaikan challenge, **tidak ada notifikasi yang dikirim** ke member tersebut meskipun:
- Event `ChallengeCompleted` sudah ada
- Listener `SendChallengeCompletedNotification` sudah ada dan terdaftar
- Listener sudah memiliki logic untuk mengirim push notification dan menyimpan ke database

**Root Cause**: Event `ChallengeCompleted` tidak pernah di-dispatch ketika challenge completed di `ChallengeProgressService`.

## Perbaikan yang Dilakukan

### File: `app/Services/ChallengeProgressService.php`

1. **Import Event**
   - Menambahkan `use App\Events\ChallengeCompleted;`

2. **Dispatch Event ketika Challenge Completed**
   - Di method `checkAndCompleteChallenge()`, setelah challenge ditandai sebagai completed
   - Extract reward type dan reward data dari challenge rules
   - Dispatch event dengan data yang sesuai

### Logic Reward Type Detection

Event akan mendeteksi reward type dari:
1. **`rules['reward_type']`** - Primary source
   - `'point'` - Points reward
   - `'item'` - Item reward
   - `'voucher'` - Voucher reward
2. **`challenge->points_reward`** - Legacy fallback
   - Jika `points_reward > 0` dan tidak ada `reward_type`, akan menggunakan `'point'`

### Reward Data yang Dikirim

**Point Reward:**
```php
$rewardData = [
    'points' => $pointAmount,
    'points_earned' => $pointAmount
];
```

**Item Reward:**
```php
$rewardData = [
    'item_id' => $itemId,
    'item_ids' => [$itemId, ...],
    'item_name' => 'Item Name'
];
```

**Voucher Reward:**
```php
$rewardData = [
    'voucher_id' => $voucherId,
    'voucher_ids' => [$voucherId, ...],
    'voucher_name' => 'Voucher Name'
];
```

## Flow Notifikasi

1. **Challenge Completed** (di `ChallengeProgressService`)
   - Progress di-mark sebagai `is_completed = true`
   - `completed_at` di-set
   - `reward_expires_at` dihitung jika ada `validity_period_days`

2. **Event Dispatched**
   - `ChallengeCompleted` event di-dispatch dengan:
     - Member object
     - Challenge ID
     - Challenge title
     - Reward type
     - Reward data

3. **Listener Triggered** (`SendChallengeCompletedNotification`)
   - Cek apakah member allow notification
   - Build notification message berdasarkan reward type
   - Kirim push notification via FCM
   - Simpan notification ke database (`member_apps_notifications`)

## Testing

### 1. Test Challenge Completion
```sql
-- Cek challenge yang sudah completed
SELECT * FROM member_apps_challenge_progress 
WHERE is_completed = 1 
ORDER BY completed_at DESC 
LIMIT 5;
```

### 2. Cek Log
```bash
# Cek log untuk event dispatch
grep -i "Challenge completed event dispatched" storage/logs/laravel.log

# Cek log untuk notification
grep -i "SendChallengeCompletedNotification" storage/logs/laravel.log

# Cek log untuk push notification
grep -i "Sending challenge completed notification" storage/logs/laravel.log
```

### 3. Cek Database Notifications
```sql
-- Cek notifications yang sudah dibuat
SELECT * FROM member_apps_notifications 
WHERE type = 'challenge_completed' 
ORDER BY created_at DESC 
LIMIT 10;
```

### 4. Test Manual

1. **Start Challenge** di member app
2. **Lakukan transaksi** di POS sampai challenge completed
3. **Cek log** untuk memastikan event di-dispatch
4. **Cek member app** untuk notifikasi
5. **Cek database** untuk notification record

## Notification Message Examples

### Point Reward
- **Title**: "Challenge Completed! 🎉"
- **Message**: "Congratulations! You've earned {points} points for completing: {challenge_title}. Keep up the great work!"

### Item Reward
- **Title**: "Free Item Unlocked! 🎁"
- **Message**: "Amazing! You've unlocked a free {item_name} for completing: {challenge_title}. Redeem it now at any outlet!"

### Voucher Reward
- **Title**: "Voucher Unlocked! 🎫"
- **Message**: "Fantastic! You've unlocked a {voucher_name} voucher for completing: {challenge_title}. Use it on your next visit!"

### Default (No Reward Type)
- **Title**: "Challenge Completed! 🎉"
- **Message**: "Congratulations! You've completed: {challenge_title}. Check your rewards!"

## Troubleshooting

### Notifikasi tidak terkirim

1. **Cek apakah event di-dispatch**
   ```bash
   grep "Challenge completed event dispatched" storage/logs/laravel.log
   ```
   - Jika tidak ada, cek apakah challenge benar-benar completed
   - Cek apakah ada error di log

2. **Cek apakah listener triggered**
   ```bash
   grep "SendChallengeCompletedNotification listener triggered" storage/logs/laravel.log
   ```
   - Jika tidak ada, cek EventServiceProvider registration

3. **Cek member notification settings**
   ```sql
   SELECT id, allow_notification FROM member_apps_members WHERE id = [MEMBER_ID];
   ```
   - Jika `allow_notification = 0`, notification akan di-skip

4. **Cek FCM service**
   - Cek log untuk "Challenge completed notification result"
   - Cek apakah device token valid
   - Cek FCM configuration

### Error di Log

1. **"Error dispatching challenge completed event"**
   - Cek apakah member ditemukan
   - Cek apakah rules valid
   - Cek error message di log

2. **"Error sending challenge completed notification"**
   - Cek FCM service
   - Cek device tokens
   - Cek network connectivity

## Catatan Penting

1. **Notification hanya dikirim jika:**
   - Challenge benar-benar completed (`is_completed = true`)
   - Member `allow_notification = true`
   - Event berhasil di-dispatch
   - Listener berhasil di-trigger

2. **Error handling:**
   - Jika dispatch event gagal, challenge tetap completed
   - Jika notification gagal, tidak akan mempengaruhi completion status
   - Semua error di-log untuk debugging

3. **Reward type detection:**
   - Prioritas: `rules['reward_type']` > `challenge->points_reward`
   - Jika tidak ada reward type, akan menggunakan default message

4. **Notification data:**
   - Notification disimpan di `member_apps_notifications` table
   - Type: `'challenge_completed'`
   - URL: `'/challenges'`
   - Data berisi challenge_id, reward_type, dan reward data
