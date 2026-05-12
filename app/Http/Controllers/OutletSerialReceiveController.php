<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OutletSerialReceiveController extends Controller
{
    public function index()
    {
        return Inertia::render('OutletSerialReceive/Index');
    }

    /**
     * Scan a serial number: validate, auto-detect outlet/warehouse/DO, process inventory, mark received.
     */
    public function scan(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|max:50',
        ]);

        $serialNumber = trim($request->serial_number);
        $user = Auth::user();
        $userOutletId = $user->id_outlet ?? null;

        try {
            return DB::transaction(function () use ($serialNumber, $user, $userOutletId) {
                $serial = DB::table('inventory_item_serials')
                    ->where('serial_number', $serialNumber)
                    ->lockForUpdate()
                    ->first();

                if (!$serial) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nomor seri tidak ditemukan.',
                    ]);
                }

                if (!$serial->is_out) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nomor seri belum di-dispatch (belum keluar via DO).',
                    ]);
                }

                if ($serial->is_received) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nomor seri sudah diterima sebelumnya.',
                    ]);
                }

                $outletId = $serial->out_outlet_id;
                $warehouseOutletId = $serial->out_warehouse_outlet_id;
                $doId = $serial->out_delivery_order_id;

                if (!$outletId || !$warehouseOutletId || !$doId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data outlet/warehouse/DO pada serial tidak lengkap.',
                    ]);
                }

                if ($userOutletId && $userOutletId != '1' && $userOutletId != $outletId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Nomor seri ini ditujukan untuk outlet lain.',
                    ]);
                }

                $doNumber = DB::table('delivery_orders')->where('id', $doId)->value('number') ?? '';

                $itemMaster = DB::table('items')->where('id', $serial->item_id)->first();
                if (!$itemMaster) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Item master tidak ditemukan.',
                    ]);
                }

                $effectiveQty = ($serial->repack_unit_id && $serial->repack_qty > 0)
                    ? (float) $serial->repack_qty
                    : 1;

                $unitId = $serial->unit_id;

                $costSmall = $this->determineCost($serial, $itemMaster, $outletId);

                $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
                $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);

                $qtySmall = 0; $qtyMedium = 0; $qtyLarge = 0;
                if ($unitId == $itemMaster->small_unit_id) {
                    $qtySmall = $effectiveQty;
                    $qtyMedium = $smallConv > 0 ? $qtySmall / $smallConv : 0;
                    $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0;
                } elseif ($unitId == $itemMaster->medium_unit_id) {
                    $qtyMedium = $effectiveQty;
                    $qtySmall = $qtyMedium * $smallConv;
                    $qtyLarge = $mediumConv > 0 ? $qtyMedium / $mediumConv : 0;
                } elseif ($unitId == $itemMaster->large_unit_id) {
                    $qtyLarge = $effectiveQty;
                    $qtyMedium = $qtyLarge * $mediumConv;
                    $qtySmall = $qtyMedium * $smallConv;
                } else {
                    $qtySmall = $effectiveQty;
                    $qtyMedium = $smallConv > 0 ? $qtySmall / $smallConv : 0;
                    $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0;
                }

                $costMedium = $costSmall * $smallConv;
                $costLarge = $costMedium * $mediumConv;
                $qtySmallForValue = $qtySmall;

                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $serial->item_id)
                    ->first();

                if (!$inventoryItem) {
                    $inventoryItemId = DB::table('outlet_food_inventory_items')->insertGetId([
                        'item_id' => $serial->item_id,
                        'small_unit_id' => $itemMaster->small_unit_id,
                        'medium_unit_id' => $itemMaster->medium_unit_id,
                        'large_unit_id' => $itemMaster->large_unit_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $inventoryItemId = $inventoryItem->id;
                }

                $stock = DB::table('outlet_food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->first();

                $qtyLama = $stock ? (float) $stock->qty_small : 0;
                $nilaiLama = $stock ? (float) $stock->value : 0;
                $nilaiBaru = $qtySmallForValue * $costSmall;
                $totalQty = $qtyLama + $qtySmall;
                $totalNilai = $nilaiLama + $nilaiBaru;
                $mac = $totalQty > 0 ? $totalNilai / $totalQty : $costSmall;

                if ($stock) {
                    DB::table('outlet_food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => DB::raw("qty_small + {$qtySmall}"),
                            'qty_medium' => DB::raw("qty_medium + {$qtyMedium}"),
                            'qty_large' => DB::raw("qty_large + {$qtyLarge}"),
                            'value' => DB::raw("value + {$nilaiBaru}"),
                            'last_cost_small' => $mac,
                            'last_cost_medium' => $costMedium,
                            'last_cost_large' => $costLarge,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('outlet_food_inventory_stocks')->insert([
                        'inventory_item_id' => $inventoryItemId,
                        'id_outlet' => $outletId,
                        'warehouse_outlet_id' => $warehouseOutletId,
                        'qty_small' => $qtySmall,
                        'qty_medium' => $qtyMedium,
                        'qty_large' => $qtyLarge,
                        'value' => $nilaiBaru,
                        'last_cost_small' => $mac,
                        'last_cost_medium' => $costMedium,
                        'last_cost_large' => $costLarge,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $lastCard = DB::table('outlet_food_inventory_cards')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->first();

                $saldoQtySmall = ($lastCard ? (float) $lastCard->saldo_qty_small : 0) + $qtySmall;
                $saldoQtyMedium = ($lastCard ? (float) $lastCard->saldo_qty_medium : 0) + $qtyMedium;
                $saldoQtyLarge = ($lastCard ? (float) $lastCard->saldo_qty_large : 0) + $qtyLarge;

                $receiveLogId = DB::table('outlet_serial_receives')->insertGetId([
                    'serial_id' => $serial->id,
                    'serial_number' => $serialNumber,
                    'delivery_order_id' => $doId,
                    'delivery_order_number' => $doNumber,
                    'item_id' => $serial->item_id,
                    'unit_id' => $unitId,
                    'qty' => $effectiveQty,
                    'outlet_id' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'cost_small' => $costSmall,
                    'cost_source' => $serial->source_type === 'good_receive' ? 'fgr_modal_12pct' : 'item_prices',
                    'received_by' => $user->id,
                    'received_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('outlet_food_inventory_cards')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'date' => now()->toDateString(),
                    'reference_type' => 'serial_receive',
                    'reference_id' => $receiveLogId,
                    'in_qty_small' => $qtySmall,
                    'in_qty_medium' => $qtyMedium,
                    'in_qty_large' => $qtyLarge,
                    'out_qty_small' => 0,
                    'out_qty_medium' => 0,
                    'out_qty_large' => 0,
                    'cost_per_small' => $mac,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_in' => $nilaiBaru,
                    'value_out' => 0,
                    'saldo_qty_small' => $saldoQtySmall,
                    'saldo_qty_medium' => $saldoQtyMedium,
                    'saldo_qty_large' => $saldoQtyLarge,
                    'saldo_value' => $saldoQtySmall * $mac,
                    'description' => "Serial Receive: {$serialNumber}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $lastCostHistory = DB::table('outlet_food_inventory_cost_histories')
                    ->where('inventory_item_id', $inventoryItemId)
                    ->where('id_outlet', $outletId)
                    ->where('warehouse_outlet_id', $warehouseOutletId)
                    ->orderByDesc('date')
                    ->orderByDesc('created_at')
                    ->first();

                DB::table('outlet_food_inventory_cost_histories')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'id_outlet' => $outletId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'date' => now()->toDateString(),
                    'old_cost' => $lastCostHistory ? $lastCostHistory->new_cost : 0,
                    'new_cost' => $costSmall,
                    'mac' => $mac,
                    'type' => 'serial_receive',
                    'reference_type' => 'serial_receive',
                    'reference_id' => $receiveLogId,
                    'created_at' => now(),
                ]);

                DB::table('inventory_item_serials')
                    ->where('id', $serial->id)
                    ->update([
                        'is_received' => 1,
                        'received_at' => now(),
                        'received_by' => $user->id,
                        'received_outlet_gr_id' => $receiveLogId,
                        'updated_at' => now(),
                    ]);

                $itemName = $itemMaster->name ?? '';
                $unitName = DB::table('units')->where('id', $unitId)->value('name') ?? '';
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet') ?? $outletId;
                $warehouseName = DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->value('name') ?? '';

                $repackLabel = '';
                if ($serial->repack_unit_id && $serial->repack_qty > 0) {
                    $repackUnitName = DB::table('units')->where('id', $serial->repack_unit_id)->value('name') ?? '';
                    $fmtQty = rtrim(rtrim(number_format((float) $serial->repack_qty, 4, '.', ''), '0'), '.');
                    $repackLabel = "1 {$repackUnitName} = {$fmtQty} {$unitName}";
                }

                return response()->json([
                    'success' => true,
                    'message' => "Serial {$serialNumber} diterima.",
                    'data' => [
                        'id' => $receiveLogId,
                        'serial_number' => $serialNumber,
                        'item_name' => $itemName,
                        'item_id' => $serial->item_id,
                        'unit_name' => $unitName,
                        'qty' => $effectiveQty,
                        'do_number' => $doNumber,
                        'outlet_name' => $outletName,
                        'warehouse_name' => $warehouseName,
                        'cost_small' => round($costSmall, 4),
                        'cost_source' => $serial->source_type === 'good_receive' ? 'FGR (Modal+12%)' : 'Item Price',
                        'repack_label' => $repackLabel,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses serial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine cost_small based on serial source type.
     */
    private function determineCost($serial, $itemMaster, $outletId): float
    {
        if ($serial->source_type === 'good_receive') {
            return (float) ($serial->cost_small ?: 0) * 1.12;
        }

        $itemPrice = DB::table('item_prices')
            ->where('item_id', $serial->item_id)
            ->where('outlet_id', $outletId)
            ->value('price');

        if ($itemPrice && $itemPrice > 0) {
            $priceUnitId = null;
            $priceRow = DB::table('item_prices')
                ->where('item_id', $serial->item_id)
                ->where('outlet_id', $outletId)
                ->first();

            if ($priceRow) {
                return (float) $priceRow->price;
            }
        }

        if ($serial->cost_small && $serial->cost_small > 0) {
            return (float) $serial->cost_small;
        }

        return 0;
    }

    /**
     * History of serial receives for today, filtered by outlet.
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $outletId = $user->id_outlet ?? null;
        $date = $request->input('date', now()->toDateString());

        $query = DB::table('outlet_serial_receives as osr')
            ->leftJoin('items as i', 'i.id', '=', 'osr.item_id')
            ->leftJoin('units as u', 'u.id', '=', 'osr.unit_id')
            ->leftJoin('users as usr', 'usr.id', '=', 'osr.received_by')
            ->select(
                'osr.id',
                'osr.serial_number',
                'osr.delivery_order_number',
                'osr.item_id',
                'i.name as item_name',
                'osr.unit_id',
                'u.name as unit_name',
                'osr.qty',
                'osr.outlet_id',
                'osr.warehouse_outlet_id',
                'osr.cost_small',
                'osr.cost_source',
                'osr.received_at',
                'usr.name as received_by_name'
            )
            ->whereDate('osr.received_at', $date);

        if ($outletId && $outletId != '1') {
            $query->where('osr.outlet_id', $outletId);
        }

        $rows = $query->orderByDesc('osr.id')->limit(500)->get();

        return response()->json($rows);
    }

    /**
     * Rollback a single serial receive: reverse inventory changes, reset serial flags.
     */
    public function rollback($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $receive = DB::table('outlet_serial_receives')->where('id', $id)->first();

                if (!$receive) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data receive tidak ditemukan.',
                    ], 404);
                }

                $serial = DB::table('inventory_item_serials')
                    ->where('id', $receive->serial_id)
                    ->lockForUpdate()
                    ->first();

                if (!$serial || !$serial->is_received) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Serial sudah di-rollback atau tidak ditemukan.',
                    ]);
                }

                $itemMaster = DB::table('items')->where('id', $receive->item_id)->first();
                $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
                $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);
                $unitId = $receive->unit_id;
                $effectiveQty = (float) $receive->qty;
                $costSmall = (float) $receive->cost_small;

                $qtySmall = 0; $qtyMedium = 0; $qtyLarge = 0;
                if ($unitId == $itemMaster->small_unit_id) {
                    $qtySmall = $effectiveQty;
                    $qtyMedium = $smallConv > 0 ? $qtySmall / $smallConv : 0;
                    $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0;
                } elseif ($unitId == $itemMaster->medium_unit_id) {
                    $qtyMedium = $effectiveQty;
                    $qtySmall = $qtyMedium * $smallConv;
                    $qtyLarge = $mediumConv > 0 ? $qtyMedium / $mediumConv : 0;
                } elseif ($unitId == $itemMaster->large_unit_id) {
                    $qtyLarge = $effectiveQty;
                    $qtyMedium = $qtyLarge * $mediumConv;
                    $qtySmall = $qtyMedium * $smallConv;
                } else {
                    $qtySmall = $effectiveQty;
                    $qtyMedium = $smallConv > 0 ? $qtySmall / $smallConv : 0;
                    $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0;
                }

                $nilaiBaru = $qtySmall * $costSmall;
                $inventoryItem = DB::table('outlet_food_inventory_items')
                    ->where('item_id', $receive->item_id)
                    ->first();

                if ($inventoryItem) {
                    $stock = DB::table('outlet_food_inventory_stocks')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $receive->outlet_id)
                        ->where('warehouse_outlet_id', $receive->warehouse_outlet_id)
                        ->first();

                    if ($stock) {
                        $newQtySmall = max(0, (float) $stock->qty_small - $qtySmall);
                        $newValue = max(0, (float) $stock->value - $nilaiBaru);
                        $newMac = $newQtySmall > 0 ? $newValue / $newQtySmall : 0;

                        DB::table('outlet_food_inventory_stocks')
                            ->where('id', $stock->id)
                            ->update([
                                'qty_small' => $newQtySmall,
                                'qty_medium' => max(0, (float) $stock->qty_medium - $qtyMedium),
                                'qty_large' => max(0, (float) $stock->qty_large - $qtyLarge),
                                'value' => $newValue,
                                'last_cost_small' => $newMac,
                                'updated_at' => now(),
                            ]);
                    }

                    $lastCard = DB::table('outlet_food_inventory_cards')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('id_outlet', $receive->outlet_id)
                        ->where('warehouse_outlet_id', $receive->warehouse_outlet_id)
                        ->orderByDesc('date')
                        ->orderByDesc('id')
                        ->first();

                    $saldoQtySmall = ($lastCard ? (float) $lastCard->saldo_qty_small : 0) - $qtySmall;
                    $saldoQtyMedium = ($lastCard ? (float) $lastCard->saldo_qty_medium : 0) - $qtyMedium;
                    $saldoQtyLarge = ($lastCard ? (float) $lastCard->saldo_qty_large : 0) - $qtyLarge;

                    DB::table('outlet_food_inventory_cards')->insert([
                        'inventory_item_id' => $inventoryItem->id,
                        'id_outlet' => $receive->outlet_id,
                        'warehouse_outlet_id' => $receive->warehouse_outlet_id,
                        'date' => now()->toDateString(),
                        'reference_type' => 'serial_receive_rollback',
                        'reference_id' => $receive->id,
                        'in_qty_small' => 0,
                        'in_qty_medium' => 0,
                        'in_qty_large' => 0,
                        'out_qty_small' => $qtySmall,
                        'out_qty_medium' => $qtyMedium,
                        'out_qty_large' => $qtyLarge,
                        'cost_per_small' => $costSmall,
                        'cost_per_medium' => $costSmall * $smallConv,
                        'cost_per_large' => $costSmall * $smallConv * $mediumConv,
                        'value_in' => 0,
                        'value_out' => $nilaiBaru,
                        'saldo_qty_small' => $saldoQtySmall,
                        'saldo_qty_medium' => $saldoQtyMedium,
                        'saldo_qty_large' => $saldoQtyLarge,
                        'saldo_value' => max(0, $saldoQtySmall * ($stock ? ((float) $stock->last_cost_small) : $costSmall)),
                        'description' => "Rollback Serial Receive: {$receive->serial_number}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('inventory_item_serials')
                    ->where('id', $serial->id)
                    ->update([
                        'is_received' => 0,
                        'received_at' => null,
                        'received_by' => null,
                        'received_outlet_gr_id' => null,
                        'updated_at' => now(),
                    ]);

                DB::table('outlet_serial_receives')->where('id', $id)->delete();

                return response()->json([
                    'success' => true,
                    'message' => "Rollback serial {$receive->serial_number} berhasil.",
                ]);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal rollback: ' . $e->getMessage(),
            ], 500);
        }
    }
}
