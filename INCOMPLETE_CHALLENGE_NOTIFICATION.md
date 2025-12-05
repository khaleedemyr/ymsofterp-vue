# Incomplete Challenge Notification System

This document outlines the implementation of a system to notify members who have started a challenge but haven't completed it within 24 hours (and every 24 hours thereafter).

## Overview

The system sends push notifications to members who:
- Have started a challenge (`started_at` is not null)
- Have not completed the challenge (`is_completed = false`)
- Are at 24-hour intervals since starting the challenge (24h, 48h, 72h, etc.)

## 1. Artisan Command

A new Artisan command `member:notify-incomplete-challenge` has been created to identify and send notifications to eligible members.

**File**: `app/Console/Commands/SendIncompleteChallengeNotification.php`

### How It Works

1. **Query Incomplete Challenges**: Finds all challenge progresses that:
   - Are not completed (`is_completed = false`)
   - Have a `started_at` timestamp (challenge has been started)
   - Were started at least 23 hours ago

2. **Filter by 24-Hour Intervals**: Filters progresses to only those at 24-hour intervals:
   - Uses modulo operation: `hours_since_start % 24`
   - Allows a 1-hour window (23-25 hours, 47-49 hours, etc.) for flexibility
   - This ensures notifications are sent every 24 hours, not just once

3. **Validation Checks**: For each eligible progress, validates:
   - Challenge exists and is active
   - Challenge has not expired (`end_date` check)
   - Member exists and has notifications enabled
   - Progress is still incomplete (double-check)

4. **Send Notification**: Sends FCM notification with:
   - **Title**: "Complete Your Challenge! 🎯"
   - **Message**: "Let's complete your challenge! You're almost there—come visit us!"
   - **Data Payload**:
     ```json
     {
         "type": "incomplete_challenge",
         "challenge_id": 12,
         "challenge_title": "NPD TASTE EXPERIENCE",
         "progress_id": 45,
         "action": "view_challenge"
     }
     ```

## 2. Scheduling

The Artisan command is scheduled to run hourly to check for eligible members.

**File**: `app/Console/Kernel.php`

```php
// Send incomplete challenge notification - run every hour
// This checks for members who started a challenge but haven't completed it within 24 hours
$schedule->command('member:notify-incomplete-challenge')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/incomplete-challenge-notifications.log'))
    ->description('Send notification to members who started a challenge but haven\'t completed it within 24 hours');
```

## 3. Notification Timing Logic

The system uses a smart timing algorithm to send notifications every 24 hours:

- **First Notification**: 24 hours after challenge start (23-25 hour window)
- **Subsequent Notifications**: Every 24 hours thereafter (48h, 72h, 96h, etc.)
- **Window Flexibility**: 1-hour window (23-25, 47-49, etc.) to account for hourly command execution

### Example Timeline

- **Day 0, 10:00 AM**: Member starts challenge
- **Day 1, 9:00-11:00 AM**: First notification sent (24 hours later)
- **Day 2, 9:00-11:00 AM**: Second notification sent (48 hours later)
- **Day 3, 9:00-11:00 AM**: Third notification sent (72 hours later)
- And so on...

## 4. Database Structure

The system uses the existing `member_apps_challenge_progress` table:

