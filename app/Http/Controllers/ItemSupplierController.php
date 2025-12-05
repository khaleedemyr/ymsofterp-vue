<?php

namespace App\Http\Controllers;

use App\Models\ItemSupplier;
use App\Models\ItemSupplierOutlet;
use App\Models\Supplier;
use App\Models\Item;
use App\Models\Unit;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ItemSupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = ItemSupplier::with(['supplier', 'item', 'unit', 'itemSupplierOutlets.outlet']);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('supplier', function($q2) use ($search) {
                    $q2->where('name', 'like', "%$search%");
                })
                ->orWhereHas('item', function($q2) use ($search) {
                    $q2->where('name', 'like', "%$search%");
                })
                ->orWhereHas('itemSupplierOutlets.outlet', function($q2) use ($search) {
                    $q2->where('nama_outlet', 'like', "%$search%");
                });
            });
        }
        $data = $query->orderByDesc('id')->paginate(20)->withQueryString();
        return Inertia::render('ItemSupplier/Index', [
            'data' => $data,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'required|exists:tbl_data_outlet,id_outlet',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.price' => 'required|numeric|min:0',
        ]);
        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                $itemSupplier = ItemSupplier::create([
                    'supplier_id' => $request->supplier_id,
                    'item_id' => $item['item_id'],
                    'price' => $item['price'],
                    'unit_id' => $item['unit_id'],
                ]);
                foreach ($request->outlet_ids as $outletId) {
                    ItemSupplierOutlet::create([
                        'item_supplier_id' => $itemSupplier->id,
                        'outlet_id' => $outletId,
                    ]);
                }
            }
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan data', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'required|exists:tbl_data_outlet,id_outlet',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.price' => 'required|numeric|min:0',
        ]);
        DB::beginTransaction();
        try {
            // Hapus semua item_supplier dan relasi outlet lama untuk supplier ini
            $oldItemSuppliers = ItemSupplier::where('supplier_id', $request->supplier_id)
                ->whereIn('id', function($q) use ($request) {
                    $q->select('item_supplier_id')
                      ->from('item_supplier_outlet')
                      ->whereIn('outlet_id', $request->outlet_ids);
                })->get();
            foreach ($oldItemSuppliers as $old) {
                ItemSupplierOutlet::where('item_supplier_id', $old->id)->delete();
                $old->delete();
            }
            // Insert ulang
            foreach ($request->items as $item) {
                $itemSupplier = ItemSupplier::create([
                    'supplier_id' => $request->supplier_id,
                    'item_id' => $item['item_id'],
                    'price' => $item['price'],
                    'unit_id' => $item['unit_id'],
                ]);
                foreach ($request->outlet_ids as $outletId) {
                    ItemSupplierOutlet::create([
                        'item_supplier_id' => $itemSupplier->id,
                        'outlet_id' => $outletId,
                    ]);
                }
            }
            DB::commit();
            return response()->json(['message' => 'Data berhasil diupdate']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengupdate data', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $itemSupplier = ItemSupplier::findOrFail($id);
            ItemSupplierOutlet::where('item_supplier_id', $itemSupplier->id)->delete();
            $itemSupplier->delete();
            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus data', 'error' => $e->getMessage()], 500);
        }
    }

    public function create()
    {
        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        return Inertia::render('ItemSupplier/Form', [
            'itemSupplier' => null,
            'suppliers' => $suppliers,
            'outlets' => $outlets,
        ]);
    }

    public function edit($id)
    {
        $itemSupplier = ItemSupplier::with(['supplier', 'item', 'unit', 'itemSupplierOutlets.outlet'])
            ->findOrFail($id);
        // Untuk multiple item per supplier, cari semua itemSupplier dengan supplier dan outlet yang sama
        $related = ItemSupplier::with(['item', 'unit', 'itemSupplierOutlets.outlet'])
            ->where('supplier_id', $itemSupplier->supplier_id)
            ->whereHas('itemSupplierOutlets', function($q) use ($itemSupplier) {
                $q->whereIn('outlet_id', $itemSupplier->itemSupplierOutlets->pluck('outlet_id'));
            })->get();
        $itemSupplierData = [
            'id' => $itemSupplier->id,
            'supplier_id' => $itemSupplier->supplier_id,
            'supplier' => $itemSupplier->supplier,
            'item_supplier_outlets' => $itemSupplier->itemSupplierOutlets,
            'items' => $related->map(function($row) {
                return [
                    'item_id' => $row->item_id,
                    'item' => $row->item,
                    'unit_id' => $row->unit_id,
                    'unit' => $row->unit,
                    'price' => $row->price,
                ];
            })->values(),
        ];
        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        return Inertia::render('ItemSupplier/Form', [
            'itemSupplier' => $itemSupplierData,
            'suppliers' => $suppliers,
            'outlets' => $outlets,
        ]);
    }
} 