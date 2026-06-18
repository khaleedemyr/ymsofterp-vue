<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaSchedule;
use App\Models\JustAcademy\JaScheduleParticipant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $stats = [
            'programs_published' => JaProgram::where('status', 'published')->count(),
            'schedules_upcoming' => JaSchedule::where('start_at', '>=', now())
                ->whereIn('status', ['published', 'ongoing'])
                ->count(),
            'my_upcoming' => JaScheduleParticipant::where('user_id', $userId)
                ->whereHas('schedule', fn ($q) => $q->where('start_at', '>=', now()))
                ->count(),
        ];

        $upcomingSchedules = JaSchedule::with(['program:id,title', 'outlet:id_outlet,nama_outlet'])
            ->where('start_at', '>=', now())
            ->whereIn('status', ['published', 'ongoing'])
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        $mySchedules = JaSchedule::with(['program:id,title'])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->where('start_at', '>=', now()->subDay())
            ->orderBy('start_at')
            ->limit(8)
            ->get();

        return Inertia::render('JustAcademy/Dashboard', [
            'stats' => $stats,
            'upcomingSchedules' => $upcomingSchedules,
            'mySchedules' => $mySchedules,
        ]);
    }
}
