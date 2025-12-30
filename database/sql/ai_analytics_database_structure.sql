-- =====================================================
-- AI ANALYTICS: DATABASE STRUCTURE DOCUMENTATION
-- =====================================================
-- Dokumentasi struktur database untuk AI Analytics
-- Sales + Inventory + BOM Analysis
-- =====================================================

-- =====================================================
-- 1. SALES STRUCTURE
-- =====================================================

-- Tabel: orders
-- Struktur utama untuk sales data
-- Key columns:
--   - id: Primary key
--   - kode_outlet: Outlet code (link ke tbl_data_outlet.qr_code)
--   - created_at: Order date/time
--   - grand_total: Total revenue
--   - pax: Number of customers
--   - discount: Discount amount
--   - service: Service charge
--   - commfee: Commission fee
--   - manual_discount_amount: Manual discount
--   - status: Order status

-- Tabel: order_items
-- Detail items dalam order
-- Key columns:
--   - id: Primary key
--   - order_id: FK ke orders.id
--   - item_id: FK ke items.id (bisa null jika item sudah dihapus)
--   - item_name: Nama item (snapshot)
--   - qty: Quantity
--   - price: Unit price
--   - subtotal: Total (qty * price)
--   - modifier_options: JSON modifier data (bisa punya BOM sendiri)

-- Tabel: order_payment
-- Payment methods untuk order
-- Key columns:
--   - order_id: FK ke orders.id
--   - payment_code: Payment method code
--   - payment_type: Payment type
--   - amount: Payment amount

-- Relasi:
-- orders (1) -> (N) order_items
-- orders (1) -> (N) order_payment
-- orders.kode_outlet -> tbl_data_outlet.qr_code
-- order_items.item_id -> items.id

-- =====================================================
-- 2. INVENTORY STRUCTURE
-- =====================================================

-- Tabel: items
-- Master data items (products)
-- Key columns:
--   - id: Primary key
--   - name: Item name
--   - sku: SKU code
--   - type: Item type (food, beverages, etc)
--   - composition_type: 'composed' (punya BOM) atau 'single'
--   - status: active/inactive
--   - min_stock: Minimum stock level
--   - warehouse_division_id: FK ke warehouse_division

-- Tabel: item_bom
-- Bill of Materials (BOM) untuk items
-- Key columns:
--   - id: Primary key
--   - item_id: FK ke items.id (item yang punya BOM)
--   - material_item_id: FK ke items.id (bahan baku/material)
--   - qty: Quantity bahan baku yang dibutuhkan
--   - unit_id: FK ke units.id (satuan)
-- Relasi: item (1) -> (N) item_bom (banyak bahan baku)

-- Tabel: food_inventory_items
-- Master inventory items (untuk warehouse/head office)
-- Key columns:
--   - id: Primary key
--   - item_id: FK ke items.id
--   - small_unit_id, medium_unit_id, large_unit_id: Unit conversions

-- Tabel: outlet_food_inventory_items
-- Inventory items per outlet
-- Key columns:
--   - id: Primary key
--   - item_id: FK ke items.id
--   - small_unit_id, medium_unit_id, large_unit_id: Unit conversions

-- Tabel: outlet_food_inventory_stocks
-- Current stock levels per outlet per warehouse
-- Key columns:
--   - id: Primary key
--   - inventory_item_id: FK ke outlet_food_inventory_items.id
--   - id_outlet: FK ke tbl_data_outlet.id_outlet
--   - warehouse_outlet_id: FK ke warehouse_outlets.id (gudang di outlet)
--   - qty_small, qty_medium, qty_large: Stock quantities
--   - value: Total stock value
--   - last_cost_small, last_cost_medium, last_cost_large: Last cost per unit

-- Tabel: outlet_food_inventory_cards
-- Stock movement history (in/out transactions)
-- Key columns:
--   - id: Primary key
--   - inventory_item_id: FK ke outlet_food_inventory_items.id
--   - id_outlet: FK ke tbl_data_outlet.id_outlet
--   - warehouse_outlet_id: FK ke warehouse_outlets.id
--   - date: Transaction date
--   - reference_type: Type (stock_cut, production, adjustment, transfer, etc)
--   - reference_id: ID dari reference (bisa order_id, production_id, etc)
--   - in_qty_small, in_qty_medium, in_qty_large: Stock IN
--   - out_qty_small, out_qty_medium, out_qty_large: Stock OUT
--   - cost_per_small, cost_per_medium, cost_per_large: Cost per unit
--   - value_in, value_out: Value IN/OUT
--   - saldo_qty_small, saldo_qty_medium, saldo_qty_large: Balance after transaction
--   - saldo_value: Balance value
--   - description: Transaction description

-- Tabel: outlet_food_inventory_cost_histories
-- Cost history untuk inventory items
-- Key columns:
--   - id: Primary key
--   - inventory_item_id: FK ke outlet_food_inventory_items.id
--   - id_outlet: FK ke tbl_data_outlet.id_outlet
--   - warehouse_outlet_id: FK ke warehouse_outlets.id
--   - cost_small, cost_medium, cost_large: Cost per unit
--   - effective_date: When cost is effective

-- Relasi Inventory:
-- items (1) -> (N) item_bom (BOM materials)
-- items (1) -> (1) outlet_food_inventory_items
-- outlet_food_inventory_items (1) -> (N) outlet_food_inventory_stocks (per outlet/warehouse)
-- outlet_food_inventory_items (1) -> (N) outlet_food_inventory_cards (movement history)
-- outlet_food_inventory_items (1) -> (N) outlet_food_inventory_cost_histories

