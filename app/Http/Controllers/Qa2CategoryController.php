<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class Qa2CategoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A');

        $query = DB::table('qa2_categories')->select([
            'id',
            'code',
            'name',
            'status',
            'updated_at',
        ]);

        if ($status === 'A' || $status === 'N') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $categories = $query
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Qa2Categories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Qa2Categories/Form', [
            'mode' => 'create',
            'category' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:qa2_categories,code'],
            'name' => ['required', 'string', 'max:180'],
            'status' => ['required', Rule::in(['A', 'N'])],
        ]);

        DB::table('qa2_categories')->insert([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('qa2-categories.index')->with('success', 'QA2 Category berhasil dibuat.');
    }

    public function edit(int $id)
    {
        $category = DB::table('qa2_categories')->where('id', $id)->first();
        abort_if(!$category, 404);

        return Inertia::render('Qa2Categories/Form', [
            'mode' => 'edit',
            'category' => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'status' => $category->status,
            ],
        ]);
    }

    public function update(Request $request, int $id)
    {
        abort_if(!DB::table('qa2_categories')->where('id', $id)->exists(), 404);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('qa2_categories', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:180'],
            'status' => ['required', Rule::in(['A', 'N'])],
        ]);

        DB::table('qa2_categories')->where('id', $id)->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        return redirect()->route('qa2-categories.index')->with('success', 'QA2 Category berhasil diupdate.');
    }

    public function destroy(int $id)
    {
        DB::table('qa2_categories')->where('id', $id)->update([
            'status' => 'N',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Category dinonaktifkan.');
    }

    public function toggleStatus(int $id)
    {
        $category = DB::table('qa2_categories')->where('id', $id)->first();
        abort_if(!$category, 404);

        $nextStatus = $category->status === 'A' ? 'N' : 'A';

        DB::table('qa2_categories')->where('id', $id)->update([
            'status' => $nextStatus,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Status category berhasil diubah.');
    }
}
