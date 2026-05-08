<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\MasterItemMoq;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MasterMoqController extends Controller
{
    public function index()
    {
        return Inertia::render('MasterMoq/Index');
    }

    public function list(Request $request)
    {
        $q = MasterItemMoq::query()
            ->leftJoin('items', 'items.id', '=', 'master_item_moq.item_id')
            ->leftJoin('units', 'units.id', '=', 'master_item_moq.unit_id')
            ->select(
                'master_item_moq.id',
                'master_item_moq.item_id',
                'master_item_moq.unit_id',
                'master_item_moq.conversion_qty',
                'master_item_moq.moq_qty',
                'master_item_moq.is_active',
                'master_item_moq.notes',
                'items.sku as item_sku',
                'items.name as item_name',
                'units.name as unit_name'
            );

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $q->where(function ($sub) use ($search) {
                $sub->where('items.name', 'like', "%{$search}%")
                    ->orWhere('items.sku', 'like', "%{$search}%")
                    ->orWhere('units.name', 'like', "%{$search}%");
            });
        }

        $rows = $q->orderByDesc('master_item_moq.id')->get();

        return response()->json($rows);
    }

    public function searchItems(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $items = Item::query()
            ->select('id', 'sku', 'name', 'small_unit_id', 'medium_unit_id', 'large_unit_id', 'medium_conversion_qty', 'small_conversion_qty')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    public function itemUnits($itemId)
    {
        $item = Item::query()
            ->with(['smallUnit:id,name', 'mediumUnit:id,name', 'largeUnit:id,name'])
            ->findOrFail($itemId);

        $smallConversion = (float) ($item->small_conversion_qty ?? 1);
        $mediumConversion = (float) ($item->medium_conversion_qty ?? 1);

        $units = [];

        if ($item->smallUnit) {
            $units[] = [
                'unit_id' => $item->smallUnit->id,
                'unit_name' => $item->smallUnit->name,
                'conversion_qty' => 1,
                'conversion_note' => "1 {$item->smallUnit->name}",
            ];
        }

        if ($item->mediumUnit) {
            $units[] = [
                'unit_id' => $item->mediumUnit->id,
                'unit_name' => $item->mediumUnit->name,
                'conversion_qty' => $mediumConversion,
                'conversion_note' => "1 {$item->mediumUnit->name} = {$mediumConversion} {$item->smallUnit?->name}",
            ];
        }

        if ($item->largeUnit) {
            $units[] = [
                'unit_id' => $item->largeUnit->id,
                'unit_name' => $item->largeUnit->name,
                'conversion_qty' => ($mediumConversion * $smallConversion),
                'conversion_note' => "1 {$item->largeUnit->name} = " . ($mediumConversion * $smallConversion) . " {$item->smallUnit?->name}",
            ];
        }

        return response()->json([
            'item' => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
            ],
            'units' => $units,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:items,id',
            'unit_id' => 'required|integer|exists:units,id',
            'conversion_qty' => 'required|numeric|min:0.0001',
            'moq_qty' => 'required|numeric|min:0.0001',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $existing = MasterItemMoq::where('item_id', $validated['item_id'])
            ->where('unit_id', $validated['unit_id'])
            ->first();

        if ($existing) {
            $existing->update([
                'conversion_qty' => $validated['conversion_qty'],
                'moq_qty' => $validated['moq_qty'],
                'notes' => $validated['notes'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'message' => 'MoQ berhasil diperbarui.',
                'data' => $existing->fresh(),
            ]);
        }

        $row = MasterItemMoq::create([
            'item_id' => $validated['item_id'],
            'unit_id' => $validated['unit_id'],
            'conversion_qty' => $validated['conversion_qty'],
            'moq_qty' => $validated['moq_qty'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'MoQ berhasil disimpan.',
            'data' => $row,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'moq_qty' => 'required|numeric|min:0.0001',
            'notes' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $row = MasterItemMoq::findOrFail($id);
        $row->update($validated);

        return response()->json([
            'message' => 'MoQ berhasil diupdate.',
            'data' => $row,
        ]);
    }

    public function destroy($id)
    {
        $row = MasterItemMoq::findOrFail($id);
        $row->delete();

        return response()->json([
            'message' => 'MoQ berhasil dihapus.',
        ]);
    }
}
