# Birthday Voucher Distribution - Cron Job Setup

## Overview
This cron job automatically distributes birthday vouchers to members who have their birthday on the current day.

## Command
```bash
php artisan vouchers:distribute-birthday
```

## Schedule
The command runs daily at **01:00 AM** (1:00 AM) via Laravel's scheduler.

## How It Works
1. The command checks for all active vouchers marked as `is_birthday_voucher = true`
2. It finds all active members whose birthday (month and day) matches today's date
3. For each birthday voucher and each member with a birthday:
   - Checks if the member already received this voucher today (prevents duplicates)
   - Generates a unique voucher code and serial code
   - Creates a `MemberAppsMemberVoucher` record with status 'active'
   - Sets expiration date based on voucher's `valid_until` or 1 year from today

## Logs
Logs are written to: `storage/logs/birthday-vouchers-distribution.log`

## Manual Execution
You can manually run the command for testing:
```bash
php artisan vouchers:distribute-birthday
```

## Server Cron Setup
Make sure your server's cron is set up to run Laravel's scheduler:

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/your/project` with your actual project path.

## Notes
- The command uses database transactions to ensure data integrity
- Duplicate prevention: Members won't receive the same birthday voucher twice on the same day
- Only active members (`is_active = true`) with a valid `tanggal_lahir` are eligible
- Only active vouchers (`is_active = true`) marked as birthday vouchers are distributed

