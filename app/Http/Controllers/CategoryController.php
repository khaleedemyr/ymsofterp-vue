<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Category;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $categories = $query->with(['availabilities.outlet'])->orderBy('id', 'desc')->paginate(10)->withQueryString();
        $regions = \DB::table('regions')->where('status', 'active')->select('id', 'code', 'name')->get();
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->select('id_outlet as id', 'nama_outlet', 'region_id')->get();
        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $request->search,
            ],
            'regions' => $regions,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('CategoryController@store - request', $request->all());
        \Log::info('CategoryController@store - outlet_ids', ['outlet_ids' => $request->outlet_ids]);
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:categories,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'show_pos' => 'required|in:0,1',
            'outlet_ids' => 'array',
            'outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
        ]);
        $categoryData = $validated;
        unset($categoryData['outlet_ids']);
        $category = \App\Models\Category::create($categoryData);

        // Simpan relasi ke tabel pivot
        if (!empty($request->outlet_ids)) {
            $insertData = [];
            foreach ($request->outlet_ids as $outletId) {
                $insertData[] = [
                    'category_id' => $category->id,
                    'outlet_id' => $outletId,
                ];
            }
            try {
                \DB::table('category_outlet')->insert($insertData);
                \Log::info('CategoryController@store - insert category_outlet success');
            } catch (\Exception $e) {
                \Log::error('CategoryController@store - error insert category_outlet', ['error' => $e->getMessage()]);
            }
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'categories',
            'description' => 'Menambahkan kategori baru: ' . $category->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $category->toArray()
        ]);

        \Log::info('CategoryController@store - success', ['category_id' => $category->id]);
        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        \Log::info('CategoryController@update - request', $request->all());
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:categories,code,' . $id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'show_pos' => 'required|in:0,1',
            'outlet_ids' => 'array',
            'outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
        ]);

        $cat = \App\Models\Category::findOrFail($id);
        $oldData = $cat->toArray();

        $categoryData = $validated;
        unset($categoryData['outlet_ids']);

        $cat->update($categoryData);

        // Update relasi ke tabel pivot
        \DB::table('category_outlet')->where('category_id', $id)->delete();
        if (!empty($request->outlet_ids)) {
            $insertData = [];
            foreach ($request->outlet_ids as $outletId) {
                $insertData[] = [
                    'category_id' => $id,
                    'outlet_id' => $outletId,
                ];
            }
            \DB::table('category_outlet')->insert($insertData);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'categories',
            'description' => 'Mengupdate kategori: ' . $cat->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $cat->fresh()->toArray()
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diupdate!');
    }

    public function destroy($id)
    {
        $cat = \App\Models\Category::findOrFail($id);
        $oldData = $cat->toArray();
        
        $cat->update(['status' => 'inactive']);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'categories',
            'description' => 'Menonaktifkan kategori: ' . $cat->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $cat->fresh()->toArray()
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil dinonaktifkan!');
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        $category->outlet_ids = DB::table('category_outlet')->where('category_id', $category->id)->pluck('outlet_id')->toArray();
        return Inertia::render('Categories/Show', [
            'category' => $category,
        ]);
    }

    public function toggleStatus($id, Request $request)
    {
        $cat = Category::findOrFail($id);
        $cat->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }

    // ---------- Approval App API (JSON) ----------

    public function apiIndex(Request $request)
    {
        $query = Category::query()->with(['availabilities.outlet']);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $perPage = (int) $request->get('per_page', 15);
        $categories = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
        // Add outlet_ids to each category for form
        $categories->getCollection()->transform(function ($cat) {
            $cat->outlet_ids = DB::table('category_outlet')->where('category_id', $cat->id)->pluck('outlet_id')->toArray();
            return $cat;
        });
        return response()->json($categories);
    }

    public function apiCreateData()
    {
        $regions = \DB::table('regions')->where('status', 'active')->select('id', 'code', 'name')->get();
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->select('id_outlet as id', 'nama_outlet', 'region_id')->get();
        return response()->json([
            'regions' => $regions,
            'outlets' => $outlets,
        ]);
    }

    public function apiShow($id)
    {
        $category = Category::with(['availabilities.outlet'])->findOrFail($id);
        $category->outlet_ids = DB::table('category_outlet')->where('category_id', $category->id)->pluck('outlet_id')->toArray();
        return response()->json($category);
    }

    public function apiStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:categories,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'show_pos' => 'required|in:0,1',
            'outlet_ids' => 'array',
            'outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
        ]);
        $categoryData = collect($validated)->except('outlet_ids')->all();
        $category = Category::create($categoryData);
        if (!empty($request->outlet_ids)) {
            $insertData = array_map(function ($outletId) use ($category) {
                return ['category_id' => $category->id, 'outlet_id' => $outletId];
            }, $request->outlet_ids);
            DB::table('category_outlet')->insert($insertData);
        }
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'categories',
            'description' => 'Menambahkan kategori baru: ' . $category->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $category->toArray(),
        ]);
        return response()->json(['success' => true, 'data' => $category->fresh(['availabilities.outlet'])]);
    }

    public function apiUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:categories,code,' . $id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'show_pos' => 'required|in:0,1',
            'outlet_ids' => 'array',
            'outlet_ids.*' => 'integer|exists:tbl_data_outlet,id_outlet',
        ]);
        $cat = Category::findOrFail($id);
        $categoryData = collect($validated)->except('outlet_ids')->all();
        $cat->update($categoryData);
        DB::table('category_outlet')->where('category_id', $id)->delete();
        if (!empty($request->outlet_ids)) {
            $insertData = array_map(function ($outletId) use ($id) {
                return ['category_id' => (int) $id, 'outlet_id' => $outletId];
            }, $request->outlet_ids);
            DB::table('category_outlet')->insert($insertData);
        }
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'categories',
            'description' => 'Mengupdate kategori: ' . $cat->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $cat->fresh()->toArray(),
        ]);
        return response()->json(['success' => true, 'data' => $cat->fresh(['availabilities.outlet'])]);
    }

    public function apiToggleStatus(Request $request, $id)
    {
        $cat = Category::findOrFail($id);
        $newStatus = $request->get('status', $cat->status === 'active' ? 'inactive' : 'active');
        if (!in_array($newStatus, ['active', 'inactive'])) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 422);
        }
        $cat->update(['status' => $newStatus]);
        return response()->json(['success' => true, 'data' => $cat->fresh()]);
    }

    public function apiDestroy($id)
    {
        $cat = Category::findOrFail($id);
        $oldData = $cat->toArray();
        $cat->update(['status' => 'inactive']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'categories',
            'description' => 'Menonaktifkan kategori: ' . $cat->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $cat->fresh()->toArray(),
        ]);
        return response()->json(['success' => true]);
    }
}
