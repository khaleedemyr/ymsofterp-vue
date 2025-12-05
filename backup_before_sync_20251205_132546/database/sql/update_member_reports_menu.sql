-- Update Member Reports menu route
UPDATE erp_menu 
SET route = '/crm/member-reports' 
WHERE code = 'crm_reports' AND name = 'Member Reports';

-- Verify the update
SELECT id, name, code, route, icon 
FROM erp_menu 
WHERE code = 'crm_reports'; 