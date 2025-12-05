# Tier Upgrade & Downgrade Notification System

This document outlines the implementation of a system to notify members when their tier is upgraded or downgraded.

## Overview

The system sends push notifications to members when their tier changes (both upgrade and downgrade). There are 3 tiers:
- **Silver**: Default tier (Rp 0 - Rp 15.000.000 rolling 12-month spending)
- **Loyal**: Mid tier (Rp 15.000.001 - Rp 40.000.000 rolling 12-month spending)
- **Elite**: Highest tier (Rp 40.000.001+ rolling 12-month spending)

## 1. Event System

### Event: `MemberTierUpgraded`

**File**: `app/Events/MemberTierUpgraded.php`

This event is dispatched when a member's tier is upgraded. It contains:
- `$member`: The member object
- `$oldTier`: Previous tier name
- `$newTier`: New tier name
- `$rollingSpending`: Rolling 12-month spending amount

### Listener: `SendTierUpgradedNotification`

**File**: `app/Listeners/SendTierUpgradedNotification.php`

This listener handles the `MemberTierUpgraded` event and sends FCM notifications.

**Features**:
- Sends notification for both tier upgrades and downgrades
- For upgrades: Congratulatory message with tier benefits
- For downgrades: Encouraging message with benefits of reaching next tier
- Respects member's notification preferences
- Normalizes tier name for display
- Includes tier information in notification payload

## 2. Integration with MemberTierService

**File**: `app/Services/MemberTierService.php`

The `updateMemberTier()` method has been modified to dispatch the `MemberTierUpgraded` event when a tier change is detected:

```php
// Update member tier only if actually changed
if ($currentTierNormalized !== $newTierNormalized) {
    $oldTier = $member->member_level;
    $member->member_level = $newTier;
    $member->save();

    // Dispatch event for push notification
    event(new \App\Events\MemberTierUpgraded(
        $member,
        $oldTier,
        $newTier,
        $rollingSpending
    ));
}
```

## 3. Notification Details

### Upgrade Notification Content

When tier is upgraded:
- **Title**: "You've been upgraded! 🎉"
- **Message**: "You've been upgraded! Enjoy new benefits as an [Tier Name] Member."
  - Examples:
    - "You've been upgraded! Enjoy new benefits as an Loyal Member."
    - "You've been upgraded! Enjoy new benefits as an Elite Member."

### Downgrade Notification Content

When tier is downgraded:
- **Title**: "Keep Climbing! 💪"
- **Message**: Encouraging message with specific benefits based on current tier:
  - **From Elite/Loyal to Silver**: "You're back to Silver tier. Reach Loyal tier to unlock exclusive rewards, 1.5x point earnings, special member benefits, and priority customer support!"
  - **From Elite to Loyal**: "You're at Loyal tier. Reach Elite tier to enjoy maximum benefits, 2x point earnings, premium rewards, exclusive access, and VIP treatment!"

### Data Payload

**Upgrade Payload**:
```json
{
    "type": "tier_upgraded",
    "member_id": 123,
    "old_tier": "Silver",
    "new_tier": "Loyal",
    "tier_name": "Loyal",
    "rolling_spending": 16000000,
    "action": "view_benefits"
}
```

**Downgrade Payload**:
```json
{
    "type": "tier_downgraded",
    "member_id": 123,
    "old_tier": "Elite",
    "new_tier": "Loyal",
    "current_tier": "Loyal",
    "next_tier": "Elite",
    "rolling_spending": 35000000,
    "action": "view_tier_progress"
}
```

## 4. Tier Change Logic

The system sends notifications for both **upgrades** and **downgrades**:

### Tier Hierarchy
- Silver (Level 1)
- Loyal (Level 2)
- Elite (Level 3)

### Notification Scenarios

| Old Tier | New Tier | Notification Type | Notification Sent? |
|----------|----------|-------------------|-------------------|
| Silver   | Loyal    | Upgrade           | ✅ Yes            |
| Silver   | Elite    | Upgrade           | ✅ Yes            |
| Loyal    | Elite    | Upgrade           | ✅ Yes            |
| Loyal    | Silver   | Downgrade         | ✅ Yes (encouraging) |
| Elite    | Loyal    | Downgrade         | ✅ Yes (encouraging) |
| Elite    | Silver   | Downgrade         | ✅ Yes (encouraging) |
| Silver   | Silver   | No change         | ❌ No             |

## 5. Event Registration

**File**: `app/Providers/EventServiceProvider.php`

The event and listener are registered in the `$listen` array:

```php
protected $listen = [
    // ... other events
    \App\Events\MemberTierUpgraded::class => [
        \App\Listeners\SendTierUpgradedNotification::class,
    ],
];
```

## 6. When Tier Upgrades Occur

Tier upgrades can happen in the following scenarios:

