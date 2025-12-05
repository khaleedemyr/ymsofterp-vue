<?php

namespace App\Http\Controllers;

use App\Models\LockedBudgetFoodCategory;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LockedBudgetFoodCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LockedBudgetFoodCategory::with(['category', 'subCategory', 'outlet', 'creator', 'updater'])
            ->select('locked_budget_food_categories.*');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('category', function ($categoryQuery) use ($search) {
                    $categoryQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('outlet', function ($outletQuery) use ($search) {
                    $outletQuery->where('nama_outlet', 'like', "%{$search}%");
                })
                ->orWhere('budget', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by outlet
        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $budgets = $query->paginate($perPage);

        // Get filter options
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        $subCategories = SubCategory::select('id', 'category_id', 'name', 'status')->where('status', 'active')->orderBy('name')->get();
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();

        return Inertia::render('LockedBudgetFoodCategories/Index', [
            'budgets' => $budgets,
            'categories' => $categories,
            'subCategories' => $subCategories,
            'outlets' => $outlets,
            'filters' => $request->only(['search', 'category_id', 'outlet_id', 'per_page', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        $subCategories = SubCategory::select('id', 'category_id', 'name', 'status')->where('status', 'active')->orderBy('name')->get();
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();

        return Inertia::render('LockedBudgetFoodCategories/Create', [
            'categories' => $categories,
            'subCategories' => $subCategories,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'budget' => 'required|numeric|min:0',
        ], [
            'category_id.required' => 'Kategori harus dipilih',
            'category_id.exists' => 'Kategori tidak valid',
            'sub_category_id.required' => 'Sub kategori harus dipilih',
            'sub_category_id.exists' => 'Sub kategori tidak valid',
            'outlet_id.required' => 'Outlet harus dipilih',
            'outlet_id.exists' => 'Outlet tidak valid',
            'budget.required' => 'Budget harus diisi',
            'budget.numeric' => 'Budget harus berupa angka',
            'budget.min' => 'Budget tidak boleh kurang dari 0',
        ]);

        try {
            DB::beginTransaction();

            // Check if combination already exists
            $existing = LockedBudgetFoodCategory::where('category_id', $request->category_id)
                ->where('sub_category_id', $request->sub_category_id)
                ->where('outlet_id', $request->outlet_id)
                ->first();

            if ($existing) {
                return back()->withErrors([
                    'category_id' => 'Budget untuk kategori, sub kategori dan outlet ini sudah ada',
                ]);
            }

            LockedBudgetFoodCategory::create([
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'outlet_id' => $request->outlet_id,
                'budget' => $request->budget,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('locked-budget-food-categories.index')
                ->with('success', 'Budget berhasil disimpan');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat menyimpan data']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(LockedBudgetFoodCategory $lockedBudgetFoodCategory)
    {
        $lockedBudgetFoodCategory->load(['category', 'outlet', 'creator', 'updater']);

        return Inertia::render('LockedBudgetFoodCategories/Show', [
            'budget' => $lockedBudgetFoodCategory,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LockedBudgetFoodCategory $lockedBudgetFoodCategory)
    {
        $categories = Category::select('id', 'name')->orderBy('name')->get();
        $subCategories = SubCategory::select('id', 'category_id', 'name', 'status')->where('status', 'active')->orderBy('name')->get();
        $outlets = Outlet::select('id_outlet', 'nama_outlet')->orderBy('nama_outlet')->get();

        return Inertia::render('LockedBudgetFoodCategories/Edit', [
            'budget' => $lockedBudgetFoodCategory,
            'categories' => $categories,
            'subCategories' => $subCategories,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LockedBudgetFoodCategory $lockedBudgetFoodCategory)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'budget' => 'required|numeric|min:0',
        ], [
            'category_id.required' => 'Kategori harus dipilih',
            'category_id.exists' => 'Kategori tidak valid',
            'sub_category_id.required' => 'Sub kategori harus dipilih',
            'sub_category_id.exists' => 'Sub kategori tidak valid',
            'outlet_id.required' => 'Outlet harus dipilih',
            'outlet_id.exists' => 'Outlet tidak valid',
            'budget.required' => 'Budget harus diisi',
            'budget.numeric' => 'Budget harus berupa angka',
            'budget.min' => 'Budget tidak boleh kurang dari 0',
        ]);

        try {
            DB::beginTransaction();

            // Check if combination already exists (excluding current record)
            $existing = LockedBudgetFoodCategory::where('category_id', $request->category_id)
                ->where('sub_category_id', $request->sub_category_id)
                ->where('outlet_id', $request->outlet_id)
                ->where('id', '!=', $lockedBudgetFoodCategory->id)
                ->first();

            if ($existing) {
                return back()->withErrors([
                    'category_id' => 'Budget untuk kategori, sub kategori dan outlet ini sudah ada',
                ]);
            }

            $lockedBudgetFoodCategory->update([
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'outlet_id' => $request->outlet_id,
                'budget' => $request->budget,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('locked-budget-food-categories.index')
                ->with('success', 'Budget berhasil diperbarui');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat memperbarui data']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LockedBudgetFoodCategory $lockedBudgetFoodCategory)
    {
        try {
            $lockedBudgetFoodCategory->delete();

            return redirect()->route('locked-budget-food-categories.index')
                ->with('success', 'Budget berhasil dihapus');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan saat menghapus data']);
        }
    }
}
