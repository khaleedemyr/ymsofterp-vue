<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaFeedback;
use App\Models\JustAcademy\JaQuiz;
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
            'program:id,title',
            'outlet:id_outlet,nama_outlet',
            'trainers.user:id,name',
        ]);

        $attendance = $schedule->attendances()->where('user_id', $userId)->first();
        $curriculum = $this->service->buildParticipantCurriculum($schedule, $userId);

        return Inertia::render('JustAcademy/MyTraining/Show', [
            'schedule' => $schedule,
            'attendance' => $attendance,
            'curriculum' => $curriculum,
        ]);
    }

    public function completeMaterial(Request $request, JaSchedule $schedule, int $materialId)
    {
        $this->service->markMaterialComplete($schedule, (int) $request->user()->id, $materialId);

        return back()->with('success', 'Materi ditandai selesai.');
    }

    public function submitQuiz(Request $request, JaSchedule $schedule, int $quizId)
    {
        $quiz = JaQuiz::findOrFail($quizId);
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
