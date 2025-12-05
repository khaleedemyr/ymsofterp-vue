-- Fix double encoded documentation_paths in dynamic_inspection_details table
-- This script will decode the double-encoded JSON strings

-- First, let's see what we're dealing with
SELECT id, documentation_paths, 
       JSON_VALID(documentation_paths) as is_valid_json,
       JSON_TYPE(documentation_paths) as json_type
FROM dynamic_inspection_details 
WHERE documentation_paths IS NOT NULL 
  AND documentation_paths != '[]'
  AND documentation_paths != 'null'
  AND documentation_paths LIKE '%dynamic-inspection-docs%';

-- Fix the double-encoded strings
-- The pattern is: "[\"path\"]" should become ["path"]
UPDATE dynamic_inspection_details 
SET documentation_paths = JSON_UNQUOTE(JSON_EXTRACT(documentation_paths, '$'))
WHERE documentation_paths IS NOT NULL 
  AND documentation_paths != '[]'
  AND documentation_paths != 'null'
  AND documentation_paths LIKE '%dynamic-inspection-docs%'
  AND JSON_VALID(documentation_paths) = 1;

-- Alternative approach using REGEXP_REPLACE for more complex cases
-- UPDATE dynamic_inspection_details 
-- SET documentation_paths = REGEXP_REPLACE(
--   documentation_paths, 
--   '^"(.*)"$', 
--   '$1'
-- )
-- WHERE documentation_paths IS NOT NULL 
--   AND documentation_paths != '[]'
--   AND documentation_paths != 'null'
--   AND documentation_paths LIKE '%dynamic-inspection-docs%';
