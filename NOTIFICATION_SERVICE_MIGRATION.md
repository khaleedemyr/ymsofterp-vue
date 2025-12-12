# Migration Guide: NotificationService

## Overview
`NotificationService` adalah service yang menggantikan `DB::table('notifications')->insert()` dengan menggunakan Eloquent `Notification::create()`, sehingga `NotificationObserver` akan otomatis terpicu untuk mengirim push notification ke mobile app.

## Keuntungan
- ✅ Otomatis trigger push notification via `NotificationObserver`
- ✅ Validasi data otomatis
- ✅ Auto-generate title jika tidak disediakan
- ✅ Error handling dan logging
- ✅ Mendukung semua field di table notifications

## Cara Penggunaan

### Sebelum (Old Way):
```php
DB::table('notifications')->insert([
    'user_id' => $userId,
    'task_id' => $taskId,
    'type' => 'approval_type',
    'title' => 'Notification Title',
    'message' => 'Notification message',
    'url' => 'https://example.com',
    'is_read' => 0,
    'created_at' => now(),
    'updated_at' => now(),
]);
```

### Sesudah (New Way):
```php
use App\Services\NotificationService;

NotificationService::create([
    'user_id' => $userId,
    'task_id' => $taskId,
    'approval_id' => $approvalId, // Optional
    'type' => 'approval_type',
    'title' => 'Notification Title', // Optional, akan auto-generate jika kosong
    'message' => 'Notification message',
    'url' => 'https://example.com',
    'is_read' => 0, // Optional, default 0
]);
```

## Field yang Didukung
- `user_id` (required)
- `task_id` (optional)
- `approval_id` (optional)
- `type` (optional)
- `title` (optional, akan auto-generate jika kosong)
- `message` (required)
- `url` (optional)
- `is_read` (optional, default 0)

## Catatan Penting
- `created_at` dan `updated_at` tidak perlu diset, akan otomatis diisi oleh Eloquent
- Jika `title` kosong, akan auto-generate berdasarkan `type` atau `message`
- Service ini menggunakan Eloquent, sehingga `NotificationObserver` akan otomatis terpicu

## Contoh Replace Pattern (Find & Replace)

### Pattern 1: Simple Insert
**Find:**
```php
DB::table('notifications')->insert([
```

**Replace:**
```php
NotificationService::create([
```

**Lalu hapus:**
- `'created_at' => now(),`
- `'updated_at' => now(),`

### Pattern 2: Insert dengan insertGetId
**Find:**
```php
$notificationId = DB::table('notifications')->insertGetId([
```

**Replace:**
```php
$notification = NotificationService::create([
```

**Lalu ganti:**
```php
$notificationId = $notification->id;
```

## File yang Perlu Diupdate
Total: ~86 file controller yang menggunakan `DB::table('notifications')->insert()`

Contoh file:
- PurchaseRequisitionController.php
- StockOpnameController.php
- PurchaseOrderOpsController.php
- ApprovalController.php
- EmployeeMovementController.php
- Dan banyak lagi...

## Testing
Setelah replace, pastikan:
1. Notification tetap tersimpan di database
2. Push notification tetap terkirim ke mobile app
3. Tidak ada error di log

