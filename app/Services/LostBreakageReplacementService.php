<?php

namespace App\Services;

use App\Support\AssetOwnership;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LostBreakageReplacementService
{
    public function prLinesTableExists(): bool
    {
        return Schema::hasTable('lost_breakage_pr_lines');
    }

    public function replacementsTableExists(): bool
    {
        return Schema::hasTable('lost_breakage_replacements');
    }

    /**
     * @return array<int, object>
     */
    public function pendingDetailRows(array $filters, $user): array
    {
        if (!$this->replacementsTableExists()) {
            return [];
        }

        $repSub = DB::table('lost_breakage_replacements')
            ->select('detail_id', DB::raw('COALESCE(SUM(qty_replaced), 0) AS rep_sum'))
            ->groupBy('detail_id');

        $query = DB::table('lost_breakage_details as d')
            ->join('lost_breakage_headers as h', 'd.header_id', '=', 'h.id')
            ->join('items as i', 'd.item_id', '=', 'i.id')
            ->leftJoin('units as u', 'd.unit_id', '=', 'u.id')
            ->leftJoin('tbl_data_outlet as oo', 'h.owner_outlet_id', '=', 'oo.id_outlet')
            ->leftJoin('tbl_data_outlet as ol', 'h.outlet_id', '=', 'ol.id_outlet')
            ->leftJoin('warehouse_outlets as wo', 'h.warehouse_outlet_id', '=', 'wo.id')
            ->leftJoinSub($repSub, 'rs', 'd.id', '=', 'rs.detail_id')
            ->where('h.status', 'APPROVED')
            ->whereRaw('d.qty > COALESCE(rs.rep_sum, 0) + 0.000001')
            ->select(
                'd.id as detail_id',
                'd.header_id',
                'd.item_id',
                'd.type',
                'd.qty',
                'd.unit_id',
                'd.note as detail_note',
                'h.number as header_number',
                'h.date as header_date',
                'h.owner_outlet_id',
                'h.outlet_id',
                'h.warehouse_outlet_id',
                'i.name as item_name',
                'i.sku',
                'u.name as unit_name',
                DB::raw(AssetOwnership::ownerNameSql('h.owner_outlet_id', 'oo.nama_outlet') . ' as owner_outlet_name'),
                'ol.nama_outlet as location_outlet_name',
                'wo.name as warehouse_outlet_name',
                DB::raw('COALESCE(rs.rep_sum, 0) AS qty_replaced'),
                DB::raw('(d.qty - COALESCE(rs.rep_sum, 0)) AS qty_remaining')
            );

        if ($user && (int) ($user->id_outlet ?? 0) !== 1) {
            $query->where('h.owner_outlet_id', (int) $user->id_outlet);
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('h.number', 'like', "%{$s}%")
                    ->orWhere('i.name', 'like', "%{$s}%")
                    ->orWhere('i.sku', 'like', "%{$s}%");
            });
        }
        if (!empty($filters['owner_outlet_id'])) {
            $query->where('h.owner_outlet_id', (int) $filters['owner_outlet_id']);
        }
        if (!empty($filters['outlet_id'])) {
            $query->where('h.outlet_id', (int) $filters['outlet_id']);
        }
        if (!empty($filters['type'])) {
            $query->where('d.type', $filters['type']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('h.date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('h.date', '<=', $filters['date_to']);
        }

        $rows = $query
            ->orderByDesc('h.date')
            ->orderByDesc('d.id')
            ->limit(500)
            ->get()
            ->all();

        return $this->attachProcurementPipeline($rows);
    }

    /**
     * Lampirkan status PR → PO → NFP → GR per baris detail (untuk backlog penggantian).
     *
     * @param  array<int, object>  $rows
     * @return array<int, object>
     */
    public function attachProcurementPipeline(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $detailIds = collect($rows)->pluck('detail_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $prsByDetail = collect();
        $prItemIds = [];
        if ($this->prLinesTableExists()) {
            $prsByDetail = DB::table('lost_breakage_pr_lines as lb')
                ->join('purchase_requisitions as pr', 'lb.purchase_requisition_id', '=', 'pr.id')
                ->whereIn('lb.lost_breakage_detail_id', $detailIds)
                ->select(
                    'lb.lost_breakage_detail_id as detail_id',
                    'lb.qty_planned',
                    'lb.purchase_requisition_item_id as pr_item_id',
                    'pr.id as pr_id',
                    'pr.pr_number',
                    'pr.status as pr_status'
                )
                ->orderByDesc('lb.id')
                ->get()
                ->groupBy('detail_id');

            $prItemIds = $prsByDetail->flatten(1)->pluck('pr_item_id')->filter()->unique()->values()->all();
        }

        $posByPrItem = collect();
        $poIds = [];
        if (!empty($prItemIds) && Schema::hasTable('purchase_order_ops_items')) {
            $posByPrItem = DB::table('purchase_order_ops_items as poi')
                ->join('purchase_order_ops as po', 'poi.purchase_order_ops_id', '=', 'po.id')
                ->whereIn('poi.pr_ops_item_id', $prItemIds)
                ->select(
                    'poi.pr_ops_item_id',
                    'poi.id as po_item_id',
                    'po.id as po_id',
                    'po.number as po_number',
                    'po.status as po_status'
                )
                ->orderByDesc('po.id')
                ->get()
                ->groupBy('pr_ops_item_id');

            $poIds = $posByPrItem->flatten(1)->pluck('po_id')->unique()->values()->all();
        }

        $prIds = $prsByDetail->flatten(1)->pluck('pr_id')->unique()->values()->all();

        $nfpByPo = collect();
        $nfpByPr = collect();
        if (Schema::hasTable('non_food_payments')) {
            if (!empty($poIds)) {
                $nfpByPo = DB::table('non_food_payments')
                    ->whereIn('purchase_order_ops_id', $poIds)
                    ->where('status', '!=', 'cancelled')
                    ->select('id', 'purchase_order_ops_id as po_id', 'payment_number', 'status')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('po_id');
            }
            if (!empty($prIds)) {
                $nfpByPr = DB::table('non_food_payments')
                    ->whereIn('purchase_requisition_id', $prIds)
                    ->whereNull('purchase_order_ops_id')
                    ->where('status', '!=', 'cancelled')
                    ->select('id', 'purchase_requisition_id as pr_id', 'payment_number', 'status')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('pr_id');
            }
        }

        $grByDetail = collect();
        $grByPo = collect();
        if (Schema::hasTable('asset_good_receives')) {
            if ($this->replacementsTableExists() && Schema::hasColumn('lost_breakage_replacements', 'asset_good_receive_id')) {
                $grByDetail = DB::table('lost_breakage_replacements as r')
                    ->join('asset_good_receives as gr', 'r.asset_good_receive_id', '=', 'gr.id')
                    ->whereIn('r.detail_id', $detailIds)
                    ->whereNotNull('r.asset_good_receive_id')
                    ->select(
                        'r.detail_id',
                        'r.qty_replaced',
                        'gr.id as gr_id',
                        'gr.gr_number',
                        'gr.status as gr_status'
                    )
                    ->orderByDesc('gr.id')
                    ->get()
                    ->groupBy('detail_id');
            }

            if (!empty($poIds)) {
                $grByPo = DB::table('asset_good_receives as gr')
                    ->whereIn('gr.po_id', $poIds)
                    ->select('gr.id as gr_id', 'gr.po_id', 'gr.gr_number', 'gr.status as gr_status')
                    ->orderByDesc('gr.id')
                    ->get()
                    ->groupBy('po_id');
            }
        }

        foreach ($rows as $row) {
            $detailId = (int) $row->detail_id;
            $prs = [];
            $pos = [];
            $nfps = [];
            $grs = [];
            $seenPo = [];
            $seenNfp = [];
            $seenGr = [];

            foreach ($prsByDetail->get($detailId, collect()) as $pl) {
                $prs[] = [
                    'id' => (int) $pl->pr_id,
                    'number' => $pl->pr_number,
                    'status' => $pl->pr_status,
                    'qty_planned' => (float) $pl->qty_planned,
                    'url' => '/purchase-requisitions/' . $pl->pr_id,
                ];

                $prItemId = (int) ($pl->pr_item_id ?? 0);
                foreach ($posByPrItem->get($prItemId, collect()) as $poRow) {
                    $poId = (int) $poRow->po_id;
                    if (isset($seenPo[$poId])) {
                        continue;
                    }
                    $seenPo[$poId] = true;
                    $pos[] = [
                        'id' => $poId,
                        'number' => $poRow->po_number,
                        'status' => $poRow->po_status,
                        'url' => '/po-ops/' . $poId,
                    ];

                    foreach ($nfpByPo->get($poId, collect()) as $nfp) {
                        $nfpId = (int) $nfp->id;
                        if (isset($seenNfp[$nfpId])) {
                            continue;
                        }
                        $seenNfp[$nfpId] = true;
                        $nfps[] = [
                            'id' => $nfpId,
                            'number' => $nfp->payment_number,
                            'status' => $nfp->status,
                            'url' => '/non-food-payments/' . $nfpId,
                        ];
                    }

                    foreach ($grByPo->get($poId, collect()) as $grRow) {
                        $grId = (int) $grRow->gr_id;
                        if (isset($seenGr[$grId])) {
                            continue;
                        }
                        $seenGr[$grId] = true;
                        $grs[] = [
                            'id' => $grId,
                            'number' => $grRow->gr_number,
                            'status' => $grRow->gr_status,
                            'url' => '/asset-good-receives/' . $grId,
                        ];
                    }
                }

                foreach ($nfpByPr->get((int) $pl->pr_id, collect()) as $nfp) {
                    $nfpId = (int) $nfp->id;
                    if (isset($seenNfp[$nfpId])) {
                        continue;
                    }
                    $seenNfp[$nfpId] = true;
                    $nfps[] = [
                        'id' => $nfpId,
                        'number' => $nfp->payment_number,
                        'status' => $nfp->status,
                        'url' => '/non-food-payments/' . $nfpId,
                    ];
                }
            }

            foreach ($grByDetail->get($detailId, collect()) as $grRep) {
                $grId = (int) $grRep->gr_id;
                if (isset($seenGr[$grId])) {
                    continue;
                }
                $seenGr[$grId] = true;
                $grs[] = [
                    'id' => $grId,
                    'number' => $grRep->gr_number,
                    'status' => $grRep->gr_status,
                    'qty_replaced' => (float) $grRep->qty_replaced,
                    'url' => '/asset-good-receives/' . $grId,
                ];
            }

            $step = 'belum_pr';
            $stepLabel = 'Belum PR';
            if (!empty($grs)) {
                $step = 'gr';
                $stepLabel = 'GR tercatat';
            } elseif (!empty($nfps)) {
                $step = 'nfp';
                $stepLabel = 'NFP';
            } elseif (!empty($pos)) {
                $step = 'po';
                $stepLabel = 'PO';
            } elseif (!empty($prs)) {
                $step = 'pr';
                $stepLabel = 'PR';
            }

            $row->pipeline_prs = $prs;
            $row->pipeline_pos = $pos;
            $row->pipeline_nfps = $nfps;
            $row->pipeline_grs = $grs;
            $row->pipeline_step = $step;
            $row->pipeline_step_label = $stepLabel;
        }

        return $rows;
    }

    public function resolveDefaultAssetPrCategoryId(): ?int
    {
        $row = DB::table('purchase_requisition_categories')
            ->where(function ($q) {
                $q->where('name', 'like', '%asset%')
                    ->orWhere('name', 'like', '%aset%')
                    ->orWhere('name', 'like', '%inventori%');
            })
            ->where('name', 'not like', '%transport%')
            ->where('name', 'not like', '%akomodasi%')
            ->where('name', 'not like', '%kasbon%')
            ->orderBy('name')
            ->value('id');

        if ($row) {
            return (int) $row;
        }

        return DB::table('purchase_requisition_categories')
            ->where('name', 'not like', '%transport%')
            ->where('name', 'not like', '%akomodasi%')
            ->where('name', 'not like', '%kasbon%')
            ->orderBy('name')
            ->value('id');
    }

    /**
     * Build PR Asset prefill payload from detail IDs.
     */
    public function buildPrPrefill(array $detailIds, $user): array
    {
        $detailIds = array_values(array_unique(array_filter(array_map('intval', $detailIds))));
        if (empty($detailIds)) {
            throw new \InvalidArgumentException('Pilih minimal satu baris item.');
        }

        $rows = $this->pendingDetailRows(['search' => null], $user);
        $byId = collect($rows)->keyBy('detail_id');
        $lines = [];

        foreach ($detailIds as $did) {
            $row = $byId->get($did);
            if (!$row) {
                throw new \InvalidArgumentException("Baris #{$did} tidak valid atau sudah terpenuhi penggantiannya.");
            }
            $lines[] = [
                'lost_breakage_detail_id' => (int) $row->detail_id,
                'header_number' => $row->header_number,
                'header_date' => $row->header_date,
                'type' => $row->type,
                'item_id' => (int) $row->item_id,
                'item_name' => $row->item_name,
                'sku' => $row->sku,
                'unit_id' => (int) $row->unit_id,
                'unit_name' => $row->unit_name,
                'qty_remaining' => (float) $row->qty_remaining,
                'owner_outlet_id' => (int) $row->owner_outlet_id,
                'owner_outlet_name' => $row->owner_outlet_name,
                'location_outlet_id' => (int) $row->outlet_id,
                'location_outlet_name' => $row->location_outlet_name,
                'warehouse_outlet_name' => $row->warehouse_outlet_name,
                'asset' => $this->assetItemSnapshot((int) $row->item_id, $row->item_name, $row->sku, (int) $row->unit_id, $row->unit_name),
            ];
        }

        $categoryId = $this->resolveDefaultAssetPrCategoryId();
        $lbNumbers = collect($lines)->pluck('header_number')->filter()->unique()->values();
        $numbersForTitle = $lbNumbers->take(3)->implode(', ');
        $moreTitle = $lbNumbers->count() > 3 ? '…' : '';

        $numbersForDesc = $lbNumbers->take(10)->implode(', ');
        if ($lbNumbers->count() > 10) {
            $numbersForDesc .= ' … (+' . ($lbNumbers->count() - 10) . ' dokumen)';
        }

        return [
            'title' => 'Pengganti L&B — ' . $numbersForTitle . $moreTitle,
            'description' => 'PR Asset untuk penggantian Lost & Breakage. No. L&B: '
                . ($numbersForDesc ?: '-')
                . '. Baris detail: ' . implode(', ', $detailIds),
            'default_category_id' => $categoryId,
            'lines' => $lines,
        ];
    }

    private function assetItemSnapshot(int $itemId, string $name, ?string $sku, int $unitId, ?string $unitName): array
    {
        $row = DB::table('items as i')
            ->join('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('units as su', 'i.small_unit_id', '=', 'su.id')
            ->leftJoin('units as mu', 'i.medium_unit_id', '=', 'mu.id')
            ->leftJoin('units as lu', 'i.large_unit_id', '=', 'lu.id')
            ->where('i.id', $itemId)
            ->select(
                'i.id',
                'i.name',
                'i.sku',
                'c.name as category_name',
                'i.small_unit_id',
                'i.medium_unit_id',
                'i.large_unit_id',
                'su.name as small_unit_name',
                'mu.name as medium_unit_name',
                'lu.name as large_unit_name'
            )
            ->first();

        if ($row) {
            $image = DB::table('item_images')->where('item_id', $itemId)->value('path');

            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'category_name' => $row->category_name,
                'small_unit_id' => $row->small_unit_id,
                'medium_unit_id' => $row->medium_unit_id,
                'large_unit_id' => $row->large_unit_id,
                'small_unit_name' => $row->small_unit_name,
                'medium_unit_name' => $row->medium_unit_name,
                'large_unit_name' => $row->large_unit_name,
                'image' => $image,
            ];
        }

        return [
            'id' => $itemId,
            'name' => $name,
            'sku' => $sku,
            'category_name' => null,
            'small_unit_id' => $unitId,
            'medium_unit_id' => null,
            'large_unit_id' => null,
            'small_unit_name' => $unitName,
            'medium_unit_name' => null,
            'large_unit_name' => null,
            'image' => null,
        ];
    }

    public function linkPrItems(int $purchaseRequisitionId, array $items): void
    {
        if (!$this->prLinesTableExists()) {
            return;
        }

        foreach ($items as $item) {
            $detailId = isset($item['lost_breakage_detail_id']) ? (int) $item['lost_breakage_detail_id'] : 0;
            if ($detailId < 1) {
                continue;
            }

            $detail = DB::table('lost_breakage_details as d')
                ->join('lost_breakage_headers as h', 'd.header_id', '=', 'h.id')
                ->where('d.id', $detailId)
                ->where('h.status', 'APPROVED')
                ->select('d.id', 'd.qty', 'd.unit_id')
                ->first();

            if (!$detail) {
                throw new \InvalidArgumentException("Baris L&B #{$detailId} tidak ditemukan atau belum disetujui.");
            }

            $repSum = (float) DB::table('lost_breakage_replacements')
                ->where('detail_id', $detailId)
                ->sum('qty_replaced');
            $remaining = (float) $detail->qty - $repSum;
            $qtyPlanned = (float) ($item['qty'] ?? 0);

            if ($qtyPlanned > $remaining + 1e-6) {
                throw new \InvalidArgumentException(
                    "Qty PR melebihi sisa penggantian baris #{$detailId} (sisa " . round(max(0, $remaining), 4) . ').'
                );
            }

            $prItemId = isset($item['purchase_requisition_item_id']) ? (int) $item['purchase_requisition_item_id'] : null;

            DB::table('lost_breakage_pr_lines')->insert([
                'lost_breakage_detail_id' => $detailId,
                'purchase_requisition_id' => $purchaseRequisitionId,
                'purchase_requisition_item_id' => $prItemId,
                'qty_planned' => $qtyPlanned,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * After GR item saved: record replacement from linked PR line.
     */
    public function recordReplacementFromGrItem(
        int $assetGoodReceiveId,
        int $assetGoodReceiveItemId,
        int $poItemId,
        float $qtyReceived,
        int $grUnitId,
        int $receivedItemId
    ): void {
        if (!$this->prLinesTableExists() || !$this->replacementsTableExists() || $qtyReceived < 1e-9) {
            return;
        }

        $poItem = DB::table('purchase_order_ops_items')->where('id', $poItemId)->first();
        if (!$poItem || !$poItem->pr_ops_item_id) {
            return;
        }

        $prLine = DB::table('lost_breakage_pr_lines')
            ->where('purchase_requisition_item_id', (int) $poItem->pr_ops_item_id)
            ->first();

        if (!$prLine) {
            return;
        }

        $detailId = (int) $prLine->lost_breakage_detail_id;
        $detail = DB::table('lost_breakage_details')->where('id', $detailId)->first();
        if (!$detail) {
            return;
        }

        if ((int) $grUnitId !== (int) $detail->unit_id) {
            return;
        }

        $repSum = (float) DB::table('lost_breakage_replacements')
            ->where('detail_id', $detailId)
            ->sum('qty_replaced');
        $remaining = (float) $detail->qty - $repSum;
        $add = min($qtyReceived, max(0, $remaining));

        if ($add < 1e-9) {
            return;
        }

        $replacementItemId = null;
        if ((int) $receivedItemId !== (int) $detail->item_id) {
            $replacementItemId = (int) $receivedItemId;
        }

        $userId = Auth::id() ?? 0;
        $note = 'Otomatis dari GR Asset #' . $assetGoodReceiveId;

        $insert = [
            'detail_id' => $detailId,
            'qty_replaced' => $add,
            'unit_id' => (int) $detail->unit_id,
            'replacement_item_id' => $replacementItemId,
            'note' => $note,
            'replaced_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('lost_breakage_replacements', 'source')) {
            $insert['source'] = 'asset_gr';
            $insert['asset_good_receive_id'] = $assetGoodReceiveId;
            $insert['asset_good_receive_item_id'] = $assetGoodReceiveItemId;
            $insert['purchase_requisition_id'] = (int) $prLine->purchase_requisition_id;
        }

        DB::table('lost_breakage_replacements')->insert($insert);
    }
}
