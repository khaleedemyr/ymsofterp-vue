-- Simple fix for double-encoded documentation_paths
-- Remove the outer quotes from JSON strings

UPDATE dynamic_inspection_details 
SET documentation_paths = TRIM(BOTH '"' FROM documentation_paths)
WHERE documentation_paths IS NOT NULL 
  AND documentation_paths != '[]'
  AND documentation_paths != 'null'
  AND documentation_paths LIKE '%dynamic-inspection-docs%'
  AND documentation_paths LIKE '"%';

-- Check the results
SELECT id, documentation_paths 
FROM dynamic_inspection_details 
WHERE documentation_paths IS NOT NULL 
  AND documentation_paths != '[]'
  AND documentation_paths != 'null'
  AND documentation_paths LIKE '%dynamic-inspection-docs%';
