# Challenge Progress Integration Guide

## Overview
Service ini digunakan untuk update progress challenge member berdasarkan transaksi mereka di table `orders` dan `order_items`.

## Cara Penggunaan

### 1. Manual Update Progress
Panggil service ini setelah ada transaksi baru (order dengan status 'paid'):

```php
use App\Services\ChallengeProgressService;

// Setelah order berhasil dibuat dan status menjadi 'paid'
$progressService = new ChallengeProgressService();
$progressService->updateProgressFromTransaction($memberId, $orderId);
```

### 2. Via Event/Listener (Recommended)
Buat Event Listener untuk auto-update progress saat order paid:

**File: app/Events/OrderPaid.php**
```php
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

**File: app/Listeners/UpdateChallengeProgress.php**
```php
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

**File: app/Providers/EventServiceProvider.php** (tambahkan di method boot)
```php
protected $listen = [
    OrderPaid::class => [
        UpdateChallengeProgress::class,
    ],
];
```

### 3. Via Database Trigger (Alternative)
Jika ingin update otomatis via database trigger:

```sql
DELIMITER $$

CREATE TRIGGER after_order_paid_update_challenge_progress
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status = 'paid' AND OLD.status != 'paid' AND NEW.member_id IS NOT NULL THEN
        -- Call service via HTTP request or stored procedure
        -- Note: This requires additional setup
    END IF;
END$$

DELIMITER ;
```

### 4. Via Scheduled Job (For Batch Update)
Update progress secara berkala untuk semua active challenges:

**File: app/Console/Commands/UpdateChallengeProgress.php**
```php
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

        foreach ($activeProgresses as $progress) {
            $progressService->updateProgressFromTransaction($progress->member_id);
        }

        $this->info('Challenge progress updated successfully');
    }
}
```

**File: app/Console/Kernel.php** (tambahkan di method schedule)
```php
$schedule->command('challenges:update-progress')->everyFiveMinutes();
```

## Cara Kerja

### Spending-based Challenge
- Menghitung total spending dari table `orders` sejak challenge dimulai
- Filter berdasarkan outlet jika ada di rules
- Auto-complete jika `total_spending >= min_amount`

### Product-based Challenge
- Menghitung total quantity dari table `order_items` untuk products yang ditentukan
- Filter berdasarkan outlet jika ada di rules
- Auto-complete jika `total_quantity >= quantity_required`

### Reward Expiry
- Saat challenge completed, `reward_expires_at` = `completed_at + validity_period_days`
- Reward hangus jika `now() > reward_expires_at`

## Testing

Untuk test manual, bisa panggil langsung:

```php
use App\Services\ChallengeProgressService;

$service = new ChallengeProgressService();
$service->updateProgressFromTransaction('MEMBER_ID_HERE', 'ORDER_ID_HERE');
```

## Notes

- Progress dihitung dari `started_at` sampai sekarang
- Hanya menghitung order dengan status = 'paid'
- Outlet filtering menggunakan `kode_outlet` dari orders yang di-join dengan `qr_code` di tbl_data_outlet
- Progress auto-update saat user membuka challenge detail (jika belum completed)

