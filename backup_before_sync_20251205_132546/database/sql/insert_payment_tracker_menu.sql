-- Insert Payment Tracker menu and permissions in one execution
INSERT INTO erp_menu (name, code, parent_id, route, icon, created_at, updated_at)
VALUES ('Payment Tracker', 'payment_tracker', NULL, '/purchase-requisitions/payment-tracker', 'fa-solid fa-chart-line', NOW(), NOW());

-- Insert permissions for Payment Tracker using subquery to get the menu_id
INSERT INTO erp_permission (menu_id, action, code, created_at, updated_at)
SELECT 
    m.id as menu_id,
    p.action,
    p.code,
    NOW() as created_at,
    NOW() as updated_at
FROM erp_menu m
CROSS JOIN (
    SELECT 'view' as action, 'payment_tracker_view' as code
    UNION ALL SELECT 'create', 'payment_tracker_create'
    UNION ALL SELECT 'update', 'payment_tracker_update'
    UNION ALL SELECT 'delete', 'payment_tracker_delete'
) p
WHERE m.code = 'payment_tracker';

