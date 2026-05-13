-- Permintaan void item dari POS → banyak approver HO (siapa saja yang dicantumkan boleh approve/reject; bukan level bertingkat).
-- Jalankan manual di database ymsofterp (tidak lewat migrate).
--
-- Ganti approver saat masih pending: dari POS via API POST .../pos/void/item-request/reassign-approvers
-- (hanya pemohon yang sama / requester_user_id cocok).

CREATE TABLE IF NOT EXISTS pos_void_item_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_token CHAR(36) NOT NULL COMMENT 'UUID untuk polling POS',
    kode_outlet VARCHAR(64) NOT NULL,
    order_id VARCHAR(64) NOT NULL,
    order_nomor VARCHAR(128) NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    requester_user_id BIGINT UNSIGNED NULL,
    requester_username VARCHAR(255) NULL,
    reason TEXT NOT NULL,
    item_snapshot JSON NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    approved_at TIMESTAMP NULL DEFAULT NULL,
    approved_by_user_id BIGINT UNSIGNED NULL COMMENT 'users.id — siapa yang benar-benar klik approve',
    rejected_at TIMESTAMP NULL DEFAULT NULL,
    rejection_note TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pos_void_item_public_token (public_token),
    KEY idx_pvi_status (status),
    KEY idx_pvi_kode_order (kode_outlet, order_id),
    KEY idx_pvi_item_status (order_item_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satu request → banyak user HO; siapa pun yang terdaftar di sini boleh approve/tolak (bukan urutan level).
CREATE TABLE IF NOT EXISTS pos_void_item_request_approvers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pos_void_item_request_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'users.id — HO, biasanya id_outlet=1',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_request_user (pos_void_item_request_id, user_id),
    KEY idx_pvira_user (user_id),
    CONSTRAINT fk_pvira_request FOREIGN KEY (pos_void_item_request_id) REFERENCES pos_void_item_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Upgrade dari skema lama (jika pernah buat tabel dengan kolom approver_user_id):
-- 1) INSERT INTO pos_void_item_request_approvers (pos_void_item_request_id, user_id, created_at)
--    SELECT id, approver_user_id, NOW() FROM pos_void_item_requests WHERE approver_user_id IS NOT NULL;
-- 2) ALTER TABLE pos_void_item_requests DROP COLUMN approver_user_id;
-- 3) ALTER TABLE pos_void_item_requests ADD COLUMN approved_by_user_id BIGINT UNSIGNED NULL AFTER approved_at;
--    (sesuaikan posisi kolom jika perlu)
-- ---------------------------------------------------------------------------
