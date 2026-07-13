<?php

namespace App\Http\Controllers;

use App\Http\Traits\WritesActivityLogTrait;
use App\Models\FoodFloorOrder;
use App\Models\FoodFloorOrderItem;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Item;
use Illuminate\Support\Facades\Mail;
use App\Services\FloorOrderService;
use Carbon\Carbon;
use App\Models\WarehouseOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\FloorOrderItemPriceResolver;
use App\Support\FloorOrderPriceAuditor;
use App\Support\FoodFloorOrderApprovalService;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FoodFloorOrderController extends Controller
{
    use WritesActivityLogTrait;
    private const FORECAST_AFTER_RESERVE_RATIO = 0.80; // 100% - 20%
    private const FORECAST_LOCK_RATIO_OF_REST = 0.40; // 40% dari 80% (efektif 32%)

    protected $floorOrderService;

    protected FoodFloorOrderApprovalService $approvalService;

    public function __construct(
        FloorOrderService $floorOrderService,
        FoodFloorOrderApprovalService $approvalService,
    ) {
        $this->floorOrderService = $floorOrderService;
        $this->approvalService = $approvalService;
    }

    // Tampilkan form edit draft
    public function edit($id)
    {
        $order = FoodFloorOrder::with(['items', 'approvalFlows.approver'])->findOrFail($id);
        if (! $order->canEdit()) {
            return redirect()->route('floor-order.index')
                ->with('error', 'Request Order tidak dapat diedit karena sudah melewati batas waktu edit (besok jam 07:00).');
        }

        $this->appendEditWindowMeta($order);

        return Inertia::render('FloorOrder/Form', [
            'order' => $order,
            'user' => Auth::user()->load('outlet'),
        ]);
    }

    private function appendEditWindowMeta(FoodFloorOrder $order): void
    {
        $order->setAttribute('can_edit', $order->canEdit());
        $order->setAttribute('edit_cutoff_at', $order->editCutoffAt()?->toIso8601String());
    }

    public function getApprovers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $query = User::query()
            ->where('users.status', 'A')
            ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'tbl_data_jabatan.nama_jabatan as jabatan',
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('tbl_data_jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'users' => $query->orderBy('users.nama_lengkap')->limit(30)->get(),
        ]);
    }

    /**
     * Item kategori asset tidak boleh masuk RO (kecuali RO Supplier — alur item terpisah).
     */
    private function shouldBlockAssetItemsForFloorOrder(?string $foMode): bool
    {
        return $foMode !== 'RO Supplier';
    }

    private function assertNoAssetItemsForFloorOrder(array $items, ?string $foMode = null): void
    {
        if (! $this->shouldBlockAssetItemsForFloorOrder($foMode)) {
            return;
        }

        foreach ($items as $item) {
            if (empty($item['item_id'])) {
                continue;
            }
            if (FloorOrderItemPriceResolver::isAssetItem((int) $item['item_id'])) {
                $name = $item['item_name'] ?? 'Item asset';
                $modeLabel = $foMode ?: 'Request Order';
                throw new \Exception("Item \"{$name}\" adalah kategori asset dan tidak boleh dipakai di {$modeLabel}. Pilih item groceries/perishable yang sesuai.");
            }
        }
    }

    private function applyServerFloorOrderPrices(array $items, $outletId): array
    {
        $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id');
        $regionId = $regionId ? (int) $regionId : null;
        $outletKey = $outletId ? (string) $outletId : null;

        foreach ($items as &$item) {
            if (empty($item['item_id'])) {
                continue;
            }
            $price = FloorOrderItemPriceResolver::resolveLineUnitPrice(
                (int) $item['item_id'],
                isset($item['unit']) ? (string) $item['unit'] : null,
                $regionId,
                $outletKey,
            );
            $qty = (float) ($item['qty'] ?? 0);
            $item['price'] = $price;
            $item['subtotal'] = round($qty * $price, 2);
        }
        unset($item);

        return $items;
    }

    private function resolveItemSupplierMapping(int $itemId, int $outletId, ?string $unitName = null): ?object
    {
        $baseQuery = fn (?string $unitFilter) => DB::table('item_supplier_outlet')
            ->join('item_supplier', 'item_supplier_outlet.item_supplier_id', '=', 'item_supplier.id')
            ->leftJoin('units', 'item_supplier.unit_id', '=', 'units.id')
            ->where('item_supplier_outlet.outlet_id', $outletId)
            ->where('item_supplier.item_id', $itemId)
            ->when($unitFilter, function ($query) use ($unitFilter) {
                $query->where(function ($q) use ($unitFilter) {
                    $q->where('units.name', $unitFilter)
                        ->orWhereRaw('LOWER(units.name) = ?', [strtolower($unitFilter)]);
                });
            })
            ->select('item_supplier.supplier_id', 'item_supplier.id as item_supplier_id')
            ->orderByDesc('item_supplier.id');

        if ($unitName !== null && $unitName !== '') {
            $match = $baseQuery($unitName)->first();
            if ($match) {
                return $match;
            }
        }

        return $baseQuery(null)->first();
    }

    // Method untuk memproses item tanpa validasi supplier
    private function validateAndGroupItemsBySupplier($items, $outletId, $foMode = null)
    {
        $this->assertNoAssetItemsForFloorOrder($items, $foMode);
        $items = $this->applyServerFloorOrderPrices($items, $outletId);
        $processedItems = [];

        foreach ($items as $item) {
            // Abaikan item kosong
            if (empty($item['item_id']) || empty($item['item_name'])) {
                continue;
            }

            $itemSupplier = $this->resolveItemSupplierMapping(
                (int) $item['item_id'],
                (int) $outletId,
                isset($item['unit']) ? (string) $item['unit'] : null
            );

            $processedItems[] = [
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'supplier_id' => $itemSupplier ? $itemSupplier->supplier_id : null,
                'item_supplier_id' => $itemSupplier ? $itemSupplier->item_supplier_id : null,
                'id_outlet' => $outletId
            ];
        }

        return $processedItems;
    }

    /**
     * Satu baris per item_id: qty & subtotal dijumlahkan (hindari double dari mode tab / payload ganda).
     */
    private function dedupeProcessedItemsByItemId(array $processedItems): array
    {
        $map = [];
        foreach ($processedItems as $item) {
            $key = (string) $item['item_id'];
            if (! isset($map[$key])) {
                $map[$key] = $item;

                continue;
            }
            $acc = &$map[$key];
            $acc['qty'] = (float) $acc['qty'] + (float) $item['qty'];
            $acc['subtotal'] = (float) $acc['subtotal'] + (float) $item['subtotal'];
            if ($acc['qty'] > 0) {
                $acc['price'] = round($acc['subtotal'] / $acc['qty'], 4);
            }
        }

        return array_values($map);
    }

    private function assertHasProcessedItems(array $processedItems, string $actionLabel = 'menyimpan'): void
    {
        if ($processedItems === []) {
            throw new \Exception("Minimal 1 item wajib diisi untuk {$actionLabel} Request Order.");
        }
    }

    private function assertSupplierMappedForRoSupplierItems(array $processedItems): void
    {
        $missing = [];
        $outletName = null;

        foreach ($processedItems as $item) {
            if (empty($item['supplier_id']) || empty($item['item_supplier_id'])) {
                $missing[] = $item['item_name'] ?? ('Item #' . ($item['item_id'] ?? '?'));
                if ($outletName === null && ! empty($item['id_outlet'])) {
                    $outletName = DB::table('tbl_data_outlet')
                        ->where('id_outlet', $item['id_outlet'])
                        ->value('nama_outlet');
                }
            }
        }

        if ($missing !== []) {
            $names = implode(', ', array_unique($missing));
            $outletLabel = $outletName ? " (outlet: {$outletName})" : '';
            throw new \Exception(
                "Item berikut belum memiliki mapping Item Supplier{$outletLabel}: {$names}. "
                . 'Tambahkan outlet ini di menu Item Supplier → Edit mapping item + supplier.'
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProcessedItemsFromOrder(FoodFloorOrder $order): array
    {
        $items = $order->items()->get()->map(function ($item) {
            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'price' => $item->price,
                'subtotal' => $item->subtotal,
            ];
        })->all();

        return $this->dedupeProcessedItemsByItemId(
            $this->validateAndGroupItemsBySupplier($items, $order->id_outlet, $order->fo_mode)
        );
    }

    private function persistFloorOrderItems(int $floorOrderId, array $processedItems): void
    {
        foreach ($processedItems as $item) {
            $masterItem = Item::find($item['item_id']);
            DB::table('food_floor_order_items')->insert([
                'floor_order_id' => $floorOrderId,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'category_id' => $masterItem ? $masterItem->category_id : null,
                'warehouse_division_id' => $masterItem ? $masterItem->warehouse_division_id : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function clearSupplierItemTables(int $floorOrderId): void
    {
        DB::table('food_floor_order_supplier_items')->where('floor_order_id', $floorOrderId)->delete();
        DB::table('food_floor_order_supplier_headers')->where('floor_order_id', $floorOrderId)->delete();
    }

    private function syncSupplierGroupsFromProcessedItems(int $floorOrderId, array $processedItems): void
    {
        $this->clearSupplierItemTables($floorOrderId);

        $bySupplier = [];
        foreach ($processedItems as $item) {
            $supplierId = $item['supplier_id'] ?? null;
            if (! $supplierId) {
                continue;
            }
            $bySupplier[(int) $supplierId][] = $item;
        }

        foreach ($bySupplier as $supplierId => $items) {
            $headerId = DB::table('food_floor_order_supplier_headers')->insertGetId([
                'floor_order_id' => $floorOrderId,
                'supplier_id' => $supplierId,
                'supplier_fo_number' => $this->floorOrderService->generateSupplierFONumber($supplierId),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                $row = [
                    'floor_order_id' => $floorOrderId,
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('food_floor_order_supplier_items', 'supplier_id')) {
                    $row['supplier_id'] = $supplierId;
                }

                if (Schema::hasColumn('food_floor_order_supplier_items', 'item_supplier_id')) {
                    if (empty($item['item_supplier_id'])) {
                        throw new \Exception(
                            'Item ' . ($item['item_name'] ?? ('#' . ($item['item_id'] ?? '?')))
                            . ' belum memiliki mapping Item Supplier untuk outlet ini.'
                        );
                    }
                    $row['item_supplier_id'] = $item['item_supplier_id'];
                }

                if (Schema::hasColumn('food_floor_order_supplier_items', 'supplier_header_id')) {
                    $row['supplier_header_id'] = $headerId;
                }

                if (Schema::hasColumn('food_floor_order_supplier_items', 'unit')) {
                    $row['unit'] = $item['unit'];
                }

                DB::table('food_floor_order_supplier_items')->insert($row);
            }
        }
    }

    /**
     * @return array<int, array{header: object, items: \Illuminate\Support\Collection<int, object>}>
     */
    private function loadItemsBySupplier(int $floorOrderId): array
    {
        $headers = DB::table('food_floor_order_supplier_headers')
            ->where('floor_order_id', $floorOrderId)
            ->orderBy('id')
            ->get();

        $groups = [];
        foreach ($headers as $header) {
            $itemQuery = DB::table('food_floor_order_supplier_items')
                ->where('floor_order_id', $floorOrderId);

            if (Schema::hasColumn('food_floor_order_supplier_items', 'supplier_header_id')) {
                $itemQuery->where('supplier_header_id', $header->id);
            }

            $groups[] = [
                'header' => $header,
                'items' => $itemQuery->orderBy('id')->get(),
            ];
        }

        return $groups;
    }

    // Method store untuk membuat floor order
    public function store(Request $request)
    {
        // Validasi arrival_date wajib diisi
        if (empty($request->arrival_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal kedatangan wajib diisi'
            ], 422);
        }
        
        try {
            \DB::beginTransaction();

            $foMode = $request->fo_mode ?? 'RO Utama';
            $approverIds = null;
            if ($foMode === 'RO Khusus') {
                $approverIds = $this->approvalService->validateAndNormalizeApproverIds($request);
            }

            // Ambil id_outlet dari request atau user login
            $idOutlet = $request->outlet_id ?? (auth()->user()->id_outlet ?? null);
            $userId = auth()->id();
            $tanggal = $request->tanggal ?? now()->toDateString();

            // --- VALIDASI warehouse_outlet_id ---
            $warehouseOutletId = $request->warehouse_outlet_id;
            $warehouseOutlet = \App\Models\WarehouseOutlet::where('id', $warehouseOutletId)
                ->where('outlet_id', $idOutlet)
                ->where('status', 'active')
                ->first();
            if (!$warehouseOutlet) {
                throw new \Exception('Warehouse outlet tidak valid atau tidak aktif untuk outlet ini.');
            }

            // Cek apakah sudah ada draft untuk user, tanggal, outlet, warehouse, status draft
            $existingOrder = \DB::table('food_floor_orders')
                ->where('user_id', $userId)
                ->where('id_outlet', $idOutlet)
                ->where('warehouse_outlet_id', $warehouseOutletId)
                ->where('tanggal', $tanggal)
                ->where('status', 'draft')
                ->first();

            if ($existingOrder) {
                // Update header FO
                \DB::table('food_floor_orders')->where('id', $existingOrder->id)->update([
                    'description' => $request->description ?? '',
                    'fo_mode' => $request->fo_mode ?? 'RO Utama',
                    'input_mode' => $request->input_mode ?? 'pc',
                    'fo_schedule_id' => $request->fo_schedule_id ?? null,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'arrival_date' => $request->arrival_date ?? null,
                    'updated_at' => now()
                ]);
                $floorOrderId = $existingOrder->id;
            } else {
                // Insert header FO (DRAFT, bukan RO-...)
                $headerData = [
                    'order_number' => 'DRAFT-' . $userId . '-' . time(),
                    'tanggal' => $tanggal,
                    'description' => $request->description ?? '',
                    'fo_mode' => $request->fo_mode ?? 'RO Utama',
                    'input_mode' => $request->input_mode ?? 'pc',
                    'fo_schedule_id' => $request->fo_schedule_id ?? null,
                    'arrival_date' => $request->arrival_date ?? null,
                    'id_outlet' => $idOutlet,
                    'user_id' => $userId,
                    'warehouse_outlet_id' => $warehouseOutletId,
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $inserted = \DB::table('food_floor_orders')->insert($headerData);
                $floorOrderId = \DB::getPdo()->lastInsertId();
            }

            // Ambil data outlet
            $outlet = \DB::table('tbl_data_outlet')->where('id_outlet', $idOutlet)->first();
            $outletName = $outlet ? $outlet->nama_outlet : 'Unknown Outlet';

            $items = $request->items;
            
            // Proses item tanpa validasi supplier
            $processedItems = $this->dedupeProcessedItemsByItemId(
                $this->validateAndGroupItemsBySupplier($items, $idOutlet, $request->fo_mode)
            );
            $this->assertHasProcessedItems($processedItems, 'menyimpan');

            // Hapus item lama (hanya untuk draft ini)
            \DB::table('food_floor_order_items')->where('floor_order_id', $floorOrderId)->delete();
            $this->clearSupplierItemTables((int) $floorOrderId);

            $this->persistFloorOrderItems((int) $floorOrderId, $processedItems);

            if ($foMode === 'RO Supplier') {
                $this->syncSupplierGroupsFromProcessedItems((int) $floorOrderId, $processedItems);
            }

            if ($foMode === 'RO Khusus' && $approverIds !== null) {
                $this->approvalService->syncFlows((int) $floorOrderId, $approverIds);
            }

            \DB::commit();

            $order = FoodFloorOrder::with('items')->find($floorOrderId);
            $this->writeActivityLog(
                $request,
                'food_floor_order',
                'create',
                'Membuat Floor Order / Request Order: ' . ($order->order_number ?? $floorOrderId),
                null,
                $order ? $order->toArray() : ['floor_order_id' => $floorOrderId]
            );

            return response()->json([
                'success' => true,
                'message' => 'Floor Order berhasil dibuat',
                'data' => [
                    'floor_order_id' => $floorOrderId,
                    'order_number' => $existingOrder->order_number ?? ('DRAFT-' . $userId . '-' . time())
                ]
            ]);
        } catch (ValidationException $e) {
            \DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error dalam store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method update untuk mengupdate floor order
    public function update(Request $request, $id)
    {
        // Validasi arrival_date wajib diisi
        if (empty($request->arrival_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal kedatangan wajib diisi'
            ], 422);
        }
        
        $order = FoodFloorOrder::findOrFail($id);
        if ($order->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya draft yang dapat diubah.',
            ], 422);
        }
        if (! $order->isWithinEditWindow()) {
            return response()->json([
                'success' => false,
                'message' => 'Request Order tidak dapat diubah karena sudah melewati batas waktu edit (besok jam 07:00).',
            ], 422);
        }

        $oldData = $order->toArray();
        $approverIds = null;
        try {
            if (($request->fo_mode ?? $order->fo_mode) === 'RO Khusus') {
                $approverIds = $this->approvalService->validateAndNormalizeApproverIds($request);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        }
        // --- VALIDASI warehouse_outlet_id ---
        $warehouseOutletId = $request->warehouse_outlet_id;
        $warehouseOutlet = \App\Models\WarehouseOutlet::where('id', $warehouseOutletId)
            ->where('outlet_id', $order->id_outlet)
            ->where('status', 'active')
            ->first();
        if (!$warehouseOutlet) {
            return response()->json(['success' => false, 'message' => 'Warehouse outlet tidak valid atau tidak aktif untuk outlet ini.'], 422);
        }
        $order->update(array_merge(
            $request->only(['tanggal', 'description', 'fo_mode', 'input_mode', 'fo_schedule_id', 'arrival_date']),
            ['warehouse_outlet_id' => $warehouseOutletId]
        ));

        // Proses item tanpa validasi supplier
        $processedItems = $this->dedupeProcessedItemsByItemId(
            $this->validateAndGroupItemsBySupplier($request->items, $order->id_outlet, $order->fo_mode)
        );
        $this->assertHasProcessedItems($processedItems, 'mengubah');

        // Hapus data item lama
        $order->items()->delete();
        $this->clearSupplierItemTables((int) $order->id);

        $this->persistFloorOrderItems((int) $order->id, $processedItems);

        if (($request->fo_mode ?? $order->fo_mode) === 'RO Supplier') {
            $this->syncSupplierGroupsFromProcessedItems((int) $order->id, $processedItems);
        }

        if ($approverIds !== null) {
            $this->approvalService->syncFlows((int) $order->id, $approverIds);
        }

        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'update',
            'module' => 'food_floor_order',
            'description' => 'Update Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $order->fresh()->toArray(),
        ]);

        return response()->json(['success' => true, 'message' => 'Floor Order berhasil diupdate']);
    }

    // Method destroy untuk menghapus floor order
    public function destroy($id)
    {
        $order = FoodFloorOrder::findOrFail($id);
        
        // Cek apakah RO sudah ada di packing list
        $packingListExists = \DB::table('food_packing_lists')
            ->where('food_floor_order_id', $order->id)
            ->exists();
        
        if ($packingListExists) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Request Order tidak dapat dihapus karena sudah ada di Packing List'
                ], 422);
            }
            return redirect()->route('floor-order.index')
                ->with('error', 'Request Order tidak dapat dihapus karena sudah ada di Packing List');
        }

        $deleteSnapshot = $this->enrichDeleteSnapshot(
            array_merge($order->toArray(), ['items' => $order->items()->get()->toArray()]),
            'user_id'
        );

        $order->delete();

        $this->writeActivityLog(
            request(),
            'food_floor_order',
            'delete',
            'Menghapus Floor Order / Request Order: ' . ($deleteSnapshot['order_number'] ?? $id),
            $deleteSnapshot,
            null
        );
        
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Floor Order berhasil dihapus'
            ]);
        }
        
        return redirect()->route('floor-order.index')->with('success', 'Floor Order berhasil dihapus');
    }

    // Submit draft
    public function submit(Request $request, $id)
    {
        $order = FoodFloorOrder::with(['approvalFlows.approver', 'warehouseOutlet'])->findOrFail($id);

        if ($order->fo_mode === 'RO Khusus') {
            if (! $this->approvalService->usesCustomFlow($order)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approver wajib dipilih sebelum mengirim RO Khusus.',
                ], 422);
            }
        }

        try {
            // Pastikan harga baris FO = item_prices / FGR +12% terkini sebelum budget & approve
            app(FloorOrderPriceAuditor::class)->refreshOrder((int) $order->id);
            $order->refresh();
            $order->load('items');

            if ($order->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request Order tidak memiliki item. Tambahkan minimal 1 item sebelum submit.',
                ], 422);
            }

            if ($order->fo_mode === 'RO Supplier') {
                $processedItems = $this->buildProcessedItemsFromOrder($order);
                $this->assertSupplierMappedForRoSupplierItems($processedItems);
                $this->syncSupplierGroupsFromProcessedItems((int) $order->id, $processedItems);
            }

            // Budget checking untuk RO yang akan di-approve (RO Utama/Tambahan)
            if ($order->fo_mode !== 'RO Khusus') {

                $budgetCheckResult = $this->checkBudgetForFloorOrder($order);
                if (! $budgetCheckResult['success']) {
                    \Log::error('FO_SUBMIT: Budget check failed', [
                        'order_id' => $order->id,
                        'message' => $budgetCheckResult['message'],
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $budgetCheckResult['message'],
                    ], 422);
                }

            }

            $oldData = $order->toArray();

            $date = now()->format('Ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $order_number = 'RO-' . $date . '-' . $random;

            $order->update([
                'status' => $order->fo_mode === 'RO Khusus' ? 'submitted' : 'approved',
                'order_number' => $order_number,
            ]);

            // Kirim notifikasi jika RO Khusus
            if ($order->fo_mode === 'RO Khusus' && $order->status === 'submitted') {
                $order->refresh();
                $order->load(['approvalFlows.approver', 'warehouseOutlet']);
                if ($this->approvalService->usesCustomFlow($order)) {
                    $this->approvalService->notifyFirstApprover($order);
                } else {
                    $this->sendNotificationByWarehouse($order->warehouse_outlet_id, $order->id, $order_number);
                }
            }

            $this->writeActivityLog(
                $request,
                'food_floor_order',
                'submit',
                'Submit Floor Order / Request Order: ' . $order_number,
                $oldData,
                $order->fresh()->toArray()
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('FO_SUBMIT: Error', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // Cek apakah sudah ada FO Utama/Tambahan di hari dan outlet yang sama
    public function checkExists(Request $request)
    {
        $tanggal = $request->tanggal;
        $id_outlet = $request->id_outlet;
        $fo_mode = $request->fo_mode;
        $exclude_id = $request->exclude_id;
        $warehouse_outlet_id = $request->warehouse_outlet_id;
        $currentHour = now('Asia/Jakarta')->hour;

        // Logika untuk mengatasi masalah RO Utama yang dibuat jam 00:01:
        // - RO yang dibuat sebelum jam 12:00 untuk tanggal X, dianggap untuk tanggal X+1 (besok)
        // - RO yang dibuat setelah jam 12:00 untuk tanggal X, dianggap untuk tanggal X yang sama (hari ini)
        // 
        // Contoh:
        // - RO dibuat jam 00:01 tanggal 6 Jan untuk tanggal 7 Jan → dianggap untuk tanggal 7 Jan (besok)
        // - RO dibuat jam 22:00 tanggal 7 Jan untuk tanggal 7 Jan → dianggap untuk tanggal 7 Jan (hari ini)
        // - Jadi kedua RO tersebut bisa ada bersamaan karena berbeda "effective date"

        $query = \App\Models\FoodFloorOrder::where(function($q) use ($tanggal, $currentHour) {
                // Jika user input setelah jam 12:00 untuk tanggal X
                if ($currentHour >= 12) {
                    // Cek RO dengan tanggal X yang dibuat SETELAH jam 12:00
                    // (karena RO yang dibuat sebelum jam 12:00 dianggap untuk hari berikutnya, bukan hari ini)
                    $q->where('tanggal', $tanggal)
                      ->whereRaw('HOUR(created_at) >= 12');
                } else {
                    // Jika user input sebelum jam 12:00 untuk tanggal X
                    // Cek RO dengan tanggal X yang dibuat SEBELUM jam 12:00
                    // (karena RO yang dibuat setelah jam 12:00 dianggap untuk hari yang sama, bukan hari berikutnya)
                    $q->where('tanggal', $tanggal)
                      ->whereRaw('HOUR(created_at) < 12');
                }
            })
            ->where('id_outlet', $id_outlet)
            ->where('fo_mode', $fo_mode)
            ->whereNotIn('status', ['rejected']);

        if ($warehouse_outlet_id) {
            $query->where('warehouse_outlet_id', $warehouse_outlet_id);
        }
        if ($exclude_id) {
            $query->where('id', '!=', $exclude_id);
        }

        $exists = $query->exists();
        return response()->json(['exists' => $exists]);
    }

    public function show($id)
    {
        $order = FoodFloorOrder::with(['outlet', 'requester', 'foSchedule', 'approver', 'warehouseOutlet', 'approvalFlows.approver'])->findOrFail($id);
        
        // Load items dari relasi untuk semua mode
        $order->load('items.category');
        $this->appendEditWindowMeta($order);
        
        return Inertia::render('FloorOrder/Show', [
            'order' => $order,
            'user' => Auth::user()->load('outlet'),
            'itemsBySupplier' => $order->fo_mode === 'RO Supplier'
                ? $this->loadItemsBySupplier((int) $order->id)
                : [],
        ]);
    }

    // Tambahkan method sendNotification
    private function sendNotification($userIds, $type, $title, $message, $url) {
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
            ];
        }
        NotificationService::createMany($data);
    }

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $order = FoodFloorOrder::with(['approvalFlows.approver', 'warehouseOutlet'])->findOrFail($id);

        if (($order->fo_mode !== 'RO Khusus') || $order->status !== 'submitted') {
            abort(400, 'Tidak bisa approve order ini');
        }

        if (! $this->approvalService->canUserApprove($user, $order)) {
            abort(403, 'Unauthorized - Anda tidak memiliki hak untuk approve RO Khusus ini');
        }

        $isReject = ($request->has('approved') && $request->approved === false)
            || ($request->has('reject') && $request->reject === true);

        $note = $request->input('note') ?? $request->input('comment')
            ?? $request->input('notes') ?? $request->input('reason');

        if (! $isReject) {
            app(FloorOrderPriceAuditor::class)->refreshOrder((int) $order->id);
            $order->refresh();
            $order->load(['items', 'approvalFlows.approver', 'warehouseOutlet']);
        }

        try {
            $flowResult = $this->approvalService->resolveCurrentFlow($order, $user, $isReject, $note);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Approval gagal.';
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->withErrors(['approval' => $message]);
        }

        if ($flowResult['rejected']) {
            $order->update([
                'status' => 'rejected',
                'approval_by' => $user->id,
                'approval_at' => now(),
                'approval_notes' => $note,
            ]);

            if ($order->user_id) {
                $this->sendNotification(
                    [$order->user_id],
                    'floor_order_rejected',
                    'RO Khusus Ditolak',
                    "RO Khusus {$order->order_number} telah ditolak.",
                    route('floor-order.show', $order->id)
                );
            }

            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => 'reject',
                'module' => 'food_floor_order',
                'description' => 'Reject Floor Order: ' . $order->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $order->fresh()->toArray(),
            ]);

            $message = 'RO Khusus berhasil ditolak';
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->back()->with('success', $message);
        }

        if (! $flowResult['final']) {
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => 'approve',
                'module' => 'food_floor_order',
                'description' => 'Partial approve Floor Order: ' . $order->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $order->fresh()->toArray(),
            ]);

            $message = 'Approval tercatat. Menunggu persetujuan approver berikutnya.';
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->back()->with('success', $message);
        }

        if (! $isReject) {
            $budgetCheckResult = $this->checkBudgetForFloorOrder($order);
            if (! $budgetCheckResult['success']) {
                \Log::error('FO_APPROVE: Budget check failed', [
                    'order_id' => $order->id,
                    'message' => $budgetCheckResult['message'],
                ]);
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $budgetCheckResult['message'],
                        'violations' => $budgetCheckResult['violations'] ?? null,
                    ], 422);
                }

                return redirect()->back()->withErrors(['budget' => $budgetCheckResult['message']]);
            }
        }

        $order->update([
            'status' => 'approved',
            'approval_by' => $user->id,
            'approval_at' => now(),
            'approval_notes' => $note,
        ]);

        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'approve',
            'module' => 'food_floor_order',
            'description' => 'Approve Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $order->fresh()->toArray(),
        ]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'RO Khusus berhasil di-approve',
            ]);
        }

        return redirect()->back()->with('success', 'Floor Order berhasil di-approve');
    }

    // Method untuk mengecek apakah user bisa approve berdasarkan warehouse outlet
    private function canUserApproveByWarehouse($user, $warehouseOutletId)
    {
        // Ambil warehouse outlet
        $warehouseOutlet = \DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->first();
        if (!$warehouseOutlet) {
            return false;
        }

        $warehouseName = $warehouseOutlet->name;
        $userJabatan = $user->id_jabatan;
        $userStatus = $user->status;

        // Cek berdasarkan nama warehouse outlet
        // Sekarang semua jabatan yang menerima notifikasi juga bisa approve
        switch ($warehouseName) {
            case 'Kitchen':
                // Jabatan yang bisa approve: semua yang menerima notifikasi
                return in_array($userJabatan, [163, 174, 180, 345, 346, 347, 348, 349]) && $userStatus === 'A';
            case 'Bar':
                // Jabatan yang bisa approve: semua yang menerima notifikasi
                return in_array($userJabatan, [175, 182, 323]) && $userStatus === 'A';
            case 'Service':
                // Jabatan yang bisa approve: semua yang menerima notifikasi
                return in_array($userJabatan, [176, 322, 164, 321]) && $userStatus === 'A';
            default:
                return false;
        }
    }

    // Method untuk mengirim notifikasi berdasarkan warehouse outlet
    private function sendNotificationByWarehouse($warehouseOutletId, $orderId, $orderNumber)
    {
        // Ambil warehouse outlet
        $warehouseOutlet = \DB::table('warehouse_outlets')->where('id', $warehouseOutletId)->first();
        if (!$warehouseOutlet) {
            return;
        }

        $warehouseName = $warehouseOutlet->name;
        $jabatanIds = [];

        // Tentukan jabatan berdasarkan nama warehouse outlet
        switch ($warehouseName) {
            case 'Kitchen':
                $jabatanIds = [163, 174, 180, 345, 346, 347, 348, 349];
                break;
            case 'Bar':
                $jabatanIds = [175, 182, 323];
                break;
            case 'Service':
                $jabatanIds = [176, 322, 164, 321];
                break;
            default:
                return; // Tidak ada notifikasi untuk warehouse outlet lain
        }

        // Ambil user yang memiliki jabatan tersebut dan status aktif
        $users = \DB::table('users')
            ->whereIn('id_jabatan', $jabatanIds)
            ->where('status', 'A')
            ->pluck('id')
            ->toArray();

        if (empty($users)) {
            return;
        }

        // Kirim notifikasi
        $data = [];
        foreach ($users as $userId) {
            $data[] = [
                'user_id' => $userId,
                'type' => 'floor_order_approval',
                'title' => 'Approval RO Khusus',
                'message' => "RO Khusus {$orderNumber} dari warehouse {$warehouseName} menunggu approval Anda.",
                'url' => route('floor-order.show', $orderId),
                'is_read' => 0,
            ];
        }
        NotificationService::createMany($data);
    }

    public function index(Request $request)
    {
        $user = auth()->user()->load('outlet');
        $query = FoodFloorOrder::with(['outlet', 'requester', 'foSchedule', 'warehouseOutlet']);
        if ($user->id_outlet != 1) {
            $query->where('id_outlet', $user->id_outlet);
        }
        if ($request->search) {
            $search = $request->search;
            $query->where('order_number', 'like', "%$search%")
                  ->orWhereHas('outlet', function($q) use ($search) {
                      $q->where('nama_outlet', 'like', "%$search%") ;
                  });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->start_date) {
            $query->whereDate('tanggal', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('tanggal', '<=', $request->end_date);
        }
        $floorOrders = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        // Load items untuk semua order
        $floorOrders->getCollection()->transform(function($order) {
          
            $order->loadMissing('items');
            $order->setRelation('outlet', $order->outlet);
            $order->setRelation('requester', $order->requester);
            
            // Cek apakah RO sudah ada di packing list
            $order->has_packing_list = \DB::table('food_packing_lists')
                ->where('food_floor_order_id', $order->id)
                ->exists();
            $this->appendEditWindowMeta($order);
            $order->setRelation('foSchedule', $order->foSchedule);
            $order->setRelation('warehouseOutlet', $order->warehouseOutlet);
            return $order;
        });

        return Inertia::render('FloorOrder/Index', [
            'user' => $user,
            'floorOrders' => $floorOrders,
            'filters' => $request->only(['search', 'status', 'start_date', 'end_date']),
        ]);
    }

    // API: List floor orders (Approval App)
    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $query = FoodFloorOrder::with(['outlet', 'requester', 'foSchedule', 'warehouseOutlet', 'items.category']);

        if ($user && $user->id_outlet != 1) {
            $query->where('id_outlet', $user->id_outlet);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%$search%")
                  ->orWhereHas('outlet', function($q) use ($search) {
                      $q->where('nama_outlet', 'like', "%$search%") ;
                  });
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->start_date) {
            $query->whereDate('tanggal', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('tanggal', '<=', $request->end_date);
        }

        $perPage = (int) ($request->get('per_page') ?? 10);
        $floorOrders = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        $floorOrders->getCollection()->transform(function($order) {
            $order->loadMissing('items');
            $order->has_packing_list = \DB::table('food_packing_lists')
                ->where('food_floor_order_id', $order->id)
                ->exists();
            $this->appendEditWindowMeta($order);
            $order->total_amount = $order->items->sum(function($item) {
                return ($item->qty ?? 0) * ($item->price ?? 0);
            });
            return $order;
        });

        return response()->json($floorOrders);
    }

    // API: Floor order detail (Approval App)
    public function apiShow($id)
    {
        $order = FoodFloorOrder::with([
            'outlet',
            'requester',
            'foSchedule',
            'approver',
            'warehouseOutlet',
            'approvalFlows.approver',
            'items.category',
            'items.item'
        ])->findOrFail($id);

        $order->total_amount = $order->items->sum(function($item) {
            return ($item->qty ?? 0) * ($item->price ?? 0);
        });

        $order->has_packing_list = \DB::table('food_packing_lists')
            ->where('food_floor_order_id', $order->id)
            ->exists();
        $this->appendEditWindowMeta($order);
        $order->setAttribute('items_by_supplier', $order->fo_mode === 'RO Supplier'
            ? $this->loadItemsBySupplier((int) $order->id)
            : []);

        return response()->json($order);
    }

    public function warehouseOutlet() {
        return $this->belongsTo(WarehouseOutlet::class, 'warehouse_outlet_id');
    }

    // API untuk mengambil RO Supplier yang tersedia untuk dibuat PO
    public function supplierAvailable()
    {
        try {
            $user = auth()->user();
            
            // Pastikan user terautentikasi
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }
            
            // Query untuk RO Supplier yang sudah approved dan belum packing
            $query = FoodFloorOrder::with(['items', 'outlet', 'warehouseOutlet'])
                ->where('fo_mode', 'RO Supplier')
                ->whereIn('status', ['approved', 'submitted'])
                ->whereNotNull('id_outlet'); // Pastikan id_outlet tidak null
            
            // Jika user bukan superuser (id_outlet != 1), hanya tampilkan RO dari outlet mereka
            if ($user->id_outlet != 1) {
                $query->where('id_outlet', $user->id_outlet);
            }
            // Jika user adalah superuser (id_outlet = 1), tampilkan semua RO Supplier
            
            $roSuppliers = $query->orderBy('created_at', 'desc')->get();

            // Filter RO Supplier yang belum semua itemnya dibuat PO
            $roSuppliers = $roSuppliers->filter(function($ro) {
                $totalItems = \DB::table('food_floor_order_items')->where('floor_order_id', $ro->id)->count();
                $itemsInPO = \App\Models\PurchaseOrderFoodItem::where('ro_id', $ro->id)->count();
                
                // Hanya tampilkan RO yang belum semua itemnya dibuat PO
                return $totalItems > $itemsInPO;
            });

            // Debug: Cek unique outlet IDs
            $uniqueOutlets = $roSuppliers->pluck('id_outlet')->unique()->values();

            // Debug: Log RO Supplier yang difilter

            // Debug logging

            // Transform data untuk frontend
            $transformedData = $roSuppliers->map(function($ro) {
                return [
                    'id' => $ro->id,
                    'order_number' => $ro->order_number,
                    'tanggal' => $ro->tanggal,
                    'description' => $ro->description,
                    'status' => $ro->status,
                    'id_outlet' => $ro->id_outlet,
                    'outlet_name' => $ro->outlet ? $ro->outlet->nama_outlet : 'Unknown Outlet',
                    'warehouse_outlet_id' => $ro->warehouse_outlet_id,
                    'warehouse_outlet_name' => $ro->warehouseOutlet ? $ro->warehouseOutlet->name : 'Unknown Warehouse',
                    'items' => $ro->items->map(function($item) use ($ro) {
                        return [
                            'id' => $item->id,
                            'item_id' => $item->item_id,
                            'item_name' => $item->item->name ?? $item->item_name ?? 'Unknown Item',
                            'qty' => $item->qty,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'subtotal' => $item->subtotal,
                            'category_id' => $item->category_id,
                            'warehouse_division_id' => $item->warehouse_division_id,
                            'arrival_date' => $item->arrival_date ?? null,
                            'source' => 'ro_supplier', // Mark as RO Supplier item
                            'ro_id' => $ro->id,
                            'ro_number' => $ro->order_number,
                        ];
                    })
                ];
            });

            // Pastikan mengembalikan array, bukan object
            return response()->json($transformedData->values()->toArray());
        } catch (\Exception $e) {
            \Log::error('Error fetching RO Supplier available:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch RO Supplier data'], 500);
        }
    }

    /**
     * Check budget untuk Food Floor Order
     */
    private function checkBudgetForFloorOrder($order)
    {
        return ['success' => true, 'budget_info' => []];
    }

    /**
     * Budget lock berbasis forecast bulanan:
     * 100% forecast -> kurangi 20% => 80%, lalu ambil 40% dari 80% (efektif 32% dari forecast).
     *
     * @return array{forecast_monthly_total: float, lock_budget: float}|null
     */
    private function resolveMonthlyForecastBudget(int $outletId, string $monthStart): ?array
    {
        $header = DB::table('outlet_revenue_target_headers')
            ->where('outlet_id', $outletId)
            ->where('target_month', $monthStart)
            ->first(['id']);
        if (! $header) {
            return null;
        }

        $forecastMonthlyTotal = (float) (DB::table('outlet_revenue_target_details')
            ->where('header_id', $header->id)
            ->sum('forecast_revenue') ?? 0);
        if ($forecastMonthlyTotal <= 0) {
            return null;
        }

        $usableAfterReserve = $forecastMonthlyTotal * self::FORECAST_AFTER_RESERVE_RATIO;
        $lockBudget = round($usableAfterReserve * self::FORECAST_LOCK_RATIO_OF_REST, 2);

        return [
            'forecast_monthly_total' => round($forecastMonthlyTotal, 2),
            'lock_budget' => $lockBudget,
        ];
    }

    /**
     * Retail Food dihitung khusus payment_method = contra_bon.
     *
     * @return array{retail_food_total: float, food_floor_order_total: float}
     */
    private function monthlyUsageContraBonAndRo(int $outletId, int $subCategoryId, string $monthYm, ?int $excludeFloorOrderId = null): array
    {
        $retailFoodTotal = (float) (DB::table('retail_food_items as rfi')
            ->join('retail_food as rf', 'rfi.retail_food_id', '=', 'rf.id')
            ->join('items as i', DB::raw('TRIM(i.name)'), '=', DB::raw('TRIM(rfi.item_name)'))
            ->where('i.sub_category_id', $subCategoryId)
            ->where('rf.outlet_id', $outletId)
            ->where('rf.status', 'approved')
            ->where('rf.payment_method', 'contra_bon')
            ->whereRaw("DATE_FORMAT(rf.transaction_date, '%Y-%m') = ?", [$monthYm])
            ->sum('rfi.subtotal') ?? 0);

        $foQuery = DB::table('food_floor_order_items as ffoi')
            ->join('food_floor_orders as ffo', 'ffoi.floor_order_id', '=', 'ffo.id')
            ->join('items as i', 'ffoi.item_id', '=', 'i.id')
            ->where('i.sub_category_id', $subCategoryId)
            ->where('ffo.id_outlet', $outletId)
            ->whereIn('ffo.status', ['approved', 'received'])
            ->whereRaw("DATE_FORMAT(ffo.tanggal, '%Y-%m') = ?", [$monthYm]);

        if ($excludeFloorOrderId !== null) {
            $foQuery->where('ffo.id', '!=', $excludeFloorOrderId);
        }

        $foodFloorOrderTotal = (float) ($foQuery->sum('ffoi.subtotal') ?? 0);

        return [
            'retail_food_total' => round($retailFoodTotal, 2),
            'food_floor_order_total' => round($foodFloorOrderTotal, 2),
        ];
    }

    // API: Get pending RO Khusus approvals
    public function getPendingROKhususApprovals(Request $request)
    {
        try {
            $user = Auth::user();

            $allApprovals = FoodFloorOrder::with(['outlet', 'requester', 'warehouseOutlet', 'items', 'approvalFlows.approver'])
                ->where('fo_mode', 'RO Khusus')
                ->where('status', 'submitted')
                ->orderByDesc('created_at')
                ->get();

            $filtered = $this->approvalService->filterPendingForUser($allApprovals, $user);

            $pendingApprovals = [];
            foreach ($filtered as $order) {
                $warehouseName = $order->warehouseOutlet ? $order->warehouseOutlet->name : 'Unknown';
                $nextFlow = $this->approvalService->nextPendingFlow($order);
                $approverName = $nextFlow?->approver?->nama_lengkap
                    ?? $this->getApprovalLevelDisplay($warehouseName);

                $pendingApprovals[] = [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'tanggal' => $order->tanggal,
                    'arrival_date' => $order->arrival_date,
                    'outlet' => $order->outlet ? ['nama_outlet' => $order->outlet->nama_outlet] : null,
                    'warehouse_outlet' => $order->warehouseOutlet ? ['name' => $order->warehouseOutlet->name] : null,
                    'requester' => $order->requester ? ['nama_lengkap' => $order->requester->nama_lengkap] : null,
                    'items_count' => $order->items->count(),
                    'description' => $order->description,
                    'approval_level' => $nextFlow ? ('level_' . $nextFlow->approval_level) : 'ro_khusus',
                    'approval_level_display' => $nextFlow
                        ? ('Level ' . $nextFlow->approval_level . ': ' . ($nextFlow->approver?->nama_lengkap ?? '-'))
                        : $this->getApprovalLevelDisplay($warehouseName),
                    'approver_name' => $approverName,
                    'current_approval_flow_id' => $nextFlow?->id,
                    'created_at' => $order->created_at,
                ];
            }

            return response()->json([
                'success' => true,
                'ro_khusus' => $pendingApprovals,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending RO Khusus approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }

    // Helper untuk mendapatkan approval level display
    private function getApprovalLevelDisplay($warehouseName)
    {
        switch ($warehouseName) {
            case 'Kitchen':
                return 'Kitchen Manager';
            case 'Bar':
                return 'Bar Manager';
            case 'Service':
                return 'Service Manager';
            default:
                return 'Manager';
        }
    }
    
    // Helper untuk mendapatkan approver berdasarkan warehouse outlet
    private function getApproverByWarehouse($warehouseName)
    {
        $jabatanIds = [];
        switch ($warehouseName) {
            case 'Kitchen':
                $jabatanIds = [163, 174, 180, 345, 346, 347, 348, 349];
                break;
            case 'Bar':
                $jabatanIds = [175, 182, 323];
                break;
            case 'Service':
                $jabatanIds = [176, 322, 164, 321];
                break;
            default:
                return null;
        }
        
        // Get first active user with matching jabatan
        return \App\Models\User::whereIn('id_jabatan', $jabatanIds)
            ->where('status', 'A')
            ->first();
    }

    // API: Get RO Khusus detail for approval modal
    public function getROKhususDetail($id)
    {
        try {
            $order = FoodFloorOrder::with([
                'outlet',
                'requester',
                'warehouseOutlet',
                'approver',
                'approvalFlows.approver',
                'items.item',
                'items.category',
            ])->findOrFail($id);
            
            // Ensure items have proper data
            $order->items->transform(function($item) {
                if (!$item->item && $item->item_id) {
                    $item->item = \App\Models\Item::find($item->item_id);
                }
                // Unit is already in the item as 'unit' column (varchar)
                // No need to transform, just ensure it's accessible
                return $item;
            });
            
            // Calculate total_amount from items (qty * price)
            $totalAmount = $order->items->sum(function($item) {
                return ($item->qty ?? 0) * ($item->price ?? 0);
            });
            
            // Add total_amount to order object
            $order->total_amount = $totalAmount;
            
            $nextFlow = $this->approvalService->nextPendingFlow($order);

            return response()->json([
                'success' => true,
                'ro_khusus' => $order,
                'current_approval_flow_id' => $nextFlow?->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting RO Khusus detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load RO Khusus detail'
            ], 500);
        }
    }

    public function exportPdf($id)
    {
        $order = FoodFloorOrder::with([
            'outlet', 
            'requester', 
            'foSchedule', 
            'approver', 
            'warehouseOutlet',
            'items.category'
        ])->findOrFail($id);

        // Group items by category
        $groupedItems = [];
        foreach ($order->items as $item) {
            $categoryName = $item->category ? $item->category->name : 'Lainnya';
            if (!isset($groupedItems[$categoryName])) {
                $groupedItems[$categoryName] = [];
            }
            $groupedItems[$categoryName][] = $item;
        }

        // Calculate subtotals
        $subtotal = $order->items->sum(function($item) {
            return ($item->qty ?? 0) * ($item->price ?? 0);
        });

        $pdf = Pdf::loadView('exports.floor_order_pdf', [
            'order' => $order,
            'groupedItems' => $groupedItems,
            'subtotal' => $subtotal
        ]);

        $filename = 'RO-' . $order->order_number . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Info sisa budget bulan berjalan berbasis forecast bulanan.
     * Budget lock = 40% dari (forecast bulanan setelah dikurangi 20%).
     */
    public function forecastBudgetVsInput(Request $request)
    {
        $validated = $request->validate([
            'arrival_date' => 'required|date',
            'warehouse_outlet_id' => 'required|integer',
            'exclude_floor_order_id' => 'nullable|integer',
            'current_input_total' => 'nullable|numeric|min:0',
        ]);

        $user = Auth::user();
        $outletId = (int) ($user->id_outlet ?? 0);
        if ($outletId <= 0) {
            return response()->json(['success' => false, 'message' => 'Outlet tidak valid'], 422);
        }

        $monthStart = Carbon::parse($validated['arrival_date'])->copy()->startOfMonth()->toDateString();
        $whId = (int) $validated['warehouse_outlet_id'];

        $wo = DB::table('warehouse_outlets')->where('id', $whId)->first(['id', 'name']);
        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'Warehouse outlet tidak ditemukan'], 422);
        }

        return response()->json([
            'success' => true,
            'budget_lock_active' => false,
            'warehouse_outlet_name' => (string) ($wo->name ?? ''),
            'message' => 'Locking budget Food Floor Order dinonaktifkan.',
        ]);
    }
} 