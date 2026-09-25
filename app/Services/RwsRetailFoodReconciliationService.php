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
        $result = $this->reconcile($filters);
        $unmatched = $result['unmatched'];

        if (! empty($filters['include_items'])) {
            $unmatched = $this->attachItems($unmatched);
        }

        $byOutlet = $this->summarizeByOutlet($unmatched);

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
     * RWS yang sudah punya pasangan Retail Food Justus/Yuditama (tampil nomor RWS + RF).
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
     *     summary: array{total_rws: int, total_amount: float, total_rf: int, outlet_count: int},
     *     by_outlet: Collection<int, object>
     * }
     */
    public function matchedRws(array $filters = []): array
    {
        $filters['_defer_search'] = true;
        $result = $this->reconcile($filters);
        $matched = $result['matched'];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $matched = $matched->filter(function ($w) use ($search) {
                $hay = strtolower(implode(' ', [
                    (string) ($w->number ?? ''),
                    (string) ($w->rf_number ?? ''),
                    (string) ($w->outlet_name ?? ''),
                    (string) ($w->customer_name ?? ''),
                    (string) ($w->rf_supplier_name ?? ''),
                ]));

                return str_contains($hay, strtolower($search));
            })->values();
        }

        if (! empty($filters['include_items'])) {
            $matched = $this->attachItems($matched);
        }

        $byOutlet = $matched
            ->groupBy(fn ($w) => (int) $w->outlet_id)
            ->map(function (Collection $rows, $oid) {
                $first = $rows->first();

                return (object) [
                    'outlet_id' => (int) $oid,
                    'outlet_name' => $first->outlet_name ?: ($first->customer_name ?: 'Outlet #'.$oid),
                    'total_rws' => $rows->count(),
                    'total_rf' => $rows->pluck('rf_id')->filter()->unique()->count(),
                    'total_amount' => (float) $rows->sum('total_amount'),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return [
            'rows' => $matched,
            'summary' => [
                'total_rws' => $matched->count(),
                'total_amount' => (float) $matched->sum('total_amount'),
                'total_rf' => $matched->pluck('rf_id')->filter()->unique()->count(),
                'outlet_count' => $byOutlet->count(),
            ],
            'by_outlet' => $byOutlet,
        ];
    }

    /**
     * @param  array{
     *     from_date?: string|null,
     *     to_date?: string|null,
     *     outlet_id?: int|null,
     *     search?: string|null,
     *     _defer_search?: bool
     * }  $filters
     * @return array{matched: Collection<int, object>, unmatched: Collection<int, object>}
     */
    private function reconcile(array $filters): array
    {
        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? null;
        $outletId = isset($filters['outlet_id']) && $filters['outlet_id'] !== '' && $filters['outlet_id'] !== null
            ? (int) $filters['outlet_id']
            : null;
        $search = trim((string) ($filters['search'] ?? ''));
        $tol = self::AMOUNT_TOLERANCE;
        $deferSearch = ! empty($filters['_defer_search']);

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
        if ($search !== '' && ! $deferSearch) {
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
                'matched' => collect(),
                'unmatched' => collect(),
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
            $rfFrom = date('Y-m-d', strtotime($from.' -3 days'));
            $rfQuery->whereDate('rf.transaction_date', '>=', $rfFrom);
        }
        if ($to) {
            $rfTo = date('Y-m-d', strtotime($to.' +3 days'));
            $rfQuery->whereDate('rf.transaction_date', '<=', $rfTo);
        }

        $rfAll = $rfQuery->orderBy('rf.transaction_date')->orderBy('rf.id')->get();

        /** @var array<int, object> $rwsMatch */
        $rwsMatch = [];
        $usedRf = [];
        $usedRws = [];

        $rwsByOutlet = $rwsAll->groupBy(fn ($w) => (int) $w->outlet_id);
        $rfByOutlet = $rfAll->groupBy(fn ($f) => (int) $f->outlet_id);

        $storeMatch = function ($w, $f, string $type) use (&$rwsMatch, &$usedRf, &$usedRws): void {
            $usedRf[$f->id] = true;
            $usedRws[$w->id] = true;
            $rwsMatch[$w->id] = (object) [
                'rf_id' => $f->id,
                'rf_number' => $f->retail_number,
                'rf_date' => $f->transaction_date,
                'rf_amount' => (float) $f->total_amount,
                'rf_supplier_name' => $f->supplier_name,
                'match_type' => $type,
                'amount_diff' => round((float) $w->total_amount - (float) $f->total_amount, 2),
            ];
        };

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
                        $storeMatch($w, $f, '1:1 same day');
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
                            $storeMatch($w, $f, 'N:1 same day');
                        }
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
                        $storeMatch($w, $f, 'subset same day');
                    }
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
                        $storeMatch($w, $f, '1:1 ±3 hari');
                        break;
                    }
                }
            }
        }

        $inPeriod = function ($w) use ($from, $to): bool {
            $d = substr((string) $w->sale_date, 0, 10);
            if ($from && $d < $from) {
                return false;
            }
            if ($to && $d > $to) {
                return false;
            }

            return true;
        };

        $matched = $rwsAll
            ->filter(fn ($w) => isset($rwsMatch[$w->id]) && $inPeriod($w))
            ->map(function ($w) use ($rwsMatch) {
                $m = $rwsMatch[$w->id];
                $w->rf_id = $m->rf_id;
                $w->rf_number = $m->rf_number;
                $w->rf_date = $m->rf_date;
                $w->rf_amount = $m->rf_amount;
                $w->rf_supplier_name = $m->rf_supplier_name;
                $w->match_type = $m->match_type;
                $w->amount_diff = $m->amount_diff;

                return $w;
            })
            ->values();

        $unmatched = $rwsAll
            ->filter(fn ($w) => ! isset($usedRws[$w->id]) && $inPeriod($w))
            ->values();

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function attachItems(Collection $rows): Collection
    {
        if ($rows->isEmpty() || ! Schema::hasTable('retail_warehouse_sale_items')) {
            return $rows;
        }

        $ids = $rows->pluck('id')->all();
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

        return $rows->map(function ($w) use ($itemRows) {
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

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function summarizeByOutlet(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn ($w) => (int) $w->outlet_id)
            ->map(function (Collection $group, $oid) {
                $first = $group->first();

                return (object) [
                    'outlet_id' => (int) $oid,
                    'outlet_name' => $first->outlet_name ?: ($first->customer_name ?: 'Outlet #'.$oid),
                    'total_rws' => $group->count(),
                    'total_amount' => (float) $group->sum('total_amount'),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();
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
