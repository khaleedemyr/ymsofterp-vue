<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillItemSupplierOutletsCommand extends Command
{
    protected $signature = 'item-supplier:backfill-outlets
                            {--by-supplier : Salin outlet yang sudah dipakai supplier lain ke semua item supplier supplier yang sama}
                            {--from-ro-history : Buat mapping dari riwayat RO Supplier (food_floor_order_supplier_items)}
                            {--outlet= : Batasi ke id_outlet tertentu (bisa dipisah koma)}
                            {--dry-run : Tampilkan rencana tanpa menulis ke database}';

    protected $description = 'Lengkapi mapping item_supplier_outlet yang hilang (backfill RO Supplier)';

    public function handle(): int
    {
        if (! Schema::hasTable('item_supplier') || ! Schema::hasTable('item_supplier_outlet')) {
            $this->error('Tabel item_supplier / item_supplier_outlet tidak ditemukan.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $outletFilter = $this->parseOutletFilter();

        $bySupplier = (bool) $this->option('by-supplier');
        $fromRo = (bool) $this->option('from-ro-history');
        if (! $bySupplier && ! $fromRo) {
            $bySupplier = true;
            $fromRo = true;
        }

        $insertedOutlets = 0;
        $createdRows = 0;

        if ($bySupplier) {
            $insertedOutlets += $this->propagateOutletsBySupplier($dryRun, $outletFilter);
        }

        if ($fromRo) {
            $createdRows += $this->createFromRoSupplierHistory($dryRun, $outletFilter);
            $createdRows += $this->createFromRoOrderItems($dryRun, $outletFilter);
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Selesai. Outlet ditambahkan: {$insertedOutlets}, item_supplier baru: {$createdRows}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>|null
     */
    private function parseOutletFilter(): ?array
    {
        $raw = trim((string) $this->option('outlet'));
        if ($raw === '') {
            return null;
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    private function propagateOutletsBySupplier(bool $dryRun, ?array $outletFilter): int
    {
        $this->info('Mode: propagate outlet per supplier...');
        $inserted = 0;

        $supplierIds = DB::table('item_supplier')->distinct()->pluck('supplier_id');

        foreach ($supplierIds as $supplierId) {
            $canonicalOutlets = DB::table('item_supplier as is')
                ->join('item_supplier_outlet as iso', 'is.id', '=', 'iso.item_supplier_id')
                ->where('is.supplier_id', $supplierId)
                ->distinct()
                ->pluck('iso.outlet_id');

            if ($outletFilter !== null) {
                $canonicalOutlets = $canonicalOutlets->intersect($outletFilter)->values();
            }

            if ($canonicalOutlets->isEmpty()) {
                continue;
            }

            $itemSupplierIds = DB::table('item_supplier')
                ->where('supplier_id', $supplierId)
                ->pluck('id');

            foreach ($itemSupplierIds as $itemSupplierId) {
                $existing = DB::table('item_supplier_outlet')
                    ->where('item_supplier_id', $itemSupplierId)
                    ->pluck('outlet_id');

                foreach ($canonicalOutlets as $outletId) {
                    if ($existing->contains($outletId)) {
                        continue;
                    }

                    $itemId = DB::table('item_supplier')->where('id', $itemSupplierId)->value('item_id');
                    $itemName = DB::table('items')->where('id', $itemId)->value('name');
                    $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
                    $this->line("  + IS#{$itemSupplierId} {$itemName} → outlet {$outletId} ({$outletName})");

                    if (! $dryRun) {
                        DB::table('item_supplier_outlet')->insert([
                            'item_supplier_id' => $itemSupplierId,
                            'outlet_id' => $outletId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $inserted++;
                }
            }
        }

        return $inserted;
    }

    private function createFromRoSupplierHistory(bool $dryRun, ?array $outletFilter): int
    {
        if (! Schema::hasTable('food_floor_order_supplier_items')) {
            $this->warn('Tabel food_floor_order_supplier_items tidak ada, lewati from-ro-history.');

            return 0;
        }

        $this->info('Mode: buat mapping dari riwayat RO Supplier...');
        $created = 0;

        $historyQuery = DB::table('food_floor_order_supplier_items as fsi')
            ->join('food_floor_orders as fo', 'fo.id', '=', 'fsi.floor_order_id')
            ->where('fo.fo_mode', 'RO Supplier')
            ->whereNotNull('fsi.item_id')
            ->select(
                'fsi.item_id',
                'fo.id_outlet as outlet_id',
                DB::raw('MAX(fsi.supplier_id) as supplier_id'),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('fsi.item_id', 'fo.id_outlet');

        if ($outletFilter !== null) {
            $historyQuery->whereIn('fo.id_outlet', $outletFilter);
        }

        $history = $historyQuery->get();

        foreach ($history as $row) {
            if (empty($row->item_id) || empty($row->outlet_id)) {
                continue;
            }

            $supplierId = (int) ($row->supplier_id ?: 0);
            if ($supplierId <= 0) {
                $supplierId = (int) ($this->supplierFromPurchaseOrder((int) $row->item_id) ?? 0);
            }
            if ($supplierId <= 0) {
                continue;
            }

            if ($this->mappingExists((int) $row->item_id, (int) $row->outlet_id)) {
                continue;
            }

            $itemName = DB::table('items')->where('id', $row->item_id)->value('name');
            $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $row->outlet_id)->value('nama_outlet');
            $supplierName = DB::table('suppliers')->where('id', $supplierId)->value('name');

            $this->line("  * buat mapping {$itemName} / {$supplierName} → {$outletName}");

            if ($dryRun) {
                $created++;

                continue;
            }

            $created += $this->ensureItemSupplierMapping(
                (int) $row->item_id,
                $supplierId,
                (int) $row->outlet_id,
                false
            ) > 0 ? 1 : 0;
        }

        return $created;
    }

    private function createFromRoOrderItems(bool $dryRun, ?array $outletFilter): int
    {
        if (! Schema::hasTable('food_floor_order_items') || ! Schema::hasTable('food_floor_orders')) {
            return 0;
        }

        $this->info('Mode: buat mapping dari item RO Supplier (food_floor_order_items)...');
        $created = 0;

        $query = DB::table('food_floor_order_items as foi')
            ->join('food_floor_orders as fo', 'fo.id', '=', 'foi.floor_order_id')
            ->where('fo.fo_mode', 'RO Supplier')
            ->whereNotNull('foi.item_id')
            ->select('foi.item_id', 'fo.id_outlet as outlet_id')
            ->distinct();

        if ($outletFilter !== null) {
            $query->whereIn('fo.id_outlet', $outletFilter);
        }

        foreach ($query->get() as $row) {
            if ($this->mappingExists((int) $row->item_id, (int) $row->outlet_id)) {
                continue;
            }

            $supplierId = (int) ($this->supplierFromPurchaseOrder((int) $row->item_id) ?? 0);
            if ($supplierId <= 0) {
                continue;
            }

            $created += $this->ensureItemSupplierMapping(
                (int) $row->item_id,
                $supplierId,
                (int) $row->outlet_id,
                $dryRun
            );
        }

        return $created;
    }

    private function ensureItemSupplierMapping(int $itemId, int $supplierId, int $outletId, bool $dryRun): int
    {
        $itemName = DB::table('items')->where('id', $itemId)->value('name');
        $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->value('nama_outlet');
        $supplierName = DB::table('suppliers')->where('id', $supplierId)->value('name');
        $this->line("  * buat mapping {$itemName} / {$supplierName} → {$outletName}");

        if ($dryRun) {
            return 1;
        }

        $created = 0;
        $itemSupplierId = DB::table('item_supplier')
            ->where('item_id', $itemId)
            ->where('supplier_id', $supplierId)
            ->value('id');

        if (! $itemSupplierId) {
            $itemSupplierId = DB::table('item_supplier')->insertGetId([
                'supplier_id' => $supplierId,
                'item_id' => $itemId,
                'unit_id' => $this->resolveUnitIdForItem($itemId),
                'price' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created = 1;
        }

        $existsLink = DB::table('item_supplier_outlet')
            ->where('item_supplier_id', $itemSupplierId)
            ->where('outlet_id', $outletId)
            ->exists();

        if (! $existsLink) {
            DB::table('item_supplier_outlet')->insert([
                'item_supplier_id' => $itemSupplierId,
                'outlet_id' => $outletId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $created;
    }

    private function mappingExists(int $itemId, int $outletId): bool
    {
        return DB::table('item_supplier_outlet as iso')
            ->join('item_supplier as is', 'is.id', '=', 'iso.item_supplier_id')
            ->where('is.item_id', $itemId)
            ->where('iso.outlet_id', $outletId)
            ->exists();
    }

    private function resolveUnitIdForItem(int $itemId): ?int
    {
        $existing = DB::table('item_supplier')
            ->where('item_id', $itemId)
            ->whereNotNull('unit_id')
            ->value('unit_id');
        if ($existing) {
            return (int) $existing;
        }

        $item = DB::table('items')->where('id', $itemId)->first(['small_unit_id', 'medium_unit_id', 'large_unit_id']);
        if (! $item) {
            return null;
        }

        foreach (['medium_unit_id', 'small_unit_id', 'large_unit_id'] as $col) {
            if (! empty($item->{$col})) {
                return (int) $item->{$col};
            }
        }

        return null;
    }

    private function supplierFromPurchaseOrder(int $itemId): ?int
    {
        if (! Schema::hasTable('purchase_order_food_items') || ! Schema::hasTable('purchase_order_food')) {
            return null;
        }

        $supplierId = DB::table('purchase_order_food_items as poi')
            ->join('purchase_order_food as po', 'poi.purchase_order_food_id', '=', 'po.id')
            ->where('poi.item_id', $itemId)
            ->whereNotNull('po.supplier_id')
            ->select('po.supplier_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('po.supplier_id')
            ->orderByDesc('cnt')
            ->value('supplier_id');

        return $supplierId ? (int) $supplierId : null;
    }
}
