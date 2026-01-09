<?php

namespace App\Http\Controllers;

use App\Models\FoodPayment;
use App\Models\FoodPaymentContraBon;
use App\Models\ContraBon;
use App\Services\NotificationService;
use App\Services\BankBookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FoodPaymentController extends Controller
{
    // List all food payments
    public function index(Request $request)
    {
        $query = FoodPayment::with(['supplier', 'creator', 'financeManager', 'contraBons' => function($q) {
            $q->select('food_contra_bons.id', 'food_contra_bons.supplier_invoice_number', 'food_contra_bons.number');
        }])->orderByDesc('created_at');

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Search di kolom langsung
                $q->where('number', 'like', "%$search%")
                  ->orWhere('payment_type', 'like', "%$search%")
                  ->orWhere('status', 'like', "%$search%")
                  ->orWhere('notes', 'like', "%$search%")
                  // Search di total (format angka)
                  ->orWhereRaw("CAST(total AS CHAR) LIKE ?", ["%$search%"])
                  // Search di tanggal (format YYYY-MM-DD)
                  ->orWhereDate('date', 'like', "%$search%")
                  ->orWhereRaw("DATE_FORMAT(date, '%d-%m-%Y') LIKE ?", ["%$search%"])
                  ->orWhereRaw("DATE_FORMAT(date, '%Y-%m-%d') LIKE ?", ["%$search%"])
                  // Search di supplier name
                  ->orWhereHas('supplier', function($q2) use ($search) {
                      $q2->where('name', 'like', "%$search%");
                  })
                  // Search di creator name
                  ->orWhereHas('creator', function($q2) use ($search) {
                      $q2->where('nama_lengkap', 'like', "%$search%");
                  })
                  // Search di finance manager name
                  ->orWhereHas('financeManager', function($q2) use ($search) {
                      $q2->where('nama_lengkap', 'like', "%$search%");
                  })
                  // Search di GM finance name
                  ->orWhereHas('gmFinance', function($q2) use ($search) {
                      $q2->where('nama_lengkap', 'like', "%$search%");
                  })
                  // Search di invoice numbers dari contra bon
                  ->orWhereHas('contraBons', function($q2) use ($search) {
                      $q2->where('supplier_invoice_number', 'like', "%$search%");
                  });
            });
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('date', '<=', $request->to);
        }

        $payments = $query->paginate(10)->withQueryString();
        
        // Transform payments untuk menambahkan invoice numbers
        $payments->getCollection()->transform(function($payment) {
            $payment->invoice_numbers = $payment->contraBons
                ->pluck('supplier_invoice_number')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            return $payment;
        });

        return inertia('FoodPayment/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status', 'from', 'to']),
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
        
        return inertia('FoodPayment/Form', [
            'banks' => $banks,
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
        $payment = FoodPayment::with(['supplier', 'creator', 'financeManager', 'gmFinance', 'contraBons.purchaseOrder', 'contraBons.retailFood'])->findOrFail($id);
        
        // Transform contra bons to include source type and outlet information
        $payment->contra_bons = $payment->contraBons ? $payment->contraBons->map(function($contraBon) {
            $sourceTypeDisplay = 'Unknown';
            $outletNames = [];
            
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                if ($contraBon->purchaseOrder->source_type === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
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
                // Get outlet name for Retail Food
                if ($contraBon->retailFood) {
                    $outletNames = [$contraBon->retailFood->outlet_name];
                }
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
    public function markAsPaid($id, BankBookService $bankBookService)
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
            
            $foodPayment->update([
                'status' => 'paid'
            ]);
            
            // Create bank book entry if payment type is Transfer or Giro
            $bankBookService->createFromFoodPayment($foodPayment);
            
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
        // Hanya ambil Contra Bon yang terkait dengan Food Payment yang statusnya bukan 'rejected'
        $paidContraBonIds = FoodPaymentContraBon::whereHas('foodPayment', function($query) {
                $query->where('status', '!=', 'rejected');
            })
            ->pluck('contra_bon_id')
            ->toArray();
        
        $query = ContraBon::with(['supplier', 'purchaseOrder', 'retailFood'])
            ->where('status', 'approved')
            ->whereNotIn('id', $paidContraBonIds);
        
        // Search functionality - search across multiple fields
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Search by contra bon number
                $q->where('number', 'like', "%{$search}%")
                  // Search by supplier invoice number
                  ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                  // Search by total amount
                  ->orWhereRaw("CAST(total_amount AS CHAR) LIKE ?", ["%{$search}%"])
                  // Search by date
                  ->orWhereDate('date', 'like', "%{$search}%")
                  ->orWhereRaw("DATE_FORMAT(date, '%d-%m-%Y') LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("DATE_FORMAT(date, '%Y-%m-%d') LIKE ?", ["%{$search}%"])
                  // Search by notes
                  ->orWhere('notes', 'like', "%{$search}%")
                  // Search by supplier name
                  ->orWhereHas('supplier', function($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  })
                  // Search by PO number
                  ->orWhereHas('purchaseOrder', function($q2) use ($search) {
                      $q2->where('number', 'like', "%{$search}%");
                  })
                  // Search by retail food number
                  ->orWhereHas('retailFood', function($q2) use ($search) {
                      $q2->where('number', 'like', "%{$search}%");
                  });
            });
        }
        
        // Filter by supplier_id if provided
        if ($request->has('supplier_id') && !empty($request->supplier_id)) {
            $query->where('supplier_id', $request->supplier_id);
        }
        
        $contraBons = $query->get();
        
        // Transform data to include source type and outlet information
        $contraBons = $contraBons->map(function($contraBon) {
            $sourceTypeDisplay = 'Unknown';
            $outletNames = [];
            
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                if ($contraBon->purchaseOrder->source_type === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
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
                // Get outlet name for Retail Food
                if ($contraBon->retailFood) {
                    $outletNames = [$contraBon->retailFood->outlet_name];
                }
            }
            
            $contraBon->source_type_display = $sourceTypeDisplay;
            $contraBon->outlet_names = $outletNames;
            
            return $contraBon;
        });
        
        return response()->json($contraBons);
    }

    public function edit($id)
    {
        $payment = FoodPayment::with(['supplier', 'creator', 'financeManager', 'gmFinance', 'contraBons.purchaseOrder', 'contraBons.retailFood'])->findOrFail($id);
        
        // Transform contra bons to include source type and outlet information (same as show)
        $payment->contra_bons = $payment->contraBons ? $payment->contraBons->map(function($contraBon) {
            $sourceTypeDisplay = 'Unknown';
            $outletNames = [];
            
            if ($contraBon->source_type === 'purchase_order' && $contraBon->purchaseOrder) {
                if ($contraBon->purchaseOrder->source_type === 'pr_foods') {
                    $sourceTypeDisplay = 'PR Foods';
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
                // Get outlet name for Retail Food
                if ($contraBon->retailFood) {
                    $outletNames = [$contraBon->retailFood->outlet_name];
                }
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