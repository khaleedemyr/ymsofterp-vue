<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SubCategory;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;

class SubCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = SubCategory::query()->with('category');
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $subCategories = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        // Tambahkan category_name ke setiap sub category
        $subCategories->getCollection()->transform(function($item) {
            $item->category_name = $item->category ? $item->category->name : '-';
            return $item;
        });
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return Inertia::render('SubCategories/Index', [
            'subCategories' => $subCategories,
            'filters' => [
                'search' => $request->search,
            ],
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);
        $sub = SubCategory::create($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'create',
            'module' => 'sub_categories',
            'description' => 'Menambahkan sub kategori baru: ' . $sub->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $sub->toArray(),
        ]);
        return redirect()->route('sub-categories.index')->with('success', 'Sub kategori berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);
        $sub = SubCategory::findOrFail($id);
        $oldData = $sub->toArray();
        $sub->update($validated);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'sub_categories',
            'description' => 'Mengupdate sub kategori: ' . $sub->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $sub->fresh()->toArray(),
        ]);
        return redirect()->route('sub-categories.index')->with('success', 'Sub kategori berhasil diupdate!');
    }

    public function destroy($id)
    {
        $sub = SubCategory::findOrFail($id);
        $oldData = $sub->toArray();
        $sub->update(['status' => 'inactive']);
        ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'sub_categories',
            'description' => 'Menonaktifkan sub kategori: ' . $sub->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => $sub->fresh()->toArray(),
        ]);
        return redirect()->route('sub-categories.index')->with('success', 'Sub kategori berhasil dinonaktifkan!');
    }

    public function toggleStatus($id, Request $request)
    {
        $sub = SubCategory::findOrFail($id);
        $sub->update(['status' => $request->status]);
        return response()->json(['success' => true]);
    }
} 