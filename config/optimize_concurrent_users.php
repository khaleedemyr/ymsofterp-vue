<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Concurrent Users Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk mengoptimasi performa saat multiple users
    | menggunakan menu packing list dan delivery order bersamaan
    |
    */

    'database' => [
        // Connection pool settings
        'pool_size' => 20, // Maksimal 20 koneksi database
        'timeout' => 30,   // Timeout 30 detik
        
        // Query optimization
        'query_cache' => true,
        'query_cache_ttl' => 300, // 5 menit cache
    ],

    'memory' => [
        // Memory limit untuk operasi berat
        'limit' => '1G',
        'peak_limit' => '2G',
    ],

    'execution' => [
        // Timeout untuk operasi yang berat
        'timeout' => 300, // 5 menit
        'max_execution_time' => 300,
    ],

    'caching' => [
        // Cache untuk data yang sering diakses
        'warehouse_divisions' => 3600, // 1 jam
        'outlets' => 1800,             // 30 menit
        'items' => 1800,               // 30 menit
        'units' => 3600,               // 1 jam
    ],

    'queue' => [
        // Queue untuk operasi yang berat
        'enabled' => true,
        'connection' => 'database',
        'queue' => 'packing_do_operations',
    ],

    'logging' => [
        // Logging untuk monitoring performa
        'enabled' => true,
        'level' => 'info',
        'slow_query_threshold' => 1000, // Log query > 1 detik
    ],
];
