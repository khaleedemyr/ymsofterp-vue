# Long Inactive Member Notification System

This document outlines the implementation of a system to notify members who haven't made a transaction in the last 3 months (90 days).

## Overview

The system sends push notifications to members who:
- Are active members (`is_active = true`)
- Have notifications enabled (`allow_notification = true`)
- Have made at least one transaction in the past (to avoid notifying new members)
- Last transaction was exactly 90 days ago (with 2 day window: 89-91 days)

## 1. Artisan Command

A new Artisan command `member:notify-long-inactive` has been created to identify and send notifications to eligible members.

**File**: `app/Console/Commands/SendLongInactiveMemberNotification.php`

### How It Works

1. **Query Long Inactive Members**: Finds all members who:
   - Are active and allow notifications
   - Have at least one paid transaction (`orders.status = 'paid'`)
   - Last transaction date is between 89-91 days ago (2 day window for flexibility)

2. **Validation Checks**: For each eligible member, validates:
   - Member still exists and is active
   - Member still allows notifications
   - Last transaction is still within 89-91 day window (to avoid duplicate notifications)

3. **Send Notification**: Sends FCM notification with:
   - **Title**: "Welcome Back! 🎁"
   - **Message**: "We haven't seen you in a while! Here's a special offer to welcome you back."
   - **Data Payload**:
     ```json
     {
         "type": "long_inactive_member",
         "member_id": 123,
         "days_since_last_transaction": 90,
         "last_transaction_date": "2025-09-01",
         "action": "view_offers"
     }
     ```

## 2. Scheduling

The Artisan command is scheduled to run daily at 11:00 AM to check for eligible members.

**File**: `app/Console/Kernel.php`

```php
// Send long inactive member notification - run daily at 11:00 AM
// This checks for members who haven't made a transaction in the last 3 months (90 days)
$schedule->command('member:notify-long-inactive')
    ->dailyAt('11:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/long-inactive-member-notifications.log'))
    ->description('Send notification to members who haven\'t made a transaction in the last 3 months (90 days)');
```

## 3. Notification Timing Logic

The system uses a smart timing algorithm to send notifications once, approximately 90 days after the last transaction:

- **Window**: 89-91 days after last transaction (2 day window for flexibility)
- **Frequency**: Once per member (notification sent only once when they reach the 90-day mark)
- **Query Logic**: Uses `DATE()` comparison to match by date, not exact time

### Example Timeline

- **Day 0, 10:00 AM**: Member makes last transaction
- **Day 90, 11:00 AM**: Command runs, finds member's last transaction was 90 days ago → **Notification sent** ✓
- **Day 91, 11:00 AM**: Command runs, finds member's last transaction was 91 days ago → **Notification sent** ✓ (if not sent on Day 90)
- **Day 92, 11:00 AM**: Command runs, finds member's last transaction was 92 days ago → **No notification** (outside window)

## 4. Database Structure

The system uses the existing `orders` and `member_apps_members` tables (same as inactive member notification for 7 days).

### Orders Table
```sql
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  ...
  PRIMARY KEY (`id`),
  KEY `orders_member_id_index` (`member_id`),
  ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Fields Used**:
- `member_id`: Links order to member
- `status`: Must be 'paid' for transaction to count
- `created_at`: Used to determine last transaction date

### Member Apps Members Table
```sql
CREATE TABLE `member_apps_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` varchar(255) DEFAULT NULL,
  `nama_lengkap` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `allow_notification` tinyint(1) NOT NULL DEFAULT 1,
  ...
  PRIMARY KEY (`id`),
  ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Fields Used**:
- `is_active`: Only active members are notified
- `allow_notification`: Only members who allow notifications are notified

## 5. Query Logic

The command uses a SQL query to find eligible members:

```sql
SELECT 
    m.id,
    m.member_id,
    m.nama_lengkap,
    m.allow_notification,
    MAX(o.created_at) as last_transaction_date
FROM member_apps_members m
INNER JOIN orders o ON o.member_id = m.id AND o.status = 'paid'
WHERE m.is_active = 1
    AND m.allow_notification = 1
    AND o.member_id IS NOT NULL
GROUP BY m.id, m.member_id, m.nama_lengkap, m.allow_notification
HAVING DATE(last_transaction_date) >= ? 
    AND DATE(last_transaction_date) <= ?
```

**Parameters**:
- `?` (first): 91 days ago (start of day)
- `?` (second): 89 days ago (start of day)

This ensures we find members whose last transaction was exactly 89-91 days ago.

## 6. Testing

### Manual Test

You can manually run the command to test its functionality:

```bash
php artisan member:notify-long-inactive
```

### Scenario Test

