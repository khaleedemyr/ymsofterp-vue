<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class PaymentTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentType::query();
        
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $paymentTypes = $query->latest()->get();

        return Inertia::render('PaymentTypes/Index', [
            'paymentTypes' => $paymentTypes,
            'search' => $request->search,
            'status' => $request->status
        ]);
    }

    public function create()
    {
        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();

        $regions = Region::where('status', 'active')
            ->select('id', 'name')
            ->get();

        return Inertia::render('PaymentTypes/Form', [
            'outlets' => $outlets,
            'regions' => $regions,
            'isEdit' => false
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('PaymentTypeController@store - Input', $request->all());
        try {
            $request->merge([
                'regions' => $this->normalizeIdArray($request->input('regions', [])),
                'outlets' => $this->normalizeIdArray($request->input('outlets', [])),
            ]);

            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'code' => 'required|string|max:50|unique:payment_types,code',
                'is_bank' => 'boolean',
                'bank_name' => 'required_if:is_bank,true|nullable|string|max:100',
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
                'outlet_type' => 'required|in:region,outlet',
                'outlets' => 'required_if:outlet_type,outlet|array',
                'outlets.*' => 'integer|exists:tbl_data_outlet,id_outlet',
                'regions' => 'required_if:outlet_type,region|array',
                'regions.*' => 'integer|exists:regions,id'
            ]);
            \Log::info('PaymentTypeController@store - Validated', $validated);

            DB::beginTransaction();

            $paymentType = PaymentType::create($validated);
            \Log::info('PaymentTypeController@store - Created', $paymentType->toArray());

            $this->cleanupOrphanedRegionLinks($paymentType->id);

            if ($validated['outlet_type'] === 'region') {
                $paymentType->regions()->attach($validated['regions'] ?? []);
                \Log::info('PaymentTypeController@store - Attach regions', $validated['regions'] ?? []);
            } else {
                $paymentType->outlets()->attach($validated['outlets'] ?? []);
                \Log::info('PaymentTypeController@store - Attach outlets', $validated['outlets'] ?? []);
            }


            DB::commit();

            return redirect()->route('payment-types.index')
                ->with('success', 'Jenis pembayaran berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('PaymentTypeController@store - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Gagal menambahkan jenis pembayaran: ' . $e->getMessage());
        }
    }

    public function show(PaymentType $paymentType)
    {
        $paymentType->load(['outlets', 'regions']);
        
        return Inertia::render('PaymentTypes/Show', [
            'paymentType' => $paymentType
        ]);
    }

    public function edit(PaymentType $paymentType)
    {
        $this->cleanupOrphanedRegionLinks($paymentType->id);

        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();

        $regions = Region::where('status', 'active')
            ->select('id', 'name')
            ->get();

        return Inertia::render('PaymentTypes/Form', [
            'paymentType' => [
                ...$paymentType->toArray(),
                'outlets' => $paymentType->outlets->map(fn($o) => ['id' => $o->id, 'name' => $o->name])->values(),
                'regions' => $paymentType->regions->map(fn($r) => ['id' => $r->id, 'name' => $r->name])->values(),
            ],
            'outlets' => $outlets,
            'regions' => $regions,
            'isEdit' => true
        ]);
    }

    public function update(Request $request, PaymentType $paymentType)
    {
        try {
            $request->merge([
                'regions' => $this->normalizeIdArray($request->input('regions', [])),
                'outlets' => $this->normalizeIdArray($request->input('outlets', [])),
            ]);

            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'code' => 'required|string|max:50|unique:payment_types,code,' . $paymentType->id,
                'is_bank' => 'boolean',
                'bank_name' => 'required_if:is_bank,true|nullable|string|max:100',
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
                'outlet_type' => 'required|in:region,outlet',
                'outlets' => 'required_if:outlet_type,outlet|array',
                'outlets.*' => 'integer|exists:tbl_data_outlet,id_outlet',
                'regions' => 'required_if:outlet_type,region|array',
                'regions.*' => 'integer|exists:regions,id'
            ]);

            DB::beginTransaction();

            $paymentType->update($validated);

            $this->cleanupOrphanedRegionLinks($paymentType->id);

            if ($validated['outlet_type'] === 'region') {
                $paymentType->regions()->sync($validated['regions'] ?? []);
                $paymentType->outlets()->detach();
            } else {
                $paymentType->outlets()->sync($validated['outlets'] ?? []);
                $paymentType->regions()->detach();
            }

            // Sync bank accounts with outlet_id
            // Delete existing bank account relationships
            DB::table('bank_account_payment_type')
                ->where('payment_type_id', $paymentType->id)
                ->delete();
            
            // Insert new relationships
            if ($request->has('bank_accounts') && is_array($request->bank_accounts)) {
                foreach ($request->bank_accounts as $bank) {
                    DB::table('bank_account_payment_type')->insert([
                        'payment_type_id' => $paymentType->id,
                        'outlet_id' => $bank['outlet_id'] ?? null,
                        'bank_account_id' => $bank['id'],
                        'is_default' => $bank['is_default'] ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('payment-types.index')
                ->with('success', 'Jenis pembayaran berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memperbarui jenis pembayaran: ' . $e->getMessage());
        }
    }

    public function destroy(PaymentType $paymentType)
    {
        try {
            $paymentType->delete();
            return back()->with('success', 'Jenis pembayaran berhasil dihapus');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus jenis pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Show manage banks page for a payment type
     */
    public function manageBanks(PaymentType $paymentType)
    {
        if (!$paymentType->is_bank) {
            return back()->with('error', 'Payment type ini bukan tipe bank');
        }

        $outlets = Outlet::where('status', 'A')
            ->whereNotNull('nama_outlet')
            ->where('nama_outlet', '!=', '')
            ->get()
            ->map(function($o) {
                return [
                    'id' => $o->id_outlet,
                    'name' => $o->nama_outlet,
                ];
            })
            ->values();

        $banks = BankAccount::where('is_active', 1)
            ->with('outlet')
            ->get()
            ->map(function($bank) {
                return [
                    'id' => $bank->id,
                    'bank_name' => $bank->bank_name,
                    'account_number' => $bank->account_number,
                    'account_name' => $bank->account_name,
                    'outlet_id' => $bank->outlet_id,
                    'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
                ];
            })
            ->values();

        // Get existing bank accounts for this payment type
        $bankAccountsData = DB::table('bank_account_payment_type')
            ->where('payment_type_id', $paymentType->id)
            ->join('bank_accounts', 'bank_account_payment_type.bank_account_id', '=', 'bank_accounts.id')
            ->leftJoin('tbl_data_outlet', 'bank_account_payment_type.outlet_id', '=', 'tbl_data_outlet.id_outlet')
            ->select(
                'bank_accounts.id',
                'bank_accounts.bank_name',
                'bank_accounts.account_number',
                'bank_accounts.account_name',
                'bank_account_payment_type.outlet_id',
                'tbl_data_outlet.nama_outlet as outlet_name'
            )
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'bank_name' => $item->bank_name,
                    'account_number' => $item->account_number,
                    'account_name' => $item->account_name,
                    'outlet_id' => $item->outlet_id,
                    'outlet_name' => $item->outlet_name ?? 'Head Office (Semua Outlet)'
                ];
            })
            ->values();

        return Inertia::render('PaymentTypes/ManageBanks', [
            'paymentType' => $paymentType,
            'outlets' => $outlets,
            'banks' => $banks,
            'bankAccounts' => $bankAccountsData
        ]);
    }

    /**
     * Store bank account for payment type (supports batch insert)
     */
    public function storeBank(Request $request, PaymentType $paymentType)
    {
        if (!$paymentType->is_bank) {
            return back()->with('error', 'Payment type ini bukan tipe bank');
        }

        // Support both single and batch insert
        if ($request->has('banks') && is_array($request->banks)) {
            // Batch insert
            $validated = $request->validate([
                'banks' => 'required|array|min:1',
                'banks.*.bank_account_id' => 'required|exists:bank_accounts,id',
                'banks.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet'
            ]);

            $insertData = [];
            $errors = [];

            foreach ($validated['banks'] as $bank) {
                // Check if combination already exists
                $exists = DB::table('bank_account_payment_type')
                    ->where('payment_type_id', $paymentType->id)
                    ->where('outlet_id', $bank['outlet_id'] ?? null)
                    ->where('bank_account_id', $bank['bank_account_id'])
                    ->exists();

                if (!$exists) {
                    $insertData[] = [
                        'payment_type_id' => $paymentType->id,
                        'outlet_id' => $bank['outlet_id'] ?? null,
                        'bank_account_id' => $bank['bank_account_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } else {
                    $errors[] = "Bank account untuk outlet ini sudah ada";
                }
            }

            if (!empty($insertData)) {
                DB::table('bank_account_payment_type')->insert($insertData);
            }

            if (!empty($errors) && empty($insertData)) {
                return back()->with('error', implode(', ', $errors));
            }

            $message = count($insertData) . ' bank account berhasil ditambahkan';
            if (!empty($errors)) {
                $message .= '. ' . count($errors) . ' bank account diabaikan karena sudah ada';
            }

            return back()->with('success', $message);
        } else {
            // Single insert (backward compatibility)
            $validated = $request->validate([
                'bank_account_id' => 'required|exists:bank_accounts,id',
                'outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet'
            ]);

            // Check if combination already exists
            $exists = DB::table('bank_account_payment_type')
                ->where('payment_type_id', $paymentType->id)
                ->where('outlet_id', $validated['outlet_id'] ?? null)
                ->where('bank_account_id', $validated['bank_account_id'])
                ->exists();

            if ($exists) {
                return back()->with('error', 'Bank account untuk outlet ini sudah ada');
            }

            DB::table('bank_account_payment_type')->insert([
                'payment_type_id' => $paymentType->id,
                'outlet_id' => $validated['outlet_id'] ?? null,
                'bank_account_id' => $validated['bank_account_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return back()->with('success', 'Bank account berhasil ditambahkan');
        }
    }

    /**
     * Delete bank account from payment type
     */
    public function deleteBank(Request $request, PaymentType $paymentType)
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'outlet_id' => 'nullable'
        ]);

        $query = DB::table('bank_account_payment_type')
            ->where('payment_type_id', $paymentType->id)
            ->where('bank_account_id', $validated['bank_account_id']);
        
        if ($validated['outlet_id'] === null) {
            $query->whereNull('outlet_id');
        } else {
            $query->where('outlet_id', $validated['outlet_id']);
        }
        
        $query->delete();

        return back()->with('success', 'Bank account berhasil dihapus');
    }

    private function normalizeIdArray($items): array
    {
        return collect($items)
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item['id'] ?? null;
                }

                if (is_object($item)) {
                    return $item->id ?? null;
                }

                return $item;
            })
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function cleanupOrphanedRegionLinks(int $paymentTypeId): void
    {
        DB::table('payment_type_regions as ptr')
            ->leftJoin('regions as r', 'r.id', '=', 'ptr.region_id')
            ->where('ptr.payment_type_id', $paymentTypeId)
            ->whereNull('r.id')
            ->delete();
    }
} 