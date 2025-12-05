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
        \Log::info('Fetching items for good receive', ['id' => $id]);
        
        $items = FoodGoodReceiveItem::where('good_receive_id', $id)
            ->with(['item' => function($q) {
                $q->select('id', 'name', 'sku', 'status', 'small_unit_id', 'small_conversion_qty');
            }, 'unit'])
            ->get()
            ->map(function($item) {
                \Log::info('Processing item', [
                    'food_good_receive_item_id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_exists' => $item->item ? true : false,
                    'item_name' => $item->item ? $item->item->name : null
                ]);
                
                // Only return if item exists
                if (!$item->item) {
                    return null;
                }
                
                return [
                    'id' => $item->item->id,
                    'name' => $item->item->name,
                    'sku' => $item->item->sku,
                    'qty_received' => $item->qty_received,
                    'unit' => $item->unit ? $item->unit->name : '',
                    'sisa_qty' => $item->qty_received - $item->used_qty,
                    'po_price' => $item->price,
                    'po_unit_id' => $item->unit_id,
                    'small_unit_id' => $item->item->small_unit_id,
                    'small_conversion_qty' => $item->item->small_conversion_qty
                ];
            })
            ->filter() // Remove null items
            ->values(); // Reset array keys
            
        \Log::info('Returning items', ['count' => $items->count()]);
        return response()->json($items);
    }
} 