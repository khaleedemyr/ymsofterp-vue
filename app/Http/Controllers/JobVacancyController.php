<?php

namespace App\Http\Controllers;

use App\Models\JobVacancy;
use App\Models\JobVacancyApplication;
use App\Models\JobVacancyRecruitmentConfig;
use App\Models\User;
use App\Support\ApplicantRecruitment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JobVacancyController extends Controller
{
    public function index(Request $request)
    {
        $query = JobVacancy::query()
            ->with(['recruitmentConfig', 'pics:id,nama_lengkap,email'])
            ->withCount('applications');

        if ($request->has('search') && $request->search) {
            $q = '%'.$request->search.'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('position', 'like', $q)
                    ->orWhere('location', 'like', $q)
                    ->orWhere('description', 'like', $q);
            });
        }
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }
        if ($request->has('job_scope') && $request->job_scope !== '') {
            $query->where('job_scope', $request->job_scope);
        }
        $jobs = $query->orderByDesc('created_at')->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($jobs);
        }

        return \Inertia\Inertia::render('Admin/JobVacancy/Index', [
            'vacancies' => $jobs,
            'filters' => [
                'search' => $request->search ?? '',
                'is_active' => $request->is_active ?? '',
                'job_scope' => $request->job_scope ?? '',
            ],
        ]);
    }

    public function publicList(Request $request)
    {
        $jobs = JobVacancy::query()
            ->where(function ($q) {
                $q->where('is_active', 1)
                    ->orWhere('is_active', '1')
                    ->orWhere('is_active', true)
                    ->orWhereRaw("LOWER(CAST(is_active AS CHAR)) IN ('active','aktif','true','yes','y')");
            })
            ->where(function ($q) {
                $q->whereNull('closing_date')
                    ->orWhereDate('closing_date', '>=', now()->toDateString());
            })
            ->when($request->scope, function ($query, $scope) {
                if (in_array($scope, ['outlet', 'head_office'], true)) {
                    $query->where('job_scope', $scope);
                }
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($jobs);
    }

    public function store(Request $request)
    {
        $data = $this->validateVacancy($request);
        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('job_banners', 'public');
        }

        $job = DB::transaction(function () use ($data) {
            $job = JobVacancy::create($data);
            $this->syncVacancyRecruitment($job, $data);

            return $job;
        });

        return response()->json($job->load(['recruitmentConfig', 'pics:id,nama_lengkap,email']));
    }

    public function update(Request $request, $id)
    {
        $job = JobVacancy::findOrFail($id);
        $data = $this->validateVacancy($request);
        if ($request->hasFile('banner')) {
            if ($job->banner) {
                Storage::disk('public')->delete($job->banner);
            }
            $data['banner'] = $request->file('banner')->store('job_banners', 'public');
        }

        DB::transaction(function () use ($job, $data) {
            $job->update($data);
            $this->syncVacancyRecruitment($job, $data);
        });

        return response()->json($job->fresh()->load(['recruitmentConfig', 'pics:id,nama_lengkap,email']));
    }

    public function destroy(Request $request, $id)
    {
        $job = JobVacancy::findOrFail($id);
        if ($job->banner) {
            Storage::disk('public')->delete($job->banner);
        }
        $job->delete();

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('success', 'Lowongan pekerjaan berhasil dihapus.');
        }

        return response()->json(['success' => true]);
    }

    public function setActive(Request $request, $id)
    {
        $job = JobVacancy::findOrFail($id);
        $job->is_active = $request->input('is_active', 1);
        $job->save();

        $message = $job->is_active
            ? 'Lowongan berhasil diaktifkan.'
            : 'Lowongan berhasil dinonaktifkan.';

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('success', $message);
        }

        return response()->json($job);
    }

    public function show($id)
    {
        $job = JobVacancy::with(['recruitmentConfig', 'pics:id,nama_lengkap,email'])
            ->withCount('applications')
            ->findOrFail($id);

        return response()->json($job);
    }

    public function searchUsers(Request $request)
    {
        $search = $request->get('q', $request->get('search', ''));

        $users = User::query()
            ->select('id', 'nama_lengkap', 'email')
            ->where('status', 'A')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_lengkap')
            ->limit(20)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'email' => $user->email,
                'label' => trim($user->nama_lengkap.' ('.$user->email.')'),
            ]);

        return response()->json($users);
    }

    public function applyPublic(Request $request, $id)
    {
        $job = JobVacancy::findOrFail($id);

        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'domicile' => 'required|string|max:255',
            'last_education' => 'required|string|max:255',
            'birth_date' => 'required|date|before:today',
            'cover_letter' => 'nullable|string',
            'cv_file' => 'required|file|mimes:pdf,doc,docx|max:5120',
            'photo_file' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $cvPath = null;
        if ($request->hasFile('cv_file')) {
            $file = $request->file('cv_file');
            $fileName = time().'_cv_'.$job->id.'_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $cvPath = $file->storeAs('job_applications/cv', $fileName, 'public');
        }

        $photoPath = null;
        if ($request->hasFile('photo_file')) {
            $file = $request->file('photo_file');
            $fileName = time().'_photo_'.$job->id.'_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $photoPath = $file->storeAs('job_applications/photos', $fileName, 'public');
        }

        JobVacancyApplication::create([
            'job_vacancy_id' => $job->id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'domicile' => $data['domicile'],
            'last_education' => $data['last_education'],
            'birth_date' => $data['birth_date'],
            'cover_letter' => $data['cover_letter'] ?? null,
            'cv_file' => $cvPath,
            'photo_file' => $photoPath,
            'status' => 'submitted',
            'screening_status' => ApplicantRecruitment::PENDING,
            'hr_interview_status' => ApplicantRecruitment::PENDING,
            'user_interview_status' => ApplicantRecruitment::PENDING,
            'loi_status' => ApplicantRecruitment::PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lamaran berhasil dikirim.',
        ]);
    }

    public function applicationsIndex(Request $request)
    {
        $query = JobVacancyApplication::with([
            'jobVacancy:id,position,location,job_scope',
        ]);

        if ($request->filled('search')) {
            $q = '%'.$request->search.'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', $q)
                    ->orWhere('email', 'like', $q)
                    ->orWhere('phone', 'like', $q)
                    ->orWhere('domicile', 'like', $q)
                    ->orWhere('last_education', 'like', $q)
                    ->orWhere('cover_letter', 'like', $q)
                    ->orWhereHas('jobVacancy', function ($job) use ($q) {
                        $job->where('position', 'like', $q)
                            ->orWhere('location', 'like', $q);
                    });
            });
        }

        if ($request->filled('scope') && in_array($request->scope, ['outlet', 'head_office'], true)) {
            $query->whereHas('jobVacancy', function ($job) use ($request) {
                $job->where('job_scope', $request->scope);
            });
        }

        if ($request->filled('job_vacancy_id')) {
            $query->where('job_vacancy_id', $request->job_vacancy_id);
        }

        $applications = $query->orderByDesc('created_at')->paginate(20);

        $applications->through(function (JobVacancyApplication $app) {
            $app->photo_url = $app->photo_file ? Storage::url($app->photo_file) : null;
            $app->cv_url = $app->cv_file ? Storage::url($app->cv_file) : null;

            return $app;
        });

        return \Inertia\Inertia::render('Admin/JobVacancy/Applications', [
            'applications' => $applications,
            'filters' => [
                'search' => $request->search ?? '',
                'scope' => $request->scope ?? '',
                'job_vacancy_id' => $request->job_vacancy_id ?? '',
            ],
            'stepOptions' => ApplicantRecruitment::STEP_OPTIONS,
            'stepLabels' => ApplicantRecruitment::STEP_LABELS,
        ]);
    }

    public function applicationUpdateProgress(Request $request, $id)
    {
        $data = $request->validate([
            'screening_status' => 'nullable|in:'.implode(',', ApplicantRecruitment::STEP_OPTIONS),
            'hr_interview_status' => 'nullable|in:'.implode(',', ApplicantRecruitment::STEP_OPTIONS),
            'user_interview_status' => 'nullable|in:'.implode(',', ApplicantRecruitment::STEP_OPTIONS),
            'loi_status' => 'nullable|in:'.implode(',', ApplicantRecruitment::STEP_OPTIONS),
            'stage_notes' => 'nullable|string',
            'joined_at' => 'nullable|date',
        ]);

        $application = JobVacancyApplication::findOrFail($id);

        foreach (['screening_status', 'hr_interview_status', 'user_interview_status', 'loi_status', 'stage_notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $application->{$field} = $data[$field];
            }
        }

        if (array_key_exists('joined_at', $data)) {
            $application->joined_at = $data['joined_at'];
        }

        ApplicantRecruitment::syncLegacyStatus($application);
        $application->save();

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('success', 'Progress pelamar berhasil diperbarui.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Progress pelamar berhasil diperbarui.',
            'application' => $application,
        ]);
    }

    public function recruitmentDashboard(Request $request)
    {
        $query = JobVacancy::query()
            ->with(['recruitmentConfig', 'pics:id,nama_lengkap,email'])
            ->withCount('applications');

        if ($request->filled('scope') && in_array($request->scope, ['outlet', 'head_office'], true)) {
            $query->where('job_scope', $request->scope);
        }

        if ($request->filled('date_from')) {
            $query->whereHas('recruitmentConfig', function ($q) use ($request) {
                $q->whereDate('search_start_date', '>=', $request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('recruitmentConfig', function ($q) use ($request) {
                $q->whereDate('search_start_date', '<=', $request->date_to);
            });
        }

        if ($request->filled('search')) {
            $q = '%'.$request->search.'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('position', 'like', $q)
                    ->orWhere('location', 'like', $q)
                    ->orWhereHas('pics', function ($pic) use ($q) {
                        $pic->where('nama_lengkap', 'like', $q)
                            ->orWhere('email', 'like', $q);
                    })
                    ->orWhereHas('recruitmentConfig', function ($cfg) use ($q) {
                        $cfg->where('final_notes', 'like', $q);
                    });
            });
        }

        $vacancies = $query->orderByDesc('created_at')->get();

        $vacancies->each(function (JobVacancy $vacancy) {
            if (! $vacancy->recruitmentConfig) {
                $vacancy->recruitmentConfig()->create([]);
                $vacancy->load('recruitmentConfig');
            }

            $vacancy->stage_counts = ApplicantRecruitment::aggregateCounts($vacancy->id);
            $vacancy->join_date = JobVacancyApplication::query()
                ->where('job_vacancy_id', $vacancy->id)
                ->whereNotNull('joined_at')
                ->orderByDesc('joined_at')
                ->value('joined_at');
        });

        $grouped = [
            'head_office' => $vacancies->where('job_scope', 'head_office')->values(),
            'outlet' => $vacancies->where('job_scope', 'outlet')->values(),
        ];

        return \Inertia\Inertia::render('Admin/JobVacancy/RecruitmentDashboard', [
            'grouped' => $grouped,
            'filters' => [
                'scope' => $request->scope ?? '',
                'search' => $request->search ?? '',
                'date_from' => $request->date_from ?? '',
                'date_to' => $request->date_to ?? '',
            ],
        ]);
    }

    public function updateRecruitmentConfig(Request $request, $id)
    {
        $job = JobVacancy::findOrFail($id);

        $data = $request->validate([
            'hr_interview_notes' => 'nullable|string',
            'user_interview_notes' => 'nullable|string',
            'final_notes' => 'nullable|string',
        ]);

        $config = $job->recruitmentConfig()->firstOrCreate([]);
        $config->fill($data);
        $config->save();

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('success', 'Keterangan rekrutmen berhasil disimpan.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Keterangan rekrutmen berhasil disimpan.',
            'config' => $config->fresh(),
        ]);
    }

    private function validateVacancy(Request $request): array
    {
        $data = $request->validate([
            'position' => 'required',
            'description' => 'required',
            'requirements' => 'nullable',
            'location' => 'required',
            'job_scope' => 'required|in:outlet,head_office',
            'closing_date' => 'required|date',
            'is_active' => 'required|boolean',
            'banner' => 'nullable|image|max:2048',
            'headcount_needed' => 'nullable|integer|min:0|max:999',
            'is_hold' => 'nullable|boolean',
            'search_start_date' => 'nullable|date',
            'target_fulfill_date' => 'nullable|date',
            'pic_user_ids' => 'nullable|array',
            'pic_user_ids.*' => 'integer|exists:users,id',
            'hr_interview_notes' => 'nullable|string',
            'user_interview_notes' => 'nullable|string',
            'final_notes' => 'nullable|string',
        ]);

        $data['is_hold'] = filter_var($data['is_hold'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $data;
    }

    private function syncVacancyRecruitment(JobVacancy $job, array $data): void
    {
        $config = $job->recruitmentConfig()->firstOrCreate([]);
        $config->update([
            'headcount_needed' => $data['headcount_needed'] ?? null,
            'is_hold' => $data['is_hold'] ?? false,
            'search_start_date' => $data['search_start_date'] ?? null,
            'target_fulfill_date' => $data['target_fulfill_date'] ?? null,
            'hr_interview_notes' => $data['hr_interview_notes'] ?? null,
            'user_interview_notes' => $data['user_interview_notes'] ?? null,
            'final_notes' => $data['final_notes'] ?? null,
        ]);

        if (array_key_exists('pic_user_ids', $data)) {
            $job->pics()->sync($data['pic_user_ids'] ?? []);
        }
    }
}
