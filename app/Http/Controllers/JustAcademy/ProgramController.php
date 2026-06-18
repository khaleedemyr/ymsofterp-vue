<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaCategory;
use App\Models\JustAcademy\JaMaterial;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaQuiz;
use App\Services\JustAcademy\JustAcademyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgramController extends Controller
{
    public function __construct(
        protected JustAcademyService $service,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = JaProgram::with('category:id,name')
            ->withCount('items')
            ->orderByDesc('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return Inertia::render('JustAcademy/Programs/Index', [
            'programs' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create()
    {
        return Inertia::render('JustAcademy/Programs/Form', [
            'program' => null,
            'categories' => JaCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'libraryMaterials' => [],
            'libraryQuizzes' => [],
            'curriculum' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:ja_categories,id',
            'code' => 'nullable|string|max:50|unique:ja_programs,code',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_hours' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,published,archived',
        ]);

        $program = JaProgram::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('just-academy.programs.edit', $program->id)
            ->with('success', 'Program berhasil dibuat. Atur urutan materi & quiz di bawah.');
    }

    public function edit(JaProgram $program)
    {
        $program->load('category:id,name');

        $curriculum = $this->service->getProgramCurriculum($program)->map(fn ($item) => [
            'item_type' => $item->item_type,
            'ref_id' => $item->item_type === 'material' ? $item->material_id : $item->quiz_id,
            'title' => $item->item_type === 'material' ? $item->material?->title : $item->quiz?->title,
            'is_required' => $item->is_required,
        ]);

        return Inertia::render('JustAcademy/Programs/Form', [
            'program' => $program,
            'categories' => JaCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'libraryMaterials' => JaMaterial::where('is_active', true)->orderBy('title')->get(['id', 'title', 'type']),
            'libraryQuizzes' => JaQuiz::where('is_active', true)->orderBy('title')->get(['id', 'title', 'pass_score']),
            'curriculum' => $curriculum,
        ]);
    }

    public function update(Request $request, JaProgram $program)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:ja_categories,id',
            'code' => 'nullable|string|max:50|unique:ja_programs,code,' . $program->id,
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_hours' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,published,archived',
        ]);

        $program->update($validated);

        return back()->with('success', 'Program berhasil diperbarui.');
    }

    public function syncCurriculum(Request $request, JaProgram $program)
    {
        $validated = $request->validate([
            'items' => 'present|array',
            'items.*.item_type' => 'required|in:material,quiz',
            'items.*.ref_id' => 'required|integer|min:1',
            'items.*.is_required' => 'boolean',
        ]);

        $this->service->syncProgramCurriculum($program, $validated['items']);

        return back()->with('success', 'Urutan curriculum program berhasil disimpan.');
    }

    public function destroy(int $id)
    {
        $program = JaProgram::findOrFail($id);

        if ($program->schedules()->exists()) {
            return back()->with('error', 'Program tidak dapat dihapus karena sudah memiliki jadwal.');
        }

        $program->delete();

        return redirect()
            ->route('just-academy.programs.index')
            ->with('success', 'Program berhasil dihapus.');
    }
}
