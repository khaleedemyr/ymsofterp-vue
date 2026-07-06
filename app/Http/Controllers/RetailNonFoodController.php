<?php

namespace App\Http\Controllers;

use App\Models\RetailNonFood;
use App\Models\RetailNonFoodItem;
use App\Models\Outlet;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionOutletBudget;
use App\Services\PettyCashLockBudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RetailNonFoodController extends Controller
{
    public function __construct(
        private PettyCashLockBudgetService $pettyCashLockBudget,
    ) {}

    /**
     * @return array{monthly_target: float, usable_after_reserve: float, lock_budget: float}|null
     */
    private function resolveMonthlyPettyCashBudget(int $outletId, string $monthStart): ?array
    {
        return $this->pettyCashLockBudget->resolveForOutlet($outletId, $monthStart);
    }

    /**
     * Usage petty cash bulan berjalan: RF non-contra + RNF non-contra.
     */
    private function monthlyUsagePettyCashOutlet(int $outletId, string $monthYm): array
    {
        $retailFoodNonContraBonTotal = (float) (DB::table('retail_food')
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->where('payment_method', '!=', 'contra_bon')
            ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$monthYm])
            ->sum('total_amount') ?? 0);

        $retailNonFoodNonContraBonTotal = (float) (DB::table('retail_non_food')
            ->where('outlet_id', $outletId)
            ->where('status', 'approved')
            ->where('payment_method', '!=', 'contra_bon')
            ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$monthYm])
            ->sum('total_amount') ?? 0);

        $monthlyTotal = $retailFoodNonContraBonTotal + $retailNonFoodNonContraBonTotal;

        return [
            'retail_food_non_contra_bon_total' => round($retailFoodNonContraBonTotal, 2),
            'retail_non_food_non_contra_bon_total' => round($retailNonFoodNonContraBonTotal, 2),
            'monthly_total' => round($monthlyTotal, 2),
        ];
    }

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
        
        // Check delete permission: superadmin, division warehouse (11), or division 2 can delete
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11) || ($user->division_id == 2);

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
        $categoryBudgets = PurchaseRequisitionCategory::active()
            ->where('budget_type', 'GLOBAL')
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

        // Mobile app sends multipart with items as JSON string; decode to array for validation
        $itemsInput = $request->input('items');
        if (!is_array($itemsInput) && is_string($itemsInput)) {
            $decoded = json_decode($itemsInput, true);
            if (is_array($decoded)) {
                $request->replace(array_merge($request->all(), ['items' => $decoded]));
            }
        }

        try {
            $request->validate([
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'transaction_date' => 'required|date|after_or_equal:today',
                'category_budget_id' => 'required|exists:purchase_requisition_categories,id',
                'payment_method' => 'required|in:cash,contra_bon',
                'supplier_id' => 'required|exists:suppliers,id',
                'items' => 'required|array|min:1',
                'items.*.item_name' => 'required|string',
                'items.*.qty' => 'required|numeric|min:0',
                'items.*.unit' => 'required|string',
                'items.*.price' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ], [
                'transaction_date.after_or_equal' => 'Tanggal transaksi tidak boleh backdate. Pilih tanggal hari ini atau setelahnya.',
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

            // Locking budget petty cash untuk mode selain contra bon.
            if ($request->payment_method !== 'contra_bon') {
                $monthStart = now()->startOfMonth()->toDateString();
                $monthlyBudget = $this->resolveMonthlyPettyCashBudget((int) $request->outlet_id, $monthStart);
                if ($monthlyBudget !== null) {
                    $monthYm = now()->format('Y-m');
                    $usage = $this->monthlyUsagePettyCashOutlet((int) $request->outlet_id, $monthYm);
                    $totalAfterNew = $usage['monthly_total'] + $totalAmount;
                    if ($totalAfterNew > $monthlyBudget['lock_budget']) {
                        DB::rollBack();

                        return response()->json([
                            'message' => $this->pettyCashLockBudget->buildExceededMessage($monthlyBudget, $usage, $totalAmount),
                        ], 422);
                    }
                }
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

            $creatorMeta = $this->resolveActivityUserMeta(auth()->id());
            $this->writeRetailNonFoodActivityLog(
                $request,
                'create',
                'Membuat retail non food: ' . $retailNumber . ' (dibuat oleh: ' . $creatorMeta['user_name'] . ')',
                null,
                array_merge($creatorMeta, [
                    'retail_number' => $retailNumber,
                    'outlet_id' => $request->outlet_id,
                    'category_budget_id' => $request->category_budget_id,
                    'total_amount' => $totalAmount,
                    'payment_method' => $request->payment_method,
                    'supplier_id' => $request->supplier_id,
                    'item_count' => count($request->items),
                ])
            );

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
            
            // Check permission: superadmin, division warehouse (11), or division 2 can delete
            if ($user->id_role !== '5af56935b011a' && $user->division_id != 11 && $user->division_id != 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus data ini'
                ], 403);
            }

            $retailNonFood = RetailNonFood::with(['items', 'creator'])->findOrFail($id);
            $deleteSnapshot = $this->buildRetailNonFoodDeleteSnapshot($retailNonFood, $user);

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

            $this->writeRetailNonFoodActivityLog(
                request(),
                'delete',
                'Menghapus retail non food: ' . $deleteSnapshot['retail_number']
                    . ' (dibuat oleh: ' . ($deleteSnapshot['created_by_name'] ?? '-')
                    . ', dihapus oleh: ' . ($deleteSnapshot['deleted_by_name'] ?? '-') . ')',
                $deleteSnapshot,
                null
            );

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
            $globalCategories = PurchaseRequisitionCategory::active()
                ->where('budget_type', 'GLOBAL')
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
                    $perOutletCategories = PurchaseRequisitionCategory::active()
                        ->where('budget_type', 'PER_OUTLET')
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
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'payment_method' => 'nullable|in:cash,contra_bon',
            ]);

            $paymentMethod = (string) $request->input('payment_method', 'cash');
            $outletId = (int) $request->input('outlet_id', 0);

            if ($paymentMethod === 'contra_bon') {
                return response()->json([
                    'budget_info' => null,
                    'petty_cash_info' => [
                        'budget_lock_active' => false,
                        'message' => 'Locking budget tidak berlaku untuk Contra Bon.',
                    ],
                ]);
            }

            $monthStart = now()->startOfMonth()->toDateString();
            $monthlyBudget = $this->resolveMonthlyPettyCashBudget($outletId, $monthStart);
            if ($monthlyBudget === null) {
                return response()->json([
                    'budget_info' => null,
                    'petty_cash_info' => [
                        'budget_lock_active' => false,
                        'message' => 'Target pendapatan bulan berjalan belum ada. Locking budget petty cash di-skip.',
                    ],
                ]);
            }

            $usage = $this->monthlyUsagePettyCashOutlet($outletId, now()->format('Y-m'));

            return response()->json([
                'budget_info' => null,
                'petty_cash_info' => [
                    'budget_lock_active' => true,
                    'monthly_target' => $monthlyBudget['monthly_target'],
                    'budget_amount' => $monthlyBudget['lock_budget'],
                    'retail_food_non_contra_bon_total' => $usage['retail_food_non_contra_bon_total'],
                    'retail_non_food_non_contra_bon_total' => $usage['retail_non_food_non_contra_bon_total'],
                    'monthly_total' => $usage['monthly_total'],
                    'remaining_budget' => $monthlyBudget['lock_budget'] - $usage['monthly_total'],
                    'budget_percentage' => $monthlyBudget['lock_budget'] > 0
                        ? round(($usage['monthly_total'] / $monthlyBudget['lock_budget']) * 100, 2)
                        : 0,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'budget_info' => null,
                'petty_cash_info' => null,
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
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11) || ($user->division_id == 2);

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
            'canDelete' => $canDelete,
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

        $categoryBudgets = PurchaseRequisitionCategory::active()
            ->where('budget_type', 'GLOBAL')
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
        $canDelete = ($user->id_role === '5af56935b011a') || ($user->division_id == 11) || ($user->division_id == 2);

        return response()->json([
            'success' => true,
            'retail_non_food' => $retailNonFood,
            'canDelete' => $canDelete,
        ]);
    }

    /**
     * API: Store retail non food from mobile app (delegates to store)
     */
    public function apiStore(Request $request)
    {
        return $this->store($request);
    }

    private function resolveActivityUserMeta(?int $userId): array
    {
        if (!$userId) {
            return [
                'user_id' => null,
                'user_name' => '-',
            ];
        }

        $name = DB::table('users')->where('id', $userId)->value('nama_lengkap');

        return [
            'user_id' => $userId,
            'user_name' => $name ?: ('User #' . $userId),
        ];
    }

    private function buildRetailNonFoodDeleteSnapshot(RetailNonFood $retailNonFood, $deletedByUser): array
    {
        $creatorMeta = $this->resolveActivityUserMeta($retailNonFood->created_by);
        $deleterMeta = $this->resolveActivityUserMeta($deletedByUser->id ?? null);

        return [
            'id' => $retailNonFood->id,
            'retail_number' => $retailNonFood->retail_number,
            'outlet_id' => $retailNonFood->outlet_id,
            'category_budget_id' => $retailNonFood->category_budget_id,
            'warehouse_outlet_id' => $retailNonFood->warehouse_outlet_id,
            'transaction_date' => $retailNonFood->transaction_date?->format('Y-m-d'),
            'total_amount' => (float) $retailNonFood->total_amount,
            'payment_method' => $retailNonFood->payment_method,
            'supplier_id' => $retailNonFood->supplier_id,
            'status' => $retailNonFood->status,
            'notes' => $retailNonFood->notes,
            'created_by' => $retailNonFood->created_by,
            'created_by_name' => $creatorMeta['user_name'],
            'deleted_by' => $deleterMeta['user_id'],
            'deleted_by_name' => $deleterMeta['user_name'],
            'items' => $retailNonFood->items->map(function ($item) {
                return [
                    'item_name' => $item->item_name,
                    'qty' => (float) $item->qty,
                    'unit' => $item->unit,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                ];
            })->values()->all(),
        ];
    }

    private function writeRetailNonFoodActivityLog(
        Request $request,
        string $activityType,
        string $description,
        ?array $oldData,
        ?array $newData
    ): void {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'activity_type' => $activityType,
                'module' => 'retail_non_food',
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $oldData ? json_encode($oldData) : null,
                'new_data' => $newData ? json_encode($newData) : null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Activity log tidak critical
        }
    }
} 