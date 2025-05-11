<?php

namespace App\Http\Controllers;

use App\Models\FoodPayment;
use App\Models\FoodPaymentContraBon;
use App\Models\ContraBon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FoodPaymentController extends Controller
{
    // List all food payments
    public function index(Request $request)
    {
        $query = FoodPayment::with(['supplier', 'creator', 'financeManager', 'contraBons'])->orderByDesc('created_at');

        if ($request->search) {
            $query->where('number', 'like', '%' . $request->search . '%');
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $payments = $query->get();

        return inertia('FoodPayment/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    // Show create form
    public function create()
    {
        return inertia('FoodPayment/Form');
    }

    // Store new food payment
    public function store(Request $request)
    {
        \Log::info('FoodPaymentController@store - Input', $request->all());
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'payment_type' => 'required|string',
                'supplier_id' => 'required|exists:suppliers,id',
                'contra_bon_ids' => 'required|array|min:1',
                'contra_bon_ids.*' => 'exists:food_contra_bons,id',
                'notes' => 'nullable|string',
                'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            ]);
            \Log::info('FoodPaymentController@store - Validated', $validated);

            DB::beginTransaction();
            // Generate number
            $dateStr = date('Ymd', strtotime($validated['date']));
            $countToday = FoodPayment::whereDate('date', $validated['date'])->count() + 1;
            $number = 'FP-' . $dateStr . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            // Hitung total
            $contraBons = \App\Models\ContraBon::whereIn('id', $validated['contra_bon_ids'])->get();
            \Log::info('FoodPaymentController@store - ContraBons', $contraBons->toArray());
            $total = $contraBons->sum('total_amount');

            // Upload file jika ada
            $buktiPath = null;
            if ($request->hasFile('bukti_transfer')) {
                $buktiPath = $request->file('bukti_transfer')->store('food_payment_bukti', 'public');
                \Log::info('FoodPaymentController@store - Bukti transfer path', [$buktiPath]);
            }

            // Simpan FoodPayment
            $payment = FoodPayment::create([
                'number' => $number,
                'date' => $validated['date'],
                'supplier_id' => $validated['supplier_id'],
                'total' => $total,
                'payment_type' => $validated['payment_type'],
                'notes' => $validated['notes'] ?? null,
                'bukti_transfer_path' => $buktiPath,
                'status' => 'paid',
                'created_by' => Auth::id(),
            ]);
            \Log::info('FoodPaymentController@store - FoodPayment created', $payment->toArray());

            // Simpan relasi ke contra bon
            foreach ($contraBons as $cb) {
                FoodPaymentContraBon::create([
                    'food_payment_id' => $payment->id,
                    'contra_bon_id' => $cb->id,
                ]);
                // Update status contra bon menjadi paid
                $cb->status = 'paid';
                $cb->save();
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('FoodPaymentController@store - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Show detail food payment
    public function show($id)
    {
        $payment = FoodPayment::with(['supplier', 'creator', 'financeManager', 'contraBons'])->findOrFail($id);
        return inertia('FoodPayment/Show', [
            'payment' => $payment
        ]);
    }

    // Approve payment (Finance Manager)
    public function approve(Request $request, $id)
    {
        // Logic approval
        return response()->json(['success' => true]);
    }

    // API: Get contra bon yang belum dibayar
    public function getContraBonUnpaid()
    {
        $paidContraBonIds = FoodPaymentContraBon::pluck('contra_bon_id')->toArray();
        $contraBons = ContraBon::with(['supplier', 'purchaseOrder'])
            ->where('status', 'approved')
            ->whereNotIn('id', $paidContraBonIds)
            ->get();
        return response()->json($contraBons);
    }
} 