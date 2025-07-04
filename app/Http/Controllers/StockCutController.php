<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockCutController extends Controller
{
    /**
     * Potong stock berdasarkan order_items yang belum dipotong stock (stock_cut = 0)
     * - Kalkulasi kebutuhan bahan baku dari item_bom
     * - Cek stok di outlet_food_inventory_stocks (per outlet & warehouse)
     * - Jika stok kurang, tampilkan list kekurangan
     * - Jika stok cukup, update stok, catat di outlet_food_inventory_cards, update flag stock_cut
     */
    public function potongStockOrderItems(Request $request)
    {
        $tanggal = $request->input('tanggal');
        $id_outlet = $request->input('id_outlet');
        if (!$tanggal || !$id_outlet) {
            return response()->json(['status' => 'error', 'message' => 'Tanggal dan id_outlet wajib diisi'], 422);
        }

        // 1. Ambil order_items yang belum dipotong stock
        $orderItems = DB::table('order_items')
            ->join('items', 'order_items.item_id', '=', 'items.id')
            ->whereDate('order_items.created_at', $tanggal)
            ->where('order_items.kode_outlet', $id_outlet)
            ->where('order_items.stock_cut', 0)
            ->select('order_items.*', 'items.type')
            ->get();

        if ($orderItems->isEmpty()) {
            return response()->json(['status' => 'success', 'message' => 'Tidak ada order_items yang perlu dipotong stock']);
        }

        // 2. Mapping kebutuhan bahan baku & warehouse
        $kebutuhanBahan = [];
        $warehouseMap = [];
        foreach ($orderItems as $oi) {
            // Tentukan warehouse
            if (in_array($oi->type, ['Food Asian', 'Food Western', 'Food'])) {
                $warehouse = DB::table('warehouse_outlets')
                    ->where('outlet_id', $id_outlet)
                    ->where('name', 'Kitchen')
                    ->where('status', 'active')
                    ->first();
            } elseif ($oi->type == 'Beverages') {
                $warehouse = DB::table('warehouse_outlets')
                    ->where('outlet_id', $id_outlet)
                    ->where('name', 'Bar')
                    ->where('status', 'active')
                    ->first();
            } else {
                continue; // skip jika type tidak dikenali
            }
            if (!$warehouse) continue;
            $warehouseMap[$oi->item_id] = $warehouse->id;
            // Ambil BOM
            $boms = DB::table('item_bom')->where('item_id', $oi->item_id)->get();
            foreach ($boms as $bom) {
                $key = $bom->material_item_id . '-' . $warehouse->id;
                $kebutuhanBahan[$key] = ($kebutuhanBahan[$key] ?? 0) + ($bom->qty * $oi->qty);
            }
        }

        // 3. Cek stok
        $kurang = [];
        foreach ($kebutuhanBahan as $key => $qty) {
            [$item_id, $warehouse_id] = explode('-', $key);
            $stock = DB::table('outlet_food_inventory_items')
                ->join('outlet_food_inventory_stocks', 'outlet_food_inventory_items.id', '=', 'outlet_food_inventory_stocks.inventory_item_id')
                ->where('outlet_food_inventory_items.item_id', $item_id)
                ->where('outlet_food_inventory_stocks.id_outlet', $id_outlet)
                ->where('outlet_food_inventory_stocks.warehouse_outlet_id', $warehouse_id)
                ->first();
            if (!$stock || $stock->qty_small < $qty) {
                $kurang[] = [
                    'item_id' => $item_id,
                    'warehouse_id' => $warehouse_id,
                    'kurang' => $qty - ($stock->qty_small ?? 0)
                ];
            }
        }
        if (count($kurang) > 0) {
            return response()->json(['status' => 'error', 'kurang' => $kurang]);
        }

        // 4. Potong stock & catat kartu stok
        foreach ($kebutuhanBahan as $key => $qty) {
            [$item_id, $warehouse_id] = explode('-', $key);
            // Ambil inventory_item_id
            $inventoryItem = DB::table('outlet_food_inventory_items')->where('item_id', $item_id)->first();
            if (!$inventoryItem) continue;
            $inventory_item_id = $inventoryItem->id;
            // Update stok (qty_small saja, sesuaikan jika multi-unit)
            DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('id_outlet', $id_outlet)
                ->where('warehouse_outlet_id', $warehouse_id)
                ->decrement('qty_small', $qty);
            // Catat kartu stok
            DB::table('outlet_food_inventory_cards')->insert([
                'inventory_item_id' => $inventory_item_id,
                'id_outlet' => $id_outlet,
                'warehouse_outlet_id' => $warehouse_id,
                'date' => $tanggal,
                'reference_type' => 'order_items',
                'reference_id' => null,
                'in_qty_small' => 0,
                'out_qty_small' => $qty,
                'saldo_qty_small' => null, // bisa diisi jika ingin update saldo
                'description' => 'Potong stock otomatis dari order_items',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Update flag stock_cut di order_items
        DB::table('order_items')
            ->whereDate('created_at', $tanggal)
            ->where('kode_outlet', $id_outlet)
            ->where('stock_cut', 0)
            ->update(['stock_cut' => 1]);

        return response()->json(['status' => 'success', 'message' => 'Potong stock berhasil']);
    }

    /**
     * API: List log potong stock
     */
    public function getLogs()
    {
        $logs = \DB::table('stock_cut_logs as scl')
            ->join('tbl_data_outlet as o', 'scl.id_outlet', '=', 'o.id_outlet')
            ->join('users as u', 'scl.user_id', '=', 'u.id')
            ->select(
                'scl.id',
                'scl.tanggal',
                'o.nama_outlet as outlet_name',
                'u.nama_lengkap as user_name',
                'scl.created_at'
            )
            ->orderByDesc('scl.created_at')
            ->get();
        return response()->json($logs);
    }

    /**
     * API: Rollback potong stock (hapus log dan kembalikan stock)
     */
    public function rollback($id)
    {
        $log = \DB::table('stock_cut_logs')->where('id', $id)->first();
        if (!$log) {
            return response()->json(['error' => 'Log tidak ditemukan'], 404);
        }
        $tanggal = $log->tanggal;
        $id_outlet = $log->id_outlet;
        // Ambil semua kartu stok out pada tanggal & outlet tsb
        $cards = \DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $id_outlet)
            ->where('date', $tanggal)
            ->where('reference_type', 'order_items')
            ->get();
        // Rollback: tambahkan kembali qty yang sudah dipotong
        foreach ($cards as $card) {
            \DB::table('outlet_food_inventory_stocks')
                ->where('inventory_item_id', $card->inventory_item_id)
                ->where('id_outlet', $id_outlet)
                ->where('warehouse_outlet_id', $card->warehouse_outlet_id)
                ->increment('qty_small', $card->out_qty_small);
        }
        // Hapus kartu stok
        \DB::table('outlet_food_inventory_cards')
            ->where('id_outlet', $id_outlet)
            ->where('date', $tanggal)
            ->where('reference_type', 'order_items')
            ->delete();
        // Reset flag stock_cut di order_items
        \DB::table('order_items')
            ->whereDate('created_at', $tanggal)
            ->where('kode_outlet', $id_outlet)
            ->update(['stock_cut' => 0]);
        // Hapus log
        \DB::table('stock_cut_logs')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * API: Engineering - Rekap item terjual pada tanggal & outlet (group by nama, sum qty)
     */
    public function engineering(Request $request)
    {
        $tanggal = $request->input('tanggal');
        $id_outlet = $request->input('id_outlet');
        // Ambil qr_code dari tbl_data_outlet
        $qr_code = \DB::table('tbl_data_outlet')->where('id_outlet', $id_outlet)->value('qr_code');
        $rows = \DB::table('order_items as oi')
            ->join('items as i', 'oi.item_id', '=', 'i.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
            ->select(
                'i.id as item_id',
                'i.type',
                'c.name as category_name',
                'sc.name as sub_category_name',
                'i.name as item_name',
                \DB::raw('SUM(oi.qty) as total_qty')
            )
            ->whereDate('oi.created_at', $tanggal)
            ->where('oi.kode_outlet', $qr_code)
            ->groupBy('i.id', 'i.type', 'c.name', 'sc.name', 'i.name')
            ->orderBy('i.type')
            ->orderBy('c.name')
            ->orderBy('sc.name')
            ->orderBy('i.name')
            ->get();
        // Strukturkan hasil group by type > category > sub_category > item
        $result = [];
        $itemIds = [];
        foreach ($rows as $row) {
            $type = $row->type ?: 'Tanpa Type';
            $cat = $row->category_name ?: 'Tanpa Kategori';
            $subcat = $row->sub_category_name ?: 'Tanpa Sub Kategori';
            if (!isset($result[$type])) $result[$type] = [];
            if (!isset($result[$type][$cat])) $result[$type][$cat] = [];
            if (!isset($result[$type][$cat][$subcat])) $result[$type][$cat][$subcat] = [];
            $result[$type][$cat][$subcat][] = [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'total_qty' => $row->total_qty
            ];
            $itemIds[] = $row->item_id;
        }
        // Cek item yang tidak ada di item_bom
        $itemIds = array_unique($itemIds);
        $itemBomIds = \DB::table('item_bom')->whereIn('item_id', $itemIds)->pluck('item_id')->unique()->toArray();
        $missingBom = [];
        foreach ($rows as $row) {
            if (!in_array($row->item_id, $itemBomIds)) {
                $missingBom[] = [
                    'item_id' => $row->item_id,
                    'item_name' => $row->item_name
                ];
            }
        }
        return response()->json([
            'engineering' => $result,
            'missing_bom' => $missingBom
        ]);
    }
} 