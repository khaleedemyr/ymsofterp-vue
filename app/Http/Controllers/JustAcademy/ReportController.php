<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaAttendance;
use App\Models\JustAcademy\JaQuizAttempt;
use App\Models\JustAcademy\JaSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $scheduleId = $request->input('schedule_id');
        $from = $request->input('from');
        $to = $request->input('to');

        $schedules = JaSchedule::with('program:id,title')
            ->orderByDesc('start_at')
            ->limit(100)
            ->get(['id', 'title', 'program_id', 'start_at', 'status']);

        $attendanceQuery = JaAttendance::with([
            'user:id,name,email',
            'schedule.program:id,title',
        ])->orderByDesc('check_in_at');

        if ($scheduleId) {
            $attendanceQuery->where('schedule_id', $scheduleId);
        }
        if ($from) {
            $attendanceQuery->whereDate('check_in_at', '>=', $from);
        }
        if ($to) {
            $attendanceQuery->whereDate('check_in_at', '<=', $to);
        }

        $completionQuery = JaQuizAttempt::with([
            'user:id,name',
            'quiz:id,title,type',
            'schedule.program:id,title',
        ])->whereNotNull('submitted_at')->orderByDesc('submitted_at');

        if ($scheduleId) {
            $completionQuery->where('schedule_id', $scheduleId);
        }

        return Inertia::render('JustAcademy/Reports/Index', [
            'schedules' => $schedules,
            'attendance' => $attendanceQuery->paginate(20, ['*'], 'attendance_page')->withQueryString(),
            'completions' => $completionQuery->paginate(20, ['*'], 'completion_page')->withQueryString(),
            'filters' => [
                'schedule_id' => $scheduleId,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}
