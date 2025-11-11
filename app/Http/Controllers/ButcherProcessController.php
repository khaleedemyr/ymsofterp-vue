<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use Carbon\Carbon;
use App\Models\ButcherProcess;
use App\Models\ButcherProcessItem;
use App\Models\ButcherHalalCertificate;
use App\Models\FoodGoodReceive;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Unit;
use App\Models\Warehouse;
use Inertia\Inertia;
use App\Models\ButcherProcessItemDetail;
use App\Models\FoodInventoryStock;
use Illuminate\Support\Facades\Log;
use App\Models\FoodInventoryItem;
use App\Models\GoodReceive;

class ButcherProcessController extends Controller
{
    public function index(Request $request)
    {
        $query = ButcherProcess::query()
            ->with(['warehouse', 'goodReceive', 'createdBy'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhereHas('goodReceive', function ($q) use ($search) {
                            $q->where('gr_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('warehouse', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->from, function ($query, $from) {
                $query->where('process_date', '>=', $from);
            })
            ->when($request->to, function ($query, $to) {
                $query->where('process_date', '<=', $to);
            })
            ->latest();

        $butcherProcesses = $query->paginate(10)->withQueryString();
        $butcherProcesses->getCollection()->transform(function ($process) {
            return [
                'id' => $process->id,
                'number' => $process->number,
                'process_date' => $process->process_date,
                'created_at' => $process->created_at,
                'gr_number' => $process->goodReceive->gr_number ?? '-',
                'warehouse_name' => $process->warehouse->name ?? '-',
                'created_by_name' => $process->createdBy->nama_lengkap ?? '-',
                // tambahkan field lain yang dibutuhkan di tabel jika perlu
            ];
        });
        return Inertia::render('ButcherProcess/Index', [
            'butcherProcesses' => $butcherProcesses,
            'filters' => $request->only(['search', 'from', 'to'])
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::where('status', 'active')->get();
        $units = Unit::where('status', 'active')->get();
        
        // Get PCS items (items that can be produced from whole items)
        $pcsItems = Item::whereHas('category', function($q) {
            $q->where('show_pos', '0');
        })
            ->where('items.status', 'active')
            ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
            ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
            ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->select(
                'items.*',
                'small_unit.id as small_unit_id',
                'small_unit.name as small_unit_name',
                'medium_unit.id as medium_unit_id',
                'medium_unit.name as medium_unit_name',
                'large_unit.id as large_unit_id',
                'large_unit.name as large_unit_name',
                'categories.code as category_code'
            )
            ->orderBy('items.name', 'asc')
            ->get();

        // Filter out items that don't have any units configured
        $pcsItems = $pcsItems->filter(function($item) {
            return $item->small_unit_id || $item->medium_unit_id || $item->large_unit_id;
        });

        // Log items yang tidak memiliki unit untuk monitoring
        $itemsWithoutUnits = Item::whereHas('category', function($q) {
            $q->where('show_pos', '0');
        })
            ->where('items.status', 'active')
            ->whereNull('small_unit_id')
            ->whereNull('medium_unit_id')
            ->whereNull('large_unit_id')
            ->pluck('name', 'id');
        
        if ($itemsWithoutUnits->count() > 0) {
            \Log::warning('Items without units found:', $itemsWithoutUnits->toArray());
        }

        // Get good receives that have remaining quantity
        $goodReceives = FoodGoodReceive::whereHas('items', function($query) {
            $query->whereRaw('qty_received > COALESCE((
                SELECT SUM(bpi.whole_qty)
                FROM butcher_process_items bpi
                JOIN butcher_processes bp ON bp.id = bpi.butcher_process_id
                WHERE bp.good_receive_id = food_good_receives.id
                AND bpi.whole_item_id = food_good_receive_items.item_id
            ), 0)');
        })->get();

        return Inertia::render('ButcherProcess/Create', [
            'warehouses' => $warehouses,
            'units' => $units,
            'pcsItems' => $pcsItems,
            'goodReceives' => $goodReceives
        ]);
    }

    public function store(Request $request)
    {
        // Jika items dikirim sebagai string (karena FormData), decode dulu
        if (is_string($request->items)) {
            $request->merge([
                'items' => json_decode($request->items, true)
            ]);
        }
        // Jika certificates dikirim sebagai string (karena FormData), decode juga
        if (is_string($request->certificates)) {
            $request->merge([
                'certificates' => json_decode($request->certificates, true)
            ]);
        }
        // Mapping file upload ke certificates
        if ($request->hasFile('certificate_files')) {
            $certificateFiles = $request->file('certificate_files');
            $certificates = $request->certificates;
            foreach ($certificateFiles as $idx => $file) {
                if (isset($certificates[$idx])) {
                    $certificates[$idx]['file'] = $file;
                }
            }
            $request->merge(['certificates' => $certificates]);
        }
        // Mapping file upload ke items
        if ($request->hasFile('items_files')) {
            $itemsFiles = $request->file('items_files');
            $items = $request->items;
            foreach ($itemsFiles as $idx => $files) {
                foreach ($files as $key => $file) {
                    if (isset($items[$idx])) {
                        $items[$idx][$key] = $file;
                    }
                }
            }
            $request->merge(['items' => $items]);
        }
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'good_receive_id' => 'nullable|exists:food_good_receives,id',
            'items' => 'required|array|min:1',
            'items.*.whole_item_id' => 'required|exists:items,id',
            'items.*.pcs_item_id' => 'required|exists:items,id',
            'items.*.whole_qty' => 'required|numeric|min:0',
            'items.*.pcs_qty' => 'required|numeric|min:0',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.slaughter_date' => 'nullable|date',
            'items.*.packing_date' => 'nullable|date',
            'items.*.batch_est' => 'nullable|string|max:255',
            'items.*.qty_purchase' => 'nullable|numeric|min:0',
            'items.*.attachment_pdf' => 'nullable|file|mimes:pdf|max:2048',
            'items.*.upload_image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'certificates' => 'required|array|min:1',
            'certificates.*.producer_name' => 'required|string',
            'certificates.*.certificate_number' => 'required|string',
            'certificates.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();
            // Log::info('BUTCHER STORE: Mulai simpan butcher process', $request->all());

            // Generate butcher number
            $lastButcher = ButcherProcess::latest()->first();
            $number = 'BTR-' . date('Ymd') . '-' . str_pad(($lastButcher ? substr($lastButcher->number, -4) + 1 : 1), 4, '0', STR_PAD_LEFT);

            // Pastikan process_date tidak null
            $defaultProcessDate = $request->process_date ?: now();
            // Create butcher process
            $butcherProcess = ButcherProcess::create([
                'number' => $number,
                'process_date' => $defaultProcessDate,
                'warehouse_id' => $request->warehouse_id,
                'good_receive_id' => $request->good_receive_id,
                'created_by' => auth()->id(),
                'notes' => $request->notes
            ]);

            // 1. Hitung total cost butcher (MAC small cost x qty add to butcher dalam small unit)
            $itemMasterSample = null;
            $macSmallCost = 0;
            $qtyAddToButcherSmall = 0;
            foreach ($request->items as $item) {
                if (!$itemMasterSample) {
                    $itemMasterSample = \App\Models\Item::find($item['whole_item_id']);
                }
            }
            if ($itemMasterSample) {
                // Get price from purchase order
                $goodReceive = FoodGoodReceive::find($request->good_receive_id);
                if ($goodReceive) {
                    $poItem = DB::table('purchase_order_food_items')
                        ->join('food_good_receive_items', 'food_good_receive_items.po_item_id', '=', 'purchase_order_food_items.id')
                        ->where('food_good_receive_items.good_receive_id', $goodReceive->id)
                        ->where('food_good_receive_items.item_id', $itemMasterSample->id)
                        ->select('purchase_order_food_items.price', 'purchase_order_food_items.unit_id')
                        ->first();

                    if ($poItem) {
                        // Get conversion based on PO unit
                        $poUnitId = $poItem->unit_id;
                        $conversionValue = 1;

                        if ($poUnitId == $itemMasterSample->small_unit_id) {
                            $conversionValue = 1;
                        } elseif ($poUnitId == $itemMasterSample->medium_unit_id) {
                            $conversionValue = $itemMasterSample->small_conversion_qty;
                        } elseif ($poUnitId == $itemMasterSample->large_unit_id) {
                            $conversionValue = $itemMasterSample->small_conversion_qty * $itemMasterSample->medium_conversion_qty;
                        }

                        $macSmallCost = $poItem->price / $conversionValue;
                    }
                }
            }
            // Ambil total qty add to butcher (dalam small unit)
            $qtyAddToButcher = array_sum(array_column($request->items, 'whole_qty'));
            $unitInput = $request->items[0]['whole_unit'] ?? '';
            $smallConv = $itemMasterSample ? ($itemMasterSample->small_conversion_qty ?: 1) : 1;
            $mediumConv = $itemMasterSample ? ($itemMasterSample->medium_conversion_qty ?: 1) : 1;
            if ($unitInput === optional($itemMasterSample->smallUnit)->name) {
                $qtyAddToButcherSmall = $qtyAddToButcher;
            } elseif ($unitInput === optional($itemMasterSample->mediumUnit)->name) {
                $qtyAddToButcherSmall = $qtyAddToButcher * $smallConv;
            } elseif ($unitInput === optional($itemMasterSample->largeUnit)->name) {
                $qtyAddToButcherSmall = $qtyAddToButcher * $smallConv * $mediumConv;
            } else {
                $qtyAddToButcherSmall = $qtyAddToButcher;
            }
            $totalCost = $macSmallCost * $qtyAddToButcherSmall;

            // --- NEW MAC PCS LOGIC ---
            // 1. Sum qty_kg (PCS) yang cost 0 = false, konversi ke gram
            $sumQtyKgCostFalseGram = 0;
            foreach ($request->items as $item) {
                if (empty($item['costs_0'])) {
                    $qtyKg = isset($item['qty_kg']) ? $item['qty_kg'] : (isset($item['qty']) ? $item['qty'] : 0);
                    $sumQtyKgCostFalseGram += $qtyKg * 1000; // 1 kg = 1000 gram
                }
            }
            $costPerGram = $sumQtyKgCostFalseGram > 0 ? $totalCost / $sumQtyKgCostFalseGram : 0;
            // --- END NEW MAC PCS LOGIC ---

            // 3. Untuk setiap item PCS, hitung total cost dan cost per PCS, simpan ke detail
            foreach ($request->items as $idx => $item) {
                try {
                    // Ambil small_conversion_qty dari item PCS
                    $pcsItemModel = \App\Models\Item::find($item['pcs_item_id']);
                    $pcsSmallConv = $pcsItemModel ? ($pcsItemModel->small_conversion_qty ?: 1) : 1;
                    // Ambil mac_pcs dari request jika ada, fallback ke hasil hitung
                    $macPcs = isset($item['mac_pcs']) ? $item['mac_pcs'] : ($costPerGram * $pcsSmallConv);
                    $serialNumber = 'BTR-' . date('Ymd') . '-' . $butcherProcess->id . '-' . str_pad($idx+1, 3, '0', STR_PAD_LEFT);
                    $butcherItem = ButcherProcessItem::create([
                        'butcher_process_id' => $butcherProcess->id,
                        'whole_item_id' => $item['whole_item_id'],
                        'pcs_item_id' => $item['pcs_item_id'],
                        'whole_qty' => $item['whole_qty'],
                        'pcs_qty' => $item['pcs_qty'],
                        'unit_id' => $item['unit_id'],
                        'serial_number' => $serialNumber
                    ]);

                    // Handle file upload (PDF & image)
                    $attachmentPdfPath = null;
                    $uploadImagePath = null;
                    if (isset($item['attachment_pdf']) && $item['attachment_pdf']) {
                        $attachmentPdfPath = $item['attachment_pdf']->store('butcher_attachments/pdf', 'public');
                    }
                    if (isset($item['upload_image']) && $item['upload_image']) {
                        $uploadImagePath = $item['upload_image']->store('butcher_attachments/image', 'public');
                    }

                    // Create detail record
                    ButcherProcessItemDetail::create([
                        'butcher_process_item_id' => $butcherItem->id,
                        'slaughter_date' => $item['slaughter_date'] ?? null,
                        'packing_date' => $item['packing_date'] ?? null,
                        'batch_est' => $item['batch_est'] ?? null,
                        'qty_purchase' => $item['qty_purchase'] ?? null,
                        'qty_kg' => $item['qty_kg'] ?? null,
                        'costs_0' => isset($item['costs_0']) ? (bool)$item['costs_0'] : false,
                        'attachment_pdf' => $attachmentPdfPath,
                        'upload_image' => $uploadImagePath,
                        'susut_air_qty' => $item['susut_air']['qty'] ?? null,
                        'susut_air_unit' => $item['susut_air']['unit'] ?? null,
                        'mac_pcs' => $macPcs,
                    ]);

                    // Update inventory for pcs item (increase)
                    $pcsInventoryItem = FoodInventoryItem::where('item_id', $item['pcs_item_id'])->first();
                    $pcsInventory = FoodInventoryStock::firstOrCreate(
                        [
                            'inventory_item_id' => $pcsInventoryItem->id ?? 0,
                            'warehouse_id' => $request->warehouse_id
                        ],
                        ['qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0]
                    );
                    $pcsInventory->update([
                        'qty_small' => $pcsInventory->qty_small + $item['pcs_qty'],
                        'qty_medium' => $pcsInventory->qty_medium + $item['pcs_qty'],
                        'qty_large' => $pcsInventory->qty_large + $item['pcs_qty']
                    ]);
                    $saldo_qty_small_pcs = $pcsInventory->qty_small;
                    $saldo_qty_medium_pcs = $pcsInventory->qty_medium;
                    $saldo_qty_large_pcs = $pcsInventory->qty_large;
                    $saldo_value_pcs = ($saldo_qty_small_pcs * $macPcs)
                        + ($saldo_qty_medium_pcs * $macPcs)
                        + ($saldo_qty_large_pcs * $macPcs);
                    // Insert ke FoodInventoryCard (PCS)
                    \App\Models\FoodInventoryCard::create([
                        'inventory_item_id' => $pcsInventoryItem->id ?? null,
                        'warehouse_id' => $request->warehouse_id,
                        'date' => now()->toDateString(),
                        'reference_type' => 'butcher_process',
                        'reference_id' => $butcherProcess->id,
                        'in_qty_small' => $item['pcs_qty'],
                        'in_qty_medium' => $item['pcs_qty'],
                        'in_qty_large' => $item['pcs_qty'],
                        'cost_per_small' => $macPcs,
                        'value_in' => $item['pcs_qty'] * $macPcs,
                        'saldo_qty_small' => $saldo_qty_small_pcs,
                        'saldo_qty_medium' => $saldo_qty_medium_pcs,
                        'saldo_qty_large' => $saldo_qty_large_pcs,
                        'saldo_value' => $saldo_value_pcs,
                        'description' => 'Hasil potong PCS',
                    ]);
                    // Cost history jika ada perubahan MAC pada item PCS
                    if (empty($item['costs_0'])) {
                        \DB::table('food_inventory_cost_histories')->insert([
                            'inventory_item_id' => $pcsInventoryItem->id ?? null,
                            'warehouse_id' => $request->warehouse_id,
                            'date' => now()->toDateString(),
                            'old_cost' => 0,
                            'new_cost' => $macPcs,
                            'mac' => $macPcs,
                            'type' => 'butcher_process',
                            'reference_type' => 'butcher_process',
                            'reference_id' => $butcherProcess->id,
                            'created_at' => now(),
                        ]);
                    }
                    // Setelah insert detail

                } catch (\Exception $e) {
                    Log::error('BUTCHER STORE: ERROR proses item', ['idx' => $idx, 'item' => $item, 'error' => $e->getMessage()]);
                    throw $e;
                }
            }

            // Store certificates
            foreach ($request->certificates as $certificate) {
                $path = $certificate['file']->store('certificates', 'public');
                
                ButcherHalalCertificate::create([
                    'butcher_process_id' => $butcherProcess->id,
                    'producer_name' => $certificate['producer_name'],
                    'certificate_number' => $certificate['certificate_number'],
                    'file_path' => $path
                ]);
            }

            DB::commit();

            // Activity log CREATE
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'create',
                'module' => 'butcher_process',
                'description' => 'Membuat butcher process: ' . $butcherProcess->number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $butcherProcess->toArray(),
            ]);


            // Jika request expects JSON (AJAX/axios)
            if ($request->expectsJson() || $request->wantsJson() || $request->isJson() || $request->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => true,
                    'id' => $butcherProcess->id,
                    'redirect' => route('butcher-processes.show', $butcherProcess->id)
                ]);
            }
            // Jika request biasa (form biasa)
            return redirect()->route('butcher-processes.show', $butcherProcess->id)
                ->with('success', 'Butcher process created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ButcherProcess store error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $butcherProcess = ButcherProcess::with([
            'warehouse',
            'goodReceive.supplier',
            'createdBy',
            'items.wholeItem',
            'items.pcsItem',
            'items.unit',
            'items.details',
            'certificates'
        ])->findOrFail($id);


        // Mapping manual nama item agar selalu muncul di frontend
        $butcherProcess->items->transform(function ($item) {
            $item->whole_item_name = $item->wholeItem ? $item->wholeItem->name : null;
            $item->pcs_item_name = $item->pcsItem ? $item->pcsItem->name : null;
            // Ambil small_conversion_qty dari relasi pcsItem
            $item->small_conversion_qty = $item->pcsItem ? $item->pcsItem->small_conversion_qty : null;
            // Ambil exp dari relasi pcsItem untuk expire date calculation
            $item->pcs_item_exp = $item->pcsItem ? $item->pcsItem->exp : null;
            // Ambil hanya satu detail (paling awal)
            $item->details = collect($item->details)->take(1)->values();
            return $item;
        });

        // Tambahkan field created_by_nama_lengkap dan gr_number manual jika relasi ada
        $butcherProcess->created_by_nama_lengkap = $butcherProcess->createdBy ? $butcherProcess->createdBy->nama_lengkap : null;
        $butcherProcess->gr_number = $butcherProcess->goodReceive ? $butcherProcess->goodReceive->gr_number : null;

        return Inertia::render('ButcherProcess/Show', [
            'butcherProcess' => $butcherProcess
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $butcher = ButcherProcess::with(['items', 'items.details'])->findOrFail($id);
            $warehouseId = $butcher->warehouse_id;

            // Rollback stok dan kartu stok
            foreach ($butcher->items as $item) {
                // Rollback stok whole (tambah kembali)
                $inventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item->whole_item_id)->first();
                if ($inventoryItem) {
                    $stock = \App\Models\FoodInventoryStock::where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $warehouseId)->first();
                    if ($stock) {
                        $stock->qty_small += $item->whole_qty;
                        $stock->save();
                    }
                    // Hapus kartu stok OUT whole
                    \App\Models\FoodInventoryCard::where('reference_type', 'butcher_process')
                        ->where('reference_id', $butcher->id)
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->delete();
                }
                // Rollback stok PCS (kurangi kembali)
                $pcsInventoryItem = \App\Models\FoodInventoryItem::where('item_id', $item->pcs_item_id)->first();
                if ($pcsInventoryItem) {
                    $pcsStock = \App\Models\FoodInventoryStock::where('inventory_item_id', $pcsInventoryItem->id)
                        ->where('warehouse_id', $warehouseId)->first();
                    if ($pcsStock) {
                        $pcsStock->qty_small -= $item->pcs_qty;
                        $pcsStock->save();
                    }
                    // Hapus kartu stok IN PCS
                    \App\Models\FoodInventoryCard::where('reference_type', 'butcher_process')
                        ->where('reference_id', $butcher->id)
                        ->where('inventory_item_id', $pcsInventoryItem->id)
                        ->delete();
                }
            }
            // Hapus detail, item, certificate
            foreach ($butcher->items as $item) {
                $item->details()->delete();
            }
            $butcher->items()->delete();
            $butcher->certificates()->delete();
            $butcher->delete();
            // Activity log DELETE
            \App\Models\ActivityLog::create([
                'user_id' => auth()->id(),
                'activity_type' => 'delete',
                'module' => 'butcher_process',
                'description' => 'Menghapus butcher process: ' . ($butcher->number ?? $id),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => $butcher->toArray(),
                'new_data' => null,
            ]);
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
} 