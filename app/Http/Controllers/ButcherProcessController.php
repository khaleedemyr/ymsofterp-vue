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
        // Ambil semua item PCS dan Whole yang status aktif dan kategori show_pos = 0
        $pcsItems = Item::join('categories', 'items.category_id', '=', 'categories.id')
            ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
            ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
            ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
            ->where('items.status', 'active')
            ->where('categories.show_pos', '0')
            ->select(
                'items.*',
                'small_unit.name as unit_small',
                'small_unit.id as small_unit_id',
                'medium_unit.name as unit_medium',
                'medium_unit.id as medium_unit_id',
                'large_unit.name as unit_large',
                'large_unit.id as large_unit_id'
            )
            ->orderBy('items.name', 'asc')
            ->get();
        $wholeItems = Item::join('categories', 'items.category_id', '=', 'categories.id')
            ->leftJoin('units as small_unit', 'items.small_unit_id', '=', 'small_unit.id')
            ->leftJoin('units as medium_unit', 'items.medium_unit_id', '=', 'medium_unit.id')
            ->leftJoin('units as large_unit', 'items.large_unit_id', '=', 'large_unit.id')
            ->where('items.status', 'active')
            ->where('categories.show_pos', '0')
            ->select(
                'items.*',
                'small_unit.name as unit_small',
                'small_unit.id as small_unit_id',
                'medium_unit.name as unit_medium',
                'medium_unit.id as medium_unit_id',
                'large_unit.name as unit_large',
                'large_unit.id as large_unit_id'
            )
            ->orderBy('items.name', 'asc')
            ->get();
        return Inertia::render('ButcherProcess/Create', [
            'warehouses' => Warehouse::all(),
            'wholeItems' => $wholeItems,
            'pcsItems' => $pcsItems,
            'units' => Unit::all(),
            'goodReceives' => FoodGoodReceive::with('supplier')->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
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

            // 1. Hitung total whole_qty per whole_item_id
            $wholeQtyMap = [];
            $wholeUnitMap = [];
            foreach ($request->items as $item) {
                if (!isset($wholeQtyMap[$item['whole_item_id']])) {
                    $wholeQtyMap[$item['whole_item_id']] = $item['whole_qty'];
                    $wholeUnitMap[$item['whole_item_id']] = $item['whole_unit'];
                }
            }
            // 2. Update stok whole dan insert kartu stok hanya sekali per whole_item_id
            $wholeInventoryMap = [];
            foreach ($wholeQtyMap as $whole_item_id => $total_whole_qty) {
                $itemMaster = \App\Models\Item::find($whole_item_id);
                $inventoryItem = FoodInventoryItem::where('item_id', $whole_item_id)->first();
                $wholeInventory = FoodInventoryStock::where('inventory_item_id', $inventoryItem->id ?? 0)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->first();
                $unitSmall = optional($itemMaster->smallUnit)->name;
                $unitMedium = optional($itemMaster->mediumUnit)->name;
                $unitLarge = optional($itemMaster->largeUnit)->name;
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $unitName = $wholeUnitMap[$whole_item_id];
                $qty_small = 0; $qty_medium = 0; $qty_large = 0;
                if ($unitName === $unitSmall) {
                    $qty_small = $total_whole_qty;
                    $qty_medium = $smallConv > 0 ? $qty_small / $smallConv : 0;
                    $qty_large = ($smallConv > 0 && $mediumConv > 0) ? $qty_small / ($smallConv * $mediumConv) : 0;
                } elseif ($unitName === $unitMedium) {
                    $qty_medium = $total_whole_qty;
                    $qty_small = $qty_medium * $smallConv;
                    $qty_large = $mediumConv > 0 ? $qty_medium / $mediumConv : 0;
                } elseif ($unitName === $unitLarge) {
                    $qty_large = $total_whole_qty;
                    $qty_medium = $qty_large * $mediumConv;
                    $qty_small = $qty_medium * $smallConv;
                } else {
                    $qty_small = $total_whole_qty;
                }
                // Update stok whole
                $wholeInventory->qty_small -= $qty_small;
                $wholeInventory->qty_medium -= $qty_medium;
                $wholeInventory->qty_large -= $qty_large;
                $wholeInventory->value = $wholeInventory->qty_small * $wholeInventory->last_cost_small;
                $wholeInventory->last_cost_small = $wholeInventory->last_cost_small;
                $wholeInventory->last_cost_medium = $wholeInventory->last_cost_medium;
                $wholeInventory->last_cost_large = $wholeInventory->last_cost_large;
                $wholeInventory->save();
                // Simpan saldo setelah update untuk kartu stok
                $wholeInventoryMap[$whole_item_id] = [
                    'qty_small' => $wholeInventory->qty_small,
                    'qty_medium' => $wholeInventory->qty_medium,
                    'qty_large' => $wholeInventory->qty_large,
                    'last_cost_small' => $wholeInventory->last_cost_small,
                    'last_cost_medium' => $wholeInventory->last_cost_medium,
                    'last_cost_large' => $wholeInventory->last_cost_large,
                ];
                // Insert kartu stok OUT whole
                \App\Models\FoodInventoryCard::create([
                    'inventory_item_id' => $inventoryItem->id,
                    'warehouse_id' => $request->warehouse_id,
                    'date' => now()->toDateString(),
                    'reference_type' => 'butcher_process',
                    'reference_id' => $butcherProcess->id,
                    'out_qty_small' => $qty_small,
                    'out_qty_medium' => $qty_medium,
                    'out_qty_large' => $qty_large,
                    'cost_per_small' => $wholeInventory->last_cost_small,
                    'cost_per_medium' => $wholeInventory->last_cost_medium,
                    'cost_per_large' => $wholeInventory->last_cost_large,
                    'value_out' => $qty_small * $wholeInventory->last_cost_small,
                    'saldo_qty_small' => $wholeInventory->qty_small,
                    'saldo_qty_medium' => $wholeInventory->qty_medium,
                    'saldo_qty_large' => $wholeInventory->qty_large,
                    'saldo_value' => $wholeInventory->qty_small * $wholeInventory->last_cost_small,
                    'description' => 'Pemotongan whole item',
                ]);
                Log::info('DEBUG UPDATE WHOLE STOCK FINAL', [
                    'whole_item_id' => $whole_item_id,
                    'total_whole_qty' => $total_whole_qty,
                    'unitName' => $unitName,
                    'qty_small' => $qty_small,
                    'qty_medium' => $qty_medium,
                    'qty_large' => $qty_large,
                    'after_qty_small' => $wholeInventory->qty_small,
                    'after_qty_medium' => $wholeInventory->qty_medium,
                    'after_qty_large' => $wholeInventory->qty_large,
                ]);
            }

            // Store items and update inventory
            foreach ($request->items as $item) {
                // Create butcher process item
                $butcherItem = ButcherProcessItem::create([
                    'butcher_process_id' => $butcherProcess->id,
                    'whole_item_id' => $item['whole_item_id'],
                    'pcs_item_id' => $item['pcs_item_id'],
                    'whole_qty' => $item['whole_qty'],
                    'pcs_qty' => $item['pcs_qty'],
                    'unit_id' => $item['unit_id']
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

                // Ambil MAC whole (last_cost_small) dari food_inventory_stocks
                $inventoryItem = FoodInventoryItem::where('item_id', $item['whole_item_id'])->first();
                $stock = FoodInventoryStock::where('inventory_item_id', $inventoryItem->id)
                    ->where('warehouse_id', $request->warehouse_id)->first();
                $macWhole = $stock ? $stock->last_cost_small : 0;

                // Konversi qty whole ke small unit
                $itemMaster = \App\Models\Item::find($item['whole_item_id']);
                $unitInput = $item['whole_unit'];
                $qtyInput = $item['whole_qty'];
                $smallConv = $itemMaster->small_conversion_qty ?: 1;
                $mediumConv = $itemMaster->medium_conversion_qty ?: 1;
                $qtySmall = 0;
                if ($unitInput === optional($itemMaster->smallUnit)->name) {
                    $qtySmall = $qtyInput;
                } elseif ($unitInput === optional($itemMaster->mediumUnit)->name) {
                    $qtySmall = $qtyInput * $smallConv;
                } elseif ($unitInput === optional($itemMaster->largeUnit)->name) {
                    $qtySmall = $qtyInput * $smallConv * $mediumConv;
                } else {
                    $qtySmall = $qtyInput;
                }
                $totalCost = $qtySmall * $macWhole;

                // Hitung total qty PCS yang cost 0-nya tidak dicentang
                $totalQtyPcsCost = 0;
                foreach ($request->items as $i) {
                    if (empty($i['costs_0'])) {
                        $totalQtyPcsCost += $i['pcs_qty'];
                    }
                }
                // Alokasikan cost ke PCS
                $costPerPcs = 0;
                if (empty($item['costs_0']) && $totalQtyPcsCost > 0) {
                    $costPerPcs = $totalCost / $totalQtyPcsCost;
                }
                // Simpan cost per PCS ke detail (misal: mac_pcs)
                $detail = \App\Models\ButcherProcessItemDetail::where('butcher_process_item_id', $butcherItem->id)->first();
                if ($detail) {
                    $detail->mac_pcs = $costPerPcs;
                    $detail->save();
                }

                // Tambahkan konversi satuan PCS sebelum insert FoodInventoryCard untuk PCS
                $pcsItemMaster = \App\Models\Item::find($item['pcs_item_id']);
                $pcsSmallConv = $pcsItemMaster->small_conversion_qty ?: 1;
                $pcsMediumConv = $pcsItemMaster->medium_conversion_qty ?: 1;
                $pcs_qty_small = 0; $pcs_qty_medium = 0; $pcs_qty_large = 0;
                if ($item['unit_id'] == $pcsItemMaster->small_unit_id) {
                    $pcs_qty_small = $item['pcs_qty'];
                    $pcs_qty_medium = $pcsSmallConv > 0 ? $pcs_qty_small / $pcsSmallConv : 0;
                    $pcs_qty_large = ($pcsSmallConv > 0 && $pcsMediumConv > 0) ? $pcs_qty_small / ($pcsSmallConv * $pcsMediumConv) : 0;
                } elseif ($item['unit_id'] == $pcsItemMaster->medium_unit_id) {
                    $pcs_qty_medium = $item['pcs_qty'];
                    $pcs_qty_small = $pcs_qty_medium * $pcsSmallConv;
                    $pcs_qty_large = $pcsMediumConv > 0 ? $pcs_qty_medium / $pcsMediumConv : 0;
                } elseif ($item['unit_id'] == $pcsItemMaster->large_unit_id) {
                    $pcs_qty_large = $item['pcs_qty'];
                    $pcs_qty_medium = $pcs_qty_large * $pcsMediumConv;
                    $pcs_qty_small = $pcs_qty_medium * $pcsSmallConv;
                } else {
                    $pcs_qty_small = $item['pcs_qty'];
                }
                // Insert ke FoodInventoryCard
                \App\Models\FoodInventoryCard::create([
                    'inventory_item_id' => $pcsInventoryItem->id ?? null,
                    'warehouse_id' => $request->warehouse_id,
                    'date' => now()->toDateString(),
                    'reference_type' => 'butcher_process',
                    'reference_id' => $butcherProcess->id,
                    'in_qty_small' => $pcs_qty_small,
                    'in_qty_medium' => $pcs_qty_medium,
                    'in_qty_large' => $pcs_qty_large,
                    'cost_per_small' => $costPerPcs ?? 0,
                    'value_in' => $pcs_qty_small * ($costPerPcs ?? 0),
                    'saldo_qty_small' => $pcsInventory->qty_small,
                    'saldo_qty_medium' => $pcsInventory->qty_medium,
                    'saldo_qty_large' => $pcsInventory->qty_large,
                    'saldo_value' => $pcsInventory->qty_small * ($costPerPcs ?? 0),
                    'description' => 'Hasil potong PCS',
                ]);
                // Cost history jika ada perubahan MAC pada item PCS
                if (isset($costPerPcs) && $costPerPcs > 0) {
                    \DB::table('food_inventory_cost_histories')->insert([
                        'inventory_item_id' => $pcsInventoryItem->id ?? null,
                        'warehouse_id' => $request->warehouse_id,
                        'date' => now()->toDateString(),
                        'old_cost' => 0, // update jika ada histori sebelumnya
                        'new_cost' => $costPerPcs,
                        'mac' => $costPerPcs,
                        'type' => 'butcher_process',
                        'reference_type' => 'butcher_process',
                        'reference_id' => $butcherProcess->id,
                        'created_at' => now(),
                    ]);
                }
                // Setelah insert detail
                Log::info('DEBUG DETAIL', [
                    'butcher_process_item_id' => $butcherItem->id,
                    'detail' => $detail ?? null
                ]);

                Log::info('DEBUG STOCK', [
                    'unitName_input' => $unitName,
                    'smallUnit' => optional($inventoryItem->smallUnit)->name,
                    'mediumUnit' => optional($inventoryItem->mediumUnit)->name,
                    'largeUnit' => optional($inventoryItem->largeUnit)->name,
                    'qty_small' => $pcsInventory->qty_small ?? null,
                    'qty_medium' => $pcsInventory->qty_medium ?? null,
                    'qty_large' => $pcsInventory->qty_large ?? null,
                    'item_whole_qty' => $item['whole_qty'],
                    'inventory_item_id' => $inventoryItem->id ?? null,
                    'warehouse_id' => $request->warehouse_id,
                ]);
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

            Log::info('ButcherProcess created', ['butcherProcess' => $butcherProcess]);

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