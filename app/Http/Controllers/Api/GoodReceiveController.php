<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FoodGoodReceive;
use App\Models\FoodGoodReceiveItem;

class GoodReceiveController extends Controller
{
    // Autocomplete good receive
    public function autocomplete(Request $request)
    {
        $q = $request->input('q');
        $warehouseId = $request->input('warehouse_id');
        $results = FoodGoodReceive::with('supplier')
            ->when($warehouseId, function($query) use ($warehouseId) {
                $query->whereHas('butcherPurchaseOrder.butcherPoItems.butcherPrFoodItem.butcherPrFood', function($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                });
            })
            ->when($q, function($query) use ($q) {
                $query->where('gr_number', 'like', "%$q%")
                    ->orWhere('receive_date', 'like', "%$q%")
                    ->orWhereHas('supplier', function($q2) use ($q) {
                        $q2->where('name', 'like', "%$q%");
                    });
            })
            ->orderByDesc('receive_date')
            ->limit(15)
            ->get()
            ->map(function($gr) {
                return [
                    'id' => $gr->id,
                    'gr_number' => $gr->gr_number,
                    'receive_date' => $gr->receive_date,
                    'supplier_name' => $gr->supplier ? $gr->supplier->name : null,
                ];
            });
        return response()->json($results);
    }

    // Get items by good receive
    public function items($id)
    {
        $items = FoodGoodReceiveItem::where('good_receive_id', $id)
            ->with(['item' => function($q) {
                $q->select('id', 'name', 'sku', 'status');
            }, 'unit'])
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->item_id,
                    'name' => $item->item ? $item->item->name : '',
                    'sku' => $item->item ? $item->item->sku : '',
                    'qty' => $item->qty_received,
                    'unit' => $item->unit ? $item->unit->name : '',
                ];
            });
        return response()->json($items);
    }
} 