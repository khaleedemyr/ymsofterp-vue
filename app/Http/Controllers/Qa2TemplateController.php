<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class Qa2TemplateController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'A');

        $query = DB::table('qa2_templates')->select([
            'id',
            'code',
            'name',
            'audit_type',
            'department',
            'version',
            'status',
            'updated_at',
        ]);

        if ($status === 'A' || $status === 'N') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('audit_type', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        $templates = $query
            ->orderByDesc('id')
            ->paginate(15)
            ->through(function ($row) {
                $row->items_count = DB::table('qa2_template_items')->where('template_id', $row->id)->count();
                return $row;
            })
            ->withQueryString();

        return Inertia::render('Qa2Templates/Index', [
            'templates' => $templates,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Qa2Templates/Form', [
            'mode' => 'create',
            'template' => null,
            'categories' => $this->categoriesTree(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:180',
            'audit_type' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'version' => 'required|integer|min:1',
            'status' => 'required|in:A,N',
            'notes' => 'nullable|string',
            'parameter_ids' => 'required|array|min:1',
            'parameter_ids.*' => 'integer|exists:qa2_parameters,id',
        ]);

        DB::transaction(function () use ($validated) {
            $exists = DB::table('qa2_templates')
                ->where('code', $validated['code'])
                ->where('version', $validated['version'])
                ->exists();

            if ($exists) {
                abort(422, 'Code dan version sudah dipakai.');
            }

            $templateId = DB::table('qa2_templates')->insertGetId([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'audit_type' => $validated['audit_type'] ?? null,
                'department' => $validated['department'] ?? null,
                'version' => $validated['version'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncTemplateItems($templateId, $validated['parameter_ids']);
        });

        return redirect()->route('qa2-templates.index')->with('success', 'QA2 Template berhasil dibuat.');
    }

    public function edit(int $id)
    {
        $template = DB::table('qa2_templates')->where('id', $id)->first();

        abort_if(!$template, 404);

        $selected = DB::table('qa2_template_items')
            ->where('template_id', $id)
            ->pluck('parameter_id')
            ->map(fn ($x) => (int) $x)
            ->values()
            ->all();

        return Inertia::render('Qa2Templates/Form', [
            'mode' => 'edit',
            'template' => [
                'id' => $template->id,
                'code' => $template->code,
                'name' => $template->name,
                'audit_type' => $template->audit_type,
                'department' => $template->department,
                'version' => $template->version,
                'status' => $template->status,
                'notes' => $template->notes,
                'parameter_ids' => $selected,
            ],
            'categories' => $this->categoriesTree(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $template = DB::table('qa2_templates')->where('id', $id)->first();
        abort_if(!$template, 404);

        $validated = $request->validate([
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:180',
            'audit_type' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'version' => 'required|integer|min:1',
            'status' => 'required|in:A,N',
            'notes' => 'nullable|string',
            'parameter_ids' => 'required|array|min:1',
            'parameter_ids.*' => 'integer|exists:qa2_parameters,id',
        ]);

        DB::transaction(function () use ($validated, $id) {
            $exists = DB::table('qa2_templates')
                ->where('code', $validated['code'])
                ->where('version', $validated['version'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                abort(422, 'Code dan version sudah dipakai template lain.');
            }

            DB::table('qa2_templates')->where('id', $id)->update([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'audit_type' => $validated['audit_type'] ?? null,
                'department' => $validated['department'] ?? null,
                'version' => $validated['version'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'updated_at' => now(),
            ]);

            DB::table('qa2_template_items')->where('template_id', $id)->delete();
            $this->syncTemplateItems($id, $validated['parameter_ids']);
        });

        return redirect()->route('qa2-templates.index')->with('success', 'QA2 Template berhasil diupdate.');
    }

    public function destroy(int $id)
    {
        DB::table('qa2_templates')->where('id', $id)->update([
            'status' => 'N',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Template dinonaktifkan.');
    }

    public function toggleStatus(int $id)
    {
        $template = DB::table('qa2_templates')->where('id', $id)->first();
        abort_if(!$template, 404);

        $nextStatus = $template->status === 'A' ? 'N' : 'A';

        DB::table('qa2_templates')->where('id', $id)->update([
            'status' => $nextStatus,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Status template berhasil diubah.');
    }

    private function syncTemplateItems(int $templateId, array $parameterIds): void
    {
        $rows = [];
        $sort = 1;
        foreach (array_values(array_unique(array_map('intval', $parameterIds))) as $parameterId) {
            $rows[] = [
                'template_id' => $templateId,
                'parameter_id' => $parameterId,
                'sort_order' => $sort++,
                'is_required' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('qa2_template_items')->insert($rows);
        }
    }

    private function categoriesTree(): array
    {
        $categories = DB::table('qa2_categories')
            ->where('status', 'A')
            ->orderBy('id')
            ->get();

        $subcategories = DB::table('qa2_subcategories')
            ->where('status', 'A')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $parameters = DB::table('qa2_parameters')
            ->where('status', 'A')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $subByCat = [];
        foreach ($subcategories as $sub) {
            $subByCat[$sub->category_id][] = $sub;
        }

        $paramBySub = [];
        foreach ($parameters as $param) {
            $paramBySub[$param->subcategory_id][] = [
                'id' => (int) $param->id,
                'code' => $param->code,
                'text' => $param->parameter_text,
                'weight' => (float) $param->weight,
            ];
        }

        $tree = [];
        foreach ($categories as $cat) {
            $subs = [];
            foreach ($subByCat[$cat->id] ?? [] as $sub) {
                $subs[] = [
                    'id' => (int) $sub->id,
                    'code' => $sub->code,
                    'name' => $sub->name,
                    'parameters' => $paramBySub[$sub->id] ?? [],
                ];
            }

            $tree[] = [
                'id' => (int) $cat->id,
                'code' => $cat->code,
                'name' => $cat->name,
                'subcategories' => $subs,
            ];
        }

        return $tree;
    }
}
