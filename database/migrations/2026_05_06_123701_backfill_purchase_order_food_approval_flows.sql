-- Backfill approval flow for existing PO Food records.
-- Assumption:
--   level 1 = Purchasing Manager candidate (id_jabatan 167/168, active)
--   level 2 = GM Finance candidate (id_jabatan 152/381, active)
-- Candidate selection uses the first active user by id.

START TRANSACTION;

SET @pm_approver_id := (
  SELECT u.id
  FROM users u
  WHERE u.status = 'A'
    AND u.id_jabatan IN (167, 168)
  ORDER BY u.id
  LIMIT 1
);

SET @gm_approver_id := (
  SELECT u.id
  FROM users u
  WHERE u.status = 'A'
    AND u.id_jabatan IN (152, 381)
  ORDER BY u.id
  LIMIT 1
);

-- Safety checks (run manually if needed):
-- SELECT @pm_approver_id AS pm_approver_id, @gm_approver_id AS gm_approver_id;

-- Level 1 flow (Purchasing Manager)
INSERT INTO purchase_order_food_approval_flows (
  purchase_order_food_id,
  approver_id,
  approval_level,
  status,
  approved_at,
  rejected_at,
  comments,
  created_at,
  updated_at
)
SELECT
  pof.id,
  COALESCE(pof.purchasing_manager_approved_by, @pm_approver_id) AS approver_id,
  1 AS approval_level,
  CASE
    WHEN pof.status = 'rejected' AND pof.purchasing_manager_approved_at IS NULL THEN 'REJECTED'
    WHEN pof.purchasing_manager_approved_at IS NOT NULL THEN 'APPROVED'
    ELSE 'PENDING'
  END AS status,
  pof.purchasing_manager_approved_at AS approved_at,
  CASE
    WHEN pof.status = 'rejected' AND pof.purchasing_manager_approved_at IS NULL THEN pof.updated_at
    ELSE NULL
  END AS rejected_at,
  pof.purchasing_manager_note AS comments,
  COALESCE(pof.created_at, NOW()) AS created_at,
  COALESCE(pof.updated_at, NOW()) AS updated_at
FROM purchase_order_foods pof
WHERE NOT EXISTS (
  SELECT 1
  FROM purchase_order_food_approval_flows f
  WHERE f.purchase_order_food_id = pof.id
    AND f.approval_level = 1
)
AND COALESCE(pof.purchasing_manager_approved_by, @pm_approver_id) IS NOT NULL;

-- Level 2 flow (GM Finance)
INSERT INTO purchase_order_food_approval_flows (
  purchase_order_food_id,
  approver_id,
  approval_level,
  status,
  approved_at,
  rejected_at,
  comments,
  created_at,
  updated_at
)
SELECT
  pof.id,
  COALESCE(pof.gm_finance_approved_by, @gm_approver_id) AS approver_id,
  2 AS approval_level,
  CASE
    WHEN pof.gm_finance_approved_at IS NOT NULL THEN 'APPROVED'
    WHEN pof.status = 'rejected' AND pof.purchasing_manager_approved_at IS NOT NULL THEN 'REJECTED'
    WHEN pof.purchasing_manager_approved_at IS NOT NULL THEN 'PENDING'
    ELSE 'PENDING'
  END AS status,
  pof.gm_finance_approved_at AS approved_at,
  CASE
    WHEN pof.status = 'rejected' AND pof.purchasing_manager_approved_at IS NOT NULL THEN pof.updated_at
    ELSE NULL
  END AS rejected_at,
  pof.gm_finance_note AS comments,
  COALESCE(pof.created_at, NOW()) AS created_at,
  COALESCE(pof.updated_at, NOW()) AS updated_at
FROM purchase_order_foods pof
WHERE NOT EXISTS (
  SELECT 1
  FROM purchase_order_food_approval_flows f
  WHERE f.purchase_order_food_id = pof.id
    AND f.approval_level = 2
)
AND COALESCE(pof.gm_finance_approved_by, @gm_approver_id) IS NOT NULL;

COMMIT;
