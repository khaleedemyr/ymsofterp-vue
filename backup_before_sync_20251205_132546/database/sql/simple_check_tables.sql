-- Simple check for Retail Non Food tables
-- Run this to see if tables exist

SELECT 
    'retail_non_food' as table_name,
    CASE 
        WHEN COUNT(*) > 0 THEN 'EXISTS'
        ELSE 'NOT EXISTS'
    END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'retail_non_food'

UNION ALL

SELECT 
    'retail_non_food_items' as table_name,
    CASE 
        WHEN COUNT(*) > 0 THEN 'EXISTS'
        ELSE 'NOT EXISTS'
    END as status
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'retail_non_food_items'; 