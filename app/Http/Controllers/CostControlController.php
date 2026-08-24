<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class CostControlController extends Controller
{
    public function poPriceChangeReport(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $shouldLoad = $request->boolean('load') || $request->filled('date_from') || $request->filled('date_to');

        if (!$shouldLoad) {
            return Inertia::render('CostControl/PoPriceChangeReport', [
                'priceChanges' => [],
                'filters' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
                'loaded' => false,
            ]);
        }

        // Ambil histori harga PO per item (urut terbaru dulu)
        $sub = DB::table('purchase_order_food_items as poi')
            ->join('purchase_order_foods as po', 'poi.purchase_order_food_id', '=', 'po.id')
            ->select(
                'poi.item_id',
                'po.supplier_id',
                'poi.price',
                'po.date',
                'poi.unit_id',
                'poi.id as po_item_id',
                'po.id as po_id'
            )
            ->orderBy('poi.item_id')
            ->orderByDesc('po.date');

        $rows = DB::table(DB::raw('(' . $sub->toSql() . ') as t'))
            ->mergeBindings($sub)
            ->select(
                'item_id',
                DB::raw('GROUP_CONCAT(supplier_id ORDER BY date DESC) as supplier_ids'),
                DB::raw('GROUP_CONCAT(price ORDER BY date DESC) as prices'),
                DB::raw('GROUP_CONCAT(unit_id ORDER BY date DESC) as unit_ids'),
                DB::raw('GROUP_CONCAT(date ORDER BY date DESC) as dates')
            )
            ->groupBy('item_id')
            ->get();

        // Ambil semua large_unit_id yang dipakai
        $largeUnitIds = [];
        foreach ($rows as $row) {
            $item = Item::find($row->item_id);
            if ($item && $item->large_unit_id) {
                $largeUnitIds[] = $item->large_unit_id;
            }
        }
        $unitNames = [];
        if ($largeUnitIds) {
            $units = DB::table('units')->whereIn('id', $largeUnitIds)->pluck('name', 'id');
            $unitNames = $units->toArray();
        }

        $result = [];
        foreach ($rows as $row) {
            $supplierIds = explode(',', $row->supplier_ids);
            $prices = explode(',', $row->prices);
            $unitIds = explode(',', $row->unit_ids);
            $dates = explode(',', $row->dates ?? '');

            if (count($prices) < 2 || count($supplierIds) < 2 || count($unitIds) < 2) {
                continue;
            }

            $latestDate = $dates[0] ?? null;

            // Opsi 1: tampilkan jika PO terbaru masuk rentang tanggal
            if ($dateFrom && $latestDate && $latestDate < $dateFrom) {
                continue;
            }
            if ($dateTo && $latestDate && $latestDate > $dateTo) {
                continue;
            }

            $item = Item::find($row->item_id);
            if (!$item) {
                continue;
            }

            $supplierAwal = isset($supplierIds[1]) ? Supplier::find($supplierIds[1]) : null;
            $supplierBaru = isset($supplierIds[0]) ? Supplier::find($supplierIds[0]) : null;
            $hargaAwal = isset($prices[1]) ? (float) $prices[1] : 0;
            $hargaBaru = isset($prices[0]) ? (float) $prices[0] : 0;
            $unitAwal = isset($unitIds[1]) ? (int) $unitIds[1] : null;
            $unitBaru = isset($unitIds[0]) ? (int) $unitIds[0] : null;

            // Konversi harga ke satuan large
            $hargaAwalLarge = $hargaAwal;
            $hargaBaruLarge = $hargaBaru;
            if ($unitAwal && $unitAwal != $item->large_unit_id) {
                if ($unitAwal == $item->medium_unit_id && $item->medium_conversion_qty > 0) {
                    $hargaAwalLarge = $hargaAwal * $item->medium_conversion_qty;
                } elseif ($unitAwal == $item->small_unit_id && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                    $hargaAwalLarge = $hargaAwal * $item->small_conversion_qty * $item->medium_conversion_qty;
                }
            }
            if ($unitBaru && $unitBaru != $item->large_unit_id) {
                if ($unitBaru == $item->medium_unit_id && $item->medium_conversion_qty > 0) {
                    $hargaBaruLarge = $hargaBaru * $item->medium_conversion_qty;
                } elseif ($unitBaru == $item->small_unit_id && $item->small_conversion_qty > 0 && $item->medium_conversion_qty > 0) {
                    $hargaBaruLarge = $hargaBaru * $item->small_conversion_qty * $item->medium_conversion_qty;
                }
            }

            if ($hargaAwalLarge == 0) {
                continue;
            }

            $persen = round((($hargaBaruLarge - $hargaAwalLarge) / $hargaAwalLarge) * 100, 2);
            if ($hargaAwalLarge != $hargaBaruLarge) {
                $largeUnitName = ($item->large_unit_id && isset($unitNames[$item->large_unit_id]))
                    ? $unitNames[$item->large_unit_id]
                    : null;

                $result[] = [
                    'item_name' => $item->name ?: '-',
                    'large_unit_name' => $largeUnitName,
                    'supplier_awal' => $supplierAwal ? $supplierAwal->name : '-',
                    'harga_awal' => $hargaAwalLarge,
                    'supplier_baru' => $supplierBaru ? $supplierBaru->name : '-',
                    'harga_baru' => $hargaBaruLarge,
                    'persen' => $persen,
                    'po_date_baru' => $latestDate,
                    'po_date_awal' => $dates[1] ?? null,
                ];
            }
        }

        usort($result, function ($a, $b) {
            return $b['persen'] <=> $a['persen'];
        });

        return Inertia::render('CostControl/PoPriceChangeReport', [
            'priceChanges' => $result,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'loaded' => true,
        ]);
    }
}
