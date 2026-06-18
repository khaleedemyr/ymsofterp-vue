<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaSchedule;
use App\Services\RoleMenuService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;
        $allowedMenus = RoleMenuService::allowedMenuCodesForInternalUser($userId);
        $canManageSchedules = in_array('just_academy_schedules', $allowedMenus, true);

        $publishedSchedules = JaSchedule::query()
            ->whereIn('status', ['published', 'ongoing']);

        $stats = [
            'programs_published' => JaProgram::where('status', 'published')->count(),
            'schedules_upcoming' => (clone $publishedSchedules)
                ->where('end_at', '>=', now())
                ->count(),
            'schedules_ongoing' => JaSchedule::where('status', 'ongoing')->count(),
        ];

        $upcomingSchedules = JaSchedule::with(['program:id,title', 'outlet:id_outlet,nama_outlet'])
            ->withCount('participants')
            ->whereIn('status', ['published', 'ongoing'])
            ->where('end_at', '>=', now())
            ->orderBy('start_at')
            ->limit(12)
            ->get();

        return Inertia::render('JustAcademy/Dashboard', [
            'stats' => $stats,
            'canManageSchedules' => $canManageSchedules,
            'upcomingSchedules' => $upcomingSchedules,
        ]);
    }
}
