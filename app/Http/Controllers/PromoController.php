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
                'name' => 'required|string|max:100',
                'code' => 'nullable|string|max:50|unique:promos,code',
                'type' => 'required|in:percent,nominal,bundle,bogo,harga_coret',
                'value' => 'nullable|numeric|min:0',
                'min_transaction' => 'nullable|numeric|min:0',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'start_time' => 'nullable',
                'end_time' => 'nullable',
                'status' => 'required|in:active,inactive',
                'description' => 'nullable|string',
                'terms' => 'nullable|string',
                'banner' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
                'categories' => 'nullable|array',
                'categories.*.id' => 'required|exists:categories,id',
                'items' => 'nullable|array',
                'items.*.id' => 'required|exists:items,id',
                'outlets' => 'nullable|array',
                'outlets.*.id' => 'required|exists:tbl_data_outlet,id_outlet',
                'need_member' => 'required|in:Yes,No',
            ]);

            // Generate code otomatis jika kosong
            if (empty($validated['code'])) {
                $validated['code'] = 'PRM-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            }

            // Handle banner upload
            if ($request->hasFile('banner')) {
                // Pastikan folder ada
                if (!Storage::disk('public')->exists('promo_banners')) {
                    Storage::disk('public')->makeDirectory('promo_banners');
                }
                $validated['banner'] = $request->file('banner')->store('promo_banners', 'public');
            }

            $promo = Promo::create($validated);

            if ($request->has('categories')) {
                $promo->categories()->sync(collect($request->categories)->pluck('id'));
            }
            if ($request->has('items')) {
                $promo->items()->sync(collect($request->items)->pluck('id'));
            }
            if ($request->has('outlets')) {
                $promo->outlets()->sync(collect($request->outlets)->pluck('id'));
            }

            return redirect()->route('promos.index')
                ->with('success', 'Promo berhasil ditambahkan!');
        } catch (\Throwable $e) {
            \Log::error('Gagal menyimpan promo: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return redirect()->back()->with('error', 'Terjadi kesalahan saat menyimpan promo.');
        }
    }

    public function show($id)
    {
        $promo = Promo::with(['categories', 'items', 'outlets', 'regions', 'itemPrices'])->findOrFail($id);
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
            ]
        ]);
    }

    public function edit($id)
    {
        $promo = Promo::with(['categories', 'items', 'outlets', 'regions'])->findOrFail($id);
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
            'promo' => [
                // ... field lain ...
                // pastikan mapping ke array of objects
                'regions' => $promo->regions->map(fn($r) => ['id' => $r->id, 'name' => $r->name])->values(),
                'outlets' => $promo->outlets->map(fn($o) => ['id' => $o->id, 'name' => $o->name])->values(),
                // field lain bisa pakai $promo->toArray() atau manual
            ] + $promo->toArray(),
            'categories' => $categories,
            'items' => $items,
            'outlets' => $outlets,
            'regions' => $regions,
            'isEdit' => true
        ]);
    }
    public function update(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:promos,code,' . $id,
            'type' => 'required|in:percent,nominal,bundle,bogo,harga_coret',
            'value' => 'required|numeric|min:0',
            'min_transaction' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
            'terms' => 'nullable|string',
            'banner' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'categories' => 'nullable|array',
            'categories.*.id' => 'required|exists:categories,id',
            'items' => 'nullable|array',
            'items.*.id' => 'required|exists:items,id',
            'outlets' => 'nullable|array',
            'outlets.*.id' => 'required|exists:tbl_data_outlet,id_outlet',
            'need_member' => 'required|in:Yes,No',
        ]);

        // Handle banner upload
        if ($request->hasFile('banner')) {
            // Hapus banner lama jika ada
            if ($promo->banner) {
                Storage::disk('public')->delete($promo->banner);
            }
            // Pastikan folder ada
            if (!Storage::disk('public')->exists('promo_banners')) {
                Storage::disk('public')->makeDirectory('promo_banners');
            }
            $validated['banner'] = $request->file('banner')->store('promo_banners', 'public');
        } else {
            unset($validated['banner']); // Jaga agar banner lama tidak terhapus
        }

        $promo->update($validated);

        if ($request->has('categories')) {
            $promo->categories()->sync(collect($request->categories)->pluck('id'));
        }
        if ($request->has('items')) {
            $promo->items()->sync(collect($request->items)->pluck('id'));
        }
        if ($request->has('outlets')) {
            $promo->outlets()->sync(collect($request->outlets)->pluck('id'));
        }

        return redirect()->route('promos.index')
            ->with('success', 'Promo berhasil diupdate!');
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