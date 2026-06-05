-- =====================================================
-- PARTNER LEDGER (Hutang & Piutang Sub-Ledger)
-- Jalankan sekali di MySQL (paste seluruh blok).
-- =====================================================

START TRANSACTION;

-- COA Hutang Usaha & Piutang Usaha (sesuaikan parent_id jika perlu)
INSERT INTO `chart_of_accounts` (
    `code`, `name`, `type`, `parent_id`, `is_active`, `description`, `created_at`, `updated_at`
) VALUES (
    '2.1.1.01',
    'Hutang Usaha',
    'liability',
    NULL,
    1,
    'Kewajiban hutang kepada supplier (sub-ledger per supplier)',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `type` = VALUES(`type`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

INSERT INTO `chart_of_accounts` (
    `code`, `name`, `type`, `parent_id`, `is_active`, `description`, `created_at`, `updated_at`
) VALUES (
    '1.1.2.04',
    'Piutang Usaha',
    'asset',
    NULL,
    1,
    'Piutang dari outlet (sub-ledger per outlet)',
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `type` = VALUES(`type`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

CREATE TABLE IF NOT EXISTS `partner_sub_ledgers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ledger_type` VARCHAR(20) NOT NULL COMMENT 'payable | receivable',
    `partner_type` VARCHAR(20) NOT NULL COMMENT 'supplier | outlet',
    `partner_id` BIGINT UNSIGNED NOT NULL COMMENT 'suppliers.id atau tbl_data_outlet.id_outlet',
    `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_partner_sub_ledgers_partner` (`ledger_type`, `partner_type`, `partner_id`),
    INDEX `idx_partner_sub_ledgers_type` (`ledger_type`),
    INDEX `idx_partner_sub_ledgers_balance` (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Saldo hutang/piutang per supplier atau outlet';

CREATE TABLE IF NOT EXISTS `partner_ledger_entries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sub_ledger_id` BIGINT UNSIGNED NOT NULL,
    `entry_type` VARCHAR(30) NOT NULL COMMENT 'accrual | settlement | opening_balance | manual | reversal',
    `amount` DECIMAL(15, 2) NOT NULL COMMENT 'Positif = tambah saldo, negatif = kurangi saldo',
    `entry_date` DATE NOT NULL,
    `description` TEXT NULL,
    `source_type` VARCHAR(50) NULL COMMENT 'non_food_payment | food_payment | outlet_payment | manual',
    `source_id` BIGINT UNSIGNED NULL,
    `jurnal_id` BIGINT UNSIGNED NULL,
    `no_jurnal` VARCHAR(50) NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_partner_ledger_source_entry` (`source_type`, `source_id`, `entry_type`),
    INDEX `idx_partner_ledger_sub_ledger` (`sub_ledger_id`),
    INDEX `idx_partner_ledger_entry_date` (`entry_date`),
    INDEX `idx_partner_ledger_entry_type` (`entry_type`),
    CONSTRAINT `fk_partner_ledger_sub_ledger` FOREIGN KEY (`sub_ledger_id`)
        REFERENCES `partner_sub_ledgers` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_partner_ledger_jurnal` FOREIGN KEY (`jurnal_id`)
        REFERENCES `jurnal` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_partner_ledger_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Riwayat mutasi sub-ledger hutang/piutang';

COMMIT;

-- Verifikasi:
-- SELECT * FROM chart_of_accounts WHERE code IN ('2.1.1.01', '1.1.2.04');
-- SHOW TABLES LIKE 'partner_%';
