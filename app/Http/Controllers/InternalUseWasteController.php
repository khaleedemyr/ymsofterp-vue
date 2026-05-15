<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InternalUseWasteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = DB::table('internal_use_wastes')
            ->leftJoin('warehouses', 'internal_use_wastes.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('items', 'internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'internal_use_wastes.unit_id', '=', 'units.id')
            ->leftJoin('tbl_data_ruko', 'internal_use_wastes.ruko_id', '=', 'tbl_data_ruko.id_ruko')
            ->leftJoin('internal_use_waste_headers as iuw_h', 'internal_use_wastes.header_id', '=', 'iuw_h.id')
            ->select(
                'internal_use_wastes.*',
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                'units.name as unit_name',
                'tbl_data_ruko.nama_ruko',
                'iuw_h.notes as header_notes',
                'iuw_h.document_mode'
            );

        if ($request->filled('type')) {
            $query->where('internal_use_wastes.type', $request->type);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('internal_use_wastes.warehouse_id', (int) $request->warehouse_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('internal_use_wastes.date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('internal_use_wastes.date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $s = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->search).'%';
            $query->where('items.name', 'like', $s);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 5 && $perPage <= 100 ? $perPage : 15;

        $paginator = $query
            ->orderByDesc('internal_use_wastes.date')
            ->orderByDesc('internal_use_wastes.id')
            ->paginate($perPage)
            ->withQueryString();

        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        $warehouses = DB::table('warehouses')->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return inertia('InternalUseWaste/Index', [
            'rows' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => $request->only(['type', 'warehouse_id', 'date_from', 'date_to', 'search', 'per_page']),
            'warehouses' => $warehouses,
            'canDelete' => $canDelete,
        ]);
    }

    public function create()
    {
        $warehouses = DB::table('warehouses')->where('status', 'active')->get();
        $items = DB::table('items')->where('status', 'active')->get();
        $rukos = DB::table('tbl_data_ruko')->get();

        return inertia('InternalUseWaste/Create', [
            'warehouses' => $warehouses,
            'items' => $items,
            'rukos' => $rukos,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $headerId = $this->resolveHeaderIdFromAnyId($id);
        if (! $headerId) {
            abort(404, 'Data tidak ditemukan');
        }

        $header = DB::table('internal_use_waste_headers')->where('id', $headerId)->first();
        if (! $header) {
            abort(404, 'Data tidak ditemukan');
        }

        if (in_array($header->document_mode ?? 'normal', ['serial', 'mixed'], true)) {
            return redirect()
                ->route('internal-use-waste.show', $headerId)
                ->with('error', 'Dokumen mode serial/campuran tidak dapat diedit. Hapus lalu buat baru.');
        }

        $lines = DB::table('internal_use_wastes')
            ->leftJoin('items', 'internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'internal_use_wastes.unit_id', '=', 'units.id')
            ->where('internal_use_wastes.header_id', $headerId)
            ->orderBy('internal_use_wastes.id')
            ->select(
                'internal_use_wastes.id',
                'internal_use_wastes.item_id',
                'internal_use_wastes.qty',
                'internal_use_wastes.unit_id',
                'internal_use_wastes.notes as line_notes',
                'items.name as item_name',
                'units.name as unit_name'
            )
            ->get();

        $warehouses = DB::table('warehouses')->where('status', 'active')->get();
        $items = DB::table('items')->where('status', 'active')->get();
        $rukos = DB::table('tbl_data_ruko')->get();

        return inertia('InternalUseWaste/Edit', [
            'headerId' => (int) $headerId,
            'header' => $header,
            'lines' => $lines,
            'warehouses' => $warehouses,
            'items' => $items,
            'rukos' => $rukos,
        ]);
    }

    public function store(Request $request)
    {
        $this->mergeLegacySingleItemPayload($request);
        $this->validateDocumentRequest($request, false);

        DB::beginTransaction();
        try {
            $headerId = $this->insertHeaderFromRequest($request);

            if (! empty($request->serial_items)) {
                $this->processSerialItemsForIUW($request, $headerId, $request->serial_items);
            }

            $itemsPayload = $this->normalizeItemsPayload($request);
            foreach ($itemsPayload as $row) {
                $this->insertLineAndApplyStock($request, $headerId, $row);
            }

            DB::commit();
            $this->logActivity('create', 'Membuat internal use/waste header: '.$headerId, null, ['header_id' => $headerId], $request);

            return redirect()->route('internal-use-waste.index')->with('success', 'Data berhasil disimpan');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan data: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $headerId = $this->resolveHeaderIdFromAnyId($id);
        if (! $headerId) {
            abort(404, 'Data tidak ditemukan');
        }

        $existingHeader = DB::table('internal_use_waste_headers')->where('id', $headerId)->first();
        if ($existingHeader && in_array($existingHeader->document_mode ?? 'normal', ['serial', 'mixed'], true)) {
            return redirect()
                ->route('internal-use-waste.show', $headerId)
                ->with('error', 'Dokumen mode serial/campuran tidak dapat diedit. Hapus lalu buat baru.');
        }

        $this->mergeLegacySingleItemPayload($request);
        $this->validateDocumentRequest($request, true);

        DB::beginTransaction();
        try {
            $existingLines = DB::table('internal_use_wastes')->where('header_id', $headerId)->orderBy('id')->get();
            foreach ($existingLines as $line) {
                $this->rollbackOneLine($line);
            }
            DB::table('internal_use_wastes')->where('header_id', $headerId)->delete();

            DB::table('internal_use_waste_headers')->where('id', $headerId)->update([
                'type' => $request->type,
                'date' => $request->date,
                'warehouse_id' => (int) $request->warehouse_id,
                'ruko_id' => $request->type === 'internal_use' ? $request->ruko_id : null,
                'notes' => $request->notes,
                'updated_at' => now(),
            ]);

            $itemsPayload = $this->normalizeItemsPayload($request);
            foreach ($itemsPayload as $row) {
                $this->insertLineAndApplyStock($request, $headerId, $row);
            }

            DB::commit();
            $this->logActivity('update', 'Mengubah internal use/waste header: '.$headerId, null, ['header_id' => $headerId], $request);

            return redirect()->route('internal-use-waste.index')->with('success', 'Data berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui data: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $headerId = $this->resolveHeaderIdFromAnyId($id);
        if (! $headerId) {
            abort(404, 'Data tidak ditemukan');
        }

        $header = DB::table('internal_use_waste_headers as h')
            ->leftJoin('warehouses', 'h.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('tbl_data_ruko', 'h.ruko_id', '=', 'tbl_data_ruko.id_ruko')
            ->where('h.id', $headerId)
            ->select(
                'h.*',
                'warehouses.name as warehouse_name',
                'tbl_data_ruko.nama_ruko'
            )
            ->first();

        if (! $header) {
            abort(404, 'Data tidak ditemukan');
        }

        $lines = DB::table('internal_use_wastes')
            ->leftJoin('items', 'internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'internal_use_wastes.unit_id', '=', 'units.id')
            ->where('internal_use_wastes.header_id', $headerId)
            ->orderBy('internal_use_wastes.id')
            ->select(
                'internal_use_wastes.id',
                'internal_use_wastes.qty',
                'internal_use_wastes.notes as line_notes',
                'items.name as item_name',
                'units.name as unit_name'
            )
            ->get();

        $serialItems = DB::table('internal_use_waste_serial_items as iuws')
            ->leftJoin('items as i', 'iuws.item_id', '=', 'i.id')
            ->where('iuws.internal_use_waste_header_id', $headerId)
            ->orderBy('iuws.id')
            ->select('iuws.*', 'i.name as item_name')
            ->get();

        return inertia('InternalUseWaste/Show', [
            'headerId' => (int) $headerId,
            'header' => $header,
            'lines' => $lines,
            'serialItems' => $serialItems,
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        if (! $canDelete) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus data ini'], 403);
        }

        $headerId = $this->resolveHeaderIdFromAnyId($id);
        if (! $headerId) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $header = DB::table('internal_use_waste_headers')->where('id', $headerId)->first();
            if (! $header) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
            }

            $documentMode = $header->document_mode ?? 'normal';

            if (in_array($documentMode, ['serial', 'mixed'], true)) {
                $this->rollbackSerialItemsForIUW($header);
            }

            $lines = DB::table('internal_use_wastes')->where('header_id', $headerId)->orderBy('id')->get();
            if ($documentMode !== 'serial') {
                foreach ($lines as $line) {
                    $this->rollbackOneLine($line);
                }
            }

            DB::table('internal_use_waste_serial_items')->where('internal_use_waste_header_id', $headerId)->delete();
            DB::table('internal_use_wastes')->where('header_id', $headerId)->delete();
            DB::table('internal_use_waste_headers')->where('id', $headerId)->delete();

            $this->logActivity('delete', 'Menghapus internal use/waste header: '.$headerId, json_encode(['header_id' => $headerId]), null, request());
            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getItemUnits($itemId)
    {
        $item = DB::table('items')->where('id', $itemId)->first();
        if (! $item) {
            return response()->json(['units' => []]);
        }

        $units = [];
        if ($item->small_unit_id) {
            $units[] = [
                'id' => $item->small_unit_id,
                'name' => DB::table('units')->where('id', $item->small_unit_id)->value('name'),
            ];
        }
        if ($item->medium_unit_id) {
            $units[] = [
                'id' => $item->medium_unit_id,
                'name' => DB::table('units')->where('id', $item->medium_unit_id)->value('name'),
            ];
        }
        if ($item->large_unit_id) {
            $units[] = [
                'id' => $item->large_unit_id,
                'name' => DB::table('units')->where('id', $item->large_unit_id)->value('name'),
            ];
        }

        return response()->json(['units' => $units]);
    }

    public function report(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $ruko_id = $request->input('ruko_id');

        $query = DB::table('internal_use_wastes')
            ->leftJoin('warehouses', 'internal_use_wastes.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('items', 'internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'internal_use_wastes.unit_id', '=', 'units.id')
            ->leftJoin('tbl_data_ruko', 'internal_use_wastes.ruko_id', '=', 'tbl_data_ruko.id_ruko')
            ->leftJoin('internal_use_waste_headers as iuw_h', 'internal_use_wastes.header_id', '=', 'iuw_h.id')
            ->select(
                'internal_use_wastes.*',
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                'units.name as unit_name',
                'tbl_data_ruko.nama_ruko',
                'iuw_h.notes as header_notes'
            )
            ->where('internal_use_wastes.type', 'internal_use');

        if ($from) {
            $query->where('internal_use_wastes.date', '>=', $from);
        }
        if ($to) {
            $query->where('internal_use_wastes.date', '<=', $to);
        }
        if ($ruko_id) {
            $query->where('internal_use_wastes.ruko_id', $ruko_id);
        }
        $data = $query->orderByDesc('internal_use_wastes.date')->orderByDesc('internal_use_wastes.id')->get();

        $rukos = DB::table('tbl_data_ruko')->get();

        return inertia('InternalUseWaste/Report', [
            'data' => $data,
            'rukos' => $rukos,
            'filters' => $request->only(['from', 'to', 'ruko_id']),
        ]);
    }

    public function reportWasteSpoil(Request $request)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $warehouse_id = $request->input('warehouse_id');

        $query = DB::table('internal_use_wastes')
            ->leftJoin('warehouses', 'internal_use_wastes.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('items', 'internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'internal_use_wastes.unit_id', '=', 'units.id')
            ->leftJoin('internal_use_waste_headers as iuw_h', 'internal_use_wastes.header_id', '=', 'iuw_h.id')
            ->select(
                'internal_use_wastes.*',
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                'units.name as unit_name',
                'iuw_h.notes as header_notes'
            )
            ->whereIn('internal_use_wastes.type', ['spoil', 'waste']);

        if ($from) {
            $query->where('internal_use_wastes.date', '>=', $from);
        }
        if ($to) {
            $query->where('internal_use_wastes.date', '<=', $to);
        }
        if ($warehouse_id) {
            $query->where('internal_use_wastes.warehouse_id', $warehouse_id);
        }
        $data = $query->orderByDesc('internal_use_wastes.date')->orderByDesc('internal_use_wastes.id')->get();

        $warehouses = DB::table('warehouses')->select('id', 'name')->orderBy('name')->get();

        return inertia('InternalUseWaste/ReportWasteSpoil', [
            'data' => $data,
            'warehouses' => $warehouses,
            'filters' => $request->only(['from', 'to', 'warehouse_id']),
        ]);
    }

    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        $query = DB::table('internal_use_wastes')
            ->leftJoin('warehouses', 'internal_use_wastes.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('items', 'internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'internal_use_wastes.unit_id', '=', 'units.id')
            ->leftJoin('tbl_data_ruko', 'internal_use_wastes.ruko_id', '=', 'tbl_data_ruko.id_ruko')
            ->leftJoin('users as creator_user', 'internal_use_wastes.created_by', '=', 'creator_user.id')
            ->leftJoin('internal_use_waste_headers as iuw_h', 'internal_use_wastes.header_id', '=', 'iuw_h.id')
            ->select(
                'internal_use_wastes.id',
                'internal_use_wastes.header_id',
                'internal_use_wastes.type',
                'internal_use_wastes.date',
                'internal_use_wastes.warehouse_id',
                'internal_use_wastes.ruko_id',
                'internal_use_wastes.item_id',
                'internal_use_wastes.qty',
                'internal_use_wastes.unit_id',
                'internal_use_wastes.notes',
                'internal_use_wastes.created_at',
                'warehouses.name as warehouse_name',
                'items.name as item_name',
                'units.name as unit_name',
                'tbl_data_ruko.nama_ruko',
                'creator_user.nama_lengkap as creator_name',
                'creator_user.avatar as creator_avatar',
                'iuw_h.notes as header_notes',
                'iuw_h.document_mode'
            )
            ->orderByDesc('internal_use_wastes.date')
            ->orderByDesc('internal_use_wastes.id');

        if ($request->filled('type')) {
            $query->where('internal_use_wastes.type', $request->type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('internal_use_wastes.date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('internal_use_wastes.date', '<=', $request->date_to);
        }
        if ($request->filled('warehouse_id')) {
            $query->where('internal_use_wastes.warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('search')) {
            $s = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->search).'%';
            $query->where('items.name', 'like', $s);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'can_delete' => $canDelete,
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
        ]);
    }

    public function apiCreateData()
    {
        $warehouses = DB::table('warehouses')->where('status', 'active')->select('id', 'name')->orderBy('name')->get();
        $items = DB::table('items')->where('status', 'active')->select('id', 'name')->orderBy('name')->get();
        $units = DB::table('units')->select('id', 'name')->orderBy('name')->get();
        $rukos = DB::table('tbl_data_ruko')->select('id_ruko as id', 'nama_ruko as name')->orderBy('nama_ruko')->get();

        return response()->json([
            'success' => true,
            'warehouses' => $warehouses,
            'items' => $items,
            'units' => $units,
            'rukos' => $rukos,
        ]);
    }

    public function apiShow($id)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        $headerId = $this->resolveHeaderIdFromAnyId($id);
        if (! $headerId) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $header = DB::table('internal_use_waste_headers as h')
            ->leftJoin('warehouses', 'h.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('tbl_data_ruko', 'h.ruko_id', '=', 'tbl_data_ruko.id_ruko')
            ->leftJoin('users as creator_user', 'h.created_by', '=', 'creator_user.id')
            ->where('h.id', $headerId)
            ->select(
                'h.*',
                'warehouses.name as warehouse_name',
                'tbl_data_ruko.nama_ruko',
                'creator_user.nama_lengkap as creator_name',
                'creator_user.avatar as creator_avatar'
            )
            ->first();

        if (! $header) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $lines = DB::table('internal_use_wastes')
            ->leftJoin('items', 'internal_use_wastes.item_id', '=', 'items.id')
            ->leftJoin('units', 'internal_use_wastes.unit_id', '=', 'units.id')
            ->where('internal_use_wastes.header_id', $headerId)
            ->orderBy('internal_use_wastes.id')
            ->select(
                'internal_use_wastes.id',
                'internal_use_wastes.item_id',
                'internal_use_wastes.qty',
                'internal_use_wastes.unit_id',
                'internal_use_wastes.notes',
                'items.name as item_name',
                'units.name as unit_name'
            )
            ->get();

        $serialItems = DB::table('internal_use_waste_serial_items as iuws')
            ->leftJoin('items as i', 'iuws.item_id', '=', 'i.id')
            ->where('iuws.internal_use_waste_header_id', $headerId)
            ->orderBy('iuws.id')
            ->select('iuws.*', 'i.name as item_name')
            ->get();

        return response()->json([
            'success' => true,
            'header' => $header,
            'lines' => $lines,
            'serial_items' => $serialItems,
            'can_delete' => $canDelete,
        ]);
    }

    public function validateSerialForIUW(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'warehouse_id' => 'required|integer',
        ]);

        $serialNumber = trim($request->serial_number);
        $warehouseId = (int) $request->warehouse_id;

        $serial = DB::table('inventory_item_serials as s')
            ->join('items as i', 's.item_id', '=', 'i.id')
            ->leftJoin('units as u', 's.unit_id', '=', 'u.id')
            ->leftJoin('units as ru', 'ru.id', '=', 's.repack_unit_id')
            ->where('s.serial_number', $serialNumber)
            ->select(
                's.id',
                's.serial_number',
                's.item_id',
                's.warehouse_id',
                's.is_out',
                's.is_transferred',
                's.repack_unit_id',
                's.repack_qty',
                's.unit_id',
                'i.name as item_name',
                'i.sku',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'u.name as unit_name',
                'ru.name as repack_unit_name'
            )
            ->first();

        if (! $serial) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak ditemukan.']);
        }

        if ($serial->is_out) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri sudah keluar gudang (sudah dipakai DO / penjualan / internal use).']);
        }

        if ($serial->is_transferred) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri sudah pernah di-transfer.']);
        }

        if ((int) $serial->warehouse_id !== $warehouseId) {
            $whName = DB::table('warehouses')->where('id', $serial->warehouse_id)->value('name') ?? $serial->warehouse_id;

            return response()->json([
                'valid' => false,
                'message' => "Serial berada di gudang {$whName}, bukan gudang yang dipilih.",
            ]);
        }

        $qty = 1.0;
        $unitId = $serial->unit_id;
        $unitName = $serial->unit_name ?? '';
        if ($serial->repack_qty && $serial->repack_unit_id) {
            $qty = (float) $serial->repack_qty;
            $unitId = $serial->repack_unit_id;
            $unitName = $serial->repack_unit_name ?? $unitName;
        }

        $smallConv = $serial->small_conversion_qty ?: 1;
        $mediumConv = $serial->medium_conversion_qty ?: 1;
        $qty_small = $qty;
        if ($unitId == $serial->medium_unit_id) {
            $qty_small = $qty * $smallConv;
        } elseif ($unitId == $serial->large_unit_id) {
            $qty_small = $qty * $smallConv * $mediumConv;
        }

        return response()->json([
            'valid' => true,
            'serial' => [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'item_id' => $serial->item_id,
                'item_name' => $serial->item_name,
                'sku' => $serial->sku,
                'qty' => $qty,
                'qty_small' => $qty_small,
                'unit_id' => $unitId,
                'unit_name' => $unitName,
            ],
        ]);
    }

    public function apiStore(Request $request)
    {
        $this->mergeLegacySingleItemPayload($request);
        $this->validateDocumentRequest($request, false);

        DB::beginTransaction();
        try {
            $headerId = $this->insertHeaderFromRequest($request);

            if (! empty($request->serial_items)) {
                $this->processSerialItemsForIUW($request, $headerId, $request->serial_items);
            }

            $itemsPayload = $this->normalizeItemsPayload($request);
            foreach ($itemsPayload as $row) {
                $this->insertLineAndApplyStock($request, $headerId, $row);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan',
                'id' => $headerId,
                'header_id' => $headerId,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function apiDestroy($id)
    {
        return $this->destroy($id);
    }

    public function apiStock(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $itemId = $request->input('item_id');
        if (! $warehouseId || ! $itemId) {
            return response()->json(['success' => false, 'message' => 'warehouse_id dan item_id wajib'], 400);
        }

        $inv = DB::table('food_inventory_items')->where('item_id', $itemId)->first();
        if (! $inv) {
            return response()->json([
                'success' => true,
                'qty_small' => 0,
                'qty_medium' => 0,
                'qty_large' => 0,
                'small_unit_name' => null,
                'medium_unit_name' => null,
                'large_unit_name' => null,
            ]);
        }

        $stock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inv->id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $item = DB::table('items')->where('id', $itemId)->first();
        $smallUnitName = $item && $item->small_unit_id ? DB::table('units')->where('id', $item->small_unit_id)->value('name') : null;
        $mediumUnitName = $item && $item->medium_unit_id ? DB::table('units')->where('id', $item->medium_unit_id)->value('name') : null;
        $largeUnitName = $item && $item->large_unit_id ? DB::table('units')->where('id', $item->large_unit_id)->value('name') : null;

        return response()->json([
            'success' => true,
            'qty_small' => $stock ? (float) $stock->qty_small : 0,
            'qty_medium' => $stock ? (float) $stock->qty_medium : 0,
            'qty_large' => $stock ? (float) $stock->qty_large : 0,
            'small_unit_name' => $smallUnitName,
            'medium_unit_name' => $mediumUnitName,
            'large_unit_name' => $largeUnitName,
        ]);
    }

    private function mergeLegacySingleItemPayload(Request $request): void
    {
        if ($request->has('items') && is_array($request->items) && count($request->items) > 0) {
            return;
        }
        if ($request->filled('item_id')) {
            $request->merge([
                'items' => [[
                    'item_id' => (int) $request->item_id,
                    'qty' => (float) $request->qty,
                    'unit_id' => (int) $request->unit_id,
                    'notes' => null,
                ]],
            ]);
        }
    }

    private function resolveHeaderIdFromAnyId($id): ?int
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        if (DB::table('internal_use_waste_headers')->where('id', $id)->exists()) {
            return $id;
        }
        $line = DB::table('internal_use_wastes')->where('id', $id)->first();
        if ($line && ! empty($line->header_id)) {
            return (int) $line->header_id;
        }

        return null;
    }

    private function validateDocumentRequest(Request $request, bool $isUpdate): void
    {
        $hasItems = is_array($request->items) && count($request->items) > 0;
        $hasSerials = is_array($request->serial_items) && count($request->serial_items) > 0;

        if (! $hasItems && ! $hasSerials) {
            throw ValidationException::withMessages([
                'items' => ['Minimal harus ada 1 baris item (qty) atau 1 nomor seri.'],
            ]);
        }

        $rules = [
            'type' => 'required|in:internal_use,spoil,waste',
            'date' => 'required|date',
            'warehouse_id' => 'required|integer',
            'ruko_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ];

        if ($hasItems) {
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.item_id'] = 'required|integer';
            $rules['items.*.qty'] = 'required|numeric|min:0.01';
            $rules['items.*.unit_id'] = 'required|integer';
            $rules['items.*.notes'] = 'nullable|string';
        }

        if ($hasSerials) {
            $rules['serial_items'] = 'required|array|min:1';
            $rules['serial_items.*.serial_id'] = 'required|integer';
            $rules['serial_items.*.serial_number'] = 'required|string';
            $rules['serial_items.*.item_id'] = 'required|integer';
            $rules['serial_items.*.qty'] = 'required|numeric|min:0.01';
            $rules['serial_items.*.qty_small'] = 'required|numeric|min:0';
            $rules['serial_items.*.unit_id'] = 'nullable|integer';
        }

        $request->validate($rules);

        if ($request->type === 'internal_use' && empty($request->ruko_id)) {
            throw ValidationException::withMessages([
                'ruko_id' => ['Ruko wajib untuk tipe Internal Use.'],
            ]);
        }
    }

    private function resolveDocumentMode(Request $request): string
    {
        $hasItems = is_array($request->items) && count($request->items) > 0;
        $hasSerials = is_array($request->serial_items) && count($request->serial_items) > 0;

        if ($hasItems && $hasSerials) {
            return 'mixed';
        }
        if ($hasSerials) {
            return 'serial';
        }

        return 'normal';
    }

    /**
     * @return array<int, array{item_id:int, qty:float, unit_id:int, notes:?string}>
     */
    private function normalizeItemsPayload(Request $request): array
    {
        $items = $request->input('items', []);
        $out = [];
        foreach ($items as $row) {
            $out[] = [
                'item_id' => (int) $row['item_id'],
                'qty' => (float) $row['qty'],
                'unit_id' => (int) $row['unit_id'],
                'notes' => isset($row['notes']) ? (string) $row['notes'] : null,
            ];
        }

        return $out;
    }

    private function insertHeaderFromRequest(Request $request): int
    {
        return (int) DB::table('internal_use_waste_headers')->insertGetId([
            'type' => $request->type,
            'date' => $request->date,
            'warehouse_id' => (int) $request->warehouse_id,
            'ruko_id' => $request->type === 'internal_use' ? $request->ruko_id : null,
            'notes' => $request->notes,
            'document_mode' => $this->resolveDocumentMode($request),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertLineAndApplyStock(Request $request, int $headerId, array $row): void
    {
        $itemId = $row['item_id'];
        $qty = $row['qty'];
        $unitId = $row['unit_id'];
        $lineNotes = $row['notes'];

        $inventoryItem = DB::table('food_inventory_items')->where('item_id', $itemId)->first();
        if (! $inventoryItem) {
            throw new \Exception('Inventory item not found for item_id: '.$itemId);
        }
        $inventory_item_id = $inventoryItem->id;

        $itemMaster = DB::table('items')->where('id', $itemId)->first();
        if (! $itemMaster) {
            throw new \Exception('Item tidak ditemukan');
        }

        [$qty_small, $qty_medium, $qty_large, $unitSmall] = $this->computeQtyTiers($itemMaster, $unitId, $qty);

        $lineId = (int) DB::table('internal_use_wastes')->insertGetId([
            'header_id' => $headerId,
            'type' => $request->type,
            'date' => $request->date,
            'warehouse_id' => (int) $request->warehouse_id,
            'ruko_id' => $request->type === 'internal_use' ? $request->ruko_id : null,
            'item_id' => $itemId,
            'qty' => $qty,
            'unit_id' => $unitId,
            'notes' => $lineNotes,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventory_item_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->first();
        if (! $stock) {
            throw new \Exception('Stok tidak ditemukan di gudang');
        }

        if ($qty_small > $stock->qty_small) {
            throw new \Exception("Qty melebihi stok yang tersedia (item {$itemMaster->name}). Stok tersedia: {$stock->qty_small} {$unitSmall}");
        }

        DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventory_item_id)
            ->where('warehouse_id', $request->warehouse_id)
            ->update([
                'qty_small' => $stock->qty_small - $qty_small,
                'qty_medium' => $stock->qty_medium - $qty_medium,
                'qty_large' => $stock->qty_large - $qty_large,
                'updated_at' => now(),
            ]);

        DB::table('food_inventory_cards')->insert([
            'inventory_item_id' => $inventory_item_id,
            'warehouse_id' => $request->warehouse_id,
            'date' => $request->date,
            'reference_type' => 'internal_use_waste',
            'reference_id' => $lineId,
            'out_qty_small' => $qty_small,
            'out_qty_medium' => $qty_medium,
            'out_qty_large' => $qty_large,
            'cost_per_small' => $stock->last_cost_small,
            'cost_per_medium' => $stock->last_cost_medium,
            'cost_per_large' => $stock->last_cost_large,
            'value_out' => $qty_small * $stock->last_cost_small,
            'saldo_qty_small' => $stock->qty_small - $qty_small,
            'saldo_qty_medium' => $stock->qty_medium - $qty_medium,
            'saldo_qty_large' => $stock->qty_large - $qty_large,
            'saldo_value' => ($stock->qty_small - $qty_small) * $stock->last_cost_small,
            'description' => 'Stock Out - '.$request->type,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{0:float,1:float,2:float,3:?string}
     */
    private function computeQtyTiers(object $itemMaster, int $unitId, float $qty_input): array
    {
        $unit = DB::table('units')->where('id', $unitId)->value('name');
        $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
        $unitMedium = $itemMaster->medium_unit_id ? DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name') : null;
        $unitLarge = $itemMaster->large_unit_id ? DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name') : null;
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

        $qty_small = 0.0;
        $qty_medium = 0.0;
        $qty_large = 0.0;

        if ($unit === $unitSmall) {
            $qty_small = $qty_input;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
        } elseif ($unitMedium && $unit === $unitMedium) {
            $qty_medium = $qty_input;
            $qty_small = $qty_medium * $smallConv;
            $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
        } elseif ($unitLarge && $unit === $unitLarge) {
            $qty_large = $qty_input;
            $qty_medium = $qty_large * $mediumConv;
            $qty_small = $qty_medium * $smallConv;
        } else {
            $qty_small = $qty_input;
        }

        return [$qty_small, $qty_medium, $qty_large, $unitSmall];
    }

    private function rollbackOneLine(object $line): void
    {
        $inventoryItem = DB::table('food_inventory_items')->where('item_id', $line->item_id)->first();
        if (! $inventoryItem) {
            throw new \Exception('Inventory item not found for item_id: '.$line->item_id);
        }
        $inventory_item_id = $inventoryItem->id;

        $itemMaster = DB::table('items')->where('id', $line->item_id)->first();
        $unit = DB::table('units')->where('id', $line->unit_id)->value('name');
        $qty_input = $line->qty;
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

        $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
        $unitMedium = $itemMaster->medium_unit_id ? DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name') : null;
        $unitLarge = $itemMaster->large_unit_id ? DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name') : null;

        $qty_small = 0.0;
        $qty_medium = 0.0;
        $qty_large = 0.0;

        if ($unit === $unitSmall) {
            $qty_small = $qty_input;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
        } elseif ($unitMedium && $unit === $unitMedium) {
            $qty_medium = $qty_input;
            $qty_small = $qty_medium * $smallConv;
            $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
        } elseif ($unitLarge && $unit === $unitLarge) {
            $qty_large = $qty_input;
            $qty_medium = $qty_large * $mediumConv;
            $qty_small = $qty_medium * $smallConv;
        } else {
            $qty_small = $qty_input;
        }

        $stock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventory_item_id)
            ->where('warehouse_id', $line->warehouse_id)
            ->first();
        if ($stock) {
            DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $line->warehouse_id)
                ->update([
                    'qty_small' => $stock->qty_small + $qty_small,
                    'qty_medium' => $stock->qty_medium + $qty_medium,
                    'qty_large' => $stock->qty_large + $qty_large,
                    'updated_at' => now(),
                ]);
        }

        DB::table('food_inventory_cards')
            ->where('reference_type', 'internal_use_waste')
            ->where('reference_id', $line->id)
            ->delete();
    }

    private function logActivity(string $type, string $description, $oldData, $newData, Request $request): void
    {
        DB::table('activity_logs')->insert([
            'user_id' => Auth::id(),
            'activity_type' => $type,
            'module' => 'internal_use_waste',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData !== null ? (is_string($oldData) ? $oldData : json_encode($oldData)) : null,
            'new_data' => $newData !== null ? (is_string($newData) ? $newData : json_encode($newData)) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function processSerialItemsForIUW(Request $request, int $headerId, array $serialItems): void
    {
        $now = now();
        $userId = Auth::id();
        $warehouseId = (int) $request->warehouse_id;
        $typeLabel = $request->type;

        foreach ($serialItems as $si) {
            $serialRow = DB::table('inventory_item_serials')
                ->where('id', $si['serial_id'])
                ->lockForUpdate()
                ->first();

            if (! $serialRow) {
                throw new \Exception('Serial tidak ditemukan: ' . ($si['serial_number'] ?? ''));
            }
            if ($serialRow->is_out) {
                throw new \Exception('Serial sudah keluar: ' . $si['serial_number']);
            }
            if ((int) $serialRow->warehouse_id !== $warehouseId) {
                throw new \Exception('Serial tidak di gudang yang dipilih: ' . $si['serial_number']);
            }

            $unitId = (int) ($si['unit_id'] ?? 0);
            $unitName = $si['unit_name'] ?? DB::table('units')->where('id', $unitId)->value('name') ?? '';
            $qty = (float) $si['qty'];
            $qty_small = (float) $si['qty_small'];

            $itemMaster = DB::table('items')->where('id', $si['item_id'])->first();
            if (! $itemMaster) {
                throw new \Exception('Item tidak ditemukan untuk serial ' . $si['serial_number']);
            }

            if ($unitId > 0) {
                [$qty_small, $qty_medium, $qty_large] = $this->computeQtyTiers($itemMaster, $unitId, $qty);
            } else {
                $qty_medium = 0;
                $qty_large = 0;
            }

            DB::table('internal_use_waste_serial_items')->insert([
                'internal_use_waste_header_id' => $headerId,
                'serial_id' => $si['serial_id'],
                'serial_number' => $si['serial_number'],
                'item_id' => $si['item_id'],
                'unit_id' => $unitId ?: null,
                'unit_name' => $unitName,
                'qty' => $qty,
                'qty_small' => $qty_small,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $inventoryItem = DB::table('food_inventory_items')->where('item_id', $si['item_id'])->first();
            if (! $inventoryItem) {
                throw new \Exception('Inventory item not found for item_id: ' . $si['item_id']);
            }
            $inventory_item_id = $inventoryItem->id;

            $stock = DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $warehouseId)
                ->first();
            if (! $stock) {
                throw new \Exception('Stok tidak ditemukan di gudang untuk serial ' . $si['serial_number']);
            }
            if ($qty_small > $stock->qty_small) {
                $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
                throw new \Exception("Qty serial melebihi stok ({$itemMaster->name}). Stok: {$stock->qty_small} {$unitSmall}");
            }

            DB::table('food_inventory_stocks')
                ->where('inventory_item_id', $inventory_item_id)
                ->where('warehouse_id', $warehouseId)
                ->update([
                    'qty_small' => $stock->qty_small - $qty_small,
                    'qty_medium' => $stock->qty_medium - $qty_medium,
                    'qty_large' => $stock->qty_large - $qty_large,
                    'updated_at' => $now,
                ]);

            DB::table('food_inventory_cards')->insert([
                'inventory_item_id' => $inventory_item_id,
                'warehouse_id' => $warehouseId,
                'date' => $request->date,
                'reference_type' => 'internal_use_waste',
                'reference_id' => $headerId,
                'out_qty_small' => $qty_small,
                'out_qty_medium' => $qty_medium,
                'out_qty_large' => $qty_large,
                'cost_per_small' => $stock->last_cost_small,
                'cost_per_medium' => $stock->last_cost_medium,
                'cost_per_large' => $stock->last_cost_large,
                'value_out' => $qty_small * $stock->last_cost_small,
                'saldo_qty_small' => $stock->qty_small - $qty_small,
                'saldo_qty_medium' => $stock->qty_medium - $qty_medium,
                'saldo_qty_large' => $stock->qty_large - $qty_large,
                'saldo_value' => ($stock->qty_small - $qty_small) * $stock->last_cost_small,
                'description' => 'Stock Out Serial - ' . $typeLabel . ' (' . $si['serial_number'] . ')',
                'created_at' => $now,
            ]);

            DB::table('inventory_item_serials')->where('id', $si['serial_id'])->update([
                'is_out' => 1,
                'out_at' => $now,
                'out_internal_use_waste_id' => $headerId,
                'updated_at' => $now,
            ]);

            DB::table('inventory_serial_movements')->insert([
                'serial_id' => $si['serial_id'],
                'serial_number' => $si['serial_number'],
                'movement_type' => 'iuw_out',
                'internal_use_waste_header_id' => $headerId,
                'item_id' => $si['item_id'],
                'qty' => $qty,
                'unit_id' => $unitId ?: null,
                'moved_by' => $userId,
                'moved_at' => $now,
                'notes' => 'Internal Use & Waste',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function rollbackSerialItemsForIUW(object $header): void
    {
        $now = now();
        $userId = Auth::id();
        $headerId = (int) $header->id;
        $warehouseId = (int) $header->warehouse_id;

        $serialItems = DB::table('internal_use_waste_serial_items')
            ->where('internal_use_waste_header_id', $headerId)
            ->get();

        foreach ($serialItems as $si) {
            $itemMaster = DB::table('items')->where('id', $si->item_id)->first();
            if (! $itemMaster) {
                continue;
            }

            $unitId = (int) ($si->unit_id ?? 0);
            $qty = (float) $si->qty;
            $qty_small = (float) ($si->qty_small ?? 0);

            if ($unitId > 0) {
                [$qty_small, $qty_medium, $qty_large] = $this->computeQtyTiers($itemMaster, $unitId, $qty);
            } else {
                $qty_medium = 0;
                $qty_large = 0;
            }

            $inventoryItem = DB::table('food_inventory_items')->where('item_id', $si->item_id)->first();
            if ($inventoryItem) {
                $stock = DB::table('food_inventory_stocks')
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                if ($stock) {
                    DB::table('food_inventory_stocks')
                        ->where('id', $stock->id)
                        ->update([
                            'qty_small' => $stock->qty_small + $qty_small,
                            'qty_medium' => $stock->qty_medium + $qty_medium,
                            'qty_large' => $stock->qty_large + $qty_large,
                            'updated_at' => $now,
                        ]);
                }
            }

            DB::table('inventory_item_serials')
                ->where('id', $si->serial_id)
                ->update([
                    'is_out' => 0,
                    'out_at' => null,
                    'out_internal_use_waste_id' => null,
                    'updated_at' => $now,
                ]);

            DB::table('inventory_serial_movements')->insert([
                'serial_id' => $si->serial_id,
                'serial_number' => $si['serial_number'],
                'movement_type' => 'iuw_return',
                'internal_use_waste_header_id' => $headerId,
                'item_id' => $si->item_id,
                'qty' => $qty,
                'unit_id' => $unitId ?: null,
                'moved_by' => $userId,
                'moved_at' => $now,
                'notes' => 'Rollback internal use/waste dihapus',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('inventory_item_serials')
            ->where('out_internal_use_waste_id', $headerId)
            ->update([
                'is_out' => 0,
                'out_at' => null,
                'out_internal_use_waste_id' => null,
                'updated_at' => $now,
            ]);

        DB::table('food_inventory_cards')
            ->where('reference_type', 'internal_use_waste')
            ->where('reference_id', $headerId)
            ->where('warehouse_id', $warehouseId)
            ->delete();
    }
}

