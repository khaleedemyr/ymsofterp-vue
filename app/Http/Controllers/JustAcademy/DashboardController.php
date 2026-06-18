<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaSchedule;
use App\Services\JustAcademy\JustAcademyService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        protected JustAcademyService $service,
    ) {}

    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;
        $participantQuery = $this->service->participantSchedulesForUser($userId);

        $stats = [
            'programs_published' => JaProgram::where('status', 'published')->count(),
            'schedules_upcoming' => JaSchedule::where('end_at', '>=', now())
                ->whereIn('status', ['published', 'ongoing'])
                ->count(),
            'my_upcoming' => (clone $participantQuery)->where('end_at', '>=', now())->count(),
            'my_total' => (clone $participantQuery)->count(),
        ];

        $upcomingSchedules = JaSchedule::with(['program:id,title', 'outlet:id_outlet,nama_outlet'])
            ->where('end_at', '>=', now())
            ->whereIn('status', ['published', 'ongoing'])
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        $mySchedules = (clone $participantQuery)
            ->with(['program:id,title'])
            ->where('end_at', '>=', now())
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        $myPastSchedules = (clone $participantQuery)
            ->with(['program:id,title'])
            ->where('end_at', '<', now())
            ->orderByDesc('start_at')
            ->limit(5)
            ->get();

        return Inertia::render('JustAcademy/Dashboard', [
            'stats' => $stats,
            'upcomingSchedules' => $upcomingSchedules,
            'mySchedules' => $mySchedules,
            'myPastSchedules' => $myPastSchedules,
        ]);
    }
}
