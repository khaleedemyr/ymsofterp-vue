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

class PromoController extends Controller
{
    public function index()
    {
        $promos = Promo::with(['categories', 'items', 'outlets'])
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
            'promos' => $promos
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
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:promos,code',
            'type' => 'required|in:percent,nominal,bundle,bogo',
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
            'outlets.*.id' => 'required|exists:outlets,id',
        ]);

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
    }

    public function show($id)
    {
        $promo = Promo::with(['categories', 'items', 'outlets', 'regions'])
            ->findOrFail($id);

        return Inertia::render('Promos/Show', [
            'promo' => [
                ...$promo->toArray(),
                'regions' => $promo->regions->map(fn($r) => ['id' => $r->id, 'name' => $r->name]),
                'outlets' => $promo->outlets->map(fn($o) => ['id' => $o->id, 'name' => $o->name]),
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
            'type' => 'required|in:percent,nominal,bundle,bogo',
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
            'outlets.*.id' => 'required|exists:outlets,id',
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
} 