<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$outletKeyword = $argv[1] ?? 'Tempayan Citarum';
$targetDate = $argv[2] ?? '2026-03-03';

function printHeader(string $title): void
{
    echo "\n============================================================\n";
    echo $title . "\n";
    echo "============================================================\n";
}

function formatMoney(float $value): string
{
    return 'Rp ' . number_format($value, 2, ',', '.');
}

printHeader('TRACE OUTLET PAYMENT - TANPA COA');
echo "Outlet keyword : {$outletKeyword}\n";
echo "Tanggal target : {$targetDate}\n";

$outlet = DB::table('tbl_data_outlet')
    ->where('nama_outlet', 'like', '%' . $outletKeyword . '%')
    ->select('id_outlet', 'nama_outlet')
    ->first();

if (!$outlet) {
    echo "Outlet tidak ditemukan untuk keyword: {$outletKeyword}\n";
    exit(1);
}

echo "Outlet ketemu   : {$outlet->id_outlet} | {$outlet->nama_outlet}\n";

printHeader('A. GR (outlet_food_good_receives) - tanggal receive_date');

$grRows = DB::table('outlet_food_good_receives as gr')
    ->leftJoin('outlet_payments as op', function ($join) {
        $join->on('gr.id', '=', 'op.gr_id')->where('op.status', '!=', 'cancelled');
    })
    ->where('gr.outlet_id', $outlet->id_outlet)
    ->whereDate('gr.receive_date', '=', $targetDate)
    ->whereNull('gr.deleted_at')
    ->select('gr.id', 'gr.number', 'gr.receive_date', 'op.id as payment_id')
    ->orderBy('gr.id')
    ->get();

if ($grRows->isEmpty()) {
    echo "Tidak ada GR pada tanggal target.\n";
} else {
    foreach ($grRows as $gr) {
        $status = $gr->payment_id ? 'SUDAH DIBAYAR' : 'BELUM DIBAYAR';
        echo "- GR {$gr->id} | {$gr->number} | {$gr->receive_date} | {$status}\n";

        $items = DB::table('outlet_food_good_receive_items as gri')
            ->join('items as i', 'gri.item_id', '=', 'i.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('chart_of_accounts as coa', 'sc.coa_id', '=', 'coa.id')
            ->join('outlet_food_good_receives as gr2', 'gri.outlet_food_good_receive_id', '=', 'gr2.id')
            ->join('delivery_orders as do', 'gr2.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_floor_order_items as foi', function ($join) {
                $join->on('gri.item_id', '=', 'foi.item_id')
                    ->on('do.floor_order_id', '=', 'foi.floor_order_id');
            })
            ->where('gri.outlet_food_good_receive_id', $gr->id)
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'sc.id as sub_category_id',
                'sc.name as sub_category_name',
                'sc.coa_id as sub_category_coa_id',
                'coa.code as coa_code',
                'coa.name as coa_name',
                DB::raw('COALESCE(gri.received_qty * foi.price, 0) as subtotal')
            )
            ->get();

        $total = (float) $items->sum('subtotal');
        $noCoa = $items->filter(static fn($r) => empty($r->sub_category_coa_id));
        $noCoaTotal = (float) $noCoa->sum('subtotal');

        echo '  Total GR          : ' . formatMoney($total) . "\n";
        echo '  Total Tanpa CoA   : ' . formatMoney($noCoaTotal) . "\n";

        if ($noCoa->isNotEmpty()) {
            echo "  Detail item tanpa CoA:\n";
            foreach ($noCoa as $row) {
                $subcat = $row->sub_category_name ?? 'Tanpa Sub Category';
                echo '    * Item ' . $row->item_id . ' - ' . $row->item_name
                    . ' | SubCat: ' . $subcat
                    . ' | Nilai: ' . formatMoney((float) $row->subtotal) . "\n";
            }
        }
    }
}

printHeader('B. Retail Warehouse Sales - filter seperti endpoint (created_at)');

$rwsRows = DB::table('retail_warehouse_sales as rws')
    ->join('customers as c', 'rws.customer_id', '=', 'c.id')
    ->leftJoin('outlet_payments as op', function ($join) {
        $join->on('rws.id', '=', 'op.retail_sales_id')->where('op.status', '!=', 'cancelled');
    })
    ->where('c.id_outlet', (string) $outlet->id_outlet)
    ->where('c.type', 'branch')
    ->where('rws.status', 'completed')
    ->whereDate('rws.created_at', '=', $targetDate)
    ->select('rws.id', 'rws.number', 'rws.sale_date', 'rws.created_at', 'op.id as payment_id')
    ->orderBy('rws.id')
    ->get();

if ($rwsRows->isEmpty()) {
    echo "Tidak ada Retail Sales (created_at) pada tanggal target.\n";
} else {
    foreach ($rwsRows as $rws) {
        $status = $rws->payment_id ? 'SUDAH DIBAYAR' : 'BELUM DIBAYAR';
        echo "- RWS {$rws->id} | {$rws->number} | sale_date={$rws->sale_date} | created_at={$rws->created_at} | {$status}\n";

        $items = DB::table('retail_warehouse_sale_items as rwsi')
            ->join('items as i', 'rwsi.item_id', '=', 'i.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->leftJoin('chart_of_accounts as coa', 'sc.coa_id', '=', 'coa.id')
            ->where('rwsi.retail_warehouse_sale_id', $rws->id)
            ->select(
                'i.id as item_id',
                'i.name as item_name',
                'sc.id as sub_category_id',
                'sc.name as sub_category_name',
                'sc.coa_id as sub_category_coa_id',
                'coa.code as coa_code',
                'coa.name as coa_name',
                DB::raw('COALESCE(rwsi.subtotal, 0) as subtotal')
            )
            ->get();

        $total = (float) $items->sum('subtotal');
        $noCoa = $items->filter(static fn($r) => empty($r->sub_category_coa_id));
        $noCoaTotal = (float) $noCoa->sum('subtotal');

        echo '  Total RWS         : ' . formatMoney($total) . "\n";
        echo '  Total Tanpa CoA   : ' . formatMoney($noCoaTotal) . "\n";

        if ($noCoa->isNotEmpty()) {
            echo "  Detail item tanpa CoA:\n";
            foreach ($noCoa as $row) {
                $subcat = $row->sub_category_name ?? 'Tanpa Sub Category';
                echo '    * Item ' . $row->item_id . ' - ' . $row->item_name
                    . ' | SubCat: ' . $subcat
                    . ' | Nilai: ' . formatMoney((float) $row->subtotal) . "\n";
            }
        }
    }
}

printHeader('C. Rekap Sub Category yang belum punya CoA (semua sumber di tanggal target)');

$missingSubCats = DB::table('items as i')
    ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
    ->whereNull('sc.coa_id')
    ->select('i.id as item_id', 'i.name as item_name', 'sc.id as sub_category_id', 'sc.name as sub_category_name')
    ->limit(200)
    ->get();

if ($missingSubCats->isEmpty()) {
    echo "Tidak ada item dengan sub category tanpa CoA secara global.\n";
} else {
    echo "Contoh item yang sub category-nya belum mapping CoA (global, max 200):\n";
    foreach ($missingSubCats as $row) {
        $subcat = $row->sub_category_name ?? 'Tanpa Sub Category';
        echo '  - Item ' . $row->item_id . ' - ' . $row->item_name
            . ' | SubCat: ' . $subcat
            . ' (ID: ' . ($row->sub_category_id ?? 'NULL') . ')' . "\n";
    }
}

echo "\nSelesai.\n";
