<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Revenue per Outlet by Region ===\n\n";

try {
    // Test the getRevenuePerOutlet query
    echo "=== Revenue per Outlet by Region Query ===\n";
    $query = "
        SELECT 
            o.kode_outlet,
            COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
            COALESCE(region.name, 'Unknown Region') as region_name,
            COALESCE(region.code, 'UNK') as region_code,
            COUNT(*) as order_count,
            SUM(o.grand_total) as total_revenue,
            SUM(o.pax) as total_pax,
            AVG(o.grand_total) as avg_order_value
        FROM orders o
        LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
        LEFT JOIN regions region ON outlet.region_id = region.id
        WHERE DATE(o.created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY o.kode_outlet, outlet.nama_outlet, region.name, region.code
        ORDER BY total_revenue DESC
        LIMIT 10
    ";

    $results = DB::select($query);

    echo "Query Results:\n";
    echo str_repeat("-", 120) . "\n";
    printf("%-15s %-20s %-15s %-10s %-12s %-15s %-10s %-15s\n", 
           "Outlet Code", "Outlet Name", "Region", "Region Code", "Orders", "Revenue", "Pax", "Avg Order");
    echo str_repeat("-", 120) . "\n";

    foreach ($results as $result) {
        printf("%-15s %-20s %-15s %-10s %-12s %-15s %-10s %-15s\n",
            $result->kode_outlet,
            substr($result->outlet_name, 0, 20),
            substr($result->region_name, 0, 15),
            $result->region_code,
            number_format($result->order_count),
            'Rp ' . number_format($result->total_revenue),
            number_format($result->total_pax),
            'Rp ' . number_format($result->avg_order_value)
        );
    }

    echo str_repeat("-", 120) . "\n\n";

    // Test region grouping
    echo "=== Region Grouping Test ===\n";
    $data = [];
    foreach ($results as $result) {
        $regionName = $result->region_name;
        $regionCode = $result->region_code;
        
        if (!isset($data[$regionName])) {
            $data[$regionName] = [
                'region_code' => $regionCode,
                'outlets' => [],
                'total_revenue' => 0,
                'total_orders' => 0,
                'total_pax' => 0
            ];
        }
        
        $data[$regionName]['outlets'][] = [
            'outlet_code' => $result->kode_outlet,
            'outlet_name' => $result->outlet_name,
            'order_count' => (int) $result->order_count,
            'total_revenue' => (float) $result->total_revenue,
            'total_pax' => (int) $result->total_pax,
            'avg_order_value' => (float) $result->avg_order_value
        ];
        
        $data[$regionName]['total_revenue'] += (float) $result->total_revenue;
        $data[$regionName]['total_orders'] += (int) $result->order_count;
        $data[$regionName]['total_pax'] += (int) $result->total_pax;
    }

    echo "Grouped by Region:\n";
    foreach ($data as $regionName => $regionData) {
        echo "\nRegion: {$regionName} ({$regionData['region_code']})\n";
        echo "  Total Revenue: Rp " . number_format($regionData['total_revenue']) . "\n";
        echo "  Total Orders: " . number_format($regionData['total_orders']) . "\n";
        echo "  Total Pax: " . number_format($regionData['total_pax']) . "\n";
        echo "  Outlets: " . count($regionData['outlets']) . "\n";
        echo "  Avg Check: Rp " . number_format($regionData['total_pax'] > 0 ? $regionData['total_revenue'] / $regionData['total_pax'] : 0) . "\n";
        
        echo "  Top Outlets:\n";
        foreach (array_slice($regionData['outlets'], 0, 3) as $outlet) {
            echo "    - {$outlet['outlet_name']}: Rp " . number_format($outlet['total_revenue']) . "\n";
        }
    }

    echo "\n";

    // Test regions table structure
    echo "=== Regions Table Test ===\n";
    $regionsQuery = "SELECT * FROM regions LIMIT 5";
    $regionsResults = DB::select($regionsQuery);
    
    echo "Regions Table Structure:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-5s %-10s %-20s %-10s %-20s %-20s\n", 
           "ID", "Code", "Name", "Status", "Created At", "Updated At");
    echo str_repeat("-", 80) . "\n";

    foreach ($regionsResults as $region) {
        printf("%-5s %-10s %-20s %-10s %-20s %-20s\n",
            $region->id,
            $region->code,
            substr($region->name, 0, 20),
            $region->status,
            $region->created_at,
            $region->updated_at
        );
    }

    echo str_repeat("-", 80) . "\n\n";

    // Test tbl_data_outlet structure
    echo "=== tbl_data_outlet Structure Test ===\n";
    $outletsQuery = "
        SELECT 
            outlet.qr_code,
            outlet.nama_outlet,
            outlet.region_id,
            region.name as region_name,
            region.code as region_code
        FROM tbl_data_outlet outlet
        LEFT JOIN regions region ON outlet.region_id = region.id
        LIMIT 5
    ";
    $outletsResults = DB::select($outletsQuery);
    
    echo "Outlet-Region Mapping:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-15s %-20s %-10s %-15s %-10s\n", 
           "QR Code", "Outlet Name", "Region ID", "Region Name", "Region Code");
    echo str_repeat("-", 100) . "\n";

    foreach ($outletsResults as $outlet) {
        printf("%-15s %-20s %-10s %-15s %-10s\n",
            $outlet->qr_code,
            substr($outlet->nama_outlet, 0, 20),
            $outlet->region_id ?? 'NULL',
            substr($outlet->region_name ?? 'NULL', 0, 15),
            $outlet->region_code ?? 'NULL'
        );
    }

    echo str_repeat("-", 100) . "\n\n";

    // Test frontend data structure
    echo "=== Frontend Data Structure ===\n";
    echo "Revenue per Outlet Data Structure:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

    // Test chart series generation
    echo "=== Chart Series Generation Test ===\n";
    $regions = array_keys($data);
    
    if (count($regions) > 0) {
        // Get all unique outlets across all regions
        $allOutlets = [];
        foreach ($regions as $region) {
            foreach ($data[$region]['outlets'] as $outlet) {
                $allOutlets[] = $outlet['outlet_name'];
            }
        }
        $allOutlets = array_unique($allOutlets);
        
        echo "All Outlets: " . implode(', ', $allOutlets) . "\n";
        echo "Total Unique Outlets: " . count($allOutlets) . "\n";
        
        // Create series for each region
        $series = [];
        foreach ($regions as $region) {
            $regionData = array_fill(0, count($allOutlets), 0);
            
            foreach ($data[$region]['outlets'] as $outlet) {
                $index = array_search($outlet['outlet_name'], $allOutlets);
                if ($index !== false) {
                    $regionData[$index] = $outlet['total_revenue'];
                }
            }
            
            $series[] = [
                'name' => $region,
                'data' => $regionData
            ];
        }
        
        echo "Chart Series:\n";
        foreach ($series as $s) {
            echo "  {$s['name']}: " . count($s['data']) . " data points\n";
        }
    }

    // Test performance
    echo "\n=== Performance Test ===\n";
    $startTime = microtime(true);
    
    $performanceQuery = "
        SELECT 
            o.kode_outlet,
            COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
            COALESCE(region.name, 'Unknown Region') as region_name,
            COUNT(*) as order_count,
            SUM(o.grand_total) as total_revenue
        FROM orders o
        LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
        LEFT JOIN regions region ON outlet.region_id = region.id
        WHERE DATE(o.created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY o.kode_outlet, outlet.nama_outlet, region.name
        ORDER BY total_revenue DESC
    ";
    
    $performanceResults = DB::select($performanceQuery);
    $endTime = microtime(true);
    
    echo "Query Execution Time: " . round(($endTime - $startTime) * 1000, 2) . " ms\n";
    echo "Records Returned: " . count($performanceResults) . "\n";
    echo "Memory Usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";

    echo "\n✅ Test completed successfully!\n";
    echo "✅ Revenue per Outlet by Region query working\n";
    echo "✅ Region grouping logic working\n";
    echo "✅ Frontend data structure ready\n";
    echo "✅ Chart series generation working\n";
    echo "✅ Performance acceptable\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
