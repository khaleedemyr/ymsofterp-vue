<?php

namespace App\Http\Controllers;

use App\Models\FoodPayment;
use App\Models\FoodPaymentContraBon;
use App\Models\ContraBon;
use App\Services\NotificationService;
use App\Services\BankBookService;
use App\Services\JurnalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FoodPaymentController extends Controller
{
    // List all food payments
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $loadData = $request->input('load_data', false);
        $perPage = $request->input('per_page', 10);
        
        // Jika belum ada request untuk load data dan tidak ada filter/search, return empty
        if (!$loadData && !$search && !$status && !$dateFrom && !$dateTo) {
            return inertia('FoodPayment/Index', [
                'payments' => new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]),
                    0,
                    $perPage,
                    1,
                    ['path' => $request->url(), 'query' => $request->query()]
                ),
                'filters' => $request->only(['search', 'status', 'date_from', 'date_to', 'per_page']),
                'dataLoaded' => false
            ]);
        }
        
        // PERFORMANCE OPTIMIZATION: Use leftJoin instead of eager loading + whereHas
        $query = FoodPayment::query()
            ->leftJoin('suppliers as s', 'food_payments.supplier_id', '=', 's.id')
            ->leftJoin('users as u', 'food_payments.created_by', '=', 'u.id')
            ->leftJoin('users as fm', 'food_payments.finance_manager_approved_by', '=', 'fm.id')
            ->leftJoin('users as gm', 'food_payments.gm_finance_approved_by', '=', 'gm.id')
            ->select(
                'food_payments.*',
                's.name as supplier_name',
                'u.nama_lengkap as creator_name',
                'fm.nama_lengkap as finance_manager_name',
                'gm.nama_lengkap as gm_finance_name'
            )
            ->distinct()
            ->orderByDesc('food_payments.created_at');

        // Optimize search with leftJoin instead of whereHas
        if ($search) {
            $query->where(function($q) use ($search) {
                // Search di kolom langsung
                $q->where('food_payments.number', 'like', "%$search%")
                  ->orWhere('food_payments.payment_type', 'like', "%$search%")
                  ->orWhere('food_payments.status', 'like', "%$search%")
                  ->orWhere('food_payments.notes', 'like', "%$search%")
                  // Search di total (format angka)
                  ->orWhereRaw("CAST(food_payments.total AS CHAR) LIKE ?", ["%$search%"])
                  // Search di tanggal
                  ->orWhereDate('food_payments.date', 'like', "%$search%")
                  ->orWhereRaw("DATE_FORMAT(food_payments.date, '%d-%m-%Y') LIKE ?", ["%$search%"])
                  ->orWhereRaw("DATE_FORMAT(food_payments.date, '%Y-%m-%d') LIKE ?", ["%$search%"])
                  // Search di supplier name (dari join)
                  ->orWhere('s.name', 'like', "%$search%")
                  // Search di creator name (dari join)
                  ->orWhere('u.nama_lengkap', 'like', "%$search%")
                  // Search di finance manager name (dari join)
                  ->orWhere('fm.nama_lengkap', 'like', "%$search%")
                  // Search di GM finance name (dari join)
                  ->orWhere('gm.nama_lengkap', 'like', "%$search%");
            });
            
            // Search di invoice numbers dari contra bon (subquery untuk contra bon)
            $query->orWhereExists(function($q) use ($search) {
                $q->select(DB::raw(1))
                  ->from('food_payment_contra_bons as fpcb')
                  ->join('food_contra_bons as fc', 'fpcb.contra_bon_id', '=', 'fc.id')
                  ->whereColumn('fpcb.food_payment_id', 'food_payments.id')
                  ->where('fc.supplier_invoice_number', 'like', "%$search%");
            });
        }
        
        if ($status) {
            $query->where('food_payments.status', $status);
        }
        if ($dateFrom) {
            $query->whereDate('food_payments.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('food_payments.date', '<=', $dateTo);
        }

        $payments = $query->paginate($perPage)->withQueryString();
        
        // PERFORMANCE OPTIMIZATION: Batch query contraBons untuk semua payments sekaligus
        $paymentIds = $payments->getCollection()->pluck('id')->toArray();
        
        $contraBonsMap = [];
        $locationsMap = []; // Map payment_id => [locations]
        if (!empty($paymentIds)) {
            try {
                $allContraBons = DB::table('food_payment_contra_bons as fpcb')
                    ->join('food_contra_bons as fc', 'fpcb.contra_bon_id', '=', 'fc.id')
                    ->whereIn('fpcb.food_payment_id', $paymentIds)
                    ->select(
                        'fpcb.food_payment_id',
                        'fc.supplier_invoice_number'
                    )
                    ->get();
                
                foreach ($allContraBons as $cb) {
                    if (!isset($contraBonsMap[$cb->food_payment_id])) {
                        $contraBonsMap[$cb->food_payment_id] = [];
                    }
                    if ($cb->supplier_invoice_number) {
                        $contraBonsMap[$cb->food_payment_id][] = $cb->supplier_invoice_number;
                    }
                }
                
                // Remove duplicates
                foreach ($contraBonsMap as $paymentId => $invoices) {
                    $contraBonsMap[$paymentId] = array_values(array_unique(array_filter($invoices)));
                }
                
                // Query locations (outlets/warehouses) dari contra bon sources
                $allLocations = DB::table('food_payment_contra_bons as fpcb')
                    ->join('food_contra_bons as fc', 'fpcb.contra_bon_id', '=', 'fc.id')
                    ->leftJoin('retail_food as rf', function($join) {
                        $join->on('fc.source_id', '=', 'rf.id')
                             ->where('fc.source_type', '=', 'retail_food');
                    })
                    ->leftJoin('retail_non_food as rnf', function($join) {
                        $join->on('fc.source_id', '=', 'rnf.id')
                             ->where('fc.source_type', '=', 'retail_non_food');
                    })
                    ->leftJoin('retail_warehouse_food as rwf', function($join) {
                        $join->on('fc.source_id', '=', 'rwf.id')
                             ->where('fc.source_type', '=', 'warehouse_retail_food');
                    })
                    // Join PO untuk ambil warehouse dari PR
                    ->leftJoin('purchase_order_foods as po', 'fc.po_id', '=', 'po.id')
                    ->leftJoin('purchase_order_food_items as poi', 'poi.purchase_order_food_id', '=', 'po.id')
                    ->leftJoin('pr_food_items as pri', 'poi.pr_food_item_id', '=', 'pri.id')
                    ->leftJoin('pr_foods as prf', 'pri.pr_food_id', '=', 'prf.id')
                    // Join outlets untuk retail food & retail non food
                    ->leftJoin('tbl_data_outlet as o', function($join) {
                        $join->on('o.id_outlet', '=', 'rf.outlet_id')
                             ->orOn('o.id_outlet', '=', 'rnf.outlet_id');
                    })
                    // Join warehouses untuk retail warehouse food & PR
                    ->leftJoin('warehouses as w', function($join) {
                        $join->on('w.id', '=', 'rwf.warehouse_id')
                             ->orOn('w.id', '=', 'prf.warehouse_id');
                    })
                    ->whereIn('fpcb.food_payment_id', $paymentIds)
                    ->select(
                        'fpcb.food_payment_id',
                        'fc.source_type',
                        'o.id_outlet',
                        'o.nama_outlet',
                        'w.id as warehouse_id',
                        'w.name as warehouse_name'
                    )
                    ->distinct()
                    ->get();
                
                foreach ($allLocations as $loc) {
                    if (!isset($locationsMap[$loc->food_payment_id])) {
                        $locationsMap[$loc->food_payment_id] = [];
                    }
                    
                    // Prioritas: outlet dulu, baru warehouse
                    if ($loc->id_outlet && $loc->nama_outlet) {
                        $locationsMap[$loc->food_payment_id][$loc->id_outlet] = [
                            'type' => 'outlet',
                            'id' => $loc->id_outlet,
                            'name' => $loc->nama_outlet
                        ];
                    } elseif ($loc->warehouse_id && $loc->warehouse_name) {
                        $locationsMap[$loc->food_payment_id][$loc->warehouse_id] = [
                            'type' => 'warehouse',
                            'id' => $loc->warehouse_id,
                            'name' => $loc->warehouse_name
                        ];
                    }
                }
                
                // Convert to array values (remove keys)
                foreach ($locationsMap as $paymentId => $locations) {
                    $locationsMap[$paymentId] = array_values($locations);
                }
                
            } catch (\Exception $e) {
                \Log::error('Error batch query contra bons', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Eager load supplier, creator, financeManager, gmFinance untuk backward compatibility
        $supplierIds = $payments->getCollection()
            ->pluck('supplier_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        
        $suppliersMap = [];
        if (!empty($supplierIds)) {
            $suppliers = \App\Models\Supplier::whereIn('id', $supplierIds)->get();
            foreach ($suppliers as $supplier) {
                $suppliersMap[$supplier->id] = $supplier;
            }
        }
        
        $creatorIds = $payments->getCollection()
            ->pluck('created_by')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        
        $creatorsMap = [];
        if (!empty($creatorIds)) {
            $creators = \App\Models\User::whereIn('id', $creatorIds)->get();
            foreach ($creators as $creator) {
                $creatorsMap[$creator->id] = $creator;
            }
        }
        
        $financeManagerIds = $payments->getCollection()
            ->pluck('finance_manager_approved_by')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        
        $financeManagersMap = [];
        if (!empty($financeManagerIds)) {
            $financeManagers = \App\Models\User::whereIn('id', $financeManagerIds)->get();
            foreach ($financeManagers as $fm) {
                $financeManagersMap[$fm->id] = $fm;
            }
        }
        
        $gmFinanceIds = $payments->getCollection()
            ->pluck('gm_finance_approved_by')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        
        $gmFinancesMap = [];
        if (!empty($gmFinanceIds)) {
            $gmFinances = \App\Models\User::whereIn('id', $gmFinanceIds)->get();
            foreach ($gmFinances as $gm) {
                $gmFinancesMap[$gm->id] = $gm;
            }
        }
        
        // Transform payments untuk menambahkan invoice numbers (dari batch query)
        $payments->getCollection()->transform(function($payment) use ($contraBonsMap, $locationsMap, $suppliersMap, $creatorsMap, $financeManagersMap, $gmFinancesMap) {
            // Attach relations for backward compatibility
            if ($payment->supplier_id && isset($suppliersMap[$payment->supplier_id])) {
                $payment->supplier = $suppliersMap[$payment->supplier_id];
            }
            if ($payment->created_by && isset($creatorsMap[$payment->created_by])) {
                $payment->creator = $creatorsMap[$payment->created_by];
            }
            if ($payment->finance_manager_approved_by && isset($financeManagersMap[$payment->finance_manager_approved_by])) {
                $payment->financeManager = $financeManagersMap[$payment->finance_manager_approved_by];
            }
            if ($payment->gm_finance_approved_by && isset($gmFinancesMap[$payment->gm_finance_approved_by])) {
                $payment->gmFinance = $gmFinancesMap[$payment->gm_finance_approved_by];
            }
            
            // Get invoice numbers from batch query result
            $payment->invoice_numbers = isset($contraBonsMap[$payment->id]) ? $contraBonsMap[$payment->id] : [];
            $payment->contraBons = collect([]); // Empty collection untuk backward compatibility
            
            // Get locations (outlets/warehouses) from batch query result
            $payment->locations = isset($locationsMap[$payment->id]) ? $locationsMap[$payment->id] : [];
            
            return $payment;
        });

        return inertia('FoodPayment/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to', 'per_page']),
            'dataLoaded' => true
        ]);
    }

    // Show create form
    public function create()
    {
        $banks = \App\Models\BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get();
        
        // Transform untuk include outlet name (sama seperti di BankAccount/Index)
        $banks = $banks->map(function($bank) {
            return [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number,
                'account_name' => $bank->account_name,
                'outlet_id' => $bank->outlet_id,
                'outlet' => $bank->outlet ? [
                    'id_outlet' => $bank->outlet->id_outlet,
                    'nama_outlet' => $bank->outlet->nama_outlet,
                ] : null,
                'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
            ];
        });
        
        // Get COA list for payment
        $coas = \DB::table('chart_of_accounts')
            ->where('is_active', 1)
            ->orderBy('code')
            ->get()
            ->map(function($coa) {
                return [
                    'id' => $coa->id,
                    'code' => $coa->code,
                    'name' => $coa->name,
                    'display_name' => $coa->code . ' - ' . $coa->name,
                ];
            });
        
        return inertia('FoodPayment/Form', [
            'banks' => $banks,
            'coas' => $coas,
        ]);
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
                'outlet_payments' => 'nullable|array',
                'outlet_payments.*.outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
                'outlet_payments.*.warehouse_id' => 'nullable|exists:warehouses,id',
                'outlet_payments.*.amount' => 'required|numeric|min:0',
                'outlet_payments.*.bank_id' => 'nullable|required_if:payment_type,Transfer,Giro|exists:bank_accounts,id',
                'outlet_payments.*.coa_id' => 'nullable|exists:chart_of_accounts,id',
                'outlet_payments.*.location_key' => 'nullable|string',
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
                'bank_id' => in_array($validated['payment_type'], ['Transfer', 'Giro']) ? ($validated['bank_id'] ?? null) : null,
                'notes' => $validated['notes'] ?? null,
                'bukti_transfer_path' => $buktiPath,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);
            \Log::info('FoodPaymentController@store - FoodPayment created', $payment->toArray());

            // Simpan relasi ke contra bon
            foreach ($contraBons as $cb) {
                FoodPaymentContraBon::create([
                    'food_payment_id' => $payment->id,
                    'contra_bon_id' => $cb->id,
                ]);
                // Status contra bon tetap 'approved', tidak diubah menjadi 'paid' sampai payment di-mark as paid
            }

            // Save payment per outlet if provided
            if ($request->has('outlet_payments') && is_array($request->outlet_payments)) {
                \Log::info('FoodPaymentController@store - outlet_payments received', $request->outlet_payments);
                foreach ($request->outlet_payments as $outletPayment) {
                    \Log::info('FoodPaymentController@store - Processing outlet payment', $outletPayment);
                    if (!empty($outletPayment['amount']) && $outletPayment['amount'] > 0) {
                        // Validate bank_id if payment method is Transfer or Giro
                        $bankId = null;
                        if (in_array($validated['payment_type'], ['Transfer', 'Giro'])) {
                            if (empty($outletPayment['bank_id'])) {
                                DB::rollback();
                                return response()->json([
                                    'success' => false, 
                                    'message' => 'Bank harus dipilih untuk setiap outlet dengan metode pembayaran ' . $validated['payment_type'] . '.'
                                ], 422);
                            }
                            $bankId = $outletPayment['bank_id'];
                        }
                        
                        $outletPaymentData = [
                            'food_payment_id' => $payment->id,
                            'outlet_id' => $outletPayment['outlet_id'] ?? null,
                            'warehouse_id' => $outletPayment['warehouse_id'] ?? null,
                            'amount' => $outletPayment['amount'],
                            'bank_id' => $bankId,
                            'coa_id' => $outletPayment['coa_id'] ?? null,
                            'location_key' => $outletPayment['location_key'] ?? null,
                        ];
                        
                        \Log::info('FoodPaymentController@store - Creating FoodPaymentOutlet', $outletPaymentData);
                        \App\Models\FoodPaymentOutlet::create($outletPaymentData);
                    }
                }
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
        $payment = FoodPayment::with(['supplier', 'creator', 'financeManager', 'gmFinance', 'contraBons.purchaseOrder', 'contraBons.retailFood.outlet', 'contraBons.warehouseRetailFood.warehouse', 'paymentOutlets.outlet', 'paymentOutlets.bank'])->findOrFail($id);
        
        // Transform contra bons to include source type and outlet information
        $payment->contra_bons = $payment->contraBons ? $payment->contraBons->map(function($contraBon) {
            $sourceTypeDisplay = 'Unknown';
            $outletNames = [];
            
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                if ($contraBon->purchaseOrder->source_type === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
                    // PR Foods tidak punya outlet (global)
                    $outletNames = [];
                } elseif ($contraBon->purchaseOrder->source_type === 'ro_supplier') {
                    $sourceTypeDisplay = 'RO Supplier';
                    // Get outlet names for RO Supplier
                    $outletData = \DB::table('food_floor_orders as fo')
                        ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                        ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                        ->where('poi.purchase_order_food_id', $contraBon->purchaseOrder->id)
                        ->select('o.nama_outlet')
                        ->distinct()
                        ->get();
                    
                    $outletNames = $outletData->pluck('nama_outlet')->filter()->unique()->toArray();
                }
            } elseif ($contraBon->source_type === 'retail_food') {
                $sourceTypeDisplay = 'Retail Food';
                // Get outlet name for Retail Food from outlet relationship
                if ($contraBon->retailFood && $contraBon->retailFood->outlet && $contraBon->retailFood->outlet->nama_outlet) {
                    $outletNames = [$contraBon->retailFood->outlet->nama_outlet];
                }
            } elseif ($contraBon->source_type === 'warehouse_retail_food' && $contraBon->warehouseRetailFood) {
                $sourceTypeDisplay = 'Warehouse Retail Food';
                // Warehouse Retail Food tidak punya outlet langsung (hanya punya warehouse)
                // Jika perlu outlet name, bisa diambil dari warehouse jika warehouse punya outlet
                $outletNames = [];
            }
            
            $contraBon->source_type_display = $sourceTypeDisplay;
            $contraBon->outlet_names = $outletNames;
            
            return $contraBon;
        }) : collect();
        
        return inertia('FoodPayment/Show', [
            'payment' => $payment
        ]);
    }

    // Approve payment (Finance Manager)
    public function approve(Request $request, $id)
    {
        $request->validate([
            'approved' => 'required|boolean',
            'note' => 'nullable|string'
        ]);

        $user = Auth::user();
        $foodPayment = FoodPayment::with('contraBons')->findOrFail($id);

        // Superadmin check
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';

        // Finance Manager Approval (First level)
        if (
            ($user->id_jabatan == 160 && $user->status == 'A' && $foodPayment->status == 'draft' && !$foodPayment->finance_manager_approved_at)
            || ($isSuperadmin && $foodPayment->status == 'draft' && !$foodPayment->finance_manager_approved_at)
        ) {
            DB::beginTransaction();
            try {
                // Jika di-reject, ambil contra bon ids dulu sebelum update status
                $contraBonIds = [];
                if (!$request->approved) {
                    // Refresh relationship untuk memastikan data ter-load
                    $foodPayment->load('contraBons');
                    $contraBonIds = $foodPayment->contraBons->pluck('id')->toArray();
                }
                
                $foodPayment->update([
                    'finance_manager_approved_at' => now(),
                    'finance_manager_approved_by' => $user->id,
                    'finance_manager_note' => $request->note,
                    'status' => $request->approved ? 'draft' : 'rejected' // Tetap draft setelah Finance Manager approve
                ]);

                // Jika di-reject, kembalikan status Contra Bon ke 'approved' dan hapus relasi
                if (!$request->approved && !empty($contraBonIds)) {
                    ContraBon::whereIn('id', $contraBonIds)
                        ->update(['status' => 'approved']);
                    
                    // Hapus relasi FoodPaymentContraBon agar Contra Bon bisa digunakan lagi
                    FoodPaymentContraBon::where('food_payment_id', $foodPayment->id)->delete();
                }

                // Log activity
                \App\Models\ActivityLog::create([
                    'user_id' => $user->id,
                    'activity_type' => $request->approved ? 'approve' : 'reject',
                    'module' => 'food_payment',
                    'description' => ($request->approved ? 'Approve' : 'Reject') . ' Food Payment (Finance Manager): ' . $foodPayment->number,
                    'ip_address' => $request->ip(),
                ]);

                // Send notification to GM Finance if approved
                if ($request->approved) {
                    $gmFinances = \DB::table('users')
                        ->where('id_jabatan', 152)
                        ->where('status', 'A')
                        ->pluck('id');
                    $this->sendNotification(
                        $gmFinances,
                        'food_payment_approval',
                        'Approval Food Payment',
                        "Food Payment {$foodPayment->number} menunggu approval Anda.",
                        route('food-payments.show', $foodPayment->id)
                    );
                } else {
                    // Send notification to creator if rejected
                    if ($foodPayment->created_by) {
                        \App\Models\Notification::create([
                            'user_id' => $foodPayment->created_by,
                            'type' => 'food_payment_approval',
                            'title' => 'Food Payment Ditolak',
                            'message' => "Food Payment {$foodPayment->number} telah ditolak oleh Finance Manager. Status Contra Bon telah dikembalikan ke 'approved'.",
                        ]);
                    }
                }

                DB::commit();
                $msg = 'Food Payment berhasil ' . ($request->approved ? 'diapprove' : 'direject');
                return response()->json(['success' => true, 'message' => $msg]);
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Error approving/rejecting Food Payment (Finance Manager)', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['success' => false, 'message' => 'Gagal memproses approval: ' . $e->getMessage()], 500);
            }
        }

        // GM Finance Approval (Second level - final approval)
        if (
            ($user->id_jabatan == 152 && $user->status == 'A' && $foodPayment->status == 'draft' && $foodPayment->finance_manager_approved_at && !$foodPayment->gm_finance_approved_at)
            || ($isSuperadmin && $foodPayment->status == 'draft' && $foodPayment->finance_manager_approved_at && !$foodPayment->gm_finance_approved_at)
        ) {
            DB::beginTransaction();
            try {
                // Jika di-reject, ambil contra bon ids dulu sebelum update status
                $contraBonIds = [];
                if (!$request->approved) {
                    // Refresh relationship untuk memastikan data ter-load
                    $foodPayment->load('contraBons');
                    $contraBonIds = $foodPayment->contraBons->pluck('id')->toArray();
                }
                
                $foodPayment->update([
                    'gm_finance_approved_at' => now(),
                    'gm_finance_approved_by' => $user->id,
                    'gm_finance_note' => $request->note,
                    'status' => $request->approved ? 'approved' : 'rejected'
                ]);

                // Jika di-reject, kembalikan status Contra Bon ke 'approved' dan hapus relasi
                if (!$request->approved && !empty($contraBonIds)) {
                    ContraBon::whereIn('id', $contraBonIds)
                        ->update(['status' => 'approved']);
                    
                    // Hapus relasi FoodPaymentContraBon agar Contra Bon bisa digunakan lagi
                    FoodPaymentContraBon::where('food_payment_id', $foodPayment->id)->delete();
                }

                // Log activity
                \App\Models\ActivityLog::create([
                    'user_id' => $user->id,
                    'activity_type' => $request->approved ? 'approve' : 'reject',
                    'module' => 'food_payment',
                    'description' => ($request->approved ? 'Approve' : 'Reject') . ' Food Payment (GM Finance): ' . $foodPayment->number,
                    'ip_address' => $request->ip(),
                ]);

                // Send notification to creator
                if ($foodPayment->created_by) {
                    \App\Models\Notification::create([
                        'user_id' => $foodPayment->created_by,
                        'type' => 'food_payment_approval',
                        'title' => 'Food Payment ' . ($request->approved ? 'Disetujui' : 'Ditolak'),
                        'message' => "Food Payment {$foodPayment->number} telah " . ($request->approved ? 'disetujui' : 'ditolak') . " oleh GM Finance." . (!$request->approved ? " Status Contra Bon telah dikembalikan ke 'approved'." : ''),
                    ]);
                }

                DB::commit();
                $msg = 'Food Payment berhasil ' . ($request->approved ? 'diapprove' : 'direject');
                return response()->json(['success' => true, 'message' => $msg]);
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Error approving/rejecting Food Payment (GM Finance)', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['success' => false, 'message' => 'Gagal memproses approval: ' . $e->getMessage()], 500);
            }
        }

        return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk approve Food Payment ini'], 403);
    }

    // Mark Food Payment as Paid (from approved status)
    public function markAsPaid($id, BankBookService $bankBookService, JurnalService $jurnalService)
    {
        try {
            $foodPayment = FoodPayment::findOrFail($id);
            
            if ($foodPayment->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Food Payment harus berstatus approved untuk bisa ditandai sebagai paid'
                ], 400);
            }
            
            DB::beginTransaction();
            
            // Load payment outlets relationship before updating
            $foodPayment->load('paymentOutlets.outlet');
            
            $foodPayment->update([
                'status' => 'paid'
            ]);
            
            // Create bank book entry if payment type is Transfer or Giro
            $bankBookService->createFromFoodPayment($foodPayment);
            
            // Create jurnal entries (jurnal + jurnal_global) using JurnalService
            try {
                $jurnalService->createFromFoodPayment($foodPayment);
            } catch (\Exception $e) {
                // If jurnal creation fails, rollback and bubble error
                DB::rollBack();
                throw $e;
            }
            
            // Log activity
            \App\Models\ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'food_payment',
                'description' => 'Mark Food Payment as Paid: ' . $foodPayment->number,
                'ip_address' => request()->ip(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Food Payment berhasil ditandai sebagai paid'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error marking Food Payment as paid: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menandai Food Payment sebagai paid'
            ], 500);
        }
    }

    // Helper method untuk send notification
    private function sendNotification($userIds, $type, $title, $message, $url) {
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
            ];
        }
        NotificationService::createMany($data);
    }

    // API: Get pending Food Payment approvals
    public function getPendingApprovals(Request $request)
    {
        try {
            $user = Auth::user();
            $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
            
            $query = FoodPayment::with(['supplier', 'creator', 'financeManager', 'contraBons'])
                ->where('status', 'draft') // Food Payment dengan status 'draft' perlu approval
                ->orderByDesc('created_at');
            
            $pendingApprovals = [];
            
            // Finance Manager approvals (id_jabatan == 160) - First level
            if (($user->id_jabatan == 160 && $user->status == 'A') || $isSuperadmin) {
                // Get approver name for this level
                $approver = DB::table('users')
                    ->where('id_jabatan', 160)
                    ->where('status', 'A')
                    ->select('nama_lengkap')
                    ->first();
                
                $financeManagerApprovals = (clone $query)
                    ->whereNull('finance_manager_approved_at')
                    ->get();
                
                foreach ($financeManagerApprovals as $fp) {
                    $pendingApprovals[] = [
                        'id' => $fp->id,
                        'number' => $fp->number,
                        'date' => $fp->date,
                        'total' => $fp->total,
                        'payment_type' => $fp->payment_type,
                        'supplier' => $fp->supplier ? ['name' => $fp->supplier->name] : null,
                        'creator' => $fp->creator ? ['nama_lengkap' => $fp->creator->nama_lengkap] : null,
                        'approval_level' => 'finance_manager',
                        'approval_level_display' => 'Finance Manager',
                        'approver_name' => $approver ? $approver->nama_lengkap : 'Finance Manager',
                        'contra_bons_count' => $fp->contraBons->count(),
                        'notes' => $fp->notes,
                        'created_at' => $fp->created_at
                    ];
                }
            }
            
            // GM Finance approvals (id_jabatan == 152) - Second level
            if (($user->id_jabatan == 152 && $user->status == 'A') || $isSuperadmin) {
                // Get approver name for this level
                $approver = DB::table('users')
                    ->where('id_jabatan', 152)
                    ->where('status', 'A')
                    ->select('nama_lengkap')
                    ->first();
                
                $gmFinanceApprovals = (clone $query)
                    ->whereNotNull('finance_manager_approved_at')
                    ->whereNull('gm_finance_approved_at')
                    ->get();
                
                foreach ($gmFinanceApprovals as $fp) {
                    $pendingApprovals[] = [
                        'id' => $fp->id,
                        'number' => $fp->number,
                        'date' => $fp->date,
                        'total' => $fp->total,
                        'payment_type' => $fp->payment_type,
                        'supplier' => $fp->supplier ? ['name' => $fp->supplier->name] : null,
                        'creator' => $fp->creator ? ['nama_lengkap' => $fp->creator->nama_lengkap] : null,
                        'approval_level' => 'gm_finance',
                        'approval_level_display' => 'GM Finance',
                        'approver_name' => $approver ? $approver->nama_lengkap : 'GM Finance',
                        'contra_bons_count' => $fp->contraBons->count(),
                        'notes' => $fp->notes,
                        'created_at' => $fp->created_at
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'food_payments' => $pendingApprovals
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting pending Food Payment approvals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get pending approvals'
            ], 500);
        }
    }

    // API: Get Food Payment detail for approval modal
    public function getDetail($id)
    {
        try {
            $foodPayment = FoodPayment::with([
                'supplier',
                'creator',
                'financeManager',
                'gmFinance',
                'contraBons.purchaseOrder',
                'contraBons.retailFood',
                'contraBons.warehouseRetailFood',
                'contraBons.items.item',
                'contraBons.items.unit'
            ])->findOrFail($id);
            
            // Transform contra bons to include source type and outlet information
            $foodPayment->contra_bons = $foodPayment->contraBons ? $foodPayment->contraBons->map(function($contraBon) {
                $sourceTypeDisplay = 'Unknown';
                $sourceNumbers = [];
                $sourceOutlets = [];
                
                if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                    if ($contraBon->purchaseOrder->source_type === 'pr_foods') {
                        $sourceTypeDisplay = 'PR Foods';
                        // Get PR numbers
                        $prNumbers = DB::table('pr_foods as pr')
                            ->join('pr_food_items as pri', 'pr.id', '=', 'pri.pr_food_id')
                            ->join('purchase_order_food_items as poi', 'pri.id', '=', 'poi.pr_food_item_id')
                            ->where('poi.purchase_order_food_id', $contraBon->purchaseOrder->id)
                            ->distinct()
                            ->pluck('pr.pr_number')
                            ->toArray();
                        $sourceNumbers = $prNumbers;
                    } elseif ($contraBon->purchaseOrder->source_type === 'ro_supplier') {
                        $sourceTypeDisplay = 'RO Supplier';
                        // Get RO Supplier numbers and outlet names
                        $roData = DB::table('food_floor_orders as fo')
                            ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                            ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                            ->where('poi.purchase_order_food_id', $contraBon->purchaseOrder->id)
                            ->select('fo.order_number', 'o.nama_outlet')
                            ->distinct()
                            ->get();
                        $sourceNumbers = $roData->pluck('order_number')->unique()->filter()->toArray();
                        $sourceOutlets = $roData->pluck('nama_outlet')->unique()->filter()->toArray();
                    }
                } elseif ($contraBon->source_type === 'retail_food') {
                    $sourceTypeDisplay = 'Retail Food';
                    if ($contraBon->retailFood) {
                        $sourceNumbers = [$contraBon->retailFood->retail_number ?? ''];
                        $sourceOutlets = [$contraBon->retailFood->outlet_name ?? ''];
                    }
                } elseif ($contraBon->source_type === 'warehouse_retail_food') {
                    $sourceTypeDisplay = 'Warehouse Retail Food';
                    if ($contraBon->warehouseRetailFood) {
                        $sourceNumbers = [$contraBon->warehouseRetailFood->retail_number ?? ''];
                        $warehouseName = $contraBon->warehouseRetailFood->warehouse ? $contraBon->warehouseRetailFood->warehouse->name : '';
                        $divisionName = $contraBon->warehouseRetailFood->warehouseDivision ? $contraBon->warehouseRetailFood->warehouseDivision->name : '';
                        $sourceOutlets = [$warehouseName . ($divisionName ? ' - ' . $divisionName : '')];
                    }
                }
                
                $contraBon->source_type_display = $sourceTypeDisplay;
                $contraBon->source_numbers = $sourceNumbers;
                $contraBon->source_outlets = $sourceOutlets;
                
                return $contraBon;
            }) : collect();
            
            return response()->json([
                'success' => true,
                'food_payment' => $foodPayment
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting Food Payment detail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Food Payment detail'
            ], 500);
        }
    }

    // API: Get contra bon yang belum dibayar dengan search
    public function getContraBonUnpaid(Request $request)
    {
        // PERFORMANCE OPTIMIZATION: Optimize paidContraBonIds query
        $paidContraBonIds = DB::table('food_payment_contra_bons as fpcb')
            ->join('food_payments as fp', 'fpcb.food_payment_id', '=', 'fp.id')
            ->where('fp.status', '!=', 'rejected')
            ->pluck('fpcb.contra_bon_id')
            ->toArray();
        
        // PERFORMANCE OPTIMIZATION: Use DB::table with leftJoin for better field access
        $query = DB::table('food_contra_bons')
            ->leftJoin('suppliers as s', 'food_contra_bons.supplier_id', '=', 's.id')
            ->leftJoin('purchase_order_foods as po', 'food_contra_bons.po_id', '=', 'po.id')
            ->leftJoin('retail_food as rf', function($join) {
                $join->on('food_contra_bons.source_id', '=', 'rf.id')
                     ->where('food_contra_bons.source_type', '=', 'retail_food');
            })
            ->select(
                'food_contra_bons.*',
                's.name as supplier_name',
                'po.id as po_id_from_join',
                'po.number as po_number',
                'po.source_type as po_source_type',
                'po.source_id as po_source_id',
                'rf.retail_number as retail_food_number'
            )
            ->where('food_contra_bons.status', 'approved')
            ->whereNotIn('food_contra_bons.id', $paidContraBonIds);
        
        // Search functionality - optimize with leftJoin instead of whereHas
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Search by contra bon number
                $q->where('food_contra_bons.number', 'like', "%{$search}%")
                  // Search by supplier invoice number
                  ->orWhere('food_contra_bons.supplier_invoice_number', 'like', "%{$search}%")
                  // Search by total amount
                  ->orWhereRaw("CAST(food_contra_bons.total_amount AS CHAR) LIKE ?", ["%{$search}%"])
                  // Search by date
                  ->orWhereDate('food_contra_bons.date', 'like', "%{$search}%")
                  ->orWhereRaw("DATE_FORMAT(food_contra_bons.date, '%d-%m-%Y') LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("DATE_FORMAT(food_contra_bons.date, '%Y-%m-%d') LIKE ?", ["%{$search}%"])
                  // Search by notes
                  ->orWhere('food_contra_bons.notes', 'like', "%{$search}%")
                  // Search by supplier name (dari join)
                  ->orWhere('s.name', 'like', "%{$search}%")
                  // Search by PO number (dari join)
                  ->orWhere('po.number', 'like', "%{$search}%")
                  // Search by retail food number (dari join)
                  ->orWhere('rf.retail_number', 'like', "%{$search}%");
            });
        }
        
        // Filter by supplier_id if provided
        if ($request->has('supplier_id') && !empty($request->supplier_id)) {
            $query->where('food_contra_bons.supplier_id', $request->supplier_id);
        }
        
        $contraBons = $query->get();
        
        // Debug logging untuk contra bon tertentu
        $debugContraBons = $contraBons->filter(function($cb) {
            return $cb->number === 'CB-20260119-0064' || $cb->number === 'CB-20260120-0160';
        });
        
        if ($debugContraBons->count() > 0) {
            \Log::info('Raw Contra Bon Data', [
                'data' => $debugContraBons->map(function($cb) {
                    return [
                        'number' => $cb->number,
                        'po_id' => $cb->po_id,
                        'po_id_from_join' => $cb->po_id_from_join ?? 'not set',
                        'po_source_type' => $cb->po_source_type ?? 'not set',
                        'po_source_id' => $cb->po_source_id ?? 'not set',
                        'source_type' => $cb->source_type ?? 'not set',
                        'source_id' => $cb->source_id ?? 'not set',
                    ];
                })->toArray()
            ]);
        }
        
        // PERFORMANCE OPTIMIZATION: Batch query untuk outlet data dan source detection
        // Ambil semua contra bon yang punya po_id (tidak peduli source_type-nya)
        $poIds = $contraBons->filter(function($cb) {
            return !empty($cb->po_id);
        })->pluck('po_id')->unique()->values()->map(function($id) {
            return (int)$id; // Ensure integer type
        })->toArray();
        
        $outletDataMap = [];
        if (!empty($poIds)) {
            try {
                // Batch query outlet data untuk semua PO sekaligus
                $allOutletData = DB::table('food_floor_orders as fo')
                    ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                    ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                    ->join('purchase_order_foods as po', 'poi.purchase_order_food_id', '=', 'po.id')
                    ->whereIn('poi.purchase_order_food_id', $poIds)
                    ->where('po.source_type', 'ro_supplier')
                    ->select('poi.purchase_order_food_id', 'o.nama_outlet')
                    ->distinct()
                    ->get();
                
                foreach ($allOutletData as $outlet) {
                    $poId = (int)$outlet->purchase_order_food_id; // Ensure integer type
                    if (!isset($outletDataMap[$poId])) {
                        $outletDataMap[$poId] = [];
                    }
                    if ($outlet->nama_outlet) {
                        $outletDataMap[$poId][] = $outlet->nama_outlet;
                    }
                }
                
                // Remove duplicates
                foreach ($outletDataMap as $poId => $outlets) {
                    $outletDataMap[$poId] = array_values(array_unique(array_filter($outlets)));
                }
            } catch (\Exception $e) {
                \Log::error('Error batch query outlet data', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Batch query untuk source detection (retailFood, warehouseRetailFood, retailNonFood)
        $sourceIds = $contraBons->filter(function($cb) {
            return empty($cb->source_type) && $cb->source_id;
        })->pluck('source_id')->unique()->values()->toArray();
        
        $sourceTypeMap = [];
        if (!empty($sourceIds)) {
            try {
                // Batch query retail_food
                $retailFoodIds = DB::table('retail_food')
                    ->whereIn('id', $sourceIds)
                    ->pluck('id')
                    ->toArray();
                foreach ($retailFoodIds as $id) {
                    $sourceTypeMap[$id] = 'retail_food';
                }
                
                // Batch query warehouse_retail_food
                $warehouseRetailFoodIds = DB::table('retail_warehouse_food')
                    ->whereIn('id', $sourceIds)
                    ->pluck('id')
                    ->toArray();
                foreach ($warehouseRetailFoodIds as $id) {
                    if (!isset($sourceTypeMap[$id])) {
                        $sourceTypeMap[$id] = 'warehouse_retail_food';
                    }
                }
                
                // Batch query retail_non_food
                $retailNonFoodIds = DB::table('retail_non_food')
                    ->whereIn('id', $sourceIds)
                    ->pluck('id')
                    ->toArray();
                foreach ($retailNonFoodIds as $id) {
                    if (!isset($sourceTypeMap[$id])) {
                        $sourceTypeMap[$id] = 'retail_non_food';
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error batch query source type detection', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Batch query retailFood dengan outlet
        $retailFoodIds = $contraBons->filter(function($cb) {
            return $cb->source_type === 'retail_food' && $cb->source_id;
        })->pluck('source_id')->unique()->values()->toArray();
        
        $retailFoodsMap = [];
        if (!empty($retailFoodIds)) {
            try {
                $retailFoods = \App\Models\RetailFood::with('outlet')
                    ->whereIn('id', $retailFoodIds)
                    ->get();
                foreach ($retailFoods as $rf) {
                    $retailFoodsMap[$rf->id] = $rf;
                }
            } catch (\Exception $e) {
                \Log::error('Error batch query retail food', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Batch query warehouseRetailFood untuk backward compatibility
        $warehouseRetailFoodIds = $contraBons->filter(function($cb) {
            return $cb->source_type === 'warehouse_retail_food' && $cb->source_id;
        })->pluck('source_id')->unique()->values()->toArray();
        
        $warehouseRetailFoodsMap = [];
        if (!empty($warehouseRetailFoodIds)) {
            try {
                $warehouseRetailFoods = \App\Models\RetailWarehouseFood::with(['warehouse', 'warehouseDivision'])
                    ->whereIn('id', $warehouseRetailFoodIds)
                    ->get();
                foreach ($warehouseRetailFoods as $wrf) {
                    $warehouseRetailFoodsMap[$wrf->id] = $wrf;
                }
            } catch (\Exception $e) {
                \Log::error('Error batch query warehouse retail food', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Batch query warehouse data untuk PR Foods (dari PO)
        $prWarehouseMap = [];
        if (!empty($poIds)) {
            try {
                \Log::info('Starting PR Warehouse Query', [
                    'poIds' => $poIds,
                    'poIds_count' => count($poIds)
                ]);
                
                $prWarehouseData = DB::table('purchase_order_foods as po')
                    ->join('purchase_order_food_items as poi', 'po.id', '=', 'poi.purchase_order_food_id')
                    ->join('pr_food_items as pri', 'poi.pr_food_item_id', '=', 'pri.id')
                    ->join('pr_foods as pr', 'pri.pr_food_id', '=', 'pr.id')
                    ->leftJoin('warehouses as w', 'pr.warehouse_id', '=', 'w.id')
                    ->whereIn('po.id', $poIds)
                    ->where('po.source_type', 'pr_foods')
                    ->select('po.id as po_id', 'w.name as warehouse_name', 'pr.warehouse_id', 'po.number as po_number')
                    ->distinct()
                    ->get();
                
                \Log::info('PR Warehouse Query Result', [
                    'poIds' => $poIds,
                    'result_count' => $prWarehouseData->count(),
                    'data' => $prWarehouseData->toArray()
                ]);
                
                foreach ($prWarehouseData as $warehouse) {
                    $poId = (int)$warehouse->po_id;
                    if (!isset($prWarehouseMap[$poId])) {
                        $prWarehouseMap[$poId] = [
                            'names' => [],
                            'ids' => []
                        ];
                    }
                    if ($warehouse->warehouse_name) {
                        $prWarehouseMap[$poId]['names'][] = $warehouse->warehouse_name;
                        if ($warehouse->warehouse_id) {
                            $prWarehouseMap[$poId]['ids'][] = $warehouse->warehouse_id;
                        }
                    }
                }
                
                \Log::info('PR Warehouse Map Final', [
                    'prWarehouseMap' => $prWarehouseMap
                ]);
            } catch (\Exception $e) {
                \Log::error('Error batch query PR warehouse data', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            \Log::warning('No PO IDs found for PR warehouse query');
        }
        
        // Batch query retailNonFood dengan outlet
        $retailNonFoodIds = $contraBons->filter(function($cb) {
            return $cb->source_type === 'retail_non_food' && $cb->source_id;
        })->pluck('source_id')->unique()->values()->toArray();
        
        $retailNonFoodsMap = [];
        if (!empty($retailNonFoodIds)) {
            try {
                $retailNonFoods = \App\Models\RetailNonFood::with('outlet')
                    ->whereIn('id', $retailNonFoodIds)
                    ->get();
                foreach ($retailNonFoods as $rnf) {
                    $retailNonFoodsMap[$rnf->id] = $rnf;
                }
            } catch (\Exception $e) {
                \Log::error('Error batch query retail non food', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Eager load purchaseOrder untuk backward compatibility
        $purchaseOrdersMap = [];
        if (!empty($poIds)) {
            try {
                $purchaseOrders = \App\Models\PurchaseOrderFood::whereIn('id', $poIds)->get();
                foreach ($purchaseOrders as $po) {
                    $purchaseOrdersMap[$po->id] = $po;
                }
            } catch (\Exception $e) {
                \Log::error('Error batch query purchase orders', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Transform data to include source type and outlet information (using batch query results)
        $contraBons = $contraBons->map(function($contraBon) use ($outletDataMap, $sourceTypeMap, $retailFoodsMap, $warehouseRetailFoodsMap, $purchaseOrdersMap, $retailNonFoodsMap, $prWarehouseMap) {
            $sourceTypeDisplay = 'Unknown';
            $outletNames = [];
            $warehouseNames = [];
            
            // Get source_type dengan safe access (karena DB::table() return stdClass)
            $contraBonSourceType = isset($contraBon->source_type) ? trim($contraBon->source_type) : null;
            $contraBonPoId = isset($contraBon->po_id) ? $contraBon->po_id : null;
            $contraBonSourceId = isset($contraBon->source_id) ? $contraBon->source_id : null;
            
            // PRIORITAS 1: Cek po_id terlebih dahulu
            // Gunakan purchaseOrdersMap sebagai sumber utama (lebih reliable dari join)
            if ($contraBonPoId) {
                // Coba ambil dari purchaseOrdersMap dulu (batch query lebih reliable)
                $poSourceType = null;
                if (isset($purchaseOrdersMap[$contraBonPoId])) {
                    $po = $purchaseOrdersMap[$contraBonPoId];
                    $poSourceType = $po->source_type ?? null;
                }
                
                // Fallback ke po_source_type dari join query jika tidak ada di map
                if (!$poSourceType && isset($contraBon->po_source_type)) {
                    $poSourceType = $contraBon->po_source_type;
                }
                
                // Set source_type jika null (tapi jangan set dulu, biarkan logic di bawah yang handle)
                // if (empty($contraBon->source_type) || $contraBon->source_type === null) {
                //     $contraBon->source_type = 'purchase_order';
                // }
                
                // Determine display berdasarkan po_source_type
                if ($poSourceType === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
                    // PR Foods - get warehouse names from batch query result
                    $poId = (int)$contraBonPoId;
                    $warehouseNames = isset($prWarehouseMap[$poId]['names']) ? $prWarehouseMap[$poId]['names'] : [];
                    $warehouseIds = isset($prWarehouseMap[$poId]['ids']) ? $prWarehouseMap[$poId]['ids'] : [];
                    $outletNames = [];
                } elseif ($poSourceType === 'ro_supplier') {
                    $sourceTypeDisplay = 'RO Supplier';
                    // Get outlet names from batch query result - use po_id directly with type casting
                    $poId = (int)$contraBonPoId; // Ensure integer type for key matching
                    $outletNames = isset($outletDataMap[$poId]) ? $outletDataMap[$poId] : [];
                    $warehouseNames = [];
                } elseif ($poSourceType === null || $poSourceType === '') {
                    // po_source_type null atau empty - ini data lama, coba detect dari data lain
                    // Cek apakah ada outlet data (jika ada, berarti RO Supplier)
                    $poId = (int)$contraBonPoId;
                    if (isset($outletDataMap[$poId]) && !empty($outletDataMap[$poId])) {
                        // Ada outlet data, berarti RO Supplier
                        $sourceTypeDisplay = 'RO Supplier';
                        $outletNames = $outletDataMap[$poId];
                        $warehouseNames = [];
                        $warehouseIds = [];
                    } else {
                        // Tidak ada outlet data, assume PR Foods (data lama biasanya PR Foods)
                        $sourceTypeDisplay = 'PR Foods';
                        $warehouseNames = isset($prWarehouseMap[$poId]['names']) ? $prWarehouseMap[$poId]['names'] : [];
                        $warehouseIds = isset($prWarehouseMap[$poId]['ids']) ? $prWarehouseMap[$poId]['ids'] : [];
                        $outletNames = [];
                    }
                } else {
                    // Purchase Order dengan source_type lain (bukan pr_foods atau ro_supplier)
                    $sourceTypeDisplay = 'Purchase Order';
                    $outletNames = [];
                    $warehouseNames = [];
                    $warehouseIds = [];
                }
                
                // Attach purchaseOrder untuk backward compatibility
                if (isset($purchaseOrdersMap[$contraBonPoId])) {
                    $contraBon->purchaseOrder = $purchaseOrdersMap[$contraBonPoId];
                }
            } elseif ($contraBonSourceType === 'retail_food') {
                $sourceTypeDisplay = 'Retail Food';
                // Get outlet name from batch query result
                $retailFood = isset($retailFoodsMap[$contraBonSourceId]) ? $retailFoodsMap[$contraBonSourceId] : null;
                if ($retailFood && $retailFood->outlet && $retailFood->outlet->nama_outlet) {
                    $outletNames = [$retailFood->outlet->nama_outlet];
                } else {
                    $outletNames = [];
                }
                $warehouseNames = [];
                $warehouseIds = [];
                // Attach retailFood untuk backward compatibility
                if ($retailFood) {
                    $contraBon->retailFood = $retailFood;
                }
            } elseif ($contraBonSourceType === 'warehouse_retail_food') {
                $sourceTypeDisplay = 'Warehouse Retail Food';
                // Get warehouse name from batch query result
                $warehouseRetailFood = isset($warehouseRetailFoodsMap[$contraBonSourceId]) ? $warehouseRetailFoodsMap[$contraBonSourceId] : null;
                if ($warehouseRetailFood && $warehouseRetailFood->warehouse && $warehouseRetailFood->warehouse->name) {
                    $warehouseNames = [$warehouseRetailFood->warehouse->name];
                    $warehouseIds = [$warehouseRetailFood->warehouse->id];
                } else {
                    $warehouseNames = [];
                    $warehouseIds = [];
                }
                $outletNames = [];
                // Attach warehouseRetailFood untuk backward compatibility
                if ($warehouseRetailFood) {
                    $contraBon->warehouseRetailFood = $warehouseRetailFood;
                }
            } elseif ($contraBonSourceType === 'retail_non_food') {
                $sourceTypeDisplay = 'Retail Non Food';
                // Get outlet name from batch query result
                $retailNonFood = isset($retailNonFoodsMap[$contraBonSourceId]) ? $retailNonFoodsMap[$contraBonSourceId] : null;
                if ($retailNonFood && $retailNonFood->outlet && $retailNonFood->outlet->nama_outlet) {
                    $outletNames = [$retailNonFood->outlet->nama_outlet];
                } else {
                    $outletNames = [];
                }
                $warehouseNames = [];
                $warehouseIds = [];
                // Attach retailNonFood untuk backward compatibility
                if ($retailNonFood) {
                    $contraBon->retailNonFood = $retailNonFood;
                }
            } elseif ($contraBonSourceType === 'purchase_order') {
                // Fallback: source_type sudah set tapi tidak ada po_id (shouldn't happen, but handle it)
                // Coba ambil dari po_source_type jika ada
                $poSourceType = isset($contraBon->po_source_type) ? $contraBon->po_source_type : null;
                if ($poSourceType === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
                    $warehouseNames = [];
                } elseif ($poSourceType === 'ro_supplier') {
                    $sourceTypeDisplay = 'RO Supplier';
                } else {
                    $sourceTypeDisplay = 'Purchase Order';
                }
                $outletNames = [];
                $warehouseNames = [];
                $warehouseIds = [];
            } else {
                // Fallback untuk source_type yang tidak dikenal
                // Cek dulu apakah po_id ada (harusnya sudah ter-handle di atas, tapi jaga-jaga)
                if ($contraBonPoId) {
                    // Ada po_id tapi belum ter-handle di atas, coba ambil dari purchaseOrdersMap
                    $poSourceType = null;
                    if (isset($purchaseOrdersMap[$contraBonPoId])) {
                        $po = $purchaseOrdersMap[$contraBonPoId];
                        $poSourceType = $po->source_type ?? null;
                    } elseif (isset($contraBon->po_source_type)) {
                        $poSourceType = $contraBon->po_source_type;
                    }
                    
                    if ($poSourceType === 'pr_foods') {
                        $sourceTypeDisplay = 'PR Foods';
                        $poId = (int)$contraBonPoId;
                        $warehouseNames = isset($prWarehouseMap[$poId]['names']) ? $prWarehouseMap[$poId]['names'] : [];
                        $warehouseIds = isset($prWarehouseMap[$poId]['ids']) ? $prWarehouseMap[$poId]['ids'] : [];
                        $outletNames = [];
                    } elseif ($poSourceType === 'ro_supplier') {
                        $sourceTypeDisplay = 'RO Supplier';
                        $poId = (int)$contraBonPoId;
                        $outletNames = isset($outletDataMap[$poId]) ? $outletDataMap[$poId] : [];
                        $warehouseNames = [];
                        $warehouseIds = [];
                    } elseif ($poSourceType === null || $poSourceType === '') {
                        // po_source_type null - coba detect dari outlet data
                        $poId = (int)$contraBonPoId;
                        if (isset($outletDataMap[$poId]) && !empty($outletDataMap[$poId])) {
                            $sourceTypeDisplay = 'RO Supplier';
                            $outletNames = $outletDataMap[$poId];
                            $warehouseNames = [];
                            $warehouseIds = [];
                        } else {
                            $sourceTypeDisplay = 'PR Foods';
                            $warehouseNames = isset($prWarehouseMap[$poId]['names']) ? $prWarehouseMap[$poId]['names'] : [];
                            $warehouseIds = isset($prWarehouseMap[$poId]['ids']) ? $prWarehouseMap[$poId]['ids'] : [];
                            $outletNames = [];
                        }
                    } else {
                        $sourceTypeDisplay = 'Purchase Order';
                        $outletNames = [];
                        $warehouseNames = [];
                        $warehouseIds = [];
                    }
                } elseif ($contraBonSourceId && isset($sourceTypeMap[$contraBonSourceId])) {
                    // Gunakan hasil batch query untuk source type detection
                    $detectedType = $sourceTypeMap[$contraBonSourceId];
                    $sourceTypeDisplay = ucfirst(str_replace('_', ' ', $detectedType));
                } elseif (!empty($contraBonSourceType) && $contraBonSourceType !== null) {
                    // Source type ada tapi tidak masuk ke kondisi di atas, format display
                    $sourceTypeDisplay = ucfirst(str_replace('_', ' ', $contraBonSourceType));
                } else {
                    // Tidak ada po_id, source_id, atau source_type - tetap Unknown
                    // Tapi cek sekali lagi apakah ada po_id yang terlewat
                    if ($contraBonPoId) {
                        // Ada po_id tapi terlewat, assume Purchase Order
                        $sourceTypeDisplay = 'Purchase Order';
                    } else {
                        $sourceTypeDisplay = 'Unknown';
                    }
                }
            }
            
            $contraBon->source_type_display = $sourceTypeDisplay;
            // Ensure outlet_names and warehouse_names are always arrays (not null or undefined)
            $contraBon->outlet_names = is_array($outletNames) ? $outletNames : [];
            $contraBon->warehouse_names = is_array($warehouseNames) ? $warehouseNames : [];
            $contraBon->warehouse_ids = isset($warehouseIds) && is_array($warehouseIds) ? $warehouseIds : [];
            
            // Gabung outlet dan warehouse untuk location_names
            $locationNames = array_merge($outletNames, $warehouseNames);
            $contraBon->location_names = $locationNames;
            
            // Debug logging untuk contra bon tertentu
            if ($contraBon->number === 'CB-20260119-0064' || $contraBon->number === 'CB-20260120-0160') {
                \Log::info('Contra Bon Transform Debug', [
                    'number' => $contraBon->number,
                    'po_id' => $contraBonPoId,
                    'source_type_display' => $sourceTypeDisplay,
                    'outlet_names' => $outletNames,
                    'warehouse_names' => $warehouseNames,
                    'location_names' => $locationNames,
                    'po_source_type' => isset($purchaseOrdersMap[$contraBonPoId]) ? $purchaseOrdersMap[$contraBonPoId]->source_type : 'not in map',
                    'prWarehouseMap_entry' => isset($prWarehouseMap[(int)$contraBonPoId]) ? $prWarehouseMap[(int)$contraBonPoId] : 'not in map'
                ]);
            }
            
            return $contraBon;
        });
        
        return response()->json($contraBons);
    }

    public function edit($id)
    {
        $payment = FoodPayment::with(['supplier', 'creator', 'financeManager', 'gmFinance', 'contraBons.purchaseOrder', 'contraBons.retailFood.outlet', 'contraBons.warehouseRetailFood.warehouse'])->findOrFail($id);
        
        // Transform contra bons to include source type and outlet information (same as show)
        $payment->contra_bons = $payment->contraBons ? $payment->contraBons->map(function($contraBon) {
            $sourceTypeDisplay = 'Unknown';
            $outletNames = [];
            
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                if ($contraBon->purchaseOrder->source_type === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
                    // PR Foods tidak punya outlet (global)
                    $outletNames = [];
                } elseif ($contraBon->purchaseOrder->source_type === 'ro_supplier') {
                    $sourceTypeDisplay = 'RO Supplier';
                    // Get outlet names for RO Supplier
                    $outletData = \DB::table('food_floor_orders as fo')
                        ->join('purchase_order_food_items as poi', 'fo.id', '=', 'poi.ro_id')
                        ->leftJoin('tbl_data_outlet as o', 'fo.id_outlet', '=', 'o.id_outlet')
                        ->where('poi.purchase_order_food_id', $contraBon->purchaseOrder->id)
                        ->select('o.nama_outlet')
                        ->distinct()
                        ->get();
                    
                    $outletNames = $outletData->pluck('nama_outlet')->filter()->unique()->toArray();
                }
            } elseif ($contraBon->source_type === 'retail_food') {
                $sourceTypeDisplay = 'Retail Food';
                // Get outlet name for Retail Food from outlet relationship
                if ($contraBon->retailFood && $contraBon->retailFood->outlet && $contraBon->retailFood->outlet->nama_outlet) {
                    $outletNames = [$contraBon->retailFood->outlet->nama_outlet];
                }
            } elseif ($contraBon->source_type === 'warehouse_retail_food' && $contraBon->warehouseRetailFood) {
                $sourceTypeDisplay = 'Warehouse Retail Food';
                // Warehouse Retail Food tidak punya outlet langsung (hanya punya warehouse)
                // Jika perlu outlet name, bisa diambil dari warehouse jika warehouse punya outlet
                $outletNames = [];
            }
            
            $contraBon->source_type_display = $sourceTypeDisplay;
            $contraBon->outlet_names = $outletNames;
            
            return $contraBon;
        }) : collect();
        
        $banks = \App\Models\BankAccount::where('is_active', 1)
            ->with('outlet')
            ->orderBy('bank_name')
            ->get();
        
        // Transform untuk include outlet name (sama seperti di BankAccount/Index)
        $banks = $banks->map(function($bank) {
            return [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number,
                'account_name' => $bank->account_name,
                'outlet_id' => $bank->outlet_id,
                'outlet' => $bank->outlet ? [
                    'id_outlet' => $bank->outlet->id_outlet,
                    'nama_outlet' => $bank->outlet->nama_outlet,
                ] : null,
                'outlet_name' => $bank->outlet ? $bank->outlet->nama_outlet : 'Head Office',
            ];
        });
        
        return inertia('FoodPayment/Form', [
            'payment' => $payment,
            'banks' => $banks,
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'payment_type' => 'required|string|in:Transfer,Giro,Cash',
                'bank_id' => 'nullable|required_if:payment_type,Transfer,Giro|exists:bank_accounts,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'contra_bon_ids' => 'required|array|min:1',
                'contra_bon_ids.*' => 'exists:food_contra_bons,id',
                'notes' => 'nullable|string',
                'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            ]);

            DB::beginTransaction();

            $payment = FoodPayment::findOrFail($id);
            
            // Hitung total dari contra bon yang dipilih
            $contraBons = ContraBon::whereIn('id', $validated['contra_bon_ids'])->get();
            $total = $contraBons->sum('total_amount');

            // Upload file jika ada
            $buktiPath = $payment->bukti_transfer_path;
            if ($request->hasFile('bukti_transfer')) {
                // Hapus file lama jika ada
                if ($buktiPath) {
                    Storage::disk('public')->delete($buktiPath);
                }
                $buktiPath = $request->file('bukti_transfer')->store('food_payment_bukti', 'public');
            }

            // Update FoodPayment
            $payment->update([
                'date' => $validated['date'],
                'supplier_id' => $validated['supplier_id'],
                'total' => $total,
                'payment_type' => $validated['payment_type'],
                'bank_id' => in_array($validated['payment_type'], ['Transfer', 'Giro']) ? ($validated['bank_id'] ?? null) : null,
                'notes' => $validated['notes'] ?? null,
                'bukti_transfer_path' => $buktiPath,
            ]);

            // Hapus relasi lama
            $oldContraBonIds = $payment->contraBons->pluck('id')->toArray();
            FoodPaymentContraBon::where('food_payment_id', $payment->id)->delete();

            // Update status contra bon lama menjadi approved (jika payment belum paid)
            if ($payment->status !== 'paid') {
                ContraBon::whereIn('id', $oldContraBonIds)
                ->update(['status' => 'approved']);
            }

            // Buat relasi baru
            foreach ($contraBons as $cb) {
                FoodPaymentContraBon::create([
                    'food_payment_id' => $payment->id,
                    'contra_bon_id' => $cb->id,
                ]);
                // Status contra bon tetap 'approved', tidak diubah menjadi 'paid' sampai payment di-mark as paid
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('FoodPaymentController@update - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id, BankBookService $bankBookService)
    {
        try {
            DB::beginTransaction();

            $payment = FoodPayment::findOrFail($id);

            // Delete bank book entries if exists
            $bankBookService->deleteByReference('food_payment', $payment->id);

            // Update status contra bon menjadi approved
            ContraBon::whereIn('id', $payment->contraBons->pluck('id'))
                ->update(['status' => 'approved']);

            // Hapus relasi
            FoodPaymentContraBon::where('food_payment_id', $payment->id)->delete();

            // Hapus file bukti transfer jika ada
            if ($payment->bukti_transfer_path) {
                Storage::disk('public')->delete($payment->bukti_transfer_path);
            }

            // Hapus payment
            $payment->delete();

            DB::commit();
            // Redirect ke index dengan pesan sukses
            return redirect()->route('food-payments.index')->with('success', 'Food Payment berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus Food Payment: ' . $e->getMessage());
        }
    }
} 