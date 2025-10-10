<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PackingDOCacheService
{
    /**
     * Cache warehouse divisions dengan TTL yang aman
     */
    public function getWarehouseDivisions()
    {
        return Cache::remember('warehouse_divisions', 3600, function () {
            return DB::table('warehouse_division')
                ->select('id', 'name', 'warehouse_id')
                ->orderBy('name')
                ->get();
        });
    }
    
    /**
     * Cache outlets dengan TTL yang aman
     */
    public function getOutlets()
    {
        return Cache::remember('outlets_active', 1800, function () {
            return DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet')
                ->orderBy('nama_outlet')
                ->get();
        });
    }
    
    /**
     * Cache items dengan warehouse division
     */
    public function getItemsByDivision($divisionId)
    {
        return Cache::remember("items_division_{$divisionId}", 1800, function () use ($divisionId) {
            return DB::table('items as i')
                ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
                ->where('i.warehouse_division_id', $divisionId)
                ->select(
                    'i.id',
                    'i.name',
                    'i.sku',
                    'i.small_conversion_qty',
                    'i.medium_conversion_qty',
                    'u_small.name as small_unit_name',
                    'u_medium.name as medium_unit_name',
                    'u_large.name as large_unit_name'
                )
                ->get();
        });
    }
    
    /**
     * Cache stock data untuk item (TTL pendek karena data sering berubah)
     */
    public function getItemStock($itemId, $warehouseId)
    {
        return Cache::remember("item_stock_{$itemId}_{$warehouseId}", 300, function () use ($itemId, $warehouseId) {
            return DB::table('food_inventory_stocks as fis')
                ->join('food_inventory_items as fii', 'fis.inventory_item_id', '=', 'fii.id')
                ->where('fii.item_id', $itemId)
                ->where('fis.warehouse_id', $warehouseId)
                ->select('fis.*')
                ->first();
        });
    }
    
    /**
     * Clear cache untuk operasi tertentu
     */
    public function clearCache($type = null)
    {
        if ($type === 'warehouse_divisions') {
            Cache::forget('warehouse_divisions');
        } elseif ($type === 'outlets') {
            Cache::forget('outlets_active');
        } elseif ($type === 'items') {
            // Clear semua cache items
            $divisions = $this->getWarehouseDivisions();
            foreach ($divisions as $division) {
                Cache::forget("items_division_{$division->id}");
            }
        } elseif ($type === 'stocks') {
            // Clear stock cache (akan di-refresh otomatis karena TTL pendek)
            // Tidak perlu clear manual
        } else {
            // Clear semua cache
            Cache::forget('warehouse_divisions');
            Cache::forget('outlets_active');
            $divisions = $this->getWarehouseDivisions();
            foreach ($divisions as $division) {
                Cache::forget("items_division_{$division->id}");
            }
        }
    }
    
    /**
     * Preload cache untuk data yang sering digunakan
     */
    public function preloadCache()
    {
        try {
            // Preload warehouse divisions
            $this->getWarehouseDivisions();
            
            // Preload outlets
            $this->getOutlets();
            
            // Preload items untuk setiap division (maksimal 5 division untuk menghindari overload)
            $divisions = $this->getWarehouseDivisions()->take(5);
            foreach ($divisions as $division) {
                $this->getItemsByDivision($division->id);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to preload cache: ' . $e->getMessage());
        }
    }
}