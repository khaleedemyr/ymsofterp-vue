<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = ChartOfAccount::with(['parent', 'children']);
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === '1' ? 1 : 0);
        }
        
        // Filter hanya parent (untuk tree view)
        if ($request->filled('show_only_parents') && $request->show_only_parents === '1') {
            $query->whereNull('parent_id');
        }
        
        $perPage = $request->get('per_page', 15);
        $chartOfAccounts = $query->orderBy('code')->paginate($perPage)->withQueryString();
        
        // Get all CoAs for dropdown (support multi-level, bukan hanya root)
        $allCoAs = ChartOfAccount::where('is_active', 1)
            ->with('parent')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'parent_id']);
        
        // Format dengan hierarki untuk dropdown
        $parents = $this->formatCoAsForDropdown($allCoAs);
        
        // Get statistics
        $statistics = [
            'total' => ChartOfAccount::count(),
            'active' => ChartOfAccount::where('is_active', 1)->count(),
            'inactive' => ChartOfAccount::where('is_active', 0)->count(),
            'by_type' => [
                'Asset' => ChartOfAccount::where('type', 'Asset')->where('is_active', 1)->count(),
                'Liability' => ChartOfAccount::where('type', 'Liability')->where('is_active', 1)->count(),
                'Equity' => ChartOfAccount::where('type', 'Equity')->where('is_active', 1)->count(),
                'Revenue' => ChartOfAccount::where('type', 'Revenue')->where('is_active', 1)->count(),
                'Expense' => ChartOfAccount::where('type', 'Expense')->where('is_active', 1)->count(),
            ],
        ];
        
        return Inertia::render('ChartOfAccounts/Index', [
            'chartOfAccounts' => $chartOfAccounts,
            'parents' => $parents,
            'statistics' => $statistics,
            'filters' => [
                'search' => $request->search,
                'type' => $request->type,
                'is_active' => $request->is_active,
                'show_only_parents' => $request->show_only_parents,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create()
    {
        // Get all CoAs for dropdown (support multi-level)
        $allCoAs = ChartOfAccount::where('is_active', 1)
            ->with('parent')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'parent_id']);
        
        // Format dengan hierarki untuk dropdown
        $parents = $this->formatCoAsForDropdown($allCoAs);
        
        return Inertia::render('ChartOfAccounts/Form', [
            'chartOfAccount' => null,
            'parents' => $parents,
        ]);
    }
    
    // Helper method to format CoAs with hierarchy for dropdown
    private function formatCoAsForDropdown($coAs, $excludeIds = [], $parentId = null, $level = 0)
    {
        $result = [];
        $prefix = str_repeat('  ', $level); // Indentasi untuk hierarki
        
        foreach ($coAs as $coa) {
            // Skip jika dalam exclude list
            if (in_array($coa->id, $excludeIds)) {
                continue;
            }
            
            // Jika parent_id sesuai dengan level yang dicari
            if (($parentId === null && $coa->parent_id === null) || 
                ($parentId !== null && $coa->parent_id == $parentId)) {
                
                // Format display dengan indentasi dan full code path
                $displayCode = $this->getFullCodePath($coa);
                
                $result[] = [
                    'id' => $coa->id,
                    'code' => $coa->code,
                    'name' => $coa->name,
                    'type' => $coa->type,
                    'parent_id' => $coa->parent_id,
                    'display' => $prefix . $displayCode . ' - ' . $coa->name . ' (' . $coa->type . ')',
                    'level' => $level,
                ];
                
                // Recursively get children
                $children = $this->formatCoAsForDropdown($coAs, $excludeIds, $coa->id, $level + 1);
                $result = array_merge($result, $children);
            }
        }
        
        return $result;
    }
    
    // Helper to get full code path (parent.code.child.code)
    private function getFullCodePath($coa)
    {
        $path = [];
        $current = $coa;
        
        // Build path from root to current
        while ($current) {
            array_unshift($path, $current->code);
            $current = $current->parent ?? null;
        }
        
        return implode('.', $path);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:chart_of_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:Asset,Liability,Equity,Revenue,Expense',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? 1 : 0;
        $validated['parent_id'] = $request->parent_id ?: null;

        ChartOfAccount::create($validated);

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'Chart of Account berhasil dibuat!');
    }

    public function edit($id)
    {
        $chartOfAccount = ChartOfAccount::findOrFail($id);
        
        // Get all child IDs recursively to prevent circular reference
        $childIds = $this->getChildIds($id);
        $childIds[] = $id; // Include current ID
        
        // Get all CoAs for dropdown (support multi-level, exclude current and its children)
        $allCoAs = ChartOfAccount::where('is_active', 1)
            ->whereNotIn('id', $childIds)
            ->with('parent')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'parent_id']);
        
        // Format dengan hierarki untuk dropdown
        $parents = $this->formatCoAsForDropdown($allCoAs, $childIds);
        
        return Inertia::render('ChartOfAccounts/Form', [
            'chartOfAccount' => $chartOfAccount,
            'parents' => $parents,
        ]);
    }
    
    // Helper method to get all child IDs recursively
    private function getChildIds($parentId)
    {
        $childIds = [];
        $children = ChartOfAccount::where('parent_id', $parentId)->pluck('id');
        
        foreach ($children as $childId) {
            $childIds[] = $childId;
            $childIds = array_merge($childIds, $this->getChildIds($childId));
        }
        
        return $childIds;
    }

    public function update(Request $request, $id)
    {
        $chartOfAccount = ChartOfAccount::findOrFail($id);
        
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:chart_of_accounts,code,' . $id,
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:Asset,Liability,Equity,Revenue,Expense',
            'parent_id' => 'nullable|exists:chart_of_accounts,id|different:' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Prevent circular reference: check if parent_id is not a child of current (recursively)
        if ($request->parent_id) {
            $childIds = $this->getChildIds($id);
            if (in_array($request->parent_id, $childIds)) {
                return back()->withErrors(['parent_id' => 'Tidak bisa memilih child atau descendant sebagai parent.']);
            }
        }

        $validated['is_active'] = $request->has('is_active') ? 1 : 0;
        $validated['parent_id'] = $request->parent_id ?: null;

        $chartOfAccount->update($validated);

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'Chart of Account berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $chartOfAccount = ChartOfAccount::findOrFail($id);
        $chartOfAccount->delete();

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'Chart of Account berhasil dihapus!');
    }
}

