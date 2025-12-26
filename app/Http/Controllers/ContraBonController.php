<?php

namespace App\Http\Controllers;

use App\Models\ContraBon;
use App\Models\ContraBonItem;
use App\Models\ContraBonSource;
use App\Models\PurchaseOrderFood;
use App\Models\PurchaseOrderFoodItem;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ContraBonController extends Controller
{
    public function index(Request $request)
    {
        $query = ContraBon::with(['supplier', 'purchaseOrder', 'retailFood', 'warehouseRetailFood', 'retailNonFood', 'creator', 'sources.purchaseOrder', 'sources.retailFood', 'sources.warehouseRetailFood', 'sources.retailNonFood'])->orderByDesc('created_at');

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
            // Handle multiple sources (new) or single source (old data - backward compatibility)
            if ($contraBon->sources && $contraBon->sources->count() > 0) {
                // New data: multiple sources
                $sourceNumbers = [];
                $sourceOutlets = [];
                $sourceTypeDisplays = [];
                
                foreach ($contraBon->sources as $source) {
                    if ($source->source_type === 'purchase_order' && $source->purchaseOrder) {
                        $po = $source->purchaseOrder;
                        
                        // AMBIL GR NUMBERS DARI SOURCE
                        if ($source->gr_id) {
                            $grNumber = DB::table('food_good_receives')
                                ->where('id', $source->gr_id)
                                ->value('gr_number');
                            if ($grNumber) {
                                $sourceNumbers[] = $grNumber;
                            }
                        }
                        
                        // AMBIL GR NUMBERS DARI ITEMS (jika ada gr_item_id)
                        $grNumbersFromItems = DB::table('food_contra_bon_items as cbi')
                            ->join('food_good_receive_items as gri', 'cbi.gr_item_id', '=', 'gri.id')
                            ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
                            ->where('cbi.contra_bon_id', $contraBon->id)
                            ->whereNotNull('cbi.gr_item_id')
                            ->distinct()
                            ->pluck('gr.gr_number')
                            ->toArray();
                        $sourceNumbers = array_merge($sourceNumbers, $grNumbersFromItems);
                        
                        // Get source information based on PO source_type
                        if ($po->source_type === 'pr_foods' || !$po->source_type) {
                            $prNumbers = DB::table('pr_foods as pr')
                                ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
                                ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
                                ->where('poi.purchase_order_food_id', $po->id)
                                ->distinct()
                                ->pluck('pr.pr_number')
                                ->toArray();
                            $sourceNumbers = array_merge($sourceNumbers, $prNumbers);
                            $sourceTypeDisplays[] = 'PR Foods';
                        } elseif ($po->source_type === 'ro_supplier') {
                            $roData = DB::table('food_floor_orders as fo')
                                ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                                ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                                ->where('poi.purchase_order_food_id', $po->id)
                                ->select('fo.order_number', 'o.nama_outlet')
                                ->distinct()
                                ->get();
                            $sourceNumbers = array_merge($sourceNumbers, $roData->pluck('order_number')->unique()->filter()->toArray());
                            $sourceOutlets = array_merge($sourceOutlets, $roData->pluck('nama_outlet')->unique()->filter()->toArray());
                            $sourceTypeDisplays[] = 'RO Supplier';
                        }
                    } elseif ($source->source_type === 'retail_food' && $source->retailFood) {
                        $retailFood = $source->retailFood;
                        $sourceNumbers[] = $retailFood->retail_number ?? '';
                        $sourceOutlets[] = $retailFood->outlet ? $retailFood->outlet->nama_outlet : '';
                        $sourceTypeDisplays[] = 'Retail Food';
                    } elseif ($source->source_type === 'warehouse_retail_food' && $source->warehouseRetailFood) {
                        $warehouseRetailFood = $source->warehouseRetailFood;
                        $sourceNumbers[] = $warehouseRetailFood->retail_number ?? '';
                        $warehouseName = $warehouseRetailFood->warehouse ? $warehouseRetailFood->warehouse->name : '';
                        $divisionName = $warehouseRetailFood->warehouseDivision ? $warehouseRetailFood->warehouseDivision->name : '';
                        $sourceOutlets[] = $warehouseName . ($divisionName ? ' - ' . $divisionName : '');
                        $sourceTypeDisplays[] = 'Warehouse Retail Food';
                    } elseif ($source->source_type === 'retail_non_food' && $source->retailNonFood) {
                        $retailNonFood = $source->retailNonFood;
                        $sourceNumbers[] = $retailNonFood->retail_number ?? '';
                        $sourceOutlets[] = $retailNonFood->outlet ? $retailNonFood->outlet->nama_outlet : '';
                        $sourceTypeDisplays[] = 'Retail Non Food';
                    }
                }
                
                $contraBon->source_numbers = array_unique(array_filter($sourceNumbers));
                $contraBon->source_outlets = array_unique(array_filter($sourceOutlets));
                $contraBon->source_type_display = implode(', ', array_unique($sourceTypeDisplays)) ?: 'Multiple Sources';
                $contraBon->source_types = array_unique($sourceTypeDisplays); // Array untuk badge
            } elseif ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                // Old data: single source (backward compatibility)
                $po = $contraBon->purchaseOrder;
                
                $sourceNumbers = [];
                
                // AMBIL GR NUMBERS DARI ITEMS (jika ada gr_item_id)
                $grNumbersFromItems = DB::table('food_contra_bon_items as cbi')
                    ->join('food_good_receive_items as gri', 'cbi.gr_item_id', '=', 'gri.id')
                    ->join('food_good_receives as gr', 'gri.good_receive_id', '=', 'gr.id')
                    ->where('cbi.contra_bon_id', $contraBon->id)
                    ->whereNotNull('cbi.gr_item_id')
                    ->distinct()
                    ->pluck('gr.gr_number')
                    ->toArray();
                $sourceNumbers = array_merge($sourceNumbers, $grNumbersFromItems);
                
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
                    
                    $sourceNumbers = array_merge($sourceNumbers, $prNumbers);
                    $contraBon->source_numbers = array_unique(array_filter($sourceNumbers));
                    $contraBon->source_outlets = []; // PR Foods tidak punya outlet
                    $contraBon->source_type_display = 'PR Foods';
                    $contraBon->source_types = ['PR Foods'];
                } elseif ($po->source_type === 'ro_supplier') {
                    // Get RO Supplier numbers and outlet names
                    $roData = DB::table('food_floor_orders as fo')
                        ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                        ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                        ->where('poi.purchase_order_food_id', $po->id)
                        ->select('fo.order_number', 'o.nama_outlet', 'fo.id_outlet')
                        ->distinct()
                        ->get();
                    
                    $roNumbers = $roData->pluck('order_number')->unique()->filter()->toArray();
                    $sourceNumbers = array_merge($sourceNumbers, $roNumbers);
                    $contraBon->source_numbers = array_unique(array_filter($sourceNumbers));
                    $contraBon->source_outlets = $roData->pluck('nama_outlet')->unique()->filter()->toArray();
                    $contraBon->source_type_display = 'RO Supplier';
                    $contraBon->source_types = ['RO Supplier'];
                } else {
                    $contraBon->source_numbers = array_unique(array_filter($sourceNumbers));
                    $contraBon->source_outlets = [];
                    $contraBon->source_type_display = 'Unknown';
                    $contraBon->source_types = ['Unknown'];
                }
            } elseif ($contraBon->source_type === 'retail_food') {
                $retailFood = \App\Models\RetailFood::find($contraBon->source_id);
                $contraBon->source_numbers = [$retailFood->retail_number ?? ''];
                $contraBon->source_outlets = [$retailFood->outlet ? $retailFood->outlet->nama_outlet : ''];
                $contraBon->source_type_display = 'Retail Food';
                $contraBon->source_types = ['Retail Food'];
            } elseif ($contraBon->source_type === 'warehouse_retail_food') {
                $warehouseRetailFood = \App\Models\RetailWarehouseFood::find($contraBon->source_id);
                $contraBon->source_numbers = [$warehouseRetailFood->retail_number ?? ''];
                $warehouseName = $warehouseRetailFood->warehouse ? $warehouseRetailFood->warehouse->name : '';
                $divisionName = $warehouseRetailFood->warehouseDivision ? $warehouseRetailFood->warehouseDivision->name : '';
                $contraBon->source_outlets = [$warehouseName . ($divisionName ? ' - ' . $divisionName : '')];
                $contraBon->source_type_display = 'Warehouse Retail Food';
                $contraBon->source_types = ['Warehouse Retail Food'];
            } else {
                $contraBon->source_numbers = [];
                $contraBon->source_outlets = [];
                $contraBon->source_type_display = 'Unknown';
                $contraBon->source_types = ['Unknown'];
            }
            
            return $contraBon;
        });
        
        return inertia('ContraBon/Index', [
            'contraBons' => $contraBons,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
        ]);
    }

    public function create(Request $request)
    {
        // Get filter parameters
        $supplierId = $request->input('supplier_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get available Retail Non Food with payment_method = contra_bon that don't have contra bon yet
        $retailNonFoodQuery = DB::table('retail_non_food as rnf')
            ->leftJoin('suppliers as s', 'rnf.supplier_id', '=', 's.id')
            ->leftJoin('tbl_data_outlet as o', 'rnf.outlet_id', '=', 'o.id_outlet')
            ->where('rnf.payment_method', 'contra_bon')
            ->where('rnf.status', 'approved')
            ->whereNull('rnf.deleted_at')
            ->select(
                'rnf.id',
                'rnf.retail_number',
                'rnf.transaction_date',
                'rnf.total_amount',
                'rnf.supplier_id',
                'rnf.outlet_id',
                'rnf.notes',
                's.name as supplier_name',
                'o.nama_outlet as outlet_name'
            );

        // Apply filters
        if ($supplierId) {
            $retailNonFoodQuery->where('rnf.supplier_id', $supplierId);
        }
        if ($dateFrom) {
            $retailNonFoodQuery->whereDate('rnf.transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $retailNonFoodQuery->whereDate('rnf.transaction_date', '<=', $dateTo);
        }

        $allRetailNonFoods = $retailNonFoodQuery->orderBy('rnf.transaction_date', 'desc')
            ->limit(100)
            ->get();

        // Filter Retail Non Food that don't have contra bon yet
        // Check if retail_non_food_id exists in food_contra_bon_sources
        $availableRetailNonFoods = $allRetailNonFoods->filter(function($rnf) {
            $hasContraBon = DB::table('food_contra_bon_sources')
                ->where('source_type', 'retail_non_food')
                ->where('source_id', $rnf->id)
                ->exists();
            return !$hasContraBon;
        })->take(50)->values();

        // Get suppliers for filter dropdown
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return inertia('ContraBon/Form', [
            'availableRetailNonFoods' => $availableRetailNonFoods,
            'suppliers' => $suppliers,
            'filters' => $request->only(['supplier_id', 'date_from', 'date_to'])
        ]);
    }

    public function store(Request $request)
    {
        $sourceType = $request->input('source_type', 'purchase_order');
        
        // Debug: Log source_type untuk memastikan deteksi benar
        \Log::info('Contra Bon store - source_type detection', [
            'source_type' => $sourceType,
            'source_type_raw' => $request->input('source_type'),
            'po_id' => $request->input('po_id'),
            'gr_id' => $request->input('gr_id'),
            'source_id' => $request->input('source_id'),
        ]);
        
        $rules = [
            'date' => 'required|date',
            'items' => 'required|array',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'source_type' => 'nullable|in:purchase_order,retail_food,warehouse_retail_food,retail_non_food',
        ];
        
        // Conditional validation based on source_type
        if ($sourceType === 'purchase_order') {
            $rules['po_id'] = 'required|exists:purchase_order_foods,id';
            $rules['gr_id'] = 'required|exists:food_good_receives,id';
            // Untuk purchase_order, item_id dan unit_id harus ada (bisa dari item atau gr_item_id)
            $rules['items.*.item_id'] = 'nullable|exists:items,id';
            $rules['items.*.unit_id'] = 'nullable|exists:units,id';
            $rules['items.*.gr_item_id'] = 'nullable|exists:food_good_receive_items,id';
        } else {
            // For retail_food, warehouse_retail_food, and retail_non_food:
            // - po_id dan gr_id tidak required
            // - source_id required
            // - item_id dan unit_id tidak required jika ada item_name dan unit_name
            $rules['source_id'] = 'required';
            // Item_id dan unit_id tidak required, bisa dicari dari item_name dan unit_name
            // Tapi jika item_id/unit_id ada, harus valid
            $rules['items.*.item_id'] = 'nullable|exists:items,id';
            $rules['items.*.unit_id'] = 'nullable|exists:units,id';
            // Item_name dan unit_name required jika item_id/unit_id tidak ada
            foreach ($request->input('items', []) as $index => $item) {
                $itemId = $item['item_id'] ?? null;
                $unitId = $item['unit_id'] ?? null;
                // Convert string "null" atau empty string menjadi null
                if ($itemId === 'null' || $itemId === '' || $itemId === null) {
                    $rules["items.{$index}.item_name"] = 'required|string';
                }
                if ($unitId === 'null' || $unitId === '' || $unitId === null) {
                    $rules["items.{$index}.unit_name"] = 'required|string';
                }
            }
        }
        
        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Contra Bon validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->except(['image']),
                'source_type' => $sourceType,
                'source_id' => $request->input('source_id')
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
                // Convert source_id to integer if it's a string
                $sourceIdInt = is_numeric($sourceId) ? (int)$sourceId : $sourceId;
                $retailFood = \App\Models\RetailFood::findOrFail($sourceIdInt);
                $supplierId = $retailFood->supplier_id;
                $poId = null;
                $grId = null;
            } elseif ($sourceType === 'warehouse_retail_food') {
                // Convert source_id to integer if it's a string
                $sourceIdInt = is_numeric($sourceId) ? (int)$sourceId : $sourceId;
                $warehouseRetailFood = \App\Models\RetailWarehouseFood::findOrFail($sourceIdInt);
                $supplierId = $warehouseRetailFood->supplier_id;
                $poId = null;
                $grId = null;
            } elseif ($sourceType === 'retail_non_food') {
                // Convert source_id to integer if it's a string
                $sourceIdInt = is_numeric($sourceId) ? (int)$sourceId : $sourceId;
                $retailNonFood = \App\Models\RetailNonFood::findOrFail($sourceIdInt);
                $supplierId = $retailNonFood->supplier_id;
                $poId = null;
                $grId = null;
            }
            
            // Generate contra bon number
            $dateStr = date('Ymd', strtotime($request->date));
            $countToday = ContraBon::whereDate('date', $request->date)->count();
            $number = 'CB-' . $dateStr . '-' . str_pad($countToday + 1, 4, '0', STR_PAD_LEFT);

            // Calculate total amount with discount item
            $subtotal = collect($request->items)->sum(function ($item) {
                $quantity = floatval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $subtotalItem = $quantity * $price;
                
                // Apply discount item
                $discountPercent = floatval($item['discount_percent'] ?? 0);
                $discountAmount = floatval($item['discount_amount'] ?? 0);
                
                if ($discountPercent > 0) {
                    $discount = $subtotalItem * ($discountPercent / 100);
                } else if ($discountAmount > 0) {
                    $discount = $discountAmount;
                } else {
                    $discount = 0;
                }
                
                return $subtotalItem - $discount;
            });
            
            // Apply discount total
            $discountTotalPercent = floatval($request->discount_total_percent ?? 0);
            $discountTotalAmount = floatval($request->discount_total_amount ?? 0);
            
            $discountTotal = 0;
            if ($discountTotalPercent > 0) {
                $discountTotal = $subtotal * ($discountTotalPercent / 100);
            } else if ($discountTotalAmount > 0) {
                $discountTotal = $discountTotalAmount;
            }
            
            $totalAmount = $subtotal - $discountTotal;

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('contra_bon_images', 'public');
            }

            // Convert source_id to integer if needed (for retail_food, warehouse_retail_food, and retail_non_food)
            // For purchase_order, source_id should be null because we have po_id and gr_id separately
            $sourceIdForSave = null;
            if ($sourceType === 'retail_food' || $sourceType === 'warehouse_retail_food' || $sourceType === 'retail_non_food') {
                $sourceIdForSave = is_numeric($sourceId) ? (int)$sourceId : $sourceId;
            }
            // For purchase_order, source_id is not needed (we use po_id and gr_id)
            
            // Prepare data for contra bon creation
            $contraBonData = [
                'number' => $number,
                'date' => $request->date,
                'supplier_id' => $supplierId,
                'total_amount' => $totalAmount,
                'discount_total_percent' => $discountTotalPercent,
                'discount_total_amount' => $discountTotalAmount,
                'notes' => $request->notes,
                'image_path' => $imagePath,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'supplier_invoice_number' => $request->supplier_invoice_number,
                'source_type' => $sourceType,
            ];
            
            // Only include source_id if it's not null (for retail_food and warehouse_retail_food)
            if ($sourceIdForSave !== null) {
                $contraBonData['source_id'] = $sourceIdForSave;
            }
            
            // Only include po_id if it's not null (for purchase_order)
            if ($poId !== null) {
                $contraBonData['po_id'] = $poId;
            }
            
            // Create contra bon
            $contraBon = ContraBon::create($contraBonData);
            
            // Save multiple sources if provided
            $sources = $request->input('sources', []);
            if (empty($sources) && $request->input('source_type')) {
                // Backward compatibility: create source from single source_type and source_id
                // For purchase_order, use po_id as source_id (or null if not available)
                $sourceIdForPivot = null;
                if ($sourceType === 'purchase_order') {
                    // For purchase_order, source_id in pivot table should be po_id
                    $sourceIdForPivot = $poId;
                } else {
                    $sourceIdForPivot = $sourceIdForSave;
                }
                
                $sources = [[
                    'source_type' => $sourceType,
                    'source_id' => $sourceIdForPivot,
                    'po_id' => $poId,
                    'gr_id' => $grId,
                ]];
            }
            
            // Save all sources to pivot table
            foreach ($sources as $source) {
                $sourceTypeForSource = $source['source_type'] ?? $sourceType;
                $sourceIdForSource = $source['source_id'] ?? null;
                
                // Handle string 'undefined' from frontend (FormData converts undefined to string 'undefined')
                if ($sourceIdForSource === 'undefined' || $sourceIdForSource === 'null' || $sourceIdForSource === '') {
                    $sourceIdForSource = null;
                }
                
                // For purchase_order, use po_id as source_id if not provided or invalid
                if ($sourceTypeForSource === 'purchase_order') {
                    if ($sourceIdForSource === null || $sourceIdForSource === '' || $sourceIdForSource === 'null' || $sourceIdForSource === 'undefined') {
                        $sourceIdForSource = $source['po_id'] ?? $poId;
                    }
                }
                
                // Convert source_id to integer if needed
                if ($sourceIdForSource !== null && $sourceIdForSource !== '' && $sourceIdForSource !== 'null' && $sourceIdForSource !== 'undefined') {
                    if (is_numeric($sourceIdForSource)) {
                        $sourceIdForSource = (int)$sourceIdForSource;
                    } else {
                        // If not numeric and not null, log warning and set to null
                        \Log::warning('Invalid source_id value for Contra Bon source', [
                            'contra_bon_id' => $contraBon->id,
                            'source_type' => $sourceTypeForSource,
                            'source_id' => $sourceIdForSource,
                            'po_id' => $source['po_id'] ?? null,
                        ]);
                        // For purchase_order, fallback to po_id
                        if ($sourceTypeForSource === 'purchase_order') {
                            $sourceIdForSource = $source['po_id'] ?? $poId;
                        } else {
                            $sourceIdForSource = null;
                        }
                    }
                } else {
                    $sourceIdForSource = null;
                }
                
                // Final check: for purchase_order, ensure source_id is set to po_id if still null
                if ($sourceTypeForSource === 'purchase_order' && $sourceIdForSource === null) {
                    $sourceIdForSource = $source['po_id'] ?? $poId;
                }
                
                ContraBonSource::create([
                    'contra_bon_id' => $contraBon->id,
                    'source_type' => $sourceTypeForSource,
                    'source_id' => $sourceIdForSource,
                    'po_id' => $source['po_id'] ?? ($sourceTypeForSource === 'purchase_order' ? $poId : null),
                    'gr_id' => $source['gr_id'] ?? ($sourceTypeForSource === 'purchase_order' ? $grId : null),
                ]);
            }
            
            // Create contra bon items
            foreach ($request->items as $item) {
                // Handle string "null" dari FormData (FormData mengkonversi null menjadi string "null")
                // Untuk retail food, item_id dan unit_id bisa null
                $itemId = $item['item_id'] ?? null;
                $unitId = $item['unit_id'] ?? null;
                
                // Convert string "null" atau empty string menjadi null
                // FormData mengkonversi null menjadi string "null"
                $originalItemId = $itemId;
                $originalUnitId = $unitId;
                
                if ($itemId === 'null' || $itemId === '' || $itemId === null || (is_string($itemId) && strtolower(trim($itemId)) === 'null')) {
                    $itemId = null;
                } else {
                    $itemId = is_numeric($itemId) ? (int)$itemId : $itemId;
                }
                
                if ($unitId === 'null' || $unitId === '' || $unitId === null || (is_string($unitId) && strtolower(trim($unitId)) === 'null')) {
                    $unitId = null;
                } else {
                    $unitId = is_numeric($unitId) ? (int)$unitId : $unitId;
                }
                
                // Fix: Jika dari PO-GR dan item_id/unit_id null tapi ada gr_item_id, ambil dari GR item
                // Handle gr_item_id yang mungkin string "null" atau empty
                $grItemId = $item['gr_item_id'] ?? null;
                if ($grItemId === 'null' || $grItemId === '' || $grItemId === null) {
                    $grItemId = null;
                } else {
                    $grItemId = is_numeric($grItemId) ? (int)$grItemId : $grItemId;
                }
                
                if ($sourceType === 'purchase_order' && (!$itemId || !$unitId) && $grItemId) {
                    $grItem = DB::table('food_good_receive_items as gri')
                        ->where('gri.id', $grItemId)
                        ->select('gri.item_id', 'gri.unit_id')
                        ->first();
                    
                    if ($grItem) {
                        if (!$itemId && $grItem->item_id) {
                            $itemId = $grItem->item_id;
                        }
                        if (!$unitId && $grItem->unit_id) {
                            $unitId = $grItem->unit_id;
                        }
                    }
                }
                
                // Jika tidak ada item_id tapi ada item_name, WAJIB cari berdasarkan item_name
                // Ini berlaku untuk semua source type yang menggunakan item_name
                if (!$itemId && isset($item['item_name']) && !empty(trim($item['item_name']))) {
                    $itemName = trim($item['item_name']);
                    
                    // Try exact match first
                    $foundItem = \DB::table('items')->where('name', $itemName)->first();
                    // If not found, try case-insensitive match
                    if (!$foundItem) {
                        $foundItem = \DB::table('items')->whereRaw('LOWER(name) = LOWER(?)', [$itemName])->first();
                    }
                    // If still not found, try partial match (contains)
                    if (!$foundItem) {
                        $foundItem = \DB::table('items')->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $itemName . '%'])->first();
                    }
                    if ($foundItem) {
                        $itemId = $foundItem->id;
                    } else {
                        \Log::error('Item not found by name', [
                            'item_name' => $itemName, 
                            'source_type' => $sourceType,
                            'item_source_type' => $item['source_type'] ?? 'none',
                            'all_items_sample' => \DB::table('items')->select('name')->limit(5)->pluck('name')->toArray()
                        ]);
                        throw new \Exception("Item dengan nama '{$itemName}' tidak ditemukan di database. Silakan pastikan item sudah terdaftar di master item.");
                    }
                }
                
                // Jika tidak ada unit_id tapi ada unit_name, WAJIB cari berdasarkan unit_name
                // Ini berlaku untuk semua source type yang menggunakan unit_name
                if (!$unitId && isset($item['unit_name']) && !empty(trim($item['unit_name']))) {
                    $unitName = trim($item['unit_name']);
                    
                    // Try exact match first
                    $foundUnit = \DB::table('units')->where('name', $unitName)->first();
                    // If not found, try case-insensitive match
                    if (!$foundUnit) {
                        $foundUnit = \DB::table('units')->whereRaw('LOWER(name) = LOWER(?)', [$unitName])->first();
                    }
                    // If still not found, try partial match (contains)
                    if (!$foundUnit) {
                        $foundUnit = \DB::table('units')->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $unitName . '%'])->first();
                    }
                    if ($foundUnit) {
                        $unitId = $foundUnit->id;
                    } else {
                        \Log::error('Unit not found by name', [
                            'unit_name' => $unitName, 
                            'source_type' => $sourceType,
                            'item_source_type' => $item['source_type'] ?? 'none',
                            'all_units_sample' => \DB::table('units')->select('name')->limit(5)->pluck('name')->toArray()
                        ]);
                        throw new \Exception("Unit dengan nama '{$unitName}' tidak ditemukan di database. Silakan pastikan unit sudah terdaftar di master unit.");
                    }
                }
                
                // Validasi: item_id dan unit_id HARUS terisi
                if (!$itemId) {
                    throw new \Exception("Item ID tidak boleh kosong. Pastikan item_name terisi dan item sudah terdaftar di master item.");
                }
                if (!$unitId) {
                    throw new \Exception("Unit ID tidak boleh kosong. Pastikan unit_name terisi dan unit sudah terdaftar di master unit.");
                }
                
                // Calculate item total with discount
                $quantity = floatval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $subtotalItem = $quantity * $price;
                
                $discountPercent = floatval($item['discount_percent'] ?? 0);
                $discountAmount = floatval($item['discount_amount'] ?? 0);
                
                $discount = 0;
                if ($discountPercent > 0) {
                    $discount = $subtotalItem * ($discountPercent / 100);
                } else if ($discountAmount > 0) {
                    $discount = $discountAmount;
                }
                
                $itemTotal = $subtotalItem - $discount;
                
                // Prepare data for contra bon item
                $contraBonItemData = [
                    'contra_bon_id' => $contraBon->id,
                    'item_id' => $itemId,
                    'unit_id' => $unitId,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'total' => $itemTotal,
                    'notes' => $item['notes'] ?? null
                ];
                
                // Only include optional fields if they are not null
                $poItemId = $item['po_item_id'] ?? null;
                if ($poItemId !== null && $poItemId !== 'null' && $poItemId !== '') {
                    $contraBonItemData['po_item_id'] = is_numeric($poItemId) ? (int)$poItemId : $poItemId;
                }
                
                $grItemId = $item['gr_item_id'] ?? null;
                if ($grItemId !== null && $grItemId !== 'null' && $grItemId !== '') {
                    $contraBonItemData['gr_item_id'] = is_numeric($grItemId) ? (int)$grItemId : $grItemId;
                }
                
                $retailFoodItemId = $item['retail_food_item_id'] ?? null;
                if ($retailFoodItemId !== null && $retailFoodItemId !== 'null' && $retailFoodItemId !== '') {
                    $contraBonItemData['retail_food_item_id'] = is_numeric($retailFoodItemId) ? (int)$retailFoodItemId : $retailFoodItemId;
                }
                
                $warehouseRetailFoodItemId = $item['warehouse_retail_food_item_id'] ?? null;
                if ($warehouseRetailFoodItemId !== null && $warehouseRetailFoodItemId !== 'null' && $warehouseRetailFoodItemId !== '') {
                    $contraBonItemData['warehouse_retail_food_item_id'] = is_numeric($warehouseRetailFoodItemId) ? (int)$warehouseRetailFoodItemId : $warehouseRetailFoodItemId;
                }
                
                // Create contra bon item
                ContraBonItem::create($contraBonItemData);
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
                'source_type' => $sourceType ?? 'unknown',
                'source_id' => $sourceId ?? 'unknown',
                'request_data' => [
                    'source_type' => $request->input('source_type'),
                    'source_id' => $request->input('source_id'),
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
            'sources',
            'sources.purchaseOrder',
            'sources.retailFood',
            'sources.warehouseRetailFood',
            'sources.retailNonFood',
            'items.item',
            'items.unit',
            'items.grItem' => function($query) {
                $query->with(['item', 'unit']);
            },
            'creator',
            'approver',
            'financeManager',
            'gmFinance'
        ])->findOrFail($id);

        // Handle multiple sources (new) or single source (old data - backward compatibility)
        if ($contraBon->sources && $contraBon->sources->count() > 0) {
            // New data: multiple sources
            $sourceNumbers = [];
            $sourceOutlets = [];
            $sourceTypeDisplays = [];
            
            foreach ($contraBon->sources as $source) {
                if ($source->source_type === 'purchase_order' && $source->purchaseOrder) {
                    $po = $source->purchaseOrder;
                    
                    // Get PO discount information (use first PO for discount info)
                    if (!$contraBon->po_discount_info) {
                        $contraBon->po_discount_info = [
                            'discount_total_percent' => $po->discount_total_percent ?? 0,
                            'discount_total_amount' => $po->discount_total_amount ?? 0,
                            'subtotal' => $po->subtotal ?? 0,
                            'grand_total' => $po->grand_total ?? 0,
                        ];
                    }
                    
                    // Get source information based on PO source_type
                    if ($po->source_type === 'pr_foods' || !$po->source_type) {
                        $prNumbers = DB::table('pr_foods as pr')
                            ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
                            ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
                            ->where('poi.purchase_order_food_id', $po->id)
                            ->distinct()
                            ->pluck('pr.pr_number')
                            ->toArray();
                        $sourceNumbers = array_merge($sourceNumbers, $prNumbers);
                        $sourceTypeDisplays[] = 'PR Foods';
                    } elseif ($po->source_type === 'ro_supplier') {
                        $roData = DB::table('food_floor_orders as fo')
                            ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                            ->where('poi.purchase_order_food_id', $po->id)
                            ->select('fo.order_number', 'o.nama_outlet')
                            ->distinct()
                            ->get();
                        $sourceNumbers = array_merge($sourceNumbers, $roData->pluck('order_number')->unique()->filter()->toArray());
                        $sourceOutlets = array_merge($sourceOutlets, $roData->pluck('nama_outlet')->unique()->filter()->toArray());
                        $sourceTypeDisplays[] = 'RO Supplier';
                    }
                } elseif ($source->source_type === 'retail_food' && $source->retailFood) {
                    $retailFood = $source->retailFood;
                    $sourceNumbers[] = $retailFood->retail_number ?? '';
                    $sourceOutlets[] = $retailFood->outlet ? $retailFood->outlet->nama_outlet : '';
                    $sourceTypeDisplays[] = 'Retail Food';
                } elseif ($source->source_type === 'warehouse_retail_food' && $source->warehouseRetailFood) {
                    $warehouseRetailFood = $source->warehouseRetailFood;
                    $sourceNumbers[] = $warehouseRetailFood->retail_number ?? '';
                    $warehouseName = $warehouseRetailFood->warehouse ? $warehouseRetailFood->warehouse->name : '';
                    $divisionName = $warehouseRetailFood->warehouseDivision ? $warehouseRetailFood->warehouseDivision->name : '';
                    $sourceOutlets[] = $warehouseName . ($divisionName ? ' - ' . $divisionName : '');
                    $sourceTypeDisplays[] = 'Warehouse Retail Food';
                } elseif ($source->source_type === 'retail_non_food' && $source->retailNonFood) {
                    $retailNonFood = $source->retailNonFood;
                    $sourceNumbers[] = $retailNonFood->retail_number ?? '';
                    $sourceOutlets[] = $retailNonFood->outlet ? $retailNonFood->outlet->nama_outlet : '';
                    $sourceTypeDisplays[] = 'Retail Non Food';
                }
            }
            
            $contraBon->source_numbers = array_unique(array_filter($sourceNumbers));
            $contraBon->source_outlets = array_unique(array_filter($sourceOutlets));
            $contraBon->source_type_display = implode(', ', array_unique($sourceTypeDisplays)) ?: 'Multiple Sources';
            $contraBon->source_types = array_unique($sourceTypeDisplays);
        } elseif ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
            // Old data: single source (backward compatibility)
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
                $contraBon->source_types = ['PR Foods'];
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
                $contraBon->source_types = ['RO Supplier'];
            } else {
                $contraBon->source_numbers = [];
                $contraBon->source_outlets = [];
                $contraBon->source_type_display = 'Unknown';
                $contraBon->source_types = ['Unknown'];
            }
            
            // Add discount info to items and fix missing item/unit from GR
            $contraBon->items->each(function($item) {
                // Fix: Jika item_id atau unit_id null tapi ada gr_item_id, ambil dari GR item
                if ((!$item->item_id || !$item->unit_id) && $item->gr_item_id) {
                    $grItem = DB::table('food_good_receive_items as gri')
                        ->join('items as i', 'gri.item_id', '=', 'i.id')
                        ->join('units as u', 'gri.unit_id', '=', 'u.id')
                        ->where('gri.id', $item->gr_item_id)
                        ->select('i.id as item_id', 'i.name as item_name', 'u.id as unit_id', 'u.name as unit_name')
                        ->first();
                    
                    if ($grItem) {
                        // Set item relationship jika belum ada
                        if (!$item->item_id && $grItem->item_id) {
                            $item->item_id = $grItem->item_id;
                            $item->setRelation('item', \App\Models\Item::find($grItem->item_id));
                        }
                        
                        // Set unit relationship jika belum ada
                        if (!$item->unit_id && $grItem->unit_id) {
                            $item->unit_id = $grItem->unit_id;
                            $item->setRelation('unit', \App\Models\Unit::find($grItem->unit_id));
                        }
                        
                        // Tambahkan item_name dan unit_name sebagai attribute langsung
                        $item->item_name = $grItem->item_name;
                        $item->unit_name = $grItem->unit_name;
                    }
                }
                
                // Pastikan item_name dan unit_name selalu ada (dari relasi jika belum ada)
                if (!$item->item_name) {
                    if ($item->item) {
                        $item->item_name = $item->item->name;
                    } elseif ($item->grItem && $item->grItem->item) {
                        $item->item_name = $item->grItem->item->name;
                    }
                }
                if (!$item->unit_name) {
                    if ($item->unit) {
                        $item->unit_name = $item->unit->name;
                    } elseif ($item->grItem && $item->grItem->unit) {
                        $item->unit_name = $item->grItem->unit->name;
                    }
                }
                
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
            $contraBon->source_types = ['Retail Food'];
        } elseif ($contraBon->source_type === 'warehouse_retail_food') {
            $warehouseRetailFood = \App\Models\RetailWarehouseFood::find($contraBon->source_id);
            $contraBon->source_numbers = [$warehouseRetailFood->retail_number ?? ''];
            $warehouseName = $warehouseRetailFood->warehouse ? $warehouseRetailFood->warehouse->name : '';
            $divisionName = $warehouseRetailFood->warehouseDivision ? $warehouseRetailFood->warehouseDivision->name : '';
            $contraBon->source_outlets = [$warehouseName . ($divisionName ? ' - ' . $divisionName : '')];
            $contraBon->source_type_display = 'Warehouse Retail Food';
            $contraBon->source_types = ['Warehouse Retail Food'];
        } elseif ($contraBon->source_type === 'retail_non_food') {
            $retailNonFood = \App\Models\RetailNonFood::find($contraBon->source_id);
            $contraBon->source_numbers = [$retailNonFood->retail_number ?? ''];
            $contraBon->source_outlets = [$retailNonFood->outlet ? $retailNonFood->outlet->nama_outlet : ''];
            $contraBon->source_type_display = 'Retail Non Food';
            $contraBon->source_types = ['Retail Non Food'];
        } else {
            $contraBon->source_numbers = [];
            $contraBon->source_outlets = [];
            $contraBon->source_type_display = 'Unknown';
            $contraBon->source_types = ['Unknown'];
        }
        
        // Add discount info to items (for purchase_order sources)
        $hasPurchaseOrderSource = $contraBon->source_type === 'purchase_order' || 
                                  ($contraBon->sources && $contraBon->sources->where('source_type', 'purchase_order')->count() > 0);
        
        if ($hasPurchaseOrderSource) {
            // Add discount info to items and fix missing item/unit from GR
            $contraBon->items->each(function($item) {
                // Fix: Jika item_id atau unit_id null tapi ada gr_item_id, ambil dari GR item
                if ((!$item->item_id || !$item->unit_id) && $item->gr_item_id) {
                    $grItem = DB::table('food_good_receive_items as gri')
                        ->join('items as i', 'gri.item_id', '=', 'i.id')
                        ->join('units as u', 'gri.unit_id', '=', 'u.id')
                        ->where('gri.id', $item->gr_item_id)
                        ->select('i.id as item_id', 'i.name as item_name', 'u.id as unit_id', 'u.name as unit_name')
                        ->first();
                    
                    if ($grItem) {
                        // Set item relationship jika belum ada
                        if (!$item->item_id && $grItem->item_id) {
                            $item->item_id = $grItem->item_id;
                            $item->setRelation('item', \App\Models\Item::find($grItem->item_id));
                        }
                        
                        // Set unit relationship jika belum ada
                        if (!$item->unit_id && $grItem->unit_id) {
                            $item->unit_id = $grItem->unit_id;
                            $item->setRelation('unit', \App\Models\Unit::find($grItem->unit_id));
                        }
                        
                        // Tambahkan item_name dan unit_name sebagai attribute langsung
                        $item->item_name = $grItem->item_name;
                        $item->unit_name = $grItem->unit_name;
                    }
                }
                
                // Pastikan item_name dan unit_name selalu ada (dari relasi jika belum ada)
                if (!$item->item_name) {
                    if ($item->item) {
                        $item->item_name = $item->item->name;
                    } elseif ($item->grItem && $item->grItem->item) {
                        $item->item_name = $item->grItem->item->name;
                    }
                }
                if (!$item->unit_name) {
                    if ($item->unit) {
                        $item->unit_name = $item->unit->name;
                    } elseif ($item->grItem && $item->grItem->unit) {
                        $item->unit_name = $item->grItem->unit->name;
                    }
                }
                
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

        return inertia('ContraBon/Show', [
            'contraBon' => $contraBon,
            'user' => auth()->user()
        ]);
    }

    public function approve(Request $request, $id)
    {
        // Support both old format (approved boolean) and new format (comment only for approval)
        $approved = $request->has('approved') ? $request->approved : true;
        $note = $request->note ?? $request->comment ?? null;

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
                'finance_manager_note' => $note,
                'status' => $approved ? 'approved' : 'rejected'
            ]);

            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'activity_type' => $approved ? 'approve' : 'reject',
                'module' => 'contra_bon',
                'description' => ($approved ? 'Approve' : 'Reject') . ' Contra Bon (Finance Manager): ' . $contraBon->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $contraBon->fresh()->toArray(),
            ]);

            // Send notification to creator if approved
            if ($approved && $contraBon->created_by) {
                \App\Models\Notification::create([
                    'user_id' => $contraBon->created_by,
                    'type' => 'contra_bon_approval',
                    'title' => 'Contra Bon Disetujui',
                    'message' => "Contra Bon {$contraBon->number} telah disetujui oleh Finance Manager.",
                ]);
            }

            $msg = 'Contra Bon berhasil ' . ($approved ? 'diapprove' : 'direject');
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
     * Reject Contra Bon (for API)
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string',
            'comment' => 'nullable|string'
        ]);

        // Use reject reason or comment
        $note = $request->reason ?? $request->comment ?? null;

        // Call approve with approved=false
        $request->merge(['approved' => false, 'note' => $note]);
        return $this->approve($request, $id);
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
                'sources',
                'sources.purchaseOrder',
                'sources.retailFood',
                'sources.warehouseRetailFood',
                'items.item',
                'items.unit',
                'items.grItem.item',
                'items.grItem.unit',
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
                    $contraBon->source_types = ['PR Foods'];
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
                    $contraBon->source_types = ['RO Supplier'];
                } else {
                    $contraBon->source_numbers = [];
                    $contraBon->source_outlets = [];
                    $contraBon->source_type_display = 'Unknown';
                    $contraBon->source_types = ['Unknown'];
                }
                
                // Add discount info to items and fix missing item/unit from GR
                $contraBon->items->each(function($item) {
                    // Fix: Jika item_id atau unit_id null tapi ada gr_item_id, ambil dari GR item
                    if ((!$item->item_id || !$item->unit_id) && $item->gr_item_id) {
                        $grItem = DB::table('food_good_receive_items as gri')
                            ->join('items as i', 'gri.item_id', '=', 'i.id')
                            ->join('units as u', 'gri.unit_id', '=', 'u.id')
                            ->where('gri.id', $item->gr_item_id)
                            ->select('i.id as item_id', 'i.name as item_name', 'u.id as unit_id', 'u.name as unit_name')
                            ->first();
                        
                        if ($grItem) {
                            // Set item relationship jika belum ada
                            if (!$item->item_id && $grItem->item_id) {
                                $item->item_id = $grItem->item_id;
                                $item->setRelation('item', \App\Models\Item::find($grItem->item_id));
                            }
                            
                            // Set unit relationship jika belum ada
                            if (!$item->unit_id && $grItem->unit_id) {
                                $item->unit_id = $grItem->unit_id;
                                $item->setRelation('unit', \App\Models\Unit::find($grItem->unit_id));
                            }
                            
                            // Tambahkan item_name dan unit_name sebagai attribute langsung
                            $item->item_name = $grItem->item_name;
                            $item->unit_name = $grItem->unit_name;
                        }
                    }
                    
                    // Pastikan item_name dan unit_name selalu ada (dari relasi jika belum ada)
                    if (!$item->item_name) {
                        if ($item->item) {
                            $item->item_name = $item->item->name;
                        } elseif ($item->grItem && $item->grItem->item) {
                            $item->item_name = $item->grItem->item->name;
                        }
                    }
                    if (!$item->unit_name) {
                        if ($item->unit) {
                            $item->unit_name = $item->unit->name;
                        } elseif ($item->grItem && $item->grItem->unit) {
                            $item->unit_name = $item->grItem->unit->name;
                        }
                    }
                    
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
                    
                    // Add subtotal as alias of total for backward compatibility (for all items)
                    // total = (quantity * price) - discount
                    $item->subtotal = $item->total ?? 0;
                });
            } elseif ($contraBon->source_type === 'retail_food') {
                $retailFood = \App\Models\RetailFood::find($contraBon->source_id);
                $contraBon->source_numbers = [$retailFood->retail_number ?? ''];
                $contraBon->source_outlets = [$retailFood->outlet ? $retailFood->outlet->nama_outlet : ''];
                $contraBon->source_type_display = 'Retail Food';
                $contraBon->source_types = ['Retail Food'];
            } elseif ($contraBon->source_type === 'warehouse_retail_food') {
                $warehouseRetailFood = \App\Models\RetailWarehouseFood::find($contraBon->source_id);
                $contraBon->source_numbers = [$warehouseRetailFood->retail_number ?? ''];
                $warehouseName = $warehouseRetailFood->warehouse ? $warehouseRetailFood->warehouse->name : '';
                $divisionName = $warehouseRetailFood->warehouseDivision ? $warehouseRetailFood->warehouseDivision->name : '';
                $contraBon->source_outlets = [$warehouseName . ($divisionName ? ' - ' . $divisionName : '')];
                $contraBon->source_type_display = 'Warehouse Retail Food';
                $contraBon->source_types = ['Warehouse Retail Food'];
            } else {
                $contraBon->source_numbers = [];
                $contraBon->source_outlets = [];
                $contraBon->source_type_display = 'Unknown';
                $contraBon->source_types = ['Unknown'];
            }
            
            // Use getSourceTypes for multi-source support (overrides single source logic above)
            $contraBon->source_types = $this->getSourceTypes($contraBon);
            
            // Ensure all items have subtotal alias (for all source types)
            $contraBon->items->each(function($item) {
                // Add subtotal as alias of total for backward compatibility
                // total = (quantity * price) - discount
                $item->subtotal = $item->total ?? 0;
            });
            
            // Build approval flows from legacy approval system
            $approvalFlows = [];
            
            // Finance Manager approval (level 1) - id_jabatan = 160
            // Get Finance Manager user (first active user with id_jabatan = 160)
            $financeManager = \App\Models\User::where('id_jabatan', 160)
                ->where('status', 'A')
                ->first();
            
            if ($financeManager) {
                $fmStatus = 'PENDING';
                $fmApprovedAt = null;
                $fmRejectedAt = null;
                
                if ($contraBon->finance_manager_approved_at) {
                    $fmStatus = 'APPROVED';
                    $fmApprovedAt = $contraBon->finance_manager_approved_at;
                } elseif ($contraBon->status === 'rejected' && $contraBon->finance_manager_approved_by) {
                    $fmStatus = 'REJECTED';
                    $fmRejectedAt = $contraBon->finance_manager_approved_at; // Use approved_at as rejected_at
                }
                
                $approvalFlows[] = [
                    'id' => 'fm_' . $contraBon->id,
                    'approval_level' => 1,
                    'approver_id' => $financeManager->id,
                    'approver' => [
                        'id' => $financeManager->id,
                        'nama_lengkap' => $financeManager->nama_lengkap,
                        'email' => $financeManager->email,
                    ],
                    'status' => $fmStatus,
                    'approved_at' => $fmApprovedAt ? $fmApprovedAt->toDateTimeString() : null,
                    'rejected_at' => $fmRejectedAt ? $fmRejectedAt->toDateTimeString() : null,
                    'comments' => $contraBon->finance_manager_note,
                ];
            }
            
            // GM Finance approval (level 2) - if exists
            // Note: GM Finance approval might not be used in current system
            // But we'll check if gm_finance_approved_by exists
            if ($contraBon->gm_finance_approved_by) {
                $gmFinance = \App\Models\User::find($contraBon->gm_finance_approved_by);
                if ($gmFinance) {
                    $gmStatus = 'PENDING';
                    $gmApprovedAt = null;
                    $gmRejectedAt = null;
                    
                    if ($contraBon->gm_finance_approved_at) {
                        $gmStatus = 'APPROVED';
                        $gmApprovedAt = $contraBon->gm_finance_approved_at;
                    } elseif ($contraBon->status === 'rejected' && $contraBon->gm_finance_approved_by) {
                        $gmStatus = 'REJECTED';
                        $gmRejectedAt = $contraBon->gm_finance_approved_at;
                    }
                    
                    $approvalFlows[] = [
                        'id' => 'gm_' . $contraBon->id,
                        'approval_level' => 2,
                        'approver_id' => $gmFinance->id,
                        'approver' => [
                            'id' => $gmFinance->id,
                            'nama_lengkap' => $gmFinance->nama_lengkap,
                            'email' => $gmFinance->email,
                        ],
                        'status' => $gmStatus,
                        'approved_at' => $gmApprovedAt ? $gmApprovedAt->toDateTimeString() : null,
                        'rejected_at' => $gmRejectedAt ? $gmRejectedAt->toDateTimeString() : null,
                        'comments' => $contraBon->gm_finance_note,
                    ];
                }
            }
            
            // Add approval flows to response
            $contraBon->approval_flows = $approvalFlows;

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
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: User not authenticated',
                    'contra_bons' => []
                ], 401);
            }
            
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $query = ContraBon::with(['supplier', 'purchaseOrder', 'retailFood', 'warehouseRetailFood', 'retailNonFood', 'creator', 'sources'])
                ->where('status', 'draft')
                ->orderByDesc('created_at');
            
            $pendingApprovals = [];
            
            // Finance Manager approvals (id_jabatan == 160) - Only level
            if (($user->id_jabatan == 160 && $user->status == 'A') || $isSuperadmin) {
                $financeManagerApprovals = (clone $query)
                    ->whereNull('finance_manager_approved_at')
                    ->get();
                
                foreach ($financeManagerApprovals as $cb) {
                    // Ensure warehouseRetailFood is loaded if source_type is warehouse_retail_food
                    if (!$cb->relationLoaded('warehouseRetailFood')) {
                        $cb->load('warehouseRetailFood');
                    }
                    
                    // Get source_types array (same logic as index method)
                    $sourceTypes = $this->getSourceTypes($cb);
                    
                    // Get approver name - Finance Manager
                    $approverName = null;
                    if ($isSuperadmin) {
                        // For superadmin, get the next approver (Finance Manager if not approved, GM Finance if Finance Manager approved)
                        if (!$cb->finance_manager_approved_at) {
                            $financeManager = \App\Models\User::where('id_jabatan', 160)->where('status', 'A')->first();
                            $approverName = $financeManager ? $financeManager->nama_lengkap : 'Finance Manager';
                        } elseif (!$cb->gm_finance_approved_at) {
                            $gmFinance = \App\Models\User::whereIn('id_jabatan', [152, 381])->where('status', 'A')->first();
                            $approverName = $gmFinance ? $gmFinance->nama_lengkap : 'GM Finance';
                        }
                    } else {
                        // For Finance Manager, they are the approver
                        $approverName = $user->nama_lengkap;
                    }
                    
                    $pendingApprovals[] = [
                        'id' => $cb->id,
                        'number' => $cb->number,
                        'date' => $cb->date,
                        'total_amount' => $cb->total_amount,
                        'supplier' => $cb->supplier ? ['name' => $cb->supplier->name] : null,
                        'source_type' => $cb->source_type,
                        'source_type_display' => $this->getSourceTypeDisplay($cb),
                        'source_types' => $sourceTypes,
                        'creator' => $cb->creator ? ['nama_lengkap' => $cb->creator->nama_lengkap] : null,
                        'approval_level' => 'finance_manager',
                        'approval_level_display' => 'Finance Manager',
                        'approver_name' => $approverName
                    ];
                }
            }
            
            // GM Finance approvals (id_jabatan == 152 atau 381)
            if ((in_array($user->id_jabatan, [152, 381]) && $user->status == 'A') || $isSuperadmin) {
                $gmFinanceApprovals = (clone $query)
                    ->whereNotNull('finance_manager_approved_at')
                    ->whereNull('gm_finance_approved_at')
                    ->get();
                
                foreach ($gmFinanceApprovals as $cb) {
                    // Ensure warehouseRetailFood is loaded if source_type is warehouse_retail_food
                    if (!$cb->relationLoaded('warehouseRetailFood')) {
                        $cb->load('warehouseRetailFood');
                    }
                    
                    // Get source_types array (same logic as index method)
                    $sourceTypes = $this->getSourceTypes($cb);
                    
                    // Get approver name - GM Finance
                    $approverName = null;
                    if ($isSuperadmin) {
                        // For superadmin, get GM Finance approver
                        $gmFinance = \App\Models\User::whereIn('id_jabatan', [152, 381])->where('status', 'A')->first();
                        $approverName = $gmFinance ? $gmFinance->nama_lengkap : 'GM Finance';
                    } else {
                        // For GM Finance, they are the approver
                        $approverName = $user->nama_lengkap;
                    }
                    
                    $pendingApprovals[] = [
                        'id' => $cb->id,
                        'number' => $cb->number,
                        'date' => $cb->date,
                        'total_amount' => $cb->total_amount,
                        'supplier' => $cb->supplier ? ['name' => $cb->supplier->name] : null,
                        'source_type' => $cb->source_type,
                        'source_type_display' => $this->getSourceTypeDisplay($cb),
                        'source_types' => $sourceTypes,
                        'creator' => $cb->creator ? ['nama_lengkap' => $cb->creator->nama_lengkap] : null,
                        'approval_level' => 'gm_finance',
                        'approval_level_display' => 'GM Finance',
                        'approver_name' => $approverName
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
    
    private function getSourceTypes($contraBon)
    {
        $sourceTypes = [];
        
        // Handle multiple sources (new) or single source (old data - backward compatibility)
        if ($contraBon->sources && $contraBon->sources->count() > 0) {
            // New data: multiple sources
            foreach ($contraBon->sources as $source) {
                if ($source->source_type === 'purchase_order' && $source->purchaseOrder) {
                    $po = $source->purchaseOrder;
                    if ($po->source_type === 'pr_foods' || !$po->source_type) {
                        $sourceTypes[] = 'PR Foods';
                    } elseif ($po->source_type === 'ro_supplier') {
                        $sourceTypes[] = 'RO Supplier';
                    }
                } elseif ($source->source_type === 'retail_food' && $source->retailFood) {
                    $sourceTypes[] = 'Retail Food';
                } elseif ($source->source_type === 'warehouse_retail_food' && $source->warehouseRetailFood) {
                    $sourceTypes[] = 'Warehouse Retail Food';
                }
            }
        } elseif ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
            // Old data: single source
            $po = $contraBon->purchaseOrder;
            if ($po->source_type === 'pr_foods' || !$po->source_type) {
                $sourceTypes[] = 'PR Foods';
            } elseif ($po->source_type === 'ro_supplier') {
                $sourceTypes[] = 'RO Supplier';
            } else {
                $sourceTypes[] = 'Unknown';
            }
        } elseif ($contraBon->source_type === 'retail_food') {
            $sourceTypes[] = 'Retail Food';
        } elseif ($contraBon->source_type === 'warehouse_retail_food') {
            $sourceTypes[] = 'Warehouse Retail Food';
        } else {
            $sourceTypes[] = 'Unknown';
        }
        
        return array_unique($sourceTypes);
    }

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

    // API: Get approved Good Receives with PO, supplier, and items (with PO price)
    public function getApprovedGoodReceives()
    {
        // Note: food_good_receives table doesn't have 'status' column
        $goodReceives = \DB::table('food_good_receives as gr')
            ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->join('suppliers as s', 'gr.supplier_id', '=', 's.id')
            ->join('pr_foods as pr', 'po.pr_food_id', '=', 'pr.id')
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
    public function getPOWithApprovedGR(Request $request)
    {
        try {
            // Ambil semua gr_item_id yang sudah ada di contra bon items
            // Hanya ambil dari contra bon yang masih ada (tidak dihapus)
            // Join dengan contra_bons untuk memastikan hanya ambil dari contra bon yang masih ada
            // Jika contra bon dihapus (hard delete), items juga dihapus, jadi join akan otomatis exclude
            // Jika ada soft delete, tambahkan filter whereNull('cb.deleted_at')
            $usedGRItemIdsQuery = \DB::table('food_contra_bon_items as cbi')
                ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
                ->whereNotNull('cbi.gr_item_id');
            
            // Cek apakah ada soft delete
            if (Schema::hasColumn('food_contra_bons', 'deleted_at')) {
                $usedGRItemIdsQuery->whereNull('cb.deleted_at');
            }
            
            $usedGRItemIds = $usedGRItemIdsQuery->pluck('cbi.gr_item_id')->toArray();
            
            // Log untuk debug (bisa dihapus setelah fix)
            \Log::debug('getPOWithApprovedGR - usedGRItemIds count', [
                'count' => count($usedGRItemIds),
                'sample_ids' => array_slice($usedGRItemIds, 0, 10)
            ]);

            // Get all PO with GR in one query
            // Note: food_good_receives table doesn't have 'status' column, so we get all GRs
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
                ->limit(500)
                ->get();

            if ($poWithGR->isEmpty()) {
                return response()->json([]);
            }

            // Batch query: Get all GR IDs
            $grIds = $poWithGR->pluck('gr_id')->toArray();
            $poIds = $poWithGR->pluck('po_id')->unique()->toArray();
            $roSupplierPoIds = $poWithGR->where('source_type', 'ro_supplier')->pluck('po_id')->unique()->toArray();

            // REFACTOR: Query items dan units secara terpisah untuk memastikan data benar-benar ada
            // Step 1: Query semua GR items
            $allGRItems = \DB::table('food_good_receive_items as gri')
                ->leftJoin('purchase_order_food_items as poi', 'gri.po_item_id', '=', 'poi.id')
                ->whereIn('gri.good_receive_id', $grIds)
                ->whereNotIn('gri.id', $usedGRItemIds)
                ->select(
                    'gri.good_receive_id',
                    'gri.id',
                    'gri.item_id',
                    'gri.po_item_id',
                    'gri.unit_id',
                    'gri.qty_received',
                    'poi.price as po_price',
                    'poi.discount_percent',
                    'poi.discount_amount',
                    'poi.total as po_item_total'
                )
                ->get();

            // Step 2: Get semua item_id dan unit_id yang unik
            $itemIds = $allGRItems->pluck('item_id')->filter()->unique()->toArray();
            $unitIds = $allGRItems->pluck('unit_id')->filter()->unique()->toArray();

            // Step 3: Query items dan units secara batch
            $itemsMap = [];
            if (!empty($itemIds)) {
                $itemsData = \DB::table('items')->whereIn('id', $itemIds)->get();
                foreach ($itemsData as $item) {
                    $itemsMap[$item->id] = $item->name;
                }
            }

            $unitsMap = [];
            if (!empty($unitIds)) {
                $unitsData = \DB::table('units')->whereIn('id', $unitIds)->get();
                foreach ($unitsData as $unit) {
                    $unitsMap[$unit->id] = $unit->name;
                }
            }

            // Step 4: Map items dengan item_name dan unit_name - pastikan sebagai array untuk JSON serialization
            $allItems = $allGRItems->map(function($gri) use ($itemsMap, $unitsMap) {
                return (object)[
                    'good_receive_id' => $gri->good_receive_id,
                    'id' => $gri->id,
                    'item_id' => $gri->item_id,
                    'po_item_id' => $gri->po_item_id,
                    'unit_id' => $gri->unit_id,
                    'item_name' => (string)($itemsMap[$gri->item_id] ?? ''), // Pastikan string
                    'unit_name' => (string)($unitsMap[$gri->unit_id] ?? ''), // Pastikan string
                    'qty_received' => $gri->qty_received,
                    'po_price' => $gri->po_price,
                    'discount_percent' => $gri->discount_percent,
                    'discount_amount' => $gri->discount_amount,
                    'po_item_total' => $gri->po_item_total,
                ];
            });


            $allItemsGrouped = $allItems->groupBy('good_receive_id');

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
                $items = $allItemsGrouped->get($row->gr_id, collect());
                
                // Skip if no items available
                if ($items->isEmpty()) {
                    // Log untuk debug - PO di-skip karena tidak ada items yang available
                    \Log::debug('getPOWithApprovedGR - PO skipped (no available items)', [
                        'po_id' => $row->po_id,
                        'po_number' => $row->po_number,
                        'gr_id' => $row->gr_id,
                        'gr_number' => $row->gr_number
                    ]);
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
                
                // Convert items to array - data sudah di-map dengan item_name dan unit_name di step sebelumnya
                $itemsArray = [];
                foreach ($items as $item) {
                    // Data sudah di-map dengan item_name dan unit_name di query sebelumnya
                    $itemName = (string)($item->item_name ?? '');
                    $unitName = (string)($item->unit_name ?? '');
                    
                    // Warn jika masih kosong
                    if (empty($itemName) || empty($unitName)) {
                        \Log::warning('Item missing name/unit in final mapping', [
                            'gr_item_id' => $item->id,
                            'item_id' => $item->item_id,
                            'unit_id' => $item->unit_id,
                            'item_name' => $itemName,
                            'unit_name' => $unitName,
                            'raw_item' => (array)$item
                        ]);
                    }
                    
                    $itemsArray[] = [
                        'id' => (int)$item->id,
                        'item_id' => $item->item_id ? (int)$item->item_id : null,
                        'po_item_id' => $item->po_item_id ? (int)$item->po_item_id : null,
                        'unit_id' => $item->unit_id ? (int)$item->unit_id : null,
                        'item_name' => $itemName, // Sudah di-map sebelumnya
                        'unit_name' => $unitName, // Sudah di-map sebelumnya
                        'qty_received' => (float)$item->qty_received,
                        'po_price' => (float)$item->po_price,
                        'discount_percent' => $item->discount_percent ? (float)$item->discount_percent : 0,
                        'discount_amount' => $item->discount_amount ? (float)$item->discount_amount : 0,
                        'po_item_total' => $item->po_item_total ? (float)$item->po_item_total : ((float)$item->po_price * (float)$item->qty_received),
                    ];
                }
                
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
                    'items' => $itemsArray, // Gunakan array yang sudah di-map
                    'po_discount_info' => $poDiscountInfo,
                ];
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error in getPOWithApprovedGR: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Gagal mengambil data PO/GR: ' . $e->getMessage()], 500);
        }
    }
    
    // Simple test endpoint - langsung return data test
    public function testAPI()
    {
        return response()->json([
            'message' => 'API is working',
            'timestamp' => now(),
            'test_data' => [
                'item_name' => 'Test Item',
                'unit_name' => 'Test Unit'
            ]
        ]);
    }
    
    // Test endpoint untuk debug
    public function testPOGRItems($grId)
    {
        try {
            // Test 1: Query langsung dari food_good_receive_items
            $grItems = DB::table('food_good_receive_items')
                ->where('good_receive_id', $grId)
                ->select('id', 'item_id', 'unit_id', 'qty_received')
                ->get();
            
            // Test 2: Query dengan join
            $itemsWithJoin = DB::table('food_good_receive_items as gri')
                ->leftJoin('items as i', 'gri.item_id', '=', 'i.id')
                ->leftJoin('units as u', 'gri.unit_id', '=', 'u.id')
                ->where('gri.good_receive_id', $grId)
                ->select(
                    'gri.id',
                    'gri.item_id',
                    'gri.unit_id',
                    'i.name as item_name',
                    'u.name as unit_name'
                )
                ->get();
            
            // Test 3: Query items dan units secara terpisah
            $itemIds = $grItems->pluck('item_id')->filter()->unique()->toArray();
            $unitIds = $grItems->pluck('unit_id')->filter()->unique()->toArray();
            
            $itemsMap = [];
            if (!empty($itemIds)) {
                $itemsData = DB::table('items')->whereIn('id', $itemIds)->get();
                foreach ($itemsData as $item) {
                    $itemsMap[$item->id] = $item->name;
                }
            }
            
            $unitsMap = [];
            if (!empty($unitIds)) {
                $unitsData = DB::table('units')->whereIn('id', $unitIds)->get();
                foreach ($unitsData as $unit) {
                    $unitsMap[$unit->id] = $unit->name;
                }
            }
            
            // Build result dengan manual mapping
            $itemsManual = [];
            foreach ($grItems as $gri) {
                $itemsManual[] = [
                    'id' => $gri->id,
                    'item_id' => $gri->item_id,
                    'unit_id' => $gri->unit_id,
                    'item_name' => $itemsMap[$gri->item_id] ?? 'NOT FOUND',
                    'unit_name' => $unitsMap[$gri->unit_id] ?? 'NOT FOUND',
                ];
            }
            
            return response()->json([
                'gr_id' => $grId,
                'test1_raw_items' => $grItems,
                'test2_with_join' => $itemsWithJoin,
                'test3_manual_mapping' => $itemsManual,
                'items_map' => $itemsMap,
                'units_map' => $unitsMap,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // API: Get Retail Food with contra bon payment method
    public function getRetailFoodContraBon()
    {
        try {
            // Get all retail foods in one query
            // Exclude those that already have contra bon
            $retailFoods = \DB::table('retail_food as rf')
                ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
                ->join('users as creator', 'rf.created_by', '=', 'creator.id')
                ->leftJoin('tbl_data_outlet as o', 'rf.outlet_id', '=', 'o.id_outlet')
                ->leftJoin('warehouse_outlets as wo', 'rf.warehouse_outlet_id', '=', 'wo.id')
                ->where('rf.payment_method', 'contra_bon')
                ->where('rf.status', 'approved')
                ->whereNotExists(function ($query) {
                    $query->select(\DB::raw(1))
                        ->from('food_contra_bon_sources as cbs')
                        ->whereColumn('cbs.source_id', 'rf.id')
                        ->where('cbs.source_type', 'retail_food');
                })
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
            // Join ke items dan units untuk mendapatkan item_id dan unit_id
            $allItems = \DB::table('retail_food_items as rfi')
                ->leftJoin('items as i', function($join) {
                    $join->on(\DB::raw('LOWER(i.name)'), '=', \DB::raw('LOWER(rfi.item_name)'));
                })
                ->leftJoin('units as u', function($join) {
                    $join->on(\DB::raw('LOWER(u.name)'), '=', \DB::raw('LOWER(rfi.unit)'));
                })
                ->whereIn('rfi.retail_food_id', $retailFoodIds)
                ->select(
                    'rfi.retail_food_id',
                    'rfi.id',
                    'rfi.item_name',
                    'rfi.unit as unit_name',
                    'rfi.qty',
                    'rfi.price',
                    'i.id as item_id',
                    'u.id as unit_id'
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
            // Exclude those that already have contra bon
            $warehouseRetailFoods = \DB::table('retail_warehouse_food as rwf')
                ->join('suppliers as s', 'rwf.supplier_id', '=', 's.id')
                ->join('users as creator', 'rwf.created_by', '=', 'creator.id')
                ->leftJoin('warehouses as w', 'rwf.warehouse_id', '=', 'w.id')
                ->leftJoin('warehouse_division as wd', 'rwf.warehouse_division_id', '=', 'wd.id')
                ->where('rwf.payment_method', 'contra_bon')
                ->where('rwf.status', 'approved')
                ->whereNotExists(function ($query) {
                    $query->select(\DB::raw(1))
                        ->from('food_contra_bon_sources as cbs')
                        ->whereColumn('cbs.source_id', 'rwf.id')
                        ->where('cbs.source_type', 'warehouse_retail_food');
                })
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
            // Join ke items dan units untuk mendapatkan item_id dan unit_id
            $allItems = \DB::table('retail_warehouse_food_items as rwfi')
                ->leftJoin('items as i', function($join) {
                    $join->on(\DB::raw('LOWER(i.name)'), '=', \DB::raw('LOWER(rwfi.item_name)'));
                })
                ->leftJoin('units as u', function($join) {
                    $join->on(\DB::raw('LOWER(u.name)'), '=', \DB::raw('LOWER(rwfi.unit)'));
                })
                ->whereIn('rwfi.retail_warehouse_food_id', $warehouseRetailFoodIds)
                ->select(
                    'rwfi.retail_warehouse_food_id',
                    'rwfi.id',
                    'rwfi.item_name',
                    'rwfi.unit as unit_name',
                    'rwfi.qty',
                    'rwfi.price',
                    'i.id as item_id',
                    'u.id as unit_id'
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
        $user = Auth::user();
        
        // Validasi: Hanya Finance Manager (id_jabatan == 160) dan Superadmin yang bisa edit
        $isFinanceManager = $user->id_jabatan == 160 && $user->status == 'A';
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        
        if (!$isFinanceManager && !$isSuperadmin) {
            return back()->withErrors(['error' => 'Hanya Finance Manager dan Superadmin yang dapat mengedit Contra Bon.']);
        }
        
        $contraBon = ContraBon::with([
            'supplier',
            'purchaseOrder',
            'sources',
            'sources.purchaseOrder',
            'sources.retailFood',
            'sources.warehouseRetailFood',
            'sources.retailNonFood',
            'items.item',
            'items.unit',
            'items.grItem' => function($query) {
                $query->with(['item', 'unit']);
            },
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
            
            // Add discount info to items and fix missing item/unit from GR
            $contraBon->items->each(function($item) {
                // Pastikan item_name dan unit_name selalu ada
                // Prioritas: 1. Dari relasi item/unit, 2. Dari grItem, 3. Query langsung dari database
                
                // Ambil item_name
                if (!$item->item_name) {
                    if ($item->item && $item->item->name) {
                        $item->item_name = $item->item->name;
                    } elseif ($item->grItem && $item->grItem->item && $item->grItem->item->name) {
                        $item->item_name = $item->grItem->item->name;
                    } elseif ($item->item_id) {
                        // Query langsung dari database jika relasi tidak ter-load
                        $itemData = DB::table('items')->where('id', $item->item_id)->first();
                        if ($itemData && $itemData->name) {
                            $item->item_name = $itemData->name;
                            // Set relasi juga jika belum ada
                            if (!$item->item) {
                                $item->setRelation('item', \App\Models\Item::find($item->item_id));
                            }
                        } elseif ($item->gr_item_id) {
                            // Fallback: ambil dari GR item
                            $grItem = DB::table('food_good_receive_items as gri')
                                ->join('items as i', 'gri.item_id', '=', 'i.id')
                                ->where('gri.id', $item->gr_item_id)
                                ->select('i.name as item_name')
                                ->first();
                            if ($grItem && $grItem->item_name) {
                                $item->item_name = $grItem->item_name;
                            }
                        }
                    }
                }
                
                // Ambil unit_name
                if (!$item->unit_name) {
                    if ($item->unit && $item->unit->name) {
                        $item->unit_name = $item->unit->name;
                    } elseif ($item->grItem && $item->grItem->unit && $item->grItem->unit->name) {
                        $item->unit_name = $item->grItem->unit->name;
                    } elseif ($item->unit_id) {
                        // Query langsung dari database jika relasi tidak ter-load
                        $unitData = DB::table('units')->where('id', $item->unit_id)->first();
                        if ($unitData && $unitData->name) {
                            $item->unit_name = $unitData->name;
                            // Set relasi juga jika belum ada
                            if (!$item->unit) {
                                $item->setRelation('unit', \App\Models\Unit::find($item->unit_id));
                            }
                        } elseif ($item->gr_item_id) {
                            // Fallback: ambil dari GR item
                            $grItem = DB::table('food_good_receive_items as gri')
                                ->join('units as u', 'gri.unit_id', '=', 'u.id')
                                ->where('gri.id', $item->gr_item_id)
                                ->select('u.name as unit_name')
                                ->first();
                            if ($grItem && $grItem->unit_name) {
                                $item->unit_name = $grItem->unit_name;
                            }
                        }
                    }
                }
                
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

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        // Validasi: Hanya Finance Manager (id_jabatan == 160) dan Superadmin yang bisa edit
        $isFinanceManager = $user->id_jabatan == 160 && $user->status == 'A';
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        
        if (!$isFinanceManager && !$isSuperadmin) {
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Finance Manager dan Superadmin yang dapat mengedit Contra Bon.'
                ], 403);
            }
            return back()->withErrors(['error' => 'Hanya Finance Manager dan Superadmin yang dapat mengedit Contra Bon.']);
        }
        
        $contraBon = ContraBon::findOrFail($id);
        
        // Gunakan logika yang sama dengan store, tapi untuk update
        $sourceType = $request->input('source_type', $contraBon->source_type ?? 'purchase_order');
        
        $rules = [
            'date' => 'required|date',
            'items' => 'required|array',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'supplier_invoice_number' => 'nullable|string|max:100',
            'source_type' => 'nullable|in:purchase_order,retail_food,warehouse_retail_food,retail_non_food',
        ];
        
        // Conditional validation based on source_type
        if ($sourceType === 'purchase_order') {
            $rules['po_id'] = 'required|exists:purchase_order_foods,id';
            $rules['gr_id'] = 'required|exists:food_good_receives,id';
            $rules['items.*.item_id'] = 'nullable|exists:items,id';
            $rules['items.*.unit_id'] = 'nullable|exists:units,id';
            $rules['items.*.gr_item_id'] = 'nullable|exists:food_good_receive_items,id';
        } else {
            $rules['source_id'] = 'required';
            $rules['items.*.item_id'] = 'nullable|exists:items,id';
            $rules['items.*.unit_id'] = 'nullable|exists:units,id';
            foreach ($request->input('items', []) as $index => $item) {
                $itemId = $item['item_id'] ?? null;
                $unitId = $item['unit_id'] ?? null;
                if ($itemId === 'null' || $itemId === '' || $itemId === null) {
                    $rules["items.{$index}.item_name"] = 'required|string';
                }
                if ($unitId === 'null' || $unitId === '' || $unitId === null) {
                    $rules["items.{$index}.unit_name"] = 'required|string';
                }
            }
        }
        
        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Contra Bon update validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->except(['image']),
            ]);
            
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
            $sourceType = $request->input('source_type', $contraBon->source_type ?? 'purchase_order');
            $sourceId = $request->input('source_id');
            $supplierId = null;
            $poId = null;
            $grId = null;

            // Handle different source types (same as store)
            if ($sourceType === 'purchase_order') {
                $po = PurchaseOrderFood::findOrFail($request->po_id);
                $supplierId = $po->supplier_id;
                $poId = $po->id;
                $grId = $request->input('gr_id');
            } elseif ($sourceType === 'retail_food') {
                $sourceIdInt = is_numeric($sourceId) ? (int)$sourceId : $sourceId;
                $retailFood = \App\Models\RetailFood::findOrFail($sourceIdInt);
                $supplierId = $retailFood->supplier_id;
            } elseif ($sourceType === 'warehouse_retail_food') {
                $sourceIdInt = is_numeric($sourceId) ? (int)$sourceId : $sourceId;
                $warehouseRetailFood = \App\Models\RetailWarehouseFood::findOrFail($sourceIdInt);
                $supplierId = $warehouseRetailFood->supplier_id;
            } elseif ($sourceType === 'retail_non_food') {
                $sourceIdInt = is_numeric($sourceId) ? (int)$sourceId : $sourceId;
                $retailNonFood = \App\Models\RetailNonFood::findOrFail($sourceIdInt);
                $supplierId = $retailNonFood->supplier_id;
            }
            
            // Calculate total amount with discount (same as store)
            $subtotal = collect($request->items)->sum(function ($item) {
                $quantity = floatval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $subtotalItem = $quantity * $price;
                
                $discountPercent = floatval($item['discount_percent'] ?? 0);
                $discountAmount = floatval($item['discount_amount'] ?? 0);
                
                if ($discountPercent > 0) {
                    $discount = $subtotalItem * ($discountPercent / 100);
                } else if ($discountAmount > 0) {
                    $discount = $discountAmount;
                } else {
                    $discount = 0;
                }
                
                return $subtotalItem - $discount;
            });
            
            $discountTotalPercent = floatval($request->discount_total_percent ?? 0);
            $discountTotalAmount = floatval($request->discount_total_amount ?? 0);
            
            $discountTotal = 0;
            if ($discountTotalPercent > 0) {
                $discountTotal = $subtotal * ($discountTotalPercent / 100);
            } else if ($discountTotalAmount > 0) {
                $discountTotal = $discountTotalAmount;
            }
            
            $totalAmount = $subtotal - $discountTotal;

            // Handle image upload
            $imagePath = $contraBon->image_path;
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($imagePath && \Storage::disk('public')->exists($imagePath)) {
                    \Storage::disk('public')->delete($imagePath);
                }
                $imagePath = $request->file('image')->store('contra_bon_images', 'public');
            }

            $sourceIdForSave = null;
            if ($sourceType === 'retail_food' || $sourceType === 'warehouse_retail_food' || $sourceType === 'retail_non_food') {
                $sourceIdForSave = is_numeric($sourceId) ? (int)$sourceId : $sourceId;
            }
            
            // Update contra bon
            $contraBonData = [
                'date' => $request->date,
                'supplier_id' => $supplierId,
                'total_amount' => $totalAmount,
                'discount_total_percent' => $discountTotalPercent,
                'discount_total_amount' => $discountTotalAmount,
                'notes' => $request->notes,
                'image_path' => $imagePath,
                'supplier_invoice_number' => $request->supplier_invoice_number,
                'source_type' => $sourceType,
            ];
            
            if ($sourceIdForSave !== null) {
                $contraBonData['source_id'] = $sourceIdForSave;
            }
            
            if ($poId !== null) {
                $contraBonData['po_id'] = $poId;
            }
            
            $contraBon->update($contraBonData);
            
            // Delete existing sources and items
            $contraBon->sources()->delete();
            $contraBon->items()->delete();
            
            // Save multiple sources (same as store)
            $sources = $request->input('sources', []);
            if (empty($sources) && $request->input('source_type')) {
                $sourceIdForPivot = null;
                if ($sourceType === 'purchase_order') {
                    $sourceIdForPivot = $poId;
                } else {
                    $sourceIdForPivot = $sourceIdForSave;
                }
                
                $sources = [[
                    'source_type' => $sourceType,
                    'source_id' => $sourceIdForPivot,
                    'po_id' => $poId,
                    'gr_id' => $grId,
                ]];
            }
            
            foreach ($sources as $source) {
                $sourceTypeForSource = $source['source_type'] ?? $sourceType;
                $sourceIdForSource = $source['source_id'] ?? null;
                
                if ($sourceIdForSource === 'undefined' || $sourceIdForSource === 'null' || $sourceIdForSource === '') {
                    $sourceIdForSource = null;
                }
                
                if ($sourceTypeForSource === 'purchase_order') {
                    if ($sourceIdForSource === null || $sourceIdForSource === '' || $sourceIdForSource === 'null' || $sourceIdForSource === 'undefined') {
                        $sourceIdForSource = $source['po_id'] ?? $poId;
                    }
                }
                
                if ($sourceIdForSource !== null && $sourceIdForSource !== '' && $sourceIdForSource !== 'null' && $sourceIdForSource !== 'undefined') {
                    if (is_numeric($sourceIdForSource)) {
                        $sourceIdForSource = (int)$sourceIdForSource;
                    } else {
                        if ($sourceTypeForSource === 'purchase_order') {
                            $sourceIdForSource = $source['po_id'] ?? $poId;
                        } else {
                            $sourceIdForSource = null;
                        }
                    }
                } else {
                    $sourceIdForSource = null;
                }
                
                if ($sourceTypeForSource === 'purchase_order' && $sourceIdForSource === null) {
                    $sourceIdForSource = $source['po_id'] ?? $poId;
                }
                
                ContraBonSource::create([
                    'contra_bon_id' => $contraBon->id,
                    'source_type' => $sourceTypeForSource,
                    'source_id' => $sourceIdForSource,
                    'po_id' => $source['po_id'] ?? ($sourceTypeForSource === 'purchase_order' ? $poId : null),
                    'gr_id' => $source['gr_id'] ?? ($sourceTypeForSource === 'purchase_order' ? $grId : null),
                ]);
            }
            
            // Create contra bon items (same logic as store)
            foreach ($request->items as $item) {
                $itemId = $item['item_id'] ?? null;
                $unitId = $item['unit_id'] ?? null;
                
                if ($itemId === 'null' || $itemId === '' || $itemId === null || (is_string($itemId) && strtolower(trim($itemId)) === 'null')) {
                    $itemId = null;
                } else {
                    $itemId = is_numeric($itemId) ? (int)$itemId : $itemId;
                }
                
                if ($unitId === 'null' || $unitId === '' || $unitId === null || (is_string($unitId) && strtolower(trim($unitId)) === 'null')) {
                    $unitId = null;
                } else {
                    $unitId = is_numeric($unitId) ? (int)$unitId : $unitId;
                }
                
                $grItemId = $item['gr_item_id'] ?? null;
                if ($grItemId === 'null' || $grItemId === '' || $grItemId === null) {
                    $grItemId = null;
                } else {
                    $grItemId = is_numeric($grItemId) ? (int)$grItemId : $grItemId;
                }
                
                if ($sourceType === 'purchase_order' && (!$itemId || !$unitId) && $grItemId) {
                    $grItem = DB::table('food_good_receive_items as gri')
                        ->where('gri.id', $grItemId)
                        ->select('gri.item_id', 'gri.unit_id')
                        ->first();
                    
                    if ($grItem) {
                        if (!$itemId && $grItem->item_id) {
                            $itemId = $grItem->item_id;
                        }
                        if (!$unitId && $grItem->unit_id) {
                            $unitId = $grItem->unit_id;
                        }
                    }
                }
                
                // Find item by name if needed
                if (!$itemId && isset($item['item_name']) && !empty(trim($item['item_name']))) {
                    $itemName = trim($item['item_name']);
                    $foundItem = \DB::table('items')->where('name', $itemName)->first();
                    if (!$foundItem) {
                        $foundItem = \DB::table('items')->whereRaw('LOWER(name) = LOWER(?)', [$itemName])->first();
                    }
                    if (!$foundItem) {
                        $foundItem = \DB::table('items')->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $itemName . '%'])->first();
                    }
                    if ($foundItem) {
                        $itemId = $foundItem->id;
                    } else {
                        throw new \Exception("Item dengan nama '{$itemName}' tidak ditemukan di database.");
                    }
                }
                
                // Find unit by name if needed
                if (!$unitId && isset($item['unit_name']) && !empty(trim($item['unit_name']))) {
                    $unitName = trim($item['unit_name']);
                    $foundUnit = \DB::table('units')->where('name', $unitName)->first();
                    if (!$foundUnit) {
                        $foundUnit = \DB::table('units')->whereRaw('LOWER(name) = LOWER(?)', [$unitName])->first();
                    }
                    if (!$foundUnit) {
                        $foundUnit = \DB::table('units')->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $unitName . '%'])->first();
                    }
                    if ($foundUnit) {
                        $unitId = $foundUnit->id;
                    } else {
                        throw new \Exception("Unit dengan nama '{$unitName}' tidak ditemukan di database.");
                    }
                }
                
                if (!$itemId) {
                    throw new \Exception("Item ID tidak boleh kosong.");
                }
                if (!$unitId) {
                    throw new \Exception("Unit ID tidak boleh kosong.");
                }
                
                $quantity = floatval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                $subtotalItem = $quantity * $price;
                
                $discountPercent = floatval($item['discount_percent'] ?? 0);
                $discountAmount = floatval($item['discount_amount'] ?? 0);
                
                $discount = 0;
                if ($discountPercent > 0) {
                    $discount = $subtotalItem * ($discountPercent / 100);
                } else if ($discountAmount > 0) {
                    $discount = $discountAmount;
                }
                
                $itemTotal = $subtotalItem - $discount;
                
                $contraBonItemData = [
                    'contra_bon_id' => $contraBon->id,
                    'item_id' => $itemId,
                    'unit_id' => $unitId,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'total' => $itemTotal,
                    'notes' => $item['notes'] ?? null
                ];
                
                $poItemId = $item['po_item_id'] ?? null;
                if ($poItemId !== null && $poItemId !== 'null' && $poItemId !== '') {
                    $contraBonItemData['po_item_id'] = is_numeric($poItemId) ? (int)$poItemId : $poItemId;
                }
                
                $grItemId = $item['gr_item_id'] ?? null;
                if ($grItemId !== null && $grItemId !== 'null' && $grItemId !== '') {
                    $contraBonItemData['gr_item_id'] = is_numeric($grItemId) ? (int)$grItemId : $grItemId;
                }
                
                $retailFoodItemId = $item['retail_food_item_id'] ?? null;
                if ($retailFoodItemId !== null && $retailFoodItemId !== 'null' && $retailFoodItemId !== '') {
                    $contraBonItemData['retail_food_item_id'] = is_numeric($retailFoodItemId) ? (int)$retailFoodItemId : $retailFoodItemId;
                }
                
                $warehouseRetailFoodItemId = $item['warehouse_retail_food_item_id'] ?? null;
                if ($warehouseRetailFoodItemId !== null && $warehouseRetailFoodItemId !== 'null' && $warehouseRetailFoodItemId !== '') {
                    $contraBonItemData['warehouse_retail_food_item_id'] = is_numeric($warehouseRetailFoodItemId) ? (int)$warehouseRetailFoodItemId : $warehouseRetailFoodItemId;
                }
                
                ContraBonItem::create($contraBonItemData);
            }

            // Activity log
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'contra_bon',
                'description' => 'Update Contra Bon: ' . $contraBon->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $contraBon->fresh()->toArray(),
            ]);

            DB::commit();
            
            if (($request->wantsJson() || $request->ajax() || $request->expectsJson()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Contra Bon berhasil di-update',
                    'data' => [
                        'id' => $contraBon->id,
                        'number' => $contraBon->number
                    ]
                ], 200);
            }
            
            return redirect()->route('contra-bons.show', $contraBon->id)
                ->with('success', 'Contra Bon berhasil di-update');
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Error updating Contra Bon', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'contra_bon_id' => $id,
            ]);
            
            if (($request->wantsJson() || $request->ajax() || $request->expectsJson()) && !$request->header('X-Inertia')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal update Contra Bon: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withErrors(['error' => 'Gagal update Contra Bon: ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $contraBon = ContraBon::with(['items', 'sources', 'sources.purchaseOrder', 'sources.retailFood', 'sources.warehouseRetailFood'])->findOrFail($id);
        
        DB::beginTransaction();
        try {
            // Delete contra bon items first (this will free up the gr_item_id references)
            $contraBon->items()->delete();
            
            // Delete the contra bon
            $contraBon->delete();
            
            DB::commit();
            
            // Check if this is an Inertia request
            $isInertiaRequest = request()->header('X-Inertia');
            
            if ($isInertiaRequest) {
                // Return Inertia redirect for Inertia requests
                return redirect()->route('contra-bons.index')
                    ->with('success', 'Contra Bon berhasil dihapus. Item-item telah dikembalikan dan dapat digunakan lagi.');
            }
            
            // Return JSON for AJAX/API requests
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Contra Bon berhasil dihapus. Item-item telah dikembalikan dan dapat digunakan lagi.'
                ]);
            }
            
            // Fallback to regular redirect
            return redirect()->route('contra-bons.index')
                ->with('success', 'Contra Bon berhasil dihapus. Item-item telah dikembalikan dan dapat digunakan lagi.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting Contra Bon', [
                'contra_bon_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check if this is an Inertia request
            $isInertiaRequest = request()->header('X-Inertia');
            
            if ($isInertiaRequest) {
                // Return Inertia redirect for Inertia requests
                return redirect()->route('contra-bons.index')
                    ->with('error', 'Gagal menghapus Contra Bon: ' . $e->getMessage());
            }
            
            // Return JSON for AJAX/API requests
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus Contra Bon: ' . $e->getMessage()
                ], 500);
            }
            
            // Fallback to regular redirect
            return redirect()->route('contra-bons.index')
                ->with('error', 'Gagal menghapus Contra Bon: ' . $e->getMessage());
        }
    }

    /**
     * API: Trace contra bon untuk debug
     * Endpoint untuk membantu trace masalah delete contra bon
     */
    public function traceContraBon(Request $request)
    {
        $supplierId = $request->input('supplier_id');
        $grDate = $request->input('gr_date');
        
        if (!$supplierId || !$grDate) {
            return response()->json([
                'error' => 'supplier_id dan gr_date wajib diisi'
            ], 422);
        }

        $result = [
            'supplier_id' => $supplierId,
            'gr_date' => $grDate,
            'trace' => []
        ];

        // 1. Cek GR untuk supplier dan tanggal tersebut
        $goodReceives = \DB::table('food_good_receives as gr')
            ->join('purchase_order_foods as po', 'gr.po_id', '=', 'po.id')
            ->where('po.supplier_id', $supplierId)
            ->whereDate('gr.receive_date', $grDate)
            ->select(
                'gr.id as gr_id',
                'gr.gr_number',
                'gr.receive_date',
                'gr.po_id',
                'po.number as po_number',
                'po.supplier_id'
            )
            ->get();

        $result['trace']['good_receives'] = $goodReceives->toArray();
        $result['trace']['gr_count'] = $goodReceives->count();

        if ($goodReceives->isEmpty()) {
            return response()->json($result);
        }

        $grIds = $goodReceives->pluck('gr_id')->toArray();

        // 2. Cek GR items untuk GR tersebut
        $grItems = \DB::table('food_good_receive_items')
            ->whereIn('good_receive_id', $grIds)
            ->select('id', 'good_receive_id', 'item_id', 'unit_id', 'qty_received')
            ->get();

        $result['trace']['gr_items'] = $grItems->toArray();
        $result['trace']['gr_items_count'] = $grItems->count();

        $grItemIds = $grItems->pluck('id')->toArray();

        // 3. Cek contra bon items yang menggunakan GR items tersebut
        $contraBonItems = \DB::table('food_contra_bon_items as cbi')
            ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
            ->whereIn('cbi.gr_item_id', $grItemIds)
            ->select(
                'cbi.id as item_id',
                'cbi.contra_bon_id',
                'cbi.gr_item_id',
                'cb.number as contra_bon_number',
                'cb.date as contra_bon_date',
                'cb.status',
                'cb.deleted_at'
            )
            ->get();

        $result['trace']['contra_bon_items'] = $contraBonItems->toArray();
        $result['trace']['contra_bon_items_count'] = $contraBonItems->count();

        // 4. Cek contra bon yang masih ada (tidak dihapus)
        $contraBonIds = $contraBonItems->pluck('contra_bon_id')->unique()->toArray();
        
        if (!empty($contraBonIds)) {
            $contraBons = \DB::table('food_contra_bons')
                ->whereIn('id', $contraBonIds)
                ->select('id', 'number', 'date', 'status', 'deleted_at')
                ->get();
            
            $result['trace']['contra_bons'] = $contraBons->toArray();
            $result['trace']['contra_bons_count'] = $contraBons->count();
            $result['trace']['contra_bons_deleted_count'] = $contraBons->whereNotNull('deleted_at')->count();
        }

        // 5. Cek usedGRItemIds dari query getPOWithApprovedGR
        $usedGRItemIdsQuery = \DB::table('food_contra_bon_items as cbi')
            ->join('food_contra_bons as cb', 'cbi.contra_bon_id', '=', 'cb.id')
            ->whereNotNull('cbi.gr_item_id');
        
        if (Schema::hasColumn('food_contra_bons', 'deleted_at')) {
            $usedGRItemIdsQuery->whereNull('cb.deleted_at');
        }
        
        $usedGRItemIds = $usedGRItemIdsQuery->pluck('cbi.gr_item_id')->toArray();
        
        $result['trace']['used_gr_item_ids'] = $usedGRItemIds;
        $result['trace']['used_gr_item_ids_count'] = count($usedGRItemIds);
        
        // 6. Cek GR items yang seharusnya muncul (tidak di usedGRItemIds)
        $availableGRItems = $grItems->whereNotIn('id', $usedGRItemIds);
        $result['trace']['available_gr_items'] = $availableGRItems->values()->toArray();
        $result['trace']['available_gr_items_count'] = $availableGRItems->count();

        // 7. Cek apakah GR items dari supplier dan tanggal tersebut muncul di getPOWithApprovedGR
        $poWithGR = \DB::table('purchase_order_foods as po')
            ->join('food_good_receives as gr', 'gr.po_id', '=', 'po.id')
            ->where('po.supplier_id', $supplierId)
            ->whereDate('gr.receive_date', $grDate)
            ->select('po.id as po_id', 'po.number as po_number', 'gr.id as gr_id', 'gr.gr_number')
            ->get();

        $result['trace']['po_with_gr'] = $poWithGR->toArray();
        $result['trace']['po_with_gr_count'] = $poWithGR->count();

        // 8. Summary
        $result['summary'] = [
            'total_gr' => $goodReceives->count(),
            'total_gr_items' => $grItems->count(),
            'contra_bon_items_using_gr_items' => $contraBonItems->count(),
            'contra_bons_using_gr_items' => !empty($contraBonIds) ? count($contraBonIds) : 0,
            'contra_bons_deleted' => !empty($contraBonIds) ? $contraBons->whereNotNull('deleted_at')->count() : 0,
            'used_gr_item_ids_count' => count($usedGRItemIds),
            'available_gr_items_count' => $availableGRItems->count(),
            'should_appear_in_form' => $availableGRItems->count() > 0 ? 'YES' : 'NO'
        ];

        return response()->json($result);
    }
} 