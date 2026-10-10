<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContraBonPoVarianceReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->input('search', ''),
            'status' => $request->input('status', ''),
            'supplier_id' => $request->input('supplier_id', ''),
            'date_from' => $request->input('date_from', ''),
            'date_to' => $request->input('date_to', ''),
            'diff_type' => $request->input('diff_type', 'any'), // any|price|discount
            'only_diff' => $request->boolean('only_diff', true),
            'per_page' => (int) $request->input('per_page', 25),
        ];

        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        if (!$request->boolean('load_data')) {
            return Inertia::render('ContraBonPoVarianceReport/Index', [
                'rows' => null,
                'summary' => null,
                'suppliers' => $suppliers,
                'filters' => $filters,
                'dataLoaded' => false,
            ]);
        }

        $query = DB::table('food_contra_bon_items as cbi')
            ->join('food_contra_bons as cb', 'cb.id', '=', 'cbi.contra_bon_id')
            ->join('purchase_order_food_items as poi', 'poi.id', '=', 'cbi.po_item_id')
            ->leftJoin('purchase_order_foods as po', function ($join) {
                $join->whereRaw('po.id = COALESCE(poi.purchase_order_food_id, poi.purchase_order_id, cb.po_id)');
            })
            ->leftJoin('items as i', 'i.id', '=', 'cbi.item_id')
            ->leftJoin('units as u', 'u.id', '=', 'cbi.unit_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'cb.supplier_id')
            ->whereNotNull('cbi.po_item_id')
            ->select([
                'cb.id as contra_bon_id',
                'cb.number as cb_number',
                'cb.date as cb_date',
                'cb.status as cb_status',
                'cb.discount_total_percent as cb_discount_total_percent',
                'cb.discount_total_amount as cb_discount_total_amount',
                'cb.total_amount as cb_total_amount',
                'po.id as po_id',
                'po.number as po_number',
                'po.date as po_date',
                'po.discount_total_percent as po_discount_total_percent',
                'po.discount_total_amount as po_discount_total_amount',
                'po.grand_total as po_grand_total',
                's.id as supplier_id',
                's.name as supplier_name',
                'i.id as item_id',
                'i.name as item_name',
                'i.sku as item_sku',
                'u.name as unit_name',
                'cbi.id as cb_item_id',
                'cbi.quantity as cb_qty',
                'cbi.price as cb_price',
                'cbi.discount_percent as cb_discount_percent',
                'cbi.discount_amount as cb_discount_amount',
                'cbi.total as cb_line_total',
                'poi.id as po_item_id',
                'poi.quantity as po_qty',
                'poi.price as po_price',
                'poi.discount_percent as po_discount_percent',
                'poi.discount_amount as po_discount_amount',
                'poi.total as po_line_total',
            ]);

        if ($filters['date_from']) {
            $query->whereDate('cb.date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('cb.date', '<=', $filters['date_to']);
        }
        if ($filters['status']) {
            $query->where('cb.status', $filters['status']);
        }
        if ($filters['supplier_id']) {
            $query->where('cb.supplier_id', $filters['supplier_id']);
        }
        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('cb.number', 'like', "%{$search}%")
                    ->orWhere('po.number', 'like', "%{$search}%")
                    ->orWhere('s.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('i.sku', 'like', "%{$search}%");
            });
        }

        $epsilon = 0.0001;
        if ($filters['only_diff']) {
            if ($filters['diff_type'] === 'price') {
                $query->whereRaw('ABS(COALESCE(cbi.price, 0) - COALESCE(poi.price, 0)) > ?', [$epsilon]);
            } elseif ($filters['diff_type'] === 'discount') {
                $query->where(function ($q) use ($epsilon) {
                    $q->whereRaw('ABS(COALESCE(cbi.discount_percent, 0) - COALESCE(poi.discount_percent, 0)) > ?', [$epsilon])
                        ->orWhereRaw('ABS(COALESCE(cbi.discount_amount, 0) - COALESCE(poi.discount_amount, 0)) > ?', [$epsilon]);
                });
            } else {
                $query->where(function ($q) use ($epsilon) {
                    $q->whereRaw('ABS(COALESCE(cbi.price, 0) - COALESCE(poi.price, 0)) > ?', [$epsilon])
                        ->orWhereRaw('ABS(COALESCE(cbi.discount_percent, 0) - COALESCE(poi.discount_percent, 0)) > ?', [$epsilon])
                        ->orWhereRaw('ABS(COALESCE(cbi.discount_amount, 0) - COALESCE(poi.discount_amount, 0)) > ?', [$epsilon]);
                });
            }
        }

        $perPage = max(10, min(200, $filters['per_page'] ?: 25));
        $paginator = $query
            ->orderByDesc('cb.date')
            ->orderByDesc('cb.id')
            ->orderBy('i.name')
            ->paginate($perPage)
            ->withQueryString();

        $rows = collect($paginator->items())->map(function ($row) {
            $cbQty = (float) ($row->cb_qty ?? 0);
            $poQty = (float) ($row->po_qty ?? 0);
            $poPrice = (float) ($row->po_price ?? 0);
            $cbPrice = (float) ($row->cb_price ?? 0);
            $poDiscPct = (float) ($row->po_discount_percent ?? 0);
            $cbDiscPct = (float) ($row->cb_discount_percent ?? 0);
            $poDiscAmt = (float) ($row->po_discount_amount ?? 0);
            $cbDiscAmt = (float) ($row->cb_discount_amount ?? 0);
            $cbLineTotal = (float) ($row->cb_line_total ?? 0);

            // Expected line from PO rules, applied to CB qty
            $expectedSubtotal = $cbQty * $poPrice;
            if ($poDiscPct > 0) {
                $expectedDiscount = $expectedSubtotal * ($poDiscPct / 100);
            } elseif ($poDiscAmt > 0) {
                $expectedDiscount = $poQty > 0 ? ($poDiscAmt * ($cbQty / $poQty)) : $poDiscAmt;
            } else {
                $expectedDiscount = 0;
            }
            $expectedLineTotal = $expectedSubtotal - $expectedDiscount;

            $priceDiff = $cbPrice - $poPrice;
            $discPctDiff = $cbDiscPct - $poDiscPct;
            $discAmtDiff = $cbDiscAmt - $poDiscAmt;
            $lineDiff = $cbLineTotal - $expectedLineTotal;

            $diffFlags = [];
            if (abs($priceDiff) > 0.0001) {
                $diffFlags[] = 'price';
            }
            if (abs($discPctDiff) > 0.0001 || abs($discAmtDiff) > 0.0001) {
                $diffFlags[] = 'discount';
            }

            return [
                'cb_item_id' => $row->cb_item_id,
                'contra_bon_id' => $row->contra_bon_id,
                'cb_number' => $row->cb_number,
                'cb_date' => $row->cb_date,
                'cb_status' => $row->cb_status,
                'po_id' => $row->po_id,
                'po_number' => $row->po_number,
                'po_date' => $row->po_date,
                'supplier_name' => $row->supplier_name,
                'item_name' => $row->item_name,
                'item_sku' => $row->item_sku,
                'unit_name' => $row->unit_name,
                'cb_qty' => $cbQty,
                'po_qty' => $poQty,
                'po_price' => $poPrice,
                'cb_price' => $cbPrice,
                'price_diff' => round($priceDiff, 4),
                'po_discount_percent' => $poDiscPct,
                'cb_discount_percent' => $cbDiscPct,
                'discount_percent_diff' => round($discPctDiff, 4),
                'po_discount_amount' => $poDiscAmt,
                'cb_discount_amount' => $cbDiscAmt,
                'discount_amount_diff' => round($discAmtDiff, 4),
                'po_expected_line_total' => round($expectedLineTotal, 2),
                'cb_line_total' => round($cbLineTotal, 2),
                'line_total_diff' => round($lineDiff, 2),
                'diff_flags' => $diffFlags,
                'po_discount_total_percent' => (float) ($row->po_discount_total_percent ?? 0),
                'po_discount_total_amount' => (float) ($row->po_discount_total_amount ?? 0),
                'cb_discount_total_percent' => (float) ($row->cb_discount_total_percent ?? 0),
                'cb_discount_total_amount' => (float) ($row->cb_discount_total_amount ?? 0),
            ];
        });

        $summaryBase = DB::table('food_contra_bon_items as cbi')
            ->join('food_contra_bons as cb', 'cb.id', '=', 'cbi.contra_bon_id')
            ->join('purchase_order_food_items as poi', 'poi.id', '=', 'cbi.po_item_id')
            ->leftJoin('purchase_order_foods as po', function ($join) {
                $join->whereRaw('po.id = COALESCE(poi.purchase_order_food_id, poi.purchase_order_id, cb.po_id)');
            })
            ->leftJoin('suppliers as s', 's.id', '=', 'cb.supplier_id')
            ->leftJoin('items as i', 'i.id', '=', 'cbi.item_id')
            ->whereNotNull('cbi.po_item_id');

        if ($filters['date_from']) {
            $summaryBase->whereDate('cb.date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $summaryBase->whereDate('cb.date', '<=', $filters['date_to']);
        }
        if ($filters['status']) {
            $summaryBase->where('cb.status', $filters['status']);
        }
        if ($filters['supplier_id']) {
            $summaryBase->where('cb.supplier_id', $filters['supplier_id']);
        }
        if ($filters['search']) {
            $search = $filters['search'];
            $summaryBase->where(function ($q) use ($search) {
                $q->where('cb.number', 'like', "%{$search}%")
                    ->orWhere('po.number', 'like', "%{$search}%")
                    ->orWhere('s.name', 'like', "%{$search}%")
                    ->orWhere('i.name', 'like', "%{$search}%")
                    ->orWhere('i.sku', 'like', "%{$search}%");
            });
        }

        $summary = (clone $summaryBase)->selectRaw('
            COUNT(*) as total_compared_lines,
            SUM(CASE WHEN ABS(COALESCE(cbi.price, 0) - COALESCE(poi.price, 0)) > 0.0001 THEN 1 ELSE 0 END) as price_diff_count,
            SUM(CASE WHEN ABS(COALESCE(cbi.discount_percent, 0) - COALESCE(poi.discount_percent, 0)) > 0.0001
                      OR ABS(COALESCE(cbi.discount_amount, 0) - COALESCE(poi.discount_amount, 0)) > 0.0001 THEN 1 ELSE 0 END) as discount_diff_count,
            SUM(CASE WHEN ABS(COALESCE(cbi.price, 0) - COALESCE(poi.price, 0)) > 0.0001
                      OR ABS(COALESCE(cbi.discount_percent, 0) - COALESCE(poi.discount_percent, 0)) > 0.0001
                      OR ABS(COALESCE(cbi.discount_amount, 0) - COALESCE(poi.discount_amount, 0)) > 0.0001 THEN 1 ELSE 0 END) as any_diff_count
        ')->first();

        $paginator->setCollection($rows);

        return Inertia::render('ContraBonPoVarianceReport/Index', [
            'rows' => $paginator,
            'summary' => [
                'total_compared_lines' => (int) ($summary->total_compared_lines ?? 0),
                'price_diff_count' => (int) ($summary->price_diff_count ?? 0),
                'discount_diff_count' => (int) ($summary->discount_diff_count ?? 0),
                'any_diff_count' => (int) ($summary->any_diff_count ?? 0),
            ],
            'suppliers' => $suppliers,
            'filters' => $filters,
            'dataLoaded' => true,
        ]);
    }
}
