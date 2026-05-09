<?php

namespace App\Http\Controllers;

use App\Models\Repack;
use App\Models\Item;
use App\Models\ItemBarcode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepackController extends Controller
{
    public function apiIndex(Request $request)
    {
        $query = Repack::with(['itemAsal', 'itemHasil', 'creator']);
        if ($request->search) {
            $query->where('repack_number', 'like', '%' . $request->search . '%');
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $perPage = (int) $request->get('per_page', 20);
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function apiShow($id)
    {
        $repack = Repack::with(['itemAsal', 'itemHasil', 'creator'])->find($id);
        if (!$repack) {
            return response()->json(['success' => false, 'message' => 'Data repack tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'repack' => $repack,
        ]);
    }

    public function index(Request $request)
    {
        $query = Repack::with(['itemAsal', 'itemHasil', 'creator']);
        if ($request->search) {
            $query->where('repack_number', 'like', '%' . $request->search . '%');
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        $repacks = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        return Inertia::render('Repack/Index', [
            'user' => Auth::user(),
            'repacks' => $repacks,
        ]);
    }

    public function create()
    {
        $items = \App\Models\Item::with(['smallUnit', 'mediumUnit', 'largeUnit'])->get();
        $units = \App\Models\Unit::all();
        $warehouses = \App\Models\Warehouse::where('status', 'active')->get();
        return \Inertia\Inertia::render('Repack/Form', [
            'items' => $items,
            'units' => $units,
            'warehouses' => $warehouses,
            'user' => \Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_asal_id' => 'required|exists:items,id',
            'item_hasil_id' => 'required|exists:items,id',
            'unit_hasil_id' => 'required|exists:units,id',
            'qty_hasil' => 'required|numeric|min:0.0001',
        ]);

        $warehouseId = (int) $request->warehouse_id;
        $qtyHasil = (float) $request->qty_hasil;

        $itemAsal = Item::findOrFail($request->item_asal_id);
        $itemHasil = Item::findOrFail($request->item_hasil_id);

        $qtyHasilConv = $this->convertQtyByUnit($itemHasil, (int) $request->unit_hasil_id, $qtyHasil);
        $qtyAsalConv = $this->convertSmallToAll($itemAsal, $qtyHasilConv['small']);

        DB::beginTransaction();
        try {
            $inventoryAsal = DB::table('food_inventory_items')->where('item_id', $itemAsal->id)->first();
            if (!$inventoryAsal) {
                return response()->json(['message' => 'Inventory item asal belum tersedia.'], 422);
            }

            $inventoryHasil = DB::table('food_inventory_items')->where('item_id', $itemHasil->id)->first();
            if (!$inventoryHasil) {
                $inventoryHasilId = DB::table('food_inventory_items')->insertGetId([
                    'item_id' => $itemHasil->id,
                    'small_unit_id' => $itemHasil->small_unit_id,
                    'medium_unit_id' => $itemHasil->medium_unit_id,
                    'large_unit_id' => $itemHasil->large_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inventoryHasil = DB::table('food_inventory_items')->where('id', $inventoryHasilId)->first();
            }

            $stockAsal = DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventoryAsal->id)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if (!$stockAsal || (float) $stockAsal->qty_small < $qtyAsalConv['small']) {
                return response()->json([
                    'message' => 'Stok item asal tidak cukup untuk repack.'
                ], 422);
            }

            $costSmall = (float) ($stockAsal->last_cost_small ?? 0);
            $costMedium = (float) ($stockAsal->last_cost_medium ?? 0);
            $costLarge = (float) ($stockAsal->last_cost_large ?? 0);

            // Hardening: fallback cost by conversion when medium/large not populated.
            $smallConvAsal = (float) ($itemAsal->small_conversion_qty ?: 1);
            $mediumConvAsal = (float) ($itemAsal->medium_conversion_qty ?: 1);
            if ($costMedium <= 0 && $costSmall > 0) {
                $costMedium = $costSmall * $smallConvAsal;
            }
            if ($costLarge <= 0 && $costMedium > 0) {
                $costLarge = $costMedium * $mediumConvAsal;
            }

            $newAsalQtySmall = (float) $stockAsal->qty_small - $qtyAsalConv['small'];
            $newAsalQtyMedium = (float) ($stockAsal->qty_medium ?? 0) - $qtyAsalConv['medium'];
            $newAsalQtyLarge = (float) ($stockAsal->qty_large ?? 0) - $qtyAsalConv['large'];
            $newAsalValue = (float) ($stockAsal->value ?? 0) - ($qtyAsalConv['small'] * $costSmall);

            DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventoryAsal->id)
                ->where('warehouse_id', $warehouseId)
                ->update([
                    'qty_small' => $newAsalQtySmall,
                    'qty_medium' => $newAsalQtyMedium,
                    'qty_large' => $newAsalQtyLarge,
                    'value' => $newAsalValue,
                    'updated_at' => now(),
                ]);

            $stockHasil = DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventoryHasil->id)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if ($stockHasil) {
                $newHasilQtySmall = (float) $stockHasil->qty_small + $qtyHasilConv['small'];
                $newHasilQtyMedium = (float) ($stockHasil->qty_medium ?? 0) + $qtyHasilConv['medium'];
                $newHasilQtyLarge = (float) ($stockHasil->qty_large ?? 0) + $qtyHasilConv['large'];
                $newHasilValue = (float) ($stockHasil->value ?? 0) + ($qtyHasilConv['small'] * $costSmall);

                DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryHasil->id)
                    ->where('warehouse_id', $warehouseId)
                    ->update([
                        'qty_small' => $newHasilQtySmall,
                        'qty_medium' => $newHasilQtyMedium,
                        'qty_large' => $newHasilQtyLarge,
                        'value' => $newHasilValue,
                        'last_cost_small' => $costSmall,
                        'last_cost_medium' => $costMedium,
                        'last_cost_large' => $costLarge,
                        'updated_at' => now(),
                    ]);
            } else {
                $newHasilQtySmall = $qtyHasilConv['small'];
                $newHasilQtyMedium = $qtyHasilConv['medium'];
                $newHasilQtyLarge = $qtyHasilConv['large'];
                $newHasilValue = $qtyHasilConv['small'] * $costSmall;

                DB::table('food_inventory_stocks')->insert([
                    'inventory_item_id' => $inventoryHasil->id,
                    'warehouse_id' => $warehouseId,
                    'qty_small' => $newHasilQtySmall,
                    'qty_medium' => $newHasilQtyMedium,
                    'qty_large' => $newHasilQtyLarge,
                    'value' => $newHasilValue,
                    'last_cost_small' => $costSmall,
                    'last_cost_medium' => $costMedium,
                    'last_cost_large' => $costLarge,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $repack = Repack::create([
                'repack_number' => 'RP-' . date('Ymd') . '-' . Str::upper(Str::random(4)),
                'item_asal_id' => $request->item_asal_id,
                'unit_asal_id' => $itemAsal->small_unit_id,
                'qty_asal' => $qtyAsalConv['small'],
                'item_hasil_id' => $request->item_hasil_id,
                'unit_hasil_id' => $request->unit_hasil_id,
                'qty_hasil' => $request->qty_hasil,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            DB::table('food_inventory_cards')->insert([
                [
                    'inventory_item_id' => $inventoryAsal->id,
                    'warehouse_id' => $warehouseId,
                    'date' => now()->toDateString(),
                    'reference_type' => 'repack',
                    'reference_id' => $repack->id,
                    'out_qty_small' => $qtyAsalConv['small'],
                    'out_qty_medium' => $qtyAsalConv['medium'],
                    'out_qty_large' => $qtyAsalConv['large'],
                    'cost_per_small' => $costSmall,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_out' => $qtyAsalConv['small'] * $costSmall,
                    'saldo_qty_small' => $newAsalQtySmall,
                    'saldo_qty_medium' => $newAsalQtyMedium,
                    'saldo_qty_large' => $newAsalQtyLarge,
                    'saldo_value' => $newAsalValue,
                    'description' => 'Repack OUT ' . ($itemAsal->name ?? ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'inventory_item_id' => $inventoryHasil->id,
                    'warehouse_id' => $warehouseId,
                    'date' => now()->toDateString(),
                    'reference_type' => 'repack',
                    'reference_id' => $repack->id,
                    'in_qty_small' => $qtyHasilConv['small'],
                    'in_qty_medium' => $qtyHasilConv['medium'],
                    'in_qty_large' => $qtyHasilConv['large'],
                    'cost_per_small' => $costSmall,
                    'cost_per_medium' => $costMedium,
                    'cost_per_large' => $costLarge,
                    'value_in' => $qtyHasilConv['small'] * $costSmall,
                    'saldo_qty_small' => $newHasilQtySmall,
                    'saldo_qty_medium' => $newHasilQtyMedium,
                    'saldo_qty_large' => $newHasilQtyLarge,
                    'saldo_value' => $newHasilValue,
                    'description' => 'Repack IN ' . ($itemHasil->name ?? ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $barcodes = [];
            $serialRows = [];
            $serialCount = (int) round($qtyHasil);
            if ($serialCount > 0 && abs($qtyHasil - $serialCount) < 0.00001) {
                for ($i = 0; $i < $serialCount; $i++) {
                    $barcode = 'BRC-' . ($itemHasil->sku ?: 'ITEM') . '-' . Str::upper(Str::random(6));
                    $serial = $this->generateUniqueSerialNumber();

                    $barcodes[] = [
                        'item_id' => $request->item_hasil_id,
                        'barcode' => $barcode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $serialRows[] = [
                        'source_type' => 'repack',
                        'source_id' => $repack->id,
                        'source_item_id' => $repack->id,
                        'warehouse_id' => $warehouseId,
                        'inventory_item_id' => $inventoryHasil->id,
                        'item_id' => $itemHasil->id,
                        'unit_id' => $request->unit_hasil_id,
                        'serial_number' => $serial,
                        'source_qty' => $qtyHasil,
                        'source_unit_id' => $request->unit_hasil_id,
                        'generated_qty_unit' => $qtyHasil,
                        'cost_small' => $costSmall,
                        'cost_medium' => $costMedium,
                        'cost_large' => $costLarge,
                        'generated_by' => Auth::id(),
                        'generated_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($barcodes)) {
                ItemBarcode::insert($barcodes);
            }
            if (!empty($serialRows)) {
                DB::table('inventory_item_serials')->insert($serialRows);
            }

            DB::commit();

            return response()->json([
                'message' => 'Repack berhasil disimpan dan stok sudah terintegrasi.',
                'repack' => $repack,
                'barcodes' => $barcodes,
                'serial_total' => count($serialRows),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses repack: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function itemStocks(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
        ]);

        $item = Item::with(['smallUnit', 'mediumUnit', 'largeUnit'])->findOrFail($request->item_id);
        $inventoryItem = DB::table('food_inventory_items')->where('item_id', $item->id)->first();

        if (!$inventoryItem) {
            return response()->json([
                'qty_small' => 0,
                'qty_medium' => 0,
                'qty_large' => 0,
                'small_unit_name' => $item->smallUnit->name ?? null,
                'medium_unit_name' => $item->mediumUnit->name ?? null,
                'large_unit_name' => $item->largeUnit->name ?? null,
            ]);
        }

        $stock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $request->warehouse_id)
            ->first();

        return response()->json([
            'qty_small' => (float) ($stock->qty_small ?? 0),
            'qty_medium' => (float) ($stock->qty_medium ?? 0),
            'qty_large' => (float) ($stock->qty_large ?? 0),
            'small_unit_name' => $item->smallUnit->name ?? null,
            'medium_unit_name' => $item->mediumUnit->name ?? null,
            'large_unit_name' => $item->largeUnit->name ?? null,
        ]);
    }

    public function printBarcodes($repackId)
    {
        $repack = Repack::with(['itemHasil'])->findOrFail($repackId);
        $barcodes = DB::table('inventory_item_serials')
            ->where('source_type', 'repack')
            ->where('source_id', $repack->id)
            ->select('id', 'serial_number as barcode', 'generated_at as created_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('Repack/PrintBarcodes', [
            'repack' => $repack,
            'barcodes' => $barcodes
        ]);
    }

    public function serialSummary($id)
    {
        $total = DB::table('inventory_item_serials')
            ->where('source_type', 'repack')
            ->where('source_id', $id)
            ->count();

        return response()->json(['total' => $total]);
    }

    public function serialList($id)
    {
        $rows = DB::table('inventory_item_serials as s')
            ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
            ->select(
                's.id',
                's.serial_number',
                's.generated_at',
                'u.name as unit_name'
            )
            ->where('s.source_type', 'repack')
            ->where('s.source_id', $id)
            ->orderBy('s.id')
            ->get();

        return response()->json($rows);
    }

    public function rollbackSerials($id)
    {
        $deleted = DB::table('inventory_item_serials')
            ->where('source_type', 'repack')
            ->where('source_id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Rollback serial repack berhasil. Terhapus: {$deleted}",
            'deleted' => $deleted,
        ]);
    }

    // CRUD dasar (store, update, destroy, show) bisa ditambahkan sesuai kebutuhan

    private function convertQtyByUnit(Item $item, int $unitId, float $qty): array
    {
        $smallConv = (float) ($item->small_conversion_qty ?: 1);
        $mediumConv = (float) ($item->medium_conversion_qty ?: 1);

        $qtySmall = 0.0;
        $qtyMedium = 0.0;
        $qtyLarge = 0.0;

        if ($unitId === (int) $item->small_unit_id) {
            $qtySmall = $qty;
            $qtyMedium = $smallConv > 0 ? $qtySmall / $smallConv : 0;
            $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0;
        } elseif ($unitId === (int) $item->medium_unit_id) {
            $qtyMedium = $qty;
            $qtySmall = $qtyMedium * $smallConv;
            $qtyLarge = $mediumConv > 0 ? $qtyMedium / $mediumConv : 0;
        } elseif ($unitId === (int) $item->large_unit_id) {
            $qtyLarge = $qty;
            $qtyMedium = $qtyLarge * $mediumConv;
            $qtySmall = $qtyMedium * $smallConv;
        } else {
            $qtySmall = $qty;
            $qtyMedium = $smallConv > 0 ? $qtySmall / $smallConv : 0;
            $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0;
        }

        return [
            'small' => $qtySmall,
            'medium' => $qtyMedium,
            'large' => $qtyLarge,
        ];
    }

    private function generateUniqueSerialNumber(): string
    {
        $prefix = 'R' . now()->format('ymdHi');

        for ($i = 0; $i < 10; $i++) {
            $serial = $prefix . strtoupper(Str::random(4));
            $exists = DB::table('inventory_item_serials')
                ->where('serial_number', $serial)
                ->exists();
            if (!$exists) {
                return $serial;
            }
        }

        return $prefix . strtoupper(Str::random(6));
    }

    private function convertSmallToAll(Item $item, float $qtySmall): array
    {
        $smallConv = (float) ($item->small_conversion_qty ?: 1);
        $mediumConv = (float) ($item->medium_conversion_qty ?: 1);

        $qtyMedium = $smallConv > 0 ? $qtySmall / $smallConv : 0;
        $qtyLarge = ($smallConv > 0 && $mediumConv > 0) ? $qtySmall / ($smallConv * $mediumConv) : 0;

        return [
            'small' => $qtySmall,
            'medium' => $qtyMedium,
            'large' => $qtyLarge,
        ];
    }
} 