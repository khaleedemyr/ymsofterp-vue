<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryDataService
{
    /**
     * Get current stock levels per outlet
     * 
     * @param array|null $outletIds Filter by outlet IDs
     * @param array|null $itemIds Filter by item IDs
     * @param array|null $warehouseIds Filter by warehouse IDs
     * @return array
     */
    public function getCurrentStockLevels($outletIds = null, $itemIds = null, $warehouseIds = null)
    {
        $query = "
            SELECT 
                o.id_outlet,
                o.nama_outlet,
                o.qr_code as outlet_code,
                r.id as region_id,
                r.name as region_name,
                i.id as item_id,
                i.name as item_name,
                i.sku,
                i.type as item_type,
                ifi.id as inventory_item_id,
                s.warehouse_outlet_id,
                wo.name as warehouse_name,
                s.qty_small,
                s.qty_medium,
                s.qty_large,
                s.value as stock_value,
                s.last_cost_small,
                s.last_cost_medium,
                s.last_cost_large,
                i.min_stock
            FROM outlet_food_inventory_stocks s
            INNER JOIN outlet_food_inventory_items ifi ON s.inventory_item_id = ifi.id
            INNER JOIN items i ON ifi.item_id = i.id
            INNER JOIN tbl_data_outlet o ON s.id_outlet = o.id_outlet
            LEFT JOIN warehouse_outlets wo ON s.warehouse_outlet_id = wo.id
            LEFT JOIN regions r ON o.region_id = r.id
            WHERE (s.qty_small > 0 OR s.qty_medium > 0 OR s.qty_large > 0)
        ";

        $params = [];
        
        if ($outletIds && !empty($outletIds)) {
            $placeholders = implode(',', array_fill(0, count($outletIds), '?'));
            $query .= " AND s.id_outlet IN ({$placeholders})";
            $params = array_merge($params, $outletIds);
        }

        if ($itemIds && !empty($itemIds)) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $query .= " AND i.id IN ({$placeholders})";
            $params = array_merge($params, $itemIds);
        }

        if ($warehouseIds && !empty($warehouseIds)) {
            $placeholders = implode(',', array_fill(0, count($warehouseIds), '?'));
            $query .= " AND s.warehouse_outlet_id IN ({$placeholders})";
            $params = array_merge($params, $warehouseIds);
        }

        $query .= " ORDER BY o.nama_outlet, i.name";

        return DB::select($query, $params);
    }

    /**
     * Get stock movements (in/out transactions)
     * 
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @param array|null $outletIds Filter by outlet IDs
     * @param array|null $itemIds Filter by item IDs
     * @param int|null $limit Limit results
     * @return array
     */
    public function getStockMovements($dateFrom, $dateTo, $outletIds = null, $itemIds = null, $limit = null)
    {
        $query = "
            SELECT 
                c.id,
                c.date,
                o.id_outlet,
                o.nama_outlet,
                i.id as item_id,
                i.name as item_name,
                c.reference_type,
                c.reference_id,
                c.in_qty_small,
                c.in_qty_medium,
                c.in_qty_large,
                c.out_qty_small,
                c.out_qty_medium,
                c.out_qty_large,
                c.saldo_qty_small,
                c.saldo_qty_medium,
                c.saldo_qty_large,
                c.value_in,
                c.value_out,
                c.description
            FROM outlet_food_inventory_cards c
            INNER JOIN outlet_food_inventory_items ifi ON c.inventory_item_id = ifi.id
            INNER JOIN items i ON ifi.item_id = i.id
            INNER JOIN tbl_data_outlet o ON c.id_outlet = o.id_outlet
            WHERE c.date BETWEEN ? AND ?
        ";

        $params = [$dateFrom, $dateTo];

        if ($outletIds && !empty($outletIds)) {
            $placeholders = implode(',', array_fill(0, count($outletIds), '?'));
            $query .= " AND c.id_outlet IN ({$placeholders})";
            $params = array_merge($params, $outletIds);
        }

        if ($itemIds && !empty($itemIds)) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $query .= " AND i.id IN ({$placeholders})";
            $params = array_merge($params, $itemIds);
        }

        $query .= " ORDER BY c.date DESC, c.id DESC";

        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
        }

        return DB::select($query, $params);
    }

    /**
     * Get stock turnover rate per item per outlet
     * 
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @param array|null $outletIds Filter by outlet IDs
     * @param array|null $itemIds Filter by item IDs
     * @return array
     */
    public function getStockTurnover($dateFrom, $dateTo, $outletIds = null, $itemIds = null)
    {
        $query = "
            SELECT 
                o.id_outlet,
                o.nama_outlet,
                i.id as item_id,
                i.name as item_name,
                SUM(CASE WHEN c.out_qty_small > 0 THEN c.out_qty_small ELSE 0 END) as total_out_small,
                SUM(CASE WHEN c.out_qty_medium > 0 THEN c.out_qty_medium ELSE 0 END) as total_out_medium,
                SUM(CASE WHEN c.out_qty_large > 0 THEN c.out_qty_large ELSE 0 END) as total_out_large,
                AVG(s.qty_small) as avg_stock_small,
                AVG(s.qty_medium) as avg_stock_medium,
                AVG(s.qty_large) as avg_stock_large,
                CASE 
                    WHEN AVG(s.qty_small) > 0 THEN SUM(c.out_qty_small) / AVG(s.qty_small)
                    ELSE 0
                END as turnover_rate_small,
                COUNT(DISTINCT DATE(c.date)) as days_with_movement
            FROM outlet_food_inventory_cards c
            INNER JOIN outlet_food_inventory_items ifi ON c.inventory_item_id = ifi.id
            INNER JOIN items i ON ifi.item_id = i.id
            INNER JOIN tbl_data_outlet o ON c.id_outlet = o.id_outlet
            LEFT JOIN outlet_food_inventory_stocks s ON s.inventory_item_id = ifi.id AND s.id_outlet = o.id_outlet
            WHERE c.date BETWEEN ? AND ?
        ";

        $params = [$dateFrom, $dateTo];

        if ($outletIds && !empty($outletIds)) {
            $placeholders = implode(',', array_fill(0, count($outletIds), '?'));
            $query .= " AND c.id_outlet IN ({$placeholders})";
            $params = array_merge($params, $outletIds);
        }

        if ($itemIds && !empty($itemIds)) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $query .= " AND i.id IN ({$placeholders})";
            $params = array_merge($params, $itemIds);
        }

        $query .= " 
            GROUP BY o.id_outlet, o.nama_outlet, i.id, i.name
            HAVING total_out_small > 0 OR total_out_medium > 0 OR total_out_large > 0
            ORDER BY turnover_rate_small DESC
        ";

        return DB::select($query, $params);
    }

    /**
     * Get items that need reorder (below min_stock)
     * 
     * @param array|null $outletIds Filter by outlet IDs
     * @return array
     */
    public function getReorderPoints($outletIds = null)
    {
        $query = "
            SELECT 
                o.id_outlet,
                o.nama_outlet,
                i.id as item_id,
                i.name as item_name,
                i.min_stock,
                s.qty_small,
                s.qty_medium,
                s.qty_large,
                CASE 
                    WHEN i.min_stock > 0 AND s.qty_small < i.min_stock THEN 1
                    ELSE 0
                END as needs_reorder,
                (i.min_stock - s.qty_small) as reorder_qty
            FROM outlet_food_inventory_stocks s
            INNER JOIN outlet_food_inventory_items ifi ON s.inventory_item_id = ifi.id
            INNER JOIN items i ON ifi.item_id = i.id
            INNER JOIN tbl_data_outlet o ON s.id_outlet = o.id_outlet
            WHERE i.min_stock > 0
                AND s.qty_small < i.min_stock
        ";

        $params = [];

        if ($outletIds && !empty($outletIds)) {
            $placeholders = implode(',', array_fill(0, count($outletIds), '?'));
            $query .= " AND s.id_outlet IN ({$placeholders})";
            $params = array_merge($params, $outletIds);
        }

        $query .= " ORDER BY o.nama_outlet, (i.min_stock - s.qty_small) DESC";

        return DB::select($query, $params);
    }

    /**
     * Get stock history for specific item and outlet
     * 
     * @param int $itemId Item ID
     * @param int|null $outletId Outlet ID (optional)
     * @param int $limit Limit results
     * @return array
     */
    public function getStockHistory($itemId, $outletId = null, $limit = 100)
    {
        $query = "
            SELECT 
                c.id,
                c.date,
                o.id_outlet,
                o.nama_outlet,
                c.reference_type,
                c.reference_id,
                c.in_qty_small,
                c.in_qty_medium,
                c.in_qty_large,
                c.out_qty_small,
                c.out_qty_medium,
                c.out_qty_large,
                c.saldo_qty_small,
                c.saldo_qty_medium,
                c.saldo_qty_large,
                c.value_in,
                c.value_out,
                c.description
            FROM outlet_food_inventory_cards c
            INNER JOIN outlet_food_inventory_items ifi ON c.inventory_item_id = ifi.id
            INNER JOIN items i ON ifi.item_id = i.id
            INNER JOIN tbl_data_outlet o ON c.id_outlet = o.id_outlet
            WHERE i.id = ?
        ";

        $params = [$itemId];

        if ($outletId) {
            $query .= " AND c.id_outlet = ?";
            $params[] = $outletId;
        }

        $query .= " ORDER BY c.date DESC, c.id DESC LIMIT ?";
        $params[] = $limit;

        return DB::select($query, $params);
    }

    /**
     * Get inventory summary (aggregated data)
     * 
     * @param array|null $outletIds Filter by outlet IDs
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @return array
     */
    public function getInventorySummary($outletIds = null, $dateFrom = null, $dateTo = null)
    {
        // Current stock summary
        $stockQuery = "
            SELECT 
                COUNT(DISTINCT s.id_outlet) as total_outlets,
                COUNT(DISTINCT s.inventory_item_id) as total_items,
                SUM(s.qty_small) as total_qty_small,
                SUM(s.qty_medium) as total_qty_medium,
                SUM(s.qty_large) as total_qty_large,
                SUM(s.value) as total_stock_value
            FROM outlet_food_inventory_stocks s
            WHERE (s.qty_small > 0 OR s.qty_medium > 0 OR s.qty_large > 0)
        ";

        $stockParams = [];

        if ($outletIds && !empty($outletIds)) {
            $placeholders = implode(',', array_fill(0, count($outletIds), '?'));
            $stockQuery .= " AND s.id_outlet IN ({$placeholders})";
            $stockParams = array_merge($stockParams, $outletIds);
        }

        $stockData = DB::select($stockQuery, $stockParams)[0] ?? null;

        // Movement summary (if date range provided)
        $movementData = null;
        if ($dateFrom && $dateTo) {
            $movementQuery = "
                SELECT 
                    COUNT(*) as total_movements,
                    SUM(c.in_qty_small) as total_in_small,
                    SUM(c.in_qty_medium) as total_in_medium,
                    SUM(c.in_qty_large) as total_in_large,
                    SUM(c.out_qty_small) as total_out_small,
                    SUM(c.out_qty_medium) as total_out_medium,
                    SUM(c.out_qty_large) as total_out_large,
                    SUM(c.value_in) as total_value_in,
                    SUM(c.value_out) as total_value_out
                FROM outlet_food_inventory_cards c
                WHERE c.date BETWEEN ? AND ?
            ";

            $movementParams = [$dateFrom, $dateTo];

            if ($outletIds && !empty($outletIds)) {
                $placeholders = implode(',', array_fill(0, count($outletIds), '?'));
                $movementQuery .= " AND c.id_outlet IN ({$placeholders})";
                $movementParams = array_merge($movementParams, $outletIds);
            }

            $movementData = DB::select($movementQuery, $movementParams)[0] ?? null;
        }

        // Reorder alerts
        $reorderData = $this->getReorderPoints($outletIds);
        $reorderCount = count($reorderData);

        return [
            'stock' => $stockData,
            'movements' => $movementData,
            'reorder_alerts' => [
                'count' => $reorderCount,
                'items' => $reorderData
            ]
        ];
    }

    /**
     * Get BOM data for an item
     * 
     * @param int $itemId Item ID
     * @return array
     */
    public function getItemBom($itemId)
    {
        $query = "
            SELECT 
                b.id as bom_id,
                b.item_id,
                i.name as item_name,
                b.material_item_id,
                mi.name as material_name,
                mi.sku as material_sku,
                b.qty as required_qty,
                u.id as unit_id,
                u.name as unit_name
            FROM item_bom b
            INNER JOIN items i ON b.item_id = i.id
            INNER JOIN items mi ON b.material_item_id = mi.id
            LEFT JOIN units u ON b.unit_id = u.id
            WHERE b.item_id = ?
            ORDER BY mi.name
        ";

        return DB::select($query, [$itemId]);
    }

    /**
     * Get items with BOM (composed items)
     * 
     * @param bool $activeOnly Only active items
     * @return array
     */
    public function getItemsWithBom($activeOnly = true)
    {
        $query = "
            SELECT 
                i.id as item_id,
                i.name as item_name,
                i.sku,
                i.composition_type,
                i.status,
                COUNT(b.id) as bom_material_count
            FROM items i
            INNER JOIN item_bom b ON i.id = b.item_id
            WHERE i.composition_type = 'composed'
        ";

        if ($activeOnly) {
            $query .= " AND i.status = 'active'";
        }

        $query .= " 
            GROUP BY i.id, i.name, i.sku, i.composition_type, i.status
            ORDER BY bom_material_count DESC, i.name
        ";

        return DB::select($query);
    }

    /**
     * Calculate material requirements from sales
     * 
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @param array|null $outletIds Filter by outlet IDs
     * @return array
     */
    public function calculateMaterialRequirementsFromSales($dateFrom, $dateTo, $outletIds = null)
    {
        $query = "
            SELECT 
                oi.item_id,
                i.name as item_name,
                SUM(oi.qty) as total_qty_sold,
                b.material_item_id,
                mi.name as material_name,
                SUM(oi.qty * b.qty) as total_material_needed,
                u.id as unit_id,
                u.name as unit_name
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN items i ON oi.item_id = i.id
            INNER JOIN item_bom b ON i.id = b.item_id
            INNER JOIN items mi ON b.material_item_id = mi.id
            LEFT JOIN units u ON b.unit_id = u.id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND i.composition_type = 'composed'
        ";

        $params = [$dateFrom, $dateTo];

        if ($outletIds && !empty($outletIds)) {
            // Get outlet codes from outlet IDs
            $outletCodes = DB::table('tbl_data_outlet')
                ->whereIn('id_outlet', $outletIds)
                ->pluck('qr_code')
                ->toArray();
            
            if (!empty($outletCodes)) {
                $placeholders = implode(',', array_fill(0, count($outletCodes), '?'));
                $query .= " AND o.kode_outlet IN ({$placeholders})";
                $params = array_merge($params, $outletCodes);
            }
        }

        $query .= " 
            GROUP BY oi.item_id, i.name, b.material_item_id, mi.name, u.id, u.name
            ORDER BY total_material_needed DESC
        ";

        return DB::select($query, $params);
    }

    /**
     * Get current cost per unit untuk inventory items
     * 
     * @param array|null $outletIds Filter by outlet IDs
     * @param array|null $itemIds Filter by item IDs
     * @return array
     */
    public function getCurrentCosts($outletIds = null, $itemIds = null)
    {
        $query = "
            SELECT 
                o.id_outlet,
                o.nama_outlet,
                i.id as item_id,
                i.name as item_name,
                ifi.id as inventory_item_id,
                s.warehouse_outlet_id,
                s.last_cost_small,
                s.last_cost_medium,
                s.last_cost_large,
                s.value as stock_value
            FROM outlet_food_inventory_stocks s
            INNER JOIN outlet_food_inventory_items ifi ON s.inventory_item_id = ifi.id
            INNER JOIN items i ON ifi.item_id = i.id
            INNER JOIN tbl_data_outlet o ON s.id_outlet = o.id_outlet
            WHERE (s.last_cost_small > 0 OR s.last_cost_medium > 0 OR s.last_cost_large > 0)
        ";

        $params = [];

        if ($outletIds && !empty($outletIds)) {
            $placeholders = implode(',', array_fill(0, count($outletIds), '?'));
            $query .= " AND s.id_outlet IN ({$placeholders})";
            $params = array_merge($params, $outletIds);
        }

        if ($itemIds && !empty($itemIds)) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $query .= " AND i.id IN ({$placeholders})";
            $params = array_merge($params, $itemIds);
        }

        $query .= " ORDER BY o.nama_outlet, i.name";

        return DB::select($query, $params);
    }

    /**
     * Calculate menu cost berdasarkan BOM dan material cost
     * Mengikuti logika StockCutController untuk konsistensi
     * 
     * @param int $itemId Item ID (menu item)
     * @param int|null $outletId Outlet ID (optional, untuk cost per outlet)
     * @param string|null $itemType Item type (Food Asian, Food Western, Food, Beverages) untuk menentukan warehouse
     * @return array|null
     */
    public function calculateMenuCost($itemId, $outletId = null, $itemType = null)
    {
        // Get BOM untuk item - TIDAK filter composition_type, ambil semua yang punya BOM
        $bom = $this->getItemBom($itemId);
        
        if (empty($bom)) {
            return null; // Item tidak punya BOM
        }

        $totalCost = 0;
        $materialCosts = [];

        // Tentukan warehouse berdasarkan type item (sama seperti StockCutController)
        $warehouse = null;
        if ($outletId) {
            if (in_array($itemType, ['Food Asian', 'Food Western', 'Food'])) {
                $warehouse = DB::table('warehouse_outlets')
                    ->where('outlet_id', $outletId)
                    ->where('name', 'Kitchen')
                    ->where('status', 'active')
                    ->first();
            } elseif ($itemType == 'Beverages') {
                $warehouse = DB::table('warehouse_outlets')
                    ->where('outlet_id', $outletId)
                    ->where('name', 'Bar')
                    ->where('status', 'active')
                    ->first();
            }
        }

        foreach ($bom as $material) {
            $materialItemId = $material->material_item_id;
            
            // Get inventory_item_id untuk material
            $inventoryItem = DB::table('outlet_food_inventory_items')
                ->where('item_id', $materialItemId)
                ->first();
            
            if (!$inventoryItem) {
                continue; // Skip jika inventory item tidak ditemukan
            }

            // Get cost dari outlet_food_inventory_stocks
            // Jika ada warehouse, ambil dari warehouse tersebut
            // Jika tidak ada warehouse, ambil dari outlet manapun yang punya cost
            $costQuery = "
                SELECT 
                    s.last_cost_small,
                    s.last_cost_medium,
                    s.last_cost_large,
                    s.id_outlet,
                    s.warehouse_outlet_id,
                    CASE 
                        WHEN s.id_outlet = ? AND s.warehouse_outlet_id = ? THEN 1
                        WHEN s.id_outlet = ? THEN 2
                        ELSE 3
                    END as priority
                FROM outlet_food_inventory_stocks s
                WHERE s.inventory_item_id = ?
                    AND (s.last_cost_small > 0 OR s.last_cost_medium > 0 OR s.last_cost_large > 0)
            ";

            $warehouseId = $warehouse ? $warehouse->id : 0;
            $costParams = [$outletId ?? 0, $warehouseId, $outletId ?? 0, $inventoryItem->id];

            // Prioritaskan outlet + warehouse yang sama, lalu outlet yang sama, lalu outlet lain
            $costQuery .= " ORDER BY priority ASC, s.id_outlet ASC LIMIT 1";

            $costData = DB::select($costQuery, $costParams);

            if (!empty($costData)) {
                $cost = $costData[0];
                // Gunakan last_cost_small sebagai default (sama seperti StockCutController)
                $materialCost = $cost->last_cost_small ?? $cost->last_cost_medium ?? $cost->last_cost_large ?? 0;
                // Get required_qty dari BOM
                $requiredQty = $material->qty ?? $material->required_qty ?? 0;
                
                // Hanya tambahkan jika material cost > 0
                if ($materialCost > 0 && $requiredQty > 0) {
                    $materialTotalCost = $materialCost * $requiredQty;
                    $totalCost += $materialTotalCost;

                    $materialCosts[] = [
                        'material_item_id' => $materialItemId,
                        'material_name' => $material->material_name ?? 'N/A',
                        'required_qty' => $requiredQty,
                        'unit_name' => $material->unit_name ?? 'N/A',
                        'cost_per_unit' => $materialCost,
                        'total_cost' => $materialTotalCost
                    ];
                }
            }
        }

        // Hanya return jika total_cost > 0
        if ($totalCost > 0) {
            return [
                'item_id' => $itemId,
                'total_cost' => $totalCost,
                'material_costs' => $materialCosts,
                'outlet_id' => $outletId
            ];
        }

        return null;
    }

    /**
     * Calculate cost dari stock_cut_details (OPTIMIZED - menggunakan data yang sudah dihitung)
     * Ini jauh lebih cepat karena menggunakan data cost yang sudah dihitung oleh Stock Cut
     * 
     * Pendekatan: 
     * 1. Ambil order_items yang sudah stock_cut = 1 (sudah dipotong stock) - ini sudah diagregasi
     * 2. Untuk setiap menu item, ambil cost dari stock_cut_details melalui BOM menggunakan subquery
     * 3. Agregasi cost per menu item
     * 
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @param string|null $outletCode Outlet code (optional) - menggunakan kode_outlet
     * @return array
     */
    public function calculateCostFromStockCut($dateFrom, $dateTo, $outletCode = null)
    {
        // Ambil order_items yang sudah stock_cut = 1 dan agregasi per menu item
        // Gunakan subquery untuk menghitung cost dari stock_cut_details
        $query = "
            SELECT 
                menu_item.id as item_id,
                menu_item.name as item_name,
                menu_item.type as item_type,
                outlet.qr_code as kode_outlet,
                outlet.id_outlet,
                outlet.nama_outlet,
                SUM(oi.qty) as total_qty_sold,
                SUM(oi.subtotal) as total_revenue,
                AVG(oi.price) as avg_selling_price,
                (
                    SELECT SUM(scd.value_out)
                    FROM stock_cut_details scd
                    INNER JOIN stock_cut_logs scl ON scd.stock_cut_log_id = scl.id
                    INNER JOIN item_bom bom ON scd.item_id = bom.material_item_id
                    WHERE scl.outlet_id = outlet.id_outlet
                        AND DATE(scl.tanggal) = DATE(o.created_at)
                        AND scl.status = 'success'
                        AND bom.item_id = menu_item.id
                ) as total_cost
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN items menu_item ON oi.item_id = menu_item.id
            INNER JOIN item_bom bom ON menu_item.id = bom.item_id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND oi.stock_cut = 1
        ";

        $params = [$dateFrom, $dateTo];

        // Filter menggunakan kode_outlet
        if ($outletCode) {
            $query .= " AND o.kode_outlet = ?";
            $params[] = $outletCode;
        }

        $query .= " 
            GROUP BY menu_item.id, menu_item.name, menu_item.type, outlet.qr_code, outlet.id_outlet, outlet.nama_outlet, DATE(o.created_at)
            HAVING total_cost > 0
            ORDER BY total_qty_sold DESC
        ";

        $costData = DB::select($query, $params);

        // Agregasi ulang per menu item (karena bisa ada beberapa tanggal)
        $aggregated = [];
        foreach ($costData as $data) {
            $key = $data->item_id . '_' . $data->id_outlet;
            
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'item_id' => $data->item_id,
                    'item_name' => $data->item_name,
                    'item_type' => $data->item_type,
                    'kode_outlet' => $data->kode_outlet,
                    'id_outlet' => $data->id_outlet,
                    'nama_outlet' => $data->nama_outlet,
                    'total_qty_sold' => 0,
                    'total_revenue' => 0,
                    'total_cost' => 0,
                    'price_sum' => 0,
                    'price_count' => 0
                ];
            }
            
            $aggregated[$key]['total_qty_sold'] += $data->total_qty_sold;
            $aggregated[$key]['total_revenue'] += $data->total_revenue;
            $aggregated[$key]['total_cost'] += ($data->total_cost ?? 0);
            $aggregated[$key]['price_sum'] += ($data->avg_selling_price ?? 0) * $data->total_qty_sold;
            $aggregated[$key]['price_count'] += $data->total_qty_sold;
        }

        $results = [];
        foreach ($aggregated as $data) {
            if ($data['total_cost'] > 0 && $data['total_qty_sold'] > 0) {
                $costPerUnit = $data['total_cost'] / $data['total_qty_sold'];
                $totalRevenue = $data['total_revenue'];
                $totalCost = $data['total_cost'];
                $grossProfit = $totalRevenue - $totalCost;
                $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;
                $costPercentage = $totalRevenue > 0 ? ($totalCost / $totalRevenue) * 100 : 0;
                $avgPrice = $data['price_count'] > 0 ? $data['price_sum'] / $data['price_count'] : 0;

                $results[] = [
                    'item_id' => $data['item_id'],
                    'item_name' => $data['item_name'],
                    'outlet_code' => $data['kode_outlet'],
                    'outlet_id' => $data['id_outlet'],
                    'outlet_name' => $data['nama_outlet'] ?? null,
                    'qty_sold' => $data['total_qty_sold'],
                    'total_revenue' => $totalRevenue,
                    'cost_per_unit' => $costPerUnit,
                    'total_cost' => $totalCost,
                    'gross_profit' => $grossProfit,
                    'gross_margin_percent' => $grossMargin,
                    'cost_percentage' => $costPercentage,
                    'avg_selling_price' => $avgPrice
                ];
            }
        }

        return $results;
    }

    /**
     * Calculate cost untuk multiple items dari sales
     * OPTIMIZED: Menggunakan stock_cut_details jika tersedia, fallback ke perhitungan manual
     * 
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @param string|null $outletCode Outlet code (optional) - menggunakan kode_outlet seperti StockCutController
     * @return array
     */
    public function calculateCostFromSales($dateFrom, $dateTo, $outletCode = null)
    {
        // Cek apakah ada data stock cut untuk periode ini
        $stockCutQuery = DB::table('stock_cut_logs as scl')
            ->join('tbl_data_outlet as outlet', 'scl.outlet_id', '=', 'outlet.id_outlet')
            ->whereBetween('scl.tanggal', [$dateFrom, $dateTo])
            ->where('scl.status', 'success');
        
        if ($outletCode) {
            $stockCutQuery->where('outlet.qr_code', $outletCode);
        }
        
        $hasStockCutData = $stockCutQuery->exists();

        // Jika ada data stock cut, gunakan method yang dioptimalkan
        if ($hasStockCutData) {
            return $this->calculateCostFromStockCut($dateFrom, $dateTo, $outletCode);
        }

        // Fallback: Hitung manual (untuk data yang belum di-stock cut)
        // Get sales data untuk items dengan BOM
        // PENTING: Mengikuti logika StockCutController
        // 1. Gunakan kode_outlet (qr_code) bukan id_outlet untuk filter
        // 2. Ambil semua item yang punya BOM, bukan hanya composition_type = 'composed'
        // 3. Join dengan tbl_data_outlet untuk mendapatkan id_outlet yang benar
        // 4. Hanya ambil order_items yang sudah stock_cut = 1 (sudah dipotong stock)
        $query = "
            SELECT 
                oi.item_id,
                i.name as item_name,
                i.type as item_type,
                o.kode_outlet,
                outlet.id_outlet,
                outlet.nama_outlet,
                SUM(oi.qty) as total_qty_sold,
                SUM(oi.subtotal) as total_revenue,
                AVG(oi.price) as avg_selling_price,
                COUNT(DISTINCT bom.id) as bom_count
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN items i ON oi.item_id = i.id
            LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
            INNER JOIN item_bom bom ON i.id = bom.item_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
                AND oi.stock_cut = 1
        ";

        $params = [$dateFrom, $dateTo];

        // Filter menggunakan kode_outlet seperti StockCutController
        if ($outletCode) {
            $query .= " AND o.kode_outlet = ?";
            $params[] = $outletCode;
        }

        $query .= " 
            GROUP BY oi.item_id, i.name, i.type, o.kode_outlet, outlet.id_outlet, outlet.nama_outlet
            HAVING bom_count > 0
            ORDER BY total_qty_sold DESC
        ";

        $salesData = DB::select($query, $params);

        $results = [];
        foreach ($salesData as $sale) {
            // Coba hitung cost dengan outlet_id dan item_type yang benar
            $outletId = $sale->id_outlet ?? null;
            $itemType = $sale->item_type ?? null;
            $costData = $this->calculateMenuCost($sale->item_id, $outletId, $itemType);
            
            // Jika cost data null, coba tanpa outlet_id (ambil cost dari outlet manapun)
            if (!$costData && $outletId) {
                $costData = $this->calculateMenuCost($sale->item_id, null, $itemType);
            }
            
            if ($costData && $costData['total_cost'] > 0) {
                $totalCost = $costData['total_cost'] * $sale->total_qty_sold;
                $totalRevenue = $sale->total_revenue;
                $grossProfit = $totalRevenue - $totalCost;
                $grossMargin = $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0;
                $costPercentage = $totalRevenue > 0 ? ($totalCost / $totalRevenue) * 100 : 0;

                $results[] = [
                    'item_id' => $sale->item_id,
                    'item_name' => $sale->item_name,
                    'outlet_code' => $sale->kode_outlet,
                    'outlet_id' => $outletId,
                    'outlet_name' => $sale->nama_outlet ?? null,
                    'qty_sold' => $sale->total_qty_sold,
                    'total_revenue' => $totalRevenue,
                    'cost_per_unit' => $costData['total_cost'],
                    'total_cost' => $totalCost,
                    'gross_profit' => $grossProfit,
                    'gross_margin_percent' => $grossMargin,
                    'cost_percentage' => $costPercentage,
                    'avg_selling_price' => $sale->avg_selling_price
                ];
            }
        }

        return $results;
    }
}