1. **After POS Transaction**: When a member makes a purchase, `MemberTierService::recordTransaction()` is called, which then calls `updateMemberTier()`
2. **Scheduled Command**: The `members:update-tiers` command runs monthly (on the 1st) to update all member tiers
3. **Manual Update**: When `MemberTierService::updateMemberTier()` is called directly

## 7. Testing

### Manual Test

1. **Create Test Scenario**:
   - Find a member with tier "Silver"
   - Manually update their monthly spending to push them over the Loyal threshold (Rp 15.000.000)
   - Run the tier update command:
     ```bash
     php artisan members:update-tiers --member-id=[member_id]
     ```

2. **Verify**:
   - Check the console output for tier update messages
   - Check `storage/logs/laravel.log` for event dispatch and notification sending
   - Verify that the notification appears on the member's device (if device token is registered)

### SQL Test Query

To manually check member tier status:

```sql
-- Check member tier and rolling 12-month spending
SELECT 
    m.id,
    m.member_id,
    m.nama_lengkap,
    m.member_level as current_tier,
    m.total_spending as lifetime_spending,
    (
        SELECT COALESCE(SUM(amount), 0)
        FROM member_apps_monthly_spending
        WHERE member_id = m.id
            AND CONCAT(year, '-', LPAD(month, 2, '0'), '-01') >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    ) as rolling_12_month_spending
FROM member_apps_members m
WHERE m.id = [member_id];
```

### Test Tier Upgrade

To manually trigger a tier upgrade for testing:

```sql
-- Add spending to push member to next tier
-- Example: Push Silver member to Loyal (need 15M+ rolling spending)
INSERT INTO member_apps_monthly_spending (member_id, year, month, amount, created_at, updated_at)
VALUES 
    ([member_id], YEAR(CURDATE()), MONTH(CURDATE()), 16000000, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    amount = amount + 16000000,
    updated_at = NOW();

-- Then run tier update command
php artisan members:update-tiers --member-id=[member_id]
```

## 8. Logging

All actions are logged to `storage/logs/laravel.log`:

### Event Dispatch Log
```json
{
    "message": "MemberTierUpgraded event dispatched",
    "member_id": 123,
    "old_tier": "Silver",
    "new_tier": "Loyal"
}
```

### Notification Sent Log
```json
{
    "message": "Tier upgrade notification result",
    "member_id": 123,
    "old_tier": "Silver",
    "new_tier": "Loyal",
    "success_count": 1,
    "failed_count": 0
}
```

### Error Log
```json
{
    "message": "Error sending tier upgrade notification",
    "member_id": 123,
    "error": "FCM service error...",
    "trace": "..."
}
```

## 9. Important Notes

### Notification Behavior

- **Upgrade & Downgrade**: Notifications are sent for both tier upgrades and downgrades
- **Upgrade Messages**: Congratulatory messages celebrating the tier upgrade
- **Downgrade Messages**: Encouraging messages with specific benefits to motivate members to reach the next tier
- **Real-time**: Notifications are sent immediately when tier is updated
- **Respect Preferences**: Only sent to members who have `allow_notification = true`
- **Automatic**: No manual intervention needed - works automatically when tier changes

### Tier Calculation

- Tier is calculated based on **rolling 12-month spending** (not lifetime spending)
- Tier thresholds:
  - Silver: Rp 0 - Rp 15.000.000
  - Loyal: Rp 15.000.001 - Rp 40.000.000
  - Elite: Rp 40.000.001+

### Edge Cases Handled

1. **Tier Downgrade**: No notification sent (only upgrades trigger notifications)
2. **No Change**: No notification sent if tier remains the same
3. **Notifications Disabled**: Skips members who have disabled notifications
4. **Case Variations**: Handles tier name case variations (Silver, SILVER, silver)

## 10. Mobile App Integration

The mobile app should handle the notification payload:

```dart
// Example handling in Flutter
if (message.data['type'] == 'tier_upgraded') {
  final memberId = message.data['member_id'];
  final oldTier = message.data['old_tier'];
  final newTier = message.data['new_tier'];
  final tierName = message.data['tier_name'];
  
  // Navigate to benefits screen or show tier upgrade dialog
  Navigator.push(
    context,
    MaterialPageRoute(
      builder: (context) => TierBenefitsScreen(
        tierName: tierName,
        oldTier: oldTier,
        newTier: newTier,
      ),
    ),
  );
}
```

## 11. Future Enhancements

Potential improvements:
1. **Tier Benefits Display**: Show specific benefits for each tier in the notification
2. **Progress to Next Tier**: Include progress information in notification
3. **Celebration Animation**: Add special UI when tier upgrade notification is received
4. **Multiple Notifications**: Send different messages for different tier upgrades (Silver→Loyal vs Loyal→Elite)
5. **Tier Badge**: Display tier badge in notification

