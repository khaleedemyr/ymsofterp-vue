<?php

namespace App\Services;

use App\Models\BankBook;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\FoodPayment;
use App\Models\Jurnal;
use App\Models\JurnalGlobal;
use App\Models\NonFoodPayment;
use App\Models\OutletPayment;
use App\Models\PartnerLedgerEntry;
use App\Models\PartnerSubLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PartnerLedgerService
{
    public function accruePayableFromNonFoodPayment(NonFoodPayment $payment): ?PartnerLedgerEntry
    {
        if ($payment->status !== 'approved' || ! $payment->supplier_id) {
            return null;
        }

        if ($this->hasEntry('non_food_payment', $payment->id, 'accrual')) {
            return null;
        }

        $amount = (float) ($payment->amount ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $tanggal = $this->resolveDate($payment->approved_at ?? $payment->payment_date ?? now());
        $description = 'Accrual hutang NFP: '.($payment->payment_number ?? $payment->id);

        return DB::transaction(function () use ($payment, $amount, $tanggal, $description) {
            $subLedger = $this->ensureSubLedger('payable', 'supplier', (int) $payment->supplier_id);
            $jurnalMeta = $this->createPayableAccrualJurnal(
                $payment->coa_id ? (int) $payment->coa_id : null,
                $amount,
                $tanggal,
                $description,
                'non_food_payment',
                $payment->id,
                $this->resolveNonFoodPaymentOutletId($payment)
            );

            return $this->createEntry(
                $subLedger,
                'accrual',
                $amount,
                $tanggal,
                $description,
                'non_food_payment',
                $payment->id,
                $jurnalMeta
            );
        });
    }

    public function accruePayableFromFoodPayment(FoodPayment $payment): ?PartnerLedgerEntry
    {
        if ($payment->status !== 'approved' || ! $payment->supplier_id) {
            return null;
        }

        if ($this->hasEntry('food_payment', $payment->id, 'accrual')) {
            return null;
        }

        $amount = (float) ($payment->total ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $tanggal = $this->resolveDate($payment->gm_finance_approved_at ?? $payment->date ?? now());
        $description = 'Accrual hutang FP: '.($payment->number ?? $payment->id);

        return DB::transaction(function () use ($payment, $amount, $tanggal, $description) {
            $subLedger = $this->ensureSubLedger('payable', 'supplier', (int) $payment->supplier_id);

            if (! $payment->relationLoaded('paymentOutlets')) {
                $payment->load('paymentOutlets');
            }

            $jurnalMeta = $this->createFoodPaymentAccrualJurnal($payment, $tanggal, $description);

            return $this->createEntry(
                $subLedger,
                'accrual',
                $amount,
                $tanggal,
                $description,
                'food_payment',
                $payment->id,
                $jurnalMeta
            );
        });
    }

    public function accrueReceivableFromOutletPayment(OutletPayment $payment): ?PartnerLedgerEntry
    {
        if ($payment->status !== 'pending' || ! $payment->outlet_id) {
            return null;
        }

        if ($this->hasEntry('outlet_payment', $payment->id, 'accrual')) {
            return null;
        }

        $amount = (float) ($payment->total_amount ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $tanggal = $this->resolveDate($payment->date ?? now());
        $description = 'Accrual piutang Outlet Payment: '.($payment->payment_number ?? $payment->id);

        return DB::transaction(function () use ($payment, $amount, $tanggal, $description) {
            $subLedger = $this->ensureSubLedger('receivable', 'outlet', (int) $payment->outlet_id);
            $jurnalMeta = $this->createReceivableAccrualJurnal($payment, $tanggal, $description);

            return $this->createEntry(
                $subLedger,
                'accrual',
                $amount,
                $tanggal,
                $description,
                'outlet_payment',
                $payment->id,
                $jurnalMeta
            );
        });
    }

    public function settlePayableFromNonFoodPayment(NonFoodPayment $payment): ?PartnerLedgerEntry
    {
        return $this->settle('non_food_payment', $payment->id, 'payable', 'supplier', (int) $payment->supplier_id, (float) $payment->amount, 'Pelunasan hutang NFP: '.($payment->payment_number ?? $payment->id), $payment->payment_date);
    }

    public function settlePayableFromFoodPayment(FoodPayment $payment): ?PartnerLedgerEntry
    {
        return $this->settle('food_payment', $payment->id, 'payable', 'supplier', (int) $payment->supplier_id, (float) $payment->total, 'Pelunasan hutang FP: '.($payment->number ?? $payment->id), $payment->date);
    }

    public function settleReceivableFromOutletPayment(OutletPayment $payment): ?PartnerLedgerEntry
    {
        return $this->settle('outlet_payment', $payment->id, 'receivable', 'outlet', (int) $payment->outlet_id, (float) $payment->total_amount, 'Pelunasan piutang Outlet Payment: '.($payment->payment_number ?? $payment->id), $payment->date);
    }

    public function reverseAccrual(string $sourceType, int $sourceId): void
    {
        $accrual = PartnerLedgerEntry::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('entry_type', 'accrual')
            ->first();

        if (! $accrual) {
            return;
        }

        if ($this->hasEntry($sourceType, $sourceId, 'reversal')) {
            return;
        }

        DB::transaction(function () use ($accrual, $sourceType, $sourceId) {
            $subLedger = PartnerSubLedger::lockForUpdate()->find($accrual->sub_ledger_id);
            if (! $subLedger) {
                return;
            }

            $reversalAmount = -1 * (float) $accrual->amount;
            $subLedger->balance = round((float) $subLedger->balance + $reversalAmount, 2);
            $subLedger->save();

            PartnerLedgerEntry::create([
                'sub_ledger_id' => $subLedger->id,
                'entry_type' => 'reversal',
                'amount' => $reversalAmount,
                'entry_date' => now()->toDateString(),
                'description' => 'Reversal accrual '.$sourceType.' #'.$sourceId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function recordManualSettlement(
        PartnerSubLedger $subLedger,
        float $amount,
        string $entryDate,
        int $bankAccountId,
        ?string $description = null
    ): PartnerLedgerEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Jumlah pelunasan harus lebih dari 0.');
        }

        $bankAccount = BankAccount::find($bankAccountId);
        if (! $bankAccount || ! $bankAccount->coa_id) {
            throw new \InvalidArgumentException('Rekening bank tidak valid atau belum memiliki COA.');
        }

        return DB::transaction(function () use ($subLedger, $amount, $entryDate, $bankAccount, $description) {
            $locked = PartnerSubLedger::lockForUpdate()->findOrFail($subLedger->id);
            $balance = round((float) $locked->balance, 2);

            if ($balance <= 0) {
                throw new \RuntimeException('Saldo partner sudah lunas.');
            }

            if ($amount > $balance + 0.009) {
                throw new \RuntimeException(
                    'Jumlah pelunasan melebihi sisa saldo ('.number_format($balance, 0, ',', '.').').'
                );
            }

            $partnerLabel = $this->resolvePartnerLabel($locked);
            $defaultDesc = $locked->ledger_type === 'payable'
                ? "Pelunasan hutang manual: {$partnerLabel}"
                : "Penerimaan piutang manual: {$partnerLabel}";
            $keterangan = $description ?: $defaultDesc;
            $bankCoaId = (int) $bankAccount->coa_id;
            $outletId = (int) ($bankAccount->outlet_id ?: 1);

            if ($locked->ledger_type === 'payable') {
                $hutangCoaId = $this->getCoaHutangUsaha();
                if (! $hutangCoaId) {
                    throw new \RuntimeException('COA Hutang Usaha tidak ditemukan.');
                }
                $jurnalMeta = $this->postJurnalPair(
                    $hutangCoaId,
                    $bankCoaId,
                    $amount,
                    $entryDate,
                    $keterangan,
                    'partner_ledger_settlement',
                    $locked->id,
                    $outletId,
                    'partner_ledger_settlement'
                );
                $bankTxnType = 'debit';
            } else {
                $piutangCoaId = $this->getCoaPiutangUsaha();
                if (! $piutangCoaId) {
                    throw new \RuntimeException('COA Piutang Usaha tidak ditemukan.');
                }
                $jurnalMeta = $this->postJurnalPair(
                    $bankCoaId,
                    $piutangCoaId,
                    $amount,
                    $entryDate,
                    $keterangan,
                    'partner_ledger_settlement',
                    $locked->id,
                    $outletId,
                    'partner_ledger_settlement'
                );
                $bankTxnType = 'credit';
            }

            $bankBook = $this->createBankBookEntry(
                (int) $bankAccount->id,
                $entryDate,
                $bankTxnType,
                $amount,
                $keterangan,
                'partner_ledger_settlement',
                $locked->id
            );

            return $this->createEntry(
                $locked,
                'settlement',
                -1 * $amount,
                $entryDate,
                $keterangan,
                'manual_settlement',
                $bankBook->id,
                $jurnalMeta
            );
        });
    }

    public function canDeleteOpeningBalance(PartnerLedgerEntry $entry, ?PartnerSubLedger $subLedger = null): bool
    {
        if ($entry->entry_type !== 'opening_balance' || $entry->source_type !== 'manual') {
            return false;
        }

        $ledger = $subLedger ?? PartnerSubLedger::find($entry->sub_ledger_id);
        if (! $ledger) {
            return false;
        }

        return round((float) $ledger->balance, 2) >= round((float) $entry->amount, 2);
    }

    public function deleteOpeningBalance(PartnerLedgerEntry $entry): void
    {
        if (! $this->canDeleteOpeningBalance($entry)) {
            throw new \RuntimeException(
                'Saldo awal tidak bisa dihapus karena sudah ada pelunasan atau mutasi lain yang mengurangi saldo.'
            );
        }

        DB::transaction(function () use ($entry) {
            $locked = PartnerSubLedger::lockForUpdate()->findOrFail($entry->sub_ledger_id);
            $locked->balance = round((float) $locked->balance - (float) $entry->amount, 2);
            $locked->save();
            $entry->delete();
        });
    }

    public function recordOpeningBalance(string $ledgerType, string $partnerType, int $partnerId, float $amount, string $entryDate, ?string $description = null): PartnerLedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Saldo awal harus lebih dari 0.');
        }

        return DB::transaction(function () use ($ledgerType, $partnerType, $partnerId, $amount, $entryDate, $description) {
            $subLedger = $this->ensureSubLedger($ledgerType, $partnerType, $partnerId);

            if ($this->hasEntry('manual', $subLedger->id, 'opening_balance')) {
                throw new \RuntimeException('Saldo awal untuk partner ini sudah pernah dicatat.');
            }

            return $this->createEntry(
                $subLedger,
                'opening_balance',
                $amount,
                $entryDate,
                $description ?? 'Saldo awal manual',
                'manual',
                $subLedger->id,
                null
            );
        });
    }

    public function resolveOutletPaymentCoaAmounts(OutletPayment $payment): Collection
    {
        $coaAmounts = collect();

        if ($payment->coa_id) {
            $coaAmounts = collect([
                ['coa_id' => (int) $payment->coa_id, 'total_amount' => (float) $payment->total_amount],
            ]);
        } elseif ($payment->gr_id) {
            $coaAmounts = DB::table('outlet_food_good_receive_items as gri')
                ->join('outlet_food_good_receives as gr', 'gri.outlet_food_good_receive_id', '=', 'gr.id')
                ->join('delivery_orders as do', 'gr.delivery_order_id', '=', 'do.id')
                ->leftJoin('food_floor_order_items as foi', function ($join) {
                    $join->on('gri.item_id', '=', 'foi.item_id')
                        ->on('do.floor_order_id', '=', 'foi.floor_order_id');
                })
                ->join('items as i', 'gri.item_id', '=', 'i.id')
                ->join('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
                ->where('gri.outlet_food_good_receive_id', $payment->gr_id)
                ->whereNotNull('sc.coa_id')
                ->groupBy('sc.coa_id')
                ->select(
                    'sc.coa_id',
                    DB::raw('SUM(COALESCE(gri.received_qty * foi.price, 0)) as total_amount')
                )
                ->get();
        } elseif ($payment->retail_sales_id) {
            $coaAmounts = DB::table('retail_warehouse_sale_items as rwsi')
                ->join('items as i', 'rwsi.item_id', '=', 'i.id')
                ->join('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id')
                ->where('rwsi.retail_warehouse_sale_id', $payment->retail_sales_id)
                ->whereNotNull('sc.coa_id')
                ->groupBy('sc.coa_id')
                ->select(
                    'sc.coa_id',
                    DB::raw('SUM(COALESCE(rwsi.subtotal, 0)) as total_amount')
                )
                ->get();
        }

        return collect($coaAmounts)
            ->map(fn ($row) => (object) [
                'coa_id' => (int) data_get($row, 'coa_id'),
                'total_amount' => (float) data_get($row, 'total_amount'),
            ])
            ->filter(fn ($row) => $row->coa_id > 0 && $row->total_amount > 0)
            ->values();
    }

    protected function settle(
        string $sourceType,
        int $sourceId,
        string $ledgerType,
        string $partnerType,
        int $partnerId,
        float $amount,
        string $description,
        $entryDate = null
    ): ?PartnerLedgerEntry {
        if ($amount <= 0 || $partnerId <= 0) {
            return null;
        }

        if ($this->hasEntry($sourceType, $sourceId, 'settlement')) {
            return null;
        }

        $tanggal = $this->resolveDate($entryDate ?? now());

        return DB::transaction(function () use ($sourceType, $sourceId, $ledgerType, $partnerType, $partnerId, $amount, $description, $tanggal) {
            $subLedger = $this->ensureSubLedger($ledgerType, $partnerType, $partnerId);

            return $this->createEntry(
                $subLedger,
                'settlement',
                -1 * $amount,
                $tanggal,
                $description,
                $sourceType,
                $sourceId,
                null
            );
        });
    }

    protected function createEntry(
        PartnerSubLedger $subLedger,
        string $entryType,
        float $amount,
        string $entryDate,
        string $description,
        ?string $sourceType,
        ?int $sourceId,
        ?array $jurnalMeta
    ): PartnerLedgerEntry {
        $locked = PartnerSubLedger::lockForUpdate()->find($subLedger->id);
        $locked->balance = round((float) $locked->balance + $amount, 2);
        $locked->save();

        return PartnerLedgerEntry::create([
            'sub_ledger_id' => $locked->id,
            'entry_type' => $entryType,
            'amount' => $amount,
            'entry_date' => $entryDate,
            'description' => $description,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'jurnal_id' => $jurnalMeta['jurnal_id'] ?? null,
            'no_jurnal' => $jurnalMeta['no_jurnal'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    protected function ensureSubLedger(string $ledgerType, string $partnerType, int $partnerId): PartnerSubLedger
    {
        return PartnerSubLedger::firstOrCreate(
            [
                'ledger_type' => $ledgerType,
                'partner_type' => $partnerType,
                'partner_id' => $partnerId,
            ],
            ['balance' => 0]
        );
    }

    protected function hasEntry(string $sourceType, int $sourceId, string $entryType): bool
    {
        if (! Schema::hasTable('partner_ledger_entries')) {
            return false;
        }

        return PartnerLedgerEntry::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('entry_type', $entryType)
            ->exists();
    }

    protected function createPayableAccrualJurnal(
        ?int $expenseCoaId,
        float $amount,
        string $tanggal,
        string $keterangan,
        string $referenceType,
        int $referenceId,
        ?int $outletId = null
    ): ?array {
        if (! $expenseCoaId || $amount <= 0) {
            Log::warning('PartnerLedger: skip payable accrual jurnal, COA beban tidak tersedia', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            return null;
        }

        $hutangCoaId = $this->getCoaHutangUsaha();
        if (! $hutangCoaId) {
            Log::error('PartnerLedger: COA Hutang Usaha tidak ditemukan');

            return null;
        }

        return $this->postJurnalPair(
            $expenseCoaId,
            $hutangCoaId,
            $amount,
            $tanggal,
            $keterangan,
            $referenceType,
            $referenceId,
            $outletId,
            'partner_ledger_accrual'
        );
    }

    protected function createFoodPaymentAccrualJurnal(FoodPayment $payment, string $tanggal, string $keterangan): ?array
    {
        $hutangCoaId = $this->getCoaHutangUsaha();
        if (! $hutangCoaId) {
            return null;
        }

        $lines = $payment->paymentOutlets
            ->filter(fn ($op) => ! empty($op->coa_id) && (float) $op->amount > 0);

        if ($lines->isEmpty()) {
            Log::warning('PartnerLedger: skip FP accrual jurnal, tidak ada coa_id di payment outlets', [
                'food_payment_id' => $payment->id,
            ]);

            return null;
        }

        $noJurnal = Jurnal::generateNoJurnal();
        $firstJurnalId = null;

        foreach ($lines as $line) {
            $amount = (float) $line->amount;
            $jurnal = Jurnal::create([
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal,
                'keterangan' => $keterangan,
                'coa_debit_id' => (int) $line->coa_id,
                'coa_kredit_id' => $hutangCoaId,
                'jumlah_debit' => $amount,
                'jumlah_kredit' => $amount,
                'outlet_id' => $line->outlet_id ?? 1,
                'warehouse_id' => $line->warehouse_id ?? null,
                'reference_type' => 'food_payment',
                'reference_id' => $payment->id,
                'status' => 'posted',
                'created_by' => auth()->id() ?? 1,
            ]);

            JurnalGlobal::create([
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal,
                'keterangan' => $keterangan,
                'coa_debit_id' => (int) $line->coa_id,
                'coa_kredit_id' => $hutangCoaId,
                'jumlah_debit' => $amount,
                'jumlah_kredit' => $amount,
                'outlet_id' => $line->outlet_id ?? 1,
                'warehouse_id' => $line->warehouse_id ?? null,
                'source_module' => 'partner_ledger_accrual',
                'source_id' => $payment->id,
                'reference_type' => 'food_payment',
                'reference_id' => $payment->id,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id() ?? 1,
                'created_by' => auth()->id() ?? 1,
            ]);

            $firstJurnalId = $firstJurnalId ?? $jurnal->id;
        }

        return ['jurnal_id' => $firstJurnalId, 'no_jurnal' => $noJurnal];
    }

    protected function createReceivableAccrualJurnal(OutletPayment $payment, string $tanggal, string $keterangan): ?array
    {
        $piutangCoaId = $this->getCoaPiutangUsaha();
        if (! $piutangCoaId) {
            return null;
        }

        $coaAmounts = $this->resolveOutletPaymentCoaAmounts($payment);
        if ($coaAmounts->isEmpty()) {
            Log::warning('PartnerLedger: skip outlet piutang accrual jurnal, COA revenue tidak ditemukan', [
                'outlet_payment_id' => $payment->id,
            ]);

            return null;
        }

        $noJurnal = Jurnal::generateNoJurnal();
        $firstJurnalId = null;

        foreach ($coaAmounts as $coaAmount) {
            $amount = (float) $coaAmount->total_amount;
            $jurnal = Jurnal::create([
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal,
                'keterangan' => $keterangan.' - HO',
                'coa_debit_id' => $piutangCoaId,
                'coa_kredit_id' => (int) $coaAmount->coa_id,
                'jumlah_debit' => $amount,
                'jumlah_kredit' => $amount,
                'outlet_id' => 1,
                'warehouse_id' => $payment->warehouse_id,
                'reference_type' => 'outlet_payment',
                'reference_id' => $payment->id,
                'status' => 'posted',
                'created_by' => auth()->id() ?? 1,
            ]);

            JurnalGlobal::create([
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal,
                'keterangan' => $keterangan.' - HO',
                'coa_debit_id' => $piutangCoaId,
                'coa_kredit_id' => (int) $coaAmount->coa_id,
                'jumlah_debit' => $amount,
                'jumlah_kredit' => $amount,
                'outlet_id' => 1,
                'warehouse_id' => $payment->warehouse_id,
                'source_module' => 'partner_ledger_accrual',
                'source_id' => $payment->id,
                'reference_type' => 'outlet_payment',
                'reference_id' => $payment->id,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id() ?? 1,
                'created_by' => auth()->id() ?? 1,
            ]);

            $firstJurnalId = $firstJurnalId ?? $jurnal->id;
        }

        return ['jurnal_id' => $firstJurnalId, 'no_jurnal' => $noJurnal];
    }

    protected function postJurnalPair(
        int $coaDebitId,
        int $coaKreditId,
        float $amount,
        string $tanggal,
        string $keterangan,
        string $referenceType,
        int $referenceId,
        ?int $outletId,
        string $sourceModule
    ): array {
        $noJurnal = Jurnal::generateNoJurnal();

        $jurnal = Jurnal::create([
            'no_jurnal' => $noJurnal,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan,
            'coa_debit_id' => $coaDebitId,
            'coa_kredit_id' => $coaKreditId,
            'jumlah_debit' => $amount,
            'jumlah_kredit' => $amount,
            'outlet_id' => $outletId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => 'posted',
            'created_by' => auth()->id() ?? 1,
        ]);

        JurnalGlobal::create([
            'no_jurnal' => $noJurnal,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan,
            'coa_debit_id' => $coaDebitId,
            'coa_kredit_id' => $coaKreditId,
            'jumlah_debit' => $amount,
            'jumlah_kredit' => $amount,
            'outlet_id' => $outletId,
            'source_module' => $sourceModule,
            'source_id' => $referenceId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => auth()->id() ?? 1,
            'created_by' => auth()->id() ?? 1,
        ]);

        return ['jurnal_id' => $jurnal->id, 'no_jurnal' => $noJurnal];
    }

    protected function getCoaHutangUsaha(): ?int
    {
        return $this->resolveCoaId('2.1.1.01', 'Hutang Usaha');
    }

    protected function getCoaPiutangUsaha(): ?int
    {
        return $this->resolveCoaId('1.1.2.04', 'Piutang Usaha');
    }

    protected function resolveCoaId(string $code, string $name): ?int
    {
        $coa = ChartOfAccount::where('code', $code)->first()
            ?? ChartOfAccount::where('name', $name)->first();

        return $coa?->id;
    }

    protected function resolveDate($value): string
    {
        if (empty($value)) {
            return now()->toDateString();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return date('Y-m-d', strtotime((string) $value));
    }

    protected function createBankBookEntry(
        int $bankAccountId,
        string $transactionDate,
        string $transactionType,
        float $amount,
        string $description,
        string $referenceType,
        int $referenceId
    ): BankBook {
        $lastEntry = BankBook::where('bank_account_id', $bankAccountId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        $currentBalance = $lastEntry ? (float) $lastEntry->balance : 0;
        $newBalance = $transactionType === 'credit'
            ? $currentBalance + $amount
            : $currentBalance - $amount;

        $bankBook = BankBook::create([
            'bank_account_id' => $bankAccountId,
            'transaction_date' => $transactionDate,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'balance' => $newBalance,
            'created_by' => auth()->id(),
        ]);

        BankBook::recalculateBalance($bankAccountId, $transactionDate);

        return $bankBook;
    }

    protected function resolvePartnerLabel(PartnerSubLedger $ledger): string
    {
        if ($ledger->partner_type === 'supplier') {
            return (string) (DB::table('suppliers')->where('id', $ledger->partner_id)->value('name') ?? '#'.$ledger->partner_id);
        }

        return (string) (DB::table('tbl_data_outlet')->where('id_outlet', $ledger->partner_id)->value('nama_outlet') ?? '#'.$ledger->partner_id);
    }

    protected function resolveNonFoodPaymentOutletId(NonFoodPayment $payment): ?int
    {
        if (! $payment->relationLoaded('paymentOutlets')) {
            $payment->load('paymentOutlets');
        }

        $outletId = $payment->paymentOutlets->first()?->outlet_id;
        if ($outletId) {
            return (int) $outletId;
        }

        try {
            if ($payment->purchaseRequisition && $payment->purchaseRequisition->outlet_id) {
                return (int) $payment->purchaseRequisition->outlet_id;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return 1;
    }
}