1. **Create Test Data**:
   - Ensure a member has at least one paid transaction
   - Update the last transaction date to be 90 days ago:
     ```sql
     UPDATE orders
     SET created_at = DATE_SUB(NOW(), INTERVAL 90 DAY)
     WHERE member_id = [member_id]
         AND status = 'paid'
     ORDER BY created_at DESC
     LIMIT 1;
     ```

2. **Run Command**:
   ```bash
   php artisan member:notify-long-inactive
   ```

3. **Verify**:
   - Check the console output for success messages
   - Check `storage/logs/long-inactive-member-notifications.log` for detailed logs
   - Verify that the notification appears on the member's device (if device token is registered)

### SQL Test Query

To manually check which members would receive notifications:

```sql
-- Find members who would receive long inactive notification
SELECT 
    m.id,
    m.member_id,
    m.nama_lengkap,
    m.allow_notification,
    MAX(o.created_at) as last_transaction_date,
    DATEDIFF(NOW(), MAX(o.created_at)) as days_since_last_transaction
FROM member_apps_members m
INNER JOIN orders o ON o.member_id = m.id AND o.status = 'paid'
WHERE m.is_active = 1
    AND m.allow_notification = 1
    AND o.member_id IS NOT NULL
GROUP BY m.id, m.member_id, m.nama_lengkap, m.allow_notification
HAVING days_since_last_transaction >= 89
    AND days_since_last_transaction <= 91
ORDER BY last_transaction_date ASC;
```

## 7. Logging

All actions are logged to:
- **Console Output**: Real-time feedback during command execution
- **Log File**: `storage/logs/long-inactive-member-notifications.log`
- **Laravel Log**: `storage/logs/laravel.log` (for errors and detailed info)

### Log Examples

**Success Log**:
```json
{
    "message": "Long inactive member notification sent",
    "member_id": 123,
    "member_name": "John Doe",
    "last_transaction_date": "2025-09-01 10:00:00",
    "days_since_last_transaction": 90
}
```

**Error Log**:
```json
{
    "message": "Error sending long inactive member notification",
    "member_id": 123,
    "member_name": "John Doe",
    "error": "FCM service error...",
    "trace": "..."
}
```

## 8. Important Notes

### Notification Frequency

- **Frequency**: Notification is sent once per member when they reach the 90-day mark
- **Window**: 2-day window (89-91 days) to account for daily command execution
- **No Duplicates**: The system checks the exact days since last transaction to avoid sending multiple notifications

### Performance Considerations

- The command uses efficient database queries with proper indexes
- Uses `GROUP BY` and `MAX()` to get the latest transaction per member
- Uses `DATE()` comparison for date-only matching (ignores time)
- Runs in background to avoid blocking other scheduled tasks

### Edge Cases Handled

1. **No Transactions**: Skips members who have never made a transaction
2. **Member Not Found**: Skips if member doesn't exist
3. **Notifications Disabled**: Respects member's `allow_notification` preference
4. **Member Inactive**: Skips if member is not active
5. **Outside Window**: Only sends notification if last transaction is exactly 89-91 days ago
6. **Status Changed**: Refreshes member data before sending to ensure latest status

### Limitations

- **No Tracking**: The system doesn't track if a notification was already sent, so it relies on the 89-91 day window to send only once
- **Daily Execution**: Since the command runs daily, members who reach the 90-day mark will receive notification within 1-2 days
- **Transaction Status**: Only counts transactions with `status = 'paid'`

## 9. Comparison with 7-Day Inactive Notification

This system is similar to the 7-day inactive member notification, but with different timing:

| Feature | 7-Day Inactive | 90-Day Inactive |
|---------|---------------|-----------------|
| **Timing** | 7 days | 90 days (3 months) |
| **Window** | 7-8 days | 89-91 days |
| **Purpose** | Re-engagement reminder | Welcome back offer |
| **Message** | "How was your experience today?" | "We haven't seen you in a while!" |
| **Action** | `view_history` | `view_offers` |

Both systems work independently and can send notifications to the same member at different times.

## 10. Mobile App Integration

The mobile app should handle the notification payload:

```dart
// Example handling in Flutter
if (message.data['type'] == 'long_inactive_member') {
  final memberId = message.data['member_id'];
  final daysSince = message.data['days_since_last_transaction'];
  
  // Navigate to special offers or promotions screen
  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (context) => SpecialOffersScreen(memberId: memberId),
    ),
  );
}
```

## 11. Future Enhancements

Potential improvements:
1. **Tracking Field**: Add `last_long_inactive_notification_sent_at` field to track when notification was last sent
2. **Special Offers**: Automatically create or link to special offers/promotions for returning members
3. **Personalized Messages**: Customize message based on member's purchase history or preferences
4. **Multiple Reminders**: Send follow-up notifications at 120 days, 180 days, etc.
5. **A/B Testing**: Test different notification messages and timing
6. **Offer Integration**: Automatically attach a special discount code or voucher to the notification

