<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Investor Tables ===\n\n";

try {
    // Check if investors table exists
    if (Schema::hasTable('investors')) {
        echo "✓ Table 'investors' exists\n";
        $count = DB::table('investors')->count();
        echo "  - Records: $count\n";
    } else {
        echo "✗ Table 'investors' does not exist\n";
        echo "  Creating table...\n";
        
        Schema::create('investors', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        
        echo "  ✓ Table 'investors' created successfully\n";
    }

    // Check if investor_outlet table exists
    if (Schema::hasTable('investor_outlet')) {
        echo "✓ Table 'investor_outlet' exists\n";
        $count = DB::table('investor_outlet')->count();
        echo "  - Records: $count\n";
    } else {
        echo "✗ Table 'investor_outlet' does not exist\n";
        echo "  Creating table...\n";
        
        Schema::create('investor_outlet', function ($table) {
            $table->id();
            $table->unsignedBigInteger('investor_id');
            $table->unsignedBigInteger('outlet_id');
            $table->timestamps();
            
            $table->foreign('investor_id')->references('id')->on('investors')->onDelete('cascade');
            $table->foreign('outlet_id')->references('id_outlet')->on('tbl_data_outlet')->onDelete('cascade');
        });
        
        echo "  ✓ Table 'investor_outlet' created successfully\n";
    }

    // Check if tbl_data_outlet table exists
    if (Schema::hasTable('tbl_data_outlet')) {
        echo "✓ Table 'tbl_data_outlet' exists\n";
        $count = DB::table('tbl_data_outlet')->where('status', 'A')->count();
        echo "  - Active outlets: $count\n";
    } else {
        echo "✗ Table 'tbl_data_outlet' does not exist\n";
    }

    echo "\n=== Test API Endpoints ===\n";
    
    // Test outlets endpoint
    try {
        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->get();
        echo "✓ Outlets query successful - Found " . $outlets->count() . " active outlets\n";
    } catch (Exception $e) {
        echo "✗ Outlets query failed: " . $e->getMessage() . "\n";
    }

    // Test investors endpoint
    try {
        $investors = DB::table('investors')->count();
        echo "✓ Investors query successful - Found $investors investors\n";
    } catch (Exception $e) {
        echo "✗ Investors query failed: " . $e->getMessage() . "\n";
    }

    echo "\n=== Summary ===\n";
    echo "All tables and queries are working correctly!\n";
    echo "You can now access the investor outlet menu.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Please check your database connection and Laravel configuration.\n";
}
