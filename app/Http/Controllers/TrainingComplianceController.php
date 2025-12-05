<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Jabatan;
use App\Models\LmsCourse;
use App\Models\JabatanRequiredTraining;
use App\Models\UserTrainingHours;
use App\Models\TrainerTeachingHours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TrainingComplianceController extends Controller
{
    public function dashboard()
    {
        // Overall statistics
        $stats = [
            'total_users' => User::where('status', 'A')->count(),
            'total_jabatans' => Jabatan::active()->count(),
            'total_courses' => LmsCourse::published()->count(),
            'total_mandatory_trainings' => JabatanRequiredTraining::where('is_mandatory', true)->count(),
            'total_training_hours' => UserTrainingHours::where('status', 'completed')->sum('hours_completed'),
            'total_teaching_hours' => TrainerTeachingHours::sum('hours_taught'),
        ];

        // Compliance by jabatan
        $complianceByJabatan = $this->getComplianceByJabatan();

        // Top trainers by hours
        $topTrainers = $this->getTopTrainers();

        // Recent training activities
        $recentActivities = $this->getRecentActivities();

        // Users with low compliance
        $lowComplianceUsers = $this->getLowComplianceUsers();

        return Inertia::render('Training/Compliance/Dashboard', [
            'stats' => $stats,
            'complianceByJabatan' => $complianceByJabatan,
            'topTrainers' => $topTrainers,
            'recentActivities' => $recentActivities,
            'lowComplianceUsers' => $lowComplianceUsers,
        ]);
    }

    public function complianceReport(Request $request)
    {
        $query = User::where('status', 'A')
            ->with(['jabatan.divisi', 'divisi']);

        // Filter by jabatan
        if ($request->jabatan_id) {
            $query->where('id_jabatan', $request->jabatan_id);
        }

        // Filter by divisi
        if ($request->division_id) {
            $query->where('division_id', $request->division_id);
        }

        // Filter by compliance status
        if ($request->compliance_status) {
            switch ($request->compliance_status) {
                case 'compliant':
                    $query->whereHas('trainingHours', function ($q) {
                        $q->where('status', 'completed');
                    });
                    break;
                case 'non_compliant':
                    $query->whereDoesntHave('trainingHours', function ($q) {
                        $q->where('status', 'completed');
                    });
                    break;
                case 'partial':
                    // Users with some but not all mandatory trainings completed
                    break;
            }
        }

        $users = $query->orderBy('nama_lengkap')
            ->paginate(20);

        // Add compliance data to each user
        $users->getCollection()->transform(function ($user) {
            $compliance = $user->getTrainingComplianceStatus();
            $user->compliance_data = $compliance;
            $user->total_training_hours = $user->getTotalTrainingHours();
            return $user;
        });

        // Get filter options
        $jabatans = Jabatan::active()
            ->with(['divisi'])
            ->orderBy('nama_jabatan')
            ->get(['id_jabatan', 'nama_jabatan', 'id_divisi']);

        $divisions = \App\Models\Divisi::active()
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);

        return Inertia::render('Training/Compliance/Report', [
            'users' => $users,
            'jabatans' => $jabatans,
            'divisions' => $divisions,
            'filters' => $request->only(['jabatan_id', 'division_id', 'compliance_status']),
        ]);
    }

    public function userCompliance($userId)
    {
        $user = User::with(['jabatan.divisi', 'divisi'])
            ->findOrFail($userId);

        $mandatoryTrainings = $user->getMandatoryTrainings();
        $optionalTrainings = $user->getOptionalTrainings();
        $completedTrainings = $user->getCompletedTrainings();
        $inProgressTrainings = $user->getInProgressTrainings();
        $complianceStatus = $user->getTrainingComplianceStatus();

        // Get training hours detail
        $trainingHours = $user->trainingHours()
            ->with(['course.category', 'course.trainers.trainer'])
            ->orderBy('last_updated', 'desc')
            ->get();

        return Inertia::render('Training/Compliance/UserDetail', [
            'user' => $user,
            'mandatoryTrainings' => $mandatoryTrainings,
            'optionalTrainings' => $optionalTrainings,
            'completedTrainings' => $completedTrainings,
            'inProgressTrainings' => $inProgressTrainings,
            'complianceStatus' => $complianceStatus,
            'trainingHours' => $trainingHours,
        ]);
    }

    public function trainerReport(Request $request)
    {
        $query = User::where('status', 'A')
            ->whereHas('courseTrainers')
            ->with(['jabatan.divisi', 'divisi']);

        // Filter by trainer
        if ($request->trainer_id) {
            $query->where('id', $request->trainer_id);
        }

        $trainers = $query->orderBy('nama_lengkap')
            ->paginate(20);

        // Add teaching data to each trainer
        $trainers->getCollection()->transform(function ($trainer) {
            $trainer->total_teaching_hours = $trainer->getTotalTeachingHours();
            $trainer->teaching_hours_this_month = TrainerTeachingHours::byTrainer($trainer->id)->thisMonth()->sum('hours_taught');
            $trainer->teaching_hours_this_year = TrainerTeachingHours::byTrainer($trainer->id)->thisYear()->sum('hours_taught');
            $trainer->courses_taught = $trainer->getTrainerCourses();
            $trainer->recent_teaching = $trainer->teachingHours()
                ->with(['course.category'])
                ->orderBy('teaching_date', 'desc')
                ->limit(5)
                ->get();
            return $trainer;
        });

        return Inertia::render('Training/Compliance/TrainerReport', [
            'trainers' => $trainers,
            'filters' => $request->only(['trainer_id']),
        ]);
    }

    public function trainerDetail($trainerId)
    {
        $trainer = User::with(['jabatan.divisi', 'divisi'])
            ->findOrFail($trainerId);

        $teachingHours = $trainer->teachingHours()
            ->with(['course.category', 'schedule'])
            ->orderBy('teaching_date', 'desc')
            ->get();

        $courses = $trainer->getTrainerCourses();

        $statistics = [
            'total_hours' => $trainer->getTotalTeachingHours(),
            'hours_this_month' => TrainerTeachingHours::byTrainer($trainerId)->thisMonth()->sum('hours_taught'),
            'hours_this_year' => TrainerTeachingHours::byTrainer($trainerId)->thisYear()->sum('hours_taught'),
            'total_sessions' => $trainer->teachingHours()->count(),
            'unique_courses' => $trainer->teachingHours()->distinct('course_id')->count(),
            'total_participants' => $trainer->teachingHours()->sum('participant_count'),
        ];

        // Monthly teaching hours for chart
        $monthlyHours = TrainerTeachingHours::byTrainer($trainerId)
            ->selectRaw('YEAR(teaching_date) as year, MONTH(teaching_date) as month, SUM(hours_taught) as hours')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return Inertia::render('Training/Compliance/TrainerDetail', [
            'trainer' => $trainer,
            'teachingHours' => $teachingHours,
            'courses' => $courses,
            'statistics' => $statistics,
            'monthlyHours' => $monthlyHours,
        ]);
    }

    public function courseReport(Request $request)
    {
        $query = LmsCourse::published()
            ->with(['category', 'trainers.trainer']);

        // Filter by course
        if ($request->course_id) {
            $query->where('id', $request->course_id);
        }

        $courses = $query->orderBy('title')
            ->paginate(20);

        // Add training data to each course
        $courses->getCollection()->transform(function ($course) {
            $course->total_users_completed = $course->getTotalUsersCompleted();
            $course->total_users_in_progress = $course->getTotalUsersInProgress();
            $course->average_completion_hours = $course->getAverageCompletionHours();
            $course->required_jabatans = $course->getRequiredJabatans();
            $course->mandatory_jabatans = $course->getMandatoryJabatans();
            return $course;
        });

        return Inertia::render('Training/Compliance/CourseReport', [
            'courses' => $courses,
            'filters' => $request->only(['course_id']),
        ]);
    }

    public function exportComplianceReport(Request $request)
    {
        // Implementation for Excel export
        // Similar to existing export functionality
    }

    private function getComplianceByJabatan()
    {
        return Jabatan::active()
            ->with(['divisi'])
            ->get()
            ->map(function ($jabatan) {
                $users = User::where('id_jabatan', $jabatan->id_jabatan)
                    ->where('status', 'A')
                    ->get();

                $totalUsers = $users->count();
                $compliantUsers = 0;

                foreach ($users as $user) {
                    $compliance = $user->getTrainingComplianceStatus();
                    if ($compliance['compliance_percentage'] == 100) {
                        $compliantUsers++;
                    }
                }

                return [
                    'jabatan' => $jabatan,
                    'total_users' => $totalUsers,
                    'compliant_users' => $compliantUsers,
                    'compliance_percentage' => $totalUsers > 0 ? round(($compliantUsers / $totalUsers) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('compliance_percentage')
            ->values();
    }

    private function getTopTrainers()
    {
        return TrainerTeachingHours::selectRaw('trainer_id, SUM(hours_taught) as total_hours, COUNT(*) as session_count')
            ->with(['trainer.jabatan', 'trainer.divisi'])
            ->groupBy('trainer_id')
            ->orderBy('total_hours', 'desc')
            ->limit(10)
            ->get();
    }

    private function getRecentActivities()
    {
        return UserTrainingHours::with(['user.jabatan', 'course.category'])
            ->where('status', 'completed')
            ->orderBy('completion_date', 'desc')
            ->limit(10)
            ->get();
    }

    private function getLowComplianceUsers()
    {
        return User::where('status', 'A')
            ->with(['jabatan.divisi'])
            ->get()
            ->map(function ($user) {
                $compliance = $user->getTrainingComplianceStatus();
                return [
                    'user' => $user,
                    'compliance_percentage' => $compliance['compliance_percentage'],
                    'missing_trainings' => count($compliance['missing_trainings']),
                ];
            })
            ->filter(function ($item) {
                return $item['compliance_percentage'] < 100;
            })
            ->sortBy('compliance_percentage')
            ->take(10)
            ->values();
    }
}
