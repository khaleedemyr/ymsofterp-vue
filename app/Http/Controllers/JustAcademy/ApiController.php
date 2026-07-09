<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaFeedback;
use App\Models\JustAcademy\JaQuiz;
use App\Models\JustAcademy\JaSchedule;
use App\Models\JustAcademy\JaScheduleParticipant;
use App\Services\JustAcademy\JustAcademyService;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function __construct(
        protected JustAcademyService $service,
    ) {}

    public function dashboard(Request $request)
    {
        $userId = (int) $request->user()->id;

        $upcoming = JaSchedule::with(['program:id,title'])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->where('end_at', '>=', now())
            ->orderBy('start_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'upcoming_count' => JaScheduleParticipant::where('user_id', $userId)
                    ->whereHas('schedule', fn ($q) => $q->where('start_at', '>=', now()))
                    ->count(),
                'upcoming_schedules' => $upcoming,
            ],
        ]);
    }

    public function homeSchedules(Request $request)
    {
        $userId = (int) $request->user()->id;

        return response()->json([
            'success' => true,
            'data' => $this->service->homeSchedulesForUser($userId, 5),
        ]);
    }

    public function mySchedules(Request $request)
    {
        $userId = (int) $request->user()->id;
        $tab = $request->input('tab', 'upcoming');

        $query = $this->service->participantSchedulesForUser($userId)
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

        $paginator = $this->service->enrichParticipantScheduleListing(
            $query->paginate(15),
            $userId,
        );

        return response()->json([
            'success' => true,
            'data' => $paginator,
        ]);
    }

    public function scheduleDetail(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $schedule->load([
            'program:id,title',
            'outlet:id_outlet,nama_outlet',
            'trainers.user:id,nama_lengkap,email',
        ]);

        $attendance = $schedule->attendances()->where('user_id', $userId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => $schedule,
                'attendance' => $attendance,
                'checked_in' => $this->service->hasCheckedIn($schedule, $userId),
            ],
        ]);
    }

    public function materials(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $attendance = $schedule->attendances()->where('user_id', $userId)->first();
        $checkedIn = $this->service->hasCheckedIn($schedule, $userId);
        $trainingStarted = $this->service->trainingHasStarted($schedule);
        $curriculum = $checkedIn
            ? $this->service->buildParticipantCurriculum($schedule, $userId, $trainingStarted)
            : collect();

        return response()->json([
            'success' => true,
            'data' => $curriculum,
            'meta' => [
                'checked_in' => $checkedIn,
                'training_started' => $trainingStarted,
                'training_starts_at' => $schedule->start_at?->toIso8601String(),
            ],
        ]);
    }

    public function completeMaterial(Request $request, JaSchedule $schedule, int $materialId)
    {
        $progress = $this->service->markMaterialComplete(
            $schedule,
            (int) $request->user()->id,
            $materialId,
        );

        return response()->json(['success' => true, 'data' => $progress]);
    }

    public function startQuiz(Request $request, JaSchedule $schedule, JaQuiz $quiz)
    {
        $quiz->load(['questions.options']);
        $payload = $this->service->buildQuizTakingPayload($schedule, $quiz, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => $payload]);
    }

    public function submitQuiz(Request $request, JaSchedule $schedule, JaQuiz $quiz)
    {
        $validated = $request->validate(['answers' => 'required|array']);
        $attempt = $this->service->submitQuiz(
            $schedule,
            $quiz,
            (int) $request->user()->id,
            $validated['answers'],
        );

        return response()->json(['success' => true, 'data' => $attempt]);
    }

    public function syncQuizProgress(Request $request, JaSchedule $schedule, JaQuiz $quiz)
    {
        $validated = $request->validate(['current_index' => 'required|integer|min:0']);

        $this->service->syncQuizProgress(
            $schedule,
            $quiz,
            (int) $request->user()->id,
            (int) $validated['current_index'],
        );

        return response()->json(['success' => true]);
    }

    public function getFeedback(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $feedback = JaFeedback::where('schedule_id', $schedule->id)
            ->where('user_id', $userId)
            ->first();

        return response()->json(['success' => true, 'data' => $feedback]);
    }

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:ja_schedules,id',
            'qr_token' => 'required|string',
        ]);

        $schedule = JaSchedule::findOrFail($validated['schedule_id']);
        $token = $this->service->parseCheckInToken($validated['qr_token'], $schedule->id);
        $attendance = $this->service->checkIn(
            $schedule,
            (int) $request->user()->id,
            $token,
            'qr',
        );

        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:ja_schedules,id',
        ]);

        $schedule = JaSchedule::findOrFail($validated['schedule_id']);
        $attendance = $this->service->checkOut($schedule, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function feedback(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);
        $this->service->ensureCheckedIn($schedule, $userId);
        $this->service->ensureTrainingStarted($schedule);

        $request->merge([
            'trainer_id' => $request->filled('trainer_id') ? $request->input('trainer_id') : null,
        ]);

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'trainer_id' => 'nullable|integer|exists:users,id',
        ]);

        $feedback = JaFeedback::updateOrCreate(
            ['schedule_id' => $schedule->id, 'user_id' => $userId],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'trainer_id' => $validated['trainer_id'] ?? null,
            ]
        );

        return response()->json(['success' => true, 'data' => $feedback]);
    }

    public function myAttendance(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $attendance = $schedule->attendances()->where('user_id', $userId)->first();

        return response()->json(['success' => true, 'data' => $attendance]);
    }
}
