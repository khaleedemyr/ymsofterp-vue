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

    public function mySchedules(Request $request)
    {
        $userId = (int) $request->user()->id;
        $tab = $request->input('tab', 'upcoming');

        $query = JaSchedule::with(['program:id,title', 'outlet:id_outlet,nama_outlet'])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('start_at');

        if ($tab === 'past') {
            $query->where('end_at', '<', now());
        } else {
            $query->where('end_at', '>=', now()->subDay());
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(15),
        ]);
    }

    public function scheduleDetail(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $schedule->load([
            'program:id,title',
            'outlet:id_outlet,nama_outlet',
            'trainers.user:id,name',
        ]);

        $attendance = $schedule->attendances()->where('user_id', $userId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'schedule' => $schedule,
                'attendance' => $attendance,
            ],
        ]);
    }

    public function materials(Request $request, JaSchedule $schedule)
    {
        $userId = (int) $request->user()->id;
        $this->service->ensureParticipant($schedule, $userId);

        $curriculum = $this->service->buildParticipantCurriculum($schedule, $userId);

        return response()->json(['success' => true, 'data' => $curriculum]);
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

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:ja_schedules,id',
            'qr_token' => 'required|string',
        ]);

        $schedule = JaSchedule::findOrFail($validated['schedule_id']);
        $attendance = $this->service->checkIn(
            $schedule,
            (int) $request->user()->id,
            $validated['qr_token'],
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
