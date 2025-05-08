<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Models\WarehouseDivision;
use App\Models\ActivityLog;
use App\Models\Modifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Exports\ItemsExport;
use Maatwebsite\Excel\Facades\Excel;
use PDF;
use App\Exports\ItemsImportTemplateExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with(['category', 'subCategory', 'smallUnit', 'mediumUnit', 'largeUnit', 'images', 'itemModifierOptions', 'boms', 'prices', 'availabilities']);

        // Filter search (by name or SKU)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('sku', 'like', "%$search%");
            });
        }

        // Filter category
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $items = $query->orderBy('created_at', 'desc')->paginate(10);
        $units = Unit::all();
        $bomItems = \DB::table('items')->where('composition_type', 'single')->where('status', 'active')->orderBy('name')->get();
        $regions = \DB::table('regions')->where('status', 'active')->get()->keyBy('id');
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->keyBy('id_outlet');
        $itemsArr = $items->toArray();
        $itemsArr['data'] = collect($itemsArr['data'])->map(function($item) use ($units, $bomItems, $regions, $outlets) {
            // Map modifier_option_ids
            $item['modifier_option_ids'] = collect($item['item_modifier_options'] ?? [])->pluck('modifier_option_id')->toArray();
            // Map BOM
            $item['bom'] = collect($item['boms'] ?? [])->map(function($b) use ($bomItems, $units) {
                $itemName = $b['material_item_id'] ? optional($bomItems->firstWhere('id', $b['material_item_id']))->name : null;
                $unitName = $b['unit_id'] ? optional($units->firstWhere('id', $b['unit_id']))->name : null;
                return [
                    'item_id' => $b['material_item_id'],
                    'item_name' => $itemName,
                    'qty' => $b['qty'],
                    'unit_id' => $b['unit_id'],
                    'unit_name' => $unitName,
                ];
            })->toArray();
            // Map prices
            $item['prices'] = collect($item['prices'] ?? [])->map(function($p) use ($regions, $outlets) {
                $regionId = $p['region_id'] !== null ? (int) $p['region_id'] : null;
                $outletId = $p['outlet_id'] !== null ? (int) $p['outlet_id'] : null;
                $regionName = $regionId ? optional($regions->get($regionId))->name : null;
                $outletName = $outletId ? optional($outlets->get($outletId))->nama_outlet : null;
                if ($regionId && !$regions->has($regionId)) {
                    \Log::warning('Region ID not found in master', [$regionId]);
                }
                if ($outletId && !$outlets->has($outletId)) {
                    \Log::warning('Outlet ID not found in master', [$outletId]);
                }
                if ($regionName) {
                    $label = $regionName;
                } elseif ($outletName) {
                    $label = $outletName;
                } else {
                    $label = 'All';
                }
                return [
                    'region_id' => $regionId,
                    'region_name' => $regionName,
                    'outlet_id' => $outletId,
                    'outlet_name' => $outletName,
                    'label' => $label,
                    'price' => $p['price'],
                ];
            })->toArray();
            // Map availabilities
            $item['availabilities'] = collect($item['availabilities'] ?? [])->map(function($a) use ($regions, $outlets) {
                $regionId = $a['region_id'] !== null ? (int) $a['region_id'] : null;
                $outletId = $a['outlet_id'] !== null ? (int) $a['outlet_id'] : null;
                $regionName = $regionId ? optional($regions->get($regionId))->name : null;
                $outletName = $outletId ? optional($outlets->get($outletId))->nama_outlet : null;
                if ($regionId && !$regions->has($regionId)) {
                    \Log::warning('Region ID not found in master (avail)', [$regionId]);
                }
                if ($outletId && !$outlets->has($outletId)) {
                    \Log::warning('Outlet ID not found in master (avail)', [$outletId]);
                }
                if ($regionName) {
                    $label = $regionName;
                } elseif ($outletName) {
                    $label = $outletName;
                } else {
                    $label = 'All';
                }
                return [
                    'region_id' => $regionId,
                    'region_name' => $regionName,
                    'outlet_id' => $outletId,
                    'outlet_name' => $outletName,
                    'label' => $label,
                    'availability_type' => $a['availability_type'] ?? null,
                ];
            })->toArray();
            return $item;
        })->toArray();
        $categories = Category::all();
        $subCategories = SubCategory::where('status', 'active')->get();
        $warehouseDivisions = WarehouseDivision::where('status', 'active')->get();
        $menuTypes = \DB::table('menu_type')->where('status', 'active')->get();
        $modifiers = Modifier::with('options')->get();
        return Inertia::render('Items/Index', [
            'items' => $itemsArr,
            'categories' => $categories,
            'subCategories' => $subCategories,
            'units' => $units,
            'warehouseDivisions' => $warehouseDivisions,
            'menuTypes' => $menuTypes,
            'regions' => $regions,
            'outlets' => $outlets,
            'bomItems' => $bomItems,
            'modifiers' => $modifiers,
        ]);
    }

    public function create()
    {
        $categories = Category::all();
        $subCategories = SubCategory::all();
        $units = Unit::all();
        $regions = \DB::table('regions')->where('status', 'active')->get()->values();
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->values();
        return Inertia::render('Items/Create', [
            'categories' => $categories,
            'subCategories' => $subCategories,
            'units' => $units,
            'regions' => $regions,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('ItemController@store - Starting store method');
        \Log::info('ItemController@store - request', $request->all());
        
        try {
            \Log::info('ItemController@store - Starting validation');
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'sub_category_id' => 'nullable|exists:sub_categories,id',
                'warehouse_division_id' => 'nullable|string|max:255',
                'sku' => 'required|string|max:255|unique:items',
                'type' => 'nullable|in:Food,Beverages,Mod',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'specification' => 'nullable|string',
                'small_unit_id' => 'required|exists:units,id',
                'medium_unit_id' => 'required|exists:units,id',
                'large_unit_id' => 'required|exists:units,id',
                'medium_conversion_qty' => 'required|numeric|min:0',
                'small_conversion_qty' => 'required|numeric|min:0',
                'min_stock' => 'nullable|integer|min:0',
                'status' => 'required|string|in:active,inactive',
                'composition_type' => 'required|in:single,composed',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'modifier_enabled' => 'nullable|boolean',
                'modifier_option_ids' => 'nullable|array',
                'modifier_option_ids.*' => 'exists:modifier_options,id',
                'prices' => 'nullable|array',
                'prices.*.region_id' => 'nullable|exists:regions,id',
                'prices.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
                'prices.*.price' => 'required|numeric|min:0',
                'availabilities' => 'nullable|array',
                'availabilities.*.region_id' => 'nullable|exists:regions,id',
                'availabilities.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
                'availabilities.*.status' => 'required|in:available,unavailable',
            ]);
            \Log::info('ItemController@store - Validation passed', $validated);

            DB::beginTransaction();
            \Log::info('ItemController@store - Transaction started');

            \Log::info('ItemController@store - Creating item');
            $item = Item::create(array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
            ]));
            \Log::info('ItemController@store - Item created', ['item_id' => $item->id]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                \Log::info('ItemController@store - Processing images');
                foreach ($request->file('images') as $image) {
                    $path = $image->store('items', 'public');
                    $item->images()->create(['path' => $path]);
                }
                \Log::info('ItemController@store - Images processed');
            }

            // Simpan relasi modifier_option jika enabled
            if ($item->modifier_enabled && $request->modifier_option_ids) {
                \Log::info('ItemController@store - Processing modifier options');
                $item->modifierOptions()->sync($request->modifier_option_ids);
                \Log::info('ItemController@store - Modifier options processed');
            }

            // Simpan BOM jika composition_type = 'composed'
            if ($request->composition_type === 'composed' && $request->bom) {
                \Log::info('ItemController@store - Processing BOM');
                foreach ($request->bom as $bom) {
                    $item->boms()->create([
                        'material_item_id' => $bom['item_id'],
                        'qty' => $bom['qty'],
                        'unit_id' => $bom['unit_id'],
                    ]);
                }
                \Log::info('ItemController@store - BOM processed');
            }

            // Simpan harga per region/outlet
            if ($request->prices) {
                \Log::info('ItemController@store - Processing prices');
                foreach ($request->prices as $price) {
                    $type = 'all';
                    if (!empty($price['region_id']) && empty($price['outlet_id'])) {
                        $type = 'region';
                    } else if (!empty($price['outlet_id'])) {
                        $type = 'outlet';
                    }
                    $item->prices()->create([
                        'region_id' => $price['region_id'],
                        'outlet_id' => $price['outlet_id'],
                        'price' => $price['price'],
                        'availability_price_type' => $type,
                    ]);
                }
                \Log::info('ItemController@store - Prices processed');
            }

            // Simpan availability per region/outlet
            if ($request->availabilities) {
                \Log::info('ItemController@store - Processing availabilities');
                foreach ($request->availabilities as $availability) {
                    $type = 'all';
                    if (!empty($availability['region_id']) && empty($availability['outlet_id'])) {
                        $type = 'region';
                    } else if (!empty($availability['outlet_id'])) {
                        $type = 'outlet';
                    }
                    $item->availabilities()->create([
                        'region_id' => $availability['region_id'],
                        'outlet_id' => $availability['outlet_id'],
                        'availability_type' => $type,
                    ]);
                }
                \Log::info('ItemController@store - Availabilities processed');
            }

            // Log activity
            \Log::info('ItemController@store - Creating activity log');
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'items',
                'description' => 'Membuat item baru: ' . $item->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $item->toArray()
            ]);
            \Log::info('ItemController@store - Activity log created');

            DB::commit();
            \Log::info('ItemController@store - Transaction committed');
            return redirect()->back()->with('success', 'Item berhasil ditambahkan!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ItemController@store - Error occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return redirect()->back()->withErrors(['error' => 'Gagal menambah item: ' . $e->getMessage()]);
        }
    }

    public function edit(Item $item)
    {
        $categories = Category::all();
        $subCategories = SubCategory::all();
        $units = Unit::all();
        $warehouseDivisions = WarehouseDivision::where('status', 'active')->get();
        $menuTypes = \DB::table('menu_type')->where('status', 'active')->get();
        $regions = \DB::table('regions')->where('status', 'active')->get()->keyBy('id');
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->keyBy('id_outlet');
        $bomItems = \DB::table('items')->where('composition_type', 'single')->where('status', 'active')->orderBy('name')->get();
        $modifiers = Modifier::with('options')->get();
        
        $item->load([
            'images',
            'prices',
            'availabilities',
            'modifierOptions',
            'boms',
            'category',
            'subCategory',
            'smallUnit',
            'mediumUnit',
            'largeUnit',
            'warehouseDivision',
        ]);
        $itemData = $item->toArray();
        // Map prices with region/outlet name
        $itemData['prices'] = $item->prices->map(function($p) use ($regions, $outlets) {
            $regionName = $p->region_id ? optional($regions->get($p->region_id))->name : null;
            $outletName = $p->outlet_id ? optional($outlets->get($p->outlet_id))->nama_outlet : null;
            if ($regionName) {
                $label = $regionName;
            } elseif ($outletName) {
                $label = $outletName;
            } else {
                $label = 'All';
            }
            return [
                'region_id' => $p->region_id,
                'region_name' => $regionName,
                'outlet_id' => $p->outlet_id,
                'outlet_name' => $outletName,
                'label' => $label,
                'price' => $p->price,
            ];
        })->toArray();
        // Map availabilities with region/outlet name
        $itemData['availabilities'] = $item->availabilities->map(function($a) use ($regions, $outlets) {
            $regionName = $a->region_id ? optional($regions->firstWhere('id', $a->region_id))->name : null;
            $outletName = $a->outlet_id ? optional($outlets->firstWhere('id_outlet', $a->outlet_id))->nama_outlet : null;
            return [
                'region_id' => $a->region_id,
                'region_name' => $regionName,
                'outlet_id' => $a->outlet_id,
                'outlet_name' => $outletName,
                'status' => $a->availability_type,
            ];
        })->toArray();
        // Map BOM with item/unit name
        $itemData['bom'] = $item->boms->map(function($b) use ($bomItems, $units) {
            $itemName = $b->material_item_id ? optional($bomItems->firstWhere('id', $b->material_item_id))->name : null;
            $unitName = $b->unit_id ? optional($units->firstWhere('id', $b->unit_id))->name : null;
            return [
                'item_id' => $b->material_item_id,
                'item_name' => $itemName,
                'qty' => $b->qty,
                'unit_id' => $b->unit_id,
                'unit_name' => $unitName,
            ];
        })->toArray();
        // Modifier options with name
        $itemData['modifier_option_ids'] = $item->modifierOptions->pluck('id')->toArray();
        $itemData['modifiers'] = $modifiers->map(function($mod) use ($item) {
            $selectedOptions = $mod->options->whereIn('id', $item->modifierOptions->pluck('id')->toArray());
            return [
                'id' => $mod->id,
                'name' => $mod->name,
                'options' => $selectedOptions->map(function($opt) {
                    return [
                        'id' => $opt->id,
                        'name' => $opt->name,
                    ];
                })->values()->all(),
            ];
        })->filter(fn($m) => count($m['options']) > 0)->values()->all();
        return Inertia::render('Items/Edit', [
            'item' => $itemData,
            'categories' => $categories,
            'subCategories' => $subCategories,
            'units' => $units,
            'warehouseDivisions' => $warehouseDivisions,
            'menuTypes' => $menuTypes,
            'regions' => $regions,
            'outlets' => $outlets,
            'bomItems' => $bomItems,
            'modifiers' => $modifiers,
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'warehouse_division_id' => 'nullable|string|max:255',
            'sku' => 'required|string|max:255|unique:items,sku,' . $item->id,
            'type' => 'nullable|in:Food,Beverages,Mod',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'specification' => 'nullable|string',
            'small_unit_id' => 'required|exists:units,id',
            'medium_unit_id' => 'nullable|exists:units,id',
            'large_unit_id' => 'nullable|exists:units,id',
            'medium_conversion_qty' => 'nullable|numeric|min:0',
            'small_conversion_qty' => 'nullable|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
            'status' => 'required|string|in:active,inactive',
            'composition_type' => 'required|in:single,composed',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'modifier_enabled' => 'nullable|boolean',
            'modifier_option_ids' => 'nullable|array',
            'modifier_option_ids.*' => 'exists:modifier_options,id',
            'prices' => 'nullable|array',
            'prices.*.region_id' => 'nullable|exists:regions,id',
            'prices.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'prices.*.price' => 'required|numeric|min:0',
            'availabilities' => 'nullable|array',
            'availabilities.*.region_id' => 'nullable|exists:regions,id',
            'availabilities.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
        ]);

        try {
            DB::beginTransaction();

            $oldData = $item->toArray();
            $item->update(array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
            ]));

            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('items', 'public');
                    $item->images()->create(['path' => $path]);
                }
            }

            // Simpan relasi modifier_option jika enabled
            if ($item->modifier_enabled && $request->modifier_option_ids) {
                $item->modifierOptions()->sync($request->modifier_option_ids);
            } else {
                $item->modifierOptions()->detach();
            }

            // Update harga per region/outlet
            $item->prices()->delete();
            if ($request->prices) {
                foreach ($request->prices as $price) {
                    $type = 'all';
                    if (!empty($price['region_id']) && empty($price['outlet_id'])) {
                        $type = 'region';
                    } else if (!empty($price['outlet_id'])) {
                        $type = 'outlet';
                    }
                    $item->prices()->create([
                        'region_id' => $price['region_id'],
                        'outlet_id' => $price['outlet_id'],
                        'price' => $price['price'],
                        'availability_price_type' => $type,
                    ]);
                }
            }

            // Update availability per region/outlet
            $item->availabilities()->delete();
            if ($request->availabilities) {
                foreach ($request->availabilities as $availability) {
                    $type = 'all';
                    if (!empty($availability['region_id']) && empty($availability['outlet_id'])) {
                        $type = 'region';
                    } else if (!empty($availability['outlet_id'])) {
                        $type = 'outlet';
                    }
                    $item->availabilities()->create([
                        'region_id' => $availability['region_id'],
                        'outlet_id' => $availability['outlet_id'],
                        'availability_type' => $type,
                    ]);
                }
            }

            // Update BOM jika composition_type = 'composed'
            $item->boms()->delete();
            if ($request->composition_type === 'composed' && $request->bom) {
                foreach ($request->bom as $bom) {
                    $item->boms()->create([
                        'material_item_id' => $bom['item_id'],
                        'qty' => $bom['qty'],
                        'unit_id' => $bom['unit_id'],
                    ]);
                }
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'items',
                'description' => 'Mengupdate item: ' . $item->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $oldData,
                'new_data' => $item->fresh()->toArray()
            ]);

            DB::commit();
            return redirect()->route('items.index')->with('success', 'Item updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update item: ' . $e->getMessage());
        }
    }

    public function destroy(Item $item)
    {
        try {
            DB::beginTransaction();

            $oldData = $item->toArray();
            $item->status = 'inactive';
            $item->save();

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'items',
                'description' => 'Menonaktifkan item: ' . $item->name,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => $oldData,
                'new_data' => $item->fresh()->toArray()
            ]);

            DB::commit();
            return redirect()->route('items.index')->with('success', 'Item set to inactive successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to set item inactive: ' . $e->getMessage());
        }
    }

    public function show(Item $item)
    {
        $units = Unit::all();
        $regions = \DB::table('regions')->where('status', 'active')->get()->keyBy('id');
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->keyBy('id_outlet');
        $item->load(['prices']);
        $itemData = $item->toArray();
        $itemData['prices'] = $item->prices->map(function($p) use ($regions, $outlets) {
            $regionName = $p->region_id ? optional($regions->get($p->region_id))->name : null;
            $outletName = $p->outlet_id ? optional($outlets->get($p->outlet_id))->nama_outlet : null;
            if ($regionName) {
                $label = $regionName;
            } elseif ($outletName) {
                $label = $outletName;
            } else {
                $label = 'All';
            }
            return [
                'region_id' => $p->region_id,
                'region_name' => $regionName,
                'outlet_id' => $p->outlet_id,
                'outlet_name' => $outletName,
                'label' => $label,
                'price' => $p->price,
            ];
        })->toArray();
        return response()->json(['item' => $itemData]);
    }

    public function toggleStatus($id, Request $request)
    {
        $item = Item::findOrFail($id);
        $item->status = $request->status;
        $item->save();
        // Log activity (opsional)
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'items',
            'description' => 'Mengubah status item: ' . $item->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $item->toArray()
        ]);
        return response()->json(['success' => true, 'status' => $item->status]);
    }

    public function exportExcel()
    {
        return Excel::download(new ItemsExport, 'items.xlsx');
    }

    public function exportPdf()
    {
        $items = Item::with(['category', 'subCategory'])->get();
        $pdf = PDF::loadView('exports.items_pdf', compact('items'));
        return $pdf->download('items.pdf');
    }

    public function downloadImportTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new ItemsImportTemplateExport, 'items_import_template.xlsx');
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);
        $file = $request->file('file');
        $data = Excel::toArray([], $file);
        // Cari sheet dengan header 'Name', 'Category', dst (sheet Items)
        $rows = [];
        foreach ($data as $sheet) {
            if (isset($sheet[0]) && in_array('Name', $sheet[0]) && in_array('Category', $sheet[0])) {
                $rows = $sheet;
                break;
            }
        }
        $header = array_map('trim', $rows[0] ?? []);
        $preview = [];
        foreach (array_slice($rows, 1, 10) as $row) {
            $item = [];
            foreach ($header as $i => $col) {
                $item[$col] = $row[$i] ?? null;
            }
            $preview[] = $item;
        }
        return response()->json([
            'header' => $header,
            'preview' => $preview,
        ]);
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file);
        // Cari sheet dengan header 'Name', 'Category', dst (sheet Items)
        $rows = [];
        foreach ($data as $sheet) {
            if (isset($sheet[0]) && in_array('Name', $sheet[0]) && in_array('Category', $sheet[0])) {
                $rows = $sheet;
                break;
            }
        }
        $header = array_map('trim', $rows[0] ?? []);
        $results = [];

        DB::beginTransaction();
        try {
        foreach (array_slice($rows, 1) as $rowIdx => $row) {
            $itemData = [];
            foreach ($header as $i => $col) {
                $itemData[$col] = $row[$i] ?? null;
            }

                // Validasi data wajib sesuai rules store()
                $requiredFields = [
                    'Name', 'Category', 'Small Unit', 'Medium Unit', 'Large Unit',
                    'Medium Conversion Qty', 'Small Conversion Qty', 'Status', 'Composition Type'
                ];
                $missingFields = [];
                foreach ($requiredFields as $field) {
                    if (empty($itemData[$field])) {
                        $missingFields[] = $field;
                    }
                }
                if (!empty($missingFields)) {
                    throw new \Exception('Data wajib tidak lengkap: ' . implode(', ', $missingFields));
                }

                // Cari relasi
                $category = Category::where('name', $itemData['Category'])->first();
                if (!$category) {
                    throw new \Exception('Category tidak ditemukan');
                }

                $subCategory = null;
                if (!empty($itemData['Sub Category'])) {
                    $subCategory = SubCategory::where('name', $itemData['Sub Category'])->first();
                    if (!$subCategory) {
                        throw new \Exception('Sub Category tidak ditemukan');
                    }
                }

                // Cari relasi Warehouse Division (nullable)
                $warehouseDivision = null;
                if (!empty($itemData['Warehouse Division'])) {
                    $warehouseDivision = WarehouseDivision::where('name', $itemData['Warehouse Division'])->first();
                    if (!$warehouseDivision) {
                        throw new \Exception('Warehouse Division tidak ditemukan');
                    }
                }

                $smallUnit = Unit::where('name', $itemData['Small Unit'])->first();
                if (!$smallUnit) {
                    throw new \Exception('Small Unit tidak ditemukan');
                }

                $mediumUnit = Unit::where('name', $itemData['Medium Unit'])->first();
                if (!$mediumUnit) {
                    throw new \Exception('Medium Unit tidak ditemukan');
                }

                $largeUnit = Unit::where('name', $itemData['Large Unit'])->first();
                if (!$largeUnit) {
                    throw new \Exception('Large Unit tidak ditemukan');
                }

                $menuType = null;
                if (!empty($itemData['Type'])) {
                    $menuType = DB::table('menu_type')->where('type', $itemData['Type'])->first();
                    if (!$menuType) {
                        throw new \Exception('Menu Type tidak ditemukan');
                    }
                }

                // Generate SKU
                $date = now();
                $ymd = $date->format('Ymd');
                $rand = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                $sku = $category->code . '-' . $ymd . '-' . $rand;

                // Create item
                $typeString = $menuType?->type ?? ($itemData['Type'] ?? null);

                $item = Item::create([
                    'name' => $itemData['Name'],
                    'category_id' => $category->id,
                    'sub_category_id' => $subCategory?->id,
                    'warehouse_division_id' => $warehouseDivision?->id,
                    'menu_type_id' => $menuType?->id,
                    'description' => $itemData['Description'] ?? null,
                    'specification' => $itemData['Specification'] ?? null,
                    'small_unit_id' => $smallUnit->id,
                    'medium_unit_id' => $mediumUnit->id,
                    'large_unit_id' => $largeUnit->id,
                    'medium_conversion_qty' => $itemData['Medium Conversion Qty'],
                    'small_conversion_qty' => $itemData['Small Conversion Qty'],
                    'min_stock' => $itemData['Min Stock'],
                    'status' => $itemData['Status'],
                    'composition_type' => $itemData['Composition Type'],
                    'sku' => $sku,
                    'modifier_enabled' => $itemData['Modifier Enabled'] === 'Yes',
                    'type' => $typeString,
                ]);

                // Handle Modifier Options
                if (!empty($itemData['Modifier Options'])) {
                    $modifierOptions = array_map(function($v) { return trim(mb_strtolower($v)); }, explode(',', $itemData['Modifier Options']));
                    foreach ($modifierOptions as $optionName) {
                        $option = DB::table('modifier_options')
                            ->join('modifiers', 'modifier_options.modifier_id', '=', 'modifiers.id')
                            ->whereRaw('LOWER(TRIM(modifier_options.name)) = ?', [$optionName])
                            ->select('modifier_options.id')
                            ->first();
                        if ($option) {
                            DB::table('item_modifier_options')->insert([
                                'item_id' => $item->id,
                                'modifier_option_id' => $option->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            throw new \Exception('Modifier Option tidak ditemukan: ' . $optionName);
                        }
                    }
                }

                // Handle BOM
                if (!empty($itemData['BOM']) && $itemData['Composition Type'] === 'composed') {
                    $bomItems = array_map('trim', explode(';', $itemData['BOM']));
                    foreach ($bomItems as $bomItem) {
                        if (preg_match('/^(.*?)\s*x\s*(\d+)\s*\((.*?)\)$/', $bomItem, $matches)) {
                            $itemName = trim($matches[1]);
                            $qty = (int)$matches[2];
                            $unitName = trim($matches[3]);

                            $bomItem = Item::where('name', $itemName)->first();
                            $unit = Unit::where('name', $unitName)->first();

                            if ($bomItem && $unit) {
                                DB::table('item_bom')->insert([
                                    'item_id' => $item->id,
                                    'material_item_id' => $bomItem->id,
                                    'unit_id' => $unit->id,
                                    'qty' => $qty,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }

                // Handle Prices
                if (!empty($itemData['Prices'])) {
                    $prices = array_map('trim', explode(';', $itemData['Prices']));
                    foreach ($prices as $price) {
                        if (preg_match('/^(.*?)=(.*?)$/', $price, $matches)) {
                            $targetName = trim($matches[1]);
                            $priceValue = (float)$matches[2];

                            $region = DB::table('regions')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($targetName)])->first();
                            $outlet = DB::table('tbl_data_outlet')->whereRaw('LOWER(TRIM(nama_outlet)) = ?', [mb_strtolower($targetName)])->first();

                            $type = 'all';
                            if ($region && !$outlet) $type = 'region';
                            if ($outlet) $type = 'outlet';

                            if ($region) {
                                DB::table('item_prices')->insert([
                                    'item_id' => $item->id,
                                    'region_id' => $region->id,
                                    'outlet_id' => null,
                                    'price' => $priceValue,
                                    'availability_price_type' => $type,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            } elseif ($outlet) {
                                DB::table('item_prices')->insert([
                                    'item_id' => $item->id,
                                    'region_id' => null,
                                    'outlet_id' => $outlet->id_outlet,
                                    'price' => $priceValue,
                                    'availability_price_type' => $type,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            } else {
                                throw new \Exception('Region/Outlet tidak ditemukan: ' . $targetName);
                            }
                        }
                    }
                }

                // Handle Availabilities
                if (!empty($itemData['Availabilities'])) {
                    $availabilities = array_map('trim', explode(';', $itemData['Availabilities']));
                    foreach ($availabilities as $availability) {
                        $targetName = trim($availability);
                        if (strtolower($targetName) === 'all') {
                            DB::table('item_availabilities')->insert([
                                'item_id' => $item->id,
                                'region_id' => null,
                                'outlet_id' => null,
                                'availability_type' => 'all',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            $region = DB::table('regions')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($targetName)])->first();
                            $outlet = DB::table('tbl_data_outlet')->whereRaw('LOWER(TRIM(nama_outlet)) = ?', [mb_strtolower($targetName)])->first();
                            if ($region) {
                                DB::table('item_availabilities')->insert([
                                    'item_id' => $item->id,
                                    'region_id' => $region->id,
                                    'outlet_id' => null,
                                    'availability_type' => 'region',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            } elseif ($outlet) {
                                DB::table('item_availabilities')->insert([
                                    'item_id' => $item->id,
                                    'region_id' => null,
                                    'outlet_id' => $outlet->id_outlet,
                                    'availability_type' => 'outlet',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            } else {
                                throw new \Exception('Region/Outlet tidak ditemukan: ' . $targetName);
                            }
                        }
                    }
                }

                // Handle Images
                if (!empty($itemData['Images'])) {
                    $imageUrls = array_map('trim', explode(',', $itemData['Images']));
                    foreach ($imageUrls as $url) {
                        if (filter_var($url, FILTER_VALIDATE_URL)) {
                            DB::table('item_images')->insert([
                                'item_id' => $item->id,
                                'path' => $url,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                $results[] = [
                    'row' => $rowIdx + 2,
                    'name' => $itemData['Name'],
                    'status' => 'success',
                    'message' => 'Imported successfully',
                ];
            }

            DB::commit();
            } catch (\Exception $e) {
            DB::rollBack();
                $results[] = [
                'row' => $rowIdx + 2,
                    'name' => $itemData['Name'] ?? '',
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }

        return response()->json(['results' => $results]);
    }

    public function searchForPr(Request $request)
    {
        try {
            $q = $request->input('q');
            $items = \DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->leftJoin('units as u_small', 'items.small_unit_id', '=', 'u_small.id')
                ->leftJoin('units as u_medium', 'items.medium_unit_id', '=', 'u_medium.id')
                ->leftJoin('units as u_large', 'items.large_unit_id', '=', 'u_large.id')
                ->where('items.status', 'active')
                ->where('categories.show_pos', '0')
                ->where(function($query) use ($q) {
                    $query->where('items.name', 'like', "%$q%")
                          ->orWhere('items.sku', 'like', "%$q%");
                })
                ->select([
                    'items.id',
                    'items.name',
                    'items.sku',
                    'items.status',
                    'items.small_unit_id',
                    'items.medium_unit_id',
                    'items.large_unit_id',
                    'u_small.name as unit_small',
                    'u_medium.name as unit_medium',
                    'u_large.name as unit_large',
                ])
                ->orderBy('items.name')
                ->limit(20)
                ->get();
            return response()->json($items);
        } catch (\Exception $e) {
            \Log::error('Error in searchForPr: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'query' => $q ?? 'null'
            ]);
            return response()->json([
                'error' => 'Failed to search items',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Endpoint untuk ambil stok per item per warehouse
    public function getStock(Request $request)
    {
        $itemId = $request->input('item_id');
        $warehouseId = $request->input('warehouse_id');

        // Cari inventory_item_id dari item_id
        $inventoryItem = \DB::table('food_inventory_items')->where('item_id', $itemId)->first();
        if (!$inventoryItem) {
            return response()->json([
                'qty_small' => 0,
                'qty_medium' => 0,
                'qty_large' => 0,
            ]);
        }

        $stock = \DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return response()->json([
            'qty_small' => $stock->qty_small ?? 0,
            'qty_medium' => $stock->qty_medium ?? 0,
            'qty_large' => $stock->qty_large ?? 0,
        ]);
    }
} 