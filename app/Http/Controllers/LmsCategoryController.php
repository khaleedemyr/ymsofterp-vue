<?php

namespace App\Http\Controllers;

use App\Models\LmsCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsCategoryController extends Controller
{
    public function index()
    {
        $categories = LmsCategory::withCount('courses')
            ->orderBy('name')
            ->paginate(10);

        return Inertia::render('Lms/Categories/Index', [
            'categories' => $categories
        ]);
    }

    public function create()
    {
        return Inertia::render('Lms/Categories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:lms_categories,id',
            'status' => 'required|in:active,inactive'
        ]);

        $validated['created_by'] = auth()->id();

        LmsCategory::create($validated);

        return redirect()->route('lms.categories.index')
            ->with('success', 'Kategori berhasil dibuat');
    }

    public function show(LmsCategory $category)
    {
        $category->load(['courses', 'parent', 'children']);

        return Inertia::render('Lms/Categories/Show', [
            'category' => $category
        ]);
    }

    public function edit(LmsCategory $category)
    {
        return Inertia::render('Lms/Categories/Edit', [
            'category' => $category
        ]);
    }

    public function update(Request $request, LmsCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:lms_categories,id',
            'status' => 'required|in:active,inactive'
        ]);

        $category->update($validated);

        return redirect()->route('lms.categories.index')
            ->with('success', 'Kategori berhasil diperbarui');
    }

    public function destroy(LmsCategory $category)
    {
        if ($category->courses()->count() > 0) {
            return back()->withErrors(['error' => 'Tidak dapat menghapus kategori yang memiliki kursus']);
        }

        $category->delete();

        return redirect()->route('lms.categories.index')
            ->with('success', 'Kategori berhasil dihapus');
    }
} 