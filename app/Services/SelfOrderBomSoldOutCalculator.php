<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Meniru logika ymsoftpos/src/utils/bomStockEngine.js untuk self-order web:
 * sold out dari BOM menu + modifier_bom_json vs snapshot stok outlet (Kitchen / Bar).
 */
class SelfOrderBomSoldOutCalculator
{
    /**
     * @return array{item_ids: array<int>, modifier_option_ids: array<int>}
     */
    public function computeAutomatedSoldOut(int $outletId, array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($itemIds === [] || !$this->schemasReady()) {
            return ['item_ids' => [], 'modifier_option_ids' => []];
        }

        $meta = $this->resolveOutletWarehouseMeta($outletId);
        $kitchenWhId = $meta['warehouse_kitchen_id'];
        $barWhId = $meta['warehouse_bar_id'];

        $remainingMap = $this->buildRemainingStockMap($outletId, $kitchenWhId, $barWhId);
        $bomByItemId = $this->loadBomGroupedByItem($itemIds);
        $typesByItemId = $this->loadItemTypes($itemIds);

        $soldOutItems = [];
        foreach ($itemIds as $itemId) {
            $type = $typesByItemId[$itemId] ?? $typesByItemId[(string) $itemId] ?? null;
            $max = $this->maxPortionsForMenuItem($itemId, $bomByItemId, $type, $kitchenWhId, $barWhId, $remainingMap);
            if ($max === 0) {
                $soldOutItems[] = $itemId;
            }
        }

        $modifierIdsByItem = $this->loadModifierOptionIdsByItem($itemIds);
        $allOptIds = [];
        foreach ($modifierIdsByItem as $opts) {
            foreach ($opts as $oid) {
                $allOptIds[] = $oid;
            }
        }
        $allOptIds = array_values(array_unique(array_filter($allOptIds)));
        $bomJsonByOptionId = $this->loadModifierBomJsonForOptions($allOptIds);

        $soldOutMods = [];
        foreach ($itemIds as $itemId) {
            $type = $typesByItemId[$itemId] ?? $typesByItemId[(string) $itemId] ?? null;
            $wh = $this->resolveWarehouseForItemType($type, $kitchenWhId, $barWhId);
            foreach ($modifierIdsByItem[$itemId] ?? [] as $optId) {
                $raw = $bomJsonByOptionId[$optId] ?? null;
                $parts = $this->parseModifierBomJson($raw);
                $max = $this->maxPortionsForModifierParts($parts, $wh, $remainingMap);
                if ($max === 0) {
                    $soldOutMods[] = $optId;
                }
            }
        }

        return [
            'item_ids' => array_values(array_unique($soldOutItems)),
            'modifier_option_ids' => array_values(array_unique($soldOutMods)),
        ];
    }

    private function schemasReady(): bool
    {
        return Schema::hasTable('item_bom')
            && Schema::hasTable('items')
            && Schema::hasColumn('items', 'type')
            && Schema::hasTable('warehouse_outlets')
            && Schema::hasTable('outlet_food_inventory_stocks')
            && Schema::hasTable('outlet_food_inventory_items');
    }

    /**
     * @return array{warehouse_kitchen_id: ?int, warehouse_bar_id: ?int}
     */
    private function resolveOutletWarehouseMeta(int $outletId): array
    {
        $kitchenWh = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->where('name', 'Kitchen')
            ->first();
        $barWh = DB::table('warehouse_outlets')
            ->where('outlet_id', $outletId)
            ->where('status', 'active')
            ->where('name', 'Bar')
            ->first();

        return [
            'warehouse_kitchen_id' => $kitchenWh ? (int) $kitchenWh->id : null,
            'warehouse_bar_id' => $barWh ? (int) $barWh->id : null,
        ];
    }

