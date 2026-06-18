<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaCategory;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaProgramMaterial;
use App\Models\JustAcademy\JaQuiz;
use App\Models\JustAcademy\JaQuizOption;
use App\Models\JustAcademy\JaQuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = JaProgram::with('category:id,name')
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
            ->with('success', 'Program berhasil dibuat.');
    }

    public function edit(JaProgram $program)
    {
        $program->load([
            'category:id,name',
            'materials' => fn ($q) => $q->orderBy('sort_order'),
            'quizzes.questions.options',
        ]);

        return Inertia::render('JustAcademy/Programs/Form', [
            'program' => $program,
            'categories' => JaCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
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

    public function destroy(JaProgram $program)
    {
        if ($program->schedules()->exists()) {
            return back()->withErrors(['program' => 'Program tidak dapat dihapus karena sudah memiliki jadwal.']);
        }

        $program->delete();

        return redirect()
            ->route('just-academy.programs.index')
            ->with('success', 'Program berhasil dihapus.');
    }

    public function storeMaterial(Request $request, JaProgram $program)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:pdf,video,link,doc,other',
            'url' => 'nullable|string|max:500',
            'file' => 'nullable|file|max:51200',
            'sort_order' => 'nullable|integer|min:0',
            'is_pre_read' => 'boolean',
        ]);

        $path = null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('just-academy/materials', 'public');
        }

        JaProgramMaterial::create([
            'program_id' => $program->id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'file_path' => $path,
            'url' => $validated['url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_pre_read' => $request->boolean('is_pre_read'),
            'is_active' => true,
        ]);

        return back()->with('success', 'Materi berhasil ditambahkan.');
    }

    public function destroyMaterial(JaProgram $program, JaProgramMaterial $material)
    {
        if ($material->program_id !== $program->id) {
            abort(404);
        }

        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return back()->with('success', 'Materi berhasil dihapus.');
    }

    public function storeQuiz(Request $request, JaProgram $program)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:pre,post',
            'pass_score' => 'required|numeric|min:0|max:100',
            'time_limit_min' => 'nullable|integer|min:1',
            'questions' => 'required|array|min:1',
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:mcq,essay',
            'questions.*.points' => 'nullable|numeric|min:0',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.option_text' => 'required_with:questions.*.options|string',
            'questions.*.options.*.is_correct' => 'boolean',
        ]);

        $quiz = JaQuiz::create([
            'program_id' => $program->id,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'pass_score' => $validated['pass_score'],
            'time_limit_min' => $validated['time_limit_min'] ?? null,
            'is_active' => true,
        ]);

        foreach ($validated['questions'] as $i => $qData) {
            $question = JaQuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => $qData['question'],
                'type' => $qData['type'],
                'sort_order' => $i,
                'points' => $qData['points'] ?? 1,
            ]);

            if ($qData['type'] === 'mcq' && !empty($qData['options'])) {
                foreach ($qData['options'] as $j => $opt) {
                    JaQuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => $opt['option_text'],
                        'is_correct' => !empty($opt['is_correct']),
                        'sort_order' => $j,
                    ]);
                }
            }
        }

        return back()->with('success', 'Quiz berhasil ditambahkan.');
    }
}
