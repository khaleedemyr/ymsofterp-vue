<?php

namespace App\Http\Controllers;

use App\Services\AssetInventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AssetSerialController extends Controller
{
    public const NDEF_PREFIX = 'YM:ASSET:';

    // ─── WEB (Inertia) ───────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!Schema::hasTable('asset_inventory_serials')) {
            return Inertia::render('AssetSerial/Index', [
                'serials' => ['data' => [], 'links' => [], 'meta' => []],
                'filters' => [],
                'outlets' => [],
                'warehouseOutlets' => [],
                'user' => auth()->user(),
                'tableReady' => false,
            ]);
        }

        $user = auth()->user();
        $query = $this->buildSerialListQuery($request, $user);
        $serials = $query->paginate((int) $request->input('per_page', 25))->withQueryString();

        $outlets = DB::table('tbl_data_outlet')
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        $warehouseOutlets = DB::table('warehouse_outlets')
            ->select('id', 'name', 'outlet_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('AssetSerial/Index', [
            'serials' => $serials,
            'filters' => $request->only(['search', 'owner_outlet_id', 'warehouse_outlet_id', 'status']),
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'user' => $user,
            'tableReady' => true,
        ]);
    }

    public function show(int $id)
    {
        if (!Schema::hasTable('asset_inventory_serials')) {
            abort(503, 'Tabel asset serial belum tersedia.');
        }

        $user = auth()->user();
        $query = DB::table('asset_inventory_serials as s')
            ->join('asset_inventory_items as ai', 's.inventory_item_id', '=', 'ai.id')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->join('tbl_data_outlet as oo', 's.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as o', 's.outlet_id', '=', 'o.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 's.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoin('users as u', 's.tagged_by', '=', 'u.id')
            ->where('s.id', $id)
            ->select(
                's.*',
                'i.name as item_name',
                'oo.nama_outlet as owner_outlet_name',
                'o.nama_outlet as location_outlet_name',
                'wo.name as warehouse_name',
                'ai.track_serial',
                'u.name as tagged_by_name'
            );

        AssetInventoryStockService::applyOwnerVisibilityForUser($query, $user, 's.owner_outlet_id');

        $serial = $query->first();
        if (!$serial) {
            abort(404);
        }

        $movements = DB::table('asset_serial_movements as m')
            ->leftJoin('users as u', 'm.moved_by', '=', 'u.id')
            ->where('m.serial_id', $id)
            ->select('m.*', 'u.name as moved_by_name')
            ->orderByDesc('m.id')
            ->get();

        return Inertia::render('AssetSerial/Show', [
            'serial' => $serial,
            'movements' => $movements,
        ]);
    }

    public function create(Request $request)
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

        return Inertia::render('AssetSerial/Register', [
            'outlets' => $outlets,
            'warehouseOutlets' => $warehouseOutlets,
            'user' => $user,
            'prefill' => $request->only(['owner_outlet_id', 'warehouse_outlet_id', 'inventory_item_id']),
            'tableReady' => Schema::hasTable('asset_inventory_serials'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'inventory_item_id' => 'required|integer',
            'owner_outlet_id' => 'required|integer',
            'warehouse_outlet_id' => 'required|integer',
            'serial_number' => 'nullable|string|max:50',
            'tag_uid' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:500',
            'unit_level' => 'nullable|in:small,medium,large',
        ]);

        $result = $this->registerSerialRecord(
            (int) $request->inventory_item_id,
            (int) $request->owner_outlet_id,
            (int) $request->warehouse_outlet_id,
            $request->input('serial_number'),
            $request->input('tag_uid'),
            $request->input('unit_level', 'small'),
            'manual_register',
            null,
            $request->input('notes'),
            'Registrasi manual via web ERP'
        );

        if (!$result['success']) {
            return back()->withErrors(['message' => $result['message']]);
        }

        return redirect()->route('asset-serials.show', $result['serial_id'])
            ->with('success', 'Nomor seri berhasil didaftarkan.');
    }

    public function itemsWithStock(Request $request)
    {
        $items = $this->buildItemsWithStock($request);

        if ($items === null) {
            return response()->json(['success' => false, 'message' => 'Tabel asset serial belum tersedia.'], 503);
        }

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function toggleItemTracking(Request $request)
    {
        $request->validate(['inventory_item_id' => 'required|integer']);

        DB::table('asset_inventory_items')
            ->where('id', $request->inventory_item_id)
            ->update(['track_serial' => 1]);

        return back()->with('success', 'Pelacakan serial diaktifkan untuk item ini.');
    }

    // ─── API (mobile) ────────────────────────────────────────────────

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
        $data = $this->buildSerialListQuery($request, $user)
            ->paginate((int) $request->input('per_page', 20));

        return response()->json(['success' => true, 'serials' => $data]);
    }

    public function apiItemsWithStock(Request $request)
    {
        $items = $this->buildItemsWithStock($request);

        if ($items === null) {
            return response()->json(['success' => false, 'message' => 'Tabel asset serial belum tersedia.'], 503);
        }

        return response()->json(['success' => true, 'items' => $items]);
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

        $result = $this->registerSerialRecord(
            (int) $request->inventory_item_id,
            (int) $request->owner_outlet_id,
            (int) $request->warehouse_outlet_id,
            trim($request->serial_number),
            $request->tag_uid,
            $request->input('unit_level', 'small'),
            'retroactive_tag',
            null,
            null,
            'Tag stok via NFC Android'
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['status'] ?? 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nomor seri berhasil didaftarkan',
            'serial' => $this->lookupSerialById($result['serial_id']),
        ]);
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

    private function buildSerialListQuery(Request $request, $user)
    {
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
                's.source_type',
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

        return $query;
    }

    private function buildItemsWithStock(Request $request): ?\Illuminate\Support\Collection
    {
        if (!Schema::hasTable('asset_inventory_serials')) {
            return null;
        }

        $user = auth()->user();
        $ownerOutletId = (int) $request->input('owner_outlet_id');
        $warehouseOutletId = $request->filled('warehouse_outlet_id') ? (int) $request->warehouse_outlet_id : null;

        if (!$ownerOutletId) {
            return collect();
        }

        if ($user && (int) $user->id_outlet !== 1 && (int) $user->id_outlet !== $ownerOutletId) {
            return collect();
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

        return $query->get()->map(function ($row) {
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
    }

    private function registerSerialRecord(
        int $inventoryItemId,
        int $ownerOutletId,
        int $warehouseOutletId,
        ?string $serialNumber,
        ?string $tagUidRaw,
        string $unitLevel,
        string $sourceType,
        ?int $sourceId,
        ?string $notes,
        string $movementNote
    ): array {
        $user = auth()->user();
        $tagUid = $tagUidRaw ? $this->normalizeTagUid($tagUidRaw) : null;
        $serialNumber = trim((string) ($serialNumber ?: $this->generateSerialNumber()));

        if ($user && (int) $user->id_outlet !== 1 && (int) $user->id_outlet !== $ownerOutletId) {
            return ['success' => false, 'message' => 'Tidak berhak mendaftarkan serial untuk outlet ini', 'status' => 403];
        }

        if ($tagUid !== null && $tagUid !== '' && DB::table('asset_inventory_serials')->where('tag_uid', $tagUid)->exists()) {
            return ['success' => false, 'message' => 'UID tag sudah terdaftar', 'status' => 422];
        }
        if (DB::table('asset_inventory_serials')->where('serial_number', $serialNumber)->exists()) {
            return ['success' => false, 'message' => 'Nomor seri sudah terdaftar', 'status' => 422];
        }

        $invItem = DB::table('asset_inventory_items')->where('id', $inventoryItemId)->first();
        if (!$invItem) {
            return ['success' => false, 'message' => 'Item tidak ditemukan', 'status' => 404];
        }

        $stock = AssetInventoryStockService::findStock($inventoryItemId, $ownerOutletId, $warehouseOutletId);
        if (!$stock) {
            return ['success' => false, 'message' => 'Stok tidak ditemukan di lokasi ini', 'status' => 422];
        }

        $stockQty = $this->stockUnitCount($stock);
        $taggedQty = DB::table('asset_inventory_serials')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('owner_outlet_id', $ownerOutletId)
            ->where('warehouse_outlet_id', $warehouseOutletId)
            ->whereNotIn('status', ['replaced'])
            ->count();

        if ($taggedQty >= $stockQty) {
            return [
                'success' => false,
                'message' => "Semua unit stok sudah terdaftar ({$taggedQty}/{$stockQty})",
                'status' => 422,
            ];
        }

        $locationOutletId = AssetInventoryStockService::locationOutletIdForWarehouse($warehouseOutletId)
            ?? (int) ($stock->outlet_id ?? $ownerOutletId);

        DB::beginTransaction();
        try {
            DB::table('asset_inventory_items')
                ->where('id', $inventoryItemId)
                ->update(['track_serial' => 1]);

            $serialId = DB::table('asset_inventory_serials')->insertGetId([
                'serial_number' => $serialNumber,
                'tag_uid' => $tagUid ?: null,
                'inventory_item_id' => $inventoryItemId,
                'item_id' => $invItem->item_id,
                'owner_outlet_id' => $ownerOutletId,
                'outlet_id' => $locationOutletId,
                'warehouse_outlet_id' => $warehouseOutletId,
                'unit_level' => $unitLevel,
                'status' => 'available',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'cost_small' => $stock->last_cost_small ?? 0,
                'cost_medium' => $stock->last_cost_medium ?? 0,
                'cost_large' => $stock->last_cost_large ?? 0,
                'tagged_at' => now(),
                'tagged_by' => $user->id ?? null,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('asset_serial_movements')->insert([
                'serial_id' => $serialId,
                'movement_type' => 'tagged',
                'reference_type' => $sourceType,
                'reference_id' => $sourceId,
                'to_owner_outlet_id' => $ownerOutletId,
                'to_warehouse_outlet_id' => $warehouseOutletId,
                'moved_by' => $user->id ?? null,
                'notes' => $movementNote,
                'created_at' => now(),
            ]);

            DB::commit();

            return ['success' => true, 'serial_id' => $serialId, 'serial_number' => $serialNumber];
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage(), 'status' => 500];
        }
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
