<?php

namespace App\Http\Controllers;

use App\Support\FoodGrLastPurchaseForItem;
use App\Support\InventorySerialEffectiveQty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class OutletSerialReceiveController extends Controller
{
    public const GR_REJECT_REASONS = [
        'not_found' => 'Serial tidak ditemukan',
        'not_dispatched' => 'Belum keluar via DO',
        'already_received' => 'Sudah diterima outlet',
        'incomplete_do_data' => 'Data DO/outlet tidak lengkap',
        'wrong_outlet' => 'Beda outlet',
        'duplicate_scan' => 'Duplikat scan di sesi yang sama',
    ];

    public function index(Request $request)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        $isHQ = $user->id_outlet == '1';

        $query = DB::table('outlet_serial_receive_headers as h')
            ->leftJoin('users as u', 'u.id', '=', 'h.created_by')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
            ->select(
                'h.id',
                'h.number',
                'h.outlet_id',
                'h.receive_date',
                'h.status',
                'h.notes',
                'h.created_by',
                'u.nama_lengkap as created_by_name',
                'u.avatar as created_by_avatar',
                'o.nama_outlet as outlet_name',
                'h.created_at',
                DB::raw('(SELECT COUNT(*) FROM outlet_serial_receive_items WHERE header_id = h.id) as total_serials')
            )
            ->whereNull('h.deleted_at');

        if (!$isHQ) {
            $query->where('h.outlet_id', $user->id_outlet);
        } elseif ($request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->outlet_id);
        }

        if ($request->filled('date_from')) {
            $query->where('h.receive_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('h.receive_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $query->where('h.number', 'like', '%' . $request->search . '%');
        }

        $data = $query->orderByDesc('h.id')->paginate(20)->withQueryString();

        $outlets = [];
        if ($isHQ) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
        }

        return Inertia::render('OutletSerialReceive/Index', [
            'grList' => $data,
            'filters' => $request->only(['date_from', 'date_to', 'search', 'outlet_id']),
            'outlets' => $outlets,
            'canDelete' => $canDelete,
            'isHQ' => $isHQ,
            'userOutlet' => [
                'id' => $user->id_outlet,
                'name' => $this->getOutletName($user->id_outlet),
            ],
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $outletName = $this->getOutletName($user->id_outlet);

        return Inertia::render('OutletSerialReceive/Create', [
            'userOutlet' => [
                'id' => $user->id_outlet,
                'name' => $outletName,
            ],
        ]);
    }

    public function validateSerial(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|max:50',
        ]);

        $serialNumber = trim($request->serial_number);
        $user = Auth::user();
        $userOutletId = $user->id_outlet ?? null;
        $scannerOutletName = $this->getOutletName($userOutletId);

        $serial = DB::table('inventory_item_serials as s')
            ->leftJoin('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
            ->leftJoin('units as ru', 'ru.id', '=', 's.repack_unit_id')
            ->leftJoin('delivery_orders as do_tbl', 'do_tbl.id', '=', 's.out_delivery_order_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 's.out_outlet_id')
            ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 's.out_warehouse_outlet_id')
            ->select(
                's.id',
                's.serial_number',
                's.item_id',
                's.unit_id',
                's.is_out',
                's.is_received',
                's.out_outlet_id',
                's.out_warehouse_outlet_id',
                's.out_delivery_order_id',
                's.source_type',
                's.source_qty',
                's.generated_qty_unit',
                's.cost_small',
                's.repack_unit_id',
                's.repack_qty',
                'i.name as item_name',
                'u.name as unit_name',
                'ru.name as repack_unit_name',
                'do_tbl.number as do_number',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name'
            )
            ->where('s.serial_number', $serialNumber)
            ->first();

        if (!$serial) {
            return $this->rejectGrScan($user, $userOutletId, $scannerOutletName, $serialNumber, null, 'not_found', 'Nomor seri tidak ditemukan.');
        }
        if (!$serial->is_out) {
            return $this->rejectGrScan($user, $userOutletId, $scannerOutletName, $serialNumber, $serial, 'not_dispatched', 'Nomor seri belum di-dispatch (belum keluar via DO).');
        }
        if ($serial->is_received) {
            return $this->rejectGrScan($user, $userOutletId, $scannerOutletName, $serialNumber, $serial, 'already_received', 'Nomor seri sudah diterima sebelumnya.');
        }
        if (!$serial->out_outlet_id || !$serial->out_warehouse_outlet_id || !$serial->out_delivery_order_id) {
            return $this->rejectGrScan($user, $userOutletId, $scannerOutletName, $serialNumber, $serial, 'incomplete_do_data', 'Data outlet/warehouse/DO pada serial tidak lengkap.');
        }
        if ($userOutletId && $userOutletId != '1' && $userOutletId != $serial->out_outlet_id) {
            $targetName = $serial->outlet_name ?: $serial->out_outlet_id;

            return $this->rejectGrScan(
                $user,
                $userOutletId,
                $scannerOutletName,
                $serialNumber,
                $serial,
                'wrong_outlet',
                "Nomor seri ini ditujukan untuk outlet lain ({$targetName})."
            );
        }

        $effectiveQty = InventorySerialEffectiveQty::resolve($serial);
        $repackLabel = $this->serialRepackLabel($serial, $effectiveQty);

        [$costSmall, $costSourceDb, $costSourceLabel] = $this->resolveSerialReceiveCost($serial);

        return response()->json([
            'valid' => true,
            'message' => 'Nomor seri valid.',
            'serial' => [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'item_id' => $serial->item_id,
                'item_name' => $serial->item_name,
                'unit_id' => $serial->unit_id,
                'unit_name' => $serial->unit_name,
                'qty' => $effectiveQty,
                'do_id' => $serial->out_delivery_order_id,
                'do_number' => $serial->do_number ?? '',
                'outlet_id' => $serial->out_outlet_id,
                'outlet_name' => $serial->outlet_name ?? $serial->out_outlet_id,
                'warehouse_outlet_id' => $serial->out_warehouse_outlet_id,
                'warehouse_name' => $serial->warehouse_name ?? '',
                'cost_small' => round($costSmall, 4),
                'cost_source' => $costSourceLabel,
                'cost_source_key' => $costSourceDb,
                'source_type' => $serial->source_type,
                'repack_label' => $repackLabel,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'serials' => 'required|array|min:1',
            'serials.*.serial_id' => 'required|integer',
            'serials.*.serial_number' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $user = Auth::user();

        try {
            return DB::transaction(function () use ($request, $user) {
                $serialIds = collect($request->serials)->pluck('serial_id')->toArray();

                $serials = DB::table('inventory_item_serials')
                    ->whereIn('id', $serialIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($serials as $s) {
                    if (!$s->is_out) {
                        return back()->withErrors(['serials' => "Serial {$s->serial_number} belum di-dispatch."]);
                    }
                    if ($s->is_received) {
                        return back()->withErrors(['serials' => "Serial {$s->serial_number} sudah diterima."]);
                    }
                }

                $outletId = $user->id_outlet;
                $firstSerial = $serials->first();
                if ($firstSerial && $firstSerial->out_outlet_id) {
                    $outletId = $firstSerial->out_outlet_id;
                }

                $dateStr = now()->format('Ymd');
                $lockName = "gr_serial_number_{$dateStr}";
                DB::select("SELECT GET_LOCK(?, 5)", [$lockName]);

                $lastNumber = DB::table('outlet_serial_receive_headers')
                    ->where('number', 'like', "GSR-{$dateStr}-%")
                    ->orderByDesc('number')
                    ->value('number');

                $seq = 1;
                if ($lastNumber) {
                    $parts = explode('-', $lastNumber);
                    $seq = ((int) end($parts)) + 1;
                }
                $grNumber = "GSR-{$dateStr}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);

                $headerId = DB::table('outlet_serial_receive_headers')->insertGetId([
                    'number' => $grNumber,
                    'outlet_id' => $outletId,
                    'receive_date' => now()->toDateString(),
                    'status' => 'completed',
                    'notes' => $request->notes,
                    'created_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::select("SELECT RELEASE_LOCK(?)", [$lockName]);

                $itemMasterIds = $serials->pluck('item_id')->unique()->toArray();
                $itemMasters = DB::table('items')->whereIn('id', $itemMasterIds)->get()->keyBy('id');

                foreach ($request->serials as $input) {
                    $serial = $serials[$input['serial_id']] ?? null;
                    if (!$serial) continue;

                    $itemMaster = $itemMasters[$serial->item_id] ?? null;
                    if (!$itemMaster) continue;

                    $serialOutletId = $serial->out_outlet_id;
                    $warehouseOutletId = $serial->out_warehouse_outlet_id;
                    $doId = $serial->out_delivery_order_id;
                    $doNumber = DB::table('delivery_orders')->where('id', $doId)->value('number') ?? '';
                    $unitId = $serial->unit_id;

                    $effectiveQty = InventorySerialEffectiveQty::resolve($serial);

                    [$costSmall, $costSourceDb] = $this->resolveSerialReceiveCost($serial);

                    DB::table('outlet_serial_receive_items')->insert([
                        'header_id' => $headerId,
                        'serial_id' => $serial->id,
                        'serial_number' => $serial->serial_number,
                        'delivery_order_id' => $doId,
                        'delivery_order_number' => $doNumber,
                        'item_id' => $serial->item_id,
                        'unit_id' => $unitId,
                        'qty' => $effectiveQty,
                        'outlet_id' => $serialOutletId,
                        'warehouse_outlet_id' => $warehouseOutletId,
                        'cost_small' => $costSmall,
                        'cost_source' => $costSourceDb,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->processInventory($serial, $itemMaster, $costSmall, $effectiveQty, $serialOutletId, $warehouseOutletId, $headerId);

                    DB::table('inventory_item_serials')
                        ->where('id', $serial->id)
                        ->update([
                            'is_received' => 1,
                            'received_at' => now(),
                            'received_by' => $user->id,
                            'received_outlet_gr_id' => $headerId,
                            'updated_at' => now(),
                        ]);
                }

                return redirect("/outlet-serial-receive/{$headerId}")->with('success', "GR Serial {$grNumber} berhasil disimpan.");
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['serials' => 'Gagal menyimpan: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        $header = DB::table('outlet_serial_receive_headers as h')
            ->leftJoin('users as u', 'u.id', '=', 'h.created_by')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
            ->select('h.*', 'u.nama_lengkap as created_by_name', 'o.nama_outlet as outlet_name')
            ->where('h.id', $id)
            ->whereNull('h.deleted_at')
            ->first();

        if (!$header) {
            abort(404);
        }

        $items = DB::table('outlet_serial_receive_items as si')
            ->leftJoin('items as i', 'i.id', '=', 'si.item_id')
            ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'si.outlet_id')
            ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 'si.warehouse_outlet_id')
            ->select(
                'si.*',
                'i.name as item_name',
                'u.name as unit_name',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name'
            )
            ->where('si.header_id', $id)
            ->orderBy('si.delivery_order_number')
            ->orderBy('si.id')
            ->get();

        return Inertia::render('OutletSerialReceive/Show', [
            'header' => $header,
            'items' => $items,
            'canDelete' => $canDelete,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        if (!$canDelete) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus.'], 403);
            }

            return back()->withErrors(['message' => 'Anda tidak memiliki akses untuk menghapus.']);
        }

        $header = DB::table('outlet_serial_receive_headers')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$header) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
            }

            return back()->withErrors(['message' => 'Data tidak ditemukan.']);
        }

        try {
            DB::transaction(function () use ($id) {
                $items = DB::table('outlet_serial_receive_items')->where('header_id', $id)->get();

                $serialIds = $items->pluck('serial_id')->toArray();
                if (!empty($serialIds)) {
                    DB::table('inventory_item_serials')
                        ->whereIn('id', $serialIds)
                        ->lockForUpdate()
                        ->get();
                }

                $itemMasterIds = $items->pluck('item_id')->unique()->toArray();
                $itemMasters = DB::table('items')->whereIn('id', $itemMasterIds)->get()->keyBy('id');

                foreach ($items as $item) {
                    $itemMaster = $itemMasters[$item->item_id] ?? null;
                    if (!$itemMaster) continue;

                    $this->rollbackInventory($item, $itemMaster);

                    DB::table('inventory_item_serials')
                        ->where('id', $item->serial_id)
                        ->update([
                            'is_received' => 0,
                            'received_at' => null,
                            'received_by' => null,
                            'received_outlet_gr_id' => null,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('outlet_serial_receive_items')->where('header_id', $id)->delete();

                DB::table('outlet_serial_receive_headers')
                    ->where('id', $id)
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            });

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "GR Serial {$header->number} berhasil dihapus.",
                ]);
            }

            return redirect('/outlet-serial-receive')->with('success', "GR Serial {$header->number} berhasil dihapus.");
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()], 500);
            }

            return back()->withErrors(['message' => 'Gagal menghapus: ' . $e->getMessage()]);
        }
    }

    // ==================== API Methods (approval-app / mobile) ====================

    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $isHQ = $user->id_outlet == '1';

        $query = DB::table('outlet_serial_receive_headers as h')
            ->leftJoin('users as u', 'u.id', '=', 'h.created_by')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
            ->select(
                'h.id',
                'h.number',
                'h.outlet_id',
                'h.receive_date',
                'h.status',
                'h.notes',
                'h.created_by',
                'u.nama_lengkap as created_by_name',
                'u.avatar as created_by_avatar',
                'o.nama_outlet as outlet_name',
                'h.created_at',
                DB::raw('(SELECT COUNT(*) FROM outlet_serial_receive_items WHERE header_id = h.id) as total_serials')
            )
            ->whereNull('h.deleted_at');

        if (!$isHQ) {
            $query->where('h.outlet_id', $user->id_outlet);
        } elseif ($request->filled('outlet_id')) {
            $query->where('h.outlet_id', $request->outlet_id);
        }

        if ($request->filled('date_from')) {
            $query->where('h.receive_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('h.receive_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $query->where('h.number', 'like', '%' . $request->search . '%');
        }

        $perPage = $request->input('per_page', 20);
        $data = $query->orderByDesc('h.id')->paginate($perPage)->withQueryString();

        $outlets = [];
        if ($isHQ) {
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();
        }

        return response()->json([
            'data' => $data,
            'outlets' => $outlets,
            'is_hq' => $isHQ,
            'can_delete' => ($user->id_role === '5af56935b011a') || ($user->division_id == 11),
            'user_outlet' => [
                'id' => $user->id_outlet,
                'name' => $this->getOutletName($user->id_outlet),
            ],
        ]);
    }

    public function apiShow($id)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        $header = DB::table('outlet_serial_receive_headers as h')
            ->leftJoin('users as u', 'u.id', '=', 'h.created_by')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'h.outlet_id')
            ->select('h.*', 'u.nama_lengkap as created_by_name', 'o.nama_outlet as outlet_name')
            ->where('h.id', $id)
            ->whereNull('h.deleted_at')
            ->first();

        if (!$header) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $items = DB::table('outlet_serial_receive_items as si')
            ->leftJoin('items as i', 'i.id', '=', 'si.item_id')
            ->leftJoin('units as u', 'u.id', '=', 'si.unit_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 'si.outlet_id')
            ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 'si.warehouse_outlet_id')
            ->select(
                'si.*',
                'i.name as item_name',
                'u.name as unit_name',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name'
            )
            ->where('si.header_id', $id)
            ->orderBy('si.delivery_order_number')
            ->orderBy('si.id')
            ->get();

        return response()->json([
            'header' => $header,
            'items' => $items,
            'can_delete' => $canDelete,
        ]);
    }

    public function apiStore(Request $request)
    {
        $request->validate([
            'serials' => 'required|array|min:1',
            'serials.*.serial_id' => 'required|integer',
            'serials.*.serial_number' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $user = Auth::user();

        try {
            $result = DB::transaction(function () use ($request, $user) {
                $serialIds = collect($request->serials)->pluck('serial_id')->toArray();

                $serials = DB::table('inventory_item_serials')
                    ->whereIn('id', $serialIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($serials as $s) {
                    if (!$s->is_out) {
                        return ['success' => false, 'message' => "Serial {$s->serial_number} belum di-dispatch."];
                    }
                    if ($s->is_received) {
                        return ['success' => false, 'message' => "Serial {$s->serial_number} sudah diterima."];
                    }
                }

                $outletId = $user->id_outlet;
                $firstSerial = $serials->first();
                if ($firstSerial && $firstSerial->out_outlet_id) {
                    $outletId = $firstSerial->out_outlet_id;
                }

                $dateStr = now()->format('Ymd');
                $lockName = "gr_serial_number_{$dateStr}";
                DB::select("SELECT GET_LOCK(?, 5)", [$lockName]);

                $lastNumber = DB::table('outlet_serial_receive_headers')
                    ->where('number', 'like', "GSR-{$dateStr}-%")
                    ->orderByDesc('number')
                    ->value('number');

                $seq = 1;
                if ($lastNumber) {
                    $parts = explode('-', $lastNumber);
                    $seq = ((int) end($parts)) + 1;
                }
                $grNumber = "GSR-{$dateStr}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);

                $headerId = DB::table('outlet_serial_receive_headers')->insertGetId([
                    'number' => $grNumber,
                    'outlet_id' => $outletId,
                    'receive_date' => now()->toDateString(),
                    'status' => 'completed',
                    'notes' => $request->notes,
                    'created_by' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::select("SELECT RELEASE_LOCK(?)", [$lockName]);

                $itemMasterIds = $serials->pluck('item_id')->unique()->toArray();
                $itemMasters = DB::table('items')->whereIn('id', $itemMasterIds)->get()->keyBy('id');

                foreach ($request->serials as $input) {
                    $serial = $serials[$input['serial_id']] ?? null;
                    if (!$serial) continue;

                    $itemMaster = $itemMasters[$serial->item_id] ?? null;
                    if (!$itemMaster) continue;

                    $serialOutletId = $serial->out_outlet_id;
                    $warehouseOutletId = $serial->out_warehouse_outlet_id;
                    $doId = $serial->out_delivery_order_id;
                    $doNumber = DB::table('delivery_orders')->where('id', $doId)->value('number') ?? '';
                    $unitId = $serial->unit_id;

                    $effectiveQty = InventorySerialEffectiveQty::resolve($serial);

                    [$costSmall, $costSourceDb] = $this->resolveSerialReceiveCost($serial);

                    DB::table('outlet_serial_receive_items')->insert([
                        'header_id' => $headerId,
                        'serial_id' => $serial->id,
                        'serial_number' => $serial->serial_number,
                        'delivery_order_id' => $doId,
                        'delivery_order_number' => $doNumber,
                        'item_id' => $serial->item_id,
                        'unit_id' => $unitId,
                        'qty' => $effectiveQty,
                        'outlet_id' => $serialOutletId,
                        'warehouse_outlet_id' => $warehouseOutletId,
                        'cost_small' => $costSmall,
                        'cost_source' => $costSourceDb,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->processInventory($serial, $itemMaster, $costSmall, $effectiveQty, $serialOutletId, $warehouseOutletId, $headerId);

                    DB::table('inventory_item_serials')
                        ->where('id', $serial->id)
                        ->update([
                            'is_received' => 1,
                            'received_at' => now(),
                            'received_by' => $user->id,
                            'received_outlet_gr_id' => $headerId,
                            'updated_at' => now(),
                        ]);
                }

                return ['success' => true, 'message' => "GR Serial {$grNumber} berhasil disimpan.", 'id' => $headerId, 'number' => $grNumber];
            });

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], 500);
        }
    }

    public function apiDestroy($id)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        if (!$canDelete) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus.'], 403);
        }

        $header = DB::table('outlet_serial_receive_headers')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$header) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        try {
            DB::transaction(function () use ($id) {
                $items = DB::table('outlet_serial_receive_items')->where('header_id', $id)->get();

                $serialIds = $items->pluck('serial_id')->toArray();
                if (!empty($serialIds)) {
                    DB::table('inventory_item_serials')
                        ->whereIn('id', $serialIds)
                        ->lockForUpdate()
                        ->get();
                }

                $itemMasterIds = $items->pluck('item_id')->unique()->toArray();
                $itemMasters = DB::table('items')->whereIn('id', $itemMasterIds)->get()->keyBy('id');

                foreach ($items as $item) {
                    $itemMaster = $itemMasters[$item->item_id] ?? null;
                    if (!$itemMaster) continue;

                    $this->rollbackInventory($item, $itemMaster);

                    DB::table('inventory_item_serials')
                        ->where('id', $item->serial_id)
                        ->update([
                            'is_received' => 0,
                            'received_at' => null,
                            'received_by' => null,
                            'received_outlet_gr_id' => null,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('outlet_serial_receive_items')->where('header_id', $id)->delete();

                DB::table('outlet_serial_receive_headers')
                    ->where('id', $id)
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            });

            return response()->json(['success' => true, 'message' => "GR Serial {$header->number} berhasil dihapus."]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Log penolakan dari frontend (mis. duplikat scan sebelum hitung validasi server).
     */
    public function logRejectAttempt(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|max:50',
            'reject_reason' => 'required|string|in:duplicate_scan',
        ]);

        $user = Auth::user();
        $serialNumber = trim($request->serial_number);
        $userOutletId = $user->id_outlet ?? null;
        $scannerOutletName = $this->getOutletName($userOutletId);

        $serial = DB::table('inventory_item_serials as s')
            ->leftJoin('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('delivery_orders as do_tbl', 'do_tbl.id', '=', 's.out_delivery_order_id')
            ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 's.out_outlet_id')
            ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 's.out_warehouse_outlet_id')
            ->where('s.serial_number', $serialNumber)
            ->select(
                's.id',
                's.serial_number',
                's.item_id',
                's.is_out',
                's.is_received',
                's.out_outlet_id',
                's.out_warehouse_outlet_id',
                's.out_delivery_order_id',
                'i.name as item_name',
                'do_tbl.number as do_number',
                'o.nama_outlet as outlet_name',
                'wo.name as warehouse_name'
            )
            ->first();

        $this->logGrRejectAttempt(
            $user,
            $userOutletId,
            $scannerOutletName,
            $serialNumber,
            $serial,
            'duplicate_scan',
            self::GR_REJECT_REASONS['duplicate_scan'] . '.'
        );

        return response()->json(['logged' => true]);
    }

    private function rejectGrScan($user, $userOutletId, $scannerOutletName, string $serialNumber, ?object $serial, string $reason, string $message)
    {
        $this->logGrRejectAttempt($user, $userOutletId, $scannerOutletName, $serialNumber, $serial, $reason, $message);

        return response()->json(['valid' => false, 'message' => $message]);
    }

    private function logGrRejectAttempt($user, $userOutletId, $scannerOutletName, string $serialNumber, ?object $serial, string $reason, string $message): void
    {
        if (! Schema::hasTable('outlet_serial_receive_reject_logs')) {
            return;
        }

        $scannerName = $user->nama_lengkap ?? $user->name ?? null;

        try {
            DB::table('outlet_serial_receive_reject_logs')->insert([
                'serial_number' => $serialNumber,
                'serial_id' => $serial->id ?? null,
                'reject_reason' => $reason,
                'reject_message' => mb_substr($message, 0, 500),
                'scanned_by' => $user->id ?? null,
                'scanner_name' => $scannerName,
                'scanner_outlet_id' => $userOutletId,
                'scanner_outlet_name' => $scannerOutletName !== '-' ? $scannerOutletName : null,
                'serial_target_outlet_id' => $serial->out_outlet_id ?? null,
                'serial_target_outlet_name' => $serial->outlet_name ?? null,
                'delivery_order_id' => $serial->out_delivery_order_id ?? null,
                'delivery_order_number' => $serial->do_number ?? null,
                'warehouse_outlet_id' => $serial->out_warehouse_outlet_id ?? null,
                'warehouse_outlet_name' => $serial->warehouse_name ?? null,
                'item_id' => $serial->item_id ?? null,
                'item_name' => $serial->item_name ?? null,
                'is_out' => isset($serial->is_out) ? (int) $serial->is_out : null,
                'is_received' => isset($serial->is_received) ? (int) $serial->is_received : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('outlet_serial_receive_reject_logs insert failed', [
                'serial_number' => $serialNumber,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getOutletName($outletId): string
    {
        if (!$outletId) return '-';
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
        return $outlet ?: $outletId;
    }

    /**
     * Harga per satuan kecil untuk GR Nomor Seri.
     * - pricing_mode auto: Food GR pusat terakhir + 12% (selaras item_prices auto)
     * - pricing_mode manual: item_prices (outlet > region > all), basis large → kecil
     *
     * @return array{0: float, 1: string, 2: string} [cost_small, cost_source_db, cost_source_label]
     */
    private function resolveSerialReceiveCost(object $serial): array
    {
        $itemId = (int) $serial->item_id;
        $outletId = $serial->out_outlet_id ?? null;
        $itemMaster = DB::table('items')->where('id', $itemId)->first();

        $priceRow = $this->resolveItemPriceRowForOutlet($itemId, $outletId);
        $mode = 'manual';
        if ($priceRow && Schema::hasColumn('item_prices', 'pricing_mode')) {
            $mode = ($priceRow->pricing_mode === 'auto') ? 'auto' : 'manual';
        }

        if ($mode === 'auto') {
            $costSmall = $this->costSmallFromCentralFoodGrMarkup($itemId, $itemMaster);
            if ($costSmall > 0) {
                return [$costSmall, 'auto_fgr_12pct', 'FGR Pusat +12%'];
            }
            if ($serial->source_type === 'good_receive' && (float) ($serial->cost_small ?? 0) > 0) {
                return [
                    round((float) $serial->cost_small * 1.12, 4),
                    'fgr_modal_12pct',
                    'FGR (Modal+12%)',
                ];
            }

            return [0.0, 'auto_fgr_12pct', 'FGR Pusat +12%'];
        }

        if ($priceRow && (float) $priceRow->price > 0) {
            $costSmall = $this->itemPriceLargeToCostSmall((float) $priceRow->price, $itemMaster);

            return [$costSmall, 'item_prices', 'Item Price (manual)'];
        }

        if ((float) ($serial->cost_small ?? 0) > 0) {
            return [(float) $serial->cost_small, 'serial_cost_fallback', 'Harga serial'];
        }

        return [0.0, 'item_prices', 'Item Price (manual)'];
    }

    private function determineCost(object $serial): float
    {
        return $this->resolveSerialReceiveCost($serial)[0];
    }

    private function resolveItemPriceRowForOutlet(int $itemId, ?string $outletId): ?object
    {
        $regionId = null;
        if ($outletId) {
            $regionId = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('region_id');
        }

        return DB::table('item_prices')
            ->where('item_id', $itemId)
            ->where(function ($q) use ($regionId, $outletId) {
                $q->where('availability_price_type', 'all');
                if ($regionId) {
                    $q->orWhere(function ($q2) use ($regionId) {
                        $q2->where('availability_price_type', 'region')->where('region_id', $regionId);
                    });
                }
                if ($outletId) {
                    $q->orWhere(function ($q2) use ($outletId) {
                        $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
                    });
                }
            })
            ->orderByRaw("CASE
                WHEN availability_price_type = 'outlet' THEN 1
                WHEN availability_price_type = 'region' THEN 2
                ELSE 3 END")
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Harga jual large dari GR pusat + 12%, dikonversi ke cost_small.
     */
    private function costSmallFromCentralFoodGrMarkup(int $itemId, ?object $itemMaster): float
    {
        $priceLarge = FoodGrLastPurchaseForItem::suggestedSellingPrice($itemId);
        if ($priceLarge === null || $priceLarge <= 0) {
            return 0.0;
        }

        return $this->itemPriceLargeToCostSmall($priceLarge, $itemMaster);
    }

    /**
     * item_prices menyimpan harga per satuan large.
     */
    private function itemPriceLargeToCostSmall(float $priceLarge, ?object $itemMaster): float
    {
        if ($priceLarge <= 0) {
            return 0.0;
        }

        $smallConv = (float) ($itemMaster->small_conversion_qty ?? 1) ?: 1;
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?? 1) ?: 1;
        $divisor = ($smallConv > 0 && $mediumConv > 0) ? ($smallConv * $mediumConv) : 1;

        return round($priceLarge / $divisor, 4);
    }

    private function processInventory($serial, $itemMaster, $costSmall, $effectiveQty, $outletId, $warehouseOutletId, $headerId)
    {
        $unitId = $serial->unit_id;
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

        $nilaiBaru = $qtySmall * $costSmall;
        $qtyLama = $stock ? (float) $stock->qty_small : 0;
        $nilaiLama = $stock ? (float) $stock->value : 0;
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

        $saldoSmall = ($lastCard ? (float) $lastCard->saldo_qty_small : 0) + $qtySmall;
        $saldoMedium = ($lastCard ? (float) $lastCard->saldo_qty_medium : 0) + $qtyMedium;
        $saldoLarge = ($lastCard ? (float) $lastCard->saldo_qty_large : 0) + $qtyLarge;

        DB::table('outlet_food_inventory_cards')->insert([
            'inventory_item_id' => $inventoryItemId,
            'id_outlet' => $outletId,
            'warehouse_outlet_id' => $warehouseOutletId,
            'date' => now()->toDateString(),
            'reference_type' => 'serial_receive',
            'reference_id' => $headerId,
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
            'saldo_qty_small' => $saldoSmall,
            'saldo_qty_medium' => $saldoMedium,
            'saldo_qty_large' => $saldoLarge,
            'saldo_value' => $saldoSmall * $mac,
            'description' => "Serial Receive: {$serial->serial_number}",
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
            'reference_id' => $headerId,
            'created_at' => now(),
        ]);
    }

    private function rollbackInventory($item, $itemMaster)
    {
        $unitId = $item->unit_id;
        $effectiveQty = (float) $item->qty;
        $costSmall = (float) $item->cost_small;
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

        $nilaiBaru = $qtySmall * $costSmall;
        $inventoryItem = DB::table('outlet_food_inventory_items')
            ->where('item_id', $item->item_id)
            ->first();

        if (!$inventoryItem) return;

        $stock = DB::table('outlet_food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('id_outlet', $item->outlet_id)
            ->where('warehouse_outlet_id', $item->warehouse_outlet_id)
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
            ->where('id_outlet', $item->outlet_id)
            ->where('warehouse_outlet_id', $item->warehouse_outlet_id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        DB::table('outlet_food_inventory_cards')->insert([
            'inventory_item_id' => $inventoryItem->id,
            'id_outlet' => $item->outlet_id,
            'warehouse_outlet_id' => $item->warehouse_outlet_id,
            'date' => now()->toDateString(),
            'reference_type' => 'serial_receive_rollback',
            'reference_id' => $item->header_id,
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
            'saldo_qty_small' => ($lastCard ? (float) $lastCard->saldo_qty_small : 0) - $qtySmall,
            'saldo_qty_medium' => ($lastCard ? (float) $lastCard->saldo_qty_medium : 0) - $qtyMedium,
            'saldo_qty_large' => ($lastCard ? (float) $lastCard->saldo_qty_large : 0) - $qtyLarge,
            'saldo_value' => max(0, (($lastCard ? (float) $lastCard->saldo_qty_small : 0) - $qtySmall) * ($stock ? (float) $stock->last_cost_small : $costSmall)),
            'description' => "Rollback Serial Receive: {$item->serial_number}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function serialRepackLabel(object $serial, float $effectiveQty): string
    {
        if ($effectiveQty <= 1 && empty($serial->repack_unit_id)) {
            return '';
        }

        $fmtQty = rtrim(rtrim(number_format($effectiveQty, 4, '.', ''), '0'), '.');
        $pkgUnit = $serial->repack_unit_name ?? $serial->unit_name ?? '';
        $baseUnit = $serial->unit_name ?? '';

        if ($pkgUnit && $baseUnit && strcasecmp($pkgUnit, $baseUnit) !== 0) {
            return "1 {$pkgUnit} = {$fmtQty} {$baseUnit}";
        }

        return "{$fmtQty} {$baseUnit}";
    }
}
