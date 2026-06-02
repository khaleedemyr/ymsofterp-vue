<?php

namespace App\Http\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ReportHelperTrait
{
    /**
     * Get cached outlets list
     * Cache TTL: 1 hour
     */
    protected function getCachedOutlets()
    {
        return Cache::remember('outlets_list', 3600, function() {
            return DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet', 'qr_code', 'is_outlet', 'region_id')
                ->orderBy('nama_outlet')
                ->get();
        });
    }
    
    /**
     * Get cached regions list
     * Cache TTL: 1 hour
     */
    protected function getCachedRegions()
    {
        return Cache::remember('regions_list', 3600, function() {
            return DB::table('regions')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });
    }
    
    /**
     * Get cached warehouses list
     * Cache TTL: 1 hour
     */
    protected function getCachedWarehouses()
    {
        return Cache::remember('warehouses_list', 3600, function() {
            return DB::table('warehouses')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });
    }
    
    /**
     * Get cached categories list
     * Cache TTL: 1 hour
     */
    protected function getCachedCategories()
    {
        return Cache::remember('categories_list', 3600, function() {
            return DB::table('categories')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });
    }
    
    /**
     * Generate cache key for report
     * 
     * @param string $prefix
     * @param array $params
     * @return string
     */
    protected function reportCacheKey($prefix, $params)
    {
        // Remove null values and sort by key for consistent cache keys
        $params = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });
        ksort($params);
        
        return $prefix . '_' . md5(json_encode($params));
    }
    
    /**
     * Get user's outlet QR code
     * Returns null if user is HO (id_outlet = 1)
     * 
     * @return string|null
     */
    protected function getMyOutletQr()
    {
        $user = auth()->user();
        
        if (!$user || $user->id_outlet == 1) {
            return null;
        }
        
        return Cache::remember('outlet_qr_' . $user->id_outlet, 3600, function() use ($user) {
            return DB::table('tbl_data_outlet')
                ->where('id_outlet', $user->id_outlet)
                ->value('qr_code');
        });
    }
    
    /**
     * Get user's outlet ID
     * Returns null if user is HO (id_outlet = 1)
     * 
     * @return int|null
     */
    protected function getMyOutletId()
    {
        $user = auth()->user();
        
        if (!$user || $user->id_outlet == 1) {
            return null;
        }
        
        return $user->id_outlet;
    }
    
    /**
     * Check if current user is HO (Head Office)
     * 
     * @return bool
     */
    protected function isHOUser()
    {
        $user = auth()->user();
        return $user && $user->id_outlet == 1;
    }
    
    /**
     * Get cached warehouse names for filter dropdowns
     * Cache TTL: 1 hour
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getCachedWarehouseNames()
    {
        return Cache::remember('warehouse_names_list', 3600, function() {
            return DB::table('warehouses')
                ->select('name')
                ->orderBy('name')
                ->get();
        });
    }
    
    /**
     * Get cached category names for filter dropdowns
     * Cache TTL: 1 hour
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getCachedCategoryNames()
    {
        return Cache::remember('category_names_list', 3600, function() {
            return DB::table('categories')
                ->select('name')
                ->orderBy('name')
                ->get();
        });
    }
    
    /**
     * Get cached outlet names for filter dropdowns
     * Cache TTL: 1 hour
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getCachedOutletNames()
    {
        return Cache::remember('outlet_names_list', 3600, function() {
            return DB::table('tbl_data_outlet')
                ->select('nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        });
    }
    
    /**
     * Get cached years from receive_date for filter dropdowns
     * Cache TTL: 1 hour
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getCachedReceiveDateYears()
    {
        return Cache::remember('receive_date_years_list', 3600, function() {
            return DB::table('outlet_food_good_receives')
                ->select(DB::raw('DISTINCT YEAR(receive_date) as tahun'))
                ->orderBy('tahun', 'desc')
                ->pluck('tahun');
        });
    }
    
    /**
     * Get cached active outlets (status = 'A') for filter dropdowns
     * Cache TTL: 1 hour
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getCachedActiveOutlets()
    {
        return Cache::remember('active_outlets_list', 3600, function() {
            return DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get();
        });
    }
    
    /**
     * Get cached active outlets with id and name only
     * Cache TTL: 1 hour
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getCachedActiveOutletsIdName()
    {
        return Cache::remember('active_outlets_id_name_list', 3600, function() {
            return DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        });
    }
    
    /**
     * Get cached outlet QR codes by region
     * Cache TTL: 1 hour
     * 
     * @param int|null $regionId
     * @return \Illuminate\Support\Collection
     */
    protected function getCachedOutletQrCodesByRegion($regionId = null)
    {
        $cacheKey = $regionId ? "outlet_qr_codes_region_{$regionId}" : 'outlet_qr_codes_all';
        
        return Cache::remember($cacheKey, 3600, function() use ($regionId) {
            $query = DB::table('tbl_data_outlet');
            
            if ($regionId) {
                $query->where('region_id', $regionId);
            }
            
            return $query->pluck('qr_code');
        });
    }
    
    /**
     * Get cached outlet name by QR code
     * Cache TTL: 1 hour
     * 
     * @param string $qrCode
     * @return string|null
     */
    protected function getCachedOutletNameByQrCode($qrCode)
    {
        return Cache::remember("outlet_name_qr_{$qrCode}", 3600, function() use ($qrCode) {
            return DB::table('tbl_data_outlet')
                ->where('qr_code', $qrCode)
                ->value('nama_outlet');
        });
    }

    /**
     * Tabel GR Serial outlet (opsional — aman jika belum di-migrate).
     */
    protected function rekapFjHasSerialGrTables(): bool
    {
        return Schema::hasTable('outlet_serial_receive_headers')
            && Schema::hasTable('outlet_serial_receive_items');
    }

    /**
     * Baris pivot Rekap FJ dari GR Food (harga food_floor_order) — logic tidak diubah.
     */
    protected function rekapFjFetchFoodGrPivotItemRows(?string $from, ?string $to): Collection
    {
        $nonPosPriceExpr = "(SELECT ipx.price
            FROM item_prices ipx
            WHERE ipx.item_id = i.item_id
              AND ipx.availability_price_type = 'all'
              AND ipx.region_id IS NULL
              AND ipx.outlet_id IS NULL
            ORDER BY ipx.id DESC
            LIMIT 1)";
        $effectivePriceExpr = "COALESCE(
            CASE
                WHEN cat.show_pos = '0' AND cat.is_asset = '0' THEN {$nonPosPriceExpr}
                ELSE fo.price
            END,
            fo.price,
            0
        )";

        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->leftJoin('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->whereNull('gr.deleted_at')
            ->whereNotNull('w.name')
            ->select(
                'o.nama_outlet as customer',
                'o.is_outlet',
                'it.id as item_id',
                'it.name as item_name',
                'sc.name as sub_category',
                'w.name as warehouse',
                DB::raw("SUM(i.received_qty * {$effectivePriceExpr}) as item_subtotal")
            );

        if ($from) {
            $query->whereDate('gr.receive_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('gr.receive_date', '<=', $to);
        }

        return $query->groupBy('o.nama_outlet', 'o.is_outlet', 'it.id', 'it.name', 'sc.name', 'w.name')->get();
    }

    /**
     * Baris pivot Rekap FJ dari GR Nomor Seri (harga cost_small tersimpan saat GR).
     */
    protected function rekapFjFetchSerialGrPivotItemRows(?string $from, ?string $to): Collection
    {
        if (!$this->rekapFjHasSerialGrTables()) {
            return collect();
        }

        $nonPosPriceExpr = "(SELECT ipx.price
            FROM item_prices ipx
            WHERE ipx.item_id = si.item_id
              AND ipx.availability_price_type = 'all'
              AND ipx.region_id IS NULL
              AND ipx.outlet_id IS NULL
            ORDER BY ipx.id DESC
            LIMIT 1)";
        $effectivePriceExpr = "COALESCE(
            CASE
                WHEN cat.show_pos = '0' AND cat.is_asset = '0' THEN {$nonPosPriceExpr}
                ELSE si.cost_small
            END,
            si.cost_small,
            0
        )";

        $query = DB::table('outlet_serial_receive_headers as h')
            ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->whereNull('h.deleted_at')
            ->whereNotNull('w.name')
            ->select(
                'o.nama_outlet as customer',
                'o.is_outlet',
                'it.id as item_id',
                'it.name as item_name',
                'sc.name as sub_category',
                'w.name as warehouse',
                DB::raw("SUM(si.qty * {$effectivePriceExpr}) as item_subtotal")
            );

        if ($from) {
            $query->whereDate('h.receive_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('h.receive_date', '<=', $to);
        }

        return $query->groupBy('o.nama_outlet', 'o.is_outlet', 'it.id', 'it.name', 'sc.name', 'w.name')->get();
    }

    /**
     * Gabungkan baris item (Food + Serial) lalu agregasi per outlet — kategorisasi sama seperti sebelumnya.
     *
     * @param  Collection|iterable  $itemRows
     * @return array<string, object>
     */
    protected function rekapFjAggregatePivotItemRowsByOutlet($itemRows): array
    {
        $report1 = [];
        foreach ($itemRows as $item) {
            $key = $item->customer;
            if (!isset($report1[$key])) {
                $report1[$key] = (object) [
                    'customer' => $item->customer,
                    'is_outlet' => $item->is_outlet,
                    'main_kitchen' => 0,
                    'main_store' => 0,
                    'chemical' => 0,
                    'stationary' => 0,
                    'marketing' => 0,
                    'line_total' => 0,
                ];
            }

            $subtotal = (float) $item->item_subtotal;
            $warehouse = $item->warehouse ? trim($item->warehouse) : null;
            $subCategory = $item->sub_category ? trim($item->sub_category) : null;

            if ($warehouse && in_array($warehouse, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen'], true)) {
                $report1[$key]->main_kitchen += $subtotal;
            } elseif ($warehouse && strtoupper($warehouse) === 'MAIN STORE') {
                if ($subCategory && strtoupper($subCategory) === 'CHEMICAL') {
                    $report1[$key]->chemical += $subtotal;
                } elseif ($subCategory && strtoupper($subCategory) === 'STATIONARY') {
                    $report1[$key]->stationary += $subtotal;
                } elseif ($subCategory && strtoupper($subCategory) === 'MARKETING') {
                    $report1[$key]->marketing += $subtotal;
                } else {
                    $report1[$key]->main_store += $subtotal;
                }
            }

            $report1[$key]->line_total += $subtotal;
        }

        return $report1;
    }

    /**
     * Detail FJ per outlet — GR Food (floor order price).
     */
    protected function rekapFjFetchFoodGrDetailRows(
        string $customer,
        string $from,
        string $to,
        $warehouseCondition,
        $subCategoryCondition = null,
        ?array $excludeSubCategories = null
    ): Collection {
        $nonPosPriceExpr = "(SELECT ipx.price
            FROM item_prices ipx
            WHERE ipx.item_id = i.item_id
              AND ipx.availability_price_type = 'all'
              AND ipx.region_id IS NULL
              AND ipx.outlet_id IS NULL
            ORDER BY ipx.id DESC
            LIMIT 1)";
        $effectivePriceExpr = "COALESCE(
            CASE
                WHEN cat.show_pos = '0' AND cat.is_asset = '0' THEN {$nonPosPriceExpr}
                ELSE fo.price
            END,
            fo.price,
            0
        )";

        $query = DB::table('outlet_food_good_receives as gr')
            ->join('outlet_food_good_receive_items as i', 'gr.id', '=', 'i.outlet_food_good_receive_id')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->join('units as u', 'i.unit_id', '=', 'u.id')
            ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as fo', function ($join) {
                $join->on('i.item_id', '=', 'fo.item_id')
                    ->on('fo.floor_order_id', '=', 'do.floor_order_id');
            })
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('tbl_data_outlet as o', 'gr.outlet_id', '=', 'o.id_outlet')
            ->where('o.nama_outlet', $customer)
            ->whereDate('gr.receive_date', '>=', $from)
            ->whereDate('gr.receive_date', '<=', $to)
            ->whereNull('gr.deleted_at');

        $this->rekapFjApplyWarehouseSubCategoryFilters($query, $warehouseCondition, $subCategoryCondition, $excludeSubCategories, 'w', 'sc');

        return $query->select(
            'it.name as item_name',
            'cat.name as category',
            'u.name as unit',
            DB::raw('SUM(i.received_qty) as received_qty'),
            DB::raw("AVG({$effectivePriceExpr}) as price"),
            DB::raw("SUM(i.received_qty * {$effectivePriceExpr}) as subtotal")
        )
            ->groupBy('it.name', 'cat.name', 'u.name')
            ->orderBy('cat.name')
            ->orderBy('it.name')
            ->get();
    }

    /**
     * Detail FJ per outlet — GR Nomor Seri (cost_small).
     */
    protected function rekapFjFetchSerialGrDetailRows(
        string $customer,
        string $from,
        string $to,
        $warehouseCondition,
        $subCategoryCondition = null,
        ?array $excludeSubCategories = null
    ): Collection {
        if (!$this->rekapFjHasSerialGrTables()) {
            return collect();
        }

        $nonPosPriceExpr = "(SELECT ipx.price
            FROM item_prices ipx
            WHERE ipx.item_id = si.item_id
              AND ipx.availability_price_type = 'all'
              AND ipx.region_id IS NULL
              AND ipx.outlet_id IS NULL
            ORDER BY ipx.id DESC
            LIMIT 1)";
        $effectivePriceExpr = "COALESCE(
            CASE
                WHEN cat.show_pos = '0' AND cat.is_asset = '0' THEN {$nonPosPriceExpr}
                ELSE si.cost_small
            END,
            si.cost_small,
            0
        )";

        $query = DB::table('outlet_serial_receive_headers as h')
            ->join('outlet_serial_receive_items as si', 'h.id', '=', 'si.header_id')
            ->join('items as it', 'si.item_id', '=', 'it.id')
            ->join('categories as cat', 'it.category_id', '=', 'cat.id')
            ->join('sub_categories as sc', 'it.sub_category_id', '=', 'sc.id')
            ->leftJoin('units as u', 'si.unit_id', '=', 'u.id')
            ->leftJoin('warehouse_division as wd', 'it.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('warehouses as w', 'wd.warehouse_id', '=', 'w.id')
            ->join('tbl_data_outlet as o', 'h.outlet_id', '=', 'o.id_outlet')
            ->where('o.nama_outlet', $customer)
            ->whereDate('h.receive_date', '>=', $from)
            ->whereDate('h.receive_date', '<=', $to)
            ->whereNull('h.deleted_at')
            ->whereNotNull('w.name');

        $this->rekapFjApplyWarehouseSubCategoryFilters($query, $warehouseCondition, $subCategoryCondition, $excludeSubCategories, 'w', 'sc');

        return $query->select(
            'it.name as item_name',
            'cat.name as category',
            'u.name as unit',
            DB::raw('SUM(si.qty) as received_qty'),
            DB::raw("AVG({$effectivePriceExpr}) as price"),
            DB::raw("SUM(si.qty * {$effectivePriceExpr}) as subtotal")
        )
            ->groupBy('it.name', 'cat.name', 'u.name')
            ->orderBy('cat.name')
            ->orderBy('it.name')
            ->get();
    }

    /**
     * Gabungkan baris detail Food + Serial per item (qty & subtotal dijumlahkan).
     */
    protected function rekapFjMergeFjDetailRows(Collection $foodRows, Collection $serialRows): Collection
    {
        $merged = [];

        foreach ($foodRows->concat($serialRows) as $row) {
            $key = ($row->item_name ?? '') . '|' . ($row->category ?? '') . '|' . ($row->unit ?? '');
            if (!isset($merged[$key])) {
                $merged[$key] = (object) [
                    'item_name' => $row->item_name,
                    'category' => $row->category,
                    'unit' => $row->unit,
                    'received_qty' => 0,
                    'price' => 0,
                    'subtotal' => 0,
                    'source' => 'GR',
                ];
            }
            $merged[$key]->received_qty += (float) ($row->received_qty ?? 0);
            $merged[$key]->subtotal += (float) ($row->subtotal ?? 0);
        }

        $result = collect(array_values($merged));
        $result->each(function ($row) {
            $qty = (float) $row->received_qty;
            $row->price = $qty > 0 ? $row->subtotal / $qty : 0;
        });

        return $result->sortBy('category')->sortBy('item_name')->values();
    }

    /**
     * Filter warehouse / sub-category untuk query detail Rekap FJ.
     */
    protected function rekapFjApplyWarehouseSubCategoryFilters(
        $query,
        $warehouseCondition,
        $subCategoryCondition,
        ?array $excludeSubCategories,
        string $warehouseAlias = 'w',
        string $subCategoryAlias = 'sc'
    ): void {
        if (is_array($warehouseCondition)) {
            $query->whereIn("{$warehouseAlias}.name", $warehouseCondition);
        } else {
            $query->where("{$warehouseAlias}.name", $warehouseCondition);
        }

        if ($subCategoryCondition) {
            if (is_array($subCategoryCondition)) {
                $query->whereIn("{$subCategoryAlias}.name", $subCategoryCondition);
            } else {
                $query->where("{$subCategoryAlias}.name", $subCategoryCondition);
            }
        }

        if ($excludeSubCategories) {
            $query->whereNotIn("{$subCategoryAlias}.name", $excludeSubCategories);
        }
    }
}
