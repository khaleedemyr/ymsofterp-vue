<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rnfId = 12333;

$payments = DB::table('non_food_payment_retail_non_food as link')
    ->join('non_food_payments as nfp', 'nfp.id', '=', 'link.non_food_payment_id')
    ->where('link.retail_non_food_id', $rnfId)
    ->select('nfp.id', 'nfp.payment_number', 'nfp.status', 'nfp.amount', 'nfp.payment_date')
    ->get();

echo "Linked non_food_payments:\n";
echo json_encode($payments, JSON_PRETTY_PRINT) . "\n";

$available = DB::table('retail_non_food as rnf')
    ->where('rnf.id', $rnfId)
    ->where('rnf.payment_method', 'contra_bon')
    ->where('rnf.status', 'approved')
    ->whereNull('rnf.deleted_at')
    ->exists();

echo "\nWould appear in Non Food Payment > Create (contra_bon RNF list): " . ($available ? 'yes (if not already paid)' : 'no') . "\n";
