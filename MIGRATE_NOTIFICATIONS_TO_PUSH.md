# Migrasi Notifications ke Push Notification

## Masalah
Saat ini banyak controller yang menggunakan `DB::table('notifications')->insert()` yang **TIDAK** mengirim push notification ke mobile app (ymsoftapp). 

Push notification hanya terkirim jika menggunakan **Eloquent** `Notification::create()` yang akan trigger `NotificationObserver`.

## Solusi
Gunakan `NotificationService` yang sudah otomatis trigger push notification.

## Cara Migrasi

### ❌ OLD (Tidak ada push notification):
```php
DB::table('notifications')->insert([
    'user_id' => $userId,
    'task_id' => $taskId,
    'type' => 'purchase_requisition_approval',
    'message' => $message,
    'url' => config('app.url') . '/purchase-requisitions/' . $taskId,
    'is_read' => 0,
]);
```

### ✅ NEW (Dengan push notification):
```php
use App\Services\NotificationService;

NotificationService::insert([
    'user_id' => $userId,
    'task_id' => $taskId,
    'type' => 'purchase_requisition_approval',
    'message' => $message,
    'url' => config('app.url') . '/purchase-requisitions/' . $taskId,
    'is_read' => 0,
]);
```

**Atau jika perlu title:**
```php
NotificationService::create([
    'user_id' => $userId,
    'task_id' => $taskId,
    'type' => 'purchase_requisition_approval',
    'title' => 'Purchase Requisition Approval', // Optional, akan auto-generate jika tidak ada
    'message' => $message,
    'url' => config('app.url') . '/purchase-requisitions/' . $taskId,
    'is_read' => 0,
]);
```

## Perbedaan Method

### `NotificationService::insert($data)`
- Drop-in replacement untuk `DB::table('notifications')->insert()`
- Format data sama persis
- Return: `int|null` (notification ID atau null)
- **✅ Otomatis trigger push notification**

### `NotificationService::create($data)`
- Lebih lengkap dengan validasi dan auto-generate title
- Return: `Notification|null` (model instance atau null)
- **✅ Otomatis trigger push notification**

### `NotificationService::insertGetId($data)`
- Sama dengan `insert()`, tapi lebih eksplisit untuk mendapatkan ID
- Return: `int|null` (notification ID atau null)
- **✅ Otomatis trigger push notification**

## Contoh Migrasi

### Contoh 1: PurchaseRequisitionController
**Sebelum:**
```php
DB::table('notifications')->insert([
    'user_id' => $approver->id,
    'task_id' => $purchaseRequisition->id,
    'type' => 'purchase_requisition_approval',
    'message' => $message,
    'url' => config('app.url') . '/purchase-requisitions/' . $purchaseRequisition->id,
    'is_read' => 0,
]);
```

**Sesudah:**
```php
use App\Services\NotificationService;

NotificationService::insert([
    'user_id' => $approver->id,
    'task_id' => $purchaseRequisition->id,
    'type' => 'purchase_requisition_approval',
    'message' => $message,
    'url' => config('app.url') . '/purchase-requisitions/' . $purchaseRequisition->id,
    'is_read' => 0,
]);
```

### Contoh 2: StockOpnameController
**Sebelum:**
```php
DB::table('notifications')->insert([
    'user_id' => $nextFlow->approver_id,
    'type' => 'stock_opname_approval_request',
    'message' => 'Stock Opname ' . $stockOpname->opname_number . ' membutuhkan approval Anda.',
    'url' => route('stock-opnames.show', $stockOpname->id),
    'is_read' => 0,
]);
```

**Sesudah:**
```php
use App\Services\NotificationService;

NotificationService::insert([
    'user_id' => $nextFlow->approver_id,
    'type' => 'stock_opname_approval_request',
    'message' => 'Stock Opname ' . $stockOpname->opname_number . ' membutuhkan approval Anda.',
    'url' => route('stock-opnames.show', $stockOpname->id),
    'is_read' => 0,
]);
```

## Cara Mencari File yang Perlu Diupdate

Cari semua file yang masih menggunakan `DB::table('notifications')->insert`:

```bash
grep -r "DB::table('notifications')->insert" app/Http/Controllers/
```

Atau di Windows PowerShell:
```powershell
Select-String -Path "app\Http\Controllers\*.php" -Pattern "DB::table\('notifications'\)->insert"
```

## Checklist Migrasi

- [ ] Tambahkan `use App\Services\NotificationService;` di bagian atas file
- [ ] Ganti `DB::table('notifications')->insert([...])` dengan `NotificationService::insert([...])`
- [ ] Ganti `DB::table('notifications')->insertGetId([...])` dengan `NotificationService::insertGetId([...])`
- [ ] Test: Buat notification baru dan cek apakah push notification terkirim ke mobile app
- [ ] Cek log: `storage/logs/laravel.log` untuk melihat "NotificationObserver: Sending FCM notification"

## Testing

Setelah migrasi, test dengan:
1. Trigger action yang membuat notification (misal: submit approval)
2. Cek log: `tail -f storage/logs/laravel.log | grep NotificationObserver`
3. Cek mobile app (ymsoftapp) apakah push notification masuk

## Catatan Penting

- **Observer sudah terdaftar** di `AppServiceProvider::boot()` dengan `Notification::observe(NotificationObserver::class)`
- **Tidak perlu** update observer atau service provider
- **Hanya perlu** ganti cara insert notification dari `DB::table()` ke `NotificationService`
- Push notification akan otomatis terkirim ke:
  - Web devices (via `web_device_tokens`)
  - Mobile app devices (via `employee_device_tokens`)

## File yang Sudah Diupdate

- ✅ `OutletFoodInventoryAdjustmentController.php` - Sudah menggunakan `NotificationService::create()`

## File yang Masih Perlu Diupdate

Cari dengan:
```bash
grep -r "DB::table('notifications')->insert" app/Http/Controllers/
```

Beberapa file yang ditemukan:
- `PurchaseRequisitionController.php` (3 tempat)
- `StockOpnameController.php` (1 tempat)
- Dan masih banyak lagi...

