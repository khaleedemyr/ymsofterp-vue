<?php

namespace App\Http\Controllers;

use App\Models\MaintenancePurchaseOrder;
use App\Models\MaintenancePOPayment;
use App\Models\MaintenancePOPaymentHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MTPOPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenancePurchaseOrder::with(['supplier', 'items', 'invoices', 'payments'])
            ->where('status', 'APPROVED')
            ->whereIn('payment_status', ['unpaid', 'partial_paid']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }
        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }
        if ($request->filled('startDate')) {
            $query->whereDate('created_at', '>=', $request->startDate);
        }
        if ($request->filled('endDate')) {
            $query->whereDate('created_at', '<=', $request->endDate);
        }

        $unpaidPOs = $query->orderBy('created_at', 'desc')->get();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['unpaidPOs' => $unpaidPOs]);
        }

        return inertia('MTPOPayment/Index', [
            'unpaidPOs' => $unpaidPOs
        ]);
    }

    public function history(Request $request)
    {
        $query = MaintenancePurchaseOrder::with(['supplier', 'payments'])
            ->where('status', 'APPROVED')
            ->whereIn('payment_status', ['paid', 'partial_paid']);

        // Apply filters
        if ($request->filled('startDate')) {
            $query->whereDate('created_at', '>=', $request->startDate);
        }
        if ($request->filled('endDate')) {
            $query->whereDate('created_at', '<=', $request->endDate);
        }
        if ($request->filled('paymentStatus')) {
            $query->where('payment_status', $request->paymentStatus);
        }
        if ($request->filled('paymentMethod')) {
            $query->whereHas('payments', function ($q) use ($request) {
                $q->where('payment_method', $request->paymentMethod);
            });
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $history = $query->orderBy('created_at', 'desc')
                        ->paginate(10);

        return response()->json($history);
    }

    public function store(Request $request)
    {
        $request->validate([
            'po_id' => 'required|exists:maintenance_purchase_orders,id',
            'payment_date' => 'required|date',
            'payment_amount' => 'required|numeric|min:0',
            'payment_type' => 'required|in:full,partial',
            'payment_method' => 'required|string',
            'payment_reference' => 'required|string',
            'payment_proof' => 'required|file|mimes:jpeg,png,pdf|max:2048',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $po = MaintenancePurchaseOrder::findOrFail($request->po_id);
            
            // Validate payment amount
            if ($request->payment_amount > $po->total_amount) {
                return response()->json([
                    'message' => 'Payment amount cannot exceed PO total amount'
                ], 422);
            }

            // Store payment proof
            $proofPath = $request->file('payment_proof')->store('po_payment_proofs', 'public');

            // Create payment record
            $payment = MaintenancePOPayment::create([
                'po_id' => $request->po_id,
                'payment_date' => $request->payment_date,
                'payment_amount' => $request->payment_amount,
                'payment_type' => $request->payment_type,
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'payment_proof_path' => $proofPath,
                'notes' => $request->notes
            ]);

            // Create payment history record
            MaintenancePOPaymentHistory::create([
                'po_id' => $request->po_id,
                'payment_id' => $payment->id,
                'payment_date' => $request->payment_date,
                'payment_amount' => $request->payment_amount,
                'payment_type' => $request->payment_type,
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'payment_proof_path' => $proofPath,
                'notes' => $request->notes
            ]);

            // Update PO payment status
            if ($request->payment_type === 'full') {
                $po->payment_status = 'paid';
            } else {
                $po->payment_status = 'partial_paid';
            }
            $po->save();

            DB::commit();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error recording payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function details($id)
    {
        $po = MaintenancePurchaseOrder::with([
            'supplier',
            'items',
            'invoices',
            'payments',
            'paymentHistory'
        ])->findOrFail($id);

        return response()->json($po);
    }


} 