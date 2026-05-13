-- Sold out untuk self-order web (Justuskunest) per outlet — dibaca API GET /self-order/menu & divalidasi saat checkout.
-- Jalankan manual di database ymsofterp (tidak lewat migrate).

CREATE TABLE IF NOT EXISTS pos_outlet_sold_out_items (
    id_outlet BIGINT UNSIGNED NOT NULL COMMENT 'tbl_data_outlet.id_outlet',
    item_id BIGINT UNSIGNED NOT NULL COMMENT 'items.id',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_outlet, item_id),
    KEY idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_outlet_sold_out_modifier_options (
    id_outlet BIGINT UNSIGNED NOT NULL COMMENT 'tbl_data_outlet.id_outlet',
    modifier_option_id INT UNSIGNED NOT NULL COMMENT 'modifier_options.id',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_outlet, modifier_option_id),
    KEY idx_modifier_option_id (modifier_option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contoh isi (sesuaikan id_outlet / item_id / modifier_option_id):
-- INSERT INTO pos_outlet_sold_out_items (id_outlet, item_id) VALUES (1, 53356);
-- INSERT INTO pos_outlet_sold_out_modifier_options (id_outlet, modifier_option_id) VALUES (1, 21);
