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
use App\Models\FoodInventoryStock;
use App\Models\FoodInventoryCard;
use App\Exports\BomImportTemplateExport;
use App\Imports\BomImport;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with([
            'category',
            'subCategory',
            'smallUnit',
            'mediumUnit',
            'largeUnit',
            'images',
            'itemModifierOptions',
            'boms',
            'prices',
            'availabilities',
            'warehouseDivision'
        ]);

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
                'exp' => 'nullable|integer|min:0',
            ]);
            \Log::info('ItemController@store - Validation passed', $validated);

            DB::beginTransaction();
            \Log::info('ItemController@store - Transaction started');

            \Log::info('ItemController@store - Creating item');
            $item = Item::create(array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
                'exp' => $request->exp ?? 0,
            ]));
            \Log::info('ItemController@store - Item created', ['item_id' => $item->id]);

            // Generate barcode default jika kategori show_pos = '0' dan item belum punya barcode
            $category = \DB::table('categories')->where('id', $item->category_id)->first();
            $barcodeCount = \DB::table('item_barcodes')->where('item_id', $item->id)->count();
            if ($category && $category->show_pos == '0' && $barcodeCount == 0) {
                $barcode = $this->generateUniqueBarcode();
                \DB::table('item_barcodes')->insert([
                    'item_id' => $item->id,
                    'barcode' => $barcode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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
                    if ($price['price_type'] === 'specific') {
                    if (!empty($price['region_id']) && empty($price['outlet_id'])) {
                        $type = 'region';
                    } else if (!empty($price['outlet_id'])) {
                        $type = 'outlet';
                        }
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
        $allowedTypes = \DB::table('menu_type')->pluck('type')->toArray();
        if ($request->has('modifier_enabled')) {
            $request->merge([
                'modifier_enabled' => filter_var($request->modifier_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'warehouse_division_id' => 'nullable|string|max:255',
            'sku' => 'required|string|max:255|unique:items,sku,' . $item->id,
            'type' => ['nullable', \Illuminate\Validation\Rule::in($allowedTypes)],
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
            'exp' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $oldData = $item->toArray();
            \Log::info('ITEM UPDATE DEBUG - BEFORE', $item->toArray());
            \Log::info('ITEM UPDATE DEBUG - PAYLOAD', array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
                'exp' => $request->exp ?? 0,
            ]));
            $item->update(array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
                'exp' => $request->exp ?? 0,
            ]));
            \Log::info('ITEM UPDATE DEBUG - AFTER', $item->fresh()->toArray());

            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('items', 'public');
                    $item->images()->create(['path' => $path]);
                }
            }

            // Handle image deletions
            if ($request->has('deleted_images') && is_array($request->deleted_images)) {
                foreach ($request->deleted_images as $imgPath) {
                    $image = $item->images()->where('path', $imgPath)->first();
                    if ($image) {
                        \Storage::disk('public')->delete($image->path);
                        $image->delete();
                    }
                }
            }

            // Update harga per region/outlet
            if ($request->has('prices')) {
                \Log::info('ITEM UPDATE DEBUG - BEFORE PRICES', $item->prices->toArray());
                $item->prices()->delete();
                \Log::info('ITEM UPDATE DEBUG - AFTER DELETE PRICES', $item->prices->toArray());
                if ($request->prices) {
                    foreach ($request->prices as $price) {
                        $type = 'all';
                        if (isset($price['region_id']) && !empty($price['region_id']) && (!isset($price['outlet_id']) || empty($price['outlet_id']))) {
                            $type = 'region';
                        } else if (isset($price['outlet_id']) && !empty($price['outlet_id'])) {
                            $type = 'outlet';
                        }
                        $item->prices()->create([
                            'region_id' => isset($price['region_id']) ? $price['region_id'] : null,
                            'outlet_id' => isset($price['outlet_id']) ? $price['outlet_id'] : null,
                            'price' => $price['price'],
                            'availability_price_type' => $type,
                        ]);
                    }
                    \Log::info('ITEM UPDATE DEBUG - AFTER INSERT PRICES', $item->prices->toArray());
                }
            }

            // Update availability per region/outlet
            if ($request->has('availabilities')) {
                \Log::info('ITEM UPDATE DEBUG - BEFORE AVAIL', $item->availabilities->toArray());
                $item->availabilities()->delete();
                \Log::info('ITEM UPDATE DEBUG - AFTER DELETE AVAIL', $item->availabilities->toArray());
                if ($request->availabilities) {
                    foreach ($request->availabilities as $availability) {
                        $type = 'all';
                        if (isset($availability['region_id']) && !empty($availability['region_id']) && (!isset($availability['outlet_id']) || empty($availability['outlet_id']))) {
                            $type = 'region';
                        } else if (isset($availability['outlet_id']) && !empty($availability['outlet_id'])) {
                            $type = 'outlet';
                        }
                        $item->availabilities()->create([
                            'region_id' => isset($availability['region_id']) ? $availability['region_id'] : null,
                            'outlet_id' => isset($availability['outlet_id']) ? $availability['outlet_id'] : null,
                            'availability_type' => $type,
                        ]);
                    }
                    \Log::info('ITEM UPDATE DEBUG - AFTER INSERT AVAIL', $item->availabilities->toArray());
                }
            }

            // Update BOM jika composition_type = 'composed'
            if ($request->has('bom')) {
                \Log::info('ITEM UPDATE DEBUG - BEFORE BOM', $item->boms->toArray());
                $item->boms()->delete();
                \Log::info('ITEM UPDATE DEBUG - AFTER DELETE BOM', $item->boms->toArray());
                if ($request->composition_type === 'composed' && $request->bom) {
                    foreach ($request->bom as $bom) {
                        $item->boms()->create([
                            'material_item_id' => $bom['item_id'],
                            'qty' => $bom['qty'],
                            'unit_id' => $bom['unit_id'],
                        ]);
                    }
                    \Log::info('ITEM UPDATE DEBUG - AFTER INSERT BOM', $item->boms->toArray());
                }
            }

            // Update modifier options
            if ($request->has('modifier_option_ids')) {
                \Log::info('ITEM UPDATE DEBUG - BEFORE MODIFIER', $item->modifierOptions->toArray());
                if ($item->modifier_enabled && $request->modifier_option_ids) {
                    $item->modifierOptions()->sync($request->modifier_option_ids);
                } else {
                    $item->modifierOptions()->detach();
                }
                \Log::info('ITEM UPDATE DEBUG - AFTER MODIFIER', $item->modifierOptions->toArray());
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
            \Log::error('ITEM UPDATE DEBUG - ERROR', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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

    public function show($id)
    {
        $item = \App\Models\Item::with(['images', 'prices', 'availabilities', 'modifierOptions', 'boms', 'category', 'subCategory', 'smallUnit', 'mediumUnit', 'largeUnit', 'warehouseDivision'])->find($id);
        \Log::info('DEBUG MANUAL FIND', ['item' => $item]);
        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }
        $units = Unit::all();
        $regions = \DB::table('regions')->where('status', 'active')->get()->keyBy('id');
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->keyBy('id_outlet');
        $bomItems = \DB::table('items')->where('composition_type', 'single')->where('status', 'active')->orderBy('name')->get();
        $modifiers = Modifier::with('options')->get();
        \Log::info('DEBUG $item->prices RAW', $item->prices->toArray());
        \Log::info('DEBUG $regions', $regions->toArray());
        \Log::info('DEBUG $outlets', $outlets->toArray());
        $mappedPrices = $item->prices->map(function($p) use ($regions, $outlets) {
            $regionId = $p->region_id !== null ? (int) $p->region_id : null;
            $outletId = $p->outlet_id !== null ? (int) $p->outlet_id : null;
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
                'price' => $p->price,
            ];
        })->toArray();
        \Log::info('DEBUG $item->prices MAPPED', $mappedPrices);
        $itemData = [
            'id' => $item->id,
            'category_id' => $item->category_id,
            'sub_category_id' => $item->sub_category_id,
            'warehouse_division_id' => $item->warehouse_division_id,
            'sku' => $item->sku,
            'type' => $item->type,
            'name' => $item->name,
            'description' => $item->description,
            'specification' => $item->specification,
            'small_unit_id' => $item->small_unit_id,
            'medium_unit_id' => $item->medium_unit_id,
            'large_unit_id' => $item->large_unit_id,
            'medium_conversion_qty' => $item->medium_conversion_qty,
            'small_conversion_qty' => $item->small_conversion_qty,
            'min_stock' => $item->min_stock,
            'exp' => $item->exp,
            'status' => $item->status,
            'images' => $item->images,
            'prices' => $mappedPrices,
            'availabilities' => $item->availabilities->map(function($a) use ($regions, $outlets) {
                $regionId = $a->region_id !== null ? (int) $a->region_id : null;
                $outletId = $a->outlet_id !== null ? (int) $a->outlet_id : null;
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
                    'availability_type' => $a->availability_type,
                ];
            })->toArray(),
            'bom' => $item->boms->map(function($b) use ($bomItems, $units) {
                $itemName = $b->material_item_id ? optional($bomItems->firstWhere('id', $b->material_item_id))->name : null;
                $unitName = $b->unit_id ? optional($units->firstWhere('id', $b->unit_id))->name : null;
                return [
                    'item_id' => $b->material_item_id,
                    'item_name' => $itemName,
                    'qty' => $b->qty,
                    'unit_id' => $b->unit_id,
                    'unit_name' => $unitName,
                ];
            })->toArray(),
            'modifier_option_ids' => $item->modifierOptions->pluck('id')->toArray(),
            'modifiers' => $modifiers->map(function($mod) use ($item) {
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
            })->filter(fn($m) => count($m['options']) > 0)->values()->all(),
        ];
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
        $headers = [
            'Name', 'Category', 'Sub Category', 'Small Unit', 'Medium Unit', 'Large Unit',
            'Medium Conversion Qty', 'Small Conversion Qty', 'Min Stock', 'Expiry Days', 'Status',
            'Description', 'Specification', 'Type', 'Warehouse Division', 'Composition Type',
            'Modifier Enabled', 'Modifier Options', 'BOM', 'Prices', 'Availabilities', 'Images'
        ];

        $data = [
            $headers,
            [
                'Sample Item', 'Food', 'Appetizer', 'PCS', 'BOX', 'CTN',
                '12', '24', '10', '5', 'active',
                'Sample Description', 'Sample Specification', 'product', 'Kitchen', 'single',
                'No', '', '', 'All=10000', 'All', ''
            ]
        ];

        return Excel::download(new ItemsImportTemplateExport($data), 'items_import_template.xlsx');
    }

    public function previewImport(Request $request)
    {
        \Log::info('ItemController@previewImport - Starting preview');
        \Log::info('ItemController@previewImport - File received', [
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize()
        ]);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        try {
            $data = Excel::toArray([], $file);
            \Log::info('ItemController@previewImport - Excel data loaded', [
                'sheets_count' => count($data)
            ]);

            // Cari sheet dengan header 'Name', 'Category', dst (sheet Items)
            $rows = [];
            foreach ($data as $sheetIndex => $sheet) {
                if (isset($sheet[0]) && in_array('Name', $sheet[0]) && in_array('Category', $sheet[0])) {
                    $rows = $sheet;
                    \Log::info('ItemController@previewImport - Found items sheet', [
                        'sheet_index' => $sheetIndex,
                        'rows_count' => count($sheet)
                    ]);
                    break;
                }
            }

            if (empty($rows)) {
                \Log::error('ItemController@previewImport - No valid items sheet found');
                return response()->json([
                    'message' => 'File tidak valid: Sheet items tidak ditemukan',
                    'error' => true
                ], 400);
            }

            $header = array_map('trim', $rows[0] ?? []);
            $preview = [];
            foreach (array_slice($rows, 1, 10) as $rowIdx => $row) {
                $item = [];
                foreach ($header as $i => $col) {
                    $item[$col] = $row[$i] ?? null;
                }
                $preview[] = $item;
            }

            \Log::info('ItemController@previewImport - Preview generated', [
                'header' => $header,
                'preview_rows' => count($preview)
            ]);

            return response()->json([
                'header' => $header,
                'preview' => $preview,
                'error' => false
            ]);
        } catch (\Exception $e) {
            \Log::error('ItemController@previewImport - Error processing file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Gagal membaca file: ' . $e->getMessage(),
                'error' => true
            ], 400);
        }
    }

    public function importExcel(Request $request)
    {
        \Log::info('MASUK FUNGSI IMPORT EXCEL');
        \Log::info('ItemController@importExcel - Starting import');
        \Log::info('ItemController@importExcel - File received', [
            'original_name' => $request->file('file')->getClientOriginalName(),
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize()
        ]);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file);
        \Log::info('ItemController@importExcel - Excel data loaded', [
            'sheets_count' => count($data)
        ]);

        // Cari sheet dengan header 'Name', 'Category', dst (sheet Items)
        $rows = [];
        foreach ($data as $sheetIndex => $sheet) {
            if (isset($sheet[0]) && in_array('Name', $sheet[0]) && in_array('Category', $sheet[0])) {
                $rows = $sheet;
                \Log::info('ItemController@importExcel - Found items sheet', [
                    'sheet_index' => $sheetIndex,
                    'rows_count' => count($sheet)
                ]);
                break;
            }
        }

        if (empty($rows)) {
            \Log::error('ItemController@importExcel - No valid items sheet found');
            return response()->json([
                'message' => 'File tidak valid: Sheet items tidak ditemukan',
                'error' => true
            ], 400);
        }

        $header = array_map('trim', $rows[0] ?? []);
        $results = [];

        DB::beginTransaction();
        try {
            foreach (array_slice($rows, 1) as $rowIdx => $row) {
                \Log::info('ItemController@importExcel - Processing row', [
                    'row_index' => $rowIdx + 2, // +2 karena index 0 adalah header dan array_slice dimulai dari 1
                    'row_data' => $row
                ]);

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
                do {
                $rand = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                $sku = $category->code . '-' . $ymd . '-' . $rand;
                } while (\App\Models\Item::where('sku', $sku)->exists());

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
                    'exp' => $itemData['Expiry Days'] ?? 0,
                    'status' => $itemData['Status'],
                    'composition_type' => $itemData['Composition Type'],
                    'sku' => $sku,
                    'modifier_enabled' => $itemData['Modifier Enabled'] === 'Yes',
                    'type' => $typeString,
                ]);

                // Generate barcode default jika kategori show_pos = '0' dan item belum punya barcode
                $category = \DB::table('categories')->where('id', $item->category_id)->first();
                $barcodeCount = \DB::table('item_barcodes')->where('item_id', $item->id)->count();
                if ($category && $category->show_pos == '0' && $barcodeCount == 0) {
                    $barcode = $this->generateUniqueBarcode();
                    \DB::table('item_barcodes')->insert([
                        'item_id' => $item->id,
                        'barcode' => $barcode,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

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

                            if (strtolower($targetName) === 'all') {
                                DB::table('item_prices')->insert([
                                    'item_id' => $item->id,
                                    'region_id' => null,
                                    'outlet_id' => null,
                                    'price' => $priceValue,
                                    'availability_price_type' => 'all',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            } else {
                                $region = DB::table('regions')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($targetName)])->first();
                                $outlet = DB::table('tbl_data_outlet')->whereRaw('LOWER(TRIM(nama_outlet)) = ?', [mb_strtolower($targetName)])->first();
                                if ($region) {
                                    DB::table('item_prices')->insert([
                                        'item_id' => $item->id,
                                        'region_id' => $region->id,
                                        'outlet_id' => null,
                                        'price' => $priceValue,
                                        'availability_price_type' => 'region',
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                } elseif ($outlet) {
                                    DB::table('item_prices')->insert([
                                        'item_id' => $item->id,
                                        'region_id' => null,
                                        'outlet_id' => $outlet->id_outlet,
                                        'price' => $priceValue,
                                        'availability_price_type' => 'outlet',
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
                                } else {
                                    \Log::warning('ImportExcel: Region/Outlet not found for price', ['targetName' => $targetName]);
                                }
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

    public function searchForWarehouseTransfer(Request $request)
    {
        \Log::info('DEBUG: masuk method searchForWarehouseTransfer');
            $q = $request->input('q');
        // $warehouseId = $request->input('warehouse_id'); // Boleh ada, tapi tidak dipakai di query

            $items = \DB::table('items')
            ->leftJoin('units as usmall', 'usmall.id', '=', 'items.small_unit_id')
            ->leftJoin('units as umedium', 'umedium.id', '=', 'items.medium_unit_id')
            ->leftJoin('units as ularge', 'ularge.id', '=', 'items.large_unit_id')
            ->select(
                    'items.id',
                    'items.name',
                    'items.sku',
                'usmall.name as unit_small',
                'umedium.name as unit_medium',
                'ularge.name as unit_large',
                    'items.small_unit_id',
                    'items.medium_unit_id',
                    'items.large_unit_id',
                'items.medium_conversion_qty',
                'items.small_conversion_qty'
            )
            ->where('items.status', 'active')
            ->where('items.name', 'like', '%' . $q . '%')
                ->orderBy('items.name')
            ->limit(10)
            ->get();

        \Log::info('DEBUG: hasil items', ['items' => $items]);
            return response()->json($items);
    }

    // Endpoint untuk ambil stok per item per warehouse
    public function getStock(Request $request)
    {
        $item_id = $request->input('item_id');
        $warehouse_id = $request->input('warehouse_id');
        $inventoryItem = \DB::table('food_inventory_items')->where('item_id', $item_id)->first();
        $itemMaster = \DB::table('items')->where('id', $item_id)->first();
        $unitSmall = $itemMaster ? \DB::table('units')->where('id', $itemMaster->small_unit_id)->value('name') : null;
        $unitMedium = $itemMaster ? \DB::table('units')->where('id', $itemMaster->medium_unit_id)->value('name') : null;
        $unitLarge = $itemMaster ? \DB::table('units')->where('id', $itemMaster->large_unit_id)->value('name') : null;
        if (!$inventoryItem) {
            return response()->json([
                'qty_small' => 0, 'qty_medium' => 0, 'qty_large' => 0,
                'small_conversion_qty' => $itemMaster->small_conversion_qty ?? 1,
                'medium_conversion_qty' => $itemMaster->medium_conversion_qty ?? 1,
                'unit_small' => $unitSmall,
                'unit_medium' => $unitMedium,
                'unit_large' => $unitLarge,
            ]);
        }
        $stock = \DB::table('food_inventory_stocks')
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $warehouse_id)
            ->first();
        return response()->json([
            'qty_small' => $stock->qty_small ?? 0,
            'qty_medium' => $stock->qty_medium ?? 0,
            'qty_large' => $stock->qty_large ?? 0,
            'small_conversion_qty' => $itemMaster->small_conversion_qty ?? 1,
            'medium_conversion_qty' => $itemMaster->medium_conversion_qty ?? 1,
            'unit_small' => $unitSmall,
            'unit_medium' => $unitMedium,
            'unit_large' => $unitLarge,
        ]);
    }

    public function apiDetail($id)
    {
        $item = \App\Models\Item::with('images')->findOrFail($id);
        return response()->json([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'specification' => $item->specification,
                'images' => $item->images->map(function($img) {
                    return [
                        'id' => $img->id,
                        'path' => $img->path,
                    ];
                })->toArray(),
            ]
        ]);
    }

    /**
     * Ambil item berdasarkan warehouse_division_id yang terkait dengan FO Schedule tertentu dan status=active
     */
    public function getByFOSchedule($fo_schedule_id)
    {
        \Log::info('DEBUG REQUEST region_id', [
            'region_id_param' => request('region_id'),
            'outlet_id_param' => request('outlet_id'),
            'all_params' => request()->all()
        ]);
        $region_id = request('region_id');
        $outlet_id = request('outlet_id');
        $foSchedule = \App\Models\FOSchedule::with('warehouseDivisions')->findOrFail($fo_schedule_id);
        $warehouseDivisionIds = $foSchedule->warehouseDivisions->pluck('id');
        $itemIds = \App\Models\Item::whereIn('warehouse_division_id', $warehouseDivisionIds)
            ->where('status', 'active')
            ->pluck('id');
        // Ambil item_id yang available untuk user (all, region, outlet)
        $availableItemIds = \DB::table('item_availabilities')
            ->whereIn('item_id', $itemIds)
            ->where(function($q) use ($region_id, $outlet_id) {
                $q->where('availability_type', 'all');
                if ($region_id) {
                    $q->orWhere(function($q2) use ($region_id) {
                        $q2->where('availability_type', 'region')->where('region_id', $region_id);
                    });
                }
                if ($outlet_id) {
                    $q->orWhere(function($q2) use ($outlet_id) {
                        $q2->where('availability_type', 'outlet')->where('outlet_id', $outlet_id);
                    });
                }
            })
            ->pluck('item_id')
            ->unique();
        // Log debug
        \Log::info('FO getByFOSchedule availabilities', [
            'region_id' => $region_id,
            'outlet_id' => $outlet_id,
            'availableItemIds' => $availableItemIds
        ]);
        $items = Item::whereIn('id', $availableItemIds)
            ->with(['category', 'mediumUnit'])
            ->get()
            ->map(function($item) use ($region_id, $outlet_id) {
                // Ambil harga prioritas: outlet > region > all
                $price = \DB::table('item_prices')
                    ->where('item_id', $item->id)
                    ->where(function($q) use ($region_id, $outlet_id) {
                        $q->where('availability_price_type', 'all');
                        if ($region_id) {
                            $q->orWhere(function($q2) use ($region_id) {
                                $q2->where('availability_price_type', 'region')->where('region_id', $region_id);
                            });
                        }
                        if ($outlet_id) {
                            $q->orWhere(function($q2) use ($outlet_id) {
                                $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outlet_id);
                            });
                        }
                    })
                    ->orderByRaw("CASE 
                        WHEN availability_price_type = 'outlet' THEN 1
                        WHEN availability_price_type = 'region' THEN 2
                        ELSE 3 END")
                    ->orderByDesc('id')
                    ->first();
                $finalPrice = $price ? $price->price : 0;
                return array_merge($item->toArray(), [
                    'category_name' => $item->category ? $item->category->name : '-',
                    'unit_medium_name' => $item->mediumUnit ? $item->mediumUnit->name : '-',
                    'price' => $finalPrice,
                ]);
            });
        // Log debug hasil akhir
        \Log::info('FO getByFOSchedule items', [
            'count' => $items->count(),
            'item_ids' => $items->pluck('id')
        ]);
        return response()->json([
            'items' => $items
        ]);
    }

    /**
     * Ambil item untuk FO Khusus (tanpa jadwal, validasi dari item_availabilities)
     */
    public function getByFOKhusus(Request $request)
    {
        \Log::info('MASUK getByFOKhusus');
        $region_id = $request->region_id;
        $outlet_id = $request->outlet_id;

        // Ambil item_id dari item_availabilities yang aktif
        $itemIds = \DB::table('item_availabilities')
            ->where(function($q) use ($region_id, $outlet_id) {
                $q->where('availability_type', 'all');
                if ($region_id) {
                    $q->orWhere(function($q2) use ($region_id) {
                        $q2->where('availability_type', 'region')->where('region_id', $region_id);
                    });
                }
                if ($outlet_id) {
                    $q->orWhere(function($q2) use ($outlet_id) {
                        $q2->where('availability_type', 'outlet')->where('outlet_id', $outlet_id);
                    });
                }
            })
            ->pluck('item_id')
            ->unique();

        // Ambil item yang status=active dan punya warehouse_division
        $items = \App\Models\Item::whereIn('id', $itemIds)
            ->where('status', 'active')
            ->whereNotNull('warehouse_division_id')
            ->with(['category', 'mediumUnit'])
            ->get()
            ->map(function($item) use ($region_id, $outlet_id) {
                $price = \DB::table('item_prices')
                    ->where('item_id', $item->id)
                    ->where(function($q) use ($region_id, $outlet_id) {
                        $q->where('availability_price_type', 'all');
                        if ($region_id) {
                            $q->orWhere(function($q2) use ($region_id) {
                                $q2->where('availability_price_type', 'region')->where('region_id', $region_id);
                            });
                        }
                        if ($outlet_id) {
                            $q->orWhere(function($q2) use ($outlet_id) {
                                $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outlet_id);
                            });
                        }
                    })
                    ->orderByRaw("CASE 
                        WHEN availability_price_type = 'outlet' THEN 1
                        WHEN availability_price_type = 'region' THEN 2
                        ELSE 3 END")
                    ->orderByDesc('id')
                    ->first();
                $finalPrice = $price ? $price->price : 0;
                return array_merge($item->toArray(), [
                    'category_name' => $item->category ? $item->category->name : '-',
                    'unit_medium_name' => $item->mediumUnit ? $item->mediumUnit->name : '-',
                    'price' => $finalPrice,
                ]);
            });

        return response()->json([
            'items' => $items
        ]);
    }

    public function downloadBomImportTemplate()
    {
        return Excel::download(new BomImportTemplateExport, 'bom_import_template.xlsx');
    }

    public function previewBomImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\BomImport, $request->file('file'));
            $bomSheet = [];
            foreach ($data as $sheet) {
                if (
                    isset($sheet[0][0], $sheet[0][1], $sheet[0][2], $sheet[0][3]) &&
                    strtolower(trim($sheet[0][0])) === 'parent item' &&
                    strtolower(trim($sheet[0][1])) === 'child item' &&
                    strtolower(trim($sheet[0][2])) === 'quantity' &&
                    strtolower(trim($sheet[0][3])) === 'unit'
                ) {
                    $bomSheet = $sheet;
                    break;
                }
            }
            $headers = array_shift($bomSheet);
            $preview = array_slice($bomSheet, 0, 5);
            // Convert to array of object for frontend
            $previewObjects = [];
            foreach ($preview as $row) {
                $obj = [];
                foreach ($headers as $i => $h) {
                    $obj[$h] = $row[$i] ?? null;
                }
                $previewObjects[] = $obj;
            }
            return response()->json([
                'header' => $headers,
                'preview' => $previewObjects
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function importBom(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\BomImport, $request->file('file'));
            $bomSheet = [];
            foreach ($data as $sheet) {
                if (
                    isset($sheet[0][0], $sheet[0][1], $sheet[0][2], $sheet[0][3]) &&
                    strtolower(trim($sheet[0][0])) === 'parent item' &&
                    strtolower(trim($sheet[0][1])) === 'child item' &&
                    strtolower(trim($sheet[0][2])) === 'quantity' &&
                    strtolower(trim($sheet[0][3])) === 'unit'
                ) {
                    $bomSheet = $sheet;
                    break;
                }
            }
            array_shift($bomSheet); // Remove header
            $results = [];
            foreach ($bomSheet as $index => $row) {
                try {
                    // Validasi minimal kolom BOM
                    if (empty($row[0]) || empty($row[1]) || empty($row[2]) || empty($row[3])) {
                        throw new \Exception('Semua kolom wajib diisi');
                    }
                    // Find parent item
                    $parentItem = \App\Models\Item::where('name', $row[0])
                        ->where('composition_type', 'composed')
                        ->where('status', 'active')
                        ->first();
                    if (!$parentItem) {
                        throw new \Exception("Parent item not found or not active");
                    }
                    // Find child item
                    $childItem = \App\Models\Item::where('name', $row[1])
                        ->whereIn('type', ['Raw Materials', 'WIP'])
                        ->where('status', 'active')
                        ->first();
                    if (!$childItem) {
                        throw new \Exception("Child item not found or not active");
                    }
                    // Find unit
                    $unit = \App\Models\Unit::where('name', $row[3])->first();
                    if (!$unit) {
                        throw new \Exception("Unit not found");
                    }
                    // Insert/update BOM
                    \DB::table('item_bom')->updateOrInsert(
                        [
                            'item_id' => $parentItem->id,
                            'material_item_id' => $childItem->id,
                        ],
                        [
                            'qty' => $row[2],
                            'unit_id' => $unit->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    $results[] = [
                        'row' => $index + 2,
                        'name' => $row[0],
                        'status' => 'success',
                        'message' => 'Successfully imported'
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'row' => $index + 2,
                        'name' => $row[0] ?? '',
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }
            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Autocomplete PCS item (untuk butchering, dsb)
     */
    public function autocompletePcs(Request $request)
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
            \Log::error('Error in autocompletePcs: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'query' => $q ?? 'null'
            ]);
            return response()->json([
                'error' => 'Failed to search items',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function apiIndex()
    {
        $items = \App\Models\Item::with(['barcodes:id,item_id,barcode'])
            ->select('id', 'name')
            ->get();
        return response()->json($items);
    }

    public function searchForOutletTransfer(Request $request)
    {
        $q = $request->q;
        $outlet_id = $request->outlet_id;
        $region_id = $request->region_id;

        $items = \DB::table('item_availabilities')
            ->join('items', 'item_availabilities.item_id', '=', 'items.id')
            ->leftJoin('units as u_small', 'items.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'items.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'items.large_unit_id', '=', 'u_large.id')
            ->where(function($query) use ($outlet_id, $region_id) {
                $query->where('item_availabilities.availability_type', 'all')
                    ->orWhere(function($q) use ($region_id) {
                        $q->where('item_availabilities.availability_type', 'region')
                          ->where('item_availabilities.region_id', $region_id);
                    })
                    ->orWhere(function($q) use ($outlet_id) {
                        $q->where('item_availabilities.availability_type', 'outlet')
                          ->where('item_availabilities.outlet_id', $outlet_id);
                    });
            })
            ->where(function($query) use ($q) {
                $query->where('items.name', 'like', "%$q%")
                      ->orWhere('items.sku', 'like', "%$q%");
            })
            ->select(
                'items.id',
                'items.name',
                'items.sku',
                'u_small.name as unit_small',
                'u_medium.name as unit_medium',
                'u_large.name as unit_large',
                'items.small_unit_id',
                'items.medium_unit_id',
                'items.large_unit_id'
            )
            ->limit(20)
            ->get();

        \Log::info('searchForOutletTransfer', [
            'q' => $q,
            'outlet_id' => $outlet_id,
            'region_id' => $region_id,
            'result_count' => $items->count(),
            'first_item' => $items->first(),
        ]);

        foreach ($items as $item) {
            $item->unit_small = optional(\DB::table('units')->where('id', $item->small_unit_id)->first())->name;
            $item->unit_medium = optional(\DB::table('units')->where('id', $item->medium_unit_id)->first())->name;
            $item->unit_large = optional(\DB::table('units')->where('id', $item->large_unit_id)->first())->name;
        }

        return response()->json($items);
    }

    // Tambahkan fungsi helper untuk generate barcode unik
    private function generateUniqueBarcode()
    {
        do {
            $barcode = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            $exists = \DB::table('item_barcodes')->where('barcode', $barcode)->exists();
        } while ($exists);
        return $barcode;
    }

    public function bySupplier(Request $request)
    {
        $supplierId = $request->get('supplier_id');
        $outletId = $request->get('outlet_id');
        $items = DB::table('items')
            ->join('item_supplier', 'items.id', '=', 'item_supplier.item_id')
            ->join('item_supplier_outlet', 'item_supplier.id', '=', 'item_supplier_outlet.item_supplier_id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->leftJoin('units', 'item_supplier.unit_id', '=', 'units.id')
            ->where('item_supplier.supplier_id', $supplierId)
            ->where('item_supplier_outlet.outlet_id', $outletId)
            ->where('items.status', 'active')
            ->select(
                'items.id',
                'items.name',
                'items.sku',
                'items.category_id',
                'categories.name as category_name',
                'item_supplier.price',
                'units.name as unit',
                'units.id as unit_id'
            )
            ->get();
        return response()->json(['items' => $items]);
    }

    /**
     * Search items for autocomplete (mode PC)
     */
    public function search(Request $request)
    {
        try {
            $q = $request->get('q');
            $outletId = $request->get('outlet_id');
            $excludeSupplier = $request->get('exclude_supplier', false);

            $query = Item::with(['category', 'smallUnit', 'mediumUnit', 'largeUnit'])
                ->where(function($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('sku', 'like', "%{$q}%");
                });

            if ($excludeSupplier && $outletId) {
                $query->whereNotExists(function($sub) use ($outletId) {
                    $sub->select(\DB::raw(1))
                        ->from('item_supplier')
                        ->join('item_supplier_outlet', 'item_supplier.id', '=', 'item_supplier_outlet.item_supplier_id')
                        ->whereRaw('items.id = item_supplier.item_id')
                        ->where('item_supplier_outlet.outlet_id', $outletId);
                });
            }

            $region_id = $request->get('region_id');
            $outlet_id = $request->get('outlet_id');
            $items = $query->limit(10)->get()->map(function($item) use ($region_id, $outlet_id) {
                // Ambil harga medium (prioritas: outlet > region > all)
                $price = \DB::table('item_prices')
                    ->where('item_id', $item->id)
                    ->where(function($q) use ($region_id, $outlet_id) {
                        $q->where('availability_price_type', 'all');
                        if ($region_id) {
                            $q->orWhere(function($q2) use ($region_id) {
                                $q2->where('availability_price_type', 'region')->where('region_id', $region_id);
                            });
                        }
                        if ($outlet_id) {
                            $q->orWhere(function($q2) use ($outlet_id) {
                                $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outlet_id);
                            });
                        }
                    })
                    ->orderByRaw("CASE 
                        WHEN availability_price_type = 'outlet' THEN 1
                        WHEN availability_price_type = 'region' THEN 2
                        ELSE 3 END")
                    ->orderByDesc('id')
                    ->first();
                $finalPrice = $price ? $price->price : 0;
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'category_id' => $item->category_id,
                    'category_name' => optional($item->category)->name,
                    'unit' => optional($item->smallUnit)->name,
                    'unit_medium' => optional($item->mediumUnit)->name,
                    'unit_medium_name' => optional($item->mediumUnit)->name,
                    'unit_large' => optional($item->largeUnit)->name,
                    'price_medium' => $finalPrice,
                    'price' => $finalPrice,
                ];
            });

        return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            \Log::error('Error in search: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'q' => $q ?? 'null',
                'outlet_id' => $outletId ?? 'null',
                'exclude_supplier' => $excludeSupplier
            ]);
            return response()->json([
                'error' => 'Failed to search items',
                'message' => $e->getMessage()
            ], 500);
        }
    }
} 