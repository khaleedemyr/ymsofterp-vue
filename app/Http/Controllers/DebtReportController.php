<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DebtReportController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters
        $supplierId = $request->input('supplier_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $status = $request->input('status', 'all'); // all, unpaid, partial

        // Query untuk PO yang belum dibayar atau masih ada sisa
        $query = DB::table('purchase_order_ops as poo')
            ->leftJoin('suppliers as s', 'poo.supplier_id', '=', 's.id')
            ->leftJoin('purchase_requisitions as pr', 'poo.source_id', '=', 'pr.id')
            ->where('poo.status', 'approved')
            ->select(
                'poo.id',
                'poo.number as po_number',
                'poo.date as po_date',
                'poo.grand_total',
                'poo.payment_type',
                'poo.payment_terms',
                'poo.supplier_id',
                's.name as supplier_name',
                'pr.pr_number as source_pr_number'
            );

        // Apply filters
        if ($supplierId) {
            $query->where('poo.supplier_id', $supplierId);
        }
        if ($dateFrom) {
            $query->whereDate('poo.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('poo.date', '<=', $dateTo);
        }

        $pos = $query->orderBy('poo.date', 'desc')->get();

        // Calculate payment info for each PO
        $debtData = [];
        $totalDebt = 0;
        $totalPaid = 0;
        $totalRemaining = 0;

        foreach ($pos as $po) {
            // Get total paid from non_food_payments
            $totalPaidForPO = DB::table('non_food_payments')
                ->where('purchase_order_ops_id', $po->id)
                ->where('status', '!=', 'cancelled')
                ->sum('amount');

            $remaining = $po->grand_total - $totalPaidForPO;

            // Filter berdasarkan status
            if ($status === 'unpaid' && $totalPaidForPO > 0) {
                continue; // Skip jika sudah ada payment
            }
            if ($status === 'partial' && ($totalPaidForPO == 0 || $remaining == 0)) {
                continue; // Skip jika belum ada payment atau sudah lunas
            }
            if ($status === 'paid' && $remaining > 0) {
                continue; // Skip jika masih ada sisa
            }

            // Only include PO with debt (remaining > 0) or unpaid
            if ($remaining > 0 || ($status === 'unpaid' && $totalPaidForPO == 0)) {
                // Get payment history
                $paymentHistory = DB::table('non_food_payments')
                    ->where('purchase_order_ops_id', $po->id)
                    ->where('status', '!=', 'cancelled')
                    ->select('id', 'payment_number', 'amount', 'payment_date', 'status', 'payment_sequence')
                    ->orderBy('payment_sequence', 'asc')
                    ->orderBy('payment_date', 'asc')
                    ->get();

                $debtData[] = [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'po_date' => $po->po_date,
                    'supplier_id' => $po->supplier_id,
                    'supplier_name' => $po->supplier_name,
                    'source_pr_number' => $po->source_pr_number,
                    'grand_total' => (float) $po->grand_total,
                    'total_paid' => (float) $totalPaidForPO,
                    'remaining' => (float) $remaining,
                    'payment_type' => $po->payment_type,
                    'payment_terms' => $po->payment_terms,
                    'payment_count' => $paymentHistory->count(),
                    'payment_history' => $paymentHistory,
                    'days_overdue' => $this->calculateDaysOverdue($po->po_date),
                    'status' => $totalPaidForPO == 0 ? 'unpaid' : 'partial'
                ];

                $totalDebt += $po->grand_total;
                $totalPaid += $totalPaidForPO;
                $totalRemaining += $remaining;
            }
        }

        // Group by supplier
        $bySupplier = [];
        foreach ($debtData as $debt) {
            $supplierId = $debt['supplier_id'];
            if (!isset($bySupplier[$supplierId])) {
                $bySupplier[$supplierId] = [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $debt['supplier_name'],
                    'po_count' => 0,
                    'total_debt' => 0,
                    'total_paid' => 0,
                    'total_remaining' => 0,
                    'pos' => []
                ];
            }
            $bySupplier[$supplierId]['po_count']++;
            $bySupplier[$supplierId]['total_debt'] += $debt['grand_total'];
            $bySupplier[$supplierId]['total_paid'] += $debt['total_paid'];
            $bySupplier[$supplierId]['total_remaining'] += $debt['remaining'];
            $bySupplier[$supplierId]['pos'][] = $debt;
        }

        // Get suppliers for filter
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Reports/DebtReport', [
            'debtData' => array_values($debtData),
            'bySupplier' => array_values($bySupplier),
            'summary' => [
                'total_po' => count($debtData),
                'total_debt' => $totalDebt,
                'total_paid' => $totalPaid,
                'total_remaining' => $totalRemaining
            ],
            'suppliers' => $suppliers,
            'filters' => $request->only(['supplier_id', 'date_from', 'date_to', 'status'])
        ]);
    }

    private function calculateDaysOverdue($poDate)
    {
        $poDateObj = \Carbon\Carbon::parse($poDate);
        $now = \Carbon\Carbon::now();
        return $now->diffInDays($poDateObj, false); // Negative if overdue
    }

    public function export(Request $request)
    {
        // Similar logic to index but for export
        // You can implement Excel export here using Laravel Excel or similar
        return response()->json(['message' => 'Export feature coming soon']);
    }
}

