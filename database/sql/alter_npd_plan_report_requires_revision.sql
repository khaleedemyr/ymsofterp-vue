-- NPD Plan Report: tambah status Requires Revision
-- Eksekusi manual sekali di MySQL.

ALTER TABLE `npd_plan_reports`
    MODIFY COLUMN `status` ENUM(
        'draft',
        'submitted',
        'approved',
        'rejected',
        'requires_revision',
        'cancelled'
    ) NOT NULL DEFAULT 'draft';

ALTER TABLE `npd_plan_report_approval_flows`
    MODIFY COLUMN `status` ENUM(
        'PENDING',
        'APPROVED',
        'REJECTED',
        'REQUIRES_REVISION'
    ) NOT NULL DEFAULT 'PENDING';
