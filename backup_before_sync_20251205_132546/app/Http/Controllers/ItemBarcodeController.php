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

        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'create',
            'module' => 'item_barcodes',
            'description' => 'Menambah barcode untuk item: ' . $item->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $barcode->toArray(),
        ]);

        return response()->json(['barcode' => $barcode]);
    }

    public function destroy(Item $item, ItemBarcode $barcode)
    {
        if ($barcode->item_id !== $item->id) {
            abort(403);
        }

        $oldData = $barcode->toArray();
        $barcode->delete();

        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => 'delete',
            'module' => 'item_barcodes',
            'description' => 'Menghapus barcode dari item: ' . $item->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);

        return back()->with('success', 'Barcode deleted successfully');
    }
} 