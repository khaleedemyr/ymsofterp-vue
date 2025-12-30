# AI Analytics Implementation - Summary & Next Steps

## 📋 Yang Sudah Dipelajari

### 1. **Struktur Database Sales**
- **orders**: Header order dengan `kode_outlet`, `grand_total`, `pax`, `created_at`
- **order_items**: Detail items dengan `item_id`, `qty`, `price`, `subtotal`
- **order_payment**: Payment methods per order
- Relasi: `orders` -> `order_items` -> `items`, `orders.kode_outlet` -> `tbl_data_outlet.qr_code`

### 2. **Struktur Database Inventory**
- **items**: Master items dengan `composition_type` ('composed' = punya BOM, 'single' = tidak)
- **item_bom**: BOM structure (`item_id` -> `material_item_id` dengan `qty` dan `unit_id`)
- **outlet_food_inventory_items**: Mapping item ke inventory per outlet
- **outlet_food_inventory_stocks**: Current stock levels per outlet per warehouse (qty_small, qty_medium, qty_large)
- **outlet_food_inventory_cards**: Stock movement history (in/out) dengan `reference_type` dan `reference_id`
- **outlet_food_inventory_cost_histories**: Cost history per item

### 3. **Struktur BOM (Bill of Materials)**
- Item dengan `composition_type = 'composed'` punya BOM
- BOM digunakan untuk:
  - **Stock Cut**: Kalkulasi kebutuhan bahan saat order
  - **Production**: Kalkulasi kebutuhan bahan saat produksi
  - **Cost Calculation**: Hitung cost item dari bahan baku
- Relasi: `items` (1) -> (N) `item_bom` -> `items` (material_item_id)

### 4. **Struktur Warehouse**
- **warehouses**: Master warehouses (gudang head office)
- **warehouse_outlets**: Warehouses per outlet (gudang di cabang)
- **warehouse_division**: Kategori gudang
- Stock tracking per outlet per warehouse (`warehouse_outlet_id`)

### 5. **Alur Stock Movement**
- **Stock Cut**: Potong stock dari order (menggunakan BOM)
- **Production**: Produksi item (keluar bahan, masuk hasil)
- **Adjustment**: Stock adjustment
- **Transfer**: Transfer antar outlet/warehouse
- **Delivery Order**: Stock masuk dari DO
- **Purchase Order**: Stock masuk dari PO
- Semua tercatat di `outlet_food_inventory_cards` dengan `reference_type`

### 6. **Production System**
- **MKProduction**: Production di warehouse (head office)
- **OutletWIP**: Production di outlet (work in progress)
- Keduanya menggunakan BOM untuk kalkulasi kebutuhan bahan

## 📁 File SQL yang Sudah Dibuat

1. **`ai_analytics_database_structure.sql`**
   - Dokumentasi lengkap struktur database
   - Relasi antar tabel
   - Alur bisnis dan data aggregation patterns

2. **`ai_query_cache_table.sql`**
   - Tabel untuk caching hasil query AI
   - Monitoring queries untuk cache hit rate
   - Clean expired cache

3. **`ai_precomputed_insights_table.sql`**
   - Tabel untuk pre-computed insights
   - Scheduled reports dan common queries
   - Auto-expiration mechanism

4. **`ai_usage_logs_extended.sql`**
   - Extension untuk `ai_usage_logs` (jika perlu)
   - Track usage per outlet/warehouse
   - Monitoring queries per context type

5. **`ai_analytics_sample_queries.sql`**
   - Sample queries untuk Sales
   - Sample queries untuk Inventory
   - Sample queries untuk BOM
   - Cross-module queries (Sales + Inventory correlation)
   - Demand forecasting queries

## 🎯 Key Insights untuk AI Implementation

### **Sales Analysis**
- Revenue, orders, customers per periode
- Top items, peak hours, payment methods
- Regional analysis, outlet comparison
- Growth trends, seasonality

### **Inventory Analysis**
- Current stock levels per outlet/warehouse
- Stock movements (in/out) history
- Stock turnover rate
- Reorder points, stock alerts
- Cost analysis

### **BOM Analysis**
- Items dengan BOM (composed items)
- Kalkulasi kebutuhan bahan dari sales
- Stock availability untuk bahan baku
- Cost calculation dari bahan baku

### **Cross-Module Analysis**
- Sales vs Stock correlation
- Stock-out impact analysis
- Demand forecasting
- Optimal stock levels based on sales patterns

## 🚀 Next Steps (Setelah Review)

1. **Create Services**
   - `InventoryDataService.php` - Get inventory data
   - `SalesInventoryCorrelationService.php` - Cross-module analysis
   - `AICacheService.php` - Query caching
   - Extend `AIAnalyticsService.php` - Add inventory context

2. **Update AI Prompts**
   - Add inventory data to prompts
   - Add BOM context for composed items
   - Add cross-module analysis instructions

3. **Implement Smart Routing**
   - Simple queries → Gemini (cheaper)
   - Complex queries → Claude (smarter)
   - BOM/Production queries → Claude (complex logic)

4. **Caching Strategy**
   - Exact query cache (30 min)
   - Similar query cache (15 min)
   - Pre-computed insights (1 hour)

5. **Budget & Quota**
   - Per-user quota
   - Per-outlet quota
   - System-wide budget (Rp 2-3 juta/bulan)

## ⚠️ Important Notes

1. **BOM Complexity**: Item dengan BOM perlu analisis khusus karena:
   - Sales item = butuh bahan baku
   - Production = keluar bahan, masuk hasil
   - Cost calculation = dari bahan baku

2. **Multi-Warehouse**: Setiap outlet bisa punya multiple warehouses (`warehouse_outlet_id`)

3. **Stock Movement Types**: Banyak `reference_type` di `outlet_food_inventory_cards` yang perlu dipahami

4. **Data Volume**: Dengan banyak cabang dan gudang, perlu smart aggregation untuk mengurangi token usage

5. **Real-time vs Historical**: 
   - Current stock: `outlet_food_inventory_stocks`
   - Historical movements: `outlet_food_inventory_cards`
   - Sales history: `orders` + `order_items`

## ✅ Ready for Implementation

Semua struktur database sudah dipahami dan didokumentasikan. SQL queries sudah dibuat untuk:
- Tabel AI (cache, precomputed insights, usage logs)
- Sample queries untuk semua modul
- Monitoring dan maintenance queries

**Siap untuk mulai coding!** 🎉

