<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;

class AssetCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', 'all'); // 'all', 'active', 'inactive'
        $perPage = $request->get('per_page', 15);

        $query = AssetCategory::withCount('assets');

        // Search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $categories = $query->orderBy('code')->paginate($perPage)->withQueryString();

        return Inertia::render('AssetManagement/Categories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('AssetManagement/Categories/Create');
    }

    /**
     * Generate auto code for category
     */
    private function generateCode()
    {
        $lastCategory = AssetCategory::orderBy('id', 'desc')->first();
        
        if ($lastCategory && preg_match('/^CAT(\d+)$/i', $lastCategory->code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return 'CAT' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Auto-generate code if not provided
        $code = $request->code;
        if (empty($code) || trim($code) === '') {
            $code = $this->generateCode();
        }

        $validator = Validator::make(array_merge($request->all(), ['code' => $code]), [
            'code' => 'required|string|max:50|unique:asset_categories,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $category = AssetCategory::create([
            'code' => $code,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Asset category created successfully',
                'data' => $category,
            ], 201);
        }

        return redirect()->route('asset-management.categories.index')
            ->with('success', 'Asset category created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $category = AssetCategory::withCount('assets')->findOrFail($id);
        
        return Inertia::render('AssetManagement/Categories/Show', [
            'category' => $category,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $category = AssetCategory::findOrFail($id);
        
        return Inertia::render('AssetManagement/Categories/Edit', [
            'category' => $category,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $category = AssetCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:asset_categories,code,' . $id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $category->update([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? $request->is_active : $category->is_active,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Asset category updated successfully',
                'data' => $category->fresh(),
            ], 200);
        }

        return redirect()->route('asset-management.categories.index')
            ->with('success', 'Asset category updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $category = AssetCategory::findOrFail($id);

        // Check if category has assets
        if ($category->assets()->count() > 0) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with existing assets',
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot delete category with existing assets');
        }

        $category->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Asset category deleted successfully',
            ], 200);
        }

        return redirect()->route('asset-management.categories.index')
            ->with('success', 'Asset category deleted successfully');
    }

    /**
     * Toggle status of the resource.
     */
    public function toggleStatus($id)
    {
        $category = AssetCategory::findOrFail($id);
        $category->is_active = $category->is_active ? 0 : 1;
        $category->save();
        $category->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => [
                'is_active' => (bool) $category->is_active,
            ],
        ], 200);
    }
}

