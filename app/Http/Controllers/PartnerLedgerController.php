<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\PartnerLedgerEntry;
use App\Models\PartnerSubLedger;
use App\Services\PartnerLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PartnerLedgerController extends Controller
{
    public function index(Request $request)
    {
        $ledgerType = $request->input('ledger_type', 'payable');

        $query = PartnerSubLedger::query()->where('ledger_type', $ledgerType);

        if ($request->filled('partner_id')) {
            $query->where('partner_id', (int) $request->partner_id);
        }

        $ledgers = $query->orderByDesc('balance')->paginate(25)->withQueryString();

        $partnerNames = $this->resolvePartnerNames($ledgers->getCollection(), $ledgerType);

        $ledgers->getCollection()->transform(function ($ledger) use ($partnerNames) {
            $key = $ledger->partner_type.'_'.$ledger->partner_id;
            $ledger->partner_name = $partnerNames[$key] ?? ('#'.$ledger->partner_id);

            return $ledger;
        });

        $summary = [
            'total_balance' => (float) PartnerSubLedger::where('ledger_type', $ledgerType)->sum('balance'),
            'partner_count' => PartnerSubLedger::where('ledger_type', $ledgerType)->where('balance', '>', 0)->count(),
        ];

        $suppliers = DB::table('suppliers')->orderBy('name')->select('id', 'name')->get();
        $outlets = DB::table('tbl_data_outlet')->orderBy('nama_outlet')->select('id_outlet as id', 'nama_outlet as name')->get();

        return Inertia::render('PartnerLedger/Index', [
            'ledgers' => $ledgers,
            'summary' => $summary,
            'filters' => [
                'ledger_type' => $ledgerType,
                'partner_id' => $request->input('partner_id', ''),
            ],
            'suppliers' => $suppliers,
            'outlets' => $outlets,
        ]);
    }

    public function show(PartnerSubLedger $partnerSubLedger, PartnerLedgerService $partnerLedger)
    {
        $entries = PartnerLedgerEntry::where('sub_ledger_id', $partnerSubLedger->id)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->through(function ($entry) use ($partnerSubLedger, $partnerLedger) {
                $entry->can_delete = $partnerLedger->canDeleteOpeningBalance($entry, $partnerSubLedger);

                return $entry;
            });

        $partnerNames = $this->resolvePartnerNames(collect([$partnerSubLedger]), $partnerSubLedger->ledger_type);
        $key = $partnerSubLedger->partner_type.'_'.$partnerSubLedger->partner_id;
        $partnerSubLedger->partner_name = $partnerNames[$key] ?? ('#'.$partnerSubLedger->partner_id);

        $bankAccounts = BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get()
            ->map(fn ($bank) => [
                'id' => $bank->id,
                'label' => trim($bank->bank_name.' — '.$bank->account_number.' ('.($bank->outlet?->nama_outlet ?? 'Head Office').')'),
            ]);

        return Inertia::render('PartnerLedger/Show', [
            'ledger' => $partnerSubLedger,
            'entries' => $entries,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function storeManualSettlement(Request $request, PartnerSubLedger $partnerSubLedger, PartnerLedgerService $partnerLedger)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $partnerLedger->recordManualSettlement(
                $partnerSubLedger,
                (float) $validated['amount'],
                $validated['entry_date'],
                (int) $validated['bank_account_id'],
                $validated['description'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menyimpan pelunasan: '.$e->getMessage());
        }

        return back()->with('success', 'Pelunasan berhasil dicatat.');
    }

    public function storeOpeningBalance(Request $request, PartnerLedgerService $partnerLedger)
    {
        $validated = $request->validate([
            'ledger_type' => 'required|in:payable,receivable',
            'partner_type' => 'required|in:supplier,outlet',
            'partner_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $partnerLedger->recordOpeningBalance(
                $validated['ledger_type'],
                $validated['partner_type'],
                (int) $validated['partner_id'],
                (float) $validated['amount'],
                $validated['entry_date'],
                $validated['description'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menyimpan saldo awal: '.$e->getMessage());
        }

        return back()->with('success', 'Saldo awal berhasil disimpan.');
    }

    public function destroyOpeningBalance(PartnerLedgerEntry $partnerLedgerEntry, PartnerLedgerService $partnerLedger)
    {
        try {
            $partnerLedger->deleteOpeningBalance($partnerLedgerEntry);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menghapus saldo awal: '.$e->getMessage());
        }

        return back()->with('success', 'Saldo awal manual berhasil dihapus.');
    }

    protected function resolvePartnerNames($ledgers, string $ledgerType): array
    {
        $names = [];
        $supplierIds = $ledgers->where('partner_type', 'supplier')->pluck('partner_id')->unique()->filter();
        $outletIds = $ledgers->where('partner_type', 'outlet')->pluck('partner_id')->unique()->filter();

        if ($supplierIds->isNotEmpty()) {
            DB::table('suppliers')->whereIn('id', $supplierIds)->get()->each(function ($row) use (&$names) {
                $names['supplier_'.$row->id] = $row->name;
            });
        }

        if ($outletIds->isNotEmpty()) {
            DB::table('tbl_data_outlet')->whereIn('id_outlet', $outletIds)->get()->each(function ($row) use (&$names) {
                $names['outlet_'.$row->id_outlet] = $row->nama_outlet;
            });
        }

        return $names;
    }
}
