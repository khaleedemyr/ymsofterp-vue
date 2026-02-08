<?php

namespace App\Http\Controllers;

use App\Models\RetailNonFood;
use App\Models\RetailNonFoodItem;
use App\Models\Outlet;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionOutletBudget;
use App\Models\PurchaseRequisition;
use App\Services\BudgetCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RetailNonFoodController extends Controller
{
    private function generateRetailNumber()
    {
        $prefix = 'RNF';
        $date = date('Ymd');
        
        // Cari nomor terakhir hari ini (termasuk data yang soft deleted)
        $lastNumber = RetailNonFood::withTrashed()
            ->where('retail_number', 'like', $prefix . $date . '%')
            ->orderBy('retail_number', 'desc')
            ->first();
            
        if ($lastNumber) {
            $sequence = (int) substr($lastNumber->retail_number, -4) + 1;
        } else {
            $sequence = 1;
        }
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;
        $outletExists = \DB::table('tbl_data_outlet')->where('id_outlet', $userOutletId)->exists();
        if (!$outletExists && $userOutletId != 1) {
            abort(403, 'Outlet tidak terdaftar');
        }
        
        // Check delete permission: only superadmin or warehouse division can delete
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11);

        // Get filter parameters
        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        
        // Query dengan join warehouse outlet dan category budget
        $query = RetailNonFood::query()
            ->with(['outlet', 'creator', 'items', 'categoryBudget'])
            ->orderByDesc('created_at');
            
        // Apply outlet filter
        if ($userOutletId != 1) {
            $query->where('outlet_id', $userOutletId);
        }

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('retail_number', 'like', "%{$search}%")
                  ->orWhereHas('outlet', function($outletQuery) use ($search) {
                      $outletQuery->where('nama_outlet', 'like', "%{$search}%");
                  });
            });
        }

        // Apply date filter
        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }
        
        $retailNonFoods = $query->paginate(10)->withQueryString();
        
        return Inertia::render('RetailNonFood/Index', [
            'user' => $user,
            'retailNonFoods' => $retailNonFoods,
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'canDelete' => $canDelete,
        ]);
    }

    public function create()
    {
        $user = auth()->user()->load('outlet');
        $userOutletId = $user->id_outlet;
        $outletExists = \DB::table('tbl_data_outlet')->where('id_outlet', $userOutletId)->exists();
        if (!$outletExists && $userOutletId != 1) {
            abort(403, 'Outlet tidak terdaftar');
        }
        if ($userOutletId == 1) {
            $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        } else {
            $outlets = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->get(['id_outlet', 'nama_outlet']);
        }
        
        // Ambil category budgets (hanya yang GLOBAL)
        // Urutkan ascending berdasarkan divisi, kemudian nama category
        $categoryBudgets = PurchaseRequisitionCategory::where('budget_type', 'GLOBAL')
            ->orderBy('division', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'division', 'subcategory', 'budget_limit', 'description']);
        
        // Ambil data supplier
        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
        
        return Inertia::render('RetailNonFood/Form', [
            'user' => $user,
            'outlets' => $outlets,
            'categoryBudgets' => $categoryBudgets,
            'suppliers' => $suppliers
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('RETAIL_NON_FOOD_STORE: Starting store process', [
            'user_id' => auth()->id(),
            'request_data' => $request->except(['invoices'])
        ]);

        try {
            $request->validate([
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'transaction_date' => 'required|date',
                'category_budget_id' => 'required|exists:purchase_requisition_categories,id',
                'payment_method' => 'required|in:cash,contra_bon',
                'supplier_id' => 'required|exists:suppliers,id',
                'items' => 'required|array|min:1',
                'items.*.item_name' => 'required|string',
                'items.*.qty' => 'required|numeric|min:0',
                'items.*.unit' => 'required|string',
                'items.*.price' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('RETAIL_NON_FOOD_STORE: Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->except(['invoices'])
            ]);
            return response()->json([
                'message' => 'Validasi gagal: ' . implode(', ', array_map(function($errors) {
                    return implode(', ', $errors);
                }, $e->errors()))
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generate nomor retail non food
            $retailNumber = $this->generateRetailNumber();

            // Hitung total amount dari items
            $totalAmount = collect($request->items)->sum(function ($item) {
                return (float)($item['qty'] ?? 0) * (float)($item['price'] ?? 0);
            });

            // Validasi budget sebelum simpan menggunakan BudgetCalculationService
            if ($request->category_budget_id) {
                $category = PurchaseRequisitionCategory::find($request->category_budget_id);
                if (!$category) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Category budget tidak valid'
                    ], 422);
                }

                // Hitung budget yang sudah digunakan (bulan berjalan)
                $currentMonth = date('n'); // 1-12
                $currentYear = date('Y');
                $dateFrom = date('Y-m-01', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                $dateTo = date('Y-m-t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
                
                // Use BudgetCalculationService for consistent validation
                $budgetService = new BudgetCalculationService();
                $validation = $budgetService->validateBudget(
                    categoryId: $request->category_budget_id,
                    outletId: $request->outlet_id, // For PER_OUTLET budget
                    currentAmount: $totalAmount,
                    dateFrom: $dateFrom,
                    dateTo: $dateTo
                );
                
                if (!$validation['valid']) {
                    DB::rollBack();
                    
                    // Get category and outlet info for better error message
                    $categoryName = $category->name ?? 'Unknown';
                    $outletName = 'All Outlets';
                    if ($request->outlet_id && $category->isPerOutletBudget()) {
                        $outlet = Outlet::find($request->outlet_id);
                        $outletName = $outlet ? $outlet->nama_outlet : 'Unknown';
                    }
                    
                    // Format error message with details
                    $budgetInfo = $validation['budget_info'] ?? [];
                    $budgetLimit = $budgetInfo['budget_type'] === 'PER_OUTLET' 
                        ? ($budgetInfo['outlet_budget'] ?? 0)
                        : ($budgetInfo['category_budget'] ?? 0);
                    $usedAmount = $budgetInfo['budget_type'] === 'PER_OUTLET'
                        ? ($budgetInfo['outlet_used_amount'] ?? 0)
                        : ($budgetInfo['category_used_amount'] ?? 0);
                    
                    $errorMessage = "Transaksi ditolak! Budget untuk kategori '{$categoryName}'";
                    if ($category->isPerOutletBudget()) {
                        $errorMessage .= " di outlet '{$outletName}'";
                    }
                    $errorMessage .= " telah terlampaui.\n\n";
                    $errorMessage .= "📊 Detail Budget:\n";
                    $errorMessage .= "• Budget yang ditetapkan: Rp " . number_format($budgetLimit, 0, ',', '.') . "\n";
                    $errorMessage .= "• Sudah Digunakan: Rp " . number_format($usedAmount, 0, ',', '.') . "\n";
                    $errorMessage .= "• Transaksi Baru: Rp " . number_format($totalAmount, 0, ',', '.') . "\n";
                    $errorMessage .= "• Total Setelah Transaksi Baru: Rp " . number_format($budgetInfo['total_with_current'] ?? ($usedAmount + $totalAmount), 0, ',', '.') . "\n";
                    $errorMessage .= "• Kelebihan: Rp " . number_format(($budgetInfo['total_with_current'] ?? ($usedAmount + $totalAmount)) - $budgetLimit, 0, ',', '.');
                    
                    return response()->json([
                        'message' => $errorMessage
                    ], 422);
                }
                
                // OLD CODE - DEPRECATED (keeping for reference, can be removed later)
                /*
                if ($category->isGlobalBudget()) {
                    // GLOBAL BUDGET: Calculate across all outlets
                    // Get PR IDs in this category for the month
                    $prIds = PurchaseRequisition::where('category_id', $request->category_budget_id)
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $currentMonth)
                        ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                        ->pluck('id')
                        ->toArray();
                    
                    // Get PO IDs that are linked to PRs in this category
                    $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->whereIn('poi.source_id', $prIds)
                        ->distinct()
                        ->pluck('poi.purchase_order_ops_id')
                        ->toArray();
                    
                    // Get paid amount from non_food_payments (same logic as Opex Report)
                    $paidAmountFromPo = DB::table('non_food_payments as nfp')
                        ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                        ->whereIn('nfp.status', ['paid', 'approved'])
                        ->where('nfp.status', '!=', 'cancelled')
                        ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                        ->sum('nfp.amount');
                    
                    // Get Retail Non Food amounts
                    $retailNonFoodApproved = RetailNonFood::where('category_budget_id', $request->category_budget_id)
                        ->whereYear('transaction_date', $currentYear)
                        ->whereMonth('transaction_date', $currentMonth)
                        ->where('status', 'approved')
                        ->sum('total_amount');
                    
                    $retailNonFoodPending = RetailNonFood::where('category_budget_id', $request->category_budget_id)
                        ->whereYear('transaction_date', $currentYear)
                        ->whereMonth('transaction_date', $currentMonth)
                        ->where('status', 'pending')
                        ->sum('total_amount');
                    
                    // Add Retail Non Food approved amount (directly paid, same as Opex Report)
                    $paidAmount = $paidAmountFromPo + $retailNonFoodApproved;
                    
                    // Get all PRs in this category for the month (exclude held PRs)
                    $allPrs = PurchaseRequisition::where('category_id', $request->category_budget_id)
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $currentMonth)
                        ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                        ->where('is_held', false) // Exclude held PRs
                        ->get();
                    
                    // Get PO totals per PR
                    // Exclude held PRs from unpaid calculation
                    $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                        ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->where('pr.category_id', $request->category_budget_id)
                        ->where('pr.is_held', false) // Exclude held PRs
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->where('poo.status', 'approved')
                        ->whereYear('poo.date', $currentYear)
                        ->whereMonth('poo.date', $currentMonth)
                        ->groupBy('pr.id')
                        ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                        ->pluck('po_total', 'pr_id')
                        ->toArray();
                    
                    // Get total paid per PR
                    // Exclude held PRs from unpaid calculation
                    $paidTotalsByPr = DB::table('non_food_payments as nfp')
                        ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                        ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->where('pr.category_id', $request->category_budget_id)
                        ->where('pr.is_held', false) // Exclude held PRs
                        ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                        ->whereIn('nfp.status', ['paid', 'approved'])
                        ->where('nfp.status', '!=', 'cancelled')
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->groupBy('pr.id')
                        ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                        ->pluck('total_paid', 'pr_id')
                        ->toArray();
                    
                    // Calculate unpaid for each PR (exclude held PRs)
                    $unpaidAmount = 0;
                    foreach ($allPrs as $pr) {
                        // Skip held PRs
                        if ($pr->is_held) {
                            continue;
                        }
                        
                        $prId = $pr->id;
                        $poTotal = $poTotalsByPr[$prId] ?? 0;
                        $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                        
                        // If PR hasn't been converted to PO, use PR amount
                        // If PR has been converted to PO, use PO total
                        $prTotalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                        $unpaidAmount += max(0, $prTotalAmount - $totalPaid);
                    }
                    
                    // Calculate category used amount = Paid (from non_food_payments) + Unpaid PR + Retail Non Food pending
                    // Same logic as Opex Report
                    $categoryUsedAmount = $paidAmount + $unpaidAmount + $retailNonFoodPending;
                    
                    // Total setelah ditambah transaksi baru (asumsi status pending)
                    $totalAfterNew = $categoryUsedAmount + $totalAmount;
                    
                    // Validasi budget
                    if ($totalAfterNew > $category->budget_limit) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Transaksi ditolak! Budget untuk kategori '{$category->name}' telah terlampaui.\n\n" .
                                       "📊 Detail Budget:\n" .
                                       "• Budget yang ditetapkan: Rp " . number_format($category->budget_limit, 0, ',', '.') . "\n" .
                                       "• Sudah Digunakan: Rp " . number_format($categoryUsedAmount, 0, ',', '.') . "\n" .
                                       "• Transaksi Baru: Rp " . number_format($totalAmount, 0, ',', '.') . "\n" .
                                       "• Total Setelah Transaksi Baru: Rp " . number_format($totalAfterNew, 0, ',', '.') . "\n" .
                                       "• Kelebihan: Rp " . number_format($totalAfterNew - $category->budget_limit, 0, ',', '.')
                        ], 422);
                    }
                } else if ($category->isPerOutletBudget()) {
                    // PER_OUTLET BUDGET: Calculate per specific outlet
                    if (!$request->outlet_id) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Outlet ID diperlukan untuk budget per outlet'
                        ], 422);
                    }
                    
                    // Get outlet budget allocation
                    $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $request->category_budget_id)
                        ->where('outlet_id', $request->outlet_id)
                        ->first();
                    
                    if (!$outletBudget) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Budget outlet tidak dikonfigurasi untuk kategori ini'
                        ], 422);
                    }
                    
                    // Get PR IDs for this outlet and category
                    $prIds = PurchaseRequisition::where('category_id', $request->category_budget_id)
                        ->where('outlet_id', $request->outlet_id)
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $currentMonth)
                        ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                        ->pluck('id')
                        ->toArray();
                    
                    // Get PO IDs linked to PRs in this outlet
                    $poIdsInCategory = DB::table('purchase_order_ops_items as poi')
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->whereIn('poi.source_id', $prIds)
                        ->distinct()
                        ->pluck('poi.purchase_order_ops_id')
                        ->toArray();
                    
                    // Get paid amount from non_food_payments for this outlet
                    $paidAmountFromPo = DB::table('non_food_payments as nfp')
                        ->whereIn('nfp.purchase_order_ops_id', $poIdsInCategory)
                        ->whereIn('nfp.status', ['paid', 'approved'])
                        ->where('nfp.status', '!=', 'cancelled')
                        ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                        ->sum('nfp.amount');
                    
                    // Get Retail Non Food for this outlet
                    $outletRetailNonFoodApproved = RetailNonFood::where('category_budget_id', $request->category_budget_id)
                        ->where('outlet_id', $request->outlet_id)
                        ->whereYear('transaction_date', $currentYear)
                        ->whereMonth('transaction_date', $currentMonth)
                        ->where('status', 'approved')
                        ->sum('total_amount');
                    
                    $outletRetailNonFoodPending = RetailNonFood::where('category_budget_id', $request->category_budget_id)
                        ->where('outlet_id', $request->outlet_id)
                        ->whereYear('transaction_date', $currentYear)
                        ->whereMonth('transaction_date', $currentMonth)
                        ->where('status', 'pending')
                        ->sum('total_amount');
                    
                    // Get all PRs for this outlet (exclude held PRs)
                    $allPrs = PurchaseRequisition::where('category_id', $request->category_budget_id)
                        ->where('outlet_id', $request->outlet_id)
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $currentMonth)
                        ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                        ->where('is_held', false)
                        ->get();
                    
                    // Get PO totals per PR for this outlet
                    $poTotalsByPr = DB::table('purchase_order_ops_items as poi')
                        ->leftJoin('purchase_order_ops as poo', 'poi.purchase_order_ops_id', '=', 'poo.id')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->where('pr.category_id', $request->category_budget_id)
                        ->where('pr.outlet_id', $request->outlet_id)
                        ->where('pr.is_held', false)
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->where('poo.status', 'approved')
                        ->whereYear('poo.date', $currentYear)
                        ->whereMonth('poo.date', $currentMonth)
                        ->groupBy('pr.id')
                        ->select('pr.id as pr_id', DB::raw('SUM(poi.total) as po_total'))
                        ->pluck('po_total', 'pr_id')
                        ->toArray();
                    
                    // Get total paid per PR for this outlet
                    $paidTotalsByPr = DB::table('non_food_payments as nfp')
                        ->leftJoin('purchase_order_ops as poo', 'nfp.purchase_order_ops_id', '=', 'poo.id')
                        ->leftJoin('purchase_order_ops_items as poi', 'poo.id', '=', 'poi.purchase_order_ops_id')
                        ->leftJoin('purchase_requisitions as pr', 'poi.source_id', '=', 'pr.id')
                        ->where('pr.category_id', $request->category_budget_id)
                        ->where('pr.outlet_id', $request->outlet_id)
                        ->where('pr.is_held', false)
                        ->whereBetween('nfp.payment_date', [$dateFrom, $dateTo])
                        ->whereIn('nfp.status', ['paid', 'approved'])
                        ->where('nfp.status', '!=', 'cancelled')
                        ->where('poi.source_type', 'purchase_requisition_ops')
                        ->groupBy('pr.id')
                        ->select('pr.id as pr_id', DB::raw('SUM(nfp.amount) as total_paid'))
                        ->pluck('total_paid', 'pr_id')
                        ->toArray();
                    
                    // Calculate unpaid for each PR
                    $unpaidAmount = 0;
                    foreach ($allPrs as $pr) {
                        $prId = $pr->id;
                        $poTotal = $poTotalsByPr[$prId] ?? 0;
                        $totalPaid = $paidTotalsByPr[$prId] ?? 0;
                        
                        $prTotalAmount = $poTotal > 0 ? $poTotal : $pr->amount;
                        $unpaidAmount += max(0, $prTotalAmount - $totalPaid);
                    }
                    
                    // Total used = Paid (from non_food_payments + RNF approved) + Unpaid PR + RNF pending
                    $paidAmount = $paidAmountFromPo + $outletRetailNonFoodApproved;
                    $outletUsedAmount = $paidAmount + $unpaidAmount + $outletRetailNonFoodPending;
                    
                    // Total setelah ditambah transaksi baru (asumsi status pending)
                    $totalAfterNew = $outletUsedAmount + $totalAmount;
                    
                    // Validasi budget per outlet
                    if ($totalAfterNew > $outletBudget->allocated_budget) {
                        DB::rollBack();
                        $outlet = Outlet::find($request->outlet_id);
                        $outletName = $outlet ? $outlet->nama_outlet : 'Unknown';
                        return response()->json([
                            'message' => "Transaksi ditolak! Budget untuk kategori '{$category->name}' di outlet '{$outletName}' telah terlampaui.\n\n" .
                                       "📊 Detail Budget:\n" .
                                       "• Budget yang ditetapkan: Rp " . number_format($outletBudget->allocated_budget, 0, ',', '.') . "\n" .
                                       "• Sudah Digunakan: Rp " . number_format($outletUsedAmount, 0, ',', '.') . "\n" .
                                       "• Transaksi Baru: Rp " . number_format($totalAmount, 0, ',', '.') . "\n" .
                                       "• Total Setelah Transaksi Baru: Rp " . number_format($totalAfterNew, 0, ',', '.') . "\n" .
                                       "• Kelebihan: Rp " . number_format($totalAfterNew - $outletBudget->allocated_budget, 0, ',', '.')
                        ], 422);
                    }
                }
                */
                // END OF OLD CODE - DEPRECATED
            }

            // Cek total transaksi hari ini
            $dailyTotal = RetailNonFood::whereDate('transaction_date', $request->transaction_date)
                ->where('status', 'approved')
                ->sum('total_amount');

            // Buat retail non food
            $retailNonFood = RetailNonFood::create([
                'supplier_id' => $request->supplier_id,
                'payment_method' => $request->payment_method,
                'retail_number' => $retailNumber,
                'outlet_id' => $request->outlet_id,
                'category_budget_id' => $request->category_budget_id,
                'created_by' => auth()->id(),
                'transaction_date' => $request->transaction_date,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'status' => 'approved'
            ]);

            // Simpan items (tanpa inventory processing)
            foreach ($request->items as $item) {
                RetailNonFoodItem::create([
                    'retail_non_food_id' => $retailNonFood->id,
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price']
                ]);
            }

            // Setelah RetailNonFood berhasil dibuat
            if ($request->hasFile('invoices')) {
                foreach ($request->file('invoices') as $file) {
                    if (in_array($file->extension(), ['jpg', 'jpeg', 'png'])) {
                        $path = $file->store('retail_non_food_invoices', 'public');
                        $retailNonFood->invoices()->create([
                            'file_path' => $path
                        ]);
                    }
                }
            }

            DB::commit();

            // Cek apakah total hari ini sudah melebihi 500rb
            if ($dailyTotal + $totalAmount >= 500000) {
                return response()->json([
                    'message' => 'Transaksi berhasil disimpan, namun total pembelian hari ini sudah melebihi Rp 500.000',
                    'data' => $retailNonFood->load('items')
                ], 201);
            }

            return response()->json([
                'message' => 'Transaksi berhasil disimpan',
                'data' => $retailNonFood->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('RETAIL_NON_FOOD_STORE: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->except(['invoices'])
            ]);
            
            return response()->json([
                'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan saat menyimpan transaksi'
            ], 500);
        }
    }

    public function show($id)
    {
        $retailNonFood = RetailNonFood::with(['outlet', 'creator', 'items', 'warehouseOutlet', 'invoices', 'categoryBudget'])
            ->findOrFail($id);

        return Inertia::render('RetailNonFood/Detail', [
            'retailNonFood' => $retailNonFood
        ]);
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            
            // Check permission: only superadmin or warehouse division can delete
            if ($user->id_role !== '5af56935b011a' && $user->division_id != 11) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus data ini'
                ], 403);
            }

            $retailNonFood = RetailNonFood::findOrFail($id);

            \Log::info('RETAIL_NON_FOOD_DELETE: Starting deletion process', [
                'user_id' => $user->id,
                'retail_non_food_id' => $id,
                'retail_number' => $retailNonFood->retail_number
            ]);

            DB::beginTransaction();
            
            // Hapus retail non food dan items (tanpa inventory rollback)
            RetailNonFoodItem::where('retail_non_food_id', $retailNonFood->id)->delete();
            $retailNonFood->delete();
            
            DB::commit();

            \Log::info('RETAIL_NON_FOOD_DELETE: Deletion completed successfully', [
                'retail_non_food_id' => $id,
                'retail_number' => $retailNonFood->retail_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi retail non food berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('RETAIL_NON_FOOD_DELETE: Deletion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'retail_non_food_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function dailyTotal(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'transaction_date' => 'required|date',
        ]);
        
        $total = RetailNonFood::where('outlet_id', $request->outlet_id)
            ->whereDate('transaction_date', $request->transaction_date)
            ->sum('total_amount');
            
        return response()->json(['total' => $total]);
    }

    public function getCategoryBudgets(Request $request)
    {
        try {
            $request->validate([
                'outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            ]);

            $outletId = $request->input('outlet_id');

            // Ambil category budgets dengan budget_type GLOBAL
            $globalCategories = PurchaseRequisitionCategory::where('budget_type', 'GLOBAL')
                ->orderBy('division', 'asc')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'division', 'subcategory', 'budget_limit', 'description', 'budget_type']);

            // Ambil category budgets dengan budget_type PER_OUTLET untuk outlet yang dipilih
            $perOutletCategories = collect();
            if ($outletId) {
                $perOutletCategoryIds = PurchaseRequisitionOutletBudget::where('outlet_id', $outletId)
                    ->pluck('category_id')
                    ->toArray();
                
                if (!empty($perOutletCategoryIds)) {
                    $perOutletCategories = PurchaseRequisitionCategory::where('budget_type', 'PER_OUTLET')
                        ->whereIn('id', $perOutletCategoryIds)
                        ->orderBy('division', 'asc')
                        ->orderBy('name', 'asc')
                        ->get(['id', 'name', 'division', 'subcategory', 'budget_limit', 'description', 'budget_type']);
                }
            }

            // Gabungkan dan urutkan
            $categoryBudgets = $globalCategories->merge($perOutletCategories)
                ->sortBy([
                    ['division', 'asc'],
                    ['name', 'asc']
                ])
                ->values();

            return response()->json([
                'success' => true,
                'data' => $categoryBudgets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get category budgets: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBudgetInfo(Request $request)
    {
        try {
            $request->validate([
                'category_budget_id' => 'nullable|exists:purchase_requisition_categories,id',
            ]);

            if (!$request->category_budget_id) {
                return response()->json([
                    'budget_info' => null
                ]);
            }

            $category = PurchaseRequisitionCategory::findOrFail($request->category_budget_id);
            
            if ($category->budget_type !== 'GLOBAL') {
                return response()->json([
                    'budget_info' => null,
                    'message' => 'Category budget harus GLOBAL'
                ]);
            }

            // Use BudgetCalculationService for consistent calculation (same as PurchaseRequisitionController)
            $currentMonth = date('n'); // 1-12
            $currentYear = date('Y');
            $dateFrom = date('Y-m-01', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
            $dateTo = date('Y-m-t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
            
            // For GLOBAL budget type, outletId must be null (no outlet filter)
            $budgetService = new BudgetCalculationService();
            $budgetInfoResult = $budgetService->getBudgetInfo(
                categoryId: $category->id,
                outletId: null, // GLOBAL budget: no outlet filter
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                currentAmount: 0 // No current amount for budget info
            );
            
            if (!$budgetInfoResult['success']) {
                return response()->json([
                    'budget_info' => null,
                    'error' => $budgetInfoResult['message'] ?? 'Failed to get budget information'
                ], 500);
            }
            
            // Get Retail Non Food pending amount (for display only, not included in used amount)
            $retailNonFoodPending = RetailNonFood::where('category_budget_id', $request->category_budget_id)
                ->whereBetween('transaction_date', [$dateFrom, $dateTo])
                ->where('status', 'pending')
                ->sum('total_amount');
            
            // Extract values from budget service result
            $budgetInfo = $budgetInfoResult;
            $retailNonFoodApproved = $budgetInfo['breakdown']['retail_non_food'] ?? 0;
            $paidAmount = $budgetInfo['breakdown']['po_total'] + $retailNonFoodApproved; // PO total + RNF approved
            $unpaidAmount = $budgetInfo['breakdown']['pr_unpaid'] ?? 0;
            $categoryUsedAmount = $budgetInfo['category_used_amount'] ?? 0;
            $remainingBudget = $budgetInfo['category_remaining_amount'] ?? 0;
            
            // Calculate approved and unapproved amounts for display (same logic as PurchaseRequisitionController)
            // Support both old structure (category at PR level) and new structure (category at items level)
            $prIds = DB::table('purchase_requisitions as pr')
                ->leftJoin('purchase_requisition_items as pri', 'pr.id', '=', 'pri.purchase_requisition_id')
                ->where(function($q) use ($category) {
                    $q->where('pr.category_id', $category->id)
                      ->orWhere('pri.category_id', $category->id);
                })
                ->whereYear('pr.created_at', $currentYear)
                ->whereMonth('pr.created_at', $currentMonth)
                ->whereIn('pr.status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->where('pr.is_held', false)
                ->distinct()
                ->pluck('pr.id')
                ->toArray();
            
            $prsForStatus = PurchaseRequisition::whereIn('id', $prIds)->get();
            $approvedAmount = 0;
            $unapprovedAmount = 0;
            
            foreach ($prsForStatus as $pr) {
                // For PR Ops mode: calculate based on items in this category
                if (in_array($pr->mode, ['pr_ops', 'purchase_payment'])) {
                    $categoryItemsSubtotal = DB::table('purchase_requisition_items')
                        ->where('purchase_requisition_id', $pr->id)
                        ->where('category_id', $category->id)
                        ->sum('subtotal');
                    $prAmount = $categoryItemsSubtotal ?? 0;
                } else {
                    // For other modes: use PR amount
                    $prAmount = $pr->amount;
                }
                
                if (in_array($pr->status, ['APPROVED', 'PROCESSED', 'COMPLETED'])) {
                    $approvedAmount += $prAmount;
                }
                if ($pr->status === 'SUBMITTED') {
                    $unapprovedAmount += $prAmount;
                }
            }
            
            $totalApprovedAmount = $approvedAmount + $retailNonFoodApproved;
            $totalUnapprovedAmount = $unapprovedAmount + $retailNonFoodPending;
            
            // Persentase penggunaan
            $budgetPercentage = $category->budget_limit > 0 
                ? round(($categoryUsedAmount / $category->budget_limit) * 100, 2) 
                : 0;

            // Calculate purchase requisition used amount (paid + unpaid)
            $purchaseRequisitionUsed = $budgetInfo['breakdown']['po_total'] + $unpaidAmount;
            
            // Calculate retail non food used amount (for display: approved + pending)
            $retailNonFoodUsed = $retailNonFoodApproved + $retailNonFoodPending;
            
            return response()->json([
                'budget_info' => [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'division' => $category->division,
                    'subcategory' => $category->subcategory,
                    'budget_limit' => $category->budget_limit,
                    'category_used_amount' => $categoryUsedAmount, // Paid + Unpaid (from BudgetCalculationService)
                    'total_used' => $categoryUsedAmount, // Alias for frontend compatibility
                    'paid_amount' => $paidAmount, // PO total + RNF approved
                    'unpaid_amount' => $unpaidAmount, // PR unpaid (from BudgetCalculationService)
                    'retail_non_food_approved' => $retailNonFoodApproved,
                    'retail_non_food_pending' => $retailNonFoodPending,
                    'retail_non_food_used' => $retailNonFoodUsed, // For frontend display
                    'purchase_requisition_used' => $purchaseRequisitionUsed, // For frontend display
                    'approved_amount' => $totalApprovedAmount,
                    'unapproved_amount' => $totalUnapprovedAmount,
                    'remaining_budget' => $remainingBudget,
                    'budget_percentage' => $budgetPercentage,
                    'current_month' => $currentMonth,
                    'current_year' => $currentYear,
                    // Include breakdown from BudgetCalculationService for consistency
                    'breakdown' => $budgetInfo['breakdown'] ?? [],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'budget_info' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: List retail non food for mobile app (approval-app)
     */
    public function apiIndex(Request $request)
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;

        $search = $request->get('search', '');
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $outletId = $request->get('outlet_id');
        $perPage = (int) $request->get('per_page', 20);

        $query = RetailNonFood::query()
            ->with(['outlet', 'creator', 'items', 'categoryBudget', 'supplier'])
            ->orderByDesc('created_at');

        if ($userOutletId != 1) {
            $query->where('outlet_id', $userOutletId);
        } elseif ($outletId !== null && $outletId !== '') {
            $query->where('outlet_id', $outletId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('retail_number', 'like', "%{$search}%")
                    ->orWhereHas('outlet', function ($oq) use ($search) {
                        $oq->where('nama_outlet', 'like', "%{$search}%");
                    })
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        $list = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    /**
     * API: Create form data for mobile app (outlets, category_budgets, suppliers)
     */
    public function apiCreateData()
    {
        $user = auth()->user();
        $userOutletId = $user->id_outlet;

        if ($userOutletId == 1) {
            $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        } else {
            $outlets = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->get(['id_outlet', 'nama_outlet']);
        }

        $categoryBudgets = PurchaseRequisitionCategory::where('budget_type', 'GLOBAL')
            ->orderBy('division', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'division', 'subcategory', 'budget_limit', 'description']);

        $suppliers = DB::table('suppliers')
            ->where('status', 'active')
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'outlets' => $outlets,
            'category_budgets' => $categoryBudgets,
            'suppliers' => $suppliers,
            'user_outlet_id' => $userOutletId,
        ]);
    }

    /**
     * API: Show single retail non food for mobile app
     */
    public function apiShow($id)
    {
        $retailNonFood = RetailNonFood::with(['outlet', 'creator', 'items', 'categoryBudget', 'supplier', 'invoices'])->findOrFail($id);

        $user = auth()->user();
        if ($user->id_outlet != 1 && $retailNonFood->outlet_id != $user->id_outlet) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'retail_non_food' => $retailNonFood,
        ]);
    }

    /**
     * API: Store retail non food from mobile app (delegates to store)
     */
    public function apiStore(Request $request)
    {
        return $this->store($request);
    }
} 