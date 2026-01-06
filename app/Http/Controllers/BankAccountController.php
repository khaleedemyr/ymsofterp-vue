<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\DataOutlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $outletId = $request->get('outlet_id', '');
        $status = $request->get('status', 'all'); // 'all', 'active', 'inactive'
        $perPage = $request->get('per_page', 15);

        $query = BankAccount::with('outlet');

        // Search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('bank_name', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('account_name', 'like', "%{$search}%");
            });
        }

        // Outlet filter
        if ($outletId !== '') {
            if ($outletId === 'null') {
                // Filter untuk bank account yang tidak terikat outlet (untuk semua outlet)
                $query->whereNull('outlet_id');
            } else {
                $query->where('outlet_id', $outletId);
            }
        }

        // Status filter
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $bankAccounts = $query->orderBy('bank_name')->orderBy('account_name')->paginate($perPage)->withQueryString();

        // Get outlets for filter dropdown
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('BankAccount/Index', [
            'bankAccounts' => $bankAccounts,
            'outlets' => $outlets,
            'filters' => [
                'search' => $search,
                'outlet_id' => $outletId,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('BankAccount/Create', [
            'outlets' => $outlets,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:100',
            'account_name' => 'required|string|max:255',
            'outlet_id' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $bankAccount = BankAccount::create([
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'outlet_id' => $request->outlet_id ?: null,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('bank-accounts.index')
            ->with('success', 'Bank account created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(BankAccount $bankAccount)
    {
        $bankAccount->load('outlet');
        
        return Inertia::render('BankAccount/Show', [
            'bankAccount' => $bankAccount,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BankAccount $bankAccount)
    {
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('BankAccount/Edit', [
            'bankAccount' => $bankAccount,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:100',
            'account_name' => 'required|string|max:255',
            'outlet_id' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $bankAccount->update([
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'outlet_id' => $request->outlet_id ?: null,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('bank-accounts.index')
            ->with('success', 'Bank account updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($bank_account)
    {
        $bankAccount = BankAccount::findOrFail($bank_account);
        $bankAccount->delete();

        // Return JSON response for AJAX requests
        if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Bank account deleted successfully.'
            ], 200);
        }

        return redirect()->route('bank-accounts.index')
            ->with('success', 'Bank account deleted successfully.');
    }
}

