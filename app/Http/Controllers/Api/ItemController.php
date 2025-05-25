<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Unit;

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
} 