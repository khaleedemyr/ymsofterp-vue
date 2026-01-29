<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
}
