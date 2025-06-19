<?php

namespace App\Http\Controllers;

use App\Models\PaymentType;
use App\Models\Outlet;
use App\Models\Region;
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
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'code' => 'required|string|max:50|unique:payment_types,code',
                'is_bank' => 'boolean',
                'bank_name' => 'required_if:is_bank,true|nullable|string|max:100',
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
                'outlets' => 'required_if:outlet_type,outlet|array',
                'regions' => 'required_if:outlet_type,region|array'
            ]);
            \Log::info('PaymentTypeController@store - Validated', $validated);

            DB::beginTransaction();

            $paymentType = PaymentType::create($validated);
            \Log::info('PaymentTypeController@store - Created', $paymentType->toArray());

            if ($request->outlet_type === 'region') {
                $paymentType->regions()->attach($request->regions);
                \Log::info('PaymentTypeController@store - Attach regions', $request->regions);
            } else {
                $paymentType->outlets()->attach($request->outlets);
                \Log::info('PaymentTypeController@store - Attach outlets', $request->outlets);
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
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'code' => 'required|string|max:50|unique:payment_types,code,' . $paymentType->id,
                'is_bank' => 'boolean',
                'bank_name' => 'required_if:is_bank,true|nullable|string|max:100',
                'description' => 'nullable|string',
                'status' => 'required|in:active,inactive',
                'outlets' => 'required_if:outlet_type,outlet|array',
                'regions' => 'required_if:outlet_type,region|array'
            ]);

            DB::beginTransaction();

            $paymentType->update($validated);

            if ($request->outlet_type === 'region') {
                $paymentType->regions()->sync($request->regions);
                $paymentType->outlets()->detach();
            } else {
                $paymentType->outlets()->sync($request->outlets);
                $paymentType->regions()->detach();
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
} 