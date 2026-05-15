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
    return Inertia::render('SerialTracking/Index', [
      'sourceTypes' => collect(self::GENERATION_SOURCES)->map(fn ($cfg, $key) => [
        'value' => $key,
        'label' => $cfg['label'],
      ])->values(),
    ]);
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
    if (!empty($serial->out_outlet_food_return_id)) {
      return ['code' => 'returned_outlet', 'label' => 'Return outlet', 'color' => 'orange'];
    }
    if (!empty($serial->out_internal_use_waste_id) || !empty($serial->out_internal_use_waste_header_id)) {
      return ['code' => 'internal_use', 'label' => 'Internal Use / Waste', 'color' => 'red'];
    }
    if (!empty($serial->out_warehouse_sale_id)) {
      return ['code' => 'warehouse_sale', 'label' => 'Penjualan antar gudang', 'color' => 'purple'];
    }
    if (!empty($serial->is_transferred) || !empty($serial->transfer_id)) {
      return ['code' => 'transferred', 'label' => 'Sudah ditransfer', 'color' => 'indigo'];
    }
    if (!empty($serial->is_received) || !empty($serial->received_outlet_gr_id)) {
      $receiveNumber = null;
      if (!empty($serial->received_outlet_gr_id)) {
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
    if (!empty($serial->is_out) || !empty($serial->out_delivery_order_id)) {
      return [
        'code' => 'out_do',
        'label' => 'Keluar via DO' . ($serial->out_do_number ? " ({$serial->out_do_number})" : ''),
        'color' => 'amber',
      ];
    }

    return ['code' => 'in_warehouse', 'label' => 'Di gudang', 'color' => 'blue'];
  }

  private function buildTimeline(object $serial): array
  {
    $events = [];

    $sourceMeta = $this->resolveSourceMeta($serial->source_type, $serial->source_id);
    $events[] = [
      'at' => $serial->generated_at ?? $serial->created_at,
      'movement_type' => 'generated',
      'label' => self::MOVEMENT_LABELS['generated'],
      'document_label' => $sourceMeta['label'],
      'document_number' => $sourceMeta['number'],
      'document_url' => $sourceMeta['url'],
      'notes' => $serial->ref_gr_number ? "Ref GR: {$serial->ref_gr_number}" : null,
      'qty' => null,
      'unit_name' => $serial->unit_name,
      'moved_by_name' => null,
    ];

    $movements = DB::table('inventory_serial_movements as m')
      ->leftJoin('users as u', 'u.id', '=', 'm.moved_by')
      ->leftJoin('units as un', 'un.id', '=', 'm.unit_id')
      ->where('m.serial_id', $serial->id)
      ->orderBy('m.moved_at')
      ->orderBy('m.id')
      ->select('m.*', 'u.nama_lengkap as moved_by_name', 'un.name as unit_name')
      ->get();

    foreach ($movements as $m) {
      $ref = $this->resolveMovementReference($m);
      $events[] = [
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
      ];
    }

    if (!empty($serial->is_received) && !empty($serial->received_outlet_gr_id)) {
      $hdr = DB::table('outlet_serial_receive_headers')->where('id', $serial->received_outlet_gr_id)->first();
      if ($hdr) {
        $events[] = [
          'at' => $hdr->receive_date ?? $hdr->created_at,
          'movement_type' => 'outlet_receive',
          'label' => self::MOVEMENT_LABELS['outlet_receive'],
          'document_label' => 'GR Nomor Seri Outlet',
          'document_number' => $hdr->number,
          'document_url' => '/outlet-serial-receive/' . $hdr->id,
          'notes' => $hdr->notes,
          'qty' => null,
          'unit_name' => $serial->unit_name,
          'moved_by_name' => null,
        ];
      }
    }

    usort($events, function ($a, $b) {
      return strtotime((string) ($a['at'] ?? '')) <=> strtotime((string) ($b['at'] ?? ''));
    });

    return array_values($events);
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
