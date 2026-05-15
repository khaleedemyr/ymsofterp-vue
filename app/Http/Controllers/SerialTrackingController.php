<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SerialTrackingController extends Controller
{
  private const GENERATION_SOURCES = [
    'good_receive' => [
      'label' => 'Good Receive',
      'table' => 'food_good_receives',
      'number_column' => 'gr_number',
      'date_column' => 'receive_date',
      'route' => '/food-good-receive/{id}',
    ],
    'mk_production' => [
      'label' => 'MK Production',
      'table' => 'mk_productions',
      'number_column' => 'batch_number',
      'date_column' => 'production_date',
      'route' => '/mk-production/{id}',
    ],
    'butcher_process' => [
      'label' => 'Butcher Process',
      'table' => 'butcher_processes',
      'number_column' => 'number',
      'date_column' => 'process_date',
      'route' => '/butcher-processes/{id}',
    ],
    'repack' => [
      'label' => 'Repack',
      'table' => 'repacks',
      'number_column' => 'repack_number',
      'date_column' => 'created_at',
      'route' => '/repack/{id}',
    ],
    'retail_warehouse_food' => [
      'label' => 'Warehouse Retail Food',
      'table' => 'retail_warehouse_food',
      'number_column' => 'retail_number',
      'date_column' => 'transaction_date',
      'route' => '/retail-warehouse-food/{id}',
    ],
    'stock_adjustment' => [
      'label' => 'Stock Adjustment',
      'table' => 'food_inventory_adjustments',
      'number_column' => 'number',
      'date_column' => 'date',
      'route' => '/food-inventory-adjustment/{id}',
    ],
  ];

  private const MOVEMENT_LABELS = [
    'generated' => 'Serial dibuat',
    'out' => 'Keluar — Delivery Order',
    'return' => 'Retur ke gudang (DO)',
    'transfer_out' => 'Transfer outlet — keluar',
    'transfer_in' => 'Transfer outlet — masuk',
    'wt_out' => 'Pindah gudang — keluar',
    'wt_in' => 'Pindah gudang — masuk',
    'iwt_out' => 'Transfer internal WH — keluar',
    'iwt_in' => 'Transfer internal WH — masuk',
    'rws_out' => 'Penjualan Warehouse Retail',
    'rws_return' => 'Rollback penjualan retail',
    'iuw_out' => 'Internal Use & Waste',
    'iuw_return' => 'Rollback Internal Use & Waste',
    'whs_out' => 'Penjualan antar gudang — keluar',
    'whs_in' => 'Penjualan antar gudang — masuk',
    'whs_return' => 'Rollback penjualan antar gudang',
    'orj_out' => 'Outlet rejection — keluar',
    'orj_in' => 'Outlet rejection — masuk gudang',
    'ofrt_out' => 'Return outlet ke HO',
    'ofrt_in' => 'Return outlet — masuk',
    'outlet_receive' => 'Diterima outlet (GR Nomor Seri)',
  ];

  public function index()
  {
    $user = auth()->user();
    $isHQ = ($user->id_outlet ?? null) == '1';

    $outlets = [];
    if ($isHQ) {
      $outlets = DB::table('tbl_data_outlet')
        ->where('status', 'A')
        ->select('id_outlet as id', 'nama_outlet as name')
        ->orderBy('nama_outlet')
        ->get();
    }

    return Inertia::render('SerialTracking/Index', [
      'sourceTypes' => collect(self::GENERATION_SOURCES)->map(fn ($cfg, $key) => [
        'value' => $key,
        'label' => $cfg['label'],
      ])->values(),
      'outlets' => $outlets,
      'isHQ' => $isHQ,
      'userOutletId' => $user->id_outlet ?? null,
    ]);
  }

  /** Meta untuk mobile app (outlet filter, source types). */
  public function apiMeta()
  {
    $user = auth()->user();
    $isHQ = ($user->id_outlet ?? null) == '1';

    $outlets = [];
    if ($isHQ) {
      $outlets = DB::table('tbl_data_outlet')
        ->where('status', 'A')
        ->select('id_outlet as id', 'nama_outlet as name')
        ->orderBy('nama_outlet')
        ->get();
    }

    return response()->json([
      'is_hq' => $isHQ,
      'user_outlet_id' => $user->id_outlet ?? null,
      'outlets' => $outlets,
      'source_types' => collect(self::GENERATION_SOURCES)->map(fn ($cfg, $key) => [
        'value' => $key,
        'label' => $cfg['label'],
      ])->values(),
    ]);
  }

  /**
   * Serial sudah keluar via DO tetapi belum diterima outlet (GR Nomor Seri).
   * Response dikelompokkan per Delivery Order (expandable di UI).
   */
  public function pendingOutletReceive(Request $request)
  {
    $request->validate([
      'search' => 'nullable|string|max:100',
      'serial_number' => 'nullable|string|max:50',
      'do_number' => 'nullable|string|max:50',
      'outlet_id' => 'nullable|string|max:20',
      'warehouse_outlet_id' => 'nullable|integer',
      'date_from' => 'nullable|date',
      'date_to' => 'nullable|date',
      'per_page' => 'nullable|integer|min:5|max:100',
    ]);

    $user = auth()->user();
    $isHQ = ($user->id_outlet ?? null) == '1';
    $perPage = (int) $request->get('per_page', 20);

    $baseQuery = $this->pendingOutletSerialsQuery($request, $user, $isHQ);

    $totalPending = (clone $baseQuery)->count();
    $distinctDo = (clone $baseQuery)->distinct()->count('s.out_delivery_order_id');
    $distinctOutlet = (clone $baseQuery)->distinct()->count('s.out_outlet_id');

    $doQuery = (clone $baseQuery)
      ->select(
        's.out_delivery_order_id as do_id',
        'do.number as do_number',
        'do.created_at as do_date',
        DB::raw('MAX(s.out_at) as last_out_at'),
        DB::raw('COUNT(*) as pending_serial_count'),
        's.out_outlet_id as outlet_id',
        'o.nama_outlet as outlet_name',
        's.out_warehouse_outlet_id as warehouse_outlet_id',
        'wo.name as warehouse_outlet_name'
      )
      ->groupBy(
        's.out_delivery_order_id',
        'do.number',
        'do.created_at',
        's.out_outlet_id',
        'o.nama_outlet',
        's.out_warehouse_outlet_id',
        'wo.name'
      )
      ->orderByDesc(DB::raw('MAX(COALESCE(s.out_at, do.created_at))'));

    $doPaginated = $doQuery->paginate($perPage);
    $doIds = collect($doPaginated->items())->pluck('do_id')->filter()->values();

    $serialsByDo = collect();
    if ($doIds->isNotEmpty()) {
      $serialsByDo = (clone $baseQuery)
        ->whereIn('s.out_delivery_order_id', $doIds)
        ->orderBy('do.number')
        ->orderBy('s.serial_number')
        ->get()
        ->groupBy('do_id');
    }

    $data = collect($doPaginated->items())->map(function ($doRow) use ($serialsByDo) {
      $displayDate = $doRow->last_out_at ?? $doRow->do_date;
      $daysPending = $displayDate
        ? (int) \Carbon\Carbon::parse($displayDate)->diffInDays(now())
        : null;

      $serials = collect($serialsByDo->get($doRow->do_id, []))->map(function ($row) {
        return [
          'serial_id' => $row->serial_id,
          'serial_number' => $row->serial_number,
          'item_id' => $row->item_id,
          'item_name' => $row->item_name,
          'item_sku' => $row->item_sku,
          'unit_name' => $row->unit_name,
        ];
      })->values();

      return [
        'do_id' => $doRow->do_id,
        'do_number' => $doRow->do_number,
        'do_date' => $doRow->do_date,
        'last_out_at' => $doRow->last_out_at,
        'display_date' => $displayDate,
        'days_pending' => $daysPending,
        'pending_serial_count' => (int) $doRow->pending_serial_count,
        'outlet_id' => $doRow->outlet_id,
        'outlet_name' => $doRow->outlet_name ?? '-',
        'warehouse_outlet_id' => $doRow->warehouse_outlet_id,
        'warehouse_outlet_name' => $doRow->warehouse_outlet_name ?? '-',
        'do_url' => '/delivery-order/' . $doRow->do_id,
        'serials' => $serials,
      ];
    });

    $warehouseOutlets = [];
    if ($isHQ && $request->filled('outlet_id')) {
      $warehouseOutlets = DB::table('warehouse_outlets')
        ->where('outlet_id', $request->outlet_id)
        ->where('status', 'active')
        ->select('id', 'name')
        ->orderBy('name')
        ->get();
    }

    return response()->json([
      'data' => $data,
      'total' => $doPaginated->total(),
      'current_page' => $doPaginated->currentPage(),
      'last_page' => $doPaginated->lastPage(),
      'per_page' => $doPaginated->perPage(),
      'summary' => [
        'total_serials' => $totalPending,
        'distinct_do' => $distinctDo,
        'distinct_outlet' => $distinctOutlet,
      ],
      'warehouse_outlets' => $warehouseOutlets,
    ]);
  }

  private function pendingOutletSerialsQuery(Request $request, $user, bool $isHQ)
  {
    $query = DB::table('inventory_item_serials as s')
      ->join('delivery_orders as do', 'do.id', '=', 's.out_delivery_order_id')
      ->leftJoin('items as i', 'i.id', '=', 's.item_id')
      ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
      ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 's.out_outlet_id')
      ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 's.out_warehouse_outlet_id')
      ->where(function ($q) {
        $q->where('s.is_out', 1)->orWhereNotNull('s.out_delivery_order_id');
      })
      ->where(function ($q) {
        $q->where('s.is_received', 0)->orWhereNull('s.is_received');
      })
      ->where(function ($q) {
        $q->whereNull('s.received_outlet_gr_id')->orWhere('s.received_outlet_gr_id', 0);
      })
      ->whereNotExists(function ($sub) {
        $sub->select(DB::raw(1))
          ->from('outlet_serial_receive_items as osri')
          ->whereColumn('osri.serial_id', 's.id');
      })
      ->select(
        's.id as serial_id',
        's.serial_number',
        's.item_id',
        'i.name as item_name',
        'i.sku as item_sku',
        'u.name as unit_name',
        's.out_at',
        's.out_delivery_order_id as do_id',
        'do.number as do_number',
        'do.created_at as do_date',
        's.out_outlet_id as outlet_id',
        'o.nama_outlet as outlet_name',
        's.out_warehouse_outlet_id as warehouse_outlet_id',
        'wo.name as warehouse_outlet_name'
      );

    if (!$isHQ) {
      $query->where('s.out_outlet_id', $user->id_outlet);
    } elseif ($request->filled('outlet_id')) {
      $query->where('s.out_outlet_id', $request->outlet_id);
    }

    if ($request->filled('warehouse_outlet_id')) {
      $query->where('s.out_warehouse_outlet_id', (int) $request->warehouse_outlet_id);
    }

    if ($request->filled('do_number')) {
      $query->where('do.number', 'like', '%' . trim($request->do_number) . '%');
    }

    if ($request->filled('serial_number')) {
      $query->where('s.serial_number', 'like', '%' . trim($request->serial_number) . '%');
    }

    if ($request->filled('search')) {
      $search = '%' . trim($request->search) . '%';
      $query->where(function ($q) use ($search) {
        $q->where('s.serial_number', 'like', $search)
          ->orWhere('do.number', 'like', $search)
          ->orWhere('i.name', 'like', $search)
          ->orWhere('o.nama_outlet', 'like', $search)
          ->orWhere('wo.name', 'like', $search);
      });
    }

    if ($request->filled('date_from')) {
      $query->where(function ($q) use ($request) {
        $q->whereDate('s.out_at', '>=', $request->date_from)
          ->orWhere(function ($q2) use ($request) {
            $q2->whereNull('s.out_at')->whereDate('do.created_at', '>=', $request->date_from);
          });
      });
    }

    if ($request->filled('date_to')) {
      $query->where(function ($q) use ($request) {
        $q->whereDate('s.out_at', '<=', $request->date_to)
          ->orWhere(function ($q2) use ($request) {
            $q2->whereNull('s.out_at')->whereDate('do.created_at', '<=', $request->date_to);
          });
      });
    }

    return $query;
  }

  public function searchDocuments(Request $request)
  {
    $request->validate([
      'source_type' => 'required|string|in:' . implode(',', array_keys(self::GENERATION_SOURCES)),
      'search' => 'nullable|string|max:100',
      'date_from' => 'nullable|date',
      'date_to' => 'nullable|date',
      'per_page' => 'nullable|integer|min:5|max:100',
    ]);

    $sourceType = $request->source_type;
    $cfg = self::GENERATION_SOURCES[$sourceType];
    $perPage = (int) $request->get('per_page', 20);

    $query = DB::table('inventory_item_serials as s')
      ->where('s.source_type', $sourceType)
      ->whereNotNull('s.source_id')
      ->select(
        's.source_id',
        DB::raw('COUNT(*) as serial_count'),
        DB::raw('MIN(s.generated_at) as first_generated_at'),
        DB::raw('MAX(s.generated_at) as last_generated_at')
      )
      ->groupBy('s.source_id')
      ->orderByDesc(DB::raw('MAX(s.generated_at)'));

    if ($request->filled('search')) {
      $search = '%' . trim($request->search) . '%';
      $ids = DB::table($cfg['table'])
        ->where($cfg['number_column'], 'like', $search)
        ->pluck('id');
      if ($ids->isEmpty()) {
        return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
      }
      $query->whereIn('s.source_id', $ids);
    }

    if ($request->filled('date_from') || $request->filled('date_to')) {
      $docIds = DB::table($cfg['table']);
      if ($request->filled('date_from')) {
        $docIds->whereDate($cfg['date_column'], '>=', $request->date_from);
      }
      if ($request->filled('date_to')) {
        $docIds->whereDate($cfg['date_column'], '<=', $request->date_to);
      }
      $ids = $docIds->pluck('id');
      if ($ids->isEmpty()) {
        return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
      }
      $query->whereIn('s.source_id', $ids);
    }

    $paginated = $query->paginate($perPage);
    $sourceIds = collect($paginated->items())->pluck('source_id')->filter()->unique()->values();

    $docs = collect();
    if ($sourceIds->isNotEmpty()) {
      $docs = DB::table($cfg['table'])
        ->whereIn('id', $sourceIds)
        ->get()
        ->keyBy('id');
    }

    $data = collect($paginated->items())->map(function ($row) use ($cfg, $docs, $sourceType) {
      $doc = $docs->get($row->source_id);
      $number = $doc ? ($doc->{$cfg['number_column']} ?? '-') : '-';
      $date = $doc ? ($doc->{$cfg['date_column']} ?? null) : null;

      return [
        'source_type' => $sourceType,
        'source_id' => $row->source_id,
        'document_number' => $number,
        'document_date' => $date,
        'serial_count' => (int) $row->serial_count,
        'first_generated_at' => $row->first_generated_at,
        'last_generated_at' => $row->last_generated_at,
        'document_url' => str_replace('{id}', (string) $row->source_id, $cfg['route']),
        'source_label' => $cfg['label'],
      ];
    });

    return response()->json([
      'data' => $data,
      'total' => $paginated->total(),
      'current_page' => $paginated->currentPage(),
      'last_page' => $paginated->lastPage(),
      'per_page' => $paginated->perPage(),
    ]);
  }

  public function documentSerials(Request $request)
  {
    $request->validate([
      'source_type' => 'required|string|in:' . implode(',', array_keys(self::GENERATION_SOURCES)),
      'source_id' => 'required|integer|min:1',
      'search' => 'nullable|string|max:50',
      'per_page' => 'nullable|integer|min:10|max:200',
    ]);

    $query = DB::table('inventory_item_serials as s')
      ->leftJoin('items as i', 'i.id', '=', 's.item_id')
      ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
      ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
      ->where('s.source_type', $request->source_type)
      ->where('s.source_id', $request->source_id)
      ->select(
        's.id',
        's.serial_number',
        's.item_id',
        'i.name as item_name',
        's.unit_id',
        'u.name as unit_name',
        's.warehouse_id',
        'w.name as warehouse_name',
        's.is_out',
        's.is_received',
        's.generated_at',
        's.cost_small'
      )
      ->orderBy('s.serial_number');

    if ($request->filled('search')) {
      $query->where('s.serial_number', 'like', '%' . trim($request->search) . '%');
    }

    $perPage = (int) $request->get('per_page', 50);
    $paginated = $query->paginate($perPage);

    return response()->json($paginated);
  }

  public function lookupSerial(Request $request)
  {
    $request->validate([
      'serial_number' => 'required|string|min:2|max:50',
    ]);

    $serialNumber = trim($request->serial_number);

    $serial = DB::table('inventory_item_serials as s')
      ->leftJoin('items as i', 'i.id', '=', 's.item_id')
      ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
      ->leftJoin('units as ru', 'ru.id', '=', 's.repack_unit_id')
      ->leftJoin('warehouses as w', 'w.id', '=', 's.warehouse_id')
      ->leftJoin('tbl_data_outlet as o', 'o.id_outlet', '=', 's.out_outlet_id')
      ->leftJoin('warehouse_outlets as wo', 'wo.id', '=', 's.out_warehouse_outlet_id')
      ->leftJoin('delivery_orders as do_tbl', 'do_tbl.id', '=', 's.out_delivery_order_id')
      ->where('s.serial_number', $serialNumber)
      ->select(
        's.*',
        'i.name as item_name',
        'i.sku as item_sku',
        'u.name as unit_name',
        'ru.name as repack_unit_name',
        'w.name as warehouse_name',
        'o.nama_outlet as out_outlet_name',
        'wo.name as out_warehouse_outlet_name',
        'do_tbl.number as out_do_number'
      )
      ->first();

    if (!$serial) {
      $partial = DB::table('inventory_item_serials as s')
        ->leftJoin('items as i', 'i.id', '=', 's.item_id')
        ->where('s.serial_number', 'like', $serialNumber . '%')
        ->select('s.id', 's.serial_number', 'i.name as item_name', 's.source_type', 's.is_out', 's.is_received')
        ->orderBy('s.serial_number')
        ->limit(20)
        ->get();

      return response()->json([
        'found' => false,
        'suggestions' => $partial,
        'message' => 'Nomor seri tidak ditemukan.',
      ], 404);
    }

    $sourceMeta = $this->resolveSourceMeta($serial->source_type, $serial->source_id);
    $status = $this->resolveCurrentStatus($serial);
    $timeline = $this->buildTimeline($serial);

    return response()->json([
      'found' => true,
      'serial' => [
        'id' => $serial->id,
        'serial_number' => $serial->serial_number,
        'item_id' => $serial->item_id,
        'item_name' => $serial->item_name,
        'item_sku' => $serial->item_sku,
        'unit_name' => $serial->unit_name,
        'repack_unit_name' => $serial->repack_unit_name,
        'repack_qty' => $serial->repack_qty,
        'warehouse_name' => $serial->warehouse_name,
        'source_type' => $serial->source_type,
        'source_type_label' => $sourceMeta['label'] ?? $serial->source_type,
        'source_id' => $serial->source_id,
        'source_document_number' => $sourceMeta['number'] ?? null,
        'source_document_url' => $sourceMeta['url'] ?? null,
        'ref_gr_number' => $serial->ref_gr_number,
        'ref_po_number' => $serial->ref_po_number,
        'generated_at' => $serial->generated_at,
        'cost_small' => $serial->cost_small,
        'status' => $status,
      ],
      'timeline' => $timeline,
    ]);
  }

  private function resolveSourceMeta(?string $sourceType, $sourceId): array
  {
    if (!$sourceType || !$sourceId || !isset(self::GENERATION_SOURCES[$sourceType])) {
      return ['label' => $sourceType, 'number' => null, 'url' => null];
    }

    $cfg = self::GENERATION_SOURCES[$sourceType];
    $doc = DB::table($cfg['table'])->where('id', $sourceId)->first();
    if (!$doc) {
      return ['label' => $cfg['label'], 'number' => null, 'url' => null];
    }

    return [
      'label' => $cfg['label'],
      'number' => $doc->{$cfg['number_column']} ?? null,
      'url' => str_replace('{id}', (string) $sourceId, $cfg['route']),
    ];
  }

  private function resolveCurrentStatus(object $serial): array
  {
    $outletReceive = $this->findOutletSerialReceive($serial);

    if (!empty($serial->out_outlet_food_return_id)) {
      return ['code' => 'returned_outlet', 'label' => 'Return outlet', 'color' => 'orange'];
    }
    if (!empty($serial->out_internal_use_waste_id) || !empty($serial->out_internal_use_waste_header_id)) {
      return ['code' => 'internal_use', 'label' => 'Internal Use / Waste', 'color' => 'red'];
    }
    if (!empty($serial->out_warehouse_sale_id)) {
      return ['code' => 'warehouse_sale', 'label' => 'Penjualan antar gudang', 'color' => 'purple'];
    }
    if ($this->isTruthy($serial->is_transferred ?? null) || !empty($serial->transfer_id)) {
      return ['code' => 'transferred', 'label' => 'Sudah ditransfer', 'color' => 'indigo'];
    }
    if ($outletReceive || $this->isTruthy($serial->is_received ?? null) || !empty($serial->received_outlet_gr_id)) {
      $receiveNumber = $outletReceive->gr_number ?? null;
      if (!$receiveNumber && !empty($serial->received_outlet_gr_id)) {
        $receiveNumber = DB::table('outlet_serial_receive_headers')
          ->where('id', $serial->received_outlet_gr_id)
          ->value('number');
      }

      return [
        'code' => 'received_outlet',
        'label' => 'Diterima outlet' . ($receiveNumber ? " ({$receiveNumber})" : ''),
        'color' => 'green',
      ];
    }
    if (
      $this->isTruthy($serial->is_out ?? null)
      || !empty($serial->out_delivery_order_id)
      || !empty($outletReceive?->delivery_order_id)
    ) {
      $doNumber = $serial->out_do_number
        ?? $outletReceive->delivery_order_number
        ?? null;

      return [
        'code' => 'out_do',
        'label' => 'Keluar via DO' . ($doNumber ? " ({$doNumber})" : ''),
        'color' => 'amber',
      ];
    }

    return ['code' => 'in_warehouse', 'label' => 'Di gudang', 'color' => 'blue'];
  }

  private function buildTimeline(object $serial): array
  {
    $events = [];
    $dedupeKeys = [];

    $sourceMeta = $this->resolveSourceMeta($serial->source_type, $serial->source_id);
    $this->appendTimelineEvent($events, $dedupeKeys, [
      'at' => $serial->generated_at ?? $serial->created_at,
      'movement_type' => 'generated',
      'label' => self::MOVEMENT_LABELS['generated'],
      'document_label' => $sourceMeta['label'],
      'document_number' => $sourceMeta['number'],
      'document_url' => $sourceMeta['url'],
      'notes' => $serial->ref_gr_number ? "Ref GR: {$serial->ref_gr_number}" : null,
      'qty' => null,
      'unit_name' => $serial->unit_name ?? null,
      'moved_by_name' => null,
    ]);

    $movements = DB::table('inventory_serial_movements as m')
      ->leftJoin('users as u', 'u.id', '=', 'm.moved_by')
      ->leftJoin('units as un', 'un.id', '=', 'm.unit_id')
      ->where(function ($q) use ($serial) {
        $q->where('m.serial_id', $serial->id)
          ->orWhere('m.serial_number', $serial->serial_number);
      })
      ->orderBy('m.moved_at')
      ->orderBy('m.id')
      ->select('m.*', 'u.nama_lengkap as moved_by_name', 'un.name as unit_name')
      ->get();

    foreach ($movements as $m) {
      $ref = $this->resolveMovementReference($m);
      $this->appendTimelineEvent($events, $dedupeKeys, [
        'at' => $m->moved_at ?? $m->created_at,
        'movement_type' => $m->movement_type,
        'label' => self::MOVEMENT_LABELS[$m->movement_type] ?? $m->movement_type,
        'document_label' => $ref['label'],
        'document_number' => $ref['number'],
        'document_url' => $ref['url'],
        'notes' => $m->notes,
        'qty' => $m->qty,
        'unit_name' => $m->unit_name,
        'moved_by_name' => $m->moved_by_name,
      ]);
    }

    $this->appendDoFromSerialRecord($events, $dedupeKeys, $serial);
    $this->appendFromOutletSerialReceive($events, $dedupeKeys, $serial);
    $this->appendFromDeliveryOrderItems($events, $dedupeKeys, $serial);

    if ($this->isTruthy($serial->is_received ?? null) && !empty($serial->received_outlet_gr_id)) {
      $hdr = DB::table('outlet_serial_receive_headers')->where('id', $serial->received_outlet_gr_id)->first();
      if ($hdr) {
        $this->appendTimelineEvent($events, $dedupeKeys, [
          'at' => $serial->received_at ?? $hdr->receive_date ?? $hdr->created_at,
          'movement_type' => 'outlet_receive',
          'label' => self::MOVEMENT_LABELS['outlet_receive'],
          'document_label' => 'GR Nomor Seri Outlet',
          'document_number' => $hdr->number,
          'document_url' => '/outlet-serial-receive/' . $hdr->id,
          'notes' => $hdr->notes,
          'qty' => null,
          'unit_name' => $serial->unit_name ?? null,
          'moved_by_name' => null,
        ]);
      }
    }

    usort($events, function ($a, $b) {
      return strtotime((string) ($a['at'] ?? '')) <=> strtotime((string) ($b['at'] ?? ''));
    });

    return array_values($events);
  }

  private function isTruthy($value): bool
  {
    return in_array((string) $value, ['1', 'true', 'yes'], true) || $value === 1 || $value === true;
  }

  private function findOutletSerialReceive(object $serial): ?object
  {
    return DB::table('outlet_serial_receive_items as si')
      ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
      ->whereNull('h.deleted_at')
      ->where(function ($q) use ($serial) {
        $q->where('si.serial_id', $serial->id)
          ->orWhere('si.serial_number', $serial->serial_number);
      })
      ->orderByDesc('si.id')
      ->select(
        'h.id as header_id',
        'h.number as gr_number',
        'h.receive_date',
        'h.created_at as header_created_at',
        'si.delivery_order_id',
        'si.delivery_order_number',
        'si.created_at as item_created_at'
      )
      ->first();
  }

  private function appendTimelineEvent(array &$events, array &$dedupeKeys, array $event): void
  {
    $key = $this->timelineDedupeKey($event);
    if (isset($dedupeKeys[$key])) {
      return;
    }
    $dedupeKeys[$key] = true;
    $events[] = $event;
  }

  /** Dedupe per jenis + nomor dokumen (bukan per timestamp) agar DO/GR tidak dobel. */
  private function timelineDedupeKey(array $event): string
  {
    $type = (string) ($event['movement_type'] ?? '');
    $docNum = strtolower(trim((string) ($event['document_number'] ?? '')));

    $dedupeByDocOnly = [
      'out', 'return', 'outlet_receive', 'generated',
      'transfer_out', 'transfer_in', 'wt_out', 'wt_in', 'iwt_out', 'iwt_in',
      'whs_out', 'whs_in', 'whs_return', 'rws_out', 'rws_return',
      'iuw_out', 'iuw_return', 'orj_out', 'orj_in', 'ofrt_out', 'ofrt_in',
    ];

    if ($docNum !== '' && in_array($type, $dedupeByDocOnly, true)) {
      return $type . '|' . $docNum;
    }

    return $type . '|' . $docNum . '|' . (string) ($event['at'] ?? '');
  }

  private function hasTimelineEvent(array $events, string $movementType, ?string $documentNumber = null): bool
  {
    $doc = $documentNumber ? strtolower(trim($documentNumber)) : '';

    foreach ($events as $ev) {
      if (($ev['movement_type'] ?? '') !== $movementType) {
        continue;
      }
      if ($doc === '' || strtolower(trim((string) ($ev['document_number'] ?? ''))) === $doc) {
        return true;
      }
    }

    return false;
  }

  private function appendDoFromSerialRecord(array &$events, array &$dedupeKeys, object $serial): void
  {
    if (!$this->isTruthy($serial->is_out ?? null) && empty($serial->out_delivery_order_id)) {
      return;
    }

    $doId = $serial->out_delivery_order_id;
    $doNumberPreview = $serial->out_do_number
      ?? ($doId ? DB::table('delivery_orders')->where('id', $doId)->value('number') : null);
    if ($this->hasTimelineEvent($events, 'out', $doNumberPreview)) {
      return;
    }

    $doNumber = $serial->out_do_number
      ?? ($doId ? DB::table('delivery_orders')->where('id', $doId)->value('number') : null);

    $this->appendTimelineEvent($events, $dedupeKeys, [
      'at' => $serial->out_at ?? $serial->updated_at ?? $serial->created_at,
      'movement_type' => 'out',
      'label' => self::MOVEMENT_LABELS['out'],
      'document_label' => 'Delivery Order',
      'document_number' => $doNumber,
      'document_url' => $doId ? '/delivery-order/' . $doId : null,
      'notes' => 'Tercatat pada data serial (flag keluar gudang)',
      'qty' => null,
      'unit_name' => $serial->unit_name ?? null,
      'moved_by_name' => null,
    ]);
  }

  private function appendFromOutletSerialReceive(array &$events, array &$dedupeKeys, object $serial): void
  {
    $rows = DB::table('outlet_serial_receive_items as si')
      ->join('outlet_serial_receive_headers as h', 'h.id', '=', 'si.header_id')
      ->whereNull('h.deleted_at')
      ->where(function ($q) use ($serial) {
        $q->where('si.serial_id', $serial->id)
          ->orWhere('si.serial_number', $serial->serial_number);
      })
      ->orderBy('si.created_at')
      ->select(
        'si.*',
        'h.number as gr_number',
        'h.receive_date',
        'h.created_at as header_created_at',
        'h.notes as header_notes'
      )
      ->get();

    foreach ($rows as $row) {
      // DO sudah tercatat di inventory_serial_movements — cukup tampilkan GR outlet di sini.
      $this->appendTimelineEvent($events, $dedupeKeys, [
        'at' => $row->header_created_at ?? $row->receive_date ?? $row->created_at,
        'movement_type' => 'outlet_receive',
        'label' => self::MOVEMENT_LABELS['outlet_receive'],
        'document_label' => 'GR Nomor Seri Outlet',
        'document_number' => $row->gr_number,
        'document_url' => '/outlet-serial-receive/' . $row->header_id,
        'notes' => $row->header_notes,
        'qty' => $row->qty,
        'unit_name' => $serial->unit_name ?? null,
        'moved_by_name' => null,
      ]);
    }
  }

  private function appendFromDeliveryOrderItems(array &$events, array &$dedupeKeys, object $serial): void
  {
    $rows = DB::table('delivery_order_items as doi')
      ->join('delivery_orders as do', 'do.id', '=', 'doi.delivery_order_id')
      ->where('doi.item_id', $serial->item_id)
      ->whereNotNull('doi.serial_numbers')
      ->where('doi.serial_numbers', 'like', '%' . $serial->serial_number . '%')
      ->select('doi.delivery_order_id', 'doi.serial_numbers', 'do.number as do_number', 'do.created_at')
      ->orderBy('do.created_at')
      ->get();

    foreach ($rows as $row) {
      $nums = json_decode($row->serial_numbers, true);
      if (!is_array($nums) || !in_array($serial->serial_number, $nums, true)) {
        continue;
      }

      if ($this->hasTimelineEvent($events, 'out', $row->do_number)) {
        continue;
      }

      $this->appendTimelineEvent($events, $dedupeKeys, [
        'at' => $row->created_at,
        'movement_type' => 'out',
        'label' => self::MOVEMENT_LABELS['out'],
        'document_label' => 'Delivery Order',
        'document_number' => $row->do_number,
        'document_url' => '/delivery-order/' . $row->delivery_order_id,
        'notes' => 'Tercatat di detail item DO (scan serial)',
        'qty' => null,
        'unit_name' => $serial->unit_name ?? null,
        'moved_by_name' => null,
      ]);
    }
  }

  private function resolveMovementReference(object $m): array
  {
    if (!empty($m->delivery_order_id)) {
      $number = $m->delivery_order_number
        ?? DB::table('delivery_orders')->where('id', $m->delivery_order_id)->value('number');

      return [
        'label' => 'Delivery Order',
        'number' => $number,
        'url' => '/delivery-order/' . $m->delivery_order_id,
      ];
    }

    if (!empty($m->warehouse_sale_id)) {
      $number = DB::table('warehouse_sales')->where('id', $m->warehouse_sale_id)->value('number');

      return [
        'label' => 'Penjualan Antar Gudang',
        'number' => $number,
        'url' => '/warehouse-sales/' . $m->warehouse_sale_id,
      ];
    }

    if (!empty($m->warehouse_transfer_id)) {
      $number = DB::table('warehouse_transfers')->where('id', $m->warehouse_transfer_id)->value('transfer_number');

      return [
        'label' => 'Pindah Gudang',
        'number' => $number,
        'url' => '/warehouse-transfer/' . $m->warehouse_transfer_id,
      ];
    }

    if (!empty($m->internal_use_waste_header_id)) {
      return [
        'label' => 'Internal Use & Waste',
        'number' => '#' . $m->internal_use_waste_header_id,
        'url' => '/internal-use-waste/' . $m->internal_use_waste_header_id,
      ];
    }

    if (!empty($m->retail_warehouse_sale_id)) {
      $number = DB::table('retail_warehouse_sales')->where('id', $m->retail_warehouse_sale_id)->value('sale_number')
        ?? DB::table('retail_warehouse_sales')->where('id', $m->retail_warehouse_sale_id)->value('number');

      return [
        'label' => 'Penjualan Warehouse Retail',
        'number' => $number,
        'url' => '/retail-warehouse-sale/' . $m->retail_warehouse_sale_id,
      ];
    }

    if (!empty($m->outlet_rejection_id)) {
      $number = DB::table('outlet_rejections')->where('id', $m->outlet_rejection_id)->value('number');

      return [
        'label' => 'Outlet Rejection',
        'number' => $number,
        'url' => '/outlet-rejections/' . $m->outlet_rejection_id,
      ];
    }

    if (!empty($m->outlet_food_return_id)) {
      $number = DB::table('outlet_food_returns')->where('id', $m->outlet_food_return_id)->value('number');

      return [
        'label' => 'Return Outlet',
        'number' => $number,
        'url' => '/outlet-food-return/' . $m->outlet_food_return_id,
      ];
    }

    if (!empty($m->outlet_transfer_id)) {
      $number = DB::table('outlet_transfers')->where('id', $m->outlet_transfer_id)->value('transfer_number');

      return [
        'label' => 'Transfer Outlet',
        'number' => $number,
        'url' => '/outlet-transfer/' . $m->outlet_transfer_id,
      ];
    }

    if (!empty($m->internal_warehouse_transfer_id)) {
      $number = DB::table('internal_warehouse_transfers')->where('id', $m->internal_warehouse_transfer_id)->value('number');

      return [
        'label' => 'Transfer Internal WH',
        'number' => $number,
        'url' => '/internal-warehouse-transfer/' . $m->internal_warehouse_transfer_id,
      ];
    }

    if (!empty($m->outlet_id)) {
      $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $m->outlet_id)->value('nama_outlet');

      return [
        'label' => 'Outlet',
        'number' => $outletName,
        'url' => null,
      ];
    }

    return ['label' => null, 'number' => null, 'url' => null];
  }
}
