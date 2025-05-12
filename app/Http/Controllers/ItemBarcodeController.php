<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemBarcode;
use Illuminate\Http\Request;

class ItemBarcodeController extends Controller
{
    public function index(Item $item)
    {
        return response()->json([
            'barcodes' => $item->barcodes()->get()
        ]);
    }

    public function store(Request $request, Item $item)
    {
        $request->validate([
            'barcode' => 'required|string|unique:item_barcodes,barcode'
        ]);

        $barcode = $item->barcodes()->create([
            'barcode' => $request->barcode
        ]);

        return response()->json(['barcode' => $barcode]);
    }

    public function destroy(Item $item, ItemBarcode $barcode)
    {
        if ($barcode->item_id !== $item->id) {
            abort(403);
        }

        $barcode->delete();

        return back()->with('success', 'Barcode deleted successfully');
    }
} 