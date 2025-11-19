<?php

namespace App\Http\Controllers;

use App\Models\ContraBon;
use App\Models\ContraBonItem;
use App\Models\PurchaseOrderFood;
use App\Models\PurchaseOrderFoodItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ContraBonController extends Controller
{
    public function index(Request $request)
    {
        $query = ContraBon::with(['supplier', 'purchaseOrder', 'retailFood', 'warehouseRetailFood', 'creator'])->orderByDesc('created_at');

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('number', 'like', "%$search%")
                  ->orWhere('supplier_invoice_number', 'like', "%$search%")
                  ->orWhereHas('supplier', function($q2) use ($search) {
                      $q2->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('purchaseOrder', function($q2) use ($search) {
                      $q2->where('number', 'like', "%$search%");
                  })
                  ->orWhereHas('retailFood', function($q2) use ($search) {
                      $q2->where('retail_number', 'like', "%$search%");
                  })
                  ->orWhere('total_amount', 'like', "%$search%")
                  ->orWhere('status', 'like', "%$search%")
                  ->orWhereHas('creator', function($q2) use ($search) {
                      $q2->where('nama_lengkap', 'like', "%$search%");
                  });
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('date', '<=', $request->to);
        }
        $contraBons = $query->paginate(10)->withQueryString();
        
        // Transform data to include source information
        $contraBons->getCollection()->transform(function ($contraBon) {
            // Add source information for purchase orders
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                $po = $contraBon->purchaseOrder;
                
                // Get source information based on PO source_type
                if ($po->source_type === 'pr_foods' || !$po->source_type) {
                    // For PR Foods, get PR numbers
                    $prNumbers = DB::table('pr_foods as pr')
                        ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
                        ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
                        ->where('poi.purchase_order_food_id', $po->id)
                        ->distinct()
                        ->pluck('pr.pr_number')
                        ->toArray();
                    
                    $contraBon->source_numbers = $prNumbers;
                    $contraBon->source_outlets = []; // PR Foods tidak punya outlet
                    $contraBon->source_type_display = 'PR Foods';
                } elseif ($po->source_type === 'ro_supplier') {
                    // Get RO Supplier numbers and outlet names
                    $roData = DB::table('food_floor_orders as fo')
                        ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                        ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                        ->where('poi.purchase_order_food_id', $po->id)
                        ->select('fo.order_number', 'o.nama_outlet', 'fo.id_outlet')
                        ->distinct()
                        ->get();
                    
                    
                    $contraBon->source_numbers = $roData->pluck('order_number')->unique()->filter()->toArray();
                    $contraBon->source_outlets = $roData->pluck('nama_outlet')->unique()->filter()->toArray();
                    $contraBon->source_type_display = 'RO Supplier';
                } else {
                    $contraBon->source_numbers = [];
                    $contraBon->source_outlets = [];
                    $contraBon->source_type_display = 'Unknown';
                }
            } elseif ($contraBon->source_type === 'retail_food') {
                $retailFood = \App\Models\RetailFood::find($contraBon->source_id);
                $contraBon->source_numbers = [$retailFood->retail_number ?? ''];
                $contraBon->source_outlets = [$retailFood->outlet ? $retailFood->outlet->nama_outlet : ''];
                $contraBon->source_type_display = 'Retail Food';
            } elseif ($contraBon->source_type === 'warehouse_retail_food') {
                $warehouseRetailFood = \App\Models\RetailWarehouseFood::find($contraBon->source_id);
                $contraBon->source_numbers = [$warehouseRetailFood->retail_number ?? ''];
                $warehouseName = $warehouseRetailFood->warehouse ? $warehouseRetailFood->warehouse->name : '';
                $divisionName = $warehouseRetailFood->warehouseDivision ? $warehouseRetailFood->warehouseDivision->name : '';
                $contraBon->source_outlets = [$warehouseName . ($divisionName ? ' - ' . $divisionName : '')];
                $contraBon->source_type_display = 'Warehouse Retail Food';
            } else {
                $contraBon->source_numbers = [];
                $contraBon->source_outlets = [];
                $contraBon->source_type_display = 'Unknown';
            }
            
            return $contraBon;
        });
        
        return inertia('ContraBon/Index', [
            'contraBons' => $contraBons,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    public function create()
    {
        return inertia('ContraBon/Form');
    }

    public function store(Request $request)
    {
        $sourceType = $request->input('source_type', 'purchase_order');
        
        $rules = [
            'date' => 'required|date',
            'items' => 'required|array',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'source_type' => 'nullable|in:purchase_order,retail_food,warehouse_retail_food',
        ];
        
        // Conditional validation based on source_type
        if ($sourceType === 'purchase_order') {
            $rules['po_id'] = 'required|exists:purchase_order_foods,id';
            $rules['gr_id'] = 'required|exists:food_good_receives,id';
            $rules['items.*.item_id'] = 'required|exists:items,id';
            $rules['items.*.unit_id'] = 'required|exists:units,id';
        } else {
            // For retail_food and warehouse_retail_food, item_id and unit_id are optional
            $rules['source_id'] = 'required|integer';
        }
        
        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Contra Bon validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->except(['image'])
            ]);
            
            // Return JSON error hanya jika request dari axios/ajax (bukan Inertia)
            // Inertia request akan memiliki header X-Inertia, jadi kita skip JSON response untuk itu
            if (($request->wantsJson() || $request->ajax() || $request->expectsJson()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $e->errors()
                ], 422);
            }
            
            throw $e;
        }

        DB::beginTransaction();
        try {
            $sourceType = $request->input('source_type', 'purchase_order');
            $sourceId = $request->input('source_id');
            $supplierId = null;
            $poId = null;
            $grId = null;

            // Handle different source types
            if ($sourceType === 'purchase_order') {
                $po = PurchaseOrderFood::findOrFail($request->po_id);
                $supplierId = $po->supplier_id;
                $poId = $po->id;
                $grId = $request->input('gr_id');
            } elseif ($sourceType === 'retail_food') {
                $retailFood = \App\Models\RetailFood::findOrFail($sourceId);
                $supplierId = $retailFood->supplier_id;
                $poId = null;
                $grId = null;
            } elseif ($sourceType === 'warehouse_retail_food') {
                $warehouseRetailFood = \App\Models\RetailWarehouseFood::findOrFail($sourceId);
                $supplierId = $warehouseRetailFood->supplier_id;
                $poId = null;
                $grId = null;
            }
            
            // Generate contra bon number
            $dateStr = date('Ymd', strtotime($request->date));
            $countToday = ContraBon::whereDate('date', $request->date)->count();
            $number = 'CB-' . $dateStr . '-' . str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);

            // Calculate total amount
            $totalAmount = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['price'];
            });

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                \Log::info('File ditemukan', [$request->file('image')]);
                $imagePath = $request->file('image')->store('contra_bon_images', 'public');
            } else {
                \Log::info('File TIDAK ditemukan');
            }
            \Log::info('Image path yang akan disimpan:', [$imagePath]);

            // Create contra bon
            $contraBon = ContraBon::create([
                'number' => $number,
                'date' => $request->date,
                'supplier_id' => $supplierId,
                'po_id' => $poId,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'image_path' => $imagePath,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'supplier_invoice_number' => $request->supplier_invoice_number,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            // Create contra bon items
            foreach ($request->items as $item) {
                // Untuk retail food, item_id dan unit_id bisa null
                $itemId = $item['item_id'] ?? null;
                $unitId = $item['unit_id'] ?? null;
                
                // Jika dari retail food atau warehouse retail food dan tidak ada item_id, coba cari berdasarkan item_name
                if (($sourceType === 'retail_food' || $sourceType === 'warehouse_retail_food') && !$itemId && isset($item['item_name'])) {
                    $foundItem = \DB::table('items')->where('name', $item['item_name'])->first();
                    if ($foundItem) {
                        $itemId = $foundItem->id;
                    }
                }
                
                // Jika dari retail food atau warehouse retail food dan tidak ada unit_id, coba cari berdasarkan unit_name
                if (($sourceType === 'retail_food' || $sourceType === 'warehouse_retail_food') && !$unitId && isset($item['unit_name'])) {
                    $foundUnit = \DB::table('units')->where('name', $item['unit_name'])->first();
                    if ($foundUnit) {
                        $unitId = $foundUnit->id;
                    }
                }
                
                ContraBonItem::create([
                    'contra_bon_id' => $contraBon->id,
                    'item_id' => $itemId,
                    'po_item_id' => $item['po_item_id'] ?? null,
                    'gr_item_id' => $item['gr_item_id'] ?? null, // Simpan gr_item_id untuk tracking
                    'retail_food_item_id' => $item['retail_food_item_id'] ?? null, // OPSIONAL: Jika kolom ada
                    'warehouse_retail_food_item_id' => $item['warehouse_retail_food_item_id'] ?? null, // OPSIONAL: Jika kolom ada
                    'quantity' => $item['quantity'],
                    'unit_id' => $unitId,
                    'price' => $item['price'],
                    'total' => $item['quantity'] * $item['price'],
                    'notes' => $item['notes'] ?? null
                ]);
            }

            // Activity log
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'contra_bon',
                'description' => 'Create Contra Bon: ' . $contraBon->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $contraBon->fresh()->toArray(),
            ]);

            // Send notification to Finance Manager
            $financeManagers = \DB::table('users')
                ->where('id_jabatan', 160)
                ->where('status', 'A')
                ->pluck('id');
            
            $this->sendNotification(
                $financeManagers,
                'contra_bon_approval',
                'Approval Contra Bon',
                "Contra Bon {$contraBon->number} menunggu approval Anda.",
                route('contra-bons.show', $contraBon->id)
            );

            DB::commit();
            
            \Log::info('Contra Bon created successfully', [
                'contra_bon_id' => $contraBon->id,
                'number' => $contraBon->number,
                'total_amount' => $contraBon->total_amount,
                'items_count' => count($request->items)
            ]);
            
            // Return JSON response hanya jika request dari axios/ajax (bukan Inertia)
            // Inertia request akan memiliki header X-Inertia, jadi kita skip JSON response untuk itu
            if (($request->wantsJson() || $request->ajax() || $request->expectsJson()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Contra Bon berhasil dibuat',
                    'data' => [
                        'id' => $contraBon->id,
                        'number' => $contraBon->number
                    ]
                ], 200);
            }
            
            return redirect()->route('contra-bons.index')
                ->with('success', 'Contra Bon berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Error creating Contra Bon', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'source_type' => $request->input('source_type'),
                    'items_count' => count($request->input('items', [])),
                ]
            ]);
            
            // Return JSON error hanya jika request dari axios/ajax (bukan Inertia)
            // Inertia request akan memiliki header X-Inertia, jadi kita skip JSON response untuk itu
            if (($request->wantsJson() || $request->ajax() || $request->expectsJson()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                    'error' => $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show($id)
    {
        $contraBon = ContraBon::with([
            'supplier',
            'purchaseOrder',
            'items.item',
            'items.unit',
            'creator',
            'approver',
            'financeManager',
            'gmFinance'
        ])->findOrFail($id);

        // Add source information for purchase orders
        if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
            $po = $contraBon->purchaseOrder;
            
            // Get PO discount information
            $contraBon->po_discount_info = [
                'discount_total_percent' => $po->discount_total_percent ?? 0,
                'discount_total_amount' => $po->discount_total_amount ?? 0,
                'subtotal' => $po->subtotal ?? 0,
                'grand_total' => $po->grand_total ?? 0,
            ];
            
            // Get source information based on PO source_type
            if ($po->source_type === 'pr_foods' || !$po->source_type) {
                // For PR Foods, get PR numbers
                $prNumbers = DB::table('pr_foods as pr')
                    ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
                    ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
                    ->where('poi.purchase_order_food_id', $po->id)
                    ->distinct()
                    ->pluck('pr.pr_number')
                    ->toArray();
                
                $contraBon->source_numbers = $prNumbers;
                $contraBon->source_outlets = []; // PR Foods tidak punya outlet
                $contraBon->source_type_display = 'PR Foods';
            } elseif ($po->source_type === 'ro_supplier') {
                // Get RO Supplier numbers and outlet names
                $roData = DB::table('food_floor_orders as fo')
                    ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                    ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                    ->where('poi.purchase_order_food_id', $po->id)
                    ->select('fo.order_number', 'o.nama_outlet', 'fo.id_outlet')
                    ->distinct()
                    ->get();
                
                
                $contraBon->source_numbers = $roData->pluck('order_number')->unique()->filter()->toArray();
                $contraBon->source_outlets = $roData->pluck('nama_outlet')->unique()->filter()->toArray();
                $contraBon->source_type_display = 'RO Supplier';
            } else {
                $contraBon->source_numbers = [];
                $contraBon->source_outlets = [];
                $contraBon->source_type_display = 'Unknown';
            }
            
            // Add discount info to items
            $contraBon->items->each(function($item) {
                if ($item->po_item_id) {
                    $poItem = PurchaseOrderFoodItem::find($item->po_item_id);
                    if ($poItem) {
                        $item->discount_percent = $poItem->discount_percent ?? 0;
                        $item->discount_amount = $poItem->discount_amount ?? 0;
                        
                        // Calculate total based on Contra Bon item quantity (not PO item quantity)
                        // Formula: (price * contra_bon_quantity) - (discount proportional to quantity)
                        $contraBonQuantity = $item->quantity ?? 0;
                        $poQuantity = $poItem->quantity ?? 1; // Avoid division by zero
                        $price = $poItem->price ?? 0;
                        
                        // Calculate subtotal for Contra Bon quantity
                        $subtotal = $price * $contraBonQuantity;
                        
                        // Calculate discount proportional to quantity ratio
                        $quantityRatio = $poQuantity > 0 ? ($contraBonQuantity / $poQuantity) : 0;
                        $discount = 0;
                        
                        if ($poItem->discount_percent > 0) {
                            // Discount percent applies to subtotal
                            $discount = $subtotal * ($poItem->discount_percent / 100);
                        } elseif ($poItem->discount_amount > 0) {
                            // Discount amount is proportional to quantity
                            $discount = $poItem->discount_amount * $quantityRatio;
                        }
                        
                        $item->po_item_total = $subtotal - $discount;
                    }
                }
            });
        } elseif ($contraBon->source_type === 'retail_food') {
            $retailFood = \App\Models\RetailFood::find($contraBon->source_id);
            $contraBon->source_numbers = [$retailFood->retail_number ?? ''];
            $contraBon->source_outlets = [$retailFood->outlet ? $retailFood->outlet->nama_outlet : ''];
            $contraBon->source_type_display = 'Retail Food';
        } elseif ($contraBon->source_type === 'warehouse_retail_food') {
            $warehouseRetailFood = \App\Models\RetailWarehouseFood::find($contraBon->source_id);
            $contraBon->source_numbers = [$warehouseRetailFood->retail_number ?? ''];
            $warehouseName = $warehouseRetailFood->warehouse ? $warehouseRetailFood->warehouse->name : '';
            $divisionName = $warehouseRetailFood->warehouseDivision ? $warehouseRetailFood->warehouseDivision->name : '';
            $contraBon->source_outlets = [$warehouseName . ($divisionName ? ' - ' . $divisionName : '')];
            $contraBon->source_type_display = 'Warehouse Retail Food';
        } else {
            $contraBon->source_numbers = [];
            $contraBon->source_outlets = [];
            $contraBon->source_type_display = 'Unknown';
        }

        return inertia('ContraBon/Show', [
            'contraBon' => $contraBon,
            'user' => auth()->user()
        ]);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'approved' => 'required|boolean',
            'note' => 'nullable|string'
        ]);

        $user = Auth::user();
        $contraBon = ContraBon::findOrFail($id);

        // Superadmin check
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        // Finance Manager Approval (Only level - final approval)
        if (
            ($user->id_jabatan == 160 && $user->status == 'A' && $contraBon->status == 'draft' && !$contraBon->finance_manager_approved_at)
            || ($isSuperadmin && $contraBon->status == 'draft' && !$contraBon->finance_manager_approved_at)
        ) {
            $contraBon->update([
                'finance_manager_approved_at' => now(),
                'finance_manager_approved_by' => $user->id,
                'finance_manager_note' => $request->note,
                'status' => $request->approved ? 'approved' : 'rejected'
            ]);

            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => $request->approved ? 'approve' : 'reject',
                'module' => 'contra_bon',
                'description' => ($request->approved ? 'Approve' : 'Reject') . ' Contra Bon (Finance Manager): ' . $contraBon->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $contraBon->fresh()->toArray(),
            ]);

            // Send notification to creator if approved
            if ($request->approved && $contraBon->created_by) {
                \App\Models\Notification::create([
                    'user_id' => $contraBon->created_by,
                    'type' => 'contra_bon_approval',
                    'title' => 'Contra Bon Disetujui',
                    'message' => "Contra Bon {$contraBon->number} telah disetujui oleh Finance Manager.",
                ]);
            }

            $msg = 'Contra Bon berhasil ' . ($request->approved ? 'diapprove' : 'direject');
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }
            return back()->with('success', $msg);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berhak melakukan approval pada tahap ini'], 403);
        }
        return back()->with('error', 'Anda tidak berhak melakukan approval pada tahap ini');
    }

    /**
     * Get Contra Bon detail for API (JSON response)
     */
    public function getDetail($id)
    {
        try {
            $contraBon = ContraBon::with([
                'supplier',
                'purchaseOrder',
                'items.item',
                'items.unit',
                'creator',
                'approver',
                'financeManager',
                'gmFinance'
            ])->findOrFail($id);

            // Add source information for purchase orders
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                $po = $contraBon->purchaseOrder;
                
                // Get PO discount information
                $contraBon->po_discount_info = [
                    'discount_total_percent' => $po->discount_total_percent ?? 0,
                    'discount_total_amount' => $po->discount_total_amount ?? 0,
                    'subtotal' => $po->subtotal ?? 0,
                    'grand_total' => $po->grand_total ?? 0,
                ];
                
                // Get source information based on PO source_type
                if ($po->source_type === 'pr_foods' || !$po->source_type) {
                    // For PR Foods, get PR numbers
                    $prNumbers = DB::table('pr_foods as pr')
                        ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
                        ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
                        ->where('poi.purchase_order_food_id', $po->id)
                        ->distinct()
                        ->pluck('pr.pr_number')
                        ->toArray();
                    
                    $contraBon->source_numbers = $prNumbers;
                    $contraBon->source_outlets = [];
                    $contraBon->source_type_display = 'PR Foods';
                } elseif ($po->source_type === 'ro_supplier') {
                    // Get RO Supplier numbers and outlet names
                    $roData = DB::table('food_floor_orders as fo')
                        ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                        ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                        ->where('poi.purchase_order_food_id', $po->id)
                        ->select('fo.order_number', 'o.nama_outlet', 'fo.id_outlet')
                        ->distinct()
                        ->get();
                    
                    $contraBon->source_numbers = $roData->pluck('order_number')->unique()->filter()->toArray();
                    $contraBon->source_outlets = $roData->pluck('nama_outlet')->unique()->filter()->toArray();
                    $contraBon->source_type_display = 'RO Supplier';
                } else {
                    $contraBon->source_numbers = [];
                    $contraBon->source_outlets = [];
                    $contraBon->source_type_display = 'Unknown';
                }
                
                // Add discount info to items
                $contraBon->items->each(function($item) {
                    if ($item->po_item_id) {
                        $poItem = PurchaseOrderFoodItem::find($item->po_item_id);
                        if ($poItem) {
                            $item->discount_percent = $poItem->discount_percent ?? 0;
                            $item->discount_amount = $poItem->discount_amount ?? 0;
                            
                            // Use item.total from database directly (already calculated correctly)
                            // Only calculate po_item_total for reference if needed, but don't use it for display
                            // The item.total from database is the source of truth
                            $item->po_item_total = $item->total; // Use database total as po_item_total
                        }
                    }
                });
            } elseif ($contraBon->source_type === 'retail_food') {
                $retailFood = \App\Models\RetailFood::find($contraBon->source_id);
                $contraBon->source_numbers = [$retailFood->retail_number ?? ''];
                $contraBon->source_outlets = [$retailFood->outlet ? $retailFood->outlet->nama_outlet : ''];
                $contraBon->source_type_display = 'Retail Food';
            } elseif ($contraBon->source_type === 'warehouse_retail_food') {
                $warehouseRetailFood = \App\Models\RetailWarehouseFood::find($contraBon->source_id);
                $contraBon->source_numbers = [$warehouseRetailFood->retail_number ?? ''];
                $warehouseName = $warehouseRetailFood->warehouse ? $warehouseRetailFood->warehouse->name : '';
                $divisionName = $warehouseRetailFood->warehouseDivision ? $warehouseRetailFood->warehouseDivision->name : '';
                $contraBon->source_outlets = [$warehouseName . ($divisionName ? ' - ' . $divisionName : '')];
                $contraBon->source_type_display = 'Warehouse Retail Food';
            } else {
                $contraBon->source_numbers = [];
                $contraBon->source_outlets = [];
                $contraBon->source_type_display = 'Unknown';
            }

            return response()->json([
                'success' => true,
                'contra_bon' => $contraBon
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error getting Contra Bon detail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail Contra Bon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending Contra Bon approvals for current user
     */
    public function getPendingApprovals(Request $request)
    {
        try {
            $user = Auth::user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $query = ContraBon::with(['supplier', 'purchaseOrder', 'retailFood', 'warehouseRetailFood', 'creator'])
                ->where('status', 'draft')
                ->orderByDesc('created_at');
            
            $pendingApprovals = [];
            
            // Finance Manager approvals (id_jabatan == 160) - Only level
            if (($user->id_jabatan == 160 && $user->status == 'A') || $isSuperadmin) {
                $financeManagerApprovals = (clone $query)
                    ->whereNull('finance_manager_approved_at')
                    ->get();
                
                foreach ($financeManagerApprovals as $cb) {
                    $pendingApprovals[] = [
                        'id' => $cb->id,
                        'number' => $cb->number,
                        'date' => $cb->date,
                        'total_amount' => $cb->total_amount,
                        'supplier' => $cb->supplier ? ['name' => $cb->supplier->name] : null,
                        'source_type' => $cb->source_type,
                        'source_type_display' => $this->getSourceTypeDisplay($cb),
                        'creator' => $cb->creator ? ['nama_lengkap' => $cb->creator->nama_lengkap] : null,
                        'approval_level' => 'finance_manager',
                        'approval_level_display' => 'Finance Manager'
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'contra_bons' => $pendingApprovals
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error loading pending Contra Bon approvals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data approval Contra Bon',
                'contra_bons' => []
            ], 500);
        }
    }
    
    private function getSourceTypeDisplay($contraBon)
    {
        if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
            return 'PR Foods';
        } elseif ($contraBon->source_type === 'retail_food') {
            return 'Retail Food';
        } elseif ($contraBon->source_type === 'warehouse_retail_food') {
            return 'Warehouse Retail Food';
        }
        return 'Unknown';
    }

    private function sendNotification($userIds, $type, $title, $message, $url) {
        $now = now();
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        \DB::table('notifications')->insert($data);
    }

    // API: Get approved Good Receives with PO, supplier, and items (with PO price)
    public function getApprovedGoodReceives()
    {
        $goodReceives = \DB::table('food_good_receives as gr')
            ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->join('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->join('pr_foods as pr', 'po.pr_food_id', '=', 'pr.id')
            ->where('gr.status', 'approved')
            ->select('gr.id', 'gr.gr_number', 'gr.receive_date', 'gr.po_id', 'po.number as po_number', 'pr.pr_number as pr_number', 's.name as supplier_name')
            ->orderByDesc('gr.receive_date')
            ->get();

        $result = [];
        foreach ($goodReceives as $gr) {
            $items = \DB::table('food_good_receive_items as gri')
                ->join('items as i', 'gri.item_id', '=', 'i.id')
                ->join('units as u', 'gri.unit_id', '=', 'u.id')
                ->join('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
                ->where('gri.good_receive_id', $gr->id)
                ->select(
                    'gri.id',
                    'gri.item_id',
                    'i.name as item_name',
                    'gri.unit_id',
                    'u.name as unit_name',
                    'gri.qty_received',
                    'poi.price as po_price'
                )
                ->get();
            $result[] = [
                'id' => $gr->id,
                'gr_number' => $gr->gr_number,
                'receive_date' => $gr->receive_date,
                'po_id' => $gr->po_id,
                'po_number' => $gr->po_number,
                'pr_number' => $gr->pr_number,
                'supplier_name' => $gr->supplier_name,
                'items' => $items,
            ];
        }
        return response()->json($result);
    }

    // API: Get PO list with approved GR for Contra Bon create
    public function getPOWithApprovedGR()
    {
        try {
            // Ambil semua gr_item_id yang sudah ada di contra bon items
            $usedGRItemIds = \DB::table('food_contra_bon_items')
                ->whereNotNull('gr_item_id')
                ->pluck('gr_item_id')
                ->toArray();

            // Get all PO with GR in one query
            $poWithGR = \DB::table('purchase_order_foods as po')
                ->join('food_good_receives as gr', 'gr.po_id', '=', 'po.id')
                ->join('suppliers as s', 'po.supplier_id', '=', 's.id')
                ->join('users as po_creator', 'po.created_by', '=', 'po_creator.id')
                ->join('users as gr_receiver', 'gr.received_by', '=', 'gr_receiver.id')
                ->select(
                    'po.id as po_id',
                    'po.number as po_number',
                    'po.date as po_date',
                    'po.source_type',
                    'po.discount_total_percent',
                    'po.discount_total_amount',
                    'po.subtotal',
                    'po.grand_total',
                    'po_creator.nama_lengkap as po_creator_name',
                    'gr.id as gr_id',
                    'gr.gr_number',
                    'gr.receive_date as gr_date',
                    'gr_receiver.nama_lengkap as gr_receiver_name',
                    's.id as supplier_id',
                    's.name as supplier_name'
                )
                ->orderByDesc('gr.receive_date')
                ->limit(500) // Limit untuk performa
                ->get();

            if ($poWithGR->isEmpty()) {
                return response()->json([]);
            }

            // Batch query: Get all GR IDs
            $grIds = $poWithGR->pluck('gr_id')->toArray();
            $poIds = $poWithGR->pluck('po_id')->unique()->toArray();
            $roSupplierPoIds = $poWithGR->where('source_type', 'ro_supplier')->pluck('po_id')->unique()->toArray();

            // Batch query: Get all items for all GRs at once
            $allItems = \DB::table('food_good_receive_items as gri')
                ->join('items as i', 'gri.item_id', '=', 'i.id')
                ->join('units as u', 'gri.unit_id', '=', 'u.id')
                ->join('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
                ->whereIn('gri.good_receive_id', $grIds)
                ->whereNotIn('gri.id', $usedGRItemIds)
                ->select(
                    'gri.good_receive_id',
                    'gri.id',
                    'gri.item_id',
                    'gri.po_item_id',
                    'i.name as item_name',
                    'gri.unit_id',
                    'u.name as unit_name',
                    'gri.qty_received',
                    'poi.price as po_price',
                    'poi.discount_percent',
                    'poi.discount_amount',
                    'poi.total as po_item_total'
                )
                ->get()
                ->groupBy('good_receive_id');

            // Batch query: Get all outlet data for RO Supplier POs at once
            $outletDataMap = [];
            if (!empty($roSupplierPoIds)) {
                $allOutletData = \DB::table('food_floor_orders as fo')
                    ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                    ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                    ->whereIn('poi.purchase_order_food_id', $roSupplierPoIds)
                    ->select('poi.purchase_order_food_id', 'o.nama_outlet')
                    ->distinct()
                    ->get();
                
                foreach ($allOutletData as $outlet) {
                    if (!isset($outletDataMap[$outlet->purchase_order_food_id])) {
                        $outletDataMap[$outlet->purchase_order_food_id] = [];
                    }
                    if ($outlet->nama_outlet) {
                        $outletDataMap[$outlet->purchase_order_food_id][] = $outlet->nama_outlet;
                    }
                }
            }

            // Build result array
            $result = [];
            foreach ($poWithGR as $row) {
                $items = $allItems->get($row->gr_id, collect());
                
                // Skip if no items available
                if ($items->isEmpty()) {
                    continue;
                }
                
                // Get source type display and outlet information
                $sourceTypeDisplay = $row->source_type === 'ro_supplier' ? 'RO Supplier' : 'PR Foods';
                $outletNames = $outletDataMap[$row->po_id] ?? [];
                
                // Get PO discount information (already in query)
                $poDiscountInfo = [
                    'discount_total_percent' => $row->discount_total_percent ?? 0,
                    'discount_total_amount' => $row->discount_total_amount ?? 0,
                    'subtotal' => $row->subtotal ?? 0,
                    'grand_total' => $row->grand_total ?? 0,
                ];
                
                $result[] = [
                    'po_id' => $row->po_id,
                    'po_number' => $row->po_number,
                    'po_date' => $row->po_date,
                    'po_creator_name' => $row->po_creator_name,
                    'gr_id' => $row->gr_id,
                    'gr_number' => $row->gr_number,
                    'gr_date' => $row->gr_date,
                    'gr_receiver_name' => $row->gr_receiver_name,
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'source_type' => $row->source_type,
                    'source_type_display' => $sourceTypeDisplay,
                    'outlet_names' => array_unique($outletNames),
                    'items' => $items->values(),
                    'po_discount_info' => $poDiscountInfo,
                ];
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in getPOWithApprovedGR: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengambil data PO/GR: ' . $e->getMessage()], 500);
        }
    }

    // API: Get Retail Food with contra bon payment method
    public function getRetailFoodContraBon()
    {
        try {
            // Get all retail foods in one query
            $retailFoods = \DB::table('retail_food as rf')
                ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
                ->join('users as creator', 'rf.created_by', '=', 'creator.id')
                ->leftJoin('tbl_data_outlet as o', 'rf.outlet_id', '=', 'o.id_outlet')
                ->leftJoin('warehouse_outlets as wo', 'rf.warehouse_outlet_id', '=', 'wo.id')
                ->where('rf.payment_method', 'contra_bon')
                ->where('rf.status', 'approved')
                ->select(
                    'rf.id as retail_food_id',
                    'rf.retail_number',
                    'rf.transaction_date',
                    'rf.total_amount',
                    'rf.notes',
                    's.id as supplier_id',
                    's.name as supplier_name',
                    'creator.nama_lengkap as creator_name',
                    'o.nama_outlet as outlet_name',
                    'wo.name as warehouse_outlet_name'
                )
                ->orderByDesc('rf.transaction_date')
                ->limit(500) // Limit untuk performa
                ->get();

            if ($retailFoods->isEmpty()) {
                return response()->json([]);
            }

            // Batch query: Get all retail food IDs
            $retailFoodIds = $retailFoods->pluck('retail_food_id')->toArray();

            // Batch query: Get all items for all retail foods at once
            $allItems = \DB::table('retail_food_items as rfi')
                ->whereIn('rfi.retail_food_id', $retailFoodIds)
                ->select(
                    'rfi.retail_food_id',
                    'rfi.id',
                    'rfi.item_name',
                    'rfi.unit as unit_name',
                    'rfi.qty',
                    'rfi.price'
                )
                ->get()
                ->groupBy('retail_food_id');

            // Build result array
            $result = [];
            foreach ($retailFoods as $row) {
                $items = $allItems->get($row->retail_food_id, collect());
                
                // Skip if no items
                if ($items->isEmpty()) {
                    continue;
                }
                
                $result[] = [
                    'retail_food_id' => $row->retail_food_id,
                    'retail_number' => $row->retail_number,
                    'transaction_date' => $row->transaction_date,
                    'total_amount' => $row->total_amount,
                    'notes' => $row->notes,
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'creator_name' => $row->creator_name,
                    'outlet_name' => $row->outlet_name,
                    'warehouse_outlet_name' => $row->warehouse_outlet_name,
                    'items' => $items->values(),
                ];
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in getRetailFoodContraBon: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengambil data Retail Food: ' . $e->getMessage()], 500);
        }
    }

    // API: Get Warehouse Retail Food with contra bon payment method
    public function getWarehouseRetailFoodContraBon()
    {
        try {
            // Get all warehouse retail foods in one query
            $warehouseRetailFoods = \DB::table('retail_warehouse_food as rwf')
                ->join('suppliers as s', 'rwf.supplier_id', '=', 's.id')
                ->join('users as creator', 'rwf.created_by', '=', 'creator.id')
                ->leftJoin('warehouses as w', 'rwf.warehouse_id', '=', 'w.id')
                ->leftJoin('warehouse_division as wd', 'rwf.warehouse_division_id', '=', 'wd.id')
                ->where('rwf.payment_method', 'contra_bon')
                ->where('rwf.status', 'approved')
                ->select(
                    'rwf.id as retail_warehouse_food_id',
                    'rwf.retail_number',
                    'rwf.transaction_date',
                    'rwf.total_amount',
                    'rwf.notes',
                    's.id as supplier_id',
                    's.name as supplier_name',
                    'creator.nama_lengkap as creator_name',
                    'w.name as warehouse_name',
                    'wd.name as warehouse_division_name'
                )
                ->orderByDesc('rwf.transaction_date')
                ->limit(500) // Limit untuk performa
                ->get();

            if ($warehouseRetailFoods->isEmpty()) {
                return response()->json([]);
            }

            // Batch query: Get all warehouse retail food IDs
            $warehouseRetailFoodIds = $warehouseRetailFoods->pluck('retail_warehouse_food_id')->toArray();

            // Batch query: Get all items for all warehouse retail foods at once
            $allItems = \DB::table('retail_warehouse_food_items as rwfi')
                ->whereIn('rwfi.retail_warehouse_food_id', $warehouseRetailFoodIds)
                ->select(
                    'rwfi.retail_warehouse_food_id',
                    'rwfi.id',
                    'rwfi.item_name',
                    'rwfi.unit as unit_name',
                    'rwfi.qty',
                    'rwfi.price'
                )
                ->get()
                ->groupBy('retail_warehouse_food_id');

            // Build result array
            $result = [];
            foreach ($warehouseRetailFoods as $row) {
                $items = $allItems->get($row->retail_warehouse_food_id, collect());
                
                // Skip if no items
                if ($items->isEmpty()) {
                    continue;
                }
                
                $result[] = [
                    'retail_warehouse_food_id' => $row->retail_warehouse_food_id,
                    'retail_number' => $row->retail_number,
                    'transaction_date' => $row->transaction_date,
                    'total_amount' => $row->total_amount,
                    'notes' => $row->notes,
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'creator_name' => $row->creator_name,
                    'warehouse_name' => $row->warehouse_name,
                    'warehouse_division_name' => $row->warehouse_division_name,
                    'items' => $items->values(),
                ];
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in getWarehouseRetailFoodContraBon: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengambil data Warehouse Retail Food: ' . $e->getMessage()], 500);
        }
    }

    public function edit($id)
    {
        $contraBon = ContraBon::with([
            'supplier',
            'purchaseOrder',
            'items.item',
            'items.unit',
            'creator',
            'approver',
            'financeManager',
            'gmFinance'
        ])->findOrFail($id);

        // Add source information and discount info for purchase orders
        if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
            $po = $contraBon->purchaseOrder;
            
            // Get PO discount information
            $contraBon->po_discount_info = [
                'discount_total_percent' => $po->discount_total_percent ?? 0,
                'discount_total_amount' => $po->discount_total_amount ?? 0,
                'subtotal' => $po->subtotal ?? 0,
                'grand_total' => $po->grand_total ?? 0,
            ];
            
            // Add discount info to items
            $contraBon->items->each(function($item) {
                if ($item->po_item_id) {
                    $poItem = PurchaseOrderFoodItem::find($item->po_item_id);
                    if ($poItem) {
                        $item->discount_percent = $poItem->discount_percent ?? 0;
                        $item->discount_amount = $poItem->discount_amount ?? 0;
                        
                        // Calculate total based on Contra Bon item quantity (not PO item quantity)
                        // Formula: (price * contra_bon_quantity) - (discount proportional to quantity)
                        $contraBonQuantity = $item->quantity ?? 0;
                        $poQuantity = $poItem->quantity ?? 1; // Avoid division by zero
                        $price = $poItem->price ?? 0;
                        
                        // Calculate subtotal for Contra Bon quantity
                        $subtotal = $price * $contraBonQuantity;
                        
                        // Calculate discount proportional to quantity ratio
                        $quantityRatio = $poQuantity > 0 ? ($contraBonQuantity / $poQuantity) : 0;
                        $discount = 0;
                        
                        if ($poItem->discount_percent > 0) {
                            // Discount percent applies to subtotal
                            $discount = $subtotal * ($poItem->discount_percent / 100);
                        } elseif ($poItem->discount_amount > 0) {
                            // Discount amount is proportional to quantity
                            $discount = $poItem->discount_amount * $quantityRatio;
                        }
                        
                        $item->po_item_total = $subtotal - $discount;
                    }
                }
            });
        }

        return inertia('ContraBon/Form', [
            'contraBon' => $contraBon
        ]);
    }

    public function destroy($id)
    {
        $contraBon = ContraBon::with('items')->findOrFail($id);
        
        DB::beginTransaction();
        try {
            // Delete contra bon items first (this will free up the gr_item_id references)
            $contraBon->items()->delete();
            
            // Delete the contra bon
            $contraBon->delete();
            
            DB::commit();
            
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Contra Bon berhasil dihapus. Item-item telah dikembalikan dan dapat digunakan lagi.'
                ]);
            }
            
            return redirect()->route('contra-bons.index')->with('success', 'Contra Bon berhasil dihapus. Item-item telah dikembalikan dan dapat digunakan lagi.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting Contra Bon', [
                'contra_bon_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus Contra Bon: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('contra-bons.index')
                ->with('error', 'Gagal menghapus Contra Bon: ' . $e->getMessage());
        }
    }
} 