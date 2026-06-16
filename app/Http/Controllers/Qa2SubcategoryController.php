<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class Qa2SubcategoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A');

        $query = DB::table('qa2_subcategories as s')
            ->join('qa2_categories as c', 'c.id', '=', 's.category_id')
            ->select([
                's.id',
                's.code',
                's.name',
                's.sort_order',
                's.status',
                's.category_id',
                'c.code as category_code',
                'c.name as category_name',
                's.updated_at',
            ]);

        if ($status === 'A' || $status === 'N') {
            $query->where('s.status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('s.code', 'like', "%{$search}%")
                    ->orWhere('s.name', 'like', "%{$search}%")
                    ->orWhere('c.code', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%");
            });
        }

        $subcategories = $query
            ->orderByDesc('s.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Qa2Subcategories/Index', [
            'subcategories' => $subcategories,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Qa2Subcategories/Form', [
            'mode' => 'create',
            'subcategory' => null,
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:qa2_categories,id'],
            'code' => ['required', 'string', 'max:30', 'unique:qa2_subcategories,code'],
            'name' => ['required', 'string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['A', 'N'])],
        ]);

        DB::table('qa2_subcategories')->insert([
            'category_id' => (int) $validated['category_id'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 1),
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('qa2-subcategories.index')->with('success', 'QA2 Subcategory berhasil dibuat.');
    }

    public function edit(int $id)
    {
        $subcategory = DB::table('qa2_subcategories')->where('id', $id)->first();
        abort_if(!$subcategory, 404);

        return Inertia::render('Qa2Subcategories/Form', [
            'mode' => 'edit',
            'subcategory' => [
                'id' => $subcategory->id,
                'category_id' => $subcategory->category_id,
                'code' => $subcategory->code,
                'name' => $subcategory->name,
                'sort_order' => (int) $subcategory->sort_order,
                'status' => $subcategory->status,
            ],
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        abort_if(!DB::table('qa2_subcategories')->where('id', $id)->exists(), 404);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:qa2_categories,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('qa2_subcategories', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['A', 'N'])],
        ]);

        DB::table('qa2_subcategories')->where('id', $id)->update([
            'category_id' => (int) $validated['category_id'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 1),
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        return redirect()->route('qa2-subcategories.index')->with('success', 'QA2 Subcategory berhasil diupdate.');
    }

    public function destroy(int $id)
    {
        DB::table('qa2_subcategories')->where('id', $id)->update([
            'status' => 'N',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Subcategory dinonaktifkan.');
    }

    public function toggleStatus(int $id)
    {
        $subcategory = DB::table('qa2_subcategories')->where('id', $id)->first();
        abort_if(!$subcategory, 404);

        $nextStatus = $subcategory->status === 'A' ? 'N' : 'A';

        DB::table('qa2_subcategories')->where('id', $id)->update([
            'status' => $nextStatus,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Status subcategory berhasil diubah.');
    }

    private function categories()
    {
        return DB::table('qa2_categories')
            ->select('id', 'code', 'name')
            ->where('status', 'A')
            ->orderBy('name')
            ->get();
    }
}