-- =====================================================
-- 3. WAREHOUSE STRUCTURE
-- =====================================================

-- Tabel: warehouses
-- Master warehouses (gudang di head office)
-- Key columns:
--   - id: Primary key
--   - name: Warehouse name
--   - code: Warehouse code
--   - status: active/inactive

-- Tabel: warehouse_outlets
-- Warehouses per outlet (gudang di cabang)
-- Key columns:
--   - id: Primary key
--   - code: Warehouse code
--   - name: Warehouse name
--   - outlet_id: FK ke tbl_data_outlet.id_outlet
--   - location: Location description
--   - status: active/inactive

-- Tabel: warehouse_division
-- Warehouse divisions (kategori gudang)
-- Key columns:
--   - id: Primary key
--   - name: Division name
--   - code: Division code

-- Relasi:
-- warehouses (1) -> (N) warehouse_outlets (bisa punya banyak warehouse per outlet)
-- items.warehouse_division_id -> warehouse_division.id

-- =====================================================
-- 4. PRODUCTION STRUCTURE
-- =====================================================

-- Tabel: mk_productions
-- Production records (produksi item)
-- Key columns:
--   - id: Primary key
--   - item_id: FK ke items.id (item yang diproduksi)
--   - qty: Quantity produced
--   - unit_id: FK ke units.id
--   - production_date: Production date
--   - batch_number: Batch number
--   - warehouse_id: FK ke warehouses.id
--   - notes: Production notes
--   - created_by: User ID

-- Tabel: stock_cut_logs
-- Log stock cut operations
-- Key columns:
--   - id: Primary key
--   - outlet_id: FK ke tbl_data_outlet.id_outlet
--   - tanggal: Stock cut date
--   - type_filter: 'food', 'beverages', null (all)
--   - total_items_cut: Total items processed
--   - total_modifiers_cut: Total modifiers processed
--   - status: success/failed

-- Alur Stock Cut:
-- 1. Ambil order_items yang belum stock_cut = 0
-- 2. Untuk setiap item, ambil BOM dari item_bom
-- 3. Kalkulasi kebutuhan bahan baku (qty * bom.qty)
-- 4. Cek stock di outlet_food_inventory_stocks
-- 5. Jika cukup, potong stock (update outlet_food_inventory_stocks)
-- 6. Catat di outlet_food_inventory_cards (out_qty)
-- 7. Update flag stock_cut di order_items

-- =====================================================
-- 5. OUTLET STRUCTURE
-- =====================================================

-- Tabel: tbl_data_outlet
-- Master outlet data
-- Key columns:
--   - id_outlet: Primary key
--   - qr_code: QR code (link ke orders.kode_outlet)
--   - nama_outlet: Outlet name
--   - region_id: FK ke regions.id
--   - status: Status (A=active)

-- Tabel: regions
-- Region/area grouping
-- Key columns:
--   - id: Primary key
--   - name: Region name
--   - code: Region code

-- =====================================================
-- 6. KEY RELATIONSHIPS FOR AI ANALYSIS
-- =====================================================

-- Sales Flow:
-- orders -> order_items -> items
-- order_items.item_id -> items.id
-- orders.kode_outlet -> tbl_data_outlet.qr_code

-- Inventory Flow:
-- items -> item_bom -> items (material_item_id)
-- items -> outlet_food_inventory_items -> outlet_food_inventory_stocks
-- outlet_food_inventory_cards (track all movements)

-- BOM Usage:
-- Item dengan composition_type='composed' punya BOM
-- BOM digunakan untuk:
--   1. Stock cut (potong bahan saat order)
--   2. Production (kalkulasi kebutuhan bahan)
--   3. Cost calculation (hitung cost item dari bahan baku)

-- Stock Movement Types (reference_type di outlet_food_inventory_cards):
-- - stock_cut: Potong stock dari order
-- - production: Produksi item (keluar bahan, masuk hasil)
-- - adjustment: Stock adjustment
-- - transfer: Transfer antar outlet/warehouse
-- - delivery_order: Stock masuk dari DO
-- - purchase_order: Stock masuk dari PO
-- - return: Stock return
-- - rejection: Stock rejection

-- =====================================================
-- 7. DATA AGGREGATION PATTERNS
-- =====================================================

-- Sales Aggregation:
-- - Per hari: GROUP BY DATE(orders.created_at)
-- - Per outlet: GROUP BY orders.kode_outlet
-- - Per item: GROUP BY order_items.item_id
-- - Per region: GROUP BY regions.id

-- Inventory Aggregation:
-- - Current stock: outlet_food_inventory_stocks (latest)
-- - Stock movements: outlet_food_inventory_cards (history)
-- - Per outlet: WHERE id_outlet = X
-- - Per warehouse: WHERE warehouse_outlet_id = X
-- - Per item: WHERE inventory_item_id = X

-- BOM Analysis:
-- - Item dengan BOM: WHERE items.composition_type = 'composed'
-- - BOM materials: SELECT FROM item_bom WHERE item_id = X
-- - Kalkulasi kebutuhan: SUM(bom.qty * order_item.qty)
-- - Cek stock bahan: JOIN dengan outlet_food_inventory_stocks

-- =====================================================
-- END OF DOCUMENTATION
-- =====================================================

