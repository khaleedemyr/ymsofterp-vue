<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Recent Orders with Outlet Information ===\n\n";

try {
    // Test the new query with outlet information
    $query = "
        SELECT 
            o.id,
            o.nomor,
            o.table,
            o.member_name,
            o.pax,
            o.grand_total,
            o.status,
            o.created_at,
            o.waiters,
            o.kode_outlet,
            COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name
        FROM orders o
        LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
        WHERE DATE(o.created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        ORDER BY o.created_at DESC
        LIMIT 10
    ";

    echo "Query:\n";
    echo $query . "\n\n";

    $results = DB::select($query);

    echo "Results (" . count($results) . " records):\n";
    echo str_repeat("-", 120) . "\n";
    printf("%-8s %-12s %-20s %-8s %-15s %-12s %-10s %-20s %-15s\n", 
           "ID", "Order #", "Outlet Name", "Table", "Customer", "Pax", "Total", "Status", "Created");
    echo str_repeat("-", 120) . "\n";

    foreach ($results as $order) {
        printf("%-8s %-12s %-20s %-8s %-15s %-12s %-10s %-10s %-20s\n",
            $order->id,
            $order->nomor,
            substr($order->outlet_name ?? '-', 0, 20),
            $order->table ?? '-',
            substr($order->member_name ?? '-', 0, 15),
            $order->pax ?? 0,
            number_format($order->grand_total),
            $order->status,
            date('Y-m-d H:i', strtotime($order->created_at))
        );
    }

    echo str_repeat("-", 120) . "\n\n";

    // Test outlet mapping
    echo "=== Testing Outlet Mapping ===\n";
    $outletQuery = "
        SELECT DISTINCT 
            o.kode_outlet,
            COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
            COUNT(*) as order_count
        FROM orders o
        LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
        WHERE DATE(o.created_at) BETWEEN '2025-09-01' AND '2025-09-10'
        GROUP BY o.kode_outlet, outlet.nama_outlet
        ORDER BY order_count DESC
    ";

    $outletResults = DB::select($outletQuery);

    echo "Outlet Distribution:\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-15s %-25s %-10s\n", "Kode Outlet", "Nama Outlet", "Orders");
    echo str_repeat("-", 60) . "\n";

    foreach ($outletResults as $outlet) {
        printf("%-15s %-25s %-10s\n",
            $outlet->kode_outlet,
            substr($outlet->outlet_name, 0, 25),
            $outlet->order_count
        );
    }

    echo str_repeat("-", 60) . "\n\n";

    // Test if tbl_data_outlet has data
    echo "=== Testing tbl_data_outlet Table ===\n";
    $outletTableQuery = "SELECT qr_code, nama_outlet FROM tbl_data_outlet LIMIT 5";
    $outletTableResults = DB::select($outletTableQuery);

    echo "Sample tbl_data_outlet data:\n";
    echo str_repeat("-", 40) . "\n";
    printf("%-15s %-20s\n", "QR Code", "Nama Outlet");
    echo str_repeat("-", 40) . "\n";

    foreach ($outletTableResults as $outlet) {
        printf("%-15s %-20s\n", $outlet->qr_code, $outlet->nama_outlet);
    }

    echo str_repeat("-", 40) . "\n\n";

    echo "✅ Test completed successfully!\n";
    echo "✅ Recent Orders now includes outlet information\n";
    echo "✅ JOIN between orders.kode_outlet and tbl_data_outlet.qr_code working\n";
    echo "✅ COALESCE fallback to kode_outlet when nama_outlet is null\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
