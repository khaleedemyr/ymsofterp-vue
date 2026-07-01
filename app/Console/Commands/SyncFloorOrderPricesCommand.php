<?php

namespace App\Console\Commands;

use App\Support\FloorOrderPriceAuditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncFloorOrderPricesCommand extends Command
{
    protected $signature = 'floor-order:sync-prices
                            {--from=2026-06-12 : Tanggal FO awal (Y-m-d)}
                            {--to= : Tanggal FO akhir (Y-m-d), kosong = hari ini}
                            {--all-statuses : Sertakan semua status FO, bukan hanya draft/submitted}
                            {--apply : Tulis perbaikan ke database (default: dry-run)}
                            {--force : Terapkan tanpa konfirmasi (pakai dengan --apply)}
                            {--items-csv= : Batasi ke item_id dari file CSV (kolom item_id)}
                            {--csv= : Path file CSV hasil selisih}
                            {--limit=40 : Jumlah baris preview di console}';

    protected $description = 'Trace & perbaiki harga food_floor_order_items vs item_prices (dengan konversi UoM)';

    public function handle(FloorOrderPriceAuditor $auditor): int
    {
        $from = (string) $this->option('from');
        $to = $this->option('to') ? (string) $this->option('to') : null;
        $allStatuses = (bool) $this->option('all-statuses');
        $apply = (bool) $this->option('apply');
        $csvPath = $this->option('csv') ? (string) $this->option('csv') : null;
        $previewLimit = max(1, (int) $this->option('limit'));
        $itemIds = $this->loadItemIdsFromCsv($this->option('items-csv') ? (string) $this->option('items-csv') : null);

        $this->info('=== Floor Order Price Sync ===');
        $this->line('Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN'));
        $this->line('Periode FO: ' . $from . ($to ? " s/d {$to}" : ' s/d hari ini'));
        $this->line('Status: ' . ($allStatuses ? 'semua' : 'draft, submitted'));
        if ($itemIds !== null) {
            $this->line('Filter item: ' . count($itemIds) . ' item dari CSV');
        }
        $this->newLine();

        $result = $auditor->scan($from, $to, $allStatuses, $itemIds);
        $mismatches = $result['mismatches'];

        $this->line('Baris di-scan: ' . $result['rows_scanned']);
        $this->line('Cocok: ' . $result['matched']);
        $this->line('Tanpa harga master: ' . $result['skipped_no_price']);
        $this->warn('Selisih: ' . count($mismatches) . ' baris, ' . count($result['summary_by_item']) . ' item');

        if ($result['summary_by_item'] !== []) {
            $this->newLine();
            $this->info('Top item selisih:');
            $rows = array_slice($result['summary_by_item'], 0, 20);
            $this->table(
                ['ID', 'Item', 'Baris', 'FO harga', 'Seharusnya'],
                array_map(function (array $s) {
                    $cur = [];
                    foreach ($s['current_prices'] as $p => $c) {
                        $cur[] = number_format((float) $p, 0, ',', '.') . " x{$c}";
                    }
                    $exp = [];
                    foreach ($s['expected_prices'] as $p => $c) {
                        $exp[] = number_format((float) $p, 0, ',', '.') . " x{$c}";
                    }

                    return [
                        $s['item_id'],
                        mb_substr($s['item_name'], 0, 28),
                        $s['mismatch_rows'],
                        implode(', ', $cur),
                        implode(', ', $exp),
                    ];
                }, $rows)
            );
        }

        if ($mismatches !== []) {
            $this->newLine();
            $this->info('Sample baris:');
            foreach (array_slice($mismatches, 0, $previewLimit) as $m) {
                $this->line("[{$m['status']}] {$m['tanggal']} {$m['order_number']} | {$m['outlet']}");
                $this->line("  {$m['item_name']} | unit={$m['unit']} ({$m['unit_tier']})");
                $this->line('  ' . number_format($m['current_price'], 0, ',', '.')
                    . ' → ' . number_format($m['expected_price'], 0, ',', '.')
                    . " (large=" . number_format($m['price_large'], 0, ',', '.') . ", mode={$m['pricing_mode']})");
            }
            if (count($mismatches) > $previewLimit) {
                $this->line('... dan ' . (count($mismatches) - $previewLimit) . ' baris lagi');
            }
        }

        if ($csvPath && $mismatches !== []) {
            $fp = fopen($csvPath, 'w');
            if ($fp) {
                fputcsv($fp, array_keys($mismatches[0]));
                foreach ($mismatches as $m) {
                    fputcsv($fp, array_values($m));
                }
                fclose($fp);
                $this->info("CSV: {$csvPath}");
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->comment('Dry-run selesai. Tambahkan --apply untuk update harga FO.');

            return self::SUCCESS;
        }

        if ($mismatches === []) {
            $this->info('Tidak ada baris yang perlu diperbaiki.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Perbaiki ' . count($mismatches) . ' baris FO?', true)) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $stats = $auditor->applyFixes($mismatches);
            DB::commit();
            $this->info("Selesai. Baris diupdate: {$stats['updated']}, FO total dihitung ulang: {$stats['orders_recalculated']}");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return list<int>|null */
    private function loadItemIdsFromCsv(?string $path): ?array
    {
        if (! $path || ! is_readable($path)) {
            return null;
        }

        $fp = fopen($path, 'r');
        if (! $fp) {
            return null;
        }

        $header = fgetcsv($fp);
        if (! $header) {
            fclose($fp);

            return null;
        }

        $idIdx = array_search('item_id', $header, true);
        if ($idIdx === false) {
            $idIdx = 0;
        }

        $ids = [];
        while (($row = fgetcsv($fp)) !== false) {
            if (! isset($row[$idIdx]) || $row[$idIdx] === '') {
                continue;
            }
            $ids[] = (int) $row[$idIdx];
        }
        fclose($fp);

        return array_values(array_unique(array_filter($ids)));
    }
}
