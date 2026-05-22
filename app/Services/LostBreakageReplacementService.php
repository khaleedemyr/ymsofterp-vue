<?php

namespace App\Services;

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
                'oo.nama_outlet as owner_outlet_name',
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

        return $query
            ->orderByDesc('h.date')
            ->orderByDesc('d.id')
            ->limit(500)
            ->get()
            ->all();
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
        $ownerIds = [];

        foreach ($detailIds as $did) {
            $row = $byId->get($did);
            if (!$row) {
                throw new \InvalidArgumentException("Baris #{$did} tidak valid atau sudah terpenuhi penggantiannya.");
            }
            $ownerIds[(int) $row->owner_outlet_id] = true;
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
            ];
        }

        if (count($ownerIds) > 1 && $user && (int) ($user->id_outlet ?? 0) !== 1) {
            throw new \InvalidArgumentException('Semua baris harus dari pemilik outlet yang sama.');
        }

        $categoryId = $this->resolveDefaultAssetPrCategoryId();
        $numbers = collect($lines)->pluck('header_number')->unique()->take(3)->implode(', ');
        $more = count($lines) > 3 ? '…' : '';

        return [
            'title' => 'Pengganti L&B — ' . $numbers . $more,
            'description' => 'PR Asset untuk penggantian Lost & Breakage. Baris: ' . implode(', ', $detailIds),
            'default_category_id' => $categoryId,
            'lines' => $lines,
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
