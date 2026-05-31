-- Breakdown L&B, Deviasi, City Ledger (by point + pro rate) — mirror service charge
ALTER TABLE payroll_generated_details
    ADD COLUMN IF NOT EXISTS lb_by_point DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER bpjs_tk,
    ADD COLUMN IF NOT EXISTS lb_pro_rate DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER lb_by_point,
    ADD COLUMN IF NOT EXISTS deviasi_by_point DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER lb_total,
    ADD COLUMN IF NOT EXISTS deviasi_pro_rate DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER deviasi_by_point,
    ADD COLUMN IF NOT EXISTS city_ledger_by_point DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER deviasi_total,
    ADD COLUMN IF NOT EXISTS city_ledger_pro_rate DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER city_ledger_by_point;
