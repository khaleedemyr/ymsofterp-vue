<?php

namespace App\Http\Controllers;

use App\Services\AssetInventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssetSerialController
{
    public const NDEF_PREFIX = 'YM:ASSET:';

    public function apiMeta(Request $request)
    {
        $user = auth()->user();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'user' => [
                'id' => $user->id ?? null,
                'id_outlet' => $user->id_outlet ?? null,
                'name' => $user->name ?? null,
            ],
            'ndef_prefix' => self::NDEF_PREFIX,
        ]);
    }

    public function apiIndex(Request $request)
    {
        if (!Schema::hasTable('asset_inventory_serials')) {
            return response()->json(['success' => false, 'message' => 'Tabel asset serial belum tersedia. Jalankan migration SQL.'], 503);
        }

        $user = auth()->user();

        $query = DB::table('asset_inventory_serials as s')
            ->join('asset_inventory_items as ai', 's.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as oo', 's.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                's.id',
                's.serial_number',
                's.tag_uid',
                's.status',
                's.unit_level',
                's.tagged_at',
                'i.name as item_name',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as location_outlet_name',
                'wo.name as warehouse_name',
                'ai.track_serial'
            )
            ->orderByDesc('s.id');

        AssetInventoryStockService::applyOwnerVisibilityForUser($query, $user, 's.owner_outlet_id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('s.serial_number', 'like', "%{$search}%")
                    ->orWhere('s.tag_uid', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('owner_outlet_id')) {
            $query->where('s.owner_outlet_id', $request->owner_outlet_id);
        }
        if ($request->filled('warehouse_outlet_id')) {
            $query->where('s.warehouse_outlet_id', $request->warehouse_outlet_id);
        }
        if ($request->filled('status')) {
            $query->where('s.status', $request->status);
        }
        if ($request->boolean('track_serial_only')) {
            $query->where('ai.track_serial', 1);
        }

        $perPage = (int) $request->input('per_page', 20);
        $data = $query->paginate($perPage);

        return response()->json(['success' => true, 'serials' => $data]);
    }

    public function apiItemsWithStock(Request $request)
    {
        if (!Schema::hasTable('asset_inventory_serials')) {
            return response()->json(['success' => false, 'message' => 'Tabel asset serial belum tersedia.'], 503);
        }

        $user = auth()->user();
        $ownerOutletId = (int) $request->input('owner_outlet_id');
        $warehouseOutletId = $request->filled('warehouse_outlet_id') ? (int) $request->warehouse_outlet_id : null;

        if (!$ownerOutletId) {
            return response()->json(['success' => false, 'message' => 'owner_outlet_id wajib diisi'], 422);
        }

        if ($user && (int) $user->id_outlet !== 1 && (int) $user->id_outlet !== $ownerOutletId) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak mengakses outlet ini'], 403);
        }

        $query = DB::table('asset_inventory_stocks as st')
            ->join('asset_inventory_items as ai', 'st.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as oo', 'st.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'st.warehouse_outlet_id', '=', 'wo.id')
            ->where('st.owner_outlet_id', $ownerOutletId)
            ->where(function ($q) {
                $q->where('st.qty_small', '>', 0)
                    ->orWhere('st.qty_medium', '>', 0)
                    ->orWhere('st.qty_large', '>', 0);
            })
            ->select(
                'st.inventory_item_id',
                'st.owner_outlet_id',
                'st.warehouse_outlet_id',
                'st.outlet_id',
                'st.qty_small',
                'st.qty_medium',
                'st.qty_large',
                'st.last_cost_small',
                'st.last_cost_medium',
                'st.last_cost_large',
                'ai.item_id',
                'ai.track_serial',
                'i.name as item_name',
                'oo.nama_outlet as owner_outlet_name',
                'wo.name as warehouse_name'
            )
            ->orderBy('i.name');

        if ($warehouseOutletId) {
            $query->where('st.warehouse_outlet_id', $warehouseOutletId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('i.name', 'like', "%{$search}%");
        }

        $rows = $query->get()->map(function ($row) {
            $stockQty = $this->stockUnitCount($row);
            $taggedQty = DB::table('asset_inventory_serials')
                ->where('inventory_item_id', $row->inventory_item_id)
                ->where('owner_outlet_id', $row->owner_outlet_id)
                ->where('warehouse_outlet_id', $row->warehouse_outlet_id)
                ->whereNotIn('status', ['replaced'])
                ->count();

            return [
                'inventory_item_id' => (int) $row->inventory_item_id,
                'item_id' => (int) $row->item_id,
                'item_name' => $row->item_name,
                'owner_outlet_id' => (int) $row->owner_outlet_id,
                'owner_outlet_name' => $row->owner_outlet_name,
                'outlet_id' => $row->outlet_id ? (int) $row->outlet_id : null,
                'warehouse_outlet_id' => $row->warehouse_outlet_id ? (int) $row->warehouse_outlet_id : null,
                'warehouse_name' => $row->warehouse_name,
                'track_serial' => (bool) $row->track_serial,
                'stock_qty' => $stockQty,
                'tagged_qty' => $taggedQty,
                'remaining_qty' => max(0, $stockQty - $taggedQty),
                'qty_small' => (float) $row->qty_small,
                'qty_medium' => (float) $row->qty_medium,
                'qty_large' => (float) $row->qty_large,
            ];
        })->filter(fn ($row) => $row['remaining_qty'] > 0 || $row['track_serial'])->values();

        return response()->json(['success' => true, 'items' => $rows]);
    }

    public function apiEnableTracking(Request $request)
    {
        $request->validate([
            'inventory_item_id' => 'required|integer',
        ]);

        DB::table('asset_inventory_items')
            ->where('id', $request->inventory_item_id)
            ->update(['track_serial' => 1]);

        return response()->json(['success' => true, 'message' => 'Pelacakan serial diaktifkan untuk item ini.']);
    }

    public function apiPrepareTag(Request $request)
    {
        $request->validate([
            'inventory_item_id' => 'required|integer',
            'owner_outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'tag_uid' => 'required|string|min:8|max:32',
        ]);

        $user = auth()->user();
        $tagUid = $this->normalizeTagUid($request->tag_uid);

        if ($user && (int) $user->id_outlet !== 1 && (int) $user->id_outlet !== (int) $request->owner_outlet_id) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak men-tag untuk outlet ini'], 403);
        }

        if (DB::table('asset_inventory_serials')->where('tag_uid', $tagUid)->exists()) {
            return response()->json(['success' => false, 'message' => 'UID tag ini sudah terdaftar di sistem'], 422);
        }

        $invItem = DB::table('asset_inventory_items as ai')
            ->join('items as i', 'ai.item_id', '=', 'i.id')
            ->where('ai.id', $request->inventory_item_id)
            ->select('ai.*', 'i.name as item_name')
            ->first();

        if (!$invItem) {
            return response()->json(['success' => false, 'message' => 'Item asset tidak ditemukan'], 404);
        }

        $stock = AssetInventoryStockService::findStock(
            (int) $request->inventory_item_id,
            (int) $request->owner_outlet_id,
            (int) $request->warehouse_outlet_id
        );

        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Stok tidak ditemukan di lokasi ini'], 422);
        }

        $stockQty = $this->stockUnitCount($stock);
        $taggedQty = DB::table('asset_inventory_serials')
            ->where('inventory_item_id', $request->inventory_item_id)
            ->where('owner_outlet_id', $request->owner_outlet_id)
            ->where('warehouse_outlet_id', $request->warehouse_outlet_id)
            ->whereNotIn('status', ['replaced'])
            ->count();

        if ($taggedQty >= $stockQty) {
            return response()->json([
                'success' => false,
                'message' => "Semua unit stok sudah di-tag ({$taggedQty}/{$stockQty})",
            ], 422);
        }

        $serialNumber = $this->generateSerialNumber();

        return response()->json([
            'success' => true,
            'serial_number' => $serialNumber,
            'ndef_payload' => self::NDEF_PREFIX . $serialNumber,
            'item_name' => $invItem->item_name,
            'stock_qty' => $stockQty,
            'tagged_qty' => $taggedQty,
            'remaining_qty' => $stockQty - $taggedQty,
        ]);
    }

    public function apiConfirmTag(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|max:50',
            'tag_uid' => 'required|string|min:8|max:32',
            'inventory_item_id' => 'required|integer',
            'owner_outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'unit_level' => 'nullable|in:small,medium,large',
        ]);

        $user = auth()->user();
        $tagUid = $this->normalizeTagUid($request->tag_uid);
        $serialNumber = trim($request->serial_number);

        if ($user && (int) $user->id_outlet !== 1 && (int) $user->id_outlet !== (int) $request->owner_outlet_id) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak men-tag untuk outlet ini'], 403);
        }

        if (DB::table('asset_inventory_serials')->where('tag_uid', $tagUid)->exists()) {
            return response()->json(['success' => false, 'message' => 'UID tag sudah terdaftar'], 422);
        }
        if (DB::table('asset_inventory_serials')->where('serial_number', $serialNumber)->exists()) {
            return response()->json(['success' => false, 'message' => 'Nomor seri sudah terdaftar'], 422);
        }

        $invItem = DB::table('asset_inventory_items')->where('id', $request->inventory_item_id)->first();
        if (!$invItem) {
            return response()->json(['success' => false, 'message' => 'Item tidak ditemukan'], 404);
        }

        $stock = AssetInventoryStockService::findStock(
            (int) $request->inventory_item_id,
            (int) $request->owner_outlet_id,
            (int) $request->warehouse_outlet_id
        );
        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Stok tidak ditemukan'], 422);
        }

        $unitLevel = $request->input('unit_level', 'small');
        $locationOutletId = AssetInventoryStockService::locationOutletIdForWarehouse((int) $request->warehouse_outlet_id)
            ?? (int) ($stock->outlet_id ?? $request->owner_outlet_id);

        DB::beginTransaction();
        try {
            DB::table('asset_inventory_items')
                ->where('id', $request->inventory_item_id)
                ->update(['track_serial' => 1]);

            $serialId = DB::table('asset_inventory_serials')->insertGetId([
                'serial_number' => $serialNumber,
                'tag_uid' => $tagUid,
                'inventory_item_id' => $request->inventory_item_id,
                'item_id' => $invItem->item_id,
                'owner_outlet_id' => $request->owner_outlet_id,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $request->warehouse_outlet_id,
                'unit_level' => $unitLevel,
                'status' => 'available',
                'source_type' => 'retroactive_tag',
                'source_id' => null,
                'cost_small' => $stock->last_cost_small ?? 0,
                'cost_medium' => $stock->last_cost_medium ?? 0,
                'cost_large' => $stock->last_cost_large ?? 0,
                'tagged_at' => now(),
                'tagged_by' => $user->id ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('asset_serial_movements')->insert([
                'serial_id' => $serialId,
                'movement_type' => 'tagged',
                'reference_type' => 'retroactive_tag',
                'reference_id' => null,
                'to_owner_outlet_id' => $request->owner_outlet_id,
                'to_warehouse_outlet_id' => $request->warehouse_outlet_id,
                'moved_by' => $user->id ?? null,
                'notes' => 'Tag stok lama via NFC Android',
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Nomor seri berhasil didaftarkan',
                'serial' => $this->lookupSerialById($serialId),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    public function apiLookup(Request $request)
    {
        if (!Schema::hasTable('asset_inventory_serials')) {
            return response()->json(['success' => false, 'message' => 'Tabel asset serial belum tersedia.'], 503);
        }

        $serialNumber = trim((string) $request->input('serial_number', ''));
        $tagUid = $this->normalizeTagUid((string) $request->input('tag_uid', ''));

        if ($serialNumber === '' && $tagUid === '') {
            return response()->json(['success' => false, 'message' => 'serial_number atau tag_uid wajib diisi'], 422);
        }

        $query = DB::table('asset_inventory_serials as s')
            ->join('asset_inventory_items as ai', 's.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as oo', 's.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->select(
                's.*',
                'i.name as item_name',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as location_outlet_name',
                'wo.name as warehouse_name'
            );

        if ($serialNumber !== '') {
            $query->where('s.serial_number', $serialNumber);
        } else {
            $query->where('s.tag_uid', $tagUid);
        }

        $serial = $query->first();
        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Nomor seri / tag tidak ditemukan'], 404);
        }

        $movements = DB::table('asset_serial_movements')
            ->where('serial_id', $serial->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'serial' => $serial,
            'movements' => $movements,
        ]);
    }

    private function lookupSerialById(int $id): ?object
    {
        return DB::table('asset_inventory_serials as s')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->where('s.id', $id)
            ->select('s.*', 'i.name as item_name')
            ->first();
    }

    private function generateSerialNumber(): string
    {
        do {
            $serial = 'AST-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while (DB::table('asset_inventory_serials')->where('serial_number', $serial)->exists());

        return $serial;
    }

    private function normalizeTagUid(string $uid): string
    {
        return strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $uid) ?? '');
    }

    private function stockUnitCount(object $row): int
    {
        $small = (float) ($row->qty_small ?? 0);
        $medium = (float) ($row->qty_medium ?? 0);
        $large = (float) ($row->qty_large ?? 0);

        if ($small > 0) {
            return (int) round($small);
        }
        if ($medium > 0) {
            return (int) round($medium);
        }
        if ($large > 0) {
            return (int) round($large);
        }

        return 0;
    }
}
