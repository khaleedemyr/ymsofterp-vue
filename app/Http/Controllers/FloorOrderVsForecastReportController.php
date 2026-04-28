<?php

namespace App\Http\Controllers;

use App\Exports\FloorOrderVsForecastExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class FloorOrderVsForecastReportController extends Controller
{
    private const KITCHEN_BAR_RATIO = 0.40;

    private const SERVICE_RATIO = 0.05;

    public function index(Request $request)
    {
        return Inertia::render('Reports/FloorOrderVsForecast', $this->buildReportPayload($request));
    }

    public function export(Request $request)
    {
        $payload = $this->buildReportPayload($request);
        $month = preg_replace('/[^0-9\-]/', '', (string) ($payload['selectedMonth'] ?? now()->format('Y-m')));

        return Excel::download(
            new FloorOrderVsForecastExport($payload),
            'floor_order_vs_forecast_'.$month.'_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /**
     * GET /api/approval-app/floor-order-vs-forecast — payload sama seperti halaman web (mobile app).
     */
    public function apiIndex(Request $request)
    {
        return response()->json(array_merge([
            'success' => true,
        ], $this->buildReportPayload($request)));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $user = auth()->user();
        /** Hanya user dengan id_outlet = 1 (HO) yang boleh memilih outlet; lainnya dikunci ke outlet user. */
        $isAdminOutlet = (int) ($user->id_outlet ?? 0) === 1;

        $month = $request->input('month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthDate = $monthStart->toDateString();

        $selectedOutletId = (int) ($request->input('outlet_id') ?: 0);
        if (!$isAdminOutlet) {
            $selectedOutletId = (int) ($user->id_outlet ?? 0);
        } elseif ($selectedOutletId <= 0) {
            $selectedOutletId = 1;
        }

        $outletsQuery = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet');

        if (!$isAdminOutlet) {
            $outletsQuery->where('id_outlet', $selectedOutletId);
        }

        $outlets = $outletsQuery->get();

        $forecastByDate = [];
        $monthlyTarget = null;
        $revenueTargetHeaderExists = false;

        if ($selectedOutletId > 0) {
            $header = DB::table('outlet_revenue_target_headers')
                ->where('outlet_id', $selectedOutletId)
                ->where('target_month', $monthDate)
                ->first();

            if ($header) {
                $revenueTargetHeaderExists = true;
                $monthlyTarget = (float) $header->monthly_target;
                $details = DB::table('outlet_revenue_target_details')
                    ->where('header_id', $header->id)
                    ->orderBy('forecast_date')
                    ->get(['forecast_date', 'forecast_revenue']);

                foreach ($details as $d) {
                    $key = Carbon::parse($d->forecast_date)->toDateString();
                    $forecastByDate[$key] = (float) $d->forecast_revenue;
                }
            }
        }

        $roKitchenBar = [];
        $roService = [];
        $costMenuByDate = [];
        $costModifierByDate = [];
        $categoryCostUsageByDate = [];
        $discountByDate = [];
        $revenueByDate = [];
        $revenueBeforeDiscountByDate = [];
        $revenueWithoutTaxServiceByDate = [];
        $categoryCostByTypeByDate = [];
        $categoryCostTypes = [];
        $stockOnHandKitchenBarByDate = [];
        $stockOnHandServiceByDate = [];
        $stockOnHandTotalByDate = [];
        $beginStockKitchenBarByDate = [];
        $beginStockServiceByDate = [];
        $beginStockTotalByDate = [];
        $transferInByDate = [];
        $transferOutByDate = [];
        $adjInByDate = [];
        $adjOutByDate = [];

        if ($selectedOutletId > 0) {
            $rangeStart = $monthStart->toDateString();
            $rangeEnd = $monthEnd->toDateString();

            $warehouseBucketById = DB::table('warehouse_outlets')
                ->select('id', 'name')
                ->get()
                ->mapWithKeys(function ($w) {
                    $name = strtolower(trim((string) ($w->name ?? '')));
                    $bucket = 'other';
                    if (in_array($name, ['kitchen', 'bar'], true)) {
                        $bucket = 'kitchen_bar';
                    } elseif ($name === 'service') {
                        $bucket = 'service';
                    }

                    return [(int) $w->id => $bucket];
                })
                ->all();

            $outletQr = DB::table('tbl_data_outlet')
                ->where('id_outlet', $selectedOutletId)
                ->value('qr_code');

            $warehouseIdsByName = DB::table('warehouse_outlets')
                ->where('outlet_id', $selectedOutletId)
                ->where('status', 'active')
                ->select('id', 'name')
                ->get()
                ->mapWithKeys(function ($warehouse) {
                    return [strtolower(trim((string) ($warehouse->name ?? ''))) => (int) $warehouse->id];
                })
                ->all();

            if ($outletQr) {
                $salesRows = DB::table('orders')
                    ->where('kode_outlet', $outletQr)
                    ->whereBetween(DB::raw('DATE(created_at)'), [$rangeStart, $rangeEnd])
                    ->selectRaw('DATE(created_at) as d,
                        SUM(grand_total) as revenue,
                        COALESCE(SUM(total), 0) as total_before_discount_tax_service,
                        COALESCE(SUM(discount), 0) + COALESCE(SUM(manual_discount_amount), 0) as total_discount')
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->get();

                foreach ($salesRows as $salesRow) {
                    $dateKey = Carbon::parse($salesRow->d)->toDateString();
                    $revenue = round((float) ($salesRow->revenue ?? 0), 2);
                    $salesTotal = round((float) ($salesRow->total_before_discount_tax_service ?? 0), 2);
                    $discount = round((float) ($salesRow->total_discount ?? 0), 2);

                    $revenueByDate[$dateKey] = $revenue;
                    $discountByDate[$dateKey] = $discount;
                    $revenueBeforeDiscountByDate[$dateKey] = $salesTotal;
                    $revenueWithoutTaxServiceByDate[$dateKey] = $salesTotal;
                }

                $foodTypes = ['Food Asian', 'Food Western', 'Food'];
                $kitchenWarehouseId = (int) ($warehouseIdsByName['kitchen'] ?? 0);
                $barWarehouseId = (int) ($warehouseIdsByName['bar'] ?? 0);

                if ($kitchenWarehouseId > 0 || $barWarehouseId > 0) {
                    $menuCostRows = DB::table('order_items as oi')
                        ->join('items as menu_item', 'menu_item.id', '=', 'oi.item_id')
                        ->join('item_bom as bom', 'bom.item_id', '=', 'menu_item.id')
                        ->join('outlet_food_inventory_items as inventory_item', 'inventory_item.item_id', '=', 'bom.material_item_id')
                        ->leftJoin('outlet_food_inventory_stocks as stock', function ($join) use ($selectedOutletId, $kitchenWarehouseId, $barWarehouseId, $foodTypes) {
                            $join->on('stock.inventory_item_id', '=', 'inventory_item.id')
                                ->where('stock.id_outlet', '=', $selectedOutletId)
                                ->where(function ($query) use ($kitchenWarehouseId, $barWarehouseId, $foodTypes) {
                                    if ($kitchenWarehouseId > 0) {
                                        $query->where(function ($nested) use ($kitchenWarehouseId, $foodTypes) {
                                            $nested->whereIn('menu_item.type', $foodTypes)
                                                ->where('stock.warehouse_outlet_id', '=', $kitchenWarehouseId);
                                        });
                                    }

                                    if ($barWarehouseId > 0) {
                                        $method = $kitchenWarehouseId > 0 ? 'orWhere' : 'where';
                                        $query->{$method}(function ($nested) use ($barWarehouseId) {
                                            $nested->where('menu_item.type', 'Beverages')
                                                ->where('stock.warehouse_outlet_id', '=', $barWarehouseId);
                                        });
                                    }
                                });
                        })
                        ->where('oi.kode_outlet', $outletQr)
                        ->where('oi.stock_cut', 1)
                        ->whereBetween(DB::raw('DATE(oi.created_at)'), [$rangeStart, $rangeEnd])
                        ->selectRaw('DATE(oi.created_at) as d, SUM(oi.qty * bom.qty * COALESCE(stock.last_cost_small, 0)) as total_menu_cost')
                        ->groupBy(DB::raw('DATE(oi.created_at)'))
                        ->get();

                    foreach ($menuCostRows as $menuCostRow) {
                        $costMenuByDate[Carbon::parse($menuCostRow->d)->toDateString()] = round((float) ($menuCostRow->total_menu_cost ?? 0), 2);
                    }

                    $modifierOrderItems = DB::table('order_items as oi')
                        ->join('items as menu_item', 'menu_item.id', '=', 'oi.item_id')
                        ->where('oi.kode_outlet', $outletQr)
                        ->where('oi.stock_cut', 1)
                        ->whereBetween(DB::raw('DATE(oi.created_at)'), [$rangeStart, $rangeEnd])
                        ->whereNotNull('oi.modifiers')
                        ->where('oi.modifiers', '!=', '')
                        ->selectRaw('DATE(oi.created_at) as d, menu_item.type, oi.modifiers')
                        ->get();

                    $modifierUsageByDate = [];
                    $modifierNames = [];
                    foreach ($modifierOrderItems as $modifierOrderItem) {
                        $parsedModifiers = json_decode((string) $modifierOrderItem->modifiers, true);
                        if (!is_array($parsedModifiers)) {
                            continue;
                        }

                        $warehouseBucket = null;
                        if (in_array($modifierOrderItem->type, $foodTypes, true)) {
                            $warehouseBucket = 'kitchen';
                        } elseif ($modifierOrderItem->type === 'Beverages') {
                            $warehouseBucket = 'bar';
                        }

                        if ($warehouseBucket === null) {
                            continue;
                        }

                        $dateKey = Carbon::parse($modifierOrderItem->d)->toDateString();
                        foreach ($parsedModifiers as $group) {
                            if (!is_array($group)) {
                                continue;
                            }

                            foreach ($group as $modifierName => $modifierQty) {
                                $modifierQty = (float) $modifierQty;
                                if ($modifierQty <= 0) {
                                    continue;
                                }

                                $usageKey = $dateKey.'|'.$warehouseBucket.'|'.$modifierName;
                                $modifierUsageByDate[$usageKey] = ($modifierUsageByDate[$usageKey] ?? 0) + $modifierQty;
                                $modifierNames[$modifierName] = true;
                            }
                        }
                    }

                    if (!empty($modifierUsageByDate)) {
                        $modifierOptions = DB::table('modifier_options')
                            ->whereIn('name', array_keys($modifierNames))
                            ->whereNotNull('modifier_bom_json')
                            ->where('modifier_bom_json', '!=', '')
                            ->where('modifier_bom_json', '!=', '[]')
                            ->orderBy('id')
                            ->get(['id', 'modifier_id', 'name', 'modifier_bom_json']);

                        $modifierOptionByName = [];
                        $modifierMaterialItemIds = [];
                        foreach ($modifierOptions as $modifierOption) {
                            if ((int) ($modifierOption->modifier_id ?? 0) === 1) {
                                continue;
                            }

                            if (isset($modifierOptionByName[$modifierOption->name])) {
                                continue;
                            }

                            $modifierBom = json_decode((string) $modifierOption->modifier_bom_json, true);
                            if (!is_array($modifierBom) || empty($modifierBom)) {
                                continue;
                            }

                            $modifierOptionByName[$modifierOption->name] = $modifierBom;
                            foreach ($modifierBom as $bomItem) {
                                $materialItemId = (int) ($bomItem['item_id'] ?? 0);
                                if ($materialItemId > 0) {
                                    $modifierMaterialItemIds[$materialItemId] = true;
                                }
                            }
                        }

                        if (!empty($modifierOptionByName) && !empty($modifierMaterialItemIds)) {
                            $inventoryItemIdByMaterialId = DB::table('outlet_food_inventory_items')
                                ->whereIn('item_id', array_keys($modifierMaterialItemIds))
                                ->pluck('id', 'item_id')
                                ->map(fn ($id) => (int) $id)
                                ->all();

                            $stockCosts = DB::table('outlet_food_inventory_stocks')
                                ->where('id_outlet', $selectedOutletId)
                                ->whereIn('warehouse_outlet_id', array_values(array_filter([$kitchenWarehouseId, $barWarehouseId])))
                                ->whereIn('inventory_item_id', array_values($inventoryItemIdByMaterialId))
                                ->get(['inventory_item_id', 'warehouse_outlet_id', 'last_cost_small']);

                            $stockCostByInventoryWarehouse = [];
                            foreach ($stockCosts as $stockCost) {
                                $stockKey = (int) $stockCost->inventory_item_id.'|'.(int) $stockCost->warehouse_outlet_id;
                                $stockCostByInventoryWarehouse[$stockKey] = (float) ($stockCost->last_cost_small ?? 0);
                            }

                            foreach ($modifierUsageByDate as $usageKey => $totalModifierQty) {
                                [$dateKey, $warehouseBucket, $modifierName] = explode('|', $usageKey, 3);
                                $warehouseId = $warehouseBucket === 'kitchen' ? $kitchenWarehouseId : $barWarehouseId;
                                $modifierBom = $modifierOptionByName[$modifierName] ?? null;

                                if (!$warehouseId || !$modifierBom) {
                                    continue;
                                }

                                $modifierTotalCost = 0.0;
                                foreach ($modifierBom as $bomItem) {
                                    $materialItemId = (int) ($bomItem['item_id'] ?? 0);
                                    $bomQty = (float) ($bomItem['qty'] ?? 0);
                                    $inventoryItemId = (int) ($inventoryItemIdByMaterialId[$materialItemId] ?? 0);
                                    if ($materialItemId <= 0 || $bomQty <= 0 || $inventoryItemId <= 0) {
                                        continue;
                                    }

                                    $stockKey = $inventoryItemId.'|'.$warehouseId;
                                    $lastCostSmall = (float) ($stockCostByInventoryWarehouse[$stockKey] ?? 0);
                                    if ($lastCostSmall <= 0) {
                                        continue;
                                    }

                                    $modifierTotalCost += $totalModifierQty * $bomQty * $lastCostSmall;
                                }

                                if ($modifierTotalCost > 0) {
                                    $costModifierByDate[$dateKey] = round(($costModifierByDate[$dateKey] ?? 0) + $modifierTotalCost, 2);
                                }
                            }
                        }
                    }
                }

                $categoryCostUsageRows = DB::table('outlet_internal_use_waste_headers as h')
                    ->where('h.outlet_id', $selectedOutletId)
                    ->where('h.type', 'usage')
                    ->whereIn('h.status', ['APPROVED', 'PROCESSED'])
                    ->whereBetween('h.date', [$rangeStart, $rangeEnd])
                    ->selectRaw('DATE(h.date) as d, SUM(COALESCE(h.subtotal_mac, 0)) as total_category_cost_usage')
                    ->groupBy(DB::raw('DATE(h.date)'))
                    ->get();

                foreach ($categoryCostUsageRows as $categoryCostUsageRow) {
                    $categoryCostUsageByDate[Carbon::parse($categoryCostUsageRow->d)->toDateString()] = round((float) ($categoryCostUsageRow->total_category_cost_usage ?? 0), 2);
                }

                $categoryCostRows = DB::table('outlet_internal_use_waste_headers as h')
                    ->where('h.outlet_id', $selectedOutletId)
                    ->where('h.type', '!=', 'usage')
                    ->whereNotNull('h.type')
                    ->where('h.type', '!=', '')
                    ->whereIn('h.status', ['APPROVED', 'PROCESSED'])
                    ->whereBetween('h.date', [$rangeStart, $rangeEnd])
                    ->selectRaw('DATE(h.date) as d, LOWER(TRIM(h.type)) as type_name, SUM(COALESCE(h.subtotal_mac, 0)) as total')
                    ->groupBy(DB::raw('DATE(h.date)'), DB::raw('LOWER(TRIM(h.type))'))
                    ->get();

                foreach ($categoryCostRows as $categoryCostRow) {
                    $typeName = trim((string) ($categoryCostRow->type_name ?? ''));
                    if ($typeName === '' || $typeName === 'usage') {
                        continue;
                    }

                    $typeKey = preg_replace('/[^a-z0-9]+/', '_', strtolower($typeName));
                    $typeKey = trim((string) $typeKey, '_');
                    if ($typeKey === '') {
                        continue;
                    }

                    $dateKey = Carbon::parse($categoryCostRow->d)->toDateString();
                    if (!isset($categoryCostByTypeByDate[$typeKey])) {
                        $categoryCostByTypeByDate[$typeKey] = [];
                    }
                    $categoryCostByTypeByDate[$typeKey][$dateKey] = round((float) ($categoryCostByTypeByDate[$typeKey][$dateKey] ?? 0) + (float) ($categoryCostRow->total ?? 0), 2);
                    $categoryCostTypes[$typeKey] = ucwords(str_replace('_', ' ', $typeKey));
                }
            }

            // Stock On Hand harian (nilai harta stok): qty dari stock card (saldo_qty_small)
            // dikalikan MAC dari source yang sama dengan menu Outlet MAC Tracking,
            // yaitu outlet_food_inventory_cost_histories.new_cost (latest per tanggal).
            $baselineLatestQtyKeyByTuple = DB::table('outlet_food_inventory_cards as c')
                ->where('c.id_outlet', $selectedOutletId)
                ->whereDate('c.date', '<', $rangeStart)
                ->groupBy('c.warehouse_outlet_id', 'c.inventory_item_id')
                ->selectRaw("c.warehouse_outlet_id, c.inventory_item_id, MAX(CONCAT(DATE(c.date), ' ', LPAD(c.id, 20, '0'))) as mx");

            $baselineQtyByTuple = [];
            $baselineQtyRows = DB::table('outlet_food_inventory_cards as c')
                ->joinSub($baselineLatestQtyKeyByTuple, 'b', function ($join) {
                    $join->on('b.warehouse_outlet_id', '=', 'c.warehouse_outlet_id')
                        ->on('b.inventory_item_id', '=', 'c.inventory_item_id')
                        ->whereRaw("CONCAT(DATE(c.date), ' ', LPAD(c.id, 20, '0')) = b.mx");
                })
                ->where('c.id_outlet', $selectedOutletId)
                ->select('c.warehouse_outlet_id', 'c.inventory_item_id', 'c.saldo_qty_small')
                ->get();

            foreach ($baselineQtyRows as $row) {
                $tupleKey = (int) $row->warehouse_outlet_id.'|'.(int) $row->inventory_item_id;
                $baselineQtyByTuple[$tupleKey] = (float) ($row->saldo_qty_small ?? 0);
            }

            $monthQtyCardRows = DB::table('outlet_food_inventory_cards as c')
                ->where('c.id_outlet', $selectedOutletId)
                ->whereBetween(DB::raw('DATE(c.date)'), [$rangeStart, $rangeEnd])
                ->selectRaw('c.warehouse_outlet_id, c.inventory_item_id, DATE(c.date) as d, c.id, c.saldo_qty_small')
                ->orderBy('c.warehouse_outlet_id')
                ->orderBy('c.inventory_item_id')
                ->orderBy(DB::raw('DATE(c.date)'))
                ->orderBy('c.id')
                ->get();

            $qtyEntriesByTuple = [];
            foreach ($monthQtyCardRows as $row) {
                $tupleKey = (int) $row->warehouse_outlet_id.'|'.(int) $row->inventory_item_id;
                if (!isset($qtyEntriesByTuple[$tupleKey])) {
                    $qtyEntriesByTuple[$tupleKey] = [];
                }
                $qtyEntriesByTuple[$tupleKey][] = [
                    'date' => Carbon::parse($row->d)->toDateString(),
                    'qty_small' => (float) ($row->saldo_qty_small ?? 0),
                ];
            }

            $baselineLatestMacKeyByTuple = DB::table('outlet_food_inventory_cost_histories as h')
                ->where('h.id_outlet', $selectedOutletId)
                ->whereDate('h.date', '<', $rangeStart)
                ->groupBy('h.warehouse_outlet_id', 'h.inventory_item_id')
                ->selectRaw("h.warehouse_outlet_id, h.inventory_item_id, MAX(CONCAT(DATE(h.date), ' ', LPAD(h.id, 20, '0'))) as mx");

            $baselineMacByTuple = [];
            $baselineMacRows = DB::table('outlet_food_inventory_cost_histories as h')
                ->joinSub($baselineLatestMacKeyByTuple, 'b', function ($join) {
                    $join->on('b.warehouse_outlet_id', '=', 'h.warehouse_outlet_id')
                        ->on('b.inventory_item_id', '=', 'h.inventory_item_id')
                        ->whereRaw("CONCAT(DATE(h.date), ' ', LPAD(h.id, 20, '0')) = b.mx");
                })
                ->where('h.id_outlet', $selectedOutletId)
                ->select('h.warehouse_outlet_id', 'h.inventory_item_id', 'h.new_cost')
                ->get();

            foreach ($baselineMacRows as $row) {
                $tupleKey = (int) $row->warehouse_outlet_id.'|'.(int) $row->inventory_item_id;
                $baselineMacByTuple[$tupleKey] = (float) ($row->new_cost ?? 0);
            }

            $monthMacRows = DB::table('outlet_food_inventory_cost_histories as h')
                ->where('h.id_outlet', $selectedOutletId)
                ->whereBetween(DB::raw('DATE(h.date)'), [$rangeStart, $rangeEnd])
                ->selectRaw('h.warehouse_outlet_id, h.inventory_item_id, DATE(h.date) as d, h.id, h.new_cost')
                ->orderBy('h.warehouse_outlet_id')
                ->orderBy('h.inventory_item_id')
                ->orderBy(DB::raw('DATE(h.date)'))
                ->orderBy('h.id')
                ->get();

            $macEntriesByTuple = [];
            foreach ($monthMacRows as $row) {
                $tupleKey = (int) $row->warehouse_outlet_id.'|'.(int) $row->inventory_item_id;
                if (!isset($macEntriesByTuple[$tupleKey])) {
                    $macEntriesByTuple[$tupleKey] = [];
                }
                $macEntriesByTuple[$tupleKey][] = [
                    'date' => Carbon::parse($row->d)->toDateString(),
                    'mac' => (float) ($row->new_cost ?? 0),
                ];
            }

            $tupleKeys = array_values(array_unique(array_merge(
                array_keys($baselineQtyByTuple),
                array_keys($qtyEntriesByTuple),
                array_keys($baselineMacByTuple),
                array_keys($macEntriesByTuple)
            )));
            $tupleStates = [];
            foreach ($tupleKeys as $tupleKey) {
                $warehouseId = (int) explode('|', $tupleKey, 2)[0];
                $tupleStates[$tupleKey] = [
                    'qty_small' => (float) ($baselineQtyByTuple[$tupleKey] ?? 0),
                    'mac' => (float) ($baselineMacByTuple[$tupleKey] ?? 0),
                    'next_qty_index' => 0,
                    'next_mac_index' => 0,
                    'qty_entries' => $qtyEntriesByTuple[$tupleKey] ?? [],
                    'mac_entries' => $macEntriesByTuple[$tupleKey] ?? [],
                    'bucket' => $warehouseBucketById[$warehouseId] ?? 'other',
                ];
            }

            // Begin Stock untuk hari pertama = saldo baseline (sebelum bulan ini)
            $baselineSohKb = 0.0;
            $baselineSohSvc = 0.0;
            $baselineSohTotal = 0.0;
            foreach ($tupleStates as $state) {
                $bv = ((float) $state['qty_small']) * ((float) $state['mac']);
                $baselineSohTotal += $bv;
                if ($state['bucket'] === 'kitchen_bar') {
                    $baselineSohKb += $bv;
                } elseif ($state['bucket'] === 'service') {
                    $baselineSohSvc += $bv;
                }
            }
            $prevSohKb = $baselineSohKb;
            $prevSohSvc = $baselineSohSvc;
            $prevSohTotal = $baselineSohTotal;

            $sohCursor = $monthStart->copy();
            while ($sohCursor->lte($monthEnd)) {
                $dayKey = $sohCursor->toDateString();
                $beginStockKitchenBarByDate[$dayKey] = round($prevSohKb, 2);
                $beginStockServiceByDate[$dayKey] = round($prevSohSvc, 2);
                $beginStockTotalByDate[$dayKey] = round($prevSohTotal, 2);
                $dayKitchenBar = 0.0;
                $dayService = 0.0;
                $dayTotal = 0.0;

                foreach ($tupleStates as &$state) {
                    $qtyEntries = $state['qty_entries'];
                    $qtyEntriesCount = count($qtyEntries);
                    while ($state['next_qty_index'] < $qtyEntriesCount && $qtyEntries[$state['next_qty_index']]['date'] <= $dayKey) {
                        $state['qty_small'] = (float) $qtyEntries[$state['next_qty_index']]['qty_small'];
                        $state['next_qty_index']++;
                    }

                    $macEntries = $state['mac_entries'];
                    $macEntriesCount = count($macEntries);
                    while ($state['next_mac_index'] < $macEntriesCount && $macEntries[$state['next_mac_index']]['date'] <= $dayKey) {
                        $state['mac'] = (float) $macEntries[$state['next_mac_index']]['mac'];
                        $state['next_mac_index']++;
                    }

                    $value = round(((float) $state['qty_small']) * ((float) $state['mac']), 2);
                    $dayTotal += $value;
                    if ($state['bucket'] === 'kitchen_bar') {
                        $dayKitchenBar += $value;
                    } elseif ($state['bucket'] === 'service') {
                        $dayService += $value;
                    }
                }
                unset($state);

                $stockOnHandKitchenBarByDate[$dayKey] = round($dayKitchenBar, 2);
                $stockOnHandServiceByDate[$dayKey] = round($dayService, 2);
                $stockOnHandTotalByDate[$dayKey] = round($dayTotal, 2);
                $prevSohKb = $dayKitchenBar;
                $prevSohSvc = $dayService;
                $prevSohTotal = $dayTotal;
                $sohCursor->addDay();
            }

            $bucketExpr = 'CASE
                WHEN LOWER(TRIM(wo.name)) IN (\'kitchen\', \'bar\') THEN \'kitchen_bar\'
                WHEN LOWER(TRIM(wo.name)) = \'service\' THEN \'service\'
                ELSE \'other\'
            END';

            /*
             * Nilai RO per baris: jika sudah ada GR completed (Outlet Food Good Receive), pakai
             * SUM(received_qty) × harga baris RO — sama seperti detail GR di laporan Invoice Outlet.
             * Jika belum ada penerimaan tercatat di GR, fallback ke subtotal baris FO.
             */
            $receivedQtyByRoItem = DB::table('outlet_food_good_receive_items as gri')
                ->join('outlet_food_good_receives as gr', function ($join) {
                    $join->on('gri.outlet_food_good_receive_id', '=', 'gr.id')
                        ->whereNull('gr.deleted_at')
                        ->where('gr.status', 'completed');
                })
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->join('food_floor_orders as ffo_r', 'do.floor_order_id', '=', 'ffo_r.id')
                ->where('ffo_r.id_outlet', $selectedOutletId)
                ->whereNotNull('ffo_r.arrival_date')
                ->whereBetween(DB::raw('DATE(ffo_r.arrival_date)'), [$rangeStart, $rangeEnd])
                ->whereNotIn('ffo_r.status', ['draft', 'rejected'])
                ->groupBy('do.floor_order_id', 'gri.item_id')
                ->select(
                    'do.floor_order_id as floor_order_id',
                    'gri.item_id as item_id',
                    DB::raw('SUM(gri.received_qty) as qty_received')
                );

            $lineValueSql = '(CASE
                WHEN recv.qty_received IS NOT NULL AND recv.qty_received > 0
                THEN recv.qty_received * COALESCE(ffoi.price, 0)
                ELSE COALESCE(ffoi.subtotal, 0)
            END)';

            $aggregates = DB::table('food_floor_orders as ffo')
                ->join('warehouse_outlets as wo', 'wo.id', '=', 'ffo.warehouse_outlet_id')
                ->join('food_floor_order_items as ffoi', 'ffoi.floor_order_id', '=', 'ffo.id')
                ->leftJoinSub($receivedQtyByRoItem, 'recv', function ($join) {
                    $join->on('recv.floor_order_id', '=', 'ffo.id')
                        ->on('recv.item_id', '=', 'ffoi.item_id');
                })
                ->where('ffo.id_outlet', $selectedOutletId)
                ->whereNotNull('ffo.arrival_date')
                ->whereBetween(DB::raw('DATE(ffo.arrival_date)'), [$rangeStart, $rangeEnd])
                ->whereNotIn('ffo.status', ['draft', 'rejected'])
                ->selectRaw('DATE(ffo.arrival_date) as d, '.$bucketExpr.' as bucket, SUM('.$lineValueSql.') as total')
                ->groupBy(DB::raw('DATE(ffo.arrival_date)'), DB::raw($bucketExpr))
                ->get();

            foreach ($aggregates as $row) {
                $dateKey = Carbon::parse($row->d)->toDateString();
                $total = round((float) $row->total, 2);
                if ($row->bucket === 'kitchen_bar') {
                    $roKitchenBar[$dateKey] = ($roKitchenBar[$dateKey] ?? 0) + $total;
                } elseif ($row->bucket === 'service') {
                    $roService[$dateKey] = ($roService[$dateKey] ?? 0) + $total;
                }
            }

            // Retail Food — pembelian langsung ke supplier (bukan melalui RO/FO), ikut dihitung sebagai Purchase
            $retailFoodRows = DB::table('retail_food as rf')
                ->join('warehouse_outlets as wo', 'wo.id', '=', 'rf.warehouse_outlet_id')
                ->where('rf.outlet_id', $selectedOutletId)
                ->where('rf.status', 'approved')
                ->whereNull('rf.deleted_at')
                ->whereBetween(DB::raw('DATE(rf.transaction_date)'), [$rangeStart, $rangeEnd])
                ->selectRaw('DATE(rf.transaction_date) as d, wo.id as warehouse_outlet_id, SUM(rf.total_amount) as total')
                ->groupBy(DB::raw('DATE(rf.transaction_date)'), 'wo.id')
                ->get();

            foreach ($retailFoodRows as $rfRow) {
                $dateKey = Carbon::parse($rfRow->d)->toDateString();
                $bucket = $warehouseBucketById[(int) $rfRow->warehouse_outlet_id] ?? 'other';
                $total = round((float) $rfRow->total, 2);
                if ($bucket === 'kitchen_bar') {
                    $roKitchenBar[$dateKey] = ($roKitchenBar[$dateKey] ?? 0) + $total;
                } elseif ($bucket === 'service') {
                    $roService[$dateKey] = ($roService[$dateKey] ?? 0) + $total;
                }
            }

            // Outlet Transfer — nilai keluar (OUT) dan masuk (IN) dari kartu stok outlet transfer
            $outletTransferCards = DB::table('outlet_food_inventory_cards')
                ->where('id_outlet', $selectedOutletId)
                ->where('reference_type', 'outlet_transfer')
                ->whereBetween(DB::raw('DATE(date)'), [$rangeStart, $rangeEnd])
                ->selectRaw('DATE(date) as d, SUM(COALESCE(value_in, 0)) as total_in, SUM(COALESCE(value_out, 0)) as total_out')
                ->groupBy(DB::raw('DATE(date)'))
                ->get();

            foreach ($outletTransferCards as $otRow) {
                $dateKey = Carbon::parse($otRow->d)->toDateString();
                $transferInByDate[$dateKey] = round((float) ($otRow->total_in ?? 0), 2);
                $transferOutByDate[$dateKey] = round((float) ($otRow->total_out ?? 0), 2);
            }

            // Stock Adjustment — nilai masuk (IN) dan keluar (OUT) dari kartu stok outlet stock adjustment
            $outletAdjCards = DB::table('outlet_food_inventory_cards')
                ->where('id_outlet', $selectedOutletId)
                ->where('reference_type', 'outlet_stock_adjustment')
                ->whereBetween(DB::raw('DATE(date)'), [$rangeStart, $rangeEnd])
                ->selectRaw('DATE(date) as d, SUM(COALESCE(value_in, 0)) as total_in, SUM(COALESCE(value_out, 0)) as total_out')
                ->groupBy(DB::raw('DATE(date)'))
                ->get();

            foreach ($outletAdjCards as $adjRow) {
                $dateKey = Carbon::parse($adjRow->d)->toDateString();
                $adjInByDate[$dateKey] = round((float) ($adjRow->total_in ?? 0), 2);
                $adjOutByDate[$dateKey] = round((float) ($adjRow->total_out ?? 0), 2);
            }
        }

        $rows = [];
        ksort($categoryCostTypes);
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $ds = $cursor->toDateString();
            $forecast = (float) ($forecastByDate[$ds] ?? 0);
            $capKb = round($forecast * self::KITCHEN_BAR_RATIO, 2);
            $capSvc = round($forecast * self::SERVICE_RATIO, 2);
            $kb = round((float) ($roKitchenBar[$ds] ?? 0), 2);
            $svc = round((float) ($roService[$ds] ?? 0), 2);

            $diffKb = round($kb - $capKb, 2);
            $diffSvc = round($svc - $capSvc, 2);

            $pctKb = $capKb > 0 ? round(($kb / $capKb) * 100, 1) : null;
            $pctSvc = $capSvc > 0 ? round(($svc / $capSvc) * 100, 1) : null;

            $revenue = round((float) ($revenueByDate[$ds] ?? 0), 2);
            $revenueBeforeDiscount = round((float) ($revenueBeforeDiscountByDate[$ds] ?? 0), 2);
            $revenueWithoutTaxService = round((float) ($revenueWithoutTaxServiceByDate[$ds] ?? 0), 2);
            $engineering = $revenueBeforeDiscount;
            $discount = round((float) ($discountByDate[$ds] ?? 0), 2);
            $pctDiscount = $revenue > 0 ? round(($discount / $revenue) * 100, 1) : null;
            $costMenu = round((float) ($costMenuByDate[$ds] ?? 0), 2);
            $costModifier = round((float) ($costModifierByDate[$ds] ?? 0), 2);
            $categoryCostUsage = round((float) ($categoryCostUsageByDate[$ds] ?? 0), 2);
            $categoryCostValues = [];
            $categoryCostTotalNonUsage = 0.0;
            foreach ($categoryCostTypes as $typeKey => $typeLabel) {
                $v = round((float) ($categoryCostByTypeByDate[$typeKey][$ds] ?? 0), 2);
                $categoryCostValues[$typeKey] = $v;
                $categoryCostTotalNonUsage += $v;
            }
            $categoryCostTotalNonUsage = round($categoryCostTotalNonUsage, 2);
            $costTotal = round($costMenu + $costModifier + $categoryCostUsage, 2);
            $categoryCostNonUsageExcludingRndMarketing = 0.0;
            foreach ($categoryCostValues as $typeKey => $value) {
                if (in_array((string) $typeKey, ['rnd', 'marketing'], true)) {
                    continue;
                }
                $categoryCostNonUsageExcludingRndMarketing += (float) $value;
            }
            $categoryCostNonUsageExcludingRndMarketing = round($categoryCostNonUsageExcludingRndMarketing, 2);
            $costPercentageBase = round($costTotal + $categoryCostNonUsageExcludingRndMarketing, 2);
            $costXRevenue = $revenue > 0 ? round(($costPercentageBase / $revenue) * 100, 2) : null;
            $costXEngineering = $engineering > 0 ? round(($costPercentageBase / $engineering) * 100, 2) : null;
            $pctCost = $revenue > 0 ? round(($costTotal / $revenue) * 100, 1) : null;
            $stockOnHandKitchenBar = round((float) ($stockOnHandKitchenBarByDate[$ds] ?? 0), 2);
            $stockOnHandService = round((float) ($stockOnHandServiceByDate[$ds] ?? 0), 2);
            $stockOnHandTotal = round((float) ($stockOnHandTotalByDate[$ds] ?? 0), 2);
            $pctCogs = $stockOnHandTotal > 0 ? round(($costTotal / $stockOnHandTotal) * 100, 2) : null;

            $rows[] = [
                'date' => $ds,
                'day_name' => $cursor->locale('id')->isoFormat('dddd'),
                'forecast_revenue' => $forecast,
                'revenue' => $revenue,
                'revenue_before_discount' => $revenueBeforeDiscount,
                'engineering' => $engineering,
                'revenue_without_tax_service' => $revenueWithoutTaxService,
                'discount' => $discount,
                'pct_discount' => $pctDiscount,
                'begin_stock_kitchen_bar' => round((float) ($beginStockKitchenBarByDate[$ds] ?? 0), 2),
                'begin_stock_service' => round((float) ($beginStockServiceByDate[$ds] ?? 0), 2),
                'begin_stock_total' => round((float) ($beginStockTotalByDate[$ds] ?? 0), 2),
                'cost_menu' => $costMenu,
                'cost_modifier' => $costModifier,
                'category_cost_usage' => $categoryCostUsage,
                'cost_total' => $costTotal,
                'pct_cost' => $pctCost,
                'category_cost_values' => $categoryCostValues,
                'category_cost_total_non_usage' => $categoryCostTotalNonUsage,
                'category_cost_total_non_usage_excluding_rnd_marketing' => $categoryCostNonUsageExcludingRndMarketing,
                'cost_percentage_base' => $costPercentageBase,
                'cost_x_revenue' => $costXRevenue,
                'cost_x_engineering' => $costXEngineering,
                'stock_on_hand_kitchen_bar' => $stockOnHandKitchenBar,
                'stock_on_hand_service' => $stockOnHandService,
                'stock_on_hand_total' => $stockOnHandTotal,
                'pct_cogs' => $pctCogs,
                'cap_kitchen_bar' => $capKb,
                'cap_service' => $capSvc,
                'ro_kitchen_bar' => $kb,
                'ro_service' => $svc,
                'transfer_in' => round((float) ($transferInByDate[$ds] ?? 0), 2),
                'transfer_out' => round((float) ($transferOutByDate[$ds] ?? 0), 2),
                'adj_in' => round((float) ($adjInByDate[$ds] ?? 0), 2),
                'adj_out' => round((float) ($adjOutByDate[$ds] ?? 0), 2),
                'diff_kitchen_bar' => $diffKb,
                'diff_service' => $diffSvc,
                'pct_kitchen_bar_vs_cap' => $pctKb,
                'pct_service_vs_cap' => $pctSvc,
            ];
            $cursor->addDay();
        }

        $totals = [
            'forecast_revenue' => 0,
            'revenue' => 0,
            'revenue_before_discount' => 0,
            'engineering' => 0,
            'revenue_without_tax_service' => 0,
            'discount' => 0,
            'pct_discount' => null,
            'begin_stock_kitchen_bar_start' => 0,
            'begin_stock_service_start' => 0,
            'begin_stock_total_start' => 0,
            'cost_menu' => 0,
            'cost_modifier' => 0,
            'category_cost_usage' => 0,
            'category_cost_values' => [],
            'category_cost_total_non_usage' => 0,
            'category_cost_total_non_usage_excluding_rnd_marketing' => 0,
            'cost_total' => 0,
            'pct_cost' => null,
            'cost_percentage_base' => 0,
            'cost_x_revenue' => null,
            'cost_x_engineering' => null,
            'stock_on_hand_kitchen_bar_end' => 0,
            'stock_on_hand_service_end' => 0,
            'stock_on_hand_total_end' => 0,
            'pct_cogs' => null,
            'cap_kitchen_bar' => 0,
            'cap_service' => 0,
            'ro_kitchen_bar' => 0,
            'ro_service' => 0,
            'transfer_in' => 0,
            'transfer_out' => 0,
            'adj_in' => 0,
            'adj_out' => 0,
            'diff_kitchen_bar' => 0,
            'diff_service' => 0,
        ];
        foreach (array_keys($categoryCostTypes) as $typeKey) {
            $totals['category_cost_values'][$typeKey] = 0;
        }
        foreach ($rows as $r) {
            $totals['forecast_revenue'] += $r['forecast_revenue'];
            $totals['revenue'] += $r['revenue'];
            $totals['revenue_before_discount'] += $r['revenue_before_discount'];
            $totals['engineering'] += $r['engineering'];
            $totals['revenue_without_tax_service'] += $r['revenue_without_tax_service'];
            $totals['discount'] += $r['discount'];
            $totals['cost_menu'] += $r['cost_menu'];
            $totals['cost_modifier'] += $r['cost_modifier'];
            $totals['category_cost_usage'] += $r['category_cost_usage'];
            $totals['category_cost_total_non_usage'] += $r['category_cost_total_non_usage'];
            $totals['category_cost_total_non_usage_excluding_rnd_marketing'] += $r['category_cost_total_non_usage_excluding_rnd_marketing'];
            foreach (($r['category_cost_values'] ?? []) as $typeKey => $value) {
                $totals['category_cost_values'][$typeKey] = ($totals['category_cost_values'][$typeKey] ?? 0) + (float) $value;
            }
            $totals['cost_total'] += $r['cost_total'];
            $totals['cap_kitchen_bar'] += $r['cap_kitchen_bar'];
            $totals['cap_service'] += $r['cap_service'];
            $totals['ro_kitchen_bar'] += $r['ro_kitchen_bar'];
            $totals['ro_service'] += $r['ro_service'];
            $totals['transfer_in'] += $r['transfer_in'];
            $totals['transfer_out'] += $r['transfer_out'];
            $totals['adj_in'] += $r['adj_in'];
            $totals['adj_out'] += $r['adj_out'];
            $totals['diff_kitchen_bar'] += $r['diff_kitchen_bar'];
            $totals['diff_service'] += $r['diff_service'];
        }
        if (!empty($rows)) {
            $first = $rows[0];
            $last = $rows[count($rows) - 1];
            $totals['begin_stock_kitchen_bar_start'] = round((float) ($first['begin_stock_kitchen_bar'] ?? 0), 2);
            $totals['begin_stock_service_start'] = round((float) ($first['begin_stock_service'] ?? 0), 2);
            $totals['begin_stock_total_start'] = round((float) ($first['begin_stock_total'] ?? 0), 2);
            $totals['stock_on_hand_kitchen_bar_end'] = round((float) ($last['stock_on_hand_kitchen_bar'] ?? 0), 2);
            $totals['stock_on_hand_service_end'] = round((float) ($last['stock_on_hand_service'] ?? 0), 2);
            $totals['stock_on_hand_total_end'] = round((float) ($last['stock_on_hand_total'] ?? 0), 2);
        }
        foreach ($totals as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vk => $vv) {
                    $totals[$k][$vk] = round((float) $vv, 2);
                }
            } else {
                $totals[$k] = round((float) $v, 2);
            }
        }
        $totals['pct_discount'] = $totals['revenue'] > 0 ? round(($totals['discount'] / $totals['revenue']) * 100, 1) : null;
        $totals['pct_cost'] = $totals['revenue'] > 0 ? round(($totals['cost_total'] / $totals['revenue']) * 100, 1) : null;
        $categoryCostTotalNonUsageExcludingRndMarketing = 0.0;
        foreach (($totals['category_cost_values'] ?? []) as $typeKey => $value) {
            if (in_array((string) $typeKey, ['rnd', 'marketing'], true)) {
                continue;
            }
            $categoryCostTotalNonUsageExcludingRndMarketing += (float) $value;
        }
        $totals['category_cost_total_non_usage_excluding_rnd_marketing'] = round($categoryCostTotalNonUsageExcludingRndMarketing, 2);
        $totals['cost_percentage_base'] = round(
            (float) $totals['cost_total'] + (float) $totals['category_cost_total_non_usage_excluding_rnd_marketing'],
            2
        );
        $totals['cost_x_revenue'] = $totals['revenue'] > 0
            ? round(((float) $totals['cost_percentage_base'] / (float) $totals['revenue']) * 100, 2)
            : null;
        $totals['cost_x_engineering'] = $totals['engineering'] > 0
            ? round(((float) $totals['cost_percentage_base'] / (float) $totals['engineering']) * 100, 2)
            : null;
        $totals['pct_cogs'] = $totals['stock_on_hand_total_end'] > 0
            ? round(((float) $totals['cost_total'] / (float) $totals['stock_on_hand_total_end']) * 100, 2)
            : null;

        return [
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'selectedMonth' => $month,
            'month_label' => $monthStart->locale('id')->translatedFormat('F Y'),
            'monthlyTarget' => $monthlyTarget,
            'kitchen_bar_ratio_pct' => (int) round(self::KITCHEN_BAR_RATIO * 100),
            'service_ratio_pct' => (int) round(self::SERVICE_RATIO * 100),
            'rows' => $rows,
            'totals' => $totals,
            'category_cost_types' => collect($categoryCostTypes)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'has_forecast_header' => $revenueTargetHeaderExists || count($forecastByDate) > 0,
            'canSelectOutlet' => $isAdminOutlet,
        ];
    }
}
