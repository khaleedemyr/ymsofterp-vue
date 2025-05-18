<?php

namespace App\Http\Controllers;

use App\Models\Repack;
use App\Models\Item;
use App\Models\ItemBarcode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RepackController extends Controller
{
    public function index(Request $request)
    {
        $query = Repack::with(['itemAsal', 'itemHasil', 'creator']);
        if ($request->search) {
            $query->where('repack_number', 'like', '%' . $request->search . '%');
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        $repacks = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        return Inertia::render('Repack/Index', [
            'user' => Auth::user(),
            'repacks' => $repacks,
        ]);
    }

    public function create()
    {
        $items = \App\Models\Item::with(['unit', 'mediumUnit', 'largeUnit'])->get();
        $units = \App\Models\Unit::all();
        \Log::info('DEBUG ITEMS', $items->toArray());
        return \Inertia\Inertia::render('Repack/Form', [
            'items' => $items,
            'units' => $units,
            'user' => \Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_asal_id' => 'required|exists:items,id',
            'unit_asal_id' => 'required|exists:units,id',
            'qty_asal' => 'required|numeric|min:0',
            'item_hasil_id' => 'required|exists:items,id',
            'unit_hasil_id' => 'required|exists:units,id',
            'qty_hasil' => 'required|numeric|min:0',
        ]);

        $repack = Repack::create([
            'repack_number' => 'RP-' . date('Ymd') . '-' . Str::random(4),
            'item_asal_id' => $request->item_asal_id,
            'unit_asal_id' => $request->unit_asal_id,
            'qty_asal' => $request->qty_asal,
            'item_hasil_id' => $request->item_hasil_id,
            'unit_hasil_id' => $request->unit_hasil_id,
            'qty_hasil' => $request->qty_hasil,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        // Generate barcodes
        $itemHasil = Item::find($request->item_hasil_id);
        $barcodes = [];
        
        for ($i = 0; $i < $request->qty_hasil; $i++) {
            $barcode = 'BRC-' . $itemHasil->sku . '-' . Str::random(6);
            $barcodes[] = [
                'item_id' => $request->item_hasil_id,
                'barcode' => $barcode,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ItemBarcode::insert($barcodes);

        return response()->json([
            'message' => 'Repack berhasil disimpan',
            'repack' => $repack,
            'barcodes' => $barcodes
        ]);
    }

    public function printBarcodes($repackId)
    {
        $repack = Repack::with(['itemHasil'])->findOrFail($repackId);
        $barcodes = ItemBarcode::where('item_id', $repack->item_hasil_id)
            ->where('created_at', '>=', $repack->created_at)
            ->get();

        return Inertia::render('Repack/PrintBarcodes', [
            'repack' => $repack,
            'barcodes' => $barcodes
        ]);
    }

    // CRUD dasar (store, update, destroy, show) bisa ditambahkan sesuai kebutuhan
} 