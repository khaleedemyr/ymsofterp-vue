<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function searchOutlets(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $items = DB::table('tbl_data_outlet')
            ->select('id_outlet', 'nama_outlet')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nama_outlet', 'like', "%{$q}%");
            })
            ->orderBy('nama_outlet')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'value' => (int) $row->id_outlet,
                'label' => (string) $row->nama_outlet,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function searchMenus(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $items = DB::table('items as i')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->select('i.id', 'i.name', 'i.type')
            ->where('i.status', 'active')
            ->where('c.is_asset', '0')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('i.name', 'like', "%{$q}%");
            })
            ->orderBy('i.name')
            ->limit(120)
            ->get()
            ->map(fn ($row) => [
                'value' => (int) $row->id,
                'label' => '[MENU] '.(string) $row->name,
                'menu_id' => (int) $row->id,
                'menu_name' => (string) $row->name,
                'menu_type' => (string) ($row->type ?? ''),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'items' => $items,
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

    public function checkMenuAvailability(Request $request)
    {
        $outletId = (int) $request->query('outlet_id', 0);
        $menuId = (int) $request->query('menu_id', 0);
        $stockCutFilter = (string) $request->query('stock_cut_filter', 'only_yes'); // only_yes|only_no|all
        if ($outletId <= 0 || $menuId <= 0) {
            return response()->json(['success' => false, 'message' => 'outlet_id dan menu_id wajib diisi'], 422);
        }
        if (! in_array($stockCutFilter, ['only_yes', 'only_no', 'all'], true)) {
            $stockCutFilter = 'only_yes';
        }

        $menu = DB::table('items')->where('id', $menuId)->select('id', 'name', 'type')->first();
        if (! $menu) {
            return response()->json(['success' => false, 'message' => 'Menu tidak ditemukan'], 404);
        }
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->select('id_outlet', 'nama_outlet')->first();
        if (! $outlet) {
            return response()->json(['success' => false, 'message' => 'Outlet tidak ditemukan'], 404);
        }

        $warehouseId = $this->resolveWarehouseForItemType((string) ($menu->type ?? ''), $outletId);
        if (! $warehouseId) {
            return response()->json([
                'success' => true,
                'outlet' => ['id' => (int) $outlet->id_outlet, 'name' => (string) $outlet->nama_outlet],
                'menu' => ['id' => (int) $menu->id, 'name' => (string) $menu->name, 'type' => (string) ($menu->type ?? '')],
                'stock_cut_filter' => $stockCutFilter,
                'warehouse' => null,
                'can_sell' => 0,
                'materials' => [],
                'message' => 'Warehouse untuk tipe menu tidak ditemukan (food -> Kitchen, beverages -> Bar).',
            ]);
        }

        $bomLines = DB::table('item_bom as b')
            ->join('items as material', 'material.id', '=', 'b.material_item_id')
            ->leftJoin('units as u', 'u.id', '=', 'b.unit_id')
            ->leftJoin('items as material_master', 'material_master.id', '=', 'b.material_item_id')
            ->leftJoin('units as us', 'us.id', '=', 'material_master.small_unit_id')
            ->select(
                'b.material_item_id',
                'material.name as material_name',
                'b.qty',
                'b.stock_cut',
                'u.name as bom_unit_name',
                'us.name as small_unit_name'
            )
            ->where('b.item_id', $menuId)
            ->orderBy('material.name')
            ->get()
            ->filter(function ($row) use ($stockCutFilter) {
                if ($stockCutFilter === 'all') {
                    return true;
                }
                $isIncluded = $this->isBomLineCountedForStock($row->stock_cut ?? null);
                return $stockCutFilter === 'only_yes' ? $isIncluded : ! $isIncluded;
            })
            ->values();

        if ($bomLines->isEmpty()) {
            return response()->json([
                'success' => true,
                'outlet' => ['id' => (int) $outlet->id_outlet, 'name' => (string) $outlet->nama_outlet],
                'menu' => ['id' => (int) $menu->id, 'name' => (string) $menu->name, 'type' => (string) ($menu->type ?? '')],
                'stock_cut_filter' => $stockCutFilter,
                'warehouse' => $this->warehouseLabel($warehouseId),
                'can_sell' => null,
                'materials' => [],
                'message' => 'Tidak ada baris BOM sesuai filter stock cut.',
            ]);
        }

        $materialIds = $bomLines->pluck('material_item_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $stockMap = $this->buildRemainingStockMap($outletId, $warehouseId, $materialIds);

        $materials = [];
        $minPortions = null;
        foreach ($bomLines as $row) {
            $materialId = (int) $row->material_item_id;
            $need = (float) ($row->qty ?? 0);
            if ($need <= 0) {
                continue;
            }
            $ready = (float) ($stockMap[$materialId] ?? 0);
            $possible = (int) floor($ready / $need);
            $minPortions = $minPortions === null ? $possible : min($minPortions, $possible);

            $materials[] = [
                'material_id' => $materialId,
                'material_name' => (string) ($row->material_name ?? '-'),
                'stock_cut' => $row->stock_cut,
                'need_per_portion' => $need,
                'ready_stock' => $ready,
                'possible_portions_by_material' => max(0, $possible),
                'unit_name' => (string) ($row->small_unit_name ?? $row->bom_unit_name ?? '-'),
            ];
        }

        return response()->json([
            'success' => true,
            'outlet' => ['id' => (int) $outlet->id_outlet, 'name' => (string) $outlet->nama_outlet],
            'menu' => ['id' => (int) $menu->id, 'name' => (string) $menu->name, 'type' => (string) ($menu->type ?? '')],
            'stock_cut_filter' => $stockCutFilter,
            'warehouse' => $this->warehouseLabel($warehouseId),
            'can_sell' => max(0, (int) ($minPortions ?? 0)),
            'materials' => $materials,
        ]);
    }

    private function resolveWarehouseForItemType(string $itemType, int $outletId): ?int
    {
        $statusOk = function ($q) {
            $q->where('status', 'active')->orWhere('status', 'A')->orWhereNull('status');
        };
        $kitchenWh = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where($statusOk)
            ->whereRaw('LOWER(TRIM(name)) = ?', ['kitchen'])
            ->orderBy('id')
            ->first();
        $barWh = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where($statusOk)
            ->whereRaw('LOWER(TRIM(name)) = ?', ['bar'])
            ->orderBy('id')
            ->first();

        $t = trim($itemType);
        if (in_array($t, ['Food Asian', 'Food Western', 'Food'], true)) {
            return $kitchenWh ? (int) $kitchenWh->id : null;
        }
        if ($t === 'Beverages') {
            return $barWh ? (int) $barWh->id : null;
        }

        return $kitchenWh ? (int) $kitchenWh->id : null;
    }

    private function warehouseLabel(?int $warehouseId): ?array
    {
        if (! $warehouseId) {
            return null;
        }
        $row = DB::table('warehouse_outlets')->where('id', $warehouseId)->select('id', 'name')->first();
        if (! $row) {
            return null;
        }
        return ['id' => (int) $row->id, 'name' => (string) $row->name];
    }

    private function isBomLineCountedForStock($stockCut): bool
    {
        if ($stockCut === null || $stockCut === false || $stockCut === 0 || $stockCut === '0' || $stockCut === 'false' || $stockCut === '') {
            return true;
        }
        if ($stockCut === true || $stockCut === 1 || $stockCut === '1' || $stockCut === 'true' || $stockCut === 'yes') {
            return false;
        }
        return false;
    }

    /**
     * @param array<int> $materialIds
     * @return array<int, float> item_id => qty_small
     */
    private function buildRemainingStockMap(int $outletId, int $warehouseId, array $materialIds): array
    {
        if ($materialIds === []) {
            return [];
        }
        $q = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as ii', 'ii.id', '=', 's.inventory_item_id')
            ->where('s.warehouse_outlet_id', $warehouseId)
            ->whereIn('ii.item_id', $materialIds);
        if (Schema::hasColumn('outlet_food_inventory_stocks', 'id_outlet')) {
            $q->where('s.id_outlet', $outletId);
        }
        $rows = $q->select('ii.item_id', 's.qty_small')->get();
        $map = [];
        foreach ($rows as $row) {
            $mid = (int) $row->item_id;
            $map[$mid] = ($map[$mid] ?? 0) + (float) ($row->qty_small ?? 0);
        }

        // Konsisten dengan POS: kurangi hold cart terbuka antar terminal jika tabelnya ada.
        if (Schema::hasTable('pos_open_cart_material_hold')) {
            $holds = DB::table('pos_open_cart_material_hold')
                ->where('id_outlet', $outletId)
                ->where('warehouse_outlet_id', $warehouseId)
                ->whereIn('material_item_id', $materialIds)
                ->select('material_item_id', DB::raw('SUM(qty_small) as qty_small'))
                ->groupBy('material_item_id')
                ->get();
            foreach ($holds as $h) {
                $mid = (int) $h->material_item_id;
                $map[$mid] = ($map[$mid] ?? 0) - (float) ($h->qty_small ?? 0);
            }
        }

        return $map;
    }
}

