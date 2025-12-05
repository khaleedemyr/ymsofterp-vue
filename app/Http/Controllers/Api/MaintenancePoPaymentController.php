<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaintenancePoPaymentController extends Controller
{
    public function index(Request $request)
    {
        // List payment
        return DB::table('maintenance_po_payments')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'po_id' => 'required|exists:maintenance_purchase_orders,id',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_type' => 'required|in:full,partial',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf',
            'notes' => 'nullable|string',
        ]);

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')->store('po_payment_proofs', 'public');
        }

        $paymentNumber = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);

        $paymentId = DB::table('maintenance_po_payments')->insertGetId([
            'po_id' => $validated['po_id'],
            'payment_number' => $paymentNumber,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_type' => $validated['payment_type'],
            'status' => 'pending',
            'payment_proof_path' => $proofPath,
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $paymentId]);
    }
}
