<?php

namespace App\Console\Commands;

use App\Http\Controllers\StockCutController;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Normalisasi historis last stock outlet yang qty_small < 0 ke 0 (kartu IN balancing).
 */
class NormalizeNegativeOutletStocksCommand extends Command
{
    protected $signature = 'stock-cut:normalize-negative-stocks
                            {--outlet= : Optional outlet id}
                            {--user= : User id sebagai eksekutor (default: first superadmin-like / id 1)}';

    protected $description = 'Balance semua outlet stock qty_small < 0 ke 0 + kartu IN + tutup variance open';

    public function handle(): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : 1;
        $user = User::find($userId);
        if (! $user) {
            $this->error("User #{$userId} tidak ditemukan.");

            return self::FAILURE;
        }

        $outletId = $this->option('outlet') !== null && $this->option('outlet') !== ''
            ? (int) $this->option('outlet')
            : null;

        $this->info('Scanning outlet stocks dengan qty_small < 0'
            .($outletId ? " (outlet {$outletId})" : ' (semua outlet)').'...');

        /** @var StockCutController $controller */
        $controller = app(StockCutController::class);
        $result = $controller->runNormalizeNegativeOutletStocks($user, $outletId);

        $this->info("Scanned: {$result['scanned']}");
        $this->info("Normalized: {$result['normalized']}");
        $this->info("Skipped: {$result['skipped']}");
        $this->info("Variances closed: {$result['closed_variances']}");

        return self::SUCCESS;
    }
}
