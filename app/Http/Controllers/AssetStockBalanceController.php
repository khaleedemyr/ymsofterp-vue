<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Outlet;
use App\Models\Item;
use App\Exports\AssetStockBalanceImportTemplateExport;
use App\Imports\AssetStockBalanceImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\AssetInventoryStockService;

class AssetStockBalanceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('asset_inventory_stocks as s')
            ->join('asset_inventory_items as ai', 's.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as oo', 's.owner_outlet_id', '=', 'oo.id_outlet')
            ->join('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('units as us', 'i.small_unit_id', '=', 'us.id')
            ->leftJoin('units as um', 'i.medium_unit_id', '=', 'um.id')
            ->leftJoin('units as ul', 'i.large_unit_id', '=', 'ul.id')
            ->select(
                's.id',
                'i.id as product_id',
                'i.name as product_name',
                'i.sku as product_code',
                'oo.id_outlet as owner_outlet_id',
                'oo.nama_outlet as owner_outlet_name',
                'o.id_outlet',
                'o.nama_outlet as location_outlet_name',
                'wo.id as warehouse_outlet_id',
                'wo.name as warehouse_outlet_name',
                's.qty_small',
                's.qty_medium',
                's.qty_large',
                's.value',
                's.last_cost_small',
                'us.name as unit_name_small',
                'um.name as unit_name_medium',
                'ul.name as unit_name_large',
                's.created_at',
                's.updated_at'
            );

        AssetInventoryStockService::applyOwnerVisibilityForUser($query, $user, 's.owner_outlet_id');

        if ($request->owner_outlet_id) {
            $query->where('s.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->outlet_id) {
            $query->where('s.outlet_id', $request->outlet_id);
        }
        if ($request->warehouse_outlet_id) {
            $query->where('wo.id', $request->warehouse_outlet_id);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('i.name', 'like', "%$search%")
                  ->orWhere('i.sku', 'like', "%$search%")
                  ->orWhere('o.nama_outlet', 'like', "%$search%");
            });
        }

        $stockBalances = $query->orderByDesc('s.created_at')->paginate(10)->withQueryString();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('AssetStockBalances/Index', [
            'stockBalances' => $stockBalances,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'filters' => $request->only(['search', 'owner_outlet_id', 'outlet_id', 'warehouse_outlet_id']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:items,id',
            'owner_outlet_id' => 'required|integer',
            'outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'qty_small' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
        ]);

        AssetInventoryStockService::assertWarehouseBelongsToOutlet(
            (int) $request->warehouse_outlet_id,
            (int) $request->outlet_id
        );

        $locationOutletId = AssetInventoryStockService::resolveLocationOutletId(
            (int) $request->outlet_id,
            (int) $request->warehouse_outlet_id
        );

        $item = DB::table('items')->where('id', $request->product_id)->first();
        if (!$item) {
            return back()->withErrors(['product_id' => 'Item tidak ditemukan']);
        }

        $inventoryItem = DB::table('asset_inventory_items')
            ->where('item_id', $item->id)
            ->first();

        if (!$inventoryItem) {
            $inventoryItemId = DB::table('asset_inventory_items')->insertGetId([
                'item_id' => $item->id,
                'small_unit_id' => $item->small_unit_id,
                'medium_unit_id' => $item->medium_unit_id,
                'large_unit_id' => $item->large_unit_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $inventoryItemId = $inventoryItem->id;
        }

        $smallConv = $item->small_conversion_qty ?: 1;
        $mediumConv = $item->medium_conversion_qty ?: 1;
        $qty_small = (float) $request->qty_small;
        $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
        $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;

        $cost_small = (float) $request->cost;
        $cost_medium = $cost_small * ($item->small_conversion_qty ?: 1);
        $cost_large = $cost_medium * ($item->medium_conversion_qty ?: 1);
        $value = $qty_small * $cost_small;

        $existing = AssetInventoryStockService::findStock(
            $inventoryItemId,
            (int) $request->owner_outlet_id,
            (int) $request->warehouse_outlet_id
        );

        if ($existing) {
            return back()->withErrors(['product_id' => 'Stok untuk kombinasi pemilik/gudang ini sudah ada. Gunakan import atau edit.']);
        }

        DB::table('asset_inventory_stocks')->insert([
            'inventory_item_id' => $inventoryItemId,
            'owner_outlet_id' => $request->owner_outlet_id,
            'outlet_id' => $locationOutletId,
            'warehouse_outlet_id' => $request->warehouse_outlet_id,
            'qty_small' => $qty_small,
            'qty_medium' => $qty_medium,
            'qty_large' => $qty_large,
            'value' => $value,
            'last_cost_small' => $cost_small,
            'last_cost_medium' => $cost_medium,
            'last_cost_large' => $cost_large,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('asset_inventory_cards')->insert([
            'inventory_item_id' => $inventoryItemId,
            'owner_outlet_id' => $request->owner_outlet_id,
            'outlet_id' => $locationOutletId,
            'warehouse_outlet_id' => $request->warehouse_outlet_id,
            'date' => now()->toDateString(),
            'reference_type' => 'initial_balance',
            'reference_id' => 0,
            'in_qty_small' => $qty_small,
            'in_qty_medium' => $qty_medium,
            'in_qty_large' => $qty_large,
            'out_qty_small' => 0, 'out_qty_medium' => 0, 'out_qty_large' => 0,
            'cost_per_small' => $cost_small,
            'cost_per_medium' => $cost_medium,
            'cost_per_large' => $cost_large,
            'value_in' => $value,
            'value_out' => 0,
            'saldo_qty_small' => $qty_small,
            'saldo_qty_medium' => $qty_medium,
            'saldo_qty_large' => $qty_large,
            'saldo_value' => $value,
            'description' => 'Initial Stock Balance Asset',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('asset_inventory_cost_histories')->insert([
            'inventory_item_id' => $inventoryItemId,
            'owner_outlet_id' => $request->owner_outlet_id,
            'outlet_id' => $locationOutletId,
            'warehouse_outlet_id' => $request->warehouse_outlet_id,
            'date' => now()->toDateString(),
            'reference_type' => 'initial_balance',
            'reference_id' => 0,
            'old_cost_small' => 0,
            'old_cost_medium' => 0,
            'old_cost_large' => 0,
            'new_cost_small' => $cost_small,
            'new_cost_medium' => $cost_medium,
            'new_cost_large' => $cost_large,
            'qty' => $qty_small,
            'value' => $value,
            'created_at' => now(),
        ]);

        return redirect()->route('asset-stock-balances.index')
            ->with('success', 'Saldo awal berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'qty_small' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
        ]);

        $stock = DB::table('asset_inventory_stocks as s')
            ->join('asset_inventory_items as ai', 's.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->where('s.id', $id)
            ->select('s.*', 'i.small_conversion_qty', 'i.medium_conversion_qty')
            ->first();

        if (!$stock) {
            return back()->withErrors(['id' => 'Data tidak ditemukan']);
        }

        $smallConv = $stock->small_conversion_qty ?: 1;
        $mediumConv = $stock->medium_conversion_qty ?: 1;
        $qty_small = (float) $request->qty_small;
        $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
        $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;

        $cost_small = (float) $request->cost;
        $cost_medium = $cost_small * ($stock->small_conversion_qty ?: 1);
        $cost_large = $cost_medium * ($stock->medium_conversion_qty ?: 1);
        $value = $qty_small * $cost_small;

        DB::table('asset_inventory_stocks')
            ->where('id', $id)
            ->update([
                'qty_small' => $qty_small,
                'qty_medium' => $qty_medium,
                'qty_large' => $qty_large,
                'value' => $value,
                'last_cost_small' => $cost_small,
                'last_cost_medium' => $cost_medium,
                'last_cost_large' => $cost_large,
                'updated_at' => now(),
            ]);

        return redirect()->route('asset-stock-balances.index')
            ->with('success', 'Saldo awal berhasil diupdate');
    }

    public function destroy($id)
    {
        DB::table('asset_inventory_stocks')->where('id', $id)->delete();

        return redirect()->route('asset-stock-balances.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function downloadTemplate()
    {
        return Excel::download(
            new AssetStockBalanceImportTemplateExport,
            'template_saldo_awal_stok_asset.xlsx'
        );
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            $sheets = Excel::toArray(new AssetStockBalanceImport, $request->file('file'));
            if (!isset($sheets['StockBalance']) || empty($sheets['StockBalance'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sheet StockBalance tidak ditemukan atau kosong'
                ], 422);
            }

            $preview = array_slice($sheets['StockBalance'], 0, 5);
            return response()->json([
                'success' => true,
                'preview' => $preview,
                'total_rows' => count($preview),
                'message' => 'File berhasil dibaca'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            $import = new AssetStockBalanceImport;
            Excel::import($import, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengimport {$import->getSuccessCount()} data",
                'errors' => $import->getErrors(),
                'error_count' => $import->getErrorCount(),
                'success_count' => $import->getSuccessCount(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
