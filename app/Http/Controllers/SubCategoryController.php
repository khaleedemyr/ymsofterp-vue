<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SubCategory;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Models\SubCategoryAvailability;
use Illuminate\Support\Facades\DB;
use App\Models\Region;
use App\Models\Outlet;
use App\Models\ChartOfAccount;

class SubCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = SubCategory::with(['category', 'coa', 'availabilities.region', 'availabilities.outlet'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            });

        $subCategories = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        $categories = Category::where('status', 'active')->get();
        $coas = ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $regions = Region::all();
        $outlets = Outlet::all();

        return Inertia::render('SubCategories/Index', [
            'subCategories' => $subCategories,
            'categories' => $categories,
            'coas' => $coas,
            'regions' => $regions,
            'outlets' => $outlets,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'show_pos' => 'required|in:0,1',
            'category_id' => 'required|exists:categories,id',
            'coa_id' => 'nullable|exists:chart_of_accounts,id',
            'availability_type' => 'required_if:show_pos,1|in:byRegion,byOutlet',
            'selected_regions' => 'required_if:availability_type,byRegion|array',
            'selected_regions.*.id' => 'required_if:availability_type,byRegion|exists:regions,id',
            'selected_outlets' => 'required_if:availability_type,byOutlet|array',
            'selected_outlets.*.id' => 'required_if:availability_type,byOutlet|exists:tbl_data_outlet,id_outlet',
        ]);

        $subCategory = SubCategory::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
            'show_pos' => $request->show_pos,
            'category_id' => $request->category_id,
            'coa_id' => $request->coa_id,
        ]);

        if ($request->show_pos === '1') {
            if ($request->availability_type === 'byRegion') {
                $data = [];
                foreach ($request->selected_regions as $region) {
                    $data[] = [
                        'availability_type' => 'byRegion',
                        'region_id' => $region['id'],
                        'sub_category_id' => $subCategory->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (count($data)) {
                    SubCategoryAvailability::insert($data);
                }
            } else if ($request->availability_type === 'byOutlet') {
                $data = [];
                foreach ($request->selected_outlets as $outlet) {
                    $data[] = [
                        'availability_type' => 'byOutlet',
                        'outlet_id' => $outlet['id'],
                        'sub_category_id' => $subCategory->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (count($data)) {
                    SubCategoryAvailability::insert($data);
                }
            }
        }

        return redirect()->back()->with('success', 'Sub category created successfully.');
    }

    public function update(Request $request, SubCategory $subCategory = null)
    {
        \Log::info('DEBUG ROUTE SUBCATEGORY', ['route' => $request->route('subCategory')]);
        if (!$subCategory || !$subCategory->id) {
            $subCategory = SubCategory::find($request->route('subCategory') ?? $request->sub_category_id ?? $request->id);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'show_pos' => 'required|in:0,1',
            'category_id' => 'required|exists:categories,id',
            'coa_id' => 'nullable|exists:chart_of_accounts,id',
            'availability_type' => [
                'nullable',
                function($attribute, $value) use ($request) {
                    if ($request->show_pos == '1' && !in_array($value, ['byRegion', 'byOutlet'])) {
                        return __('The selected availability type is invalid.');
                    }
                }
            ],
            'selected_regions' => 'required_if:availability_type,byRegion|array',
            'selected_regions.*.id' => 'required_if:availability_type,byRegion|exists:regions,id',
            'selected_outlets' => 'required_if:availability_type,byOutlet|array',
            'selected_outlets.*.id' => 'required_if:availability_type,byOutlet|exists:tbl_data_outlet,id_outlet',
        ]);

        $subCategory->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
            'show_pos' => $request->show_pos,
            'category_id' => $request->category_id,
            'coa_id' => $request->coa_id,
        ]);

        $subCategory->refresh();
        // Delete existing availabilities
        $subCategory->availabilities()->delete();

        if ($request->show_pos === '1') {
            if ($request->availability_type === 'byRegion') {
                $data = [];
                foreach ($request->selected_regions as $region) {
                    $data[] = [
                        'availability_type' => 'byRegion',
                        'region_id' => $region['id'],
                        'sub_category_id' => $subCategory->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                \Log::info('DEBUG SUB CATEGORY ID (byRegion)', ['id' => $subCategory->id, 'data' => $data]);
                if (!$subCategory->id) {
                    $subCategory = SubCategory::find($subCategory->getKey());
                }
                if (count($data)) {
                    SubCategoryAvailability::insert($data);
                }
            } else if ($request->availability_type === 'byOutlet') {
                $data = [];
                foreach ($request->selected_outlets as $outlet) {
                    $data[] = [
                        'availability_type' => 'byOutlet',
                        'outlet_id' => $outlet['id'],
                        'sub_category_id' => $subCategory->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                \Log::info('DEBUG SUB CATEGORY ID (byOutlet)', ['id' => $subCategory->id, 'data' => $data]);
                if (!$subCategory->id) {
                    $subCategory = SubCategory::find($subCategory->getKey());
                }
                if (count($data)) {
                    SubCategoryAvailability::insert($data);
                }
            }
        }

        return redirect()->back()->with('success', 'Sub category updated successfully.');
    }

    public function destroy(SubCategory $subCategory)
    {
        $subCategory->availabilities()->delete();
        $subCategory->delete();
        return redirect()->back()->with('success', 'Sub category deleted successfully.');
    }

    public function toggleStatus($id, Request $request)
    {
        $sub = SubCategory::findOrFail($id);
        $sub->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }

    // ---------- Approval App API (JSON) ----------

    public function apiIndex(Request $request)
    {
        $query = SubCategory::with(['category', 'coa', 'availabilities.region', 'availabilities.outlet']);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        $perPage = (int) $request->get('per_page', 15);
        $subCategories = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
        return response()->json($subCategories);
    }

    public function apiCreateData()
    {
        $categories = Category::where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']);
        $coas = ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $regions = \DB::table('regions')->where('status', 'active')->select('id', 'code', 'name')->get();
        $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet as id', 'nama_outlet', 'region_id']);
        return response()->json([
            'categories' => $categories,
            'coas' => $coas,
            'regions' => $regions,
            'outlets' => $outlets,
        ]);
    }

    public function apiShow($id)
    {
        $sub = SubCategory::with(['category', 'coa', 'availabilities.region', 'availabilities.outlet'])->findOrFail($id);
        return response()->json($sub);
    }

    public function apiStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'show_pos' => 'required|in:0,1',
            'category_id' => 'required|exists:categories,id',
            'coa_id' => 'nullable|exists:chart_of_accounts,id',
            'availability_type' => 'nullable|in:byRegion,byOutlet',
            'region_ids' => 'array',
            'region_ids.*' => 'integer|exists:regions,id',
            'outlet_ids' => 'array',
            'outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
        ]);

        $subCategory = SubCategory::create([
            'name' => $request->name,
            'description' => $request->description ?? '',
            'status' => $request->status,
            'show_pos' => (int) $request->show_pos,
            'category_id' => $request->category_id,
            'coa_id' => $request->coa_id,
        ]);

        if ($request->show_pos == 1 && in_array($request->availability_type, ['byRegion', 'byOutlet'])) {
            if ($request->availability_type === 'byRegion' && !empty($request->region_ids)) {
                $data = array_map(fn ($regionId) => [
                    'availability_type' => 'byRegion',
                    'region_id' => $regionId,
                    'sub_category_id' => $subCategory->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $request->region_ids);
                SubCategoryAvailability::insert($data);
            } elseif ($request->availability_type === 'byOutlet' && !empty($request->outlet_ids)) {
                $data = array_map(fn ($outletId) => [
                    'availability_type' => 'byOutlet',
                    'outlet_id' => $outletId,
                    'sub_category_id' => $subCategory->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $request->outlet_ids);
                SubCategoryAvailability::insert($data);
            }
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'sub_categories',
            'description' => 'Menambahkan sub kategori baru: ' . $subCategory->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $subCategory->toArray(),
        ]);

        return response()->json(['success' => true, 'data' => $subCategory->fresh(['category', 'availabilities'])]);
    }

    public function apiUpdate(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'show_pos' => 'required|in:0,1',
            'category_id' => 'required|exists:categories,id',
            'coa_id' => 'nullable|exists:chart_of_accounts,id',
            'availability_type' => 'nullable|in:byRegion,byOutlet',
            'region_ids' => 'array',
            'region_ids.*' => 'integer|exists:regions,id',
            'outlet_ids' => 'array',
            'outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
        ]);

        $sub = SubCategory::findOrFail($id);
        $sub->update([
            'name' => $request->name,
            'description' => $request->description ?? '',
            'status' => $request->status,
            'show_pos' => (int) $request->show_pos,
            'category_id' => $request->category_id,
            'coa_id' => $request->coa_id,
        ]);

        $sub->availabilities()->delete();

        if ($request->show_pos == 1 && in_array($request->availability_type, ['byRegion', 'byOutlet'])) {
            if ($request->availability_type === 'byRegion' && !empty($request->region_ids)) {
                $data = array_map(fn ($regionId) => [
                    'availability_type' => 'byRegion',
                    'region_id' => $regionId,
                    'sub_category_id' => $sub->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $request->region_ids);
                SubCategoryAvailability::insert($data);
            } elseif ($request->availability_type === 'byOutlet' && !empty($request->outlet_ids)) {
                $data = array_map(fn ($outletId) => [
                    'availability_type' => 'byOutlet',
                    'outlet_id' => $outletId,
                    'sub_category_id' => $sub->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $request->outlet_ids);
                SubCategoryAvailability::insert($data);
            }
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'sub_categories',
            'description' => 'Mengupdate sub kategori: ' . $sub->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $sub->fresh()->toArray(),
        ]);

        return response()->json(['success' => true, 'data' => $sub->fresh(['category', 'availabilities'])]);
    }

    public function apiToggleStatus(Request $request, $id)
    {
        $sub = SubCategory::findOrFail($id);
        $newStatus = $request->get('status', $sub->status === 'active' ? 'inactive' : 'active');
        if (!in_array($newStatus, ['active', 'inactive'])) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 422);
        }
        $sub->update(['status' => $newStatus]);
        return response()->json(['success' => true, 'data' => $sub->fresh()]);
    }

    public function apiDestroy($id)
    {
        $sub = SubCategory::findOrFail($id);
        $sub->availabilities()->delete();
        $sub->delete();
        return response()->json(['success' => true]);
    }
} 