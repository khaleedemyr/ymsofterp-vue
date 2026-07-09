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
        $userId = (int) $request->user()->id;
        $tab = $request->input('tab', 'upcoming');

        $participantQuery = $this->service->participantSchedulesForUser($userId);

        $stats = [
            'upcoming' => (clone $participantQuery)->where('end_at', '>=', now())->count(),
            'past' => (clone $participantQuery)->where('end_at', '<', now())->count(),
            'total' => (clone $participantQuery)->count(),
        ];

        $query = (clone $participantQuery)
            ->with([
                'program:id,title',
                'outlet:id_outlet,nama_outlet',
                'trainers.user:id,nama_lengkap',
            ]);

        if ($tab === 'past') {
            $query->where('end_at', '<', now())->orderByDesc('start_at');
        } else {
            $query->where('end_at', '>=', now())->orderBy('start_at');
        }

        $schedules = $this->service->enrichParticipantScheduleListing(
            $query->paginate(12)->withQueryString(),
            $userId,
        );

        return Inertia::render('JustAcademy/MyTraining/Index', [
            'schedules' => $schedules,
            'tab' => $tab,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $schedule->load([
            'program:id,title',
            'outlet:id_outlet,nama_outlet',
            'trainers.user:id,nama_lengkap,email',
        ]);

        $attendance = $schedule->attendances()->where('user_id', $userId)->first();
        $trainingStarted = $this->service->trainingHasStarted($schedule);
        $checkedIn = $this->service->hasCheckedIn($schedule, $userId);
        $curriculum = $this->service->buildParticipantCurriculum($schedule, $userId, $trainingStarted);
        $feedback = JaFeedback::where('schedule_id', $schedule->id)
            ->where('user_id', $userId)
            ->first();

        return Inertia::render('JustAcademy/MyTraining/Show', [
            'schedule' => $schedule,
            'attendance' => $attendance,
            'curriculum' => $curriculum,
            'trainingStarted' => $trainingStarted,
            'trainingStartsAt' => $schedule->start_at?->toIso8601String(),
            'checkedIn' => $checkedIn,
            'feedback' => $feedback,
        ]);
    }

    public function checkIn(Request $request, JaSchedule $schedule)
    {
        $validated = $request->validate([
            'qr_payload' => 'required|string|max:2000',
        ]);

        $token = $this->service->parseCheckInToken($validated['qr_payload'], $schedule->id);
        $this->service->checkIn($schedule, (int) $request->user()->id, $token, 'qr');

        return redirect()
            ->route('just-academy.my-training.show', $schedule)
            ->with('success', 'Check-in berhasil. Pilih materi atau quiz dari daftar.');
    }

    public function startQuiz(Request $request, JaSchedule $schedule, int $quizId)
    {
        $quiz = JaQuiz::with(['questions.options'])->findOrFail($quizId);
        $payload = $this->service->buildQuizTakingPayload($schedule, $quiz, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'quiz' => $payload,
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

    public function syncQuizProgress(Request $request, JaSchedule $schedule, int $quizId)
    {
        $quiz = JaQuiz::findOrFail($quizId);
        $validated = $request->validate(['current_index' => 'required|integer|min:0']);

        $this->service->syncQuizProgress(
            $schedule,
            $quiz,
            (int) $request->user()->id,
            (int) $validated['current_index'],
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function submitFeedback(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);
        $this->service->ensureCheckedIn($schedule, $userId);
        $this->service->ensureTrainingStarted($schedule);

        $request->merge([
            'trainer_id' => $request->filled('trainer_id') ? $request->input('trainer_id') : null,
            'rating' => $request->input('rating'),
        ]);

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:5000',
            'trainer_id' => 'nullable|integer|exists:users,id',
        ]);

        $feedback = JaFeedback::updateOrCreate(
            ['schedule_id' => $schedule->id, 'user_id' => $userId],
            [
                'rating' => (int) $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'trainer_id' => $validated['trainer_id'] ?? null,
            ]
        );

        return back()->with('success', 'Feedback berhasil dikirim.');
    }
}
