<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaSchedule;
use App\Models\JustAcademy\JaScheduleTrainer;
use App\Models\Outlet;
use App\Models\Region;
use App\Services\JustAcademy\JustAcademyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function __construct(
        protected JustAcademyService $service,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = JaSchedule::with(['program:id,title', 'outlet:id_outlet,nama_outlet'])
            ->withCount('participants')
            ->orderByDesc('start_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('program', fn ($p) => $p->where('title', 'like', "%{$search}%"));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return Inertia::render('JustAcademy/Schedules/Index', [
            'schedules' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create()
    {
        return Inertia::render('JustAcademy/Schedules/Form', [
            'schedule' => null,
            'programs' => JaProgram::where('status', 'published')->orderBy('title')->get(['id', 'title']),
            'outlets' => Outlet::orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'regions' => Region::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateSchedule($request);

        $schedule = JaSchedule::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        if ($schedule->status === 'published') {
            $this->service->ensureQrToken($schedule);
        }

        return redirect()
            ->route('just-academy.schedules.show', $schedule->id)
            ->with('success', 'Jadwal training berhasil dibuat.');
    }

    public function show(JaSchedule $schedule)
    {
        $schedule->load([
            'program.materials',
            'program.quizzes.questions.options',
            'outlet:id_outlet,nama_outlet',
            'participants.user:id,name,email',
            'trainers.user:id,name',
            'attendances.user:id,name',
        ]);

        if ($schedule->status === 'published' && !$schedule->qr_token) {
            $this->service->ensureQrToken($schedule->fresh());
            $schedule->refresh();
        }

        return Inertia::render('JustAcademy/Schedules/Show', [
            'schedule' => $schedule,
            'qrUrl' => $schedule->qr_token
                ? url('/just-academy/check-in?token=' . $schedule->qr_token . '&schedule_id=' . $schedule->id)
                : null,
        ]);
    }

    public function edit(JaSchedule $schedule)
    {
        return Inertia::render('JustAcademy/Schedules/Form', [
            'schedule' => $schedule,
            'programs' => JaProgram::whereIn('status', ['published', 'draft'])->orderBy('title')->get(['id', 'title']),
            'outlets' => Outlet::orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'regions' => Region::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, JaSchedule $schedule)
    {
        $validated = $this->validateSchedule($request);
        $wasPublished = $schedule->status === 'published';

        $schedule->update($validated);

        if ($schedule->status === 'published' && (!$wasPublished || !$schedule->qr_token)) {
            $this->service->ensureQrToken($schedule);
        }

        return redirect()
            ->route('just-academy.schedules.show', $schedule->id)
            ->with('success', 'Jadwal training berhasil diperbarui.');
    }

    public function destroy(JaSchedule $schedule)
    {
        $schedule->delete();

        return redirect()
            ->route('just-academy.schedules.index')
            ->with('success', 'Jadwal training berhasil dihapus.');
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

        $added = $this->service->inviteParticipants(
            $schedule,
            $userIds,
            $jabatanIds,
            $outletIds,
            (int) $request->user()->id,
        );

        return back()->with('success', "{$added} peserta berhasil ditambahkan.");
    }

    public function removeParticipant(JaSchedule $schedule, int $participantId)
    {
        $schedule->participants()->where('id', $participantId)->delete();

        return back()->with('success', 'Peserta dihapus dari jadwal.');
    }

    public function assignTrainer(Request $request, JaSchedule $schedule)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|in:primary,assistant',
            'hours' => 'nullable|numeric|min:0',
        ]);

        JaScheduleTrainer::updateOrCreate(
            ['schedule_id' => $schedule->id, 'user_id' => $validated['user_id']],
            ['role' => $validated['role'], 'hours' => $validated['hours'] ?? null]
        );

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
        ]);
    }
}
