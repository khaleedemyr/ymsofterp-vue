<?php

namespace App\Http\Controllers;

use App\Models\QaCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QaCategoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A'); // Default to active categories
        $perPage = $request->input('per_page', 15); // Default to 15 per page

        $query = QaCategory::query();
        
        // Filter by status
        if ($status === 'A') {
            $query->where('status', 'A'); // Active categories only
        } elseif ($status === 'N') {
            $query->where('status', 'N'); // Non-active categories only
        }
        // If status is 'all', don't filter by status (show all)
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('kode_categories', 'like', "%$search%")
                  ->orWhere('categories', 'like', "%$search%");
            });
        }
        
        $categories = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();

        // Get statistics
        $total = QaCategory::count();
        $active = QaCategory::where('status', 'A')->count();
        $inactive = QaCategory::where('status', 'N')->count();
        
        $statistics = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];

        return Inertia::render('QaCategories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'statistics' => $statistics,
        ]);
    }

    private function generateKodeCategories()
    {
        // Get the last kode_categories
        $lastCategory = QaCategory::orderBy('id', 'desc')->first();
        
        if ($lastCategory) {
            // Extract number from last kode_categories (e.g., QA001 -> 1)
            $lastNumber = intval(preg_replace('/[^0-9]/', '', $lastCategory->kode_categories));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return 'QA' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    public function create()
    {
        return Inertia::render('QaCategories/Create', [
            'category' => [
                'kode_categories' => $this->generateKodeCategories(),
                'categories' => '',
                'status' => 'A',
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_categories' => 'nullable|string|max:50|unique:qa_categories,kode_categories',
            'categories' => 'required|string|max:255',
            'status' => 'required|string|in:A,N',
        ]);

        // Auto generate kode if not provided
        if (empty($validated['kode_categories'])) {
            $validated['kode_categories'] = $this->generateKodeCategories();
        }

        try {
            QaCategory::create($validated);
            return redirect()->route('qa-categories.index')->with('success', 'QA Category berhasil ditambahkan dengan kode: ' . $validated['kode_categories']);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menyimpan data: ' . $e->getMessage()]);
        }
    }

    public function show(QaCategory $qaCategory)
    {
        return Inertia::render('QaCategories/Show', [
            'category' => $qaCategory
        ]);
    }

    public function edit(QaCategory $qaCategory)
    {
        return Inertia::render('QaCategories/Edit', [
            'category' => $qaCategory
        ]);
    }

    public function update(Request $request, QaCategory $qaCategory)
    {
        $validated = $request->validate([
            'kode_categories' => 'required|string|max:50|unique:qa_categories,kode_categories,' . $qaCategory->id,
            'categories' => 'required|string|max:255',
            'status' => 'required|string|in:A,N',
        ]);

        try {
            $qaCategory->update($validated);
            return redirect()->route('qa-categories.show', $qaCategory->id)->with('success', 'QA Category berhasil diupdate');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal update data: ' . $e->getMessage()]);
        }
    }

    public function destroy(QaCategory $qaCategory)
    {
        // Set status to 'N' (Non-aktif) instead of deleting
        $qaCategory->update(['status' => 'N']);
        
        return redirect()->back()->with('success', 'QA Category berhasil dinonaktifkan!');
    }

    public function toggleStatus(QaCategory $qaCategory)
    {
        $newStatus = $qaCategory->status === 'A' ? 'N' : 'A';
        $qaCategory->update(['status' => $newStatus]);
        
        return response()->json([
            'success' => true,
            'message' => 'Status QA Category berhasil diubah!',
            'new_status' => $newStatus
        ]);
    }
}
