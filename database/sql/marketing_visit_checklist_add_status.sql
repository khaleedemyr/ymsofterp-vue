-- Run once if migrations are not used: adds draft/submitted workflow for marketing visit checklist.
ALTER TABLE marketing_visit_checklists
  ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'submitted' AFTER visit_date;
