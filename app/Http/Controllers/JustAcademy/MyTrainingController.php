<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaFeedback;
use App\Models\JustAcademy\JaSchedule;
use App\Services\JustAcademy\JustAcademyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyTrainingController extends Controller
{
    public function __construct(
        protected JustAcademyService $service,
    ) {}

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $tab = $request->input('tab', 'upcoming');

        $query = JaSchedule::with(['program:id,title', 'outlet:id_outlet,nama_outlet'])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('start_at');

        if ($tab === 'past') {
            $query->where('end_at', '<', now());
        } else {
            $query->where('end_at', '>=', now()->subDay());
        }

        return Inertia::render('JustAcademy/MyTraining/Index', [
            'schedules' => $query->paginate(12)->withQueryString(),
            'tab' => $tab,
        ]);
    }

    public function show(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $schedule->load([
            'program.materials' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'program.quizzes' => fn ($q) => $q->where('is_active', true)->with('questions.options'),
            'outlet:id_outlet,nama_outlet',
            'trainers.user:id,name',
        ]);

        $attendance = $schedule->attendances()->where('user_id', $userId)->first();
        $materialProgress = $schedule->program->materials->map(function ($material) use ($schedule, $userId) {
            $done = $material->program_id
                ? \App\Models\JustAcademy\JaMaterialProgress::where('schedule_id', $schedule->id)
                    ->where('user_id', $userId)
                    ->where('material_id', $material->id)
                    ->exists()
                : false;

            return [
                'id' => $material->id,
                'title' => $material->title,
                'type' => $material->type,
                'file_path' => $material->file_path ? asset('storage/' . $material->file_path) : null,
                'url' => $material->url,
                'is_pre_read' => $material->is_pre_read,
                'completed' => $done,
            ];
        });

        $quizAttempts = $schedule->program->quizzes->map(function ($quiz) use ($schedule, $userId) {
            $attempt = \App\Models\JustAcademy\JaQuizAttempt::where('schedule_id', $schedule->id)
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $userId)
                ->latest('id')
                ->first();

            return [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'type' => $quiz->type,
                'pass_score' => $quiz->pass_score,
                'attempt' => $attempt ? [
                    'score' => $attempt->score,
                    'passed' => $attempt->passed,
                    'submitted_at' => $attempt->submitted_at,
                ] : null,
                'questions' => $quiz->questions,
            ];
        });

        return Inertia::render('JustAcademy/MyTraining/Show', [
            'schedule' => $schedule,
            'attendance' => $attendance,
            'materials' => $materialProgress,
            'quizzes' => $quizAttempts,
        ]);
    }

    public function completeMaterial(Request $request, JaSchedule $schedule, int $materialId)
    {
        $this->service->markMaterialComplete($schedule, (int) $request->user()->id, $materialId);

        return back()->with('success', 'Materi ditandai selesai.');
    }

    public function submitQuiz(Request $request, JaSchedule $schedule, int $quizId)
    {
        $quiz = \App\Models\JustAcademy\JaQuiz::findOrFail($quizId);
        $validated = $request->validate(['answers' => 'required|array']);

        $attempt = $this->service->submitQuiz(
            $schedule,
            $quiz,
            (int) $request->user()->id,
            $validated['answers'],
        );

        return back()->with('success', 'Quiz berhasil dikirim. Nilai: ' . $attempt->score);
    }

    public function submitFeedback(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'trainer_id' => 'nullable|integer|exists:users,id',
        ]);

        JaFeedback::updateOrCreate(
            ['schedule_id' => $schedule->id, 'user_id' => $userId],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'trainer_id' => $validated['trainer_id'] ?? null,
            ]
        );

        return back()->with('success', 'Feedback berhasil dikirim.');
    }
}