```sql
CREATE TABLE `member_apps_challenge_progress` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `challenge_id` bigint(20) unsigned NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `progress_data` json DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reward_claimed` tinyint(1) NOT NULL DEFAULT 0,
  `reward_claimed_at` timestamp NULL DEFAULT NULL,
  `reward_redeemed_at` timestamp NULL DEFAULT NULL,
  `redeemed_outlet_id` bigint(20) unsigned DEFAULT NULL,
  `reward_expires_at` timestamp NULL DEFAULT NULL,
  `serial_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_challenge_progress_unique` (`member_id`, `challenge_id`),
  KEY `member_challenge_progress_member_id_foreign` (`member_id`),
  KEY `member_challenge_progress_challenge_id_foreign` (`challenge_id`),
  KEY `member_challenge_progress_completed_index` (`is_completed`),
  KEY `member_challenge_progress_expires_index` (`reward_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Fields Used**:
- `started_at`: Timestamp when member started the challenge
- `is_completed`: Boolean flag indicating if challenge is completed
- `updated_at`: Last update timestamp (used for additional filtering)

## 5. Testing

### Manual Test

You can manually run the command to test its functionality:

```bash
php artisan member:notify-incomplete-challenge
```

### Scenario Test

1. **Create Test Data**:
   - Create a challenge in the system
   - Have a member start the challenge (create a `member_apps_challenge_progress` record)
   - Set `started_at` to 24 hours ago (or a multiple of 24 hours)
   - Ensure `is_completed = false`

2. **Run Command**:
   ```bash
   php artisan member:notify-incomplete-challenge
   ```

3. **Verify**:
   - Check the console output for success messages
   - Check `storage/logs/incomplete-challenge-notifications.log` for detailed logs
   - Verify that the notification appears on the member's device (if device token is registered)

### SQL Test Query

To manually set up test data:

```sql
-- Set a challenge progress to be 24 hours old
UPDATE member_apps_challenge_progress
SET started_at = DATE_SUB(NOW(), INTERVAL 24 HOUR),
    updated_at = DATE_SUB(NOW(), INTERVAL 24 HOUR),
    is_completed = 0
WHERE id = [progress_id];

-- Verify the data
SELECT 
    id,
    member_id,
    challenge_id,
    started_at,
    is_completed,
    TIMESTAMPDIFF(HOUR, started_at, NOW()) as hours_since_start
FROM member_apps_challenge_progress
WHERE id = [progress_id];
```

## 6. Logging

All actions are logged to:
- **Console Output**: Real-time feedback during command execution
- **Log File**: `storage/logs/incomplete-challenge-notifications.log`
- **Laravel Log**: `storage/logs/laravel.log` (for errors and detailed info)

### Log Examples

**Success Log**:
```json
{
    "message": "Incomplete challenge notification sent",
    "member_id": 123,
    "challenge_id": 12,
    "progress_id": 45,
    "challenge_title": "NPD TASTE EXPERIENCE",
    "started_at": "2025-12-02 10:00:00",
    "last_updated": "2025-12-02 10:00:00",
    "hours_since_start": 24
}
```

**Error Log**:
```json
{
    "message": "Error sending incomplete challenge notification",
    "progress_id": 45,
    "member_id": 123,
    "challenge_id": 12,
    "error": "FCM service error...",
    "trace": "..."
}
```

## 7. Important Notes

### Notification Frequency

- **Frequency**: Notifications are sent every 24 hours, not just once
- **Window**: 1-hour window (23-25 hours) to account for hourly command execution
- **Stops**: Notifications stop when:
  - Challenge is completed (`is_completed = true`)
  - Challenge expires (`end_date` has passed)
  - Challenge is deactivated (`is_active = false`)
  - Member disables notifications (`allow_notification = false`)

### Performance Considerations

- The command uses efficient database queries with proper indexes
- Uses eager loading (`with(['member', 'challenge'])`) to avoid N+1 queries
- Filters in-memory for 24-hour intervals to avoid complex SQL calculations
- Runs in background to avoid blocking other scheduled tasks

### Edge Cases Handled

1. **Challenge Expired**: Skips notification if challenge `end_date` has passed
2. **Challenge Inactive**: Skips notification if challenge `is_active = false`
3. **Member Not Found**: Skips notification if member or challenge doesn't exist
4. **Already Completed**: Double-checks `is_completed` before sending
5. **Notifications Disabled**: Respects member's `allow_notification` preference

## 8. Mobile App Integration

The mobile app should handle the notification payload:

```dart
// Example handling in Flutter
if (message.data['type'] == 'incomplete_challenge') {
  final challengeId = message.data['challenge_id'];
  final challengeTitle = message.data['challenge_title'];
  
  // Navigate to challenge details screen
  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (context) => ChallengeDetailsScreen(challengeId: challengeId),
    ),
  );
}
```

## 9. Future Enhancements

Potential improvements:
1. **Configurable Intervals**: Allow different notification intervals (e.g., 12 hours, 48 hours)
2. **Progress-Based Reminders**: Send different messages based on progress percentage
3. **Maximum Reminders**: Limit the number of reminders per challenge
4. **Last Reminder Tracking**: Add `last_reminder_sent_at` field to track and prevent duplicates more accurately