    /**
     * Snapshot stok per (material_item_id, warehouse_outlet_id), dikurangi peer hold POS jika tabel ada.
     *
     * @return array<string, float>
     */
    private function buildRemainingStockMap(int $outletId, ?int $kitchenWhId, ?int $barWhId): array
    {
        $whIds = array_values(array_filter([$kitchenWhId, $barWhId]));
        if ($whIds === []) {
            return [];
        }

        $q = DB::table('outlet_food_inventory_stocks as s')
            ->join('outlet_food_inventory_items as ii', 'ii.id', '=', 's.inventory_item_id')
            ->whereIn('s.warehouse_outlet_id', $whIds);

        if (Schema::hasColumn('outlet_food_inventory_stocks', 'id_outlet')) {
            $q->where('s.id_outlet', $outletId);
        }

        $raw = $q->select(
            'ii.item_id as material_item_id',
            's.warehouse_outlet_id',
            's.qty_small'
        )->get();

        $map = [];
        foreach ($raw as $row) {
            $mid = (int) $row->material_item_id;
            $wid = (int) $row->warehouse_outlet_id;
            $k = $this->matWhKey($mid, $wid);
            $map[$k] = ($map[$k] ?? 0) + (float) ($row->qty_small ?? 0);
        }

        if (Schema::hasTable('pos_open_cart_material_hold')) {
            $holds = DB::table('pos_open_cart_material_hold')
                ->where('id_outlet', $outletId)
                ->select(
                    'material_item_id',
                    'warehouse_outlet_id',
                    DB::raw('SUM(qty_small) as qty_small')
                )
                ->groupBy('material_item_id', 'warehouse_outlet_id')
                ->get();
            foreach ($holds as $h) {
                $k = $this->matWhKey((int) $h->material_item_id, (int) $h->warehouse_outlet_id);
                $map[$k] = ($map[$k] ?? 0) - (float) ($h->qty_small ?? 0);
            }
        }

        return $map;
    }

    /**
     * @return array<int, array<int, array{material_item_id: int, qty: float, unit_id: int}>>
     */
    private function loadBomGroupedByItem(array $itemIds): array
    {
        $q = DB::table('item_bom as ib')
            ->join('items as parent', 'parent.id', '=', 'ib.item_id')
            ->where('parent.status', 'active')
            ->whereIn('ib.item_id', $itemIds)
            ->select(
                'ib.item_id',
                'ib.material_item_id',
                'ib.qty',
                'ib.unit_id'
            );

        $rows = $q->get();
        $grouped = [];
        foreach ($rows as $row) {
            $iid = (int) $row->item_id;
            if (!isset($grouped[$iid])) {
                $grouped[$iid] = [];
            }
            $grouped[$iid][] = [
                'material_item_id' => (int) $row->material_item_id,
                'qty' => (float) ($row->qty ?? 0),
                'unit_id' => (int) ($row->unit_id ?? 0),
            ];
        }

        return $grouped;
    }

    /**
     * @return array<int, string|null>
     */
    private function loadItemTypes(array $itemIds): array
    {
        return DB::table('items')
            ->whereIn('id', $itemIds)
            ->pluck('type', 'id')
            ->map(fn ($t) => $t !== null ? (string) $t : null)
            ->all();
    }

    /**
     * @return array<int, int[]>
     */
    private function loadModifierOptionIdsByItem(array $itemIds): array
    {
        $byItem = [];
        foreach ($itemIds as $id) {
            $byItem[(int) $id] = [];
        }

        if (!Schema::hasTable('modifier_options')) {
            return $byItem;
        }

        $rows = collect();

        if (
            Schema::hasTable('item_modifiers')
            && Schema::hasColumn('item_modifiers', 'item_id')
            && Schema::hasColumn('item_modifiers', 'modifier_option_id')
        ) {
            $rows = DB::table('item_modifiers as im')
                ->whereIn('im.item_id', $itemIds)
                ->select('im.item_id', 'im.modifier_option_id')
                ->get();
        } elseif (
            Schema::hasTable('item_modifier_options')
            && Schema::hasColumn('item_modifier_options', 'item_id')
            && Schema::hasColumn('item_modifier_options', 'modifier_option_id')
        ) {
            $rows = DB::table('item_modifier_options as im')
                ->whereIn('im.item_id', $itemIds)
                ->select('im.item_id', 'im.modifier_option_id')
                ->get();
        }

        foreach ($rows as $row) {
            $iid = (int) $row->item_id;
            $oid = (int) $row->modifier_option_id;
            if ($oid <= 0) {
                continue;
            }
            if (!isset($byItem[$iid])) {
                $byItem[$iid] = [];
            }
            $byItem[$iid][$oid] = $oid;
        }

        foreach ($byItem as $iid => $set) {
            $byItem[$iid] = array_values($set);
        }

        return $byItem;
    }

