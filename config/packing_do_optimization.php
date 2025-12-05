<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Packing List & Delivery Order Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Konfigurasi optimasi performa untuk menu packing list dan delivery order
    | tanpa mengubah fungsi existing
    |
    */

    'enabled' => env('PACKING_DO_OPTIMIZATION_ENABLED', true),

    'cache' => [
        // Cache untuk data yang sering diakses
        'warehouse_divisions' => [
            'enabled' => true,
            'ttl' => 3600, // 1 jam
        ],
        'outlets' => [
            'enabled' => true,
            'ttl' => 1800, // 30 menit
        ],
        'items' => [
            'enabled' => true,
            'ttl' => 1800, // 30 menit
        ],
    ],

    'query_optimization' => [
        // Optimasi query yang diaktifkan
        'batch_queries' => true,
        'eager_loading' => true,
        'raw_queries_for_reports' => true,
    ],

    'performance' => [
        // Setting performa
        'memory_limit' => '512M',
        'max_execution_time' => 300, // 5 menit
        'query_timeout' => 30, // 30 detik
    ],

    'logging' => [
        // Logging untuk monitoring
        'enabled' => true,
        'slow_query_threshold' => 1000, // Log query > 1 detik
        'log_memory_usage' => true,
    ],
];
