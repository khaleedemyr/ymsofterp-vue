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
use Illuminate\Validation\Rule;

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
        $itemsArr = $items->toArray();

        // Ambil semua item aktif untuk BOM dropdown
        $bomItems = \App\Models\Item::with('smallUnit')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $regions = \DB::table('regions')->where('status', 'active')->get()->keyBy('id');
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->keyBy('id_outlet');
        $itemsArr = $items->toArray();
        $itemsArr['data'] = collect($itemsArr['data'])->map(function($item) use ($units, $bomItems, $regions, $outlets) {
            // Map modifier_option_ids
            $item['modifier_option_ids'] = collect($item['item_modifier_options'] ?? [])->pluck('modifier_option_id')->toArray();
            // Map BOM
            $item['bom'] = collect($item['boms'] ?? [])->map(function($b) use ($bomItems, $units) {
                $itemObj = $bomItems->where('id', $b['material_item_id'])->first();
                $itemName = $itemObj ? $itemObj->name : null;
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
        $warehouseDivisions = WarehouseDivision::where('status', 'active')->get();
        $menuTypes = \DB::table('menu_type')->where('status', 'active')->get();
        $regions = \DB::table('regions')->where('status', 'active')->get()->values();
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->values();
        
        // Ambil semua item aktif untuk BOM dropdown
        $bomItems = \App\Models\Item::with('smallUnit')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $modifiers = Modifier::with('options')->get();
        
        
        return Inertia::render('Items/Create', [
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

    public function store(Request $request)
    {
        
        try {
            
            // Get allowed types from menu_type table
            $allowedTypes = \DB::table('menu_type')->pluck('type')->toArray();
            
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'sub_category_id' => 'nullable|exists:sub_categories,id',
                'warehouse_division_id' => 'nullable|integer|exists:warehouse_division,id',
                'sku' => 'required|string|max:255|unique:items',
                'type' => ['nullable', Rule::in($allowedTypes)],
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

            DB::beginTransaction();

            $item = Item::create(array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
                'exp' => $request->exp ?? 0,
            ]));

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
                foreach ($request->file('images') as $image) {
                    $path = $image->store('items', 'public');
                    $item->images()->create(['path' => $path]);
                }
            }

            // Simpan relasi modifier_option jika enabled
            if ($item->modifier_enabled && $request->modifier_option_ids) {
                $item->modifierOptions()->sync($request->modifier_option_ids);
            }

            // Simpan BOM jika composition_type = 'composed'
            if ($request->composition_type === 'composed' && $request->bom) {
                foreach ($request->bom as $bom) {
                    $item->boms()->create([
                        'material_item_id' => $bom['item_id'],
                        'qty' => $bom['qty'],
                        'unit_id' => $bom['unit_id'],
                    ]);
                }
            }

            // Simpan harga per region/outlet
            if ($request->prices) {
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
            }

            // Simpan availability per region/outlet
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

            // Log activity
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

            DB::commit();
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
        // Ambil semua item aktif untuk BOM dropdown (kecuali item itu sendiri)
        $bomItems = \App\Models\Item::with('smallUnit')
            ->where('id', '!=', $item->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
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
        
        // Get modifier_enabled value before toArray() to ensure it's included
        $rawModifierEnabled = $item->modifier_enabled ?? 0;
        
        $itemData = $item->toArray();
        
        // Explicitly set modifier_enabled - always include it even if null or 0
        // This ensures the field is always present in the response
        // Will be converted to boolean later
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
            $itemObj = $bomItems->where('id', $b['material_item_id'])->first();
            $itemName = $itemObj ? $itemObj->name : null;
            $unitName = $b['unit_id'] ? optional($units->firstWhere('id', $b['unit_id']))->name : null;
            return [
                'item_id' => $b['material_item_id'],
                'item_name' => $itemName,
                'qty' => $b['qty'],
                'unit_id' => $b['unit_id'],
                'unit_name' => $unitName,
            ];
        })->toArray();
        // Modifier options with name
        $itemData['modifier_option_ids'] = $item->modifierOptions->pluck('id')->toArray();
        // Ensure modifier_enabled is properly set (convert integer to boolean for frontend)
        // Handle both integer (1/0) and boolean (true/false) from database
        // Use strict comparison and explicit boolean cast
        // $rawModifierEnabled was already set above
        $itemData['modifier_enabled'] = ($rawModifierEnabled === 1 || $rawModifierEnabled === true || $rawModifierEnabled === '1') ? true : false;
        
        // Ensure modifier_enabled is always present in itemData (double check)
        if (!isset($itemData['modifier_enabled'])) {
            $itemData['modifier_enabled'] = ($rawModifierEnabled === 1 || $rawModifierEnabled === true || $rawModifierEnabled === '1') ? true : false;
        }
        
        Log::info('Item edit - modifier_enabled', [
            'item_id' => $item->id,
            'raw_value' => $rawModifierEnabled,
            'raw_type' => gettype($rawModifierEnabled),
            'converted_value' => $itemData['modifier_enabled'],
            'converted_type' => gettype($itemData['modifier_enabled']),
            'itemData_has_modifier_enabled' => isset($itemData['modifier_enabled']),
            'itemData_keys' => array_keys($itemData)
        ]);
        // Send all modifiers with all options for the form
        $itemData['modifiers'] = $modifiers->map(function($mod) {
            return [
                'id' => $mod->id,
                'name' => $mod->name,
                'options' => $mod->options->map(function($opt) {
                    return [
                        'id' => $opt->id,
                        'name' => $opt->name,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
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
            'type' => ['nullable', Rule::in($allowedTypes)],
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
            $item->update(array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
                'exp' => $request->exp ?? 0,
            ]));

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
                $item->prices()->delete();
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
                }
            }

            // Update availability per region/outlet
            if ($request->has('availabilities')) {
                $item->availabilities()->delete();
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
                }
            }

            // Update BOM jika composition_type = 'composed'
            if ($request->has('bom')) {
                // Hanya hapus dan insert ulang jika ada BOM yang dikirim
                if ($request->composition_type === 'composed' && is_array($request->bom) && !empty($request->bom)) {
                    $item->boms()->delete();
                    foreach ($request->bom as $bom) {
                        if (!empty($bom['item_id']) && !empty($bom['qty']) && !empty($bom['unit_id'])) {
                            $item->boms()->create([
                                'material_item_id' => $bom['item_id'],
                                'qty' => $bom['qty'],
                                'unit_id' => $bom['unit_id'],
                            ]);
                        }
                    }
                } else if ($request->composition_type === 'single') {
                    // Jika composition_type = 'single', hapus semua BOM
                    $item->boms()->delete();
                }
                // Jika composition_type = 'composed' tapi BOM kosong, jangan hapus BOM yang ada
            }

            // Update modifier options
            if ($request->has('modifier_option_ids')) {
                if ($item->modifier_enabled && $request->modifier_option_ids) {
                    $item->modifierOptions()->sync($request->modifier_option_ids);
                } else {
                    $item->modifierOptions()->detach();
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

        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }
        $units = Unit::all();
        $regions = \DB::table('regions')->where('status', 'active')->get()->keyBy('id');
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->keyBy('id_outlet');
        // Ambil semua item aktif untuk BOM dropdown (kecuali item itu sendiri)
        $bomItems = \App\Models\Item::with('smallUnit')
            ->where('id', '!=', $item->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $modifiers = Modifier::with('options')->get();
        
        
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
            'composition_type' => $item->composition_type,
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
                $itemObj = $bomItems->where('id', $b['material_item_id'])->first();
                $itemName = $itemObj ? $itemObj->name : null;
                $unitName = $b['unit_id'] ? optional($units->firstWhere('id', $b['unit_id']))->name : null;
                return [
                    'item_id' => $b['material_item_id'],
                    'item_name' => $itemName,
                    'qty' => $b['qty'],
                    'unit_id' => $b['unit_id'],
                    'unit_name' => $unitName,
                ];
            })->toArray(),
            'modifier_option_ids' => $item->modifierOptions->pluck('id')->toArray(),
            'modifier_enabled' => ($item->modifier_enabled === 1 || $item->modifier_enabled === true || $item->modifier_enabled === '1') ? true : false,
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
    
    public function showDetail($id)
    {
        $item = \App\Models\Item::with([
            'images', 
            'category', 
            'subCategory', 
            'smallUnit', 
            'mediumUnit', 
            'largeUnit', 
            'warehouseDivision'
        ])->find($id);

        if (!$item) {
            return redirect()->route('items.index')->with('error', 'Item tidak ditemukan');
        }
        
        // Ambil informasi pembelian (Good Receive) untuk item ini
        $goodReceives = DB::table('food_good_receive_items')
            ->join('food_good_receives', 'food_good_receive_items.good_receive_id', '=', 'food_good_receives.id')
            ->join('suppliers', 'food_good_receives.supplier_id', '=', 'suppliers.id')
            ->where('food_good_receive_items.item_id', $id)
            ->select(
                'food_good_receives.id',
                'food_good_receives.gr_number',
                'food_good_receives.receive_date',
                'suppliers.name as supplier_name',
                'food_good_receive_items.qty_received'
            )
            ->orderBy('food_good_receives.receive_date', 'desc')
            ->limit(10)
            ->get();
            

            
        // Ambil informasi Butcher Process untuk item ini (jika item PCS)
        $butcherProcesses = DB::table('butcher_process_items')
            ->join('butcher_processes', 'butcher_process_items.butcher_process_id', '=', 'butcher_processes.id')
            ->join('items as whole_item', 'butcher_process_items.whole_item_id', '=', 'whole_item.id')
            ->where('butcher_process_items.pcs_item_id', $id)
            ->select(
                'butcher_processes.id',
                'butcher_processes.number',
                'butcher_processes.process_date',
                'whole_item.name as whole_item_name',
                'butcher_process_items.pcs_qty',
                'butcher_process_items.whole_qty'
            )
            ->orderBy('butcher_processes.process_date', 'desc')
            ->limit(10)
            ->get();
            

            

            
        // Ambil sertifikat halal yang terkait dengan item ini (melalui butcher process)
        $halalCertificates = DB::table('butcher_halal_certificates')
            ->join('butcher_processes', 'butcher_halal_certificates.butcher_process_id', '=', 'butcher_processes.id')
            ->join('butcher_process_items', 'butcher_processes.id', '=', 'butcher_process_items.butcher_process_id')
            ->where('butcher_process_items.pcs_item_id', $id)
            ->select(
                'butcher_halal_certificates.id',
                'butcher_halal_certificates.producer_name',
                'butcher_halal_certificates.certificate_number',
                'butcher_halal_certificates.file_path',
                'butcher_processes.process_date'
            )
            ->orderBy('butcher_processes.process_date', 'desc')
            ->limit(5)
            ->get();
            

            
        // Ambil barcode untuk item ini
        $barcodes = DB::table('item_barcodes')
            ->where('item_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
            
                        return Inertia::render('Items/Detail', [
                    'item' => $item,
                    'goodReceives' => $goodReceives,
                    'butcherProcesses' => $butcherProcesses,
                    'halalCertificates' => $halalCertificates,
                    'barcodes' => $barcodes,
                ]);
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

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        try {
            $data = Excel::toArray([], $file);

            // Cari sheet dengan header 'Name', 'Category', dst (sheet Items)
            $rows = [];
            foreach ($data as $sheetIndex => $sheet) {
                if (isset($sheet[0]) && in_array('Name', $sheet[0]) && in_array('Category', $sheet[0])) {
                    $rows = $sheet;
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

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file);

        // Cari sheet dengan header 'Name', 'Category', dst (sheet Items)
        $rows = [];
        foreach ($data as $sheetIndex => $sheet) {
            if (isset($sheet[0]) && in_array('Name', $sheet[0]) && in_array('Category', $sheet[0])) {
                $rows = $sheet;
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
                    throw new \Exception('Baris ' . ($rowIdx + 2) . ': Data wajib tidak lengkap: ' . implode(', ', $missingFields));
                }

                // Cari relasi
                $category = Category::where('name', $itemData['Category'])->first();
                if (!$category) {
                    throw new \Exception('Baris ' . ($rowIdx + 2) . ': Category tidak ditemukan: ' . $itemData['Category']);
                }

                $subCategory = null;
                if (!empty($itemData['Sub Category'])) {
                    $subCategory = SubCategory::where('name', $itemData['Sub Category'])->first();
                    if (!$subCategory) {
                        throw new \Exception('Baris ' . ($rowIdx + 2) . ': Sub Category tidak ditemukan: ' . $itemData['Sub Category']);
                    }
                }

                // Cari relasi Warehouse Division (nullable)
                $warehouseDivision = null;
                if (!empty($itemData['Warehouse Division'])) {
                    $warehouseDivision = WarehouseDivision::where('name', $itemData['Warehouse Division'])->first();
                    if (!$warehouseDivision) {
                        throw new \Exception('Baris ' . ($rowIdx + 2) . ': Warehouse Division tidak ditemukan: ' . $itemData['Warehouse Division']);
                    }
                }

                $smallUnit = Unit::where('name', $itemData['Small Unit'])->first();
                if (!$smallUnit) {
                    throw new \Exception('Baris ' . ($rowIdx + 2) . ': Small Unit tidak ditemukan: ' . $itemData['Small Unit']);
                }

                $mediumUnit = Unit::where('name', $itemData['Medium Unit'])->first();
                if (!$mediumUnit) {
                    throw new \Exception('Baris ' . ($rowIdx + 2) . ': Medium Unit tidak ditemukan: ' . $itemData['Medium Unit']);
                }

                $largeUnit = Unit::where('name', $itemData['Large Unit'])->first();
                if (!$largeUnit) {
                    throw new \Exception('Baris ' . ($rowIdx + 2) . ': Large Unit tidak ditemukan: ' . $itemData['Large Unit']);
                }

                $menuType = null;
                if (!empty($itemData['Type'])) {
                    $menuType = DB::table('menu_type')->where('type', $itemData['Type'])->first();
                    if (!$menuType) {
                        throw new \Exception('Baris ' . ($rowIdx + 2) . ': Menu Type tidak ditemukan: ' . $itemData['Type']);
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
                            throw new \Exception('Baris ' . ($rowIdx + 2) . ': Modifier Option tidak ditemukan: ' . $optionName);
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
                            } else {
                                throw new \Exception('Baris ' . ($rowIdx + 2) . ': BOM item atau unit tidak ditemukan: ' . $bomItem);
                            }
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
            
            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ItemController@importExcel - Error during import, rolling back', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => $e->getMessage(),
                'error' => true
            ], 400);
        }
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
        $item = \App\Models\Item::with(['images', 'smallUnit', 'mediumUnit', 'largeUnit'])->findOrFail($id);
        $units = [];
        if ($item->small_unit_id && $item->smallUnit) {
            $units[] = [
                'id' => $item->small_unit_id,
                'name' => $item->smallUnit->name,
                'type' => 'small',
            ];
        }
        if ($item->medium_unit_id && $item->mediumUnit) {
            $units[] = [
                'id' => $item->medium_unit_id,
                'name' => $item->mediumUnit->name,
                'type' => 'medium',
            ];
        }
        if ($item->large_unit_id && $item->largeUnit) {
            $units[] = [
                'id' => $item->large_unit_id,
                'name' => $item->largeUnit->name,
                'type' => 'large',
            ];
        }
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
                'units' => $units,
            ]
        ]);
    }

    /**
     * Ambil item berdasarkan warehouse_division_id yang terkait dengan FO Schedule tertentu dan status=active
     */
    public function getByFOSchedule($fo_schedule_id)
    {
        $region_id = request('region_id');
        $outlet_id = request('outlet_id');
        $exclude_supplier = request('exclude_supplier', false);
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
        
        // Jika exclude_supplier true, kecualikan item yang ada di item_supplier
        if ($exclude_supplier) {
            $supplierItemIds = \DB::table('item_supplier')
                ->pluck('item_id')
                ->unique();
            $availableItemIds = $availableItemIds->diff($supplierItemIds);
        }
        
        $items = Item::whereIn('id', $availableItemIds)
            ->with(['category', 'mediumUnit', 'smallUnit', 'largeUnit'])
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
                // Round up to nearest 100
                $roundedPrice = ceil($finalPrice / 100) * 100;
                
                return array_merge($item->toArray(), [
                    'category_name' => $item->category ? $item->category->name : '-',
                    'unit_medium_name' => $item->mediumUnit ? $item->mediumUnit->name : '-',
                    'unit_medium' => $item->mediumUnit ? $item->mediumUnit->name : '-',
                    'unit_small' => $item->smallUnit ? $item->smallUnit->name : '-',
                    'unit_large' => $item->largeUnit ? $item->largeUnit->name : '-',
                    'price' => $roundedPrice,
                ]);
            });
        // Log debug hasil akhir
        return response()->json([
            'items' => $items
        ]);
    }

    /**
     * Ambil item untuk FO Khusus (tanpa jadwal, validasi dari item_availabilities)
     */
    public function getByFOKhusus(Request $request)
    {
        $region_id = $request->region_id;
        $outlet_id = $request->outlet_id;
        $exclude_supplier = $request->exclude_supplier ?? false;

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

        // Jika exclude_supplier true, kecualikan item yang ada di item_supplier
        if ($exclude_supplier) {
            $supplierItemIds = \DB::table('item_supplier')
                ->pluck('item_id')
                ->unique();
            $itemIds = $itemIds->diff($supplierItemIds);
        }
        
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
                // Round up to nearest 100
                $roundedPrice = ceil($finalPrice / 100) * 100;
                return array_merge($item->toArray(), [
                    'category_name' => $item->category ? $item->category->name : '-',
                    'unit_medium_name' => $item->mediumUnit ? $item->mediumUnit->name : '-',
                    'price' => $roundedPrice,
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

    public function downloadPriceUpdateTemplate(Request $request)
    {
        // Support single atau multiple values (bisa dari region_id[] atau region_id)
        $regionIds = $request->input('region_id', []);
        $outletIds = $request->input('outlet_id', []);
        $categoryIds = $request->input('category_id', []);
        $subCategoryIds = $request->input('sub_category_id', []);
        $priceType = $request->get('price_type', 'all');

        // Normalize to array - handle both single value and array
        $regionIds = is_array($regionIds) ? array_filter($regionIds) : (!empty($regionIds) ? [$regionIds] : []);
        $outletIds = is_array($outletIds) ? array_filter($outletIds) : (!empty($outletIds) ? [$outletIds] : []);
        $categoryIds = is_array($categoryIds) ? array_filter($categoryIds) : (!empty($categoryIds) ? [$categoryIds] : []);
        $subCategoryIds = is_array($subCategoryIds) ? array_filter($subCategoryIds) : (!empty($subCategoryIds) ? [$subCategoryIds] : []);

        return Excel::download(
            new \App\Exports\PriceUpdateTemplateExport($regionIds, $outletIds, $categoryIds, $subCategoryIds, $priceType),
            'price_update_template.xlsx'
        );
    }

    public function previewPriceUpdate(Request $request)
    {
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\PriceUpdateImport, $request->file('file'));
            
            $rows = $data[0] ?? [];
            if (empty($rows)) {
                throw new \Exception('File kosong atau tidak valid');
            }
            
            // Get headers from first row
            $headers = array_keys($rows[0] ?? []);
            if (empty($headers)) {
                throw new \Exception('Header tidak ditemukan');
            }
            
            // Take first 5 rows for preview
            $preview = array_slice($rows, 0, 5);
            
            return response()->json([
                'header' => $headers,
                'preview' => $preview
            ]);
        } catch (\Exception $e) {
            \Log::error('ItemController@previewPriceUpdate - Error during preview', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function importPriceUpdate(Request $request)
    {
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            $import = new \App\Imports\PriceUpdateImport;
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil mengupdate {$import->getSuccessCount()} harga item",
                'results' => $import->getResults(),
                'errors' => $import->getErrors(),
                'error_count' => $import->getErrorCount(),
                'success_count' => $import->getSuccessCount()
            ]);
        } catch (\Exception $e) {
            \Log::error('ItemController@importPriceUpdate - Error during import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function previewBomImport(Request $request)
    {
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\BomImport, $request->file('file'));
            
            $bomSheet = $data['BOM'] ?? [];
            if (empty($bomSheet)) {
                throw new \Exception('Sheet BOM tidak ditemukan atau kosong');
            }
            
            // Get headers from first row
            $headers = array_keys($bomSheet[0] ?? []);
            if (empty($headers)) {
                throw new \Exception('Header tidak ditemukan di sheet BOM');
            }
            
            // Take first 5 rows for preview
            $preview = array_slice($bomSheet, 0, 5);
            
            return response()->json([
                'header' => $headers,
                'preview' => $preview
            ]);
        } catch (\Exception $e) {
            \Log::error('ItemController@previewBomImport - Error during preview', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            
            $bomSheet = $data['BOM'] ?? [];
            if (empty($bomSheet)) {
                throw new \Exception('Sheet BOM tidak ditemukan atau kosong');
            }
            
            $results = [];
            
            // Mulai transaction untuk rollback jika ada error
            DB::beginTransaction();
            
            try {
                foreach ($bomSheet as $index => $row) {
                    
                    // Validasi minimal kolom BOM berdasarkan header
                    $parentItemName = $row['Parent Item'] ?? $row['parent_item'] ?? $row[0] ?? null;
                    $childItemName = $row['Child Item'] ?? $row['child_item'] ?? $row[1] ?? null;
                    $quantity = $row['Quantity'] ?? $row['quantity'] ?? $row[2] ?? null;
                    $unitName = $row['Unit'] ?? $row['unit'] ?? $row[3] ?? null;
                    
                    if (empty($parentItemName) || empty($childItemName) || empty($quantity) || empty($unitName)) {
                        throw new \Exception('Baris ' . ($index + 1) . ': Semua kolom wajib diisi');
                    }
                    
                    // Find parent item
                    $parentItem = \App\Models\Item::where('name', $parentItemName)
                        ->where('composition_type', 'composed')
                        ->where('status', 'active')
                        ->first();
                    if (!$parentItem) {
                        throw new \Exception('Baris ' . ($index + 1) . ': Parent item not found or not active: ' . $parentItemName);
                    }
                    
                    // Find child item
                    $childItem = \App\Models\Item::where('name', $childItemName)
                        ->whereIn('type', ['Raw Materials', 'WIP', 'Finish Goods'])
                        ->where('status', 'active')
                        ->first();
                    if (!$childItem) {
                        throw new \Exception('Baris ' . ($index + 1) . ': Child item not found or not active: ' . $childItemName);
                    }
                    
                    // Find unit
                    $unit = \App\Models\Unit::where('name', $unitName)->first();
                    if (!$unit) {
                        throw new \Exception('Baris ' . ($index + 1) . ': Unit not found: ' . $unitName);
                    }
                    
                    // Insert/update BOM
                    \DB::table('item_bom')->updateOrInsert(
                        [
                            'item_id' => $parentItem->id,
                            'material_item_id' => $childItem->id,
                        ],
                        [
                            'qty' => $quantity,
                            'unit_id' => $unit->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    
                    $results[] = [
                        'row' => $index + 1,
                        'name' => $parentItemName,
                        'status' => 'success',
                        'message' => 'Successfully imported'
                    ];
                }
                
                // Jika semua baris berhasil, commit transaction
                DB::commit();
                
                return response()->json(['results' => $results]);
                
            } catch (\Exception $e) {
                // Rollback transaction jika ada error
                DB::rollBack();
                
                \Log::error('ItemController@importBom - Error during import, rolling back', [
                    'error' => $e->getMessage(),
                    'row_index' => $index + 1 ?? 'unknown'
                ]);
                
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => true
                ], 422);
            }
            
        } catch (\Exception $e) {
            \Log::error('ItemController@importBom - Error during file processing', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        $warehouse_outlet_id = $request->warehouse_outlet_id;
        
        // Ambil outlet_id dan region_id dari request langsung (jika dikirim)
        $outlet_id = $request->outlet_id;
        $region_id = $request->region_id;

        // Jika tidak ada, ambil dari warehouse_outlet_id
        if (!$outlet_id && $warehouse_outlet_id) {
            $outlet_id = DB::table('warehouse_outlets')
                ->where('id', $warehouse_outlet_id)
                ->value('outlet_id');
        }

        // Ambil region_id dari outlet jika belum ada
        if (!$region_id && $outlet_id) {
            $region_id = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outlet_id)
                ->value('region_id');
        }

        $items = \DB::table('item_availabilities')
            ->join('items', 'item_availabilities.item_id', '=', 'items.id')
            ->leftJoin('units as u_small', 'items.small_unit_id', '=', 'u_small.id')
            ->leftJoin('units as u_medium', 'items.medium_unit_id', '=', 'u_medium.id')
            ->leftJoin('units as u_large', 'items.large_unit_id', '=', 'u_large.id')
            ->where('items.status', 'active')
            ->where(function($query) use ($outlet_id, $region_id) {
                $query->where('item_availabilities.availability_type', 'all')
                    ->orWhere(function($q) use ($region_id) {
                        if ($region_id) {
                            $q->where('item_availabilities.availability_type', 'region')
                              ->where('item_availabilities.region_id', $region_id);
                        }
                    })
                    ->orWhere(function($q) use ($outlet_id) {
                        if ($outlet_id) {
                            $q->where('item_availabilities.availability_type', 'outlet')
                              ->where('item_availabilities.outlet_id', $outlet_id);
                        }
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
            ->distinct()
            ->get();

        // Debug logging (uncomment jika perlu debug)
        // \Log::info('searchForOutletTransfer', [
        //     'q' => $q,
        //     'warehouse_outlet_id' => $warehouse_outlet_id,
        //     'outlet_id' => $outlet_id,
        //     'region_id' => $region_id,
        //     'result_count' => $items->count(),
        // ]);

        foreach ($items as $item) {
            $item->unit_small = optional(\DB::table('units')->where('id', $item->small_unit_id)->first())->name;
            $item->unit_medium = optional(\DB::table('units')->where('id', $item->medium_unit_id)->first())->name;
            $item->unit_large = optional(\DB::table('units')->where('id', $item->large_unit_id)->first())->name;
        }

        return response()->json($items);
    }

    public function searchForInternalWarehouseTransfer(Request $request)
    {
        $q = $request->q;
        $warehouse_outlet_id = $request->warehouse_outlet_id;

        // Ambil outlet_id dari warehouse_outlet
        $outlet_id = null;
        if ($warehouse_outlet_id) {
            $outlet_id = DB::table('warehouse_outlets')
                ->where('id', $warehouse_outlet_id)
                ->value('outlet_id');
        }

        // Ambil region_id dari outlet
        $region_id = null;
        if ($outlet_id) {
            $region_id = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outlet_id)
                ->value('region_id');
        }

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
        
        // Ambil region_id dari outlet
        $region_id = null;
        if ($outletId) {
            $region_id = DB::table('tbl_data_outlet')
                ->where('id_outlet', $outletId)
                ->value('region_id');
        }
        
        
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
                'item_supplier.price as supplier_price', // Rename untuk membedakan
                'units.name as unit',
                'units.id as unit_id',
                'items.small_unit_id',
                'items.medium_unit_id',
                'items.large_unit_id'
            )
            ->get();
        
        $items = $items->map(function($item) use ($region_id, $outletId) {
            // Ambil harga dari item_prices dengan prioritas outlet > region > all (sama seperti RO utama)
            $price = \DB::table('item_prices')
                ->where('item_id', $item->id)
                ->where(function($q) use ($region_id, $outletId) {
                    $q->where('availability_price_type', 'all');
                    if ($region_id) {
                        $q->orWhere(function($q2) use ($region_id) {
                            $q2->where('availability_price_type', 'region')->where('region_id', $region_id);
                        });
                    }
                    if ($outletId) {
                        $q->orWhere(function($q2) use ($outletId) {
                            $q2->where('availability_price_type', 'outlet')->where('outlet_id', $outletId);
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
            // Round up to nearest 100
            $roundedPrice = ceil($finalPrice / 100) * 100;
            
            // Tambahkan unit names untuk konsistensi dengan RO utama
            $unit_small = DB::table('units')->where('id', $item->small_unit_id)->value('name');
            $unit_medium = DB::table('units')->where('id', $item->medium_unit_id)->value('name');
            $unit_large = DB::table('units')->where('id', $item->large_unit_id)->value('name');
            
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'category_id' => $item->category_id,
                'category_name' => $item->category_name,
                'supplier_price' => $item->supplier_price, // Harga dari supplier (untuk referensi)
                'price' => $roundedPrice, // Harga yang digunakan (dari item_prices) dengan rounding
                'unit' => $item->unit,
                'unit_id' => $item->unit_id,
                'unit_small' => $unit_small,
                'unit_medium' => $unit_medium,
                'unit_medium_name' => $unit_medium, // Untuk konsistensi dengan RO utama
                'unit_large' => $unit_large,
                'small_unit_id' => $item->small_unit_id,
                'medium_unit_id' => $item->medium_unit_id,
                'large_unit_id' => $item->large_unit_id
            ];
        });
            
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

            if ($excludeSupplier) {
                $query->whereNotExists(function($sub) {
                    $sub->select(\DB::raw(1))
                        ->from('item_supplier')
                        ->whereRaw('items.id = item_supplier.item_id');
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
                // Round up to nearest 100
                $roundedPrice = ceil($finalPrice / 100) * 100;
                
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
                    'price_medium' => $roundedPrice,
                    'price' => $roundedPrice,
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

    /**
     * API: List item untuk BOM Modifier Option (status=active, kategori show_pos=0)
     */
    public function apiForModifierBom()
    {
        
        // Cek dulu semua items active
        $allActiveItems = \DB::table('items')
            ->where('status', 'active')
            ->select('id', 'name', 'category_id')
            ->get();
        
        // Cek categories dengan show_pos = 0
        $categoriesShowPos0 = \DB::table('categories')
            ->where('show_pos', '0')
            ->select('id', 'name', 'show_pos')
            ->get();
        
        // Query final
        $items = \DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('items.status', 'active')
            ->where('categories.show_pos', '0')
            ->select('items.id', 'items.name')
            ->orderBy('items.name')
            ->get();
        
        return response()->json(['items' => $items]);
    }

    /**
     * API: Search items for outlet stock adjustment (menerima outlet_id dan region_id)
     */
    public function searchForOutletStockAdjustment(Request $request)
    {
        $q = $request->input('q');
        $outlet_id = $request->input('outlet_id');
        $region_id = $request->input('region_id');

        if (!$q || !$outlet_id || !$region_id) {
            \Log::warning('Missing required parameters', [
                'q' => $q,
                'outlet_id' => $outlet_id,
                'region_id' => $region_id
            ]);
            return response()->json([]);
        }

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
            ->where('items.status', 'active')
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
            ->orderBy('items.name')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    /**
     * API: Get categories and sub categories based on selected regions/outlets
     */
    public function getCategoriesByRegionOutlet(Request $request)
    {
        try {
            $regionIds = $request->input('region_ids', []);
            $outletIds = $request->input('outlet_ids', []);
            $priceType = $request->input('price_type', 'all');

            // Normalize to array
            $regionIds = is_array($regionIds) ? array_filter($regionIds) : (!empty($regionIds) ? [$regionIds] : []);
            $outletIds = is_array($outletIds) ? array_filter($outletIds) : (!empty($outletIds) ? [$outletIds] : []);

            // Jika user pilih region, ambil juga outlet_ids yang ada di region tersebut
            if (!empty($regionIds)) {
                $outletsInRegions = \DB::table('tbl_data_outlet')
                    ->whereIn('region_id', $regionIds)
                    ->where('status', 'A')
                    ->pluck('id_outlet')
                    ->toArray();
                // Gabungkan dengan outletIds yang sudah dipilih
                $outletIds = array_unique(array_merge($outletIds, $outletsInRegions));
            }

            // Jika user pilih outlet, ambil juga region_ids dari outlet tersebut
            if (!empty($outletIds)) {
                $regionsFromOutlets = \DB::table('tbl_data_outlet')
                    ->whereIn('id_outlet', $outletIds)
                    ->where('status', 'A')
                    ->pluck('region_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                // Gabungkan dengan regionIds yang sudah dipilih
                $regionIds = array_unique(array_merge($regionIds, $regionsFromOutlets));
            }

            // Query items yang memiliki price atau availability di region/outlet yang dipilih
            $itemQuery = Item::where('status', 'active');

            // Filter berdasarkan region/outlet melalui item_prices atau item_availabilities
            if (!empty($regionIds) || !empty($outletIds)) {
                $itemQuery->where(function($q) use ($regionIds, $outletIds, $priceType) {
                    // Filter melalui item_prices
                    $q->whereHas('prices', function($priceQ) use ($regionIds, $outletIds, $priceType) {
                        $priceQ->where(function($pq) use ($regionIds, $outletIds, $priceType) {
                            // Always include 'all' type
                            $pq->where('availability_price_type', 'all');
                            
                            // Include region prices
                            if (!empty($regionIds)) {
                                $pq->orWhere(function($rq) use ($regionIds) {
                                    $rq->where('availability_price_type', 'region')
                                       ->whereIn('region_id', $regionIds);
                                });
                            }
                            
                            // Include outlet prices
                            if (!empty($outletIds)) {
                                $pq->orWhere(function($oq) use ($outletIds) {
                                    $oq->where('availability_price_type', 'outlet')
                                       ->whereIn('outlet_id', $outletIds);
                                });
                            }
                        });
                    })
                    // Atau filter melalui item_availabilities
                    ->orWhereHas('availabilities', function($availQ) use ($regionIds, $outletIds) {
                        $availQ->where(function($aq) use ($regionIds, $outletIds) {
                            // Always include 'all' type
                            $aq->where('availability_type', 'all');
                            
                            // Include region availabilities
                            if (!empty($regionIds)) {
                                $aq->orWhere(function($rq) use ($regionIds) {
                                    $rq->where('availability_type', 'region')
                                       ->whereIn('region_id', $regionIds);
                                });
                            }
                            
                            // Include outlet availabilities
                            if (!empty($outletIds)) {
                                $aq->orWhere(function($oq) use ($outletIds) {
                                    $oq->where('availability_type', 'outlet')
                                       ->whereIn('outlet_id', $outletIds);
                                });
                            }
                        });
                    });
                });
            }

            // Get unique category_ids and sub_category_ids
            $items = $itemQuery->select('category_id', 'sub_category_id')
                ->distinct()
                ->get();

            $categoryIds = $items->pluck('category_id')->filter()->unique()->values()->toArray();
            $subCategoryIds = $items->pluck('sub_category_id')->filter()->unique()->values()->toArray();

            // Get categories and sub categories
            $categories = \App\Models\Category::whereIn('id', $categoryIds)->get(['id', 'name']);
            $subCategories = \App\Models\SubCategory::whereIn('id', $subCategoryIds)->get(['id', 'name']);

            return response()->json([
                'categories' => $categories,
                'sub_categories' => $subCategories
            ]);
        } catch (\Exception $e) {
            \Log::error('ItemController@getCategoriesByRegionOutlet - Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to get categories',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Get barcodes for an item
     */
    public function getItemBarcodes($itemId)
    {
        try {
            $barcodes = \DB::table('item_barcodes')
                ->where('item_id', $itemId)
                ->select('id', 'barcode', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'barcodes' => $barcodes
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting item barcodes', [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting barcodes',
                'barcodes' => []
            ], 500);
        }
    }

    // ---------- Approval App API ----------

    public function apiIndex(Request $request)
    {
        $query = Item::with([
            'category',
            'subCategory',
            'smallUnit',
            'mediumUnit',
            'largeUnit',
            'images',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('sku', 'like', "%$search%");
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 15), 50);
        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function apiCreateData()
    {
        $categories = Category::all();
        $subCategories = SubCategory::where('status', 'active')->get();
        $units = Unit::all();
        $warehouseDivisions = WarehouseDivision::where('status', 'active')->get();
        $menuTypes = \DB::table('menu_type')->where('status', 'active')->get();
        $regions = \DB::table('regions')->where('status', 'active')->get()->values();
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->get()->values();
        $bomItems = Item::with('smallUnit')->where('status', 'active')->orderBy('name')->get();
        $modifiers = Modifier::with('options')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'sub_categories' => $subCategories,
            'units' => $units,
            'warehouse_divisions' => $warehouseDivisions,
            'menu_types' => $menuTypes,
            'regions' => $regions,
            'outlets' => $outlets,
            'bom_items' => $bomItems,
            'modifiers' => $modifiers,
        ]);
    }

    public function apiShow($id)
    {
        $response = $this->show($id);
        $content = $response->getData(true);
        if (isset($content['error'])) {
            return $response;
        }
        return response()->json(['success' => true, 'item' => $content['item'] ?? $content]);
    }

    public function apiStore(Request $request)
    {
        $allowedTypes = \DB::table('menu_type')->pluck('type')->toArray();
        if ($request->has('modifier_enabled')) {
            $request->merge([
                'modifier_enabled' => filter_var($request->modifier_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'warehouse_division_id' => 'nullable',
            'sku' => 'required|string|max:255|unique:items',
            'type' => ['nullable', Rule::in(array_merge($allowedTypes, ['product', 'service']))],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'specification' => 'nullable|string',
            'small_unit_id' => 'required|exists:units,id',
            'medium_unit_id' => 'nullable|exists:units,id',
            'large_unit_id' => 'nullable|exists:units,id',
            'medium_conversion_qty' => 'nullable|numeric|min:0',
            'small_conversion_qty' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'status' => 'required|string|in:active,inactive',
            'composition_type' => 'required|in:single,composed',
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
            'bom' => 'nullable|array',
            'bom.*.item_id' => 'required|exists:items,id',
            'bom.*.qty' => 'required|numeric|min:0',
            'bom.*.unit_id' => 'required|exists:units,id',
            'exp' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();
            $item = Item::create(array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
                'exp' => $request->exp ?? 0,
                'min_stock' => $request->min_stock ?? 0,
                'medium_unit_id' => $request->medium_unit_id ?? $request->small_unit_id,
                'large_unit_id' => $request->large_unit_id ?? $request->small_unit_id,
                'medium_conversion_qty' => $request->medium_conversion_qty ?? 0,
                'small_conversion_qty' => $request->small_conversion_qty ?? 0,
            ]));

            if ($item->modifier_enabled && $request->modifier_option_ids) {
                $item->modifierOptions()->sync($request->modifier_option_ids);
            }
            if ($request->composition_type === 'composed' && $request->bom) {
                foreach ($request->bom as $bom) {
                    $item->boms()->create([
                        'material_item_id' => $bom['item_id'],
                        'qty' => $bom['qty'],
                        'unit_id' => $bom['unit_id'],
                    ]);
                }
            }
            if ($request->prices) {
                foreach ($request->prices as $price) {
                    $type = 'all';
                    if (!empty($price['region_id']) && empty($price['outlet_id'])) {
                        $type = 'region';
                    } elseif (!empty($price['outlet_id'])) {
                        $type = 'outlet';
                    }
                    $item->prices()->create([
                        'region_id' => $price['region_id'] ?? null,
                        'outlet_id' => $price['outlet_id'] ?? null,
                        'price' => $price['price'],
                        'availability_price_type' => $type,
                    ]);
                }
            }
            if ($request->availabilities) {
                foreach ($request->availabilities as $availability) {
                    $type = 'all';
                    if (!empty($availability['region_id']) && empty($availability['outlet_id'])) {
                        $type = 'region';
                    } elseif (!empty($availability['outlet_id'])) {
                        $type = 'outlet';
                    }
                    $item->availabilities()->create([
                        'region_id' => $availability['region_id'] ?? null,
                        'outlet_id' => $availability['outlet_id'] ?? null,
                        'availability_type' => $type,
                    ]);
                }
            }
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'items',
                'description' => 'Membuat item baru (App): ' . $item->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $item->toArray(),
            ]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Item created.', 'item' => $item->load(['category', 'subCategory', 'smallUnit'])]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ItemController@apiStore', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function apiUpdate(Request $request, $id)
    {
        $item = Item::findOrFail($id);
        $allowedTypes = \DB::table('menu_type')->pluck('type')->toArray();
        if ($request->has('modifier_enabled')) {
            $request->merge([
                'modifier_enabled' => filter_var($request->modifier_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'warehouse_division_id' => 'nullable',
            'sku' => 'required|string|max:255|unique:items,sku,' . $item->id,
            'type' => ['nullable', Rule::in(array_merge($allowedTypes, ['product', 'service']))],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'specification' => 'nullable|string',
            'small_unit_id' => 'required|exists:units,id',
            'medium_unit_id' => 'nullable|exists:units,id',
            'large_unit_id' => 'nullable|exists:units,id',
            'medium_conversion_qty' => 'nullable|numeric|min:0',
            'small_conversion_qty' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'status' => 'required|string|in:active,inactive',
            'composition_type' => 'required|in:single,composed',
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
            'bom' => 'nullable|array',
            'bom.*.item_id' => 'required|exists:items,id',
            'bom.*.qty' => 'required|numeric|min:0',
            'bom.*.unit_id' => 'required|exists:units,id',
            'exp' => 'nullable|integer|min:0',
        ]);

        try {
            DB::beginTransaction();
            $item->update(array_merge($validated, [
                'modifier_enabled' => $request->modifier_enabled ? 1 : 0,
                'composition_type' => $request->composition_type,
                'exp' => $request->exp ?? 0,
                'min_stock' => $request->min_stock ?? 0,
                'medium_unit_id' => $request->medium_unit_id ?? $item->small_unit_id,
                'large_unit_id' => $request->large_unit_id ?? $item->small_unit_id,
                'medium_conversion_qty' => $request->medium_conversion_qty ?? 0,
                'small_conversion_qty' => $request->small_conversion_qty ?? 0,
            ]));

            if ($request->has('prices')) {
                $item->prices()->delete();
                if ($request->prices) {
                    foreach ($request->prices as $price) {
                        $type = 'all';
                        if (!empty($price['region_id']) && empty($price['outlet_id'])) {
                            $type = 'region';
                        } elseif (!empty($price['outlet_id'])) {
                            $type = 'outlet';
                        }
                        $item->prices()->create([
                            'region_id' => $price['region_id'] ?? null,
                            'outlet_id' => $price['outlet_id'] ?? null,
                            'price' => $price['price'],
                            'availability_price_type' => $type,
                        ]);
                    }
                }
            }
            if ($request->has('availabilities')) {
                $item->availabilities()->delete();
                if ($request->availabilities) {
                    foreach ($request->availabilities as $availability) {
                        $type = 'all';
                        if (!empty($availability['region_id']) && empty($availability['outlet_id'])) {
                            $type = 'region';
                        } elseif (!empty($availability['outlet_id'])) {
                            $type = 'outlet';
                        }
                        $item->availabilities()->create([
                            'region_id' => $availability['region_id'] ?? null,
                            'outlet_id' => $availability['outlet_id'] ?? null,
                            'availability_type' => $type,
                        ]);
                    }
                }
            }
            if ($request->has('bom')) {
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
            }
            if ($request->has('modifier_option_ids')) {
                if ($item->modifier_enabled && $request->modifier_option_ids) {
                    $item->modifierOptions()->sync($request->modifier_option_ids);
                } else {
                    $item->modifierOptions()->detach();
                }
            }
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'items',
                'description' => 'Mengupdate item (App): ' . $item->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $item->fresh()->toArray(),
            ]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Item updated.', 'item' => $item->fresh()->load(['category', 'subCategory', 'smallUnit'])]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ItemController@apiUpdate', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function apiToggleStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:active,inactive']);
        $item = Item::findOrFail($id);
        $item->status = $request->status;
        $item->save();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'items',
            'description' => 'Mengubah status item (App): ' . $item->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $item->toArray(),
        ]);
        return response()->json(['success' => true, 'message' => 'Status updated.', 'item' => $item]);
    }

    public function apiDestroy($id)
    {
        $item = Item::findOrFail($id);
        $item->status = 'inactive';
        $item->save();
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'items',
            'description' => 'Menonaktifkan item (App): ' . $item->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => null,
            'new_data' => $item->toArray(),
        ]);
        return response()->json(['success' => true, 'message' => 'Item set to inactive.']);
    }
} 