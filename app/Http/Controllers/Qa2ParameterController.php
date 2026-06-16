<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class Qa2ParameterController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A');

        $query = DB::table('qa2_parameters as p')
            ->join('qa2_subcategories as s', 's.id', '=', 'p.subcategory_id')
            ->join('qa2_categories as c', 'c.id', '=', 's.category_id')
            ->select([
                'p.id',
                'p.code',
                'p.parameter_text',
                'p.weight',
                'p.sort_order',
                'p.status',
                'p.subcategory_id',
                's.code as subcategory_code',
                's.name as subcategory_name',
                'c.code as category_code',
                'c.name as category_name',
                'p.updated_at',
            ]);

        if ($status === 'A' || $status === 'N') {
            $query->where('p.status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('p.code', 'like', "%{$search}%")
                    ->orWhere('p.parameter_text', 'like', "%{$search}%")
                    ->orWhere('s.code', 'like', "%{$search}%")
                    ->orWhere('s.name', 'like', "%{$search}%")
                    ->orWhere('c.code', 'like', "%{$search}%")
                    ->orWhere('c.name', 'like', "%{$search}%");
            });
        }

        $parameters = $query
            ->orderByDesc('p.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Qa2Parameters/Index', [
            'parameters' => $parameters,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Qa2Parameters/Form', [
            'mode' => 'create',
            'parameter' => null,
            'categories' => $this->categoriesWithSubcategories(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subcategory_id' => ['required', 'integer', 'exists:qa2_subcategories,id'],
            'code' => ['required', 'string', 'max:40', 'unique:qa2_parameters,code'],
            'parameter_text' => ['required', 'string'],
            'weight' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['A', 'N'])],
        ]);

        DB::table('qa2_parameters')->insert([
            'subcategory_id' => (int) $validated['subcategory_id'],
            'code' => $validated['code'],
            'parameter_text' => $validated['parameter_text'],
            'weight' => (float) $validated['weight'],
            'sort_order' => (int) ($validated['sort_order'] ?? 1),
            'status' => $validated['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('qa2-parameters.index')->with('success', 'QA2 Parameter berhasil dibuat.');
    }

    public function edit(int $id)
    {
        $parameter = DB::table('qa2_parameters')->where('id', $id)->first();
        abort_if(!$parameter, 404);

        return Inertia::render('Qa2Parameters/Form', [
            'mode' => 'edit',
            'parameter' => [
                'id' => $parameter->id,
                'subcategory_id' => $parameter->subcategory_id,
                'code' => $parameter->code,
                'parameter_text' => $parameter->parameter_text,
                'weight' => (float) $parameter->weight,
                'sort_order' => (int) $parameter->sort_order,
                'status' => $parameter->status,
            ],
            'categories' => $this->categoriesWithSubcategories(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        abort_if(!DB::table('qa2_parameters')->where('id', $id)->exists(), 404);

        $validated = $request->validate([
            'subcategory_id' => ['required', 'integer', 'exists:qa2_subcategories,id'],
            'code' => ['required', 'string', 'max:40', Rule::unique('qa2_parameters', 'code')->ignore($id)],
            'parameter_text' => ['required', 'string'],
            'weight' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['A', 'N'])],
        ]);

        DB::table('qa2_parameters')->where('id', $id)->update([
            'subcategory_id' => (int) $validated['subcategory_id'],
            'code' => $validated['code'],
            'parameter_text' => $validated['parameter_text'],
            'weight' => (float) $validated['weight'],
            'sort_order' => (int) ($validated['sort_order'] ?? 1),
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        return redirect()->route('qa2-parameters.index')->with('success', 'QA2 Parameter berhasil diupdate.');
    }

    public function destroy(int $id)
    {
        DB::table('qa2_parameters')->where('id', $id)->update([
            'status' => 'N',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Parameter dinonaktifkan.');
    }

    public function toggleStatus(int $id)
    {
        $parameter = DB::table('qa2_parameters')->where('id', $id)->first();
        abort_if(!$parameter, 404);

        $nextStatus = $parameter->status === 'A' ? 'N' : 'A';

        DB::table('qa2_parameters')->where('id', $id)->update([
            'status' => $nextStatus,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Status parameter berhasil diubah.');
    }

    private function categoriesWithSubcategories(): array
    {
        $categories = DB::table('qa2_categories')
            ->where('status', 'A')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $subcategories = DB::table('qa2_subcategories')
            ->where('status', 'A')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'category_id', 'code', 'name']);

        $subByCategory = [];
        foreach ($subcategories as $sub) {
            $subByCategory[$sub->category_id][] = [
                'id' => (int) $sub->id,
                'code' => $sub->code,
                'name' => $sub->name,
            ];
        }

        $rows = [];
        foreach ($categories as $cat) {
            $rows[] = [
                'id' => (int) $cat->id,
                'code' => $cat->code,
                'name' => $cat->name,
                'subcategories' => $subByCategory[$cat->id] ?? [],
            ];
        }

        return $rows;
    }
}
