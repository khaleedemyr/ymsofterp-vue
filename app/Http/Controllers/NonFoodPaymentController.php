<?php

namespace App\Http\Controllers;

use App\Models\NonFoodPayment;
use App\Models\PurchaseOrderOps;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NonFoodPaymentController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters
        $supplier = $request->input('supplier');
        $status = $request->input('status');
        $date = $request->input('date');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);

        // Build query with search and filters
        $query = NonFoodPayment::query()
            ->leftJoin('suppliers as s', 'non_food_payments.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'non_food_payments.created_by', '=', 'u.id')
            ->leftJoin('purchase_order_ops as poo', 'non_food_payments.purchase_order_ops_id', '=', 'poo.id')
            ->leftJoin('purchase_requisitions as pr', 'non_food_payments.purchase_requisition_id', '=', 'pr.id')
            ->select(
                'non_food_payments.*',
                's.name as supplier_name',
                'u.nama_lengkap as creator_name',
                'poo.number as po_number',
                'poo.date as po_date',
                'pr.pr_number as pr_number',
                'pr.date as pr_date'
            );

        // Apply filters
        if ($supplier) {
            $query->where('non_food_payments.supplier_id', $supplier);
        }
        
        if ($status) {
            $query->where('non_food_payments.status', $status);
        }
        
        if ($date) {
            $query->whereDate('non_food_payments.payment_date', $date);
        }

        // Apply search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('non_food_payments.payment_number', 'like', "%{$search}%")
                  ->orWhere('s.name', 'like', "%{$search}%")
                  ->orWhere('u.nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('poo.number', 'like', "%{$search}%")
                  ->orWhere('pr.pr_number', 'like', "%{$search}%");
            });
        }

        $payments = $query->latest('non_food_payments.payment_date')->paginate($perPage)->withQueryString();
        
        // Transform payments to show per outlet
        $payments->getCollection()->transform(function($payment) {
            if ($payment->purchase_order_ops_id) {
                $payment->payment_type = 'PO';
                
                // Get outlet breakdown for PO payments
                try {
                    $outletBreakdown = DB::table('purchase_order_ops_items as poi')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                        ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
                        ->where('poi.purchase_order_ops_id', $payment->purchase_order_ops_id)
                        ->select(
                            'pr.outlet_id',
                            'o.nama_outlet as outlet_name',
                            'prc.name as category_name',
                            'prc.division as category_division',
                            'prc.subcategory as category_subcategory',
                            'prc.budget_type as category_budget_type',
                            'pr.pr_number',
                            'pr.title as pr_title',
                            DB::raw('SUM(poi.total) as outlet_total')
                        )
                        ->groupBy('pr.outlet_id', 'o.nama_outlet', 'prc.name', 'prc.division', 'prc.subcategory', 'prc.budget_type', 'pr.pr_number', 'pr.title')
                        ->get();
                    
                    $payment->outlet_breakdown = $outletBreakdown;
                } catch (\Exception $e) {
                    // Fallback if outlet data not available
                    $payment->outlet_breakdown = collect([[
                        'outlet_id' => null,
                        'outlet_name' => 'Unknown Outlet',
                        'category_name' => null,
                        'category_division' => null,
                        'category_subcategory' => null,
                        'category_budget_type' => null,
                        'pr_number' => null,
                        'pr_title' => null,
                        'outlet_total' => $payment->amount
                    ]]);
                }
            } else {
                $payment->payment_type = 'PR';
                // For PR payments, show as single outlet
                $payment->outlet_breakdown = collect([[
                    'outlet_id' => null,
                    'outlet_name' => 'Direct PR Payment',
                    'category_name' => null,
                    'category_division' => null,
                    'category_subcategory' => null,
                    'category_budget_type' => null,
                    'pr_number' => $payment->pr_number,
                    'pr_title' => null,
                    'outlet_total' => $payment->amount
                ]]);
            }
            return $payment;
        });

        // Get suppliers for filter dropdown
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('NonFoodPayment/Index', [
            'payments' => $payments,
            'suppliers' => $suppliers,
            'filters' => $request->only(['supplier', 'status', 'date', 'search', 'per_page'])
        ]);
    }

    public function create(Request $request)
    {
        // Get filter parameters
        $supplierId = $request->input('supplier_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get suppliers
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get available Purchase Order Ops that don't have payments yet
        $poQuery = DB::table('purchase_order_ops as poo')
            ->leftJoin('suppliers as s', 'poo.supplier_id', '=', 's.id')
            ->leftJoin('purchase_requisitions as pr', 'poo.source_id', '=', 'pr.id')
            ->leftJoin('non_food_payments as nfp', function($join) {
                $join->on('poo.id', '=', 'nfp.purchase_order_ops_id')
                     ->where('nfp.status', '!=', 'cancelled');
            })
            ->where('poo.status', 'approved')
            ->whereNull('nfp.id')
            ->select(
                'poo.id',
                'poo.number',
                'poo.date',
                'poo.grand_total',
                'poo.supplier_id',
                'poo.notes',
                's.name as supplier_name',
                'pr.pr_number as source_pr_number',
                'pr.title as pr_title'
            );

        // Apply filters
        if ($supplierId) {
            $poQuery->where('poo.supplier_id', $supplierId);
        }
        if ($dateFrom) {
            $poQuery->whereDate('poo.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $poQuery->whereDate('poo.date', '<=', $dateTo);
        }

        $availablePOs = $poQuery->orderBy('poo.date', 'desc')
            ->limit(50)
            ->get();

        // Get available Purchase Requisitions that don't have payments yet
        $prQuery = DB::table('purchase_requisitions as pr')
            ->leftJoin('non_food_payments as nfp', function($join) {
                $join->on('pr.id', '=', 'nfp.purchase_requisition_id')
                     ->where('nfp.status', '!=', 'cancelled');
            })
            ->where('pr.status', 'APPROVED')
            ->whereNull('nfp.id')
            ->select(
                'pr.id',
                'pr.pr_number',
                'pr.date',
                'pr.amount',
                'pr.title',
                'pr.description'
            );

        // Apply filters
        if ($dateFrom) {
            $prQuery->whereDate('pr.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $prQuery->whereDate('pr.date', '<=', $dateTo);
        }

        $availablePRs = $prQuery->orderBy('pr.date', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('NonFoodPayment/Create', [
            'suppliers' => $suppliers,
            'availablePOs' => $availablePOs,
            'availablePRs' => $availablePRs,
            'filters' => $request->only(['supplier_id', 'date_from', 'date_to'])
        ]);
    }

    public function getPOItems($poId)
    {
        try {
            // Get basic PO info first
            $po = DB::table('purchase_order_ops as poo')
                ->leftJoin('suppliers as s', 'poo.supplier_id', '=', 's.id')
                ->where('poo.id', $poId)
                ->select(
                    'poo.*',
                    's.name as supplier_name'
                )
                ->first();

            if (!$po) {
                return response()->json(['error' => 'Purchase Order not found'], 404);
            }

            // Try to get PR info if table exists
            try {
                $prInfo = DB::table('purchase_requisitions as pr')
                    ->leftJoin('tbl_data_divisi as d', 'pr.division_id', '=', 'd.id')
                    ->where('pr.id', $po->source_id)
                    ->select(
                        'pr.pr_number as source_pr_number',
                        'pr.title as pr_title',
                        'pr.description as pr_description',
                        'd.nama_divisi as division_name'
                    )
                    ->first();
                
                if ($prInfo) {
                    $po->source_pr_number = $prInfo->source_pr_number;
                    $po->pr_title = $prInfo->pr_title;
                    $po->pr_description = $prInfo->pr_description;
                    $po->division_name = $prInfo->division_name;
                }
            } catch (\Exception $e) {
                // PR table might not exist, continue without PR info
            }

            // Get items with outlet and category info
            try {
                $items = DB::table('purchase_order_ops_items as poi')
                    ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                    ->leftJoin('tbl_data_outlet as o', 'pr.outlet_id', '=', 'o.id_outlet')
                    ->leftJoin('purchase_requisition_categories as prc', 'pr.category_id', '=', 'prc.id')
                    ->where('poi.purchase_order_ops_id', $poId)
                    ->select(
                        'poi.id',
                        'poi.item_name',
                        'poi.quantity',
                        'poi.unit',
                        'poi.price',
                        'poi.total',
                        'pr.outlet_id',
                        'o.nama_outlet as outlet_name',
                        'pr.category_id',
                        'prc.name as category_name',
                        'prc.division as category_division',
                        'prc.subcategory as category_subcategory',
                        'prc.budget_type as category_budget_type',
                        'pr.pr_number',
                        'pr.title as pr_title'
                    )
                    ->get();
            } catch (\Exception $e) {
                // Fallback to basic items without outlet info
                $items = DB::table('purchase_order_ops_items as poi')
                    ->where('poi.purchase_order_ops_id', $poId)
                    ->select(
                        'poi.id',
                        'poi.item_name',
                        'poi.quantity',
                        'poi.unit',
                        'poi.price',
                        'poi.total'
                    )
                    ->get();
                
                // Add default values for outlet info
                $items = $items->map(function ($item) {
                    $item->outlet_id = null;
                    $item->outlet_name = 'Unknown Outlet';
                    $item->category_id = null;
                    $item->category_name = null;
                    $item->category_division = null;
                    $item->category_subcategory = null;
                    $item->category_budget_type = null;
                    $item->pr_number = null;
                    $item->pr_title = null;
                    return $item;
                });
            }

            // Group items by outlet
            $itemsByOutlet = $items->groupBy('outlet_id')->map(function ($outletItems, $outletId) {
                $firstItem = $outletItems->first();
                return [
                    'outlet_id' => $outletId,
                    'outlet_name' => $firstItem->outlet_name ?? 'Unknown Outlet',
                    'category_id' => $firstItem->category_id ?? null,
                    'category_name' => $firstItem->category_name ?? null,
                    'category_division' => $firstItem->category_division ?? null,
                    'category_subcategory' => $firstItem->category_subcategory ?? null,
                    'category_budget_type' => $firstItem->category_budget_type ?? null,
                    'pr_number' => $firstItem->pr_number ?? null,
                    'pr_title' => $firstItem->pr_title ?? null,
                    'items' => $outletItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name,
                            'quantity' => $item->quantity,
                            'unit' => $item->unit,
                            'price' => $item->price,
                            'total' => $item->total,
                        ];
                    }),
                    'subtotal' => $outletItems->sum('total')
                ];
            });

            return response()->json([
                'po' => $po,
                'items_by_outlet' => $itemsByOutlet,
                'total_amount' => $items->sum('total')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load PO items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_ops_id' => 'nullable|exists:purchase_order_ops,id',
            'purchase_requisition_id' => 'nullable|exists:purchase_requisitions,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,check',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:payment_date',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Validate that at least one transaction is selected
        if (empty($request->purchase_order_ops_id) && empty($request->purchase_requisition_id)) {
            return back()->with('error', 'Pilih minimal satu transaksi (Purchase Order atau Purchase Requisition).');
        }

        // Check if PO already has a payment
        if ($request->purchase_order_ops_id) {
            $existingPayment = NonFoodPayment::where('purchase_order_ops_id', $request->purchase_order_ops_id)
                ->where('status', '!=', 'cancelled')
                ->first();
            if ($existingPayment) {
                return back()->with('error', 'Purchase Order ini sudah memiliki payment yang aktif.');
            }
        }

        // Check if PR already has a payment
        if ($request->purchase_requisition_id) {
            $existingPayment = NonFoodPayment::where('purchase_requisition_id', $request->purchase_requisition_id)
                ->where('status', '!=', 'cancelled')
                ->first();
            if ($existingPayment) {
                return back()->with('error', 'Purchase Requisition ini sudah memiliki payment yang aktif.');
            }
        }

        try {
            DB::beginTransaction();

            // Generate payment number
            $paymentNumber = (new NonFoodPayment())->generatePaymentNumber();

            // Create payment
            $payment = NonFoodPayment::create([
                'payment_number' => $paymentNumber,
                'purchase_order_ops_id' => $request->purchase_order_ops_id,
                'purchase_requisition_id' => $request->purchase_requisition_id,
                'supplier_id' => $request->supplier_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'due_date' => $request->due_date,
                'status' => 'pending',
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('non-food-payments.index')
                ->with('success', 'Non Food Payment berhasil dibuat.');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Non Food Payment creation error: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat Non Food Payment: ' . $e->getMessage());
        }
    }

    public function show(NonFoodPayment $nonFoodPayment)
    {
        $nonFoodPayment->load([
            'purchaseOrderOps.supplier',
            'purchaseOrderOps.items',
            'purchaseRequisition.division',
            'purchaseRequisition.creator',
            'supplier',
            'creator',
            'approver',
            'attachments.uploader'
        ]);

        return Inertia::render('NonFoodPayment/Show', [
            'payment' => $nonFoodPayment
        ]);
    }

    public function edit(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeEdited()) {
            return back()->with('error', 'Payment ini tidak dapat diedit.');
        }

        $nonFoodPayment->load([
            'purchaseOrderOps.supplier',
            'purchaseRequisition.division',
            'supplier'
        ]);

        // Get suppliers
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('NonFoodPayment/Edit', [
            'payment' => $nonFoodPayment,
            'suppliers' => $suppliers
        ]);
    }

    public function update(Request $request, NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeEdited()) {
            return back()->with('error', 'Payment ini tidak dapat diedit.');
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,check',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:payment_date',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $nonFoodPayment->update([
                'supplier_id' => $request->supplier_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'due_date' => $request->due_date,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
            ]);

            return redirect()->route('non-food-payments.show', $nonFoodPayment->id)
                ->with('success', 'Non Food Payment berhasil diperbarui.');

        } catch (\Exception $e) {
            \Log::error('Non Food Payment update error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui Non Food Payment: ' . $e->getMessage());
        }
    }

    public function destroy(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeDeleted()) {
            return back()->with('error', 'Payment ini tidak dapat dihapus.');
        }

        try {
            $nonFoodPayment->delete();

            return redirect()->route('non-food-payments.index')
                ->with('success', 'Non Food Payment berhasil dihapus.');

        } catch (\Exception $e) {
            \Log::error('Non Food Payment deletion error: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus Non Food Payment: ' . $e->getMessage());
        }
    }

    public function approve(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeApproved()) {
            return back()->with('error', 'Payment ini tidak dapat disetujui.');
        }

        try {
            $nonFoodPayment->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return back()->with('success', 'Non Food Payment berhasil disetujui.');

        } catch (\Exception $e) {
            \Log::error('Non Food Payment approval error: ' . $e->getMessage());
            return back()->with('error', 'Gagal menyetujui Non Food Payment: ' . $e->getMessage());
        }
    }

    public function reject(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeRejected()) {
            return back()->with('error', 'Payment ini tidak dapat ditolak.');
        }

        try {
            $nonFoodPayment->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return back()->with('success', 'Non Food Payment berhasil ditolak.');

        } catch (\Exception $e) {
            \Log::error('Non Food Payment rejection error: ' . $e->getMessage());
            return back()->with('error', 'Gagal menolak Non Food Payment: ' . $e->getMessage());
        }
    }

    public function markAsPaid(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBePaid()) {
            return back()->with('error', 'Payment ini tidak dapat ditandai sebagai dibayar.');
        }

        try {
            $nonFoodPayment->update([
                'status' => 'paid',
            ]);

            return back()->with('success', 'Non Food Payment berhasil ditandai sebagai dibayar.');

        } catch (\Exception $e) {
            \Log::error('Non Food Payment mark as paid error: ' . $e->getMessage());
            return back()->with('error', 'Gagal menandai Non Food Payment sebagai dibayar: ' . $e->getMessage());
        }
    }

    public function cancel(NonFoodPayment $nonFoodPayment)
    {
        if (!$nonFoodPayment->canBeCancelled()) {
            return back()->with('error', 'Payment ini tidak dapat dibatalkan.');
        }

        try {
            $nonFoodPayment->update([
                'status' => 'cancelled',
            ]);

            return back()->with('success', 'Non Food Payment berhasil dibatalkan.');

        } catch (\Exception $e) {
            \Log::error('Non Food Payment cancellation error: ' . $e->getMessage());
            return back()->with('error', 'Gagal membatalkan Non Food Payment: ' . $e->getMessage());
        }
    }
}
