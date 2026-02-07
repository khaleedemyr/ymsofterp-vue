<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    // Autocomplete PCS item
    public function autocompletePcs(Request $request)
    {
        $q = $request->input('q');
        $items = Item::where('status', 'active')
            ->whereHas('category', function($q2) {
                $q2->where('show_pos', '0');
            })
            ->when($q, function($query) use ($q) {
                $query->where('name', 'like', "%$q%")
                    ->orWhere('sku', 'like', "%$q%") ;
            })
            ->limit(15)
            ->get();
        // Ambil semua unit yang diperlukan
        $unitIds = [];
        foreach ($items as $item) {
            if ($item->small_unit_id) $unitIds[] = $item->small_unit_id;
            if ($item->medium_unit_id) $unitIds[] = $item->medium_unit_id;
            if ($item->large_unit_id) $unitIds[] = $item->large_unit_id;
        }
        $units = Unit::whereIn('id', $unitIds)->get()->keyBy('id');
        $result = $items->map(function($item) use ($units) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'small_unit_id' => $item->small_unit_id,
                'medium_unit_id' => $item->medium_unit_id,
                'large_unit_id' => $item->large_unit_id,
                'unit_small' => $item->small_unit_id ? ($units[$item->small_unit_id]->name ?? null) : null,
                'unit_medium' => $item->medium_unit_id ? ($units[$item->medium_unit_id]->name ?? null) : null,
                'unit_large' => $item->large_unit_id ? ($units[$item->large_unit_id]->name ?? null) : null,
            ];
        });
        return response()->json($result);
    }

    public function index(Request $request)
    {
        $q = $request->input('q');
        $query = Item::query()->where('status', 'active');
        if ($q) {
            $query->where('name', 'like', "%$q%") ;
        }
        return $query->limit(15)->get(['id', 'name']);
    }

    public function bySupplier(Request $request)
    {
        $supplierId = $request->get('supplier_id');
        $outletId = $request->get('outlet_id');
        
        \Log::info('bySupplier API called', [
            'supplier_id' => $supplierId,
            'outlet_id' => $outletId
        ]);
        
        // Debug: Cek data di tabel item_supplier
        $itemSupplierCount = DB::table('item_supplier')
            ->where('supplier_id', $supplierId)
            ->count();
        \Log::info('item_supplier count', ['count' => $itemSupplierCount]);
        
        // Debug: Cek data di tabel item_supplier_outlet
        $itemSupplierOutletCount = DB::table('item_supplier_outlet')
            ->join('item_supplier', 'item_supplier_outlet.item_supplier_id', '=', 'item_supplier.id')
            ->where('item_supplier.supplier_id', $supplierId)
            ->where('item_supplier_outlet.outlet_id', $outletId)
            ->count();
        \Log::info('item_supplier_outlet count', ['count' => $itemSupplierOutletCount]);
        
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

        \Log::info('Final items result', [
            'count' => $items->count(),
            'first_item' => $items->first()
        ]);

        return response()->json(['items' => $items]);
    }

    public function detail($id)
    {
        $item = Item::with('images')->find($id);
        if (!$item) {
            return response()->json(['message' => 'Item tidak ditemukan'], 404);
        }

        $images = $item->images->map(function($img) {
            return [
                'id' => $img->id,
                'path' => $img->path,
            ];
        })->values();

        return response()->json([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'specification' => $item->specification,
                'images' => $images,
            ],
        ]);
    }
} 