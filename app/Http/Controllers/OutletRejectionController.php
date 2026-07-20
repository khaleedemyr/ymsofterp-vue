<?php

namespace App\Http\Controllers;

use App\Models\OutletRejection;
use App\Models\OutletRejectionItem;
use App\Models\Item;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\Outlet;
use App\Models\DeliveryOrder;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OutletRejectionController extends Controller
{
    public function index(Request $request)
    {
        $query = OutletRejection::with([
            'outlet', 
            'warehouse', 
            'deliveryOrder', 
            'createdBy:id,nama_lengkap',
            'approvedBy:id,nama_lengkap',
            'completedBy:id,nama_lengkap',
            'assistantSsdManager:id,nama_lengkap',
            'ssdManager:id,nama_lengkap'
        ])
        ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhereHas('outlet', function($q) use ($search) {
                      $q->where('nama_outlet', 'like', "%{$search}%");
                  })
                  ->orWhereHas('warehouse', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('date_from')) {
            $query->where('rejection_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('rejection_date', '<=', $request->date_to);
        }

        $rejections = $query->paginate($request->get('per_page', 15));

        // Transform data to include approval information
        $rejections->getCollection()->transform(function ($rejection) {
            // Add approval information
            $rejection->approval_info = [
                'created_by' => $rejection->createdBy ? $rejection->createdBy->nama_lengkap : null,
                'created_at' => $rejection->created_at ? $rejection->created_at->format('d/m/Y H:i') : null,
                'assistant_ssd_manager' => $rejection->assistantSsdManager ? $rejection->assistantSsdManager->nama_lengkap : null,
                'assistant_ssd_manager_at' => $rejection->assistant_ssd_manager_approved_at ? $rejection->assistant_ssd_manager_approved_at->format('d/m/Y H:i') : null,
                'ssd_manager' => $rejection->ssdManager ? $rejection->ssdManager->nama_lengkap : null,
                'ssd_manager_at' => $rejection->ssd_manager_approved_at ? $rejection->ssd_manager_approved_at->format('d/m/Y H:i') : null,
                'completed_by' => $rejection->completedBy ? $rejection->completedBy->nama_lengkap : null,
                'completed_at' => $rejection->completed_at ? $rejection->completed_at->format('d/m/Y H:i') : null,
            ];
            
            return $rejection;
        });

        // Get filter options
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();

        // Check if user can delete
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        return Inertia::render('OutletRejection/Index', [
            'rejections' => $rejections,
            'outlets' => $outlets,
            'warehouses' => $warehouses,
            'canDelete' => $canDelete,
            'filters' => $request->only(['search', 'status', 'outlet_id', 'warehouse_id', 'date_from', 'date_to', 'per_page'])
        ]);
    }

    public function create()
    {
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();
        $deliveryOrders = collect(); // Empty collection initially

        return Inertia::render('OutletRejection/Create', [
            'outlets' => $outlets,
            'warehouses' => $warehouses,
            'deliveryOrders' => $deliveryOrders,
            'documentFlowInfo' => null, // Will be populated when delivery order is selected
            'itemsWithQuantityFlow' => [] // Will be populated when delivery order is selected
        ]);
    }

    public function validateSerialForORJ(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|max:50',
            'outlet_id' => 'required',
            'warehouse_id' => 'required|integer',
            'delivery_order_id' => 'nullable|integer',
        ]);

        $serialNumber = trim($request->serial_number);
        $outletId = (string) $request->outlet_id;
        $warehouseId = (int) $request->warehouse_id;
        $doId = $request->filled('delivery_order_id') ? (int) $request->delivery_order_id : null;

        $serial = DB::table('inventory_item_serials as s')
            ->leftJoin('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('units as u', 'u.id', '=', 's.unit_id')
            ->leftJoin('units as ru', 'ru.id', '=', 's.repack_unit_id')
            ->leftJoin('delivery_orders as do_tbl', 'do_tbl.id', '=', 's.out_delivery_order_id')
            ->where('s.serial_number', $serialNumber)
            ->select(
                's.id',
                's.serial_number',
                's.item_id',
                's.unit_id',
                's.is_out',
                's.out_outlet_id',
                's.out_delivery_order_id',
                's.repack_unit_id',
                's.repack_qty',
                's.source_type',
                's.source_qty',
                's.generated_qty_unit',
                's.cost_small',
                'i.name as item_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'i.small_conversion_qty',
                'i.medium_conversion_qty',
                'u.name as unit_name',
                'ru.name as repack_unit_name',
                'do_tbl.number as do_number'
            )
            ->first();

        if (! $serial) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak ditemukan.']);
        }
        if (! $serial->is_out) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri belum keluar gudang (belum via DO).']);
        }
        if ((string) $serial->out_outlet_id !== $outletId) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak berada di outlet yang dipilih.']);
        }
        if ($doId && (int) $serial->out_delivery_order_id !== $doId) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri tidak berasal dari Delivery Order yang dipilih.']);
        }

        $pending = DB::table('outlet_rejection_serial_items as osi')
            ->join('outlet_rejections as orj', 'orj.id', '=', 'osi.outlet_rejection_id')
            ->where('osi.serial_id', $serial->id)
            ->whereIn('orj.status', ['draft', 'submitted', 'approved'])
            ->exists();
        if ($pending) {
            return response()->json(['valid' => false, 'message' => 'Nomor seri sedang dipakai di dokumen rejection lain yang belum selesai.']);
        }

        $scanQty = \App\Support\InventorySerialEffectiveQty::resolveForScan($serial);

        $itemMaster = DB::table('items')->where('id', $serial->item_id)->first();
        $inventoryItem = DB::table('food_inventory_items')->where('item_id', $serial->item_id)->first();
        $macSmall = $inventoryItem
            ? $this->defaultMacSmallFromStockOrHistory((int) $inventoryItem->id, $warehouseId)
            : (float) ($serial->cost_small ?? 0);
        $macLine = $this->convertMacSmallToLineUnit((float) $macSmall, $itemMaster, (int) $scanQty['unit_id']);

        return response()->json([
            'valid' => true,
            'message' => 'Nomor seri valid.',
            'serial' => [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'item_id' => $serial->item_id,
                'item_name' => $serial->item_name,
                'unit_id' => $scanQty['unit_id'],
                'unit_name' => $scanQty['unit_name'],
                'qty' => $scanQty['qty'],
                'qty_small' => $scanQty['qty_small'],
                'repack_unit_id' => $scanQty['repack_unit_id'],
                'repack_qty' => $scanQty['repack_qty'],
                'repack_unit_name' => $scanQty['repack_unit_name'],
                'physical_qty' => $scanQty['physical_qty'],
                'do_id' => $serial->out_delivery_order_id,
                'do_number' => $serial->do_number ?? '',
                'mac_cost' => round($macLine, 4),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validateOutletRejectionStoreRequest($request);

        DB::beginTransaction();
        try {
            $rejection = $this->createOutletRejectionHeader($request);

            if (! empty($request->serial_items)) {
                $this->saveSerialItemsForORJ($rejection, $request->serial_items, $request);
            }

            if (! empty($request->items)) {
                $this->saveQtyItemsForORJ($rejection, $request->items, (int) $request->warehouse_id);
            }

            DB::commit();

            return redirect()->route('outlet-rejections.show', $rejection->id)
                ->with('success', 'Outlet Rejection berhasil dibuat. Silakan complete untuk masuk ke inventory.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating outlet rejection: ' . $e->getMessage());

            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $rejection = OutletRejection::with([
            'outlet', 
            'warehouse', 
            'deliveryOrder', 
            'createdBy:id,nama_lengkap', 
            'approvedBy:id,nama_lengkap', 
            'completedBy:id,nama_lengkap',
            'assistantSsdManager:id,nama_lengkap',
            'ssdManager:id,nama_lengkap',
            'items.item',
            'items.unit'
        ])->findOrFail($id);

        $rejection->serialItems = $this->loadSerialItemsForORJ($rejection->id);

        // Add approval information to rejection object
        $rejection->approval_info = [
            'created_by' => $rejection->createdBy ? $rejection->createdBy->nama_lengkap : null,
            'created_at' => $rejection->created_at ? $rejection->created_at->format('d/m/Y H:i') : null,
            'assistant_ssd_manager' => $rejection->assistantSsdManager ? $rejection->assistantSsdManager->nama_lengkap : null,
            'assistant_ssd_manager_at' => $rejection->assistant_ssd_manager_approved_at ? $rejection->assistant_ssd_manager_approved_at->format('d/m/Y H:i') : null,
            'ssd_manager' => $rejection->ssdManager ? $rejection->ssdManager->nama_lengkap : null,
            'ssd_manager_at' => $rejection->ssd_manager_approved_at ? $rejection->ssd_manager_approved_at->format('d/m/Y H:i') : null,
            'completed_by' => $rejection->completedBy ? $rejection->completedBy->nama_lengkap : null,
            'completed_at' => $rejection->completed_at ? $rejection->completed_at->format('d/m/Y H:i') : null,
        ];

        // Get document flow information if delivery order exists
        $documentFlowInfo = null;
        if ($rejection->delivery_order_id) {
            $documentFlowInfo = DB::table('delivery_orders as do')
                ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
                ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
                ->leftJoin('outlet_food_good_receives as gr', 'do.id', '=', 'gr.delivery_order_id')
                ->leftJoin('users as do_creator', 'do.created_by', '=', 'do_creator.id')
                ->leftJoin('users as pl_creator', 'pl.created_by', '=', 'pl_creator.id')
                ->leftJoin('users as gr_creator', 'gr.created_by', '=', 'gr_creator.id')
                ->leftJoin('users as fo_creator', 'fo.user_id', '=', 'fo_creator.id')
                ->where('do.id', $rejection->delivery_order_id)
                ->select(
                    'fo.order_number as floor_order_number',
                    'fo.created_at as floor_order_created_at',
                    'fo_creator.nama_lengkap as floor_order_creator',
                    'fo.fo_mode as floor_order_mode',
                    'pl.packing_number',
                    'pl.created_at as packing_list_created_at',
                    'pl_creator.nama_lengkap as packing_list_creator',
                    'do.number as delivery_order_number',
                    'do.created_at as delivery_order_created_at',
                    'do_creator.nama_lengkap as delivery_order_creator',
                    'gr.number as good_receive_number',
                    'gr.receive_date as good_receive_date',
                    'gr_creator.nama_lengkap as good_receive_creator'
                )
                ->first();
        }

        // Get quantity flow information for each item if delivery order exists
        $itemsWithQuantityFlow = [];
        if ($rejection->delivery_order_id) {
            foreach ($rejection->items as $item) {
                $quantityFlow = DB::table('delivery_order_items as doi')
                    ->leftJoin('delivery_orders as do', 'doi.delivery_order_id', '=', 'do.id')
                    ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
                    ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
                    ->leftJoin('food_floor_order_items as foi', function($join) {
                        $join->on('fo.id', '=', 'foi.floor_order_id')
                             ->on('doi.item_id', '=', 'foi.item_id');
                    })
                    ->leftJoin('food_packing_list_items as pli', function($join) {
                        $join->on('pl.id', '=', 'pli.packing_list_id')
                             ->on('foi.id', '=', 'pli.food_floor_order_item_id');
                    })
                    ->leftJoin('outlet_food_good_receives as gr', 'doi.delivery_order_id', '=', 'gr.delivery_order_id')
                    ->leftJoin('outlet_food_good_receive_items as gri', function($join) {
                        $join->on('gr.id', '=', 'gri.outlet_food_good_receive_id')
                            ->on('doi.item_id', '=', 'gri.item_id');
                    })
                    ->where('doi.delivery_order_id', $rejection->delivery_order_id)
                    ->where('doi.item_id', $item->item_id)
                    ->select(
                        'foi.qty as qty_order',
                        'pli.qty as qty_packing_list',
                        'doi.qty_packing_list as qty_do',
                        'gri.received_qty as qty_receive'
                    )
                    ->first();

                $itemsWithQuantityFlow[$item->id] = $quantityFlow;
            }
        }

        return Inertia::render('OutletRejection/Show', [
            'rejection' => $rejection,
            'documentFlowInfo' => $documentFlowInfo,
            'itemsWithQuantityFlow' => $itemsWithQuantityFlow
        ]);
    }

    public function edit($id)
    {
        $rejection = OutletRejection::with([
            'outlet',
            'warehouse',
            'deliveryOrder',
            'createdBy:id,nama_lengkap',
            'approvedBy:id,nama_lengkap',
            'completedBy:id,nama_lengkap',
            'assistantSsdManager:id,nama_lengkap',
            'ssdManager:id,nama_lengkap',
            'items.item',
            'items.unit'
        ])->findOrFail($id);

        // Only allow edit if status is draft
        if ($rejection->status !== 'draft') {
            return redirect()->route('outlet-rejections.show', $id)
                ->with('error', 'Hanya rejection dengan status draft yang dapat diedit');
        }

        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();
        
        // Get delivery orders based on selected outlet and warehouse
        $deliveryOrders = collect();
        if ($rejection->outlet_id && $rejection->warehouse_id) {
            $deliveryOrders = $this->getFilteredDeliveryOrdersData($rejection->outlet_id, $rejection->warehouse_id);
        }

        $rejection->serialItems = $this->loadSerialItemsForORJ($rejection->id);

        return Inertia::render('OutletRejection/Edit', [
            'rejection' => $rejection,
            'outlets' => $outlets,
            'warehouses' => $warehouses,
            'deliveryOrders' => $deliveryOrders
        ]);
    }

    public function update(Request $request, $id)
    {
        $rejection = OutletRejection::findOrFail($id);

        // Only allow update if status is draft
        if ($rejection->status !== 'draft') {
            return back()->withErrors(['error' => 'Hanya rejection dengan status draft yang dapat diedit']);
        }

        $this->validateOutletRejectionStoreRequest($request);

        DB::beginTransaction();
        try {
            $rejection->update([
                'rejection_date' => $request->rejection_date,
                'outlet_id' => $request->outlet_id,
                'warehouse_id' => $request->warehouse_id,
                'delivery_order_id' => $request->delivery_order_id,
                'notes' => $request->notes,
                'rejection_mode' => $this->resolveRejectionMode($request),
            ]);

            DB::table('outlet_rejection_serial_items')->where('outlet_rejection_id', $rejection->id)->delete();
            $rejection->items()->delete();

            if (! empty($request->serial_items)) {
                $this->saveSerialItemsForORJ($rejection, $request->serial_items, $request);
            }
            if (! empty($request->items)) {
                $this->saveQtyItemsForORJ($rejection, $request->items, (int) $request->warehouse_id);
            }

            DB::commit();

            return redirect()->route('outlet-rejections.show', $id)
                ->with('success', 'Outlet Rejection berhasil diupdate');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating outlet rejection: ' . $e->getMessage());
            
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    // Approval Asisten SSD Manager
    public function approveAssistantSsdManager(Request $request, $id)
    {
        $rejection = OutletRejection::with(['outlet', 'warehouse'])->findOrFail($id);
        $user = Auth::user();
        $userJabatan = (int) $user->id_jabatan;
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        
        // Check if warehouse is MK1 or MK2 - jika MK, tidak perlu approval asisten SSD manager
        $isMKWarehouse = in_array($rejection->warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
        if ($isMKWarehouse) {
            return redirect()->route('outlet-rejections.index')->with('error', 'Outlet Rejection MK tidak memerlukan approval Asisten SSD Manager');
        }

        if ($rejection->status !== 'draft') {
            return redirect()->route('outlet-rejections.index')->with('error', 'Outlet Rejection tidak dalam status draft');
        }

        if ($rejection->assistant_ssd_manager_approved_at) {
            return redirect()->route('outlet-rejections.index')->with('error', 'Tahap Asisten SSD Manager sudah di-approve');
        }

        if (!in_array($userJabatan, [172, 161]) && !$isSuperadmin) {
            return redirect()->route('outlet-rejections.index')->with('error', 'Anda tidak berhak approve pada tahap Asisten SSD Manager');
        }
        
        $updateData = [
            'assistant_ssd_manager_approved_at' => now(),
            'assistant_ssd_manager_approved_by' => Auth::id(),
            'assistant_ssd_manager_note' => $request->assistant_ssd_manager_note,
        ];
        
        if ($request->approved) {
            // Jika approved, update status tetap draft (belum final approval)
            $updateData['status'] = 'draft';
            $rejection->update($updateData);
            
            // Notifikasi ke SSD Manager untuk approval selanjutnya
            $ssdManagers = DB::table('users')->where('id_jabatan', 161)->where('status', 'A')->pluck('id');
            $no_rejection = $rejection->number;
            $outlet = $rejection->outlet->nama_outlet ?? '-';
            $warehouse = $rejection->warehouse->name ?? '-';
            $approverLabel = $userJabatan === 172 ? 'Asisten SSD Manager' : ($userJabatan === 161 ? 'SSD Manager' : 'Superadmin');
            $this->sendNotification(
                $ssdManagers,
                'outlet_rejection_approval',
                'Approval Outlet Rejection',
                "Outlet Rejection $no_rejection dari $outlet ($warehouse) sudah di-approve $approverLabel, menunggu approval SSD Manager.",
                route('outlet-rejections.show', $rejection->id)
            );
        } else {
            // Jika rejected, update status jadi rejected
            $updateData['status'] = 'rejected';
            $rejection->update($updateData);
            
            // Notifikasi ke creator rejection jika di-reject
            $createdBy = $rejection->created_by;
            $no_rejection = $rejection->number;
            $outlet = $rejection->outlet->nama_outlet ?? '-';
            $warehouse = $rejection->warehouse->name ?? '-';
            $rejectorLabel = $userJabatan === 172 ? 'Asisten SSD Manager' : ($userJabatan === 161 ? 'SSD Manager' : 'Superadmin');
            $this->sendNotification(
                [$createdBy],
                'outlet_rejection_rejected',
                'Outlet Rejection Ditolak',
                "Outlet Rejection $no_rejection dari $outlet ($warehouse) telah ditolak oleh $rejectorLabel.",
                route('outlet-rejections.show', $rejection->id)
            );
        }
        
        return redirect()->route('outlet-rejections.index');
    }

    // Approval SSD Manager / Sous Chef MK
    public function approveSsdManager(Request $request, $id)
    {
        Log::info('approveSsdManager called', [
            'rejection_id' => $id,
            'request_data' => $request->all(),
            'user_id' => Auth::id()
        ]);
        
        $rejection = OutletRejection::with(['outlet', 'warehouse', 'items'])->findOrFail($id);
        
        // Check if warehouse is MK1 or MK2
        $isMKWarehouse = in_array($rejection->warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
        $approverTitle = $isMKWarehouse ? 'Sous Chef MK' : 'SSD Manager';
        
        // Untuk rejection non-MK: Asisten SSD Manager harus approve dulu,
        // kecuali SSD Manager (161) / superadmin yang boleh final approve sekaligus.
        $user = Auth::user();
        $userJabatan = (int) $user->id_jabatan;
        $isSsdManager = $userJabatan === 161 || $user->id_role === '5af56935b011a';
        if (!$isMKWarehouse && !$rejection->assistant_ssd_manager_approved_at && !$isSsdManager) {
            return redirect()->route('outlet-rejections.index')->with('error', 'Outlet Rejection harus di-approve Asisten SSD Manager terlebih dahulu');
        }
        
        $isApproved = $request->has('approved')
            ? $request->boolean('approved')
            : ! $request->boolean('reject');

        if ($isApproved) {
            Log::info('Rejection approved, starting validation and processing', [
                'rejection_id' => $rejection->id,
                'warehouse_name' => $rejection->warehouse->name ?? 'unknown'
            ]);
            
            $hasQtyLines = $rejection->items->count() > 0;
            $rules = [];
            if ($hasQtyLines) {
                $rules['items'] = 'required|array';
                $rules['items.*.id'] = 'required|exists:outlet_rejection_items,id';
                $rules['items.*.qty_received'] = 'required|numeric|min:0';
            }
            if (! empty($rules)) {
                $request->validate($rules);
            }

            DB::beginTransaction();
            try {
                // Update qty_received for each item
                $requestItems = $request->input('items', []);
                if (!is_array($requestItems)) {
                    $requestItems = [];
                }

                Log::info('Updating qty_received for items', [
                    'items_count' => count($requestItems),
                    'items_data' => $requestItems
                ]);
                
                if ($hasQtyLines && !empty($requestItems)) {
                    foreach ($requestItems as $itemData) {
                        Log::info('Processing item data', [
                            'item_data' => $itemData,
                            'item_id' => $itemData['id'] ?? 'missing',
                            'qty_received' => $itemData['qty_received'] ?? 'missing'
                        ]);

                        $item = $rejection->items()->find($itemData['id']);
                        if ($item) {
                            Log::info('Found item, updating qty_received', [
                                'item_id' => $item->id,
                                'old_qty_received' => $item->qty_received,
                                'new_qty_received' => $itemData['qty_received']
                            ]);
                            $item->update(['qty_received' => $itemData['qty_received']]);
                        } else {
                            Log::warning('Item not found', [
                                'item_id' => $itemData['id'] ?? 'missing'
                            ]);
                        }
                    }
                }
                
                // Refresh rejection data to get updated items
                $rejection->refresh();
                $rejection->load('items');
                
                // Process inventory (similar to food good receive)
                Log::info('Starting inventory processing for outlet rejection', [
                    'rejection_id' => $rejection->id,
                    'total_items' => $rejection->items->count(),
                    'items_with_qty_received' => $rejection->items->where('qty_received', '>', 0)->count(),
                    'items_data' => $rejection->items->toArray()
                ]);
                
                foreach ($rejection->items as $item) {
                    Log::info('Processing item for inventory', [
                        'item_id' => $item->id,
                        'qty_received' => $item->qty_received,
                        'will_process' => $item->qty_received > 0
                    ]);
                    
                    if ($item->qty_received > 0) {
                        Log::info('Calling processInventory for item', [
                            'item_id' => $item->id,
                            'qty_received' => $item->qty_received
                        ]);
                        $this->processInventory($rejection, $item);
                    }
                }

                $serialRows = DB::table('outlet_rejection_serial_items')
                    ->where('outlet_rejection_id', $rejection->id)
                    ->get();
                foreach ($serialRows as $serialRow) {
                    $qtyRec = (float) $serialRow->qty_rejected;
                    DB::table('outlet_rejection_serial_items')
                        ->where('id', $serialRow->id)
                        ->update(['qty_received' => $qtyRec, 'updated_at' => now()]);
                    if ($qtyRec > 0) {
                        $serialRow->qty_received = $qtyRec;
                        $this->processSerialInventoryForORJ($rejection, $serialRow);
                    }
                }

                // Update approval data and complete
                $updateData = [
                    'ssd_manager_approved_at' => now(),
                    'ssd_manager_approved_by' => Auth::id(),
                    'ssd_manager_note' => $request->ssd_manager_note,
                    'status' => 'completed',
                    'completed_by' => Auth::id(),
                    'completed_at' => now(),
                ];

                // SSD Manager final approve: auto-lengkapi tahap Asisten jika belum ada
                if (!$isMKWarehouse && !$rejection->assistant_ssd_manager_approved_at) {
                    $note = $request->ssd_manager_note;
                    $updateData['assistant_ssd_manager_approved_at'] = now();
                    $updateData['assistant_ssd_manager_approved_by'] = Auth::id();
                    $updateData['assistant_ssd_manager_note'] = $note
                        ?: 'Disetujui oleh SSD Manager (delegasi tahap Asisten SSD Manager)';
                }

                $rejection->update($updateData);
                
                Log::info('Transaction about to commit', [
                    'rejection_id' => $rejection->id
                ]);
                
                DB::commit();
                
                Log::info('Transaction committed successfully', [
                    'rejection_id' => $rejection->id
                ]);
                
                return redirect()->route('outlet-rejections.show', $id)
                    ->with('success', 'Outlet Rejection berhasil di-approve dan barang telah masuk ke inventory');
                    
            } catch (\Throwable $e) {
                Log::error('Error in approveSsdManager transaction', [
                    'rejection_id' => $rejection->id ?? 'unknown',
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString()
                ]);
                
                DB::rollBack();
                
                return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
            }
        } else {
            // Jika rejected
            $rejection->update([
                'ssd_manager_approved_at' => now(),
                'ssd_manager_approved_by' => Auth::id(),
                'ssd_manager_note' => $request->ssd_manager_note,
                'status' => 'rejected'
            ]);
            
            // Notifikasi ke creator rejection jika di-reject
            $createdBy = $rejection->created_by;
            $no_rejection = $rejection->number;
            $outlet = $rejection->outlet->nama_outlet ?? '-';
            $warehouse = $rejection->warehouse->name ?? '-';
            $this->sendNotification(
                [$createdBy],
                'outlet_rejection_rejected',
                'Outlet Rejection Ditolak',
                "Outlet Rejection $no_rejection dari $outlet ($warehouse) telah ditolak oleh $approverTitle.",
                route('outlet-rejections.show', $rejection->id)
            );
            
            return redirect()->route('outlet-rejections.index');
        }
    }



    public function cancel($id)
    {
        $rejection = OutletRejection::findOrFail($id);

        if (!$rejection->canBeCancelled()) {
            return back()->withErrors(['error' => 'Rejection tidak dapat dibatalkan']);
        }

        if ($rejection->cancel()) {
            return back()->with('success', 'Rejection berhasil dibatalkan');
        }

        return back()->withErrors(['error' => 'Gagal batalkan rejection']);
    }

    public function destroy($id)
    {
        // Check authorization
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        
        if (!$canDelete) {
            return back()->withErrors(['error' => 'Anda tidak memiliki akses untuk menghapus data ini']);
        }

        $rejection = OutletRejection::findOrFail($id);

        if ($rejection->status !== 'draft') {
            return back()->withErrors(['error' => 'Hanya rejection dengan status draft yang dapat dihapus']);
        }

        DB::beginTransaction();
        try {
            $this->rollbackSerialReservationForORJ($rejection->id);
            DB::table('outlet_rejection_serial_items')->where('outlet_rejection_id', $rejection->id)->delete();
            $rejection->items()->delete();
            $rejection->delete();

            DB::commit();

            return redirect()->route('outlet-rejections.index')
                ->with('success', 'Outlet Rejection berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting outlet rejection: ' . $e->getMessage());
            
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    private function processInventory($rejection, $item)
    {
        Log::info('Processing inventory for outlet rejection', [
            'rejection_id' => $rejection->id,
            'item_id' => $item->item_id,
            'warehouse_id' => $rejection->warehouse_id,
            'qty_received' => $item->qty_received,
            'mac_cost' => $item->mac_cost
        ]);

        // Get item master for conversion
        $itemMaster = DB::table('items')->where('id', $item->item_id)->first();
        if (!$itemMaster) {
            throw new \Exception("Item master not found for item_id: {$item->item_id}");
        }

        // Get or create inventory item
        $inventoryItem = DB::table('food_inventory_items')
            ->where('item_id', $item->item_id)
            ->first();

        if (!$inventoryItem) {
            Log::info('Creating new inventory item', [
                'item_id' => $item->item_id,
                'small_unit_id' => $itemMaster->small_unit_id,
                'medium_unit_id' => $itemMaster->medium_unit_id,
                'large_unit_id' => $itemMaster->large_unit_id
            ]);
            
            $inventoryItemId = DB::table('food_inventory_items')->insertGetId([
                'item_id' => $item->item_id,
                'small_unit_id' => $itemMaster->small_unit_id,
                'medium_unit_id' => $itemMaster->medium_unit_id,
                'large_unit_id' => $itemMaster->large_unit_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inventoryItem = DB::table('food_inventory_items')->where('id', $inventoryItemId)->first();
            
            Log::info('Inventory item created', [
                'inventory_item_id' => $inventoryItemId,
                'inventory_item' => $inventoryItem
            ]);
        } else {
            Log::info('Using existing inventory item', [
                'inventory_item_id' => $inventoryItem->id,
                'inventory_item' => $inventoryItem
            ]);
        }

        // Convert qty to small/medium/large units (prefer unit_id match; fallback to legacy unit name)
        $unit = DB::table('units')->where('id', $item->unit_id)->first();
        $qtyInput = (float) $item->qty_received;
        $qty_small = 0;
        $qty_medium = 0;
        $qty_large = 0;

        $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
        $unitMedium = $itemMaster->medium_unit_id
            ? DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name')
            : null;
        $unitLarge = $itemMaster->large_unit_id
            ? DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name')
            : null;
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

        $lineUnitId = (int) $item->unit_id;
        $qtyById = false;
        if ($lineUnitId === (int) $itemMaster->small_unit_id) {
            $qty_small = $qtyInput;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
            $qtyById = true;
        } elseif (! empty($itemMaster->medium_unit_id) && $lineUnitId === (int) $itemMaster->medium_unit_id) {
            $qty_medium = $qtyInput;
            $qty_small = $qty_medium * $smallConv;
            $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
            $qtyById = true;
        } elseif (! empty($itemMaster->large_unit_id) && $lineUnitId === (int) $itemMaster->large_unit_id) {
            $qty_large = $qtyInput;
            $qty_medium = $qty_large * $mediumConv;
            $qty_small = $qty_medium * $smallConv;
            $qtyById = true;
        }

        if (! $qtyById && $unit && $unitSmall) {
            if ($unit->name === $unitSmall) {
                $qty_small = $qtyInput;
                $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
            } elseif ($unitMedium && $unit->name === $unitMedium) {
                $qty_medium = $qtyInput;
                $qty_small = $qty_medium * $smallConv;
                $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
            } elseif ($unitLarge && $unit->name === $unitLarge) {
                $qty_large = $qtyInput;
                $qty_medium = $qty_large * $mediumConv;
                $qty_small = $qty_medium * $smallConv;
            } else {
                Log::warning('Outlet rejection qty: unit not mapped to item UOM; treating qty as small unit', [
                    'rejection_id' => $rejection->id,
                    'item_id' => $item->item_id,
                    'unit_id' => $item->unit_id,
                ]);
                $qty_small = $qtyInput;
            }
        } elseif (! $qtyById) {
            $qty_small = $qtyInput;
        }

        // Get warehouse division ID from item master
        $warehouseDivisionId = $itemMaster->warehouse_division_id;

        Log::info('Warehouse division info', [
            'warehouse_id' => $rejection->warehouse_id,
            'warehouse_division_id' => $warehouseDivisionId,
            'item_master' => $itemMaster,
        ]);

        // Get existing stock
        $existingStock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $rejection->warehouse_id)
            ->first();

        $lineMacSmall = $this->convertMacFromLineUnitToSmall((float) $item->mac_cost, $lineUnitId, $itemMaster);
        $baselineMac = $this->resolveBaselineMacPerSmall((int) $inventoryItem->id, (int) $rejection->warehouse_id, $existingStock);

        $item_mac_cost_small_unit = $lineMacSmall;
        if ($baselineMac !== null && $baselineMac > 0) {
            if ($lineMacSmall <= 0 || ! is_finite($lineMacSmall)) {
                $item_mac_cost_small_unit = $baselineMac;
            } else {
                $ratio = $lineMacSmall / $baselineMac;
                if ($ratio > 100.0 || $ratio < 0.01) {
                    Log::warning('Outlet rejection: mac_cost per small diverges from stock/history baseline; using baseline', [
                        'rejection_id' => $rejection->id,
                        'outlet_rejection_item_id' => $item->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'warehouse_id' => $rejection->warehouse_id,
                        'line_mac_small' => $lineMacSmall,
                        'baseline_mac' => $baselineMac,
                        'ratio' => $ratio,
                    ]);
                    $item_mac_cost_small_unit = $baselineMac;
                }
            }
        }

        // Calculate MAC (Moving Average Cost) in small unit
        $qty_lama = $existingStock ? (float) $existingStock->qty_small : 0;
        $nilai_lama = $existingStock ? (float) $existingStock->value : 0;
        $qty_baru = $qty_small;
        $nilai_baru = $qty_small * $item_mac_cost_small_unit;
        $total_qty = $qty_lama + $qty_baru;
        $total_nilai = $nilai_lama + $nilai_baru;
        $mac = $total_qty > 0 ? $total_nilai / $total_qty : $item_mac_cost_small_unit;

        Log::info('MAC calculation with small unit conversion', [
            'qty_lama' => $qty_lama,
            'nilai_lama' => $nilai_lama,
            'qty_baru' => $qty_baru,
            'item_mac_cost_original' => $item->mac_cost,
            'item_mac_cost_small_unit' => $item_mac_cost_small_unit,
            'line_mac_small_raw' => $lineMacSmall,
            'baseline_mac' => $baselineMac,
            'small_unit_name' => $unitSmall,
            'medium_unit_name' => $unitMedium,
            'large_unit_name' => $unitLarge,
            'small_conv' => $smallConv,
            'medium_conv' => $mediumConv,
            'nilai_baru' => $nilai_baru,
            'total_qty' => $total_qty,
            'total_nilai' => $total_nilai,
            'mac' => $mac,
            'existing_stock' => $existingStock,
        ]);

        // Update or insert stock
        if ($existingStock) {
            Log::info('Updating existing stock with corrected costs', [
                'stock_id' => $existingStock->id,
                'inventory_item_id' => $inventoryItem->id,
                'warehouse_id' => $rejection->warehouse_id,
                'qty_small' => $total_qty,
                'qty_medium' => $existingStock->qty_medium + $qty_medium,
                'qty_large' => $existingStock->qty_large + $qty_large,
                'value' => $total_nilai,
                'last_cost_small' => $mac,
                'last_cost_medium' => $mac * $smallConv,
                'last_cost_large' => $mac * $smallConv * $mediumConv
            ]);
            
            DB::table('food_inventory_stocks')
                ->where('id', $existingStock->id)
                ->update([
                    'qty_small' => $total_qty,
                    'qty_medium' => $existingStock->qty_medium + $qty_medium,
                    'qty_large' => $existingStock->qty_large + $qty_large,
                    'value' => $total_nilai,
                    'last_cost_small' => $mac,
                    'last_cost_medium' => $mac * $smallConv,
                    'last_cost_large' => $mac * $smallConv * $mediumConv,
                    'updated_at' => now(),
                ]);
        } else {
            Log::info('Creating new stock with corrected costs', [
                'inventory_item_id' => $inventoryItem->id,
                'warehouse_id' => $rejection->warehouse_id,
                'qty_small' => $qty_small,
                'qty_medium' => $qty_medium,
                'qty_large' => $qty_large,
                'value' => $nilai_baru,
                'last_cost_small' => $mac,
                'last_cost_medium' => $mac * $smallConv,
                'last_cost_large' => $mac * $smallConv * $mediumConv
            ]);
            
            DB::table('food_inventory_stocks')->insert([
                'inventory_item_id' => $inventoryItem->id,
                'warehouse_id' => $rejection->warehouse_id,
                'qty_small' => $qty_small,
                'qty_medium' => $qty_medium,
                'qty_large' => $qty_large,
                'value' => $nilai_baru,
                'last_cost_small' => $mac,
                'last_cost_medium' => $mac * $smallConv,
                'last_cost_large' => $mac * $smallConv * $mediumConv,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Calculate saldo for stock card
        $lastCard = DB::table('food_inventory_cards')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $rejection->warehouse_id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if ($lastCard) {
            $saldo_qty_small = $lastCard->saldo_qty_small + $qty_small;
            $saldo_qty_medium = $lastCard->saldo_qty_medium + $qty_medium;
            $saldo_qty_large = $lastCard->saldo_qty_large + $qty_large;
        } else {
            $saldo_qty_small = $qty_small;
            $saldo_qty_medium = $qty_medium;
            $saldo_qty_large = $qty_large;
        }

        // Insert stock card
        Log::info('Inserting stock card', [
            'inventory_item_id' => $inventoryItem->id,
            'warehouse_id' => $rejection->warehouse_id,
            'date' => $rejection->rejection_date,
            'reference_type' => 'outlet_rejection',
            'reference_id' => $rejection->id,
            'saldo_qty_small' => $saldo_qty_small,
            'saldo_qty_medium' => $saldo_qty_medium,
            'saldo_qty_large' => $saldo_qty_large
        ]);
        
                     $stockCardData = [
                 'inventory_item_id' => $inventoryItem->id,
                 'warehouse_id' => $rejection->warehouse_id,
                 'date' => $rejection->rejection_date,
                 'reference_type' => 'outlet_rejection',
                 'reference_id' => $rejection->id,
                 'in_qty_small' => $qty_small,
                 'in_qty_medium' => $qty_medium,
                 'in_qty_large' => $qty_large,
                 'out_qty_small' => 0,
                 'out_qty_medium' => 0,
                 'out_qty_large' => 0,
                 'cost_per_small' => $item_mac_cost_small_unit,
                 'cost_per_medium' => $item_mac_cost_small_unit * $smallConv,
                 'cost_per_large' => $item_mac_cost_small_unit * $smallConv * $mediumConv,
                 'value_in' => $nilai_baru,
                 'value_out' => 0,
                 'saldo_qty_small' => $saldo_qty_small,
                 'saldo_qty_medium' => $saldo_qty_medium,
                 'saldo_qty_large' => $saldo_qty_large,
                 'saldo_value' => $total_nilai,
                 'description' => 'Outlet Rejection - ' . $item->rejection_reason,
                 'created_at' => now(),
             ];
             
             Log::info('Stock card data to insert', [
                 'stock_card_data' => $stockCardData
             ]);
             
             $stockCardId = DB::table('food_inventory_cards')->insertGetId($stockCardData);
             
             Log::info('Stock card inserted successfully', [
                 'stock_card_id' => $stockCardId,
                 'inventory_item_id' => $inventoryItem->id
             ]);

        // Insert cost history (old_cost = rata saldo sebelum gabung; new_cost = biaya layer masuk; mac = MAC baru)
        $old_cost = $qty_lama > 0 ? ((float) $nilai_lama / (float) $qty_lama) : 0.0;

        Log::info('Inserting cost history with small unit costs', [
            'inventory_item_id' => $inventoryItem->id,
            'warehouse_id' => $rejection->warehouse_id,
            'warehouse_division_id' => $warehouseDivisionId,
            'date' => $rejection->rejection_date,
            'old_cost_small_unit' => $old_cost,
            'new_cost_small_unit' => $item_mac_cost_small_unit,
            'mac_small_unit' => $mac,
            'type' => 'outlet_rejection',
            'reference_type' => 'outlet_rejection',
            'reference_id' => $rejection->id,
        ]);

        DB::table('food_inventory_cost_histories')->insert([
            'inventory_item_id' => $inventoryItem->id,
            'warehouse_id' => $rejection->warehouse_id,
            'warehouse_division_id' => $warehouseDivisionId,
            'date' => $rejection->rejection_date,
            'old_cost' => $old_cost,
            'new_cost' => $item_mac_cost_small_unit,
            'mac' => $mac,
            'type' => 'outlet_rejection',
            'reference_type' => 'outlet_rejection',
            'reference_id' => $rejection->id,
            'created_at' => now(),
        ]);
        
        Log::info('Inventory processing completed successfully', [
            'rejection_id' => $rejection->id,
            'item_id' => $item->item_id
        ]);
    }

    // ---------- API for mobile app ----------

    public function apiIndex(Request $request)
    {
        $query = OutletRejection::with([
            'outlet',
            'warehouse',
            'deliveryOrder',
            'createdBy:id,nama_lengkap,avatar',
            'assistantSsdManager:id,nama_lengkap',
            'ssdManager:id,nama_lengkap'
        ])->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('outlet', fn($o) => $o->where('nama_outlet', 'like', "%{$search}%"))
                    ->orWhereHas('warehouse', fn($w) => $w->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('outlet_id')) $query->where('outlet_id', $request->outlet_id);
        if ($request->filled('warehouse_id')) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->filled('date_from')) $query->where('rejection_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->where('rejection_date', '<=', $request->date_to);

        $perPage = (int) $request->get('per_page', 15);
        $rejections = $query->paginate($perPage);
        $rejections->getCollection()->transform(function ($r) {
            $r->approval_info = [
                'created_by' => $r->createdBy ? $r->createdBy->nama_lengkap : null,
                'created_at' => $r->created_at ? $r->created_at->format('Y-m-d H:i') : null,
                'assistant_ssd_manager' => $r->assistantSsdManager ? $r->assistantSsdManager->nama_lengkap : null,
                'ssd_manager' => $r->ssdManager ? $r->ssdManager->nama_lengkap : null,
                'completed_at' => $r->completed_at ? $r->completed_at->format('Y-m-d H:i') : null,
            ];
            return $r;
        });

        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'data' => $rejections->items(),
            'current_page' => $rejections->currentPage(),
            'last_page' => $rejections->lastPage(),
            'per_page' => $rejections->perPage(),
            'total' => $rejections->total(),
            'can_delete' => $canDelete,
            'outlets' => $outlets,
            'warehouses' => $warehouses,
        ]);
    }

    public function apiCreate()
    {
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();
        $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();
        return response()->json([
            'outlets' => $outlets,
            'warehouses' => $warehouses,
        ]);
    }

    public function apiShow($id)
    {
        $rejection = OutletRejection::with([
            'outlet', 'warehouse', 'deliveryOrder',
            'createdBy:id,nama_lengkap', 'completedBy:id,nama_lengkap',
            'assistantSsdManager:id,nama_lengkap', 'ssdManager:id,nama_lengkap',
            'items.item', 'items.unit'
        ])->findOrFail($id);

        $rejection->serialItems = $this->loadSerialItemsForORJ($rejection->id);

        $rejection->approval_info = [
            'created_by' => $rejection->createdBy ? $rejection->createdBy->nama_lengkap : null,
            'created_at' => $rejection->created_at ? $rejection->created_at->format('Y-m-d H:i') : null,
            'assistant_ssd_manager' => $rejection->assistantSsdManager ? $rejection->assistantSsdManager->nama_lengkap : null,
            'assistant_ssd_manager_at' => $rejection->assistant_ssd_manager_approved_at ? $rejection->assistant_ssd_manager_approved_at->format('Y-m-d H:i') : null,
            'ssd_manager' => $rejection->ssdManager ? $rejection->ssdManager->nama_lengkap : null,
            'ssd_manager_at' => $rejection->ssd_manager_approved_at ? $rejection->ssd_manager_approved_at->format('Y-m-d H:i') : null,
            'completed_by' => $rejection->completedBy ? $rejection->completedBy->nama_lengkap : null,
            'completed_at' => $rejection->completed_at ? $rejection->completed_at->format('Y-m-d H:i') : null,
        ];

        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        return response()->json([
            'rejection' => $rejection,
            'can_delete' => $canDelete,
        ]);
    }

    public function apiStore(Request $request)
    {
        $this->validateOutletRejectionStoreRequest($request);

        DB::beginTransaction();
        try {
            $rejection = $this->createOutletRejectionHeader($request);

            if (! empty($request->serial_items)) {
                $this->saveSerialItemsForORJ($rejection, $request->serial_items, $request);
            }
            if (! empty($request->items)) {
                $this->saveQtyItemsForORJ($rejection, $request->items, (int) $request->warehouse_id);
            }

            DB::commit();
            $rejection->load(['outlet', 'warehouse', 'items.item', 'items.unit']);
            $rejection->serialItems = $this->loadSerialItemsForORJ($rejection->id);

            return response()->json([
                'success' => true,
                'message' => 'Outlet Rejection berhasil dibuat',
                'rejection' => $rejection,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OutletRejection apiStore: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function apiUpdate(Request $request, $id)
    {
        $rejection = OutletRejection::findOrFail($id);
        if ($rejection->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya rejection dengan status draft yang dapat diedit'], 403);
        }

        $this->validateOutletRejectionStoreRequest($request);

        DB::beginTransaction();
        try {
            $rejection->update([
                'rejection_date' => $request->rejection_date,
                'outlet_id' => $request->outlet_id,
                'warehouse_id' => $request->warehouse_id,
                'delivery_order_id' => $request->delivery_order_id,
                'notes' => $request->notes,
                'rejection_mode' => $this->resolveRejectionMode($request),
            ]);

            DB::table('outlet_rejection_serial_items')->where('outlet_rejection_id', $rejection->id)->delete();
            $rejection->items()->delete();

            if (! empty($request->serial_items)) {
                $this->saveSerialItemsForORJ($rejection, $request->serial_items, $request);
            }
            if (! empty($request->items)) {
                $this->saveQtyItemsForORJ($rejection, $request->items, (int) $request->warehouse_id);
            }

            DB::commit();
            $fresh = $rejection->fresh(['outlet', 'warehouse', 'items.item', 'items.unit']);
            $fresh->serialItems = $this->loadSerialItemsForORJ($fresh->id);

            return response()->json([
                'success' => true,
                'message' => 'Outlet Rejection berhasil diupdate',
                'rejection' => $fresh,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function apiDestroy($id)
    {
        $user = auth()->user();
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);
        if (!$canDelete) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus'], 403);
        }
        $rejection = OutletRejection::findOrFail($id);
        if ($rejection->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya rejection dengan status draft yang dapat dihapus'], 403);
        }
        DB::beginTransaction();
        try {
            $this->rollbackSerialReservationForORJ($rejection->id);
            DB::table('outlet_rejection_serial_items')->where('outlet_rejection_id', $rejection->id)->delete();
            $rejection->items()->delete();
            $rejection->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Outlet Rejection berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function apiCancel($id)
    {
        $rejection = OutletRejection::findOrFail($id);
        if (!$rejection->canBeCancelled()) {
            return response()->json(['success' => false, 'message' => 'Rejection tidak dapat dibatalkan'], 403);
        }
        if ($rejection->cancel()) {
            return response()->json(['success' => true, 'message' => 'Rejection berhasil dibatalkan']);
        }
        return response()->json(['success' => false, 'message' => 'Gagal batalkan rejection'], 500);
    }

    public function apiSubmit($id)
    {
        $rejection = OutletRejection::findOrFail($id);
        if ($rejection->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya rejection dengan status draft yang dapat disubmit'], 403);
        }
        if ($rejection->submit()) {
            return response()->json(['success' => true, 'message' => 'Outlet Rejection berhasil disubmit']);
        }
        return response()->json(['success' => false, 'message' => 'Submit gagal. Pastikan ada minimal 1 item.'], 400);
    }

    public function getItems(Request $request)
    {
        $items = Item::with(['smallUnit', 'mediumUnit', 'largeUnit', 'category'])
            ->where('status', 'active')
            ->when($request->search, function($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    public function getDeliveryOrderItems(Request $request)
    {
        $deliveryOrderId = $request->delivery_order_id;
        
        if (!$deliveryOrderId) {
            return response()->json([]);
        }

        // Get delivery order items with remaining qty from good receive
        $items = DB::table('delivery_order_items as doi')
            ->join('items as i', 'doi.item_id', '=', 'i.id')
            ->leftJoin('units as u_small', 'i.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'i.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'i.large_unit_id', '=', 'u_large.id')
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('delivery_orders as do', 'doi.delivery_order_id', '=', 'do.id')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('food_floor_order_items as foi', function($join) {
                $join->on('fo.id', '=', 'foi.floor_order_id')
                     ->on('doi.item_id', '=', 'foi.item_id');
            })
            ->leftJoin('food_packing_list_items as pli', function($join) {
                $join->on('pl.id', '=', 'pli.packing_list_id')
                     ->on('foi.id', '=', 'pli.food_floor_order_item_id');
            })
            ->leftJoin('outlet_food_good_receives as gr', 'doi.delivery_order_id', '=', 'gr.delivery_order_id')
            ->leftJoin('outlet_food_good_receive_items as gri', function($join) {
                $join->on('gr.id', '=', 'gri.outlet_food_good_receive_id')
                     ->on('doi.item_id', '=', 'gri.item_id');
            })
            ->select(
                'doi.id',
                'doi.item_id',
                'doi.qty_packing_list as qty_do',
                'doi.unit',
                'i.name as item_name',
                'i.sku',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'u_small.name as small_unit_name',
                'u_medium.name as medium_unit_name',
                'u_large.name as large_unit_name',
                'i.category_id',
                'c.name as category_name',
                'foi.qty as qty_order',
                'pli.qty as qty_packing_list',
                'gri.received_qty as qty_receive',
                'gri.remaining_qty',
                'gri.received_qty',
                'fo.order_number as floor_order_number',
                'fo.created_at as floor_order_created_at',
                'pl.packing_number',
                'pl.created_at as packing_list_created_at',
                'gr.number as good_receive_number',
                'gr.receive_date as good_receive_date'
            )
            ->where('doi.delivery_order_id', $deliveryOrderId)
            ->where('gri.remaining_qty', '>', 0) // Only items with remaining qty
            ->get();

        // Transform data to match expected format
        $transformedItems = $items->map(function($item) {
            // Find the unit_id based on the unit name from delivery_order_items
            $unitId = null;
            if ($item->unit === $item->small_unit_name) {
                $unitId = $item->small_unit_id;
            } elseif ($item->unit === $item->medium_unit_name) {
                $unitId = $item->medium_unit_id;
            } elseif ($item->unit === $item->large_unit_name) {
                $unitId = $item->large_unit_id;
            }
            
            return [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'qty' => $item->remaining_qty, // Use remaining_qty instead of original qty
                'unit_id' => $unitId,
                'unit' => [
                    'id' => $unitId,
                    'name' => $item->unit ?? null
                ],
                'item' => [
                    'id' => $item->item_id ?? null,
                    'name' => $item->item_name ?? null,
                    'sku' => $item->sku ?? null,
                    'small_unit_id' => $item->small_unit_id ?? null,
                    'medium_unit_id' => $item->medium_unit_id ?? null,
                    'large_unit_id' => $item->large_unit_id ?? null,
                    'small_unit_name' => $item->small_unit_name ?? null,
                    'medium_unit_name' => $item->medium_unit_name ?? null,
                    'large_unit_name' => $item->large_unit_name ?? null,
                    'category_id' => $item->category_id ?? null,
                    'category' => [
                        'id' => $item->category_id ?? null,
                        'name' => $item->category_name ?? null
                    ]
                ],
                'qty_order' => $item->qty_order ?? null,
                'qty_packing_list' => $item->qty_packing_list ?? null,
                'qty_do' => $item->qty_do ?? null,
                'qty_receive' => $item->qty_receive ?? null,
                'remaining_qty' => $item->remaining_qty ?? null,
                'received_qty' => $item->received_qty ?? null,
                'floor_order_info' => [
                    'number' => $item->floor_order_number ?? null,
                    'created_at' => $item->floor_order_created_at ?? null,
                    'mode' => $item->floor_order_mode ?? null
                ],
                'packing_list_info' => [
                    'number' => $item->packing_number ?? null,
                    'created_at' => $item->packing_list_created_at ?? null
                ],
                'good_receive_info' => [
                    'number' => $item->good_receive_number ?? null,
                    'receive_date' => $item->good_receive_date ?? null
                ]
            ];
        });

        // Get document flow information
        $documentFlowInfo = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('outlet_food_good_receives as gr', 'do.id', '=', 'gr.delivery_order_id')
            ->leftJoin('users as do_creator', 'do.created_by', '=', 'do_creator.id')
            ->leftJoin('users as pl_creator', 'pl.created_by', '=', 'pl_creator.id')
            ->leftJoin('users as gr_creator', 'gr.created_by', '=', 'gr_creator.id')
            ->leftJoin('users as fo_creator', 'fo.user_id', '=', 'fo_creator.id')
            ->where('do.id', $deliveryOrderId)
            ->select(
                'fo.order_number as floor_order_number',
                'fo.created_at as floor_order_created_at',
                'fo_creator.nama_lengkap as floor_order_creator',
                'fo.fo_mode as floor_order_mode',
                'pl.packing_number',
                'pl.created_at as packing_list_created_at',
                'pl_creator.nama_lengkap as packing_list_creator',
                'do.number as delivery_order_number',
                'do.created_at as delivery_order_created_at',
                'do_creator.nama_lengkap as delivery_order_creator',
                'gr.number as good_receive_number',
                'gr.receive_date as good_receive_date',
                'gr_creator.nama_lengkap as good_receive_creator'
            )
            ->first();

        // Get quantity flow information for each item
        $itemsWithQuantityFlow = [];
        foreach ($transformedItems as $item) {
            $quantityFlow = DB::table('delivery_order_items as doi')
                ->leftJoin('delivery_orders as do', 'doi.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
                ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
                ->leftJoin('food_floor_order_items as foi', function($join) {
                    $join->on('fo.id', '=', 'foi.floor_order_id')
                         ->on('doi.item_id', '=', 'foi.item_id');
                })
                ->leftJoin('food_packing_list_items as pli', function($join) {
                    $join->on('pl.id', '=', 'pli.packing_list_id')
                         ->on('foi.id', '=', 'pli.food_floor_order_item_id');
                })
                ->leftJoin('outlet_food_good_receives as gr', 'doi.delivery_order_id', '=', 'gr.delivery_order_id')
                ->leftJoin('outlet_food_good_receive_items as gri', function($join) {
                    $join->on('gr.id', '=', 'gri.outlet_food_good_receive_id')
                        ->on('doi.item_id', '=', 'gri.item_id');
                })
                ->where('doi.delivery_order_id', $deliveryOrderId)
                ->where('doi.item_id', $item['item_id'])
                ->select(
                    'foi.qty as qty_order',
                    'pli.qty as qty_packing_list',
                    'doi.qty_packing_list as qty_do',
                    'gri.received_qty as qty_receive'
                )
                ->first();

            $itemsWithQuantityFlow[$item['item_id']] = $quantityFlow;
        }

        return response()->json([
            'items' => $transformedItems,
            'documentFlowInfo' => $documentFlowInfo,
            'itemsWithQuantityFlow' => $itemsWithQuantityFlow
        ]);
    }

    public function getFilteredDeliveryOrders(Request $request)
    {
        $outletId = $request->outlet_id;
        $warehouseId = $request->warehouse_id;

        if (!$outletId || !$warehouseId) {
            return response()->json([]);
        }

        $deliveryOrders = $this->getFilteredDeliveryOrdersData($outletId, $warehouseId);
        return response()->json($deliveryOrders);
    }

    private function getFilteredDeliveryOrdersData($outletId, $warehouseId)
    {
        // Get delivery orders that have items with remaining qty in good receive
        // Exclude delivery orders that already have outlet rejections
        $deliveryOrders = DB::table('delivery_orders as do')
            ->leftJoin('food_packing_lists as pl', 'do.packing_list_id', '=', 'pl.id')
            ->leftJoin('food_floor_orders as fo', 'pl.food_floor_order_id', '=', 'fo.id')
            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('outlet_food_good_receives as gr', 'do.id', '=', 'gr.delivery_order_id')
            ->leftJoin('outlet_food_good_receive_items as gri', 'gr.id', '=', 'gri.outlet_food_good_receive_id')
            ->leftJoin('warehouse_division as wd', 'pl.warehouse_division_id', '=', 'wd.id')
            ->leftJoin('users as do_creator', 'do.created_by', '=', 'do_creator.id')
            ->leftJoin('users as pl_creator', 'pl.created_by', '=', 'pl_creator.id')
            ->leftJoin('users as gr_creator', 'gr.created_by', '=', 'gr_creator.id')
            ->leftJoin('users as fo_creator', 'fo.user_id', '=', 'fo_creator.id')
            ->where('o.id_outlet', $outletId)
            ->where('wd.warehouse_id', $warehouseId) // Use warehouse_id from warehouse_division table
            ->where('gri.remaining_qty', '>', 0) // Only items with remaining qty
            ->whereNotExists(function($query) {
                // Exclude delivery orders that are still being processed in active rejection flow
                // Completed/rejected/cancelled historical rejections should not block reuse
                $query->select(DB::raw(1))
                      ->from('outlet_rejections as or')
                      ->whereRaw('or.delivery_order_id = do.id')
                    ->whereIn('or.status', ['draft', 'submitted', 'approved']);
            })
            ->select(
                'do.*',
                'fo.order_number as floor_order_number',
                'fo.created_at as floor_order_created_at',
                'fo_creator.nama_lengkap as floor_order_creator',
                'fo.fo_mode as floor_order_mode',
                'pl.packing_number',
                'pl.created_at as packing_list_created_at',
                'pl_creator.nama_lengkap as packing_list_creator',
                'do_creator.nama_lengkap as delivery_order_creator',
                'gr.number as good_receive_number',
                'gr.receive_date as good_receive_date',
                'gr_creator.nama_lengkap as good_receive_creator',
                DB::raw("DATE_FORMAT(do.created_at, '%d/%m/%Y') as formatted_date"),
                DB::raw("CONCAT(DATE_FORMAT(do.created_at, '%d/%m/%Y'), ' - ', do.number) as display_text")
            )
            ->orderBy('do.created_at', 'desc')
            ->distinct()
            ->get();

        return $deliveryOrders;
    }

    /**
     * MAC per small untuk nilai yang disimpan di outlet_rejection_items (sesuai unit baris).
     */
    private function convertMacSmallToLineUnit(float $macPerSmall, ?object $itemMaster, int $selectedUnitId): float
    {
        if ($macPerSmall <= 0 || ! $itemMaster) {
            return $macPerSmall;
        }
        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);
        if ($selectedUnitId === (int) $itemMaster->small_unit_id) {
            return $macPerSmall;
        }
        if (! empty($itemMaster->medium_unit_id) && $selectedUnitId === (int) $itemMaster->medium_unit_id) {
            return $macPerSmall * $smallConv;
        }
        if (! empty($itemMaster->large_unit_id) && $selectedUnitId === (int) $itemMaster->large_unit_id) {
            return $macPerSmall * $smallConv * $mediumConv;
        }

        return $macPerSmall;
    }

    /**
     * Konversi mac_cost baris rejection (per unit baris) → per unit kecil.
     */
    private function convertMacFromLineUnitToSmall(float $macOnLine, int $lineUnitId, object $itemMaster): float
    {
        if ($macOnLine <= 0) {
            return 0.0;
        }
        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);
        if ($lineUnitId === (int) $itemMaster->small_unit_id) {
            return $macOnLine;
        }
        if (! empty($itemMaster->medium_unit_id) && $lineUnitId === (int) $itemMaster->medium_unit_id && $smallConv > 0) {
            return $macOnLine / $smallConv;
        }
        if (! empty($itemMaster->large_unit_id) && $lineUnitId === (int) $itemMaster->large_unit_id && $smallConv > 0 && $mediumConv > 0) {
            return $macOnLine / ($smallConv * $mediumConv);
        }

        return $macOnLine;
    }

    /**
     * MAC per small untuk prefilled mac_cost draft: stok (value/qty) lalu histori (sama resolveBaselineMacPerSmall).
     */
    private function defaultMacSmallFromStockOrHistory(int $inventoryItemId, int $warehouseId): float
    {
        $stockRow = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('warehouse_id', $warehouseId)
            ->first();
        $b = $this->resolveBaselineMacPerSmall($inventoryItemId, $warehouseId, $stockRow);
        if ($b !== null && $b > 0 && is_finite($b)) {
            return $b;
        }

        return 0.0;
    }

    /**
     * Baseline MAC per small dari stok (value/qty) atau histori — dipakai menjaga posting rejection dari mac_cost baris yang salah skala.
     */
    private function resolveBaselineMacPerSmall(int $inventoryItemId, int $warehouseId, $existingStock): ?float
    {
        if ($existingStock) {
            $qty = (float) ($existingStock->qty_small ?? 0);
            $val = (float) ($existingStock->value ?? 0);
            if ($qty > 0 && $val >= 0) {
                $implied = $val / $qty;
                if ($implied > 0 && is_finite($implied)) {
                    return $implied;
                }
            }
            $lc = (float) ($existingStock->last_cost_small ?? 0);
            if ($lc > 0 && is_finite($lc)) {
                return $lc;
            }
        }

        $last = DB::table('food_inventory_cost_histories')
            ->where('inventory_item_id', $inventoryItemId)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
        if ($last) {
            $m = (float) ($last->mac ?? 0);
            if ($m > 0 && is_finite($m)) {
                return $m;
            }
        }

        return null;
    }

    private function validateOutletRejectionStoreRequest(Request $request): void
    {
        $request->validate([
            'rejection_date' => 'required|date',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'warehouse_id' => 'required|exists:warehouses,id',
            'delivery_order_id' => 'nullable|exists:delivery_orders,id',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.unit_id' => 'required_with:items|exists:units,id',
            'items.*.qty_rejected' => 'required_with:items|numeric|min:0.01',
            'items.*.rejection_reason' => 'nullable|string',
            'items.*.item_condition' => 'required_with:items|in:good,damaged,expired,other',
            'items.*.condition_notes' => 'nullable|string',
            'serial_items' => 'nullable|array',
            'serial_items.*.serial_id' => 'required_with:serial_items|integer',
            'serial_items.*.serial_number' => 'required_with:serial_items|string',
            'serial_items.*.item_id' => 'required_with:serial_items|exists:items,id',
            'serial_items.*.unit_id' => 'nullable|integer',
            'serial_items.*.qty' => 'nullable|numeric|min:0.01',
            'serial_items.*.item_condition' => 'required_with:serial_items|in:good,damaged,expired,other',
            'serial_items.*.rejection_reason' => 'nullable|string',
            'serial_items.*.condition_notes' => 'nullable|string',
        ]);

        $hasItems = is_array($request->items) && count($request->items) > 0;
        $hasSerials = is_array($request->serial_items) && count($request->serial_items) > 0;
        if (! $hasItems && ! $hasSerials) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => ['Minimal satu baris item qty atau nomor seri harus diisi.'],
            ]);
        }
    }

    private function resolveRejectionMode(Request $request): string
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

    private function createOutletRejectionHeader(Request $request): OutletRejection
    {
        return OutletRejection::create([
            'number' => OutletRejection::generateNumber(),
            'rejection_date' => $request->rejection_date,
            'outlet_id' => $request->outlet_id,
            'warehouse_id' => $request->warehouse_id,
            'delivery_order_id' => $request->delivery_order_id,
            'status' => 'draft',
            'notes' => $request->notes,
            'rejection_mode' => $this->resolveRejectionMode($request),
            'created_by' => Auth::id(),
        ]);
    }

    private function saveQtyItemsForORJ(OutletRejection $rejection, array $items, int $warehouseId): void
    {
        foreach ($items as $item) {
            $inventoryItem = DB::table('food_inventory_items')->where('item_id', $item['item_id'])->first();
            $macCostSmallUnit = $inventoryItem
                ? $this->defaultMacSmallFromStockOrHistory((int) $inventoryItem->id, $warehouseId)
                : 0.0;
            $itemData = DB::table('items')->where('id', $item['item_id'])->first();
            $macCostConverted = $this->convertMacSmallToLineUnit(
                (float) $macCostSmallUnit,
                $itemData,
                (int) $item['unit_id']
            );

            OutletRejectionItem::create([
                'outlet_rejection_id' => $rejection->id,
                'item_id' => $item['item_id'],
                'unit_id' => $item['unit_id'],
                'qty_rejected' => $item['qty_rejected'],
                'qty_received' => 0,
                'rejection_reason' => $item['rejection_reason'] ?? null,
                'item_condition' => $item['item_condition'],
                'condition_notes' => $item['condition_notes'] ?? null,
                'mac_cost' => $macCostConverted,
            ]);
        }
    }

    private function saveSerialItemsForORJ(OutletRejection $rejection, array $serialItems, Request $request): void
    {
        $now = now();
        foreach ($serialItems as $row) {
            $serialId = (int) $row['serial_id'];
            $serial = DB::table('inventory_item_serials')->where('id', $serialId)->first();
            if (! $serial) {
                throw new \Exception("Serial ID {$serialId} tidak ditemukan.");
            }
            if (! $serial->is_out || (string) $serial->out_outlet_id !== (string) $request->outlet_id) {
                throw new \Exception("Serial {$row['serial_number']} tidak valid untuk outlet ini.");
            }

            $qty = (float) ($row['qty'] ?? 1);
            $unitId = $row['unit_id'] ?? $serial->unit_id;
            $unitName = DB::table('units')->where('id', $unitId)->value('name');
            $itemMaster = DB::table('items')->where('id', $row['item_id'])->first();
            $inventoryItem = DB::table('food_inventory_items')->where('item_id', $row['item_id'])->first();
            $macSmall = $inventoryItem
                ? $this->defaultMacSmallFromStockOrHistory((int) $inventoryItem->id, (int) $request->warehouse_id)
                : (float) ($serial->cost_small ?? 0);
            $macLine = isset($row['mac_cost'])
                ? (float) $row['mac_cost']
                : $this->convertMacSmallToLineUnit((float) $macSmall, $itemMaster, (int) $unitId);

            $qtySmall = $this->convertQtyToSmall($qty, (int) $unitId, $itemMaster);

            DB::table('outlet_rejection_serial_items')->insert([
                'outlet_rejection_id' => $rejection->id,
                'serial_id' => $serialId,
                'serial_number' => $row['serial_number'],
                'item_id' => $row['item_id'],
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'qty_rejected' => $qty,
                'qty_small' => $qtySmall,
                'qty_received' => 0,
                'rejection_reason' => $row['rejection_reason'] ?? null,
                'item_condition' => $row['item_condition'] ?? 'good',
                'condition_notes' => $row['condition_notes'] ?? null,
                'mac_cost' => $macLine,
                'delivery_order_id' => $request->delivery_order_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('inventory_item_serials')->where('id', $serialId)->update([
                'out_outlet_rejection_id' => $rejection->id,
                'updated_at' => $now,
            ]);
        }
    }

    private function loadSerialItemsForORJ(int $rejectionId)
    {
        return DB::table('outlet_rejection_serial_items as osi')
            ->leftJoin('items as i', 'i.id', '=', 'osi.item_id')
            ->where('osi.outlet_rejection_id', $rejectionId)
            ->select('osi.*', 'i.name as item_name')
            ->orderBy('osi.id')
            ->get();
    }

    private function processSerialInventoryForORJ($rejection, $serialRow): void
    {
        $virtual = (object) [
            'item_id' => $serialRow->item_id,
            'unit_id' => $serialRow->unit_id,
            'qty_received' => $serialRow->qty_received,
            'mac_cost' => $serialRow->mac_cost,
        ];
        $this->processInventory($rejection, $virtual);

        $now = now();
        DB::table('inventory_item_serials')->where('id', $serialRow->serial_id)->update([
            'warehouse_id' => $rejection->warehouse_id,
            'out_outlet_id' => null,
            'out_warehouse_outlet_id' => null,
            'out_delivery_order_id' => null,
            'out_outlet_rejection_id' => null,
            'is_out' => 0,
            'is_received' => 0,
            'updated_at' => $now,
        ]);

        DB::table('inventory_serial_movements')->insert([
            'serial_id' => $serialRow->serial_id,
            'serial_number' => $serialRow->serial_number,
            'movement_type' => 'orj_in',
            'outlet_rejection_id' => $rejection->id,
            'item_id' => $serialRow->item_id,
            'qty' => $serialRow->qty_received,
            'unit_id' => $serialRow->unit_id ?? null,
            'moved_by' => Auth::id(),
            'moved_at' => $now,
            'notes' => 'Outlet rejection return to warehouse',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function convertQtyToSmall(float $qty, int $unitId, ?object $itemMaster): float
    {
        if (! $itemMaster) {
            return $qty;
        }
        $smallConv = (float) ($itemMaster->small_conversion_qty ?: 1);
        $mediumConv = (float) ($itemMaster->medium_conversion_qty ?: 1);
        if ($unitId === (int) $itemMaster->small_unit_id) {
            return $qty;
        }
        if (! empty($itemMaster->medium_unit_id) && $unitId === (int) $itemMaster->medium_unit_id) {
            return $qty * $smallConv;
        }
        if (! empty($itemMaster->large_unit_id) && $unitId === (int) $itemMaster->large_unit_id) {
            return $qty * $smallConv * $mediumConv;
        }

        return $qty;
    }

    private function rollbackSerialReservationForORJ(int $rejectionId): void
    {
        DB::table('inventory_item_serials')
            ->where('out_outlet_rejection_id', $rejectionId)
            ->update([
                'out_outlet_rejection_id' => null,
                'updated_at' => now(),
            ]);
    }

    // Helper untuk insert notifikasi
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
}
