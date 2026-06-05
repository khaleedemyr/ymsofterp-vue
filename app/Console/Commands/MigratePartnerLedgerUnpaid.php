<?php

namespace App\Console\Commands;

use App\Models\FoodPayment;
use App\Models\NonFoodPayment;
use App\Models\OutletPayment;
use App\Services\PartnerLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MigratePartnerLedgerUnpaid extends Command
{
    protected $signature = 'partner-ledger:migrate-unpaid {--dry-run : Preview tanpa menulis data}';

    protected $description = 'Migrasi hutang (NFP/FP approved) dan piutang (outlet payment pending) ke partner ledger';

    public function handle(PartnerLedgerService $partnerLedger): int
    {
        if (! Schema::hasTable('partner_sub_ledgers')) {
            $this->error('Tabel partner_sub_ledgers belum ada. Jalankan database/sql/create_partner_ledger_tables.sql terlebih dahulu.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Mode dry-run: tidak ada data yang ditulis.');
        }

        $stats = ['nfp' => 0, 'fp' => 0, 'outlet' => 0, 'skipped' => 0, 'errors' => 0];

        NonFoodPayment::query()
            ->where('status', 'approved')
            ->whereNotNull('supplier_id')
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($partnerLedger, $dryRun, &$stats) {
                foreach ($payments as $payment) {
                    if ($dryRun) {
                        $stats['nfp']++;
                        $this->line("NFP #{$payment->id} {$payment->payment_number} — Rp ".number_format((float) $payment->amount, 0, ',', '.'));

                        continue;
                    }

                    try {
                        $entry = $partnerLedger->accruePayableFromNonFoodPayment($payment);
                        $entry ? $stats['nfp']++ : $stats['skipped']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $this->error("NFP #{$payment->id}: {$e->getMessage()}");
                    }
                }
            });

        FoodPayment::query()
            ->where('status', 'approved')
            ->whereNotNull('supplier_id')
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($partnerLedger, $dryRun, &$stats) {
                foreach ($payments as $payment) {
                    if ($dryRun) {
                        $stats['fp']++;
                        $this->line("FP #{$payment->id} {$payment->number} — Rp ".number_format((float) $payment->total, 0, ',', '.'));

                        continue;
                    }

                    try {
                        $entry = $partnerLedger->accruePayableFromFoodPayment($payment);
                        $entry ? $stats['fp']++ : $stats['skipped']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $this->error("FP #{$payment->id}: {$e->getMessage()}");
                    }
                }
            });

        OutletPayment::query()
            ->where('status', 'pending')
            ->whereNotNull('outlet_id')
            ->orderBy('id')
            ->chunkById(100, function ($payments) use ($partnerLedger, $dryRun, &$stats) {
                foreach ($payments as $payment) {
                    if ($dryRun) {
                        $stats['outlet']++;
                        $this->line("Outlet Payment #{$payment->id} — outlet {$payment->outlet_id} — Rp ".number_format((float) $payment->total_amount, 0, ',', '.'));

                        continue;
                    }

                    try {
                        $entry = $partnerLedger->accrueReceivableFromOutletPayment($payment);
                        $entry ? $stats['outlet']++ : $stats['skipped']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $this->error("Outlet Payment #{$payment->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info('Selesai.');
        $this->table(
            ['Jenis', 'Jumlah'],
            [
                ['NFP accrual', $stats['nfp']],
                ['FP accrual', $stats['fp']],
                ['Outlet piutang accrual', $stats['outlet']],
                ['Skipped (sudah ada)', $stats['skipped']],
                ['Errors', $stats['errors']],
            ]
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
