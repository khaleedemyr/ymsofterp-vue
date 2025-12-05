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

class SubCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = SubCategory::with(['category', 'availabilities.region', 'availabilities.outlet'])
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
        $regions = Region::all();
        $outlets = Outlet::all();

        return Inertia::render('SubCategories/Index', [
            'subCategories' => $subCategories,
            'categories' => $categories,
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
} 