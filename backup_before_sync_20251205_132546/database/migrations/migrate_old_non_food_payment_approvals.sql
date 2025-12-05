-- Migration untuk handle data lama Non Food Payment yang sudah approved
-- Data lama yang sudah approved akan di-set sebagai sudah di-approve oleh kedua level
-- (Finance Manager dan GM Finance) untuk backward compatibility

-- Update data yang sudah approved tapi belum punya approval level
-- Set approved_finance_manager_by dan approved_gm_finance_by dari approved_by
UPDATE `non_food_payments`
SET 
    `approved_finance_manager_by` = `approved_by`,
    `approved_finance_manager_at` = `approved_at`,
    `approved_gm_finance_by` = `approved_by`,
    `approved_gm_finance_at` = `approved_at`
WHERE 
    `status` = 'approved'
    AND `approved_by` IS NOT NULL
    AND `approved_at` IS NOT NULL
    AND (`approved_finance_manager_by` IS NULL OR `approved_gm_finance_by` IS NULL);

-- Update data yang status pending tapi sudah punya approved_by (data inconsistent)
-- Set status ke pending_finance_manager jika sudah ada approved_finance_manager_by
UPDATE `non_food_payments`
SET 
    `status` = 'pending_finance_manager'
WHERE 
    `status` = 'pending'
    AND `approved_finance_manager_by` IS NOT NULL
    AND `approved_gm_finance_by` IS NULL;