    /**
     * @param  array<int>  $optionIds
     * @return array<int, ?string>
     */
    private function loadModifierBomJsonForOptions(array $optionIds): array
    {
        if ($optionIds === [] || !Schema::hasColumn('modifier_options', 'modifier_bom_json')) {
            return [];
        }

        return DB::table('modifier_options')
            ->whereIn('id', $optionIds)
            ->pluck('modifier_bom_json', 'id')
            ->all();
    }

    private function matWhKey(int $materialId, int $warehouseId): string
    {
        return $materialId . '_' . $warehouseId;
    }

    private function resolveWarehouseForItemType(?string $type, ?int $kitchenId, ?int $barId): ?int
    {
        $t = trim((string) $type);
        if (in_array($t, ['Food Asian', 'Food Western', 'Food'], true)) {
            return $kitchenId !== null ? (int) $kitchenId : null;
        }
        if ($t === 'Beverages') {
            return $barId !== null ? (int) $barId : null;
        }

        return null;
    }

    /**
     * @param  array<int, array{material_item_id: int, qty: float, unit_id: int}>  $bomLines
     * @param  array<string, float>  $remainingMap
     */
    private function maxPortionsForMenuItem(
        int $menuItemId,
        array $bomByItemId,
        ?string $itemType,
        ?int $kitchenWhId,
        ?int $barWhId,
        array $remainingMap
    ): ?int {
        $boms = $bomByItemId[$menuItemId] ?? [];
        if ($boms === []) {
            return null;
        }
        $wh = $this->resolveWarehouseForItemType($itemType, $kitchenWhId, $barWhId);
        if ($wh === null) {
            return null;
        }

        $minPortions = INF;
        foreach ($boms as $b) {
            $need = (float) ($b['qty'] ?? 0);
            if ($need <= 0) {
                continue;
            }
            $k = $this->matWhKey((int) $b['material_item_id'], $wh);
            $left = array_key_exists($k, $remainingMap) ? $remainingMap[$k] : null;
            if ($left === null) {
                $minPortions = 0;
                break;
            }
            $portions = (int) floor($left / $need);
            if ($portions < $minPortions) {
                $minPortions = $portions;
            }
        }
        if ($minPortions === INF) {
            return null;
        }

        return max(0, (int) $minPortions);
    }

    /**
     * @param  array<int, array{material_item_id: int, qty: float}>  $parts
     * @param  array<string, float>  $remainingMap
     */
    private function maxPortionsForModifierParts(array $parts, ?int $warehouseId, array $remainingMap): ?int
    {
        if ($warehouseId === null || $warehouseId <= 0 || $parts === []) {
            return null;
        }
        $wh = (int) $warehouseId;

        $byMat = [];
        foreach ($parts as $p) {
            $mid = (int) ($p['material_item_id'] ?? 0);
            $add = (float) ($p['qty'] ?? 0);
            if ($mid <= 0 || $add <= 0) {
                continue;
            }
            $byMat[$mid] = ($byMat[$mid] ?? 0) + $add;
        }
        if ($byMat === []) {
            return null;
        }

        $minPortions = INF;
        foreach ($byMat as $mid => $need) {
            $k = $this->matWhKey((int) $mid, $wh);
            $left = array_key_exists($k, $remainingMap) ? $remainingMap[$k] : null;
            if ($left === null) {
                $minPortions = 0;
                break;
            }
            $portions = (int) floor($left / $need);
            if ($portions < $minPortions) {
                $minPortions = $portions;
            }
        }
        if ($minPortions === INF) {
            return null;
        }

        return max(0, (int) $minPortions);
    }

    /**
     * @return array<int, array{material_item_id: int, qty: float}>
     */
    private function parseModifierBomJson($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        try {
            $arr = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($arr)) {
                return [];
            }
            $out = [];
            foreach ($arr as $x) {
                if (!is_array($x) || !isset($x['item_id'], $x['qty'])) {
                    continue;
                }
                $mid = (int) $x['item_id'];
                $qty = (float) ($x['qty'] ?? 0);
                if ($mid <= 0) {
                    continue;
                }
                $out[] = [
                    'material_item_id' => $mid,
                    'qty' => $qty,
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
