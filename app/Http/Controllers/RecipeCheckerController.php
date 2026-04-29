<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RecipeCheckerController extends Controller
{
    public function index()
    {
        return Inertia::render('StockCut/RecipeChecker');
    }

    public function searchMaterials(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $items = DB::table('items')
            ->join('categories', 'categories.id', '=', 'items.category_id')
            ->select('items.id', 'items.name')
            ->where('items.status', 'active')
            ->where('categories.is_asset', '0')
            ->where('categories.show_pos', '0')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('items.name', 'like', "%{$q}%");
            })
            ->orderBy('items.name')
            ->get()
            ->map(fn ($row) => [
                'value' => (int) $row->id,
                'label' => (string) $row->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function searchTargets(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $menus = DB::table('items as i')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->select('i.id', 'i.name')
            ->where('i.status', 'active')
            ->where('c.is_asset', '0')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('i.name', 'like', "%{$q}%");
            })
            ->orderBy('i.name')
            ->get()
            ->map(fn ($row) => [
                'value' => 'menu:'.(int) $row->id,
                'label' => '[MENU] '.(string) $row->name,
                'target_type' => 'menu',
                'target_id' => (int) $row->id,
            ]);

        $modifiers = DB::table('modifier_options as mo')
            ->leftJoin('modifiers as m', 'm.id', '=', 'mo.modifier_id')
            ->select('mo.id', 'mo.name', 'm.name as modifier_name')
            ->whereNotNull('mo.modifier_bom_json')
            ->where('mo.modifier_bom_json', '!=', '')
            ->where('mo.modifier_bom_json', '!=', '[]')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('mo.name', 'like', "%{$q}%")
                        ->orWhere('m.name', 'like', "%{$q}%");
                });
            })
            ->orderBy('mo.name')
            ->get()
            ->map(fn ($row) => [
                'value' => 'modifier:'.(int) $row->id,
                'label' => '[MODIFIER] '.(string) ($row->modifier_name ? $row->modifier_name.' - ' : '').(string) $row->name,
                'target_type' => 'modifier',
                'target_id' => (int) $row->id,
            ]);

        return response()->json([
            'success' => true,
            'items' => $menus->concat($modifiers)->values(),
        ]);
    }

    public function checkByMaterial(Request $request)
    {
        $materialId = (int) $request->query('material_item_id', 0);
        if ($materialId <= 0) {
            return response()->json(['success' => false, 'message' => 'material_item_id tidak valid'], 422);
        }

        $material = DB::table('items')->where('id', $materialId)->select('id', 'name')->first();
        if (! $material) {
            return response()->json(['success' => false, 'message' => 'Bahan baku tidak ditemukan'], 404);
        }

        $menuUsages = DB::table('item_bom as b')
            ->join('items as menu_item', 'menu_item.id', '=', 'b.item_id')
            ->leftJoin('units as u', 'u.id', '=', 'b.unit_id')
            ->select(
                'menu_item.id as menu_id',
                'menu_item.name as menu_name',
                'b.qty',
                'u.name as unit_name',
                'b.stock_cut'
            )
            ->where('b.material_item_id', $materialId)
            ->orderBy('menu_item.name')
            ->get();

        $modifierRows = DB::table('modifier_options as mo')
            ->leftJoin('modifiers as m', 'm.id', '=', 'mo.modifier_id')
            ->select('mo.id', 'mo.name as option_name', 'm.name as modifier_name', 'mo.modifier_bom_json')
            ->whereNotNull('mo.modifier_bom_json')
            ->where('mo.modifier_bom_json', '!=', '')
            ->where('mo.modifier_bom_json', '!=', '[]')
            ->where('mo.modifier_bom_json', 'like', '%'.$materialId.'%')
            ->orderBy('mo.name')
            ->get();

        $units = DB::table('units')->pluck('name', 'id');
        $modifierUsages = [];
        foreach ($modifierRows as $row) {
            $decoded = json_decode((string) $row->modifier_bom_json, true);
            $bomRows = is_array($decoded) ? $decoded : [];
            foreach ($bomRows as $bom) {
                $bomMaterialId = (int) ($bom['item_id'] ?? $bom['material_item_id'] ?? 0);
                if ($bomMaterialId !== $materialId) {
                    continue;
                }
                $unitId = (int) ($bom['unit_id'] ?? 0);
                $modifierUsages[] = [
                    'modifier_option_id' => (int) $row->id,
                    'modifier_name' => (string) ($row->modifier_name ?? ''),
                    'modifier_option_name' => (string) ($row->option_name ?? ''),
                    'qty' => (float) ($bom['qty'] ?? 0),
                    'unit_name' => (string) ($units[$unitId] ?? '-'),
                    'stock_cut' => (bool) ($bom['stock_cut'] ?? false),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'material' => [
                'id' => (int) $material->id,
                'name' => (string) $material->name,
            ],
            'menus' => $menuUsages,
            'modifiers' => array_values($modifierUsages),
        ]);
    }

    public function checkByTarget(Request $request)
    {
        $targetType = (string) $request->query('target_type', '');
        $targetId = (int) $request->query('target_id', 0);
        if (! in_array($targetType, ['menu', 'modifier'], true) || $targetId <= 0) {
            return response()->json(['success' => false, 'message' => 'target tidak valid'], 422);
        }

        if ($targetType === 'menu') {
            $menu = DB::table('items')->where('id', $targetId)->select('id', 'name')->first();
            if (! $menu) {
                return response()->json(['success' => false, 'message' => 'Menu tidak ditemukan'], 404);
            }
            $recipe = DB::table('item_bom as b')
                ->join('items as material', 'material.id', '=', 'b.material_item_id')
                ->leftJoin('units as u', 'u.id', '=', 'b.unit_id')
                ->select(
                    'material.id as material_id',
                    'material.name as material_name',
                    'b.qty',
                    'u.name as unit_name',
                    'b.stock_cut'
                )
                ->where('b.item_id', $targetId)
                ->orderBy('material.name')
                ->get();

            return response()->json([
                'success' => true,
                'target' => [
                    'type' => 'menu',
                    'id' => (int) $menu->id,
                    'name' => (string) $menu->name,
                ],
                'recipe' => $recipe,
            ]);
        }

        $modifier = DB::table('modifier_options as mo')
            ->leftJoin('modifiers as m', 'm.id', '=', 'mo.modifier_id')
            ->select('mo.id', 'mo.name as option_name', 'm.name as modifier_name', 'mo.modifier_bom_json')
            ->where('mo.id', $targetId)
            ->first();
        if (! $modifier) {
            return response()->json(['success' => false, 'message' => 'Modifier option tidak ditemukan'], 404);
        }

        $decoded = json_decode((string) ($modifier->modifier_bom_json ?? ''), true);
        $bomRows = is_array($decoded) ? $decoded : [];
        $materialIds = collect($bomRows)
            ->map(fn ($row) => (int) ($row['item_id'] ?? $row['material_item_id'] ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $materials = DB::table('items')->whereIn('id', $materialIds)->pluck('name', 'id');
        $units = DB::table('units')->pluck('name', 'id');

        $recipe = [];
        foreach ($bomRows as $row) {
            $materialId = (int) ($row['item_id'] ?? $row['material_item_id'] ?? 0);
            $unitId = (int) ($row['unit_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }
            $recipe[] = [
                'material_id' => $materialId,
                'material_name' => (string) ($materials[$materialId] ?? '-'),
                'qty' => (float) ($row['qty'] ?? 0),
                'unit_name' => (string) ($units[$unitId] ?? '-'),
                'stock_cut' => (bool) ($row['stock_cut'] ?? false),
            ];
        }

        return response()->json([
            'success' => true,
            'target' => [
                'type' => 'modifier',
                'id' => (int) $modifier->id,
                'name' => trim(((string) ($modifier->modifier_name ?? '')).($modifier->modifier_name ? ' - ' : '').((string) ($modifier->option_name ?? ''))),
            ],
            'recipe' => $recipe,
        ]);
    }
}

