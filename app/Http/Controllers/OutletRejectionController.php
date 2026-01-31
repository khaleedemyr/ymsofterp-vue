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

    public function store(Request $request)
    {
        $request->validate([
            'rejection_date' => 'required|date',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'warehouse_id' => 'required|exists:warehouses,id',
            'delivery_order_id' => 'nullable|exists:delivery_orders,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.qty_rejected' => 'required|numeric|min:0.01',
            'items.*.rejection_reason' => 'nullable|string',
            'items.*.item_condition' => 'required|in:good,damaged,expired,other',
            'items.*.condition_notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Create header
            $rejection = OutletRejection::create([
                'number' => OutletRejection::generateNumber(),
                'rejection_date' => $request->rejection_date,
                'outlet_id' => $request->outlet_id,
                'warehouse_id' => $request->warehouse_id,
                'delivery_order_id' => $request->delivery_order_id,
                'status' => 'draft',
                'notes' => $request->notes,
                'created_by' => Auth::id()
            ]);

            // Create items
            foreach ($request->items as $item) {
                // Get MAC cost from food_inventory_cost_histories (in small unit)
                $inventoryItem = DB::table('food_inventory_items')
                    ->where('item_id', $item['item_id'])
                    ->first();

                $macCostSmallUnit = 0;
                if ($inventoryItem) {
                    $lastCostHistory = DB::table('food_inventory_cost_histories')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $request->warehouse_id)
                        ->orderByDesc('date')
                        ->orderByDesc('created_at')
                        ->first();

                    $macCostSmallUnit = $lastCostHistory ? $lastCostHistory->mac : 0;
                }

                // Convert MAC cost from small unit to selected unit
                $macCostConverted = 0;
                if ($macCostSmallUnit > 0) {
                    // Get item unit conversions
                    $itemData = DB::table('items')
                        ->where('id', $item['item_id'])
                        ->first();

                    if ($itemData) {
                        $selectedUnitId = $item['unit_id'];
                        
                        // Check if selected unit is small unit
                        if ($selectedUnitId == $itemData->small_unit_id) {
                            $macCostConverted = $macCostSmallUnit;
                        }
                        // Check if selected unit is medium unit
                        elseif ($selectedUnitId == $itemData->medium_unit_id && $itemData->small_conversion_qty) {
                            $macCostConverted = $macCostSmallUnit * $itemData->small_conversion_qty;
                        }
                        // Check if selected unit is large unit
                        elseif ($selectedUnitId == $itemData->large_unit_id && $itemData->small_conversion_qty) {
                            $macCostConverted = $macCostSmallUnit * $itemData->small_conversion_qty;
                        }
                        else {
                            // If no conversion found, use small unit cost
                            $macCostConverted = $macCostSmallUnit;
                        }
                    }
                }

                OutletRejectionItem::create([
                    'outlet_rejection_id' => $rejection->id,
                    'item_id' => $item['item_id'],
                    'unit_id' => $item['unit_id'],
                    'qty_rejected' => $item['qty_rejected'],
                    'qty_received' => 0, // Will be filled when completed
                    'rejection_reason' => $item['rejection_reason'],
                    'item_condition' => $item['item_condition'],
                    'condition_notes' => $item['condition_notes'],
                    'mac_cost' => $macCostConverted
                ]);
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

        $request->validate([
            'rejection_date' => 'required|date',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'warehouse_id' => 'required|exists:warehouses,id',
            'delivery_order_id' => 'nullable|exists:delivery_orders,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.qty_rejected' => 'required|numeric|min:0.01',
            'items.*.rejection_reason' => 'nullable|string',
            'items.*.item_condition' => 'required|in:good,damaged,expired,other',
            'items.*.condition_notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Update header
            $rejection->update([
                'rejection_date' => $request->rejection_date,
                'outlet_id' => $request->outlet_id,
                'warehouse_id' => $request->warehouse_id,
                'delivery_order_id' => $request->delivery_order_id,
                'notes' => $request->notes
            ]);

            // Delete existing items
            $rejection->items()->delete();

            // Create new items
            foreach ($request->items as $item) {
                // Get MAC cost from food_inventory_cost_histories
                $inventoryItem = DB::table('food_inventory_items')
                    ->where('item_id', $item['item_id'])
                    ->first();

                $macCost = 0;
                if ($inventoryItem) {
                    $lastCostHistory = DB::table('food_inventory_cost_histories')
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $request->warehouse_id)
                        ->orderByDesc('date')
                        ->orderByDesc('created_at')
                        ->first();

                    $macCost = $lastCostHistory ? $lastCostHistory->mac : 0;
                }

                OutletRejectionItem::create([
                    'outlet_rejection_id' => $rejection->id,
                    'item_id' => $item['item_id'],
                    'unit_id' => $item['unit_id'],
                    'qty_rejected' => $item['qty_rejected'],
                    'qty_received' => 0,
                    'rejection_reason' => $item['rejection_reason'],
                    'item_condition' => $item['item_condition'],
                    'condition_notes' => $item['condition_notes'],
                    'mac_cost' => $macCost
                ]);
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
        
        // Check if warehouse is MK1 or MK2 - jika MK, tidak perlu approval asisten SSD manager
        $isMKWarehouse = in_array($rejection->warehouse->name, ['MK1 Hot Kitchen', 'MK2 Cold Kitchen']);
        if ($isMKWarehouse) {
            return redirect()->route('outlet-rejections.index')->with('error', 'Outlet Rejection MK tidak memerlukan approval Asisten SSD Manager');
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
            $this->sendNotification(
                $ssdManagers,
                'outlet_rejection_approval',
                'Approval Outlet Rejection',
                "Outlet Rejection $no_rejection dari $outlet ($warehouse) sudah di-approve Asisten SSD Manager, menunggu approval SSD Manager.",
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
            $this->sendNotification(
                [$createdBy],
                'outlet_rejection_rejected',
                'Outlet Rejection Ditolak',
                "Outlet Rejection $no_rejection dari $outlet ($warehouse) telah ditolak oleh Asisten SSD Manager.",
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
        
        // Untuk rejection non-MK, pastikan sudah di-approve asisten SSD manager terlebih dahulu
        // Asisten SSD Manager juga bisa approve level 2, tapi harus sudah ada approval level 1 dulu
        $user = Auth::user();
        if (!$isMKWarehouse && !$rejection->assistant_ssd_manager_approved_at) {
            return redirect()->route('outlet-rejections.index')->with('error', 'Outlet Rejection harus di-approve Asisten SSD Manager terlebih dahulu');
        }
        
        if ($request->approved) {
            Log::info('Rejection approved, starting validation and processing', [
                'rejection_id' => $rejection->id,
                'warehouse_name' => $rejection->warehouse->name ?? 'unknown'
            ]);
            
            // Validasi qty_received
            $request->validate([
                'items' => 'required|array',
                'items.*.id' => 'required|exists:outlet_rejection_items,id',
                'items.*.qty_received' => 'required|numeric|min:0'
            ]);
            
            DB::beginTransaction();
            try {
                // Update qty_received for each item
                Log::info('Updating qty_received for items', [
                    'items_count' => count($request->items),
                    'items_data' => $request->items
                ]);
                
                foreach ($request->items as $itemData) {
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
                
                // Update approval data and complete
                $rejection->update([
                    'ssd_manager_approved_at' => now(),
                    'ssd_manager_approved_by' => Auth::id(),
                    'ssd_manager_note' => $request->ssd_manager_note,
                    'status' => 'completed',
                    'completed_by' => Auth::id(),
                    'completed_at' => now()
                ]);
                
                Log::info('Transaction about to commit', [
                    'rejection_id' => $rejection->id
                ]);
                
                DB::commit();
                
                Log::info('Transaction committed successfully', [
                    'rejection_id' => $rejection->id
                ]);
                
                return redirect()->route('outlet-rejections.show', $id)
                    ->with('success', 'Outlet Rejection berhasil di-approve dan barang telah masuk ke inventory');
                    
            } catch (\Exception $e) {
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

        // Convert qty to small/medium/large units
        $unit = DB::table('units')->where('id', $item->unit_id)->first();
        $qtyInput = $item->qty_received;
        $qty_small = 0; $qty_medium = 0; $qty_large = 0;
        
        $unitSmall = DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name');
        $unitMedium = DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name');
        $unitLarge = DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name');
        $smallConv = $itemMaster->small_conversion_qty ?: 1;
        $mediumConv = $itemMaster->medium_conversion_qty ?: 1;

        if ($unit->name === $unitSmall) {
            $qty_small = $qtyInput;
            $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
            $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
        } elseif ($unit->name === $unitMedium) {
            $qty_medium = $qtyInput;
            $qty_small = $qty_medium * $smallConv;
            $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
        } elseif ($unit->name === $unitLarge) {
            $qty_large = $qtyInput;
            $qty_medium = $qty_large * $mediumConv;
            $qty_small = $qty_medium * $smallConv;
        } else {
            $qty_small = $qtyInput;
        }

        // Get warehouse division ID from item master
        $warehouseDivisionId = $itemMaster->warehouse_division_id;
        
        Log::info('Warehouse division info', [
            'warehouse_id' => $rejection->warehouse_id,
            'warehouse_division_id' => $warehouseDivisionId,
            'item_master' => $itemMaster
        ]);
        
        // Get existing stock
        $existingStock = DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $rejection->warehouse_id)
            ->first();

        // Convert item cost to small unit based on rejection item unit
        $item_mac_cost_small_unit = $item->mac_cost;
        
        // Get rejection item unit
        $rejectionUnit = DB::table('units')->where('id', $item->unit_id)->first();
        
        // If rejection item unit is not small unit, convert it
        if ($rejectionUnit->name !== $unitSmall) {
            if ($rejectionUnit->name === $unitMedium) {
                // Convert from medium to small unit
                $item_mac_cost_small_unit = $item->mac_cost / $smallConv;
                Log::info('Converting cost from medium to small unit', [
                    'original_cost' => $item->mac_cost,
                    'small_conversion' => $smallConv,
                    'converted_cost' => $item_mac_cost_small_unit
                ]);
            } elseif ($rejectionUnit->name === $unitLarge) {
                // Convert from large to small unit
                $item_mac_cost_small_unit = $item->mac_cost / ($smallConv * $mediumConv);
                Log::info('Converting cost from large to small unit', [
                    'original_cost' => $item->mac_cost,
                    'small_conversion' => $smallConv,
                    'medium_conversion' => $mediumConv,
                    'converted_cost' => $item_mac_cost_small_unit
                ]);
            }
        } else {
            Log::info('Cost already in small unit, no conversion needed', [
                'original_cost' => $item->mac_cost
            ]);
        }
        
        // Calculate MAC (Moving Average Cost) in small unit
        $qty_lama = $existingStock ? $existingStock->qty_small : 0;
        $nilai_lama = $existingStock ? $existingStock->value : 0;
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
            'rejection_unit_name' => $rejectionUnit->name,
            'small_unit_name' => $unitSmall,
            'medium_unit_name' => $unitMedium,
            'large_unit_name' => $unitLarge,
            'small_conv' => $smallConv,
            'medium_conv' => $mediumConv,
            'nilai_baru' => $nilai_baru,
            'total_qty' => $total_qty,
            'total_nilai' => $total_nilai,
            'mac' => $mac,
            'existing_stock' => $existingStock
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
                 'cost_per_small' => $mac,
                 'cost_per_medium' => $mac * $smallConv,
                 'cost_per_large' => $mac * $smallConv * $mediumConv,
                 'value_in' => $qty_small * $mac,
                 'value_out' => 0,
                 'saldo_qty_small' => $saldo_qty_small,
                 'saldo_qty_medium' => $saldo_qty_medium,
                 'saldo_qty_large' => $saldo_qty_large,
                 'saldo_value' => $saldo_qty_small * $mac,
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

        // Insert cost history
        $lastCostHistory = DB::table('food_inventory_cost_histories')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $rejection->warehouse_id)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->first();

        $old_cost = $lastCostHistory ? $lastCostHistory->new_cost : 0;

        Log::info('Inserting cost history with small unit costs', [
            'inventory_item_id' => $inventoryItem->id,
            'warehouse_id' => $rejection->warehouse_id,
            'warehouse_division_id' => $warehouseDivisionId,
            'date' => $rejection->rejection_date,
            'old_cost_small_unit' => $old_cost,
            'new_cost_small_unit' => $mac,
            'mac_small_unit' => $mac,
            'type' => 'outlet_rejection',
            'reference_type' => 'outlet_rejection',
            'reference_id' => $rejection->id,
            'last_cost_history' => $lastCostHistory
        ]);

        DB::table('food_inventory_cost_histories')->insert([
            'inventory_item_id' => $inventoryItem->id,
            'warehouse_id' => $rejection->warehouse_id,
            'warehouse_division_id' => $warehouseDivisionId,
            'date' => $rejection->rejection_date,
            'old_cost' => $old_cost,
            'new_cost' => $mac,
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
                // Exclude delivery orders that already have outlet rejections
                $query->select(DB::raw(1))
                      ->from('outlet_rejections as or')
                      ->whereRaw('or.delivery_order_id = do.id')
                      ->where('or.status', '!=', 'cancelled'); // Allow cancelled rejections to be reused
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
