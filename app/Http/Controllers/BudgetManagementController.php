<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseRequisitionCategory;
use App\Models\PurchaseRequisitionOutletBudget;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class BudgetManagementController extends Controller
{
    private const DIVISION_OPTIONS = [
        'GENERAL',
        'MARKETING',
        'MAINTENANCE',
        'ASSET',
        'PROJECT_ENHANCEMENT',
    ];
    /**
     * Display budget management dashboard
     */
    public function index()
    {
        $categories = PurchaseRequisitionCategory::with(['outletBudgets.outlet'])
            ->orderBy('division')
            ->orderBy('name')
            ->get();

        $outlets = Outlet::select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        // Group categories by division
        $categoriesByDivision = $categories->groupBy('division');

        return inertia('BudgetManagement/Index', [
            'categories' => $categories,
            'categoriesByDivision' => $categoriesByDivision,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Show form to create new category
     */
    public function createCategory()
    {
        $divisions = \App\Models\Divisi::select('id', 'nama_divisi')
            ->orderBy('nama_divisi')
            ->get();

        $outlets = Outlet::select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('BudgetManagement/CreateCategory', [
            'divisions' => $divisions,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Store new category
     */
    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'division' => 'required|in:GENERAL,MARKETING,MAINTENANCE,ASSET,PROJECT_ENHANCEMENT',
            'subcategory' => 'required|string|max:255',
            'budget_limit' => 'required|numeric|min:0',
            'budget_type' => 'required|in:GLOBAL,PER_OUTLET',
            'description' => 'nullable|string',
            'selected_outlets' => 'required_if:budget_type,PER_OUTLET|array',
            'selected_outlets.*' => 'exists:tbl_data_outlet,id_outlet',
            'outlet_budgets' => 'required_if:budget_type,PER_OUTLET|array',
            'outlet_budgets.*' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Create category
            $categoryData = $request->only(['name', 'division', 'subcategory', 'budget_limit', 'budget_type', 'description']);
            $category = PurchaseRequisitionCategory::create($categoryData);

            // If PER_OUTLET, create outlet budgets
            if ($request->budget_type === 'PER_OUTLET' && $request->selected_outlets && $request->outlet_budgets) {
                foreach ($request->selected_outlets as $outletId) {
                    $budgetAmount = $request->outlet_budgets[$outletId] ?? 0;
                    PurchaseRequisitionOutletBudget::create([
                        'category_id' => $category->id,
                        'outlet_id' => $outletId,
                        'allocated_budget' => $budgetAmount,
                        'used_budget' => 0,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('budget-management.index')
                ->with('success', 'Budget category created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create category: ' . $e->getMessage()]);
        }
    }

    /**
     * Show form to edit category budget type
     */
    public function editCategory($id)
    {
        $category = PurchaseRequisitionCategory::with(['outletBudgets.outlet'])->findOrFail($id);
        $outlets = Outlet::select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        return inertia('BudgetManagement/EditCategorySimple', [
            'category' => $category,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Update category budget type
     */
    public function updateCategory(Request $request, $id)
    {
        $request->validate([
            'budget_type' => 'required|in:GLOBAL,PER_OUTLET',
            'budget_limit' => 'required|numeric|min:0',
            'selected_outlets' => 'required_if:budget_type,PER_OUTLET|array',
            'selected_outlets.*' => 'exists:tbl_data_outlet,id_outlet',
            'outlet_budgets' => 'required_if:budget_type,PER_OUTLET|array',
            'outlet_budgets.*' => 'required|numeric|min:0',
        ]);

        $category = PurchaseRequisitionCategory::findOrFail($id);

        try {
            DB::beginTransaction();

            $category->update([
                'budget_type' => $request->budget_type,
                'budget_limit' => $request->budget_limit,
            ]);

            // Handle outlet budgets based on budget type
            if ($request->budget_type === 'GLOBAL') {
                // Remove all outlet budgets for GLOBAL type
                PurchaseRequisitionOutletBudget::where('category_id', $id)->delete();
            } else if ($request->budget_type === 'PER_OUTLET') {
                // Remove existing outlet budgets
                PurchaseRequisitionOutletBudget::where('category_id', $id)->delete();
                
                // Create new outlet budgets for selected outlets
                if ($request->selected_outlets && $request->outlet_budgets) {
                    foreach ($request->selected_outlets as $outletId) {
                        $budgetAmount = $request->outlet_budgets[$outletId] ?? 0;
                        PurchaseRequisitionOutletBudget::create([
                            'category_id' => $id,
                            'outlet_id' => $outletId,
                            'allocated_budget' => $budgetAmount,
                            'used_budget' => 0,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('budget-management.index')
                ->with('success', 'Category budget type updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update category: ' . $e->getMessage()]);
        }
    }

    /**
     * Show delete category page
     */
    public function deleteCategoryPage()
    {
        $categories = PurchaseRequisitionCategory::with(['outletBudgets.outlet'])
            ->orderBy('division')
            ->orderBy('name')
            ->get();

        return inertia('BudgetManagement/DeleteCategory', [
            'categories' => $categories,
        ]);
    }

    /**
     * Delete category
     */
    public function deleteCategory($id)
    {
        try {
            $category = PurchaseRequisitionCategory::findOrFail($id);

            // Check if category has any purchase requisitions
            $hasRequisitions = \App\Models\PurchaseRequisition::where('category_id', $id)->exists();
            
            if ($hasRequisitions) {
                return back()->withErrors(['error' => 'Cannot delete category that has purchase requisitions. Please delete all related purchase requisitions first.']);
            }

            DB::beginTransaction();

            // Delete outlet budgets first
            PurchaseRequisitionOutletBudget::where('category_id', $id)->delete();
            
            // Delete category
            $category->delete();

            DB::commit();

            return redirect()->route('budget-management.index')
                ->with('success', 'Budget category deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to delete category: ' . $e->getMessage()]);
        }
    }

    /**
     * Show form to manage outlet budgets
     */
    public function manageOutletBudgets($categoryId)
    {
        $category = PurchaseRequisitionCategory::findOrFail($categoryId);
        
        if ($category->budget_type !== 'PER_OUTLET') {
            return redirect()->route('budget-management.index')
                ->withErrors(['error' => 'This category is not configured for per-outlet budget']);
        }

        $outletBudgets = PurchaseRequisitionOutletBudget::with('outlet')
            ->where('category_id', $categoryId)
            ->get();

        $outlets = Outlet::select('id_outlet', 'nama_outlet')
            ->orderBy('nama_outlet')
            ->get();

        // Get outlets that don't have budget allocation yet
        $allocatedOutletIds = $outletBudgets->pluck('outlet_id')->toArray();
        $unallocatedOutlets = $outlets->whereNotIn('id_outlet', $allocatedOutletIds);

        return inertia('BudgetManagement/ManageOutletBudgets', [
            'category' => $category,
            'outletBudgets' => $outletBudgets,
            'outlets' => $outlets,
            'unallocatedOutlets' => $unallocatedOutlets,
        ]);
    }

    /**
     * Store outlet budget allocation
     */
    public function storeOutletBudget(Request $request, $categoryId)
    {
        $request->validate([
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'allocated_budget' => 'required|numeric|min:0',
        ]);

        $category = PurchaseRequisitionCategory::findOrFail($categoryId);

        if ($category->budget_type !== 'PER_OUTLET') {
            return back()->withErrors(['error' => 'This category is not configured for per-outlet budget']);
        }

        try {
            // Check if outlet budget already exists
            $existingBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
                ->where('outlet_id', $request->outlet_id)
                ->first();

            if ($existingBudget) {
                return back()->withErrors(['error' => 'Budget allocation for this outlet already exists']);
            }

            PurchaseRequisitionOutletBudget::create([
                'category_id' => $categoryId,
                'outlet_id' => $request->outlet_id,
                'allocated_budget' => $request->allocated_budget,
                'used_budget' => 0,
            ]);

            return redirect()->route('budget-management.manage-outlet-budgets', $categoryId)
                ->with('success', 'Outlet budget allocation created successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create outlet budget: ' . $e->getMessage()]);
        }
    }

    /**
     * Update outlet budget allocation
     */
    public function updateOutletBudget(Request $request, $categoryId, $budgetId)
    {
        $request->validate([
            'allocated_budget' => 'required|numeric|min:0',
        ]);

        try {
            $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
                ->where('id', $budgetId)
                ->firstOrFail();

            $outletBudget->update([
                'allocated_budget' => $request->allocated_budget,
            ]);

            return redirect()->route('budget-management.manage-outlet-budgets', $categoryId)
                ->with('success', 'Outlet budget allocation updated successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update outlet budget: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete outlet budget allocation
     */
    public function deleteOutletBudget($categoryId, $budgetId)
    {
        try {
            $outletBudget = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
                ->where('id', $budgetId)
                ->firstOrFail();

            $outletBudget->delete();

            return redirect()->route('budget-management.manage-outlet-budgets', $categoryId)
                ->with('success', 'Outlet budget allocation deleted successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete outlet budget: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk create outlet budgets for all outlets
     */
    public function bulkCreateOutletBudgets(Request $request, $categoryId)
    {
        $request->validate([
            'default_budget' => 'required|numeric|min:0',
        ]);

        $category = PurchaseRequisitionCategory::findOrFail($categoryId);

        if ($category->budget_type !== 'PER_OUTLET') {
            return back()->withErrors(['error' => 'This category is not configured for per-outlet budget']);
        }

        try {
            DB::beginTransaction();

            $outlets = Outlet::all();
            $created = 0;

            foreach ($outlets as $outlet) {
                // Check if budget already exists
                $existing = PurchaseRequisitionOutletBudget::where('category_id', $categoryId)
                    ->where('outlet_id', $outlet->id_outlet)
                    ->first();

                if (!$existing) {
                    PurchaseRequisitionOutletBudget::create([
                        'category_id' => $categoryId,
                        'outlet_id' => $outlet->id_outlet,
                        'allocated_budget' => $request->default_budget,
                        'used_budget' => 0,
                    ]);
                    $created++;
                }
            }

            DB::commit();

            return redirect()->route('budget-management.manage-outlet-budgets', $categoryId)
                ->with('success', "Created {$created} outlet budget allocations successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create outlet budgets: ' . $e->getMessage()]);
        }
    }

    /**
     * Get budget summary for dashboard
     */
    public function getBudgetSummary()
    {
        $summary = [];

        // Global budgets
        $globalCategories = PurchaseRequisitionCategory::where('budget_type', 'GLOBAL')->get();
        foreach ($globalCategories as $category) {
            $usedAmount = \App\Models\PurchaseRequisition::where('category_id', $category->id)
                ->whereYear('created_at', date('Y'))
                ->whereMonth('created_at', date('m'))
                ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                ->sum('amount');

            $summary[] = [
                'type' => 'GLOBAL',
                'category' => $category->name,
                'division' => $category->division,
                'budget_limit' => $category->budget_limit,
                'used_amount' => $usedAmount,
                'remaining' => $category->budget_limit - $usedAmount,
            ];
        }

        // Per-outlet budgets
        $perOutletCategories = PurchaseRequisitionCategory::where('budget_type', 'PER_OUTLET')
            ->with('outletBudgets.outlet')
            ->get();

        foreach ($perOutletCategories as $category) {
            foreach ($category->outletBudgets as $outletBudget) {
                $usedAmount = \App\Models\PurchaseRequisition::where('category_id', $category->id)
                    ->where('outlet_id', $outletBudget->outlet_id)
                    ->whereYear('created_at', date('Y'))
                    ->whereMonth('created_at', date('m'))
                    ->whereIn('status', ['SUBMITTED', 'APPROVED', 'PROCESSED', 'COMPLETED'])
                    ->sum('amount');

                $summary[] = [
                    'type' => 'PER_OUTLET',
                    'category' => $category->name,
                    'division' => $category->division,
                    'outlet' => $outletBudget->outlet->nama_outlet,
                    'budget_limit' => $outletBudget->allocated_budget,
                    'used_amount' => $usedAmount,
                    'remaining' => $outletBudget->allocated_budget - $usedAmount,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    public function apiMasterCreateData()
    {
        $outlets = Outlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return response()->json([
            'success' => true,
            'divisionOptions' => self::DIVISION_OPTIONS,
            'budgetTypeOptions' => ['GLOBAL', 'PER_OUTLET'],
            'outlets' => $outlets,
        ]);
    }

    public function apiMasterIndex(Request $request)
    {
        $query = PurchaseRequisitionCategory::query()->with(['outletBudgets.outlet']);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('subcategory', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('division')) {
            $query->where('division', $request->query('division'));
        }
        if ($request->filled('budget_type')) {
            $query->where('budget_type', $request->query('budget_type'));
        }

        $perPage = (int) ($request->query('per_page') ?? 10);
        $perPage = max(1, min(100, $perPage));
        $rows = $query->orderBy('division')->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'budgetCategories' => $rows,
        ]);
    }

    public function apiMasterStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'division' => 'required|in:' . implode(',', self::DIVISION_OPTIONS),
            'subcategory' => 'required|string|max:255',
            'budget_limit' => 'required|numeric|min:0',
            'budget_type' => 'required|in:GLOBAL,PER_OUTLET',
            'description' => 'nullable|string',
            'selected_outlets' => 'required_if:budget_type,PER_OUTLET|array',
            'selected_outlets.*' => 'exists:tbl_data_outlet,id_outlet',
            'outlet_budgets' => 'required_if:budget_type,PER_OUTLET|array',
        ]);

        DB::beginTransaction();
        try {
            $category = PurchaseRequisitionCategory::create([
                'name' => $validated['name'],
                'division' => $validated['division'],
                'subcategory' => $validated['subcategory'],
                'budget_limit' => $validated['budget_limit'],
                'budget_type' => $validated['budget_type'],
                'description' => $validated['description'] ?? null,
            ]);

            if ($validated['budget_type'] === 'PER_OUTLET') {
                $outletBudgets = $validated['outlet_budgets'] ?? [];
                foreach (($validated['selected_outlets'] ?? []) as $outletId) {
                    PurchaseRequisitionOutletBudget::create([
                        'category_id' => $category->id,
                        'outlet_id' => $outletId,
                        'allocated_budget' => (float) ($outletBudgets[$outletId] ?? 0),
                        'used_budget' => 0,
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Budget category berhasil ditambahkan',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan budget category: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function apiMasterUpdate(Request $request, int $id)
    {
        $row = PurchaseRequisitionCategory::find($id);
        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'division' => 'required|in:' . implode(',', self::DIVISION_OPTIONS),
            'subcategory' => 'required|string|max:255',
            'budget_limit' => 'required|numeric|min:0',
            'budget_type' => 'required|in:GLOBAL,PER_OUTLET',
            'description' => 'nullable|string',
            'selected_outlets' => 'required_if:budget_type,PER_OUTLET|array',
            'selected_outlets.*' => 'exists:tbl_data_outlet,id_outlet',
            'outlet_budgets' => 'required_if:budget_type,PER_OUTLET|array',
        ]);

        DB::beginTransaction();
        try {
            $row->update([
                'name' => $validated['name'],
                'division' => $validated['division'],
                'subcategory' => $validated['subcategory'],
                'budget_limit' => $validated['budget_limit'],
                'budget_type' => $validated['budget_type'],
                'description' => $validated['description'] ?? null,
            ]);

            if ($validated['budget_type'] === 'GLOBAL') {
                PurchaseRequisitionOutletBudget::where('category_id', $row->id)->delete();
            } else {
                PurchaseRequisitionOutletBudget::where('category_id', $row->id)->delete();
                $outletBudgets = $validated['outlet_budgets'] ?? [];
                foreach (($validated['selected_outlets'] ?? []) as $outletId) {
                    PurchaseRequisitionOutletBudget::create([
                        'category_id' => $row->id,
                        'outlet_id' => $outletId,
                        'allocated_budget' => (float) ($outletBudgets[$outletId] ?? 0),
                        'used_budget' => 0,
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Budget category berhasil diperbarui',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui budget category: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function apiMasterDestroy(int $id)
    {
        $row = PurchaseRequisitionCategory::find($id);
        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $hasRequisitions = \App\Models\PurchaseRequisition::where('category_id', $id)->exists();
        if ($hasRequisitions) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori sudah dipakai pada purchase requisition dan tidak bisa dihapus',
            ], 422);
        }

        DB::beginTransaction();
        try {
            PurchaseRequisitionOutletBudget::where('category_id', $id)->delete();
            $row->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Budget category berhasil dihapus',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus budget category: ' . $e->getMessage(),
            ], 500);
        }
    }
}
