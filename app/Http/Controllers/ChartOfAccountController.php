<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ChartOfAccount;
use App\Models\Menu;
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
        
        // Get all menus for dropdown with parent info
        $menus = Menu::with('parent')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'route', 'parent_id', 'icon']);
        
        // Get all menus for display in frontend (with parent info)
        $allMenus = Menu::with('parent')->orderBy('name')->get(['id', 'name', 'code', 'route', 'parent_id', 'icon']);
        
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
            'menus' => $menus,
            'allMenus' => $allMenus,
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
        
        // Get all menus for dropdown with parent info
        $menus = Menu::with('parent')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'route', 'parent_id', 'icon']);
        
        return Inertia::render('ChartOfAccounts/Form', [
            'chartOfAccount' => null,
            'parents' => $parents,
            'menus' => $menus,
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
        $rules = [
            'code' => 'required|string|max:50|unique:chart_of_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:Asset,Liability,Equity,Revenue,Expense',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'show_in_menu_payment' => 'boolean',
            'static_or_dynamic' => 'nullable|string|in:static,dynamic',
            'menu_id' => 'nullable|array',
            'menu_id.*' => 'exists:erp_menu,id',
            'mode_payment' => 'nullable|array',
            'mode_payment.*' => 'in:pr_ops,purchase_payment,travel_application,kasbon',
            'budget_limit' => 'nullable|numeric|min:0',
        ];
        
        // Jika static_or_dynamic = static, maka menu_id wajib diisi (minimal 1)
        if ($request->has('static_or_dynamic') && $request->static_or_dynamic === 'static') {
            $rules['menu_id'] = 'required|array|min:1';
            $rules['menu_id.*'] = 'required|exists:erp_menu,id';
        }
        
        // Jika show_in_menu_payment = true, maka mode_payment wajib diisi (minimal 1)
        if ($request->has('show_in_menu_payment') && $request->show_in_menu_payment) {
            $rules['mode_payment'] = 'required|array|min:1';
            $rules['mode_payment.*'] = 'required|in:pr_ops,purchase_payment,travel_application,kasbon';
        }
        
        $validated = $request->validate($rules);

        $validated['is_active'] = $request->has('is_active') ? 1 : 0;
        $validated['parent_id'] = $request->parent_id ?: null;
        $validated['show_in_menu_payment'] = $request->has('show_in_menu_payment') ? 1 : 0;
        
        // Mode payment hanya diisi jika show_in_menu_payment = true
        if (!$validated['show_in_menu_payment']) {
            $validated['mode_payment'] = null;
        } else {
            // Ensure mode_payment is an array and remove duplicates
            if (isset($validated['mode_payment']) && is_array($validated['mode_payment'])) {
                $validated['mode_payment'] = array_unique($validated['mode_payment']);
                $validated['mode_payment'] = array_values($validated['mode_payment']); // Re-index array
            }
        }
        
        // Menu ID hanya diisi jika static_or_dynamic = static
        if ($validated['static_or_dynamic'] !== 'static') {
            $validated['menu_id'] = null;
        } else {
            // Ensure menu_id is an array and remove duplicates
            if (isset($validated['menu_id']) && is_array($validated['menu_id'])) {
                $validated['menu_id'] = array_unique($validated['menu_id']);
                $validated['menu_id'] = array_values(array_map('intval', $validated['menu_id'])); // Convert to integers and re-index
            }
        }

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
        
        // Get all menus for dropdown with parent info
        $menus = Menu::with('parent')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'route', 'parent_id', 'icon']);
        
        return Inertia::render('ChartOfAccounts/Form', [
            'chartOfAccount' => $chartOfAccount,
            'parents' => $parents,
            'menus' => $menus,
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
        
        $rules = [
            'code' => 'required|string|max:50|unique:chart_of_accounts,code,' . $id,
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:Asset,Liability,Equity,Revenue,Expense',
            'parent_id' => 'nullable|exists:chart_of_accounts,id|different:' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'show_in_menu_payment' => 'boolean',
            'static_or_dynamic' => 'nullable|string|in:static,dynamic',
            'menu_id' => 'nullable|array',
            'menu_id.*' => 'exists:erp_menu,id',
            'mode_payment' => 'nullable|array',
            'mode_payment.*' => 'in:pr_ops,purchase_payment,travel_application,kasbon',
            'budget_limit' => 'nullable|numeric|min:0',
        ];
        
        // Jika static_or_dynamic = static, maka menu_id wajib diisi (minimal 1)
        if ($request->has('static_or_dynamic') && $request->static_or_dynamic === 'static') {
            $rules['menu_id'] = 'required|array|min:1';
            $rules['menu_id.*'] = 'required|exists:erp_menu,id';
        }
        
        // Jika show_in_menu_payment = true, maka mode_payment wajib diisi (minimal 1)
        if ($request->has('show_in_menu_payment') && $request->show_in_menu_payment) {
            $rules['mode_payment'] = 'required|array|min:1';
            $rules['mode_payment.*'] = 'required|in:pr_ops,purchase_payment,travel_application,kasbon';
        }
        
        $validated = $request->validate($rules);

        // Prevent circular reference: check if parent_id is not a child of current (recursively)
        if ($request->parent_id) {
            $childIds = $this->getChildIds($id);
            if (in_array($request->parent_id, $childIds)) {
                return back()->withErrors(['parent_id' => 'Tidak bisa memilih child atau descendant sebagai parent.']);
            }
        }

        $validated['is_active'] = $request->has('is_active') ? 1 : 0;
        $validated['parent_id'] = $request->parent_id ?: null;
        $validated['show_in_menu_payment'] = $request->has('show_in_menu_payment') ? 1 : 0;
        
        // Mode payment hanya diisi jika show_in_menu_payment = true
        if (!$validated['show_in_menu_payment']) {
            $validated['mode_payment'] = null;
        }
        
        // Menu ID hanya diisi jika static_or_dynamic = static
        if ($validated['static_or_dynamic'] !== 'static') {
            $validated['menu_id'] = null;
        } else {
            // Ensure menu_id is an array and remove duplicates
            if (isset($validated['menu_id']) && is_array($validated['menu_id'])) {
                $validated['menu_id'] = array_unique($validated['menu_id']);
                $validated['menu_id'] = array_values(array_map('intval', $validated['menu_id'])); // Convert to integers and re-index
            }
        }

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

    public function toggleStatus($id)
    {
        $chartOfAccount = ChartOfAccount::findOrFail($id);
        
        // Toggle status: jika 1 jadi 0, jika 0 jadi 1
        $newStatus = $chartOfAccount->is_active == 1 ? 0 : 1;
        
        // Update langsung ke database tanpa cast
        $chartOfAccount->is_active = $newStatus;
        $chartOfAccount->save();

        // Refresh model untuk mendapatkan nilai terbaru
        $chartOfAccount->refresh();

        if (request()->expectsJson() || request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Status berhasil diubah',
                'is_active' => $chartOfAccount->is_active == 1
            ], 200);
        }

        return redirect()->route('chart-of-accounts.index')
            ->with('success', 'Status berhasil diubah!');
    }
}

