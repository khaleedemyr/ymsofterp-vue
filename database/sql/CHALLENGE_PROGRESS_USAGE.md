# Challenge Progress Service - Panduan Penggunaan

## Overview
Service `ChallengeProgressService` digunakan untuk update progress challenge member secara otomatis berdasarkan transaksi mereka di table `orders` dan `order_items`.

## File yang Dibutuhkan

### 1. Query SQL untuk Membuat Table
Jalankan query di file: `database/sql/create_member_apps_challenge_progress_table.sql`

### 2. Service File
- `app/Services/ChallengeProgressService.php` - Service untuk update progress
- `app/Models/MemberAppsChallengeProgress.php` - Model untuk progress

## Cara Integrasi dengan Transaksi

### Opsi 1: Manual Call (Paling Simple)
Panggil service ini setelah order berhasil di-paid di controller atau tempat dimana order dibuat:

```php
use App\Services\ChallengeProgressService;

// Setelah order status menjadi 'paid'
$progressService = new ChallengeProgressService();
$progressService->updateProgressFromTransaction($memberId, $orderId);
```

**Contoh di Controller:**
```php
// Di method yang handle order payment
public function processPayment(Request $request, $orderId)
{
    // ... process payment logic ...
    
    // Update order status to paid
    DB::table('orders')
        ->where('id', $orderId)
        ->update(['status' => 'paid']);
    
    // Get member_id from order
    $order = DB::table('orders')->where('id', $orderId)->first();
    
    if ($order && $order->member_id) {
        // Update challenge progress
        $progressService = new ChallengeProgressService();
        $progressService->updateProgressFromTransaction($order->member_id, $orderId);
    }
    
    // ... return response ...
}
```

### Opsi 2: Via Event Listener (Recommended untuk Auto-Update)
Buat Event dan Listener untuk auto-update saat order paid:

**1. Buat Event:**
```php
// app/Events/OrderPaid.php
<?php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid
{
    use Dispatchable, SerializesModels;

    public $orderId;
    public $memberId;

    public function __construct($orderId, $memberId)
    {
        $this->orderId = $orderId;
        $this->memberId = $memberId;
    }
}
```

**2. Buat Listener:**
```php
// app/Listeners/UpdateChallengeProgress.php
<?php
namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\ChallengeProgressService;

class UpdateChallengeProgress
{
    public function handle(OrderPaid $event)
    {
        $progressService = new ChallengeProgressService();
        $progressService->updateProgressFromTransaction(
            $event->memberId, 
            $event->orderId
        );
    }
}
```

**3. Register di EventServiceProvider:**
```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    OrderPaid::class => [
        UpdateChallengeProgress::class,
    ],
];
```

**4. Fire Event saat Order Paid:**
```php
// Di tempat dimana order status diubah ke 'paid'
use App\Events\OrderPaid;

DB::table('orders')
    ->where('id', $orderId)
    ->update(['status' => 'paid']);

$order = DB::table('orders')->where('id', $orderId)->first();
if ($order && $order->member_id) {
    event(new OrderPaid($orderId, $order->member_id));
}
```

### Opsi 3: Via Scheduled Job (Untuk Batch Update)
Update progress secara berkala untuk semua active challenges:

**1. Buat Command:**
```php
// app/Console/Commands/UpdateChallengeProgress.php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChallengeProgressService;
use App\Models\MemberAppsChallengeProgress;

class UpdateChallengeProgress extends Command
{
    protected $signature = 'challenges:update-progress';
    protected $description = 'Update challenge progress from transactions';

    public function handle()
    {
        $progressService = new ChallengeProgressService();
        
        $activeProgresses = MemberAppsChallengeProgress::where('is_completed', false)
            ->with('member')
            ->get();

        $bar = $this->output->createProgressBar($activeProgresses->count());
        $bar->start();

        foreach ($activeProgresses as $progress) {
            $progressService->updateProgressFromTransaction($progress->member_id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Challenge progress updated successfully');
    }
}
```

**2. Register di Kernel:**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Update progress every 5 minutes
    $schedule->command('challenges:update-progress')->everyFiveMinutes();
}
```

## Cara Kerja Service

### Spending-based Challenge
1. Menghitung total `grand_total` dari table `orders` 
2. Filter: `member_id` = member, `status` = 'paid', `created_at` >= `started_at`
3. Filter outlet jika ada di rules (join dengan `tbl_data_outlet` via `qr_code`)
4. Auto-complete jika `total_spending >= min_amount`
5. Set `reward_expires_at` = `completed_at + validity_period_days`

### Product-based Challenge
1. Menghitung total `qty` dari table `order_items` untuk products yang ditentukan
2. Filter: `orders.member_id` = member, `orders.status` = 'paid', `orders.created_at` >= `started_at`
3. Filter outlet jika ada di rules
4. Auto-complete jika `total_quantity >= quantity_required`
5. Set `reward_expires_at` = `completed_at + validity_period_days`

## Mapping Data

### Member ID Mapping
- Table `orders.member_id` (varchar) → harus sesuai dengan `member_apps_members.member_id` atau `member_apps_members.id`
- Service menggunakan `$member->member_id ?? $member->id` untuk kompatibilitas

### Outlet Mapping
- Table `orders.kode_outlet` (varchar) → join dengan `tbl_data_outlet.qr_code`
- Rules `outlet_ids` berisi `id_outlet` dari `tbl_data_outlet`
- Service convert `id_outlet` → `qr_code` untuk filter

## Testing

### Test Manual via Tinker
```php
php artisan tinker

use App\Services\ChallengeProgressService;

$service = new ChallengeProgressService();
$service->updateProgressFromTransaction('MEMBER_ID_HERE', 'ORDER_ID_HERE');
```

### Test via API
```bash
# Refresh progress untuk challenge tertentu
POST /api/mobile/member/challenges/{id}/refresh
Headers: Authorization: Bearer {token}
```

## Progress Data Structure

Progress data disimpan di field `progress_data` (JSON):

### Spending-based:
```json
{
  "spending": 500000,
  "last_updated": "2025-01-15 10:30:00",
  "last_order_id": "ORD123"
}
```

### Product-based:
```json
{
  "total_quantity": 5,
  "product_quantities": {
    "53354": 3,
    "53355": 2
  },
  "last_updated": "2025-01-15 10:30:00",
  "last_order_id": "ORD123"
}
```

## Auto-Update di Frontend

Progress otomatis di-update saat:
1. User membuka challenge detail (jika belum completed)
2. User klik tombol "Refresh Progress"
3. Challenge detail di-reload

## Notes

- Progress hanya dihitung dari order dengan `status = 'paid'`
- Progress dihitung dari `started_at` sampai sekarang
- Challenge auto-complete saat requirement terpenuhi
- Reward expiry dihitung dari `completed_at + validity_period_days`
- Jika reward expired, `is_reward_expired = true` dan `can_claim_reward = false`

