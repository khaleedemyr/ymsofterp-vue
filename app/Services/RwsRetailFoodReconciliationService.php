<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RwsRetailFoodReconciliationService
{
    /**
     * Toleransi selisih nominal RWS vs RF (Rp).
     */
    public const AMOUNT_TOLERANCE = 5000;

    /**
     * RWS completed ke customer branch yang belum punya pasangan Retail Food Justus/Yuditama.
     *
     * Matching: same-day ±tolerance, N RWS digabung 1 RF, lalu 1:1 dalam ±3 hari.
     *
     * @param  array{
     *     from_date?: string|null,
     *     to_date?: string|null,
     *     outlet_id?: int|null,
     *     search?: string|null,
     *     include_items?: bool
     * }  $filters
     * @return array{
     *     rows: Collection<int, object>,
     *     summary: array{total_rws: int, total_amount: float, outlet_count: int},
     *     by_outlet: Collection<int, object>
     * }
     */
    public function unmatchedRws(array $filters = []): array
    {
        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;
        $outletId = isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== null
            ? (int) $filters['outlet_id']
            : null;
        $search = trim((string) ($filters['search'] ?? ''));
        $includeItems = (bool) ($filters['include_items'] ?? false);
        $tol = self::AMOUNT_TOLERANCE;

        $rwsQuery = DB::table('retail_warehouse_sales as rws')
            ->join('customers as c', 'rws.customer_id', '=', 'c.id')
            ->leftJoin('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
            ->leftJoin('warehouses as w', 'rws.warehouse_id', '=', 'w.id')
            ->where('rws.status', 'completed')
            ->where('c.type', 'branch')
            ->whereNotNull('c.id_outlet')
            ->select([
                'rws.id',
                'rws.number',
                'rws.sale_date',
                'rws.total_amount',
                'rws.notes',
                'c.id as customer_id',
                'c.name as customer_name',
                'c.code as customer_code',
                'c.id_outlet as outlet_id',
                'o.nama_outlet as outlet_name',
                'w.name as warehouse_name',
            ]);

        if ($from) {
            $rwsQuery->whereDate('rws.sale_date', '>=', $from);
        }
        if ($to) {
            $rwsQuery->whereDate('rws.sale_date', '<=', $to);
        }
        if ($outletId) {
            $rwsQuery->where('c.id_outlet', $outletId);
        }
        if ($search !== '') {
            $rwsQuery->where(function ($q) use ($search) {
                $q->where('rws.number', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%")
                    ->orWhere('o.nama_outlet', 'like', "%{$search}%");
            });
        }

        $rwsAll = $rwsQuery
            ->orderBy('rws.sale_date')
            ->orderBy('rws.id')
            ->get();

        if ($rwsAll->isEmpty()) {
            return [
                'rows' => collect(),
                'summary' => ['total_rws' => 0, 'total_amount' => 0.0, 'outlet_count' => 0],
                'by_outlet' => collect(),
            ];
        }

        $outletIds = $rwsAll->pluck('outlet_id')->map(fn ($id) => (int) $id)->unique()->filter()->values()->all();

        $rfQuery = DB::table('retail_food as rf')
            ->join('suppliers as s', 'rf.supplier_id', '=', 's.id')
            ->where('rf.status', 'approved')
            ->whereNull('rf.deleted_at')
            ->whereIn('rf.outlet_id', $outletIds)
            ->where(function ($q) {
                $q->where('s.name', 'like', '%Justus%')
                    ->orWhere('s.name', 'like', '%Yuditama%');
            })
            ->select([
                'rf.id',
                'rf.retail_number',
                'rf.transaction_date',
                'rf.total_amount',
                'rf.outlet_id',
                's.name as supplier_name',
            ]);

        if ($from) {
            // RF window slightly wider so ±3 day matching still works at period edges
            $rfFrom = date('Y-m-d', strtotime($from.' -3 days'));
            $rfQuery->whereDate('rf.transaction_date', '>=', $rfFrom);
        }
        if ($to) {
            $rfTo = date('Y-m-d', strtotime($to.' +3 days'));
            $rfQuery->whereDate('rf.transaction_date', '<=', $rfTo);
        }

        $rfAll = $rfQuery->orderBy('rf.transaction_date')->orderBy('rf.id')->get();

        $usedRf = [];
        $usedRws = [];

        $rwsByOutlet = $rwsAll->groupBy(fn ($w) => (int) $w->outlet_id);
        $rfByOutlet = $rfAll->groupBy(fn ($f) => (int) $f->outlet_id);

        foreach ($rwsByOutlet as $oid => $outletRws) {
            $outletRf = $rfByOutlet->get($oid, collect());

            // 1) 1:1 same day
            foreach ($outletRws as $w) {
                foreach ($outletRf as $f) {
                    if (isset($usedRf[$f->id])) {
                        continue;
                    }
                    $sameDay = substr((string) $f->transaction_date, 0, 10) === substr((string) $w->sale_date, 0, 10);
                    if ($sameDay && abs((float) $f->total_amount - (float) $w->total_amount) <= $tol) {
                        $usedRf[$f->id] = true;
                        $usedRws[$w->id] = true;
                        break;
                    }
                }
            }

            // 2) N:1 same day
            $remainingByDay = [];
            foreach ($outletRws as $w) {
                if (isset($usedRws[$w->id])) {
                    continue;
                }
                $d = substr((string) $w->sale_date, 0, 10);
                $remainingByDay[$d][] = $w;
            }

            foreach ($remainingByDay as $day => $dayRws) {
                $dayRf = $outletRf->filter(fn ($f) => ! isset($usedRf[$f->id])
                    && substr((string) $f->transaction_date, 0, 10) === $day)->values();

                if ($dayRf->isEmpty()) {
                    continue;
                }

                $daySum = array_sum(array_map(fn ($w) => (float) $w->total_amount, $dayRws));
                foreach ($dayRf as $f) {
                    if (abs($daySum - (float) $f->total_amount) <= $tol) {
                        foreach ($dayRws as $w) {
                            $usedRws[$w->id] = true;
                        }
                        $usedRf[$f->id] = true;
                        $dayRws = [];
                        break;
                    }
                }

                $dayRws = array_values(array_filter($dayRws, fn ($w) => ! isset($usedRws[$w->id])));
                $dayRf = $outletRf->filter(fn ($f) => ! isset($usedRf[$f->id])
                    && substr((string) $f->transaction_date, 0, 10) === $day)->values();

                foreach ($dayRf as $f) {
                    $found = $this->findAmountSubset($dayRws, (float) $f->total_amount, $tol);
                    if ($found === null) {
                        continue;
                    }
                    foreach ($found as $w) {
                        $usedRws[$w->id] = true;
                    }
                    $usedRf[$f->id] = true;
                    $dayRws = array_values(array_filter($dayRws, fn ($w) => ! isset($usedRws[$w->id])));
                }
            }

            // 3) 1:1 ±3 days
            foreach ($outletRws as $w) {
                if (isset($usedRws[$w->id])) {
                    continue;
                }
                $wDate = substr((string) $w->sale_date, 0, 10);
                foreach ($outletRf as $f) {
                    if (isset($usedRf[$f->id])) {
                        continue;
                    }
                    $fDate = substr((string) $f->transaction_date, 0, 10);
                    $dayDiff = abs((strtotime($fDate) - strtotime($wDate)) / 86400);
                    if ($dayDiff <= 3 && abs((float) $f->total_amount - (float) $w->total_amount) <= $tol) {
                        $usedRf[$f->id] = true;
                        $usedRws[$w->id] = true;
                        break;
                    }
                }
            }
        }

        $unmatched = $rwsAll->filter(fn ($w) => ! isset($usedRws[$w->id]))->values();

        // Restrict RF window display relevance: only keep unmatched whose sale_date is in requested period
        if ($from || $to) {
            $unmatched = $unmatched->filter(function ($w) use ($from, $to) {
                $d = substr((string) $w->sale_date, 0, 10);
                if ($from && $d < $from) {
                    return false;
                }
                if ($to && $d > $to) {
                    return false;
                }

                return true;
            })->values();
        }

        if ($includeItems && $unmatched->isNotEmpty() && Schema::hasTable('retail_warehouse_sale_items')) {
            $ids = $unmatched->pluck('id')->all();
            $itemRows = DB::table('retail_warehouse_sale_items as i')
                ->leftJoin('items as it', 'i.item_id', '=', 'it.id')
                ->whereIn('i.retail_warehouse_sale_id', $ids)
                ->get([
                    'i.retail_warehouse_sale_id',
                    'i.qty',
                    'i.unit',
                    'i.price',
                    'i.subtotal',
                    'i.barcode',
                    'it.name as item_name',
                ])
                ->groupBy('retail_warehouse_sale_id');

            $unmatched = $unmatched->map(function ($w) use ($itemRows) {
                $items = $itemRows->get($w->id, collect())->map(function ($it) {
                    return [
                        'item_name' => $it->item_name ?: ($it->barcode ?: 'item'),
                        'qty' => (float) $it->qty,
                        'unit' => $it->unit,
                        'price' => (float) $it->price,
                        'subtotal' => (float) $it->subtotal,
                    ];
                })->values()->all();
                $w->items = $items;
                $w->items_summary = collect($items)->map(function ($it) {
                    $qty = rtrim(rtrim(number_format($it['qty'], 3, '.', ''), '0'), '.');

                    return sprintf('%s (%s %s)', $it['item_name'], $qty, $it['unit'] ?: '');
                })->implode('; ');

                return $w;
            });
        }

        $byOutlet = $unmatched
            ->groupBy(fn ($w) => (int) $w->outlet_id)
            ->map(function (Collection $rows, $oid) {
                $first = $rows->first();

                return (object) [
                    'outlet_id' => (int) $oid,
                    'outlet_name' => $first->outlet_name ?: ($first->customer_name ?: 'Outlet #'.$oid),
                    'total_rws' => $rows->count(),
                    'total_amount' => (float) $rows->sum('total_amount'),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return [
            'rows' => $unmatched,
            'summary' => [
                'total_rws' => $unmatched->count(),
                'total_amount' => (float) $unmatched->sum('total_amount'),
                'outlet_count' => $byOutlet->count(),
            ],
            'by_outlet' => $byOutlet,
        ];
    }

    /**
     * @param  list<object>  $candidates
     * @return list<object>|null
     */
    private function findAmountSubset(array $candidates, float $target, float $tol): ?array
    {
        $n = count($candidates);
        if ($n < 2) {
            return null;
        }

        for ($size = 2; $size <= min(5, $n); $size++) {
            $idxs = range(0, $n - 1);
            $combos = [[]];
            foreach ($idxs as $i) {
                $new = [];
                foreach ($combos as $c) {
                    if (count($c) >= $size) {
                        continue;
                    }
                    $nc = array_merge($c, [$i]);
                    $new[] = $nc;
                    if (count($nc) === $size) {
                        $sum = 0.0;
                        $subset = [];
                        foreach ($nc as $j) {
                            $sum += (float) $candidates[$j]->total_amount;
                            $subset[] = $candidates[$j];
                        }
                        if (abs($sum - $target) <= $tol) {
                            return $subset;
                        }
                    }
                }
                $combos = array_merge($combos, $new);
            }
        }

        return null;
    }

    /**
     * @return Collection<int, object{id:int,name:string}>
     */
    public function branchOutlets(): Collection
    {
        return DB::table('customers as c')
            ->join('tbl_data_outlet as o', 'c.id_outlet', '=', 'o.id_outlet')
            ->where('c.type', 'branch')
            ->whereNotNull('c.id_outlet')
            ->select('o.id_outlet as id', 'o.nama_outlet as name')
            ->distinct()
            ->orderBy('o.nama_outlet')
            ->get();
    }
}
