<?php

namespace App\Http\Controllers;

use App\Models\BankBook;
use App\Models\BankAccount;
use App\Services\BankBookService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class BankBookController extends Controller
{
    /**
     * Display a listing of the bank book entries
     */
    public function index(Request $request)
    {
        $bankAccountId = $request->input('bank_account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $transactionType = $request->input('transaction_type');
        $perPage = $request->input('per_page', 20);

        $query = BankBook::with(['bankAccount.outlet', 'creator', 'updater'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        // Apply filters
        if ($bankAccountId) {
            $query->where('bank_account_id', $bankAccountId);
        }

        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

        $bankBooks = $query->paginate($perPage)->withQueryString();

        // Get all bank accounts for filter
        $bankAccounts = BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get()
            ->map(function($bank) {
                return [
                    'id' => $bank->id,
                    'bank_name' => $bank->bank_name,
                    'account_number' => $bank->account_number,
                    'account_name' => $bank->account_name,
                    'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
                ];
            });

        return Inertia::render('BankBook/Index', [
            'bankBooks' => $bankBooks,
            'bankAccounts' => $bankAccounts,
            'filters' => $request->only(['bank_account_id', 'date_from', 'date_to', 'transaction_type', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new bank book entry
     */
    public function create()
    {
        $bankAccounts = BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get()
            ->map(function($bank) {
                return [
                    'id' => $bank->id,
                    'bank_name' => $bank->bank_name,
                    'account_number' => $bank->account_number,
                    'account_name' => $bank->account_name,
                    'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
                ];
            });

        return Inertia::render('BankBook/Form', [
            'bankBook' => null,
            'bankAccounts' => $bankAccounts,
            'mode' => 'create',
        ]);
    }

    /**
     * Store a newly created bank book entry
     */
    public function store(Request $request, BankBookService $bankBookService)
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'transaction_date' => 'required|date',
            'transaction_type' => 'required|in:debit,credit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        try {
            $bankBook = $bankBookService->createEntry($validated);

            return redirect()->route('bank-books.index')
                ->with('success', 'Entri buku bank berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menambahkan entri buku bank: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified bank book entry
     */
    public function show(BankBook $bankBook)
    {
        $bankBook->load(['bankAccount.outlet', 'creator', 'updater']);
        
        // Load reference if exists
        $reference = null;
        if ($bankBook->reference_type && $bankBook->reference_id) {
            $reference = $bankBook->reference;
        }
        
        $bankBook->reference_data = $reference;

        return Inertia::render('BankBook/Show', [
            'bankBook' => $bankBook,
        ]);
    }

    /**
     * Show the form for editing the specified bank book entry
     */
    public function edit(BankBook $bankBook)
    {
        $bankAccounts = BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get()
            ->map(function($bank) {
                return [
                    'id' => $bank->id,
                    'bank_name' => $bank->bank_name,
                    'account_number' => $bank->account_number,
                    'account_name' => $bank->account_name,
                    'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
                ];
            });

        return Inertia::render('BankBook/Form', [
            'bankBook' => $bankBook,
            'bankAccounts' => $bankAccounts,
            'mode' => 'edit',
        ]);
    }

    /**
     * Update the specified bank book entry
     */
    public function update(Request $request, BankBook $bankBook, BankBookService $bankBookService)
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'transaction_date' => 'required|date',
            'transaction_type' => 'required|in:debit,credit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        try {
            $bankBookService->updateEntry($bankBook, $validated);

            return redirect()->route('bank-books.index')
                ->with('success', 'Entri buku bank berhasil diupdate.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengupdate entri buku bank: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified bank book entry
     */
    public function destroy(BankBook $bankBook, BankBookService $bankBookService)
    {
        try {
            $bankBookService->deleteEntry($bankBook);

            return redirect()->route('bank-books.index')
                ->with('success', 'Entri buku bank berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus entri buku bank: ' . $e->getMessage());
        }
    }
}
