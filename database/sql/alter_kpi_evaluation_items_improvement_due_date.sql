-- Optional due date for KPI improvement plan (per evaluation item)
ALTER TABLE `kpi_evaluation_items`
    ADD COLUMN `improvement_plan_due_date` DATE NULL DEFAULT NULL
        AFTER `improvement_plan`;
