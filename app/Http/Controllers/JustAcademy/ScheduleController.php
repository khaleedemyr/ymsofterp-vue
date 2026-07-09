<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaSchedule;
use App\Models\JustAcademy\JaScheduleTrainer;
use App\Models\Outlet;
use App\Models\Region;
use App\Services\JustAcademy\JustAcademyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function __construct(
        protected JustAcademyService $service,
    ) {}

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $status = $request->input('status');

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        $rangeStart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $rangeEnd = date('Y-m-t 23:59:59', strtotime($rangeStart));

        $query = JaSchedule::with(['program:id,title'])
            ->withCount('participants')
            ->where('start_at', '<=', $rangeEnd)
            ->where('end_at', '>=', $rangeStart)
            ->orderBy('start_at');

        if ($status) {
            $query->where('status', $status);
        }

        $schedules = $query->get();

        $calendarEvents = $schedules->map(function (JaSchedule $schedule) {
            $colors = [
                'draft' => ['#64748b', '#f8fafc'],
                'published' => ['#4f46e5', '#eef2ff'],
                'ongoing' => ['#059669', '#ecfdf5'],
                'completed' => ['#6b7280', '#f3f4f6'],
                'cancelled' => ['#dc2626', '#fef2f2'],
            ];
            [$border, $bg] = $colors[$schedule->status] ?? ['#4f46e5', '#eef2ff'];

            return [
                'id' => (string) $schedule->id,
                'title' => $schedule->title,
                'start' => $schedule->start_at?->toIso8601String(),
                'end' => $schedule->end_at?->toIso8601String(),
                'backgroundColor' => $bg,
                'borderColor' => $border,
                'textColor' => '#1e293b',
                'extendedProps' => [
                    'schedule_id' => $schedule->id,
                    'status' => $schedule->status,
                    'program' => $schedule->program?->title,
                    'location' => $schedule->location,
                    'participants_count' => $schedule->participants_count,
                    'start_label' => $schedule->start_at?->format('d M Y H:i'),
                    'end_label' => $schedule->end_at?->format('d M Y H:i'),
                ],
            ];
        })->values();

        return Inertia::render('JustAcademy/Schedules/Index', [
            'calendarEvents' => $calendarEvents,
            'year' => $year,
            'month' => $month,
            'filters' => ['status' => $status],
            'holidays' => $this->companyHolidays(),
        ]);
    }

    public function create(Request $request)
    {
        $startDate = $request->input('start');
        $initialStartAt = null;
        $initialEndAt = null;

        if ($startDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $initialStartAt = $startDate . 'T09:00';
            $initialEndAt = $startDate . 'T17:00';
        }

        return Inertia::render('JustAcademy/Schedules/Form', [
            'schedule' => null,
            'programs' => JaProgram::where('status', 'published')->orderBy('title')->get(['id', 'title']),
            'outlets' => Outlet::orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'regions' => Region::orderBy('name')->get(['id', 'name']),
            'jabatanList' => Jabatan::orderBy('nama_jabatan')->get(['id_jabatan', 'nama_jabatan']),
            'divisions' => Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']),
            'initialStartAt' => $initialStartAt,
            'initialEndAt' => $initialEndAt,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSchedule($request);

        $schedule = JaSchedule::create([
            ...$this->schedulePayload($validated),
            'created_by' => $request->user()->id,
        ]);

        $newParticipantIds = $this->service->syncParticipants(
            $schedule,
            $validated['participant_ids'] ?? [],
            (int) $request->user()->id,
        );
        $newTrainerIds = $this->service->syncTrainers(
            $schedule,
            $validated['internal_trainer_ids'] ?? [],
            $validated['external_trainers'] ?? [],
        );

        if ($schedule->status === 'published') {
            $this->service->ensureQrToken($schedule);
        }

        $this->service->notifyScheduleAssignments(
            $schedule,
            $newParticipantIds,
            $newTrainerIds,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('just-academy.schedules.index', [
                'year' => $schedule->start_at->year,
                'month' => $schedule->start_at->month,
            ])
            ->with('success', 'Training plan berhasil dibuat.');
    }

    public function show(JaSchedule $schedule)
    {
        $schedule->load([
            'program:id,title',
            'program.items.material',
            'program.items.quiz.questions.options',
            'outlet:id_outlet,nama_outlet',
            'participants.user:id,nama_lengkap,email',
            'trainers.user:id,nama_lengkap,email',
            'attendances.user:id,nama_lengkap,email',
        ]);

        if ($schedule->status === 'published' && !$schedule->qr_token) {
            $this->service->ensureQrToken($schedule->fresh());
            $schedule->refresh();
        }

        $curriculum = $schedule->program
            ? $this->service->buildScheduleCurriculumOverview($schedule->program)
            : collect();

        return Inertia::render('JustAcademy/Schedules/Show', [
            'schedule' => $schedule,
            'curriculum' => $curriculum,
            'qrUrl' => $schedule->qr_token
                ? url('/just-academy/check-in?token=' . $schedule->qr_token . '&schedule_id=' . $schedule->id)
                : null,
            'jabatanList' => Jabatan::orderBy('nama_jabatan')->get(['id_jabatan', 'nama_jabatan']),
            'divisions' => Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']),
            'outlets' => Outlet::orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function edit(JaSchedule $schedule)
    {
        $schedule->load([
            'participants.user:id,nama_lengkap,email',
            'participants.user.jabatan:id_jabatan,nama_jabatan',
            'participants.user.divisi:id,nama_divisi',
            'participants.user.outlet:id_outlet,nama_outlet',
            'trainers.user:id,nama_lengkap,email',
            'trainers.user.jabatan:id_jabatan,nama_jabatan',
            'trainers.user.divisi:id,nama_divisi',
            'trainers.user.outlet:id_outlet,nama_outlet',
        ]);

        return Inertia::render('JustAcademy/Schedules/Form', [
            'schedule' => $schedule,
            'programs' => JaProgram::whereIn('status', ['published', 'draft'])->orderBy('title')->get(['id', 'title']),
            'outlets' => Outlet::orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'regions' => Region::orderBy('name')->get(['id', 'name']),
            'jabatanList' => Jabatan::orderBy('nama_jabatan')->get(['id_jabatan', 'nama_jabatan']),
            'divisions' => Divisi::active()->orderBy('nama_divisi')->get(['id', 'nama_divisi']),
        ]);
    }

    public function update(Request $request, JaSchedule $schedule)
    {
        $validated = $this->validateSchedule($request);
        $wasPublished = $schedule->status === 'published';
        $wasNotifiable = $this->service->shouldNotifyForStatus($schedule->status);
        $oldStartAt = $schedule->start_at?->copy();
        $oldEndAt = $schedule->end_at?->copy();
        $oldLocation = $schedule->location;

        $schedule->update($this->schedulePayload($validated));

        $newParticipantIds = $this->service->syncParticipants(
            $schedule,
            $validated['participant_ids'] ?? [],
            (int) $request->user()->id,
        );
        $newTrainerIds = $this->service->syncTrainers(
            $schedule,
            $validated['internal_trainer_ids'] ?? [],
            $validated['external_trainers'] ?? [],
        );

        if ($schedule->status === 'published' && (!$wasPublished || !$schedule->qr_token)) {
            $this->service->ensureQrToken($schedule);
        }

        $actorId = (int) $request->user()->id;
        if ($this->service->shouldNotifyForStatus($schedule->status)) {
            $rescheduled = !$oldStartAt?->eq($schedule->start_at)
                || !$oldEndAt?->eq($schedule->end_at)
                || $oldLocation !== $schedule->location;

            if (!$wasNotifiable) {
                $this->service->notifyScheduleAssignments(
                    $schedule,
                    $schedule->participants()->pluck('user_id'),
                    $schedule->trainers()
                        ->where('trainer_type', 'internal')
                        ->whereNotNull('user_id')
                        ->pluck('user_id'),
                    $actorId,
                );
            } elseif ($rescheduled) {
                $this->service->notifyAllAssigneesRescheduled($schedule, $actorId);
            } else {
                $this->service->notifyScheduleAssignments(
                    $schedule,
                    $newParticipantIds,
                    $newTrainerIds,
                    $actorId,
                );
            }
        }

        return redirect()
            ->route('just-academy.schedules.index', [
                'year' => $schedule->start_at->year,
                'month' => $schedule->start_at->month,
            ])
            ->with('success', 'Training plan berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $schedule = JaSchedule::findOrFail($id);
        $year = $schedule->start_at->year;
        $month = $schedule->start_at->month;

        $schedule->delete();

        return redirect()
            ->route('just-academy.schedules.index', [
                'year' => $year,
                'month' => $month,
            ])
            ->with('success', 'Training plan berhasil dihapus.');
    }

    public function invite(Request $request, JaSchedule $schedule)
    {
        $validated = $request->validate([
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'jabatan_ids' => 'nullable|array',
            'jabatan_ids.*' => 'integer',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'integer',
        ]);

        $userIds = $validated['user_ids'] ?? [];
        $jabatanIds = $validated['jabatan_ids'] ?? [];
        $outletIds = $validated['outlet_ids'] ?? [];

        if ($userIds === [] && $jabatanIds === [] && $outletIds === []) {
            return back()->withErrors(['invite' => 'Pilih minimal satu peserta, jabatan, atau outlet.']);
        }

        $result = $this->service->inviteParticipants(
            $schedule,
            $userIds,
            $jabatanIds,
            $outletIds,
            (int) $request->user()->id,
        );

        if ($result['user_ids']->isNotEmpty()) {
            $this->service->notifyScheduleAssignments(
                $schedule->fresh(),
                $result['user_ids'],
                collect(),
                (int) $request->user()->id,
            );
        }

        return back()->with('success', "{$result['added']} peserta berhasil ditambahkan.");
    }

    public function removeParticipant(JaSchedule $schedule, int $participantId)
    {
        $schedule->participants()->where('id', $participantId)->delete();

        return back()->with('success', 'Peserta dihapus dari jadwal.');
    }

    public function assignTrainer(Request $request, JaSchedule $schedule)
    {
        $validated = $request->validate([
            'trainer_type' => 'required|in:internal,external',
            'user_id' => 'required_if:trainer_type,internal|nullable|integer|exists:users,id',
            'external_name' => 'required_if:trainer_type,external|nullable|string|max:255',
            'role' => 'required|in:primary,assistant',
            'hours' => 'nullable|numeric|min:0',
        ]);

        $isNewInternalTrainer = false;
        if ($validated['trainer_type'] === 'internal') {
            $trainer = JaScheduleTrainer::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'trainer_type' => 'internal',
                    'user_id' => $validated['user_id'],
                ],
                [
                    'external_name' => null,
                    'role' => $validated['role'],
                    'hours' => $validated['hours'] ?? null,
                ]
            );
            $isNewInternalTrainer = $trainer->wasRecentlyCreated;
        } else {
            JaScheduleTrainer::firstOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'trainer_type' => 'external',
                    'external_name' => trim($validated['external_name']),
                ],
                [
                    'user_id' => null,
                    'role' => $validated['role'],
                    'hours' => $validated['hours'] ?? null,
                ]
            );
        }

        if ($isNewInternalTrainer) {
            $this->service->notifyScheduleAssignments(
                $schedule,
                collect(),
                [(int) $validated['user_id']],
                (int) $request->user()->id,
            );
        }

        return back()->with('success', 'Trainer berhasil ditambahkan.');
    }

    public function markAttendance(Request $request, JaSchedule $schedule)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->service->checkIn(
            $schedule,
            (int) $validated['user_id'],
            null,
            'manual',
            (int) $request->user()->id,
        );

        return back()->with('success', 'Absensi manual berhasil dicatat.');
    }

    public function homeSchedules(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->homeSchedulesForUser((int) $request->user()->id),
        ]);
    }

    public function checkInPage(Request $request)
    {
        $scheduleId = $request->integer('schedule_id');
        $token = $request->string('token')->toString();

        $schedule = JaSchedule::with('program:id,title')->findOrFail($scheduleId);

        try {
            $this->service->checkIn($schedule, (int) $request->user()->id, $token, 'qr');
            $message = 'Check-in berhasil.';
            $success = true;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $success = false;
        }

        return Inertia::render('JustAcademy/CheckIn', [
            'schedule' => $schedule,
            'success' => $success,
            'message' => $message,
        ]);
    }

    protected function validateSchedule(Request $request): array
    {
        return $request->validate([
            'program_id' => 'required|integer|exists:ja_programs,id',
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'location' => 'nullable|string|max:255',
            'outlet_id' => 'nullable|integer',
            'region_id' => 'nullable|integer',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published,ongoing,completed,cancelled',
            'notes' => 'nullable|string',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'integer|exists:users,id',
            'internal_trainer_ids' => 'nullable|array',
            'internal_trainer_ids.*' => 'integer|exists:users,id',
            'external_trainers' => 'nullable|array',
            'external_trainers.*' => 'string|max:255',
        ]);
    }

    protected function schedulePayload(array $validated): array
    {
        return collect($validated)->only([
            'program_id',
            'title',
            'start_at',
            'end_at',
            'location',
            'outlet_id',
            'region_id',
            'capacity',
            'status',
            'notes',
        ])->all();
    }

    protected function companyHolidays()
    {
        return DB::table('tbl_kalender_perusahaan')
            ->select('id', 'tgl_libur', 'keterangan')
            ->orderBy('tgl_libur')
            ->get()
            ->map(fn ($holiday) => [
                'id' => $holiday->id,
                'tgl_libur' => substr((string) $holiday->tgl_libur, 0, 10),
                'keterangan' => $holiday->keterangan,
            ])
            ->values();
    }
}
