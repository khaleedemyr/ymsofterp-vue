<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Promo;
use App\Models\Category;
use App\Models\Item;
use App\Models\Outlet;
use App\Models\Region;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $query = Promo::query();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $promos = $query->with(['categories', 'items', 'outlets'])
            ->latest()
            ->get()
            ->map(function ($promo) {
                return [
                    'id' => $promo->id,
                    'name' => $promo->name,
                    'type' => $promo->type,
                    'value' => $promo->value,
                    'start_date' => $promo->start_date,
                    'end_date' => $promo->end_date,
                    'status' => $promo->status,
                ];
            });

        return Inertia::render('Promos/Index', [
            'promos' => $promos,
            'search' => $request->search,
            'type' => $request->type,
        ]);
    }

    public function create()
    {
        $categories = \App\Models\Category::where('show_pos', '1')
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();
        $items = \App\Models\Item::where('status', 'active')
            ->whereHas('category', function($q) {
                $q->where('show_pos', '1');
            })
            ->select('id', 'name')
            ->get();
        $outlets = \App\Models\Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();
        $regions = \App\Models\Region::where('status', 'active')->select('id', 'name')->get();
        return Inertia::render('Promos/Form', [
            'categories' => $categories,
            'items' => $items,
            'outlets' => $outlets,
            'regions' => $regions,
            'isEdit' => false
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:50|unique:promos,code',
                'type' => 'required|in:percent,nominal,bundle,bogo,harga_coret,bill_discount_percent,bill_discount_nominal',
                'value' => 'required_if:type,percent,nominal,bundle,bill_discount_percent,bill_discount_nominal|nullable|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'is_multiple' => 'required|in:Yes,No',
                'min_transaction' => 'nullable|numeric|min:0',
                'max_transaction' => 'nullable|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'nullable|string',
                'end_time' => 'nullable|string',
                'days' => 'nullable|array',
                'days.*' => 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
                'description' => 'nullable|string',
                'terms' => 'nullable|string',
                'need_member' => 'required|in:Yes,No',
                'all_tiers' => 'nullable|boolean',
                'tiers' => 'nullable|array',
                'tiers.*' => 'in:Silver,Loyal,Elite,Prestige',
                'banner' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'by_type' => 'required_if:type,percent,nominal,bundle,bogo,harga_coret|nullable|in:kategori,item',
                'categories' => 'required_if:by_type,kategori|array',
                'items' => 'required_if:by_type,item|array',
                'outlets' => 'required_if:outlet_type,outlet|array',
                'regions' => 'required_if:outlet_type,region|array',
                'buy_items' => 'required_if:type,bogo|array',
                'get_items' => 'required_if:type,bogo|array',
                'item_prices' => 'required_if:type,harga_coret|array',
                'status' => 'required|in:active,inactive'
            ]);
            
            // Handle tier logic: if all_tiers is true, set tiers to empty array
            // Convert all_tiers to boolean (handle string "true"/"false" or "1"/"0")
            if (isset($validated['all_tiers'])) {
                $validated['all_tiers'] = filter_var($validated['all_tiers'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($validated['all_tiers'] === null) {
                    $validated['all_tiers'] = false;
                }
                
                if ($validated['all_tiers']) {
                    $validated['tiers'] = [];
                } else {
                    // Ensure tiers is an array
                    if (isset($validated['tiers']) && !is_array($validated['tiers'])) {
                        $validated['tiers'] = [];
                    } elseif (!isset($validated['tiers'])) {
                        $validated['tiers'] = [];
                    }
                }
            } else {
                // If all_tiers is not set, default to false
                $validated['all_tiers'] = false;
                if (!isset($validated['tiers'])) {
                    $validated['tiers'] = [];
                }
            }

            DB::beginTransaction();
            
            // Generate unique code if not provided
            if (empty($validated['code'])) {
                do {
                    $newCode = strtoupper(substr($validated['name'], 0, 3) . random_int(100, 999));
                } while (Promo::where('code', $newCode)->exists());
                $validated['code'] = $newCode;
            }

            // Handle banner upload
            if ($request->hasFile('banner')) {
                $banner = $request->file('banner');
                $bannerPath = $banner->store('promo-banners', 'public');
                $validated['banner'] = $bannerPath;
            }

            // Create promo
            $promo = Promo::create($validated);

            // Handle BOGO items
            if ($request->type === 'bogo') {
                $buyItems = $request->buy_items;
                $getItems = $request->get_items;

                if (count($buyItems) !== count($getItems)) {
                    throw new \Exception('Jumlah item beli dan item gratis harus sama');
                }

                foreach ($buyItems as $index => $buyItemId) {
                    $promo->bogoItems()->create([
                        'buy_item_id' => $buyItemId,
                        'get_item_id' => $getItems[$index]
                    ]);
                }
            }

            // Handle other relationships
            if ($request->type !== 'bill_discount_percent' && $request->type !== 'bill_discount_nominal') {
                if ($request->by_type === 'kategori') {
                    $promo->categories()->attach($request->categories);
                } else if ($request->by_type === 'item') {
                    $promo->items()->attach($request->items);
                }
            }

            if ($request->outlet_type === 'region') {
                $promo->regions()->attach($request->regions);
            } else {
                $promo->outlets()->attach($request->outlets);
            }

            // Handle harga_coret
            if ($request->type === 'harga_coret') {
                $itemPrices = $request->item_prices;
                foreach ($itemPrices as $price) {
                    $promo->itemPrices()->create($price);
                }
            }

            DB::commit();

            return redirect()->route('promos.index')
                ->with('success', 'Promo berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store promo: ' . $e->getMessage());
            return back()->with('error', 'Gagal menambahkan promo: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $promo = Promo::with(['categories', 'items', 'outlets', 'regions', 'itemPrices', 'bogoItems.buyItem', 'bogoItems.getItem'])->findOrFail($id);
        return Inertia::render('Promos/Show', [
            'promo' => [
                ...$promo->toArray(),
                'item_prices' => $promo->itemPrices->map(fn($ip) => [
                    'item_id' => $ip->item_id,
                    'outlet_id' => $ip->outlet_id,
                    'region_id' => $ip->region_id,
                    'item_name' => $ip->item->name ?? '',
                    'outlet_name' => $ip->outlet->nama_outlet ?? '',
                    'region_name' => $ip->region->name ?? '',
                    'new_price' => $ip->new_price,
                ]),
                'bogo_items' => $promo->bogoItems->map(fn($bogo) => [
                    'buy_item' => [
                        'id' => $bogo->buyItem->id,
                        'name' => $bogo->buyItem->name,
                    ],
                    'get_item' => [
                        'id' => $bogo->getItem->id,
                        'name' => $bogo->getItem->name,
                    ],
                ]),
            ]
        ]);
    }

    public function edit($id)
    {
        $promo = Promo::with(['categories', 'items', 'outlets', 'regions', 'bogoItems.buyItem', 'bogoItems.getItem', 'itemPrices'])->findOrFail($id);
        $categories = \App\Models\Category::where('show_pos', '1')
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();
        $items = \App\Models\Item::where('status', 'active')
            ->whereHas('category', function($q) {
                $q->where('show_pos', '1');
            })
            ->select('id', 'name')
            ->get();
        $outlets = \App\Models\Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();
        $regions = \App\Models\Region::where('status', 'active')->select('id', 'name')->get();
    
        // Prepare promo data with relationships
        $promoData = [
            'regions' => $promo->regions->map(fn($r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'outlets' => $promo->outlets->map(fn($o) => ['id' => $o->id_outlet, 'name' => $o->nama_outlet])->values(),
            'categories' => $promo->categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'items' => $promo->items->map(fn($i) => ['id' => $i->id, 'name' => $i->name])->values(),
            'buy_items' => $promo->bogoItems->map(fn($b) => ['id' => $b->buyItem->id, 'name' => $b->buyItem->name])->values(),
            'get_items' => $promo->bogoItems->map(fn($b) => ['id' => $b->getItem->id, 'name' => $b->getItem->name])->values(),
        ] + $promo->toArray();
    
        return Inertia::render('Promos/Form', [
            'promo' => $promoData,
            'categories' => $categories,
            'items' => $items,
            'outlets' => $outlets,
            'regions' => $regions,
            'isEdit' => true
        ]);
    }

    public function update(Request $request, Promo $promo)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:promos,code,' . $promo->id,
                'type' => 'required|in:percent,nominal,bundle,bogo,harga_coret,bill_discount_percent,bill_discount_nominal',
                'value' => 'required_if:type,percent,nominal,bundle,bill_discount_percent,bill_discount_nominal|nullable|numeric|min:0',
                'max_discount' => 'nullable|numeric|min:0',
                'is_multiple' => 'required|in:Yes,No',
                'min_transaction' => 'nullable|numeric|min:0',
                'max_transaction' => 'nullable|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'nullable|string',
                'end_time' => 'nullable|string',
                'days' => 'nullable|array',
                'days.*' => 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
                'description' => 'nullable|string',
                'terms' => 'nullable|string',
                'need_member' => 'required|in:Yes,No',
                'all_tiers' => 'nullable|boolean',
                'tiers' => 'nullable|array',
                'tiers.*' => 'in:Silver,Loyal,Elite,Prestige',
                'banner' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'by_type' => 'required_if:type,percent,nominal,bundle,bogo,harga_coret|nullable|in:kategori,item',
                'categories' => 'required_if:by_type,kategori|array',
                'items' => 'required_if:by_type,item|array',
                'outlets' => 'required_if:outlet_type,outlet|array',
                'regions' => 'required_if:outlet_type,region|array',
                'buy_items' => 'required_if:type,bogo|array',
                'get_items' => 'required_if:type,bogo|array',
                'item_prices' => 'required_if:type,harga_coret|array',
                'status' => 'required|in:active,inactive'
            ]);
            
            // Handle tier logic: if all_tiers is true, set tiers to empty array
            // Convert all_tiers to boolean (handle string "true"/"false" or "1"/"0")
            if (isset($validated['all_tiers'])) {
                $validated['all_tiers'] = filter_var($validated['all_tiers'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($validated['all_tiers'] === null) {
                    $validated['all_tiers'] = false;
                }
                
                if ($validated['all_tiers']) {
                    $validated['tiers'] = [];
                } else {
                    // Ensure tiers is an array
                    if (isset($validated['tiers']) && !is_array($validated['tiers'])) {
                        $validated['tiers'] = [];
                    } elseif (!isset($validated['tiers'])) {
                        $validated['tiers'] = [];
                    }
                }
            } else {
                // If all_tiers is not set, default to false
                $validated['all_tiers'] = false;
                if (!isset($validated['tiers'])) {
                    $validated['tiers'] = [];
                }
            }

            DB::beginTransaction();

            // Handle banner upload
            if ($request->hasFile('banner')) {
                // Delete old banner if exists
                if ($promo->banner) {
                    Storage::disk('public')->delete($promo->banner);
                }
                $banner = $request->file('banner');
                $bannerPath = $banner->store('promo-banners', 'public');
                $validated['banner'] = $bannerPath;
            }

            // Log data sebelum update untuk debugging
            \Log::info('Updating promo', [
                'promo_id' => $promo->id,
                'all_tiers' => $validated['all_tiers'] ?? null,
                'tiers' => $validated['tiers'] ?? null,
                'all_tiers_type' => gettype($validated['all_tiers'] ?? null),
                'tiers_type' => gettype($validated['tiers'] ?? null),
            ]);
            
            // Update promo
            $promo->update($validated);
            
            // Log data setelah update untuk debugging
            $promo->refresh();
            \Log::info('Promo updated', [
                'promo_id' => $promo->id,
                'all_tiers' => $promo->all_tiers,
                'tiers' => $promo->tiers,
            ]);

            // Sync relationships
            if ($request->type !== 'bill_discount_percent' && $request->type !== 'bill_discount_nominal') {
                if ($request->by_type === 'kategori') {
                    $promo->categories()->sync($request->categories);
                    $promo->items()->sync([]); 
                } else if ($request->by_type === 'item') {
                    $promo->items()->sync($request->items);
                    $promo->categories()->sync([]);
                }
            }

            if ($request->outlet_type === 'region') {
                $promo->regions()->sync($request->regions);
                $promo->outlets()->sync([]);
            } else {
                $promo->outlets()->sync($request->outlets);
                $promo->regions()->sync([]);
            }

            // Sync BOGO items
            if ($request->type === 'bogo') {
                $promo->bogoItems()->delete(); // Hapus yang lama
                $buyItems = $request->buy_items;
                $getItems = $request->get_items;
                if (count($buyItems) === count($getItems)) {
                    foreach ($buyItems as $index => $buyItemId) {
                        $promo->bogoItems()->create([
                            'buy_item_id' => $buyItemId,
                            'get_item_id' => $getItems[$index]
                        ]);
                    }
                }
            }

            // Sync Item Prices
            if ($request->type === 'harga_coret') {
                $promo->itemPrices()->delete(); // Hapus yang lama
                $itemPrices = $request->item_prices;
                foreach ($itemPrices as $price) {
                    $promo->itemPrices()->create($price);
                }
            }

            DB::commit();

            return redirect()->route('promos.index')
                ->with('success', 'Promo berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update promo: ' . $e->getMessage());
            return back()->with('error', 'Gagal mengupdate promo: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $promo = Promo::findOrFail($id);
        $promo->delete();

        return redirect()->route('promos.index')
            ->with('success', 'Promo berhasil dihapus!');
    }

    public function apiItemPrices(Request $request)
    {
        $itemIds = $request->input('item_ids', []);
        $outletIds = $request->input('outlet_ids', []);
        $regionIds = $request->input('region_ids', []);
        $result = [];
        if ($outletIds) {
            $prices = \DB::table('item_prices')
                ->whereIn('item_id', $itemIds)
                ->whereIn('outlet_id', $outletIds)
                ->where('availability_price_type', 'outlet')
                ->get();
            foreach ($prices as $row) {
                $result[] = [
                    'item_id' => $row->item_id,
                    'outlet_id' => $row->outlet_id,
                    'region_id' => null,
                    'item_name' => \App\Models\Item::find($row->item_id)?->name,
                    'outlet_name' => \DB::table('tbl_data_outlet')->where('id_outlet', $row->outlet_id)->value('nama_outlet'),
                    'old_price' => $row->price
                ];
            }
        }
        if ($regionIds) {
            $prices = \DB::table('item_prices')
                ->whereIn('item_id', $itemIds)
                ->whereIn('region_id', $regionIds)
                ->where('availability_price_type', 'region')
                ->get();
            foreach ($prices as $row) {
                $result[] = [
                    'item_id' => $row->item_id,
                    'outlet_id' => null,
                    'region_id' => $row->region_id,
                    'item_name' => \App\Models\Item::find($row->item_id)?->name,
                    'region_name' => \App\Models\Region::find($row->region_id)?->name,
                    'old_price' => $row->price
                ];
            }
        }
        return response()->json($result);
    }
} 