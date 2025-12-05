-- Update reward_item_selection in member_apps_challenges.rules JSON
-- 
-- Note: reward_item_selection is stored in the JSON 'rules' field, not as a separate column
-- Format: {"reward_type": "item", "reward_value": [1,2,3], "reward_item_selection": "all" or "single"}
--
-- This script updates existing challenges that have multiple item rewards but no reward_item_selection
-- Sets default to 'all' for backward compatibility

-- Step 1: Check existing challenges with item rewards and multiple items (before update)
SELECT 
    id,
    title,
    rules,
    JSON_EXTRACT(rules, '$.reward_type') as reward_type,
    JSON_EXTRACT(rules, '$.reward_value') as reward_value,
    JSON_EXTRACT(rules, '$.reward_item_selection') as reward_item_selection,
    JSON_LENGTH(JSON_EXTRACT(rules, '$.reward_value')) as item_count
FROM member_apps_challenges
WHERE JSON_EXTRACT(rules, '$.reward_type') = 'item'
  AND JSON_LENGTH(JSON_EXTRACT(rules, '$.reward_value')) > 1;

-- Step 2: Update challenges with multiple items but no reward_item_selection (set default to 'all')
UPDATE member_apps_challenges
SET rules = JSON_SET(
    rules,
    '$.reward_item_selection',
    'all'
)
WHERE JSON_EXTRACT(rules, '$.reward_type') = 'item'
  AND JSON_LENGTH(JSON_EXTRACT(rules, '$.reward_value')) > 1
  AND (
    JSON_EXTRACT(rules, '$.reward_item_selection') IS NULL 
    OR JSON_EXTRACT(rules, '$.reward_item_selection') = 'null'
    OR JSON_EXTRACT(rules, '$.reward_item_selection') = ''
  );

-- Step 3: Verify the update (after update)
SELECT 
    id,
    title,
    JSON_EXTRACT(rules, '$.reward_type') as reward_type,
    JSON_EXTRACT(rules, '$.reward_value') as reward_value,
    JSON_EXTRACT(rules, '$.reward_item_selection') as reward_item_selection,
    JSON_LENGTH(JSON_EXTRACT(rules, '$.reward_value')) as item_count
FROM member_apps_challenges
WHERE JSON_EXTRACT(rules, '$.reward_type') = 'item'
  AND JSON_LENGTH(JSON_EXTRACT(rules, '$.reward_value')) > 1;

