# FCM Push Notification Testing Guide

## 1. Test FCM Configuration

```bash
php artisan fcm:test
```

Ini akan menampilkan status konfigurasi FCM keys.

## 2. Test dengan Device Token Langsung

```bash
php artisan fcm:test --device_token=YOUR_DEVICE_TOKEN_HERE --device_type=android
php artisan fcm:test --device_token=YOUR_DEVICE_TOKEN_HERE --device_type=ios
```

## 3. Test dengan Member ID

```bash
php artisan fcm:test --member_id=1
```

## 4. Checklist Debugging

### A. Cek Konfigurasi FCM Keys
- Pastikan `FCM_IOS_KEY` dan `FCM_ANDROID_KEY` sudah di-set di `.env`
- Restart server setelah menambahkan keys

### B. Cek Member Settings
```sql
SELECT id, nama_lengkap, allow_notification 
FROM member_apps_members 
WHERE id = YOUR_MEMBER_ID;
```

Pastikan `allow_notification = 1`

### C. Cek Device Token
```sql
SELECT * 
FROM member_apps_device_tokens 
WHERE member_id = YOUR_MEMBER_ID 
AND is_active = 1;
```

Pastikan ada device token yang aktif.

### D. Cek Log Laravel
```bash
tail -f storage/logs/laravel.log | grep -i "fcm\|point.*earned\|notification"
```

Atau cek log untuk:
- "Sending point earned notification"
- "Point earned notification sent"
- "FCM notification sent successfully"
- "Error sending point earned notification"

### E. Test Point Earning Manual

1. **Via API** (jika ada endpoint):
```bash
POST /api/mobile/member/points/earn
{
  "member_id": "YOUR_MEMBER_ID",
  "order_id": "TEST_ORDER_123",
  "transaction_amount": 100000,
  "transaction_date": "2025-12-03",
  "channel": "dine-in"
}
```

2. **Via Database** (untuk testing):
```sql
-- Cek apakah point transaction dibuat
SELECT * FROM member_apps_point_transactions 
WHERE member_id = YOUR_MEMBER_ID 
ORDER BY created_at DESC 
LIMIT 5;
```

### F. Test Event Dispatch Manual

Buat file test di `routes/web.php` (temporary):

```php
Route::get('/test-fcm-event', function() {
    $member = \App\Models\MemberAppsMember::find(1); // Ganti dengan member ID yang valid
    if (!$member) {
        return 'Member not found';
    }
    
    $transaction = \App\Models\MemberAppsPointTransaction::where('member_id', $member->id)->latest()->first();
    if (!$transaction) {
        return 'No transaction found';
    }
    
    event(new \App\Events\PointEarned(
        $member,
        $transaction,
        100,
        'transaction',
        ['order_id' => 'TEST', 'outlet_name' => 'Test Outlet']
    ));
    
    return 'Event dispatched! Check logs.';
});
```

Kemudian akses: `http://your-domain/test-fcm-event`

## 5. Common Issues

### Issue: No notification received
**Possible causes:**
1. FCM keys tidak di-set atau salah
2. Member `allow_notification = 0`
3. Tidak ada device token yang aktif
4. Device token tidak valid/expired
5. Event tidak ter-trigger
6. Listener error (cek log)

### Issue: Event tidak ter-trigger
**Check:**
1. Apakah point transaction berhasil dibuat?
2. Apakah ada error di log saat dispatch event?
3. Apakah listener terdaftar? (`php artisan event:list`)

### Issue: FCM API Error
**Check:**
1. Apakah API key valid?
2. Apakah format key benar (tidak ada spasi/karakter aneh)?
3. Cek response dari FCM di log

## 6. Enable Detailed Logging

Tambahkan di `.env`:
```env
LOG_LEVEL=debug
```

Atau tambahkan logging manual di `FCMService.php` untuk debug lebih detail.

