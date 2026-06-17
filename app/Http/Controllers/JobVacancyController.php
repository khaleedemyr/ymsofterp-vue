<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobVacancy;
use App\Models\JobVacancyApplication;
use App\Models\JobVacancyRecruitmentConfig;
use App\Support\RecruitmentStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JobVacancyController extends Controller
{
    // List untuk admin panel (semua)
    public function index(Request $request)
    {
        $query = JobVacancy::query();
        if ($request->has('search') && $request->search) {
            $q = '%' . $request->search . '%';
            $query->where(function($sub) use ($q) {
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
        
        // Hybrid: jika request expects JSON (AJAX/axios), return JSON
        if ($request->wantsJson()) {
            return response()->json($jobs);
        }
        // Jika bukan, return Inertia
        return \Inertia\Inertia::render('Admin/JobVacancy/Index', [
            'vacancies' => $jobs,
            'filters' => [
                'search' => $request->search ?? '',
                'is_active' => $request->is_active ?? '',
                'job_scope' => $request->job_scope ?? '',
            ],
        ]);
    }

    // List untuk landing page (hanya aktif & belum tutup)
    public function publicList(Request $request)
    {
        $jobs = JobVacancy::query()
            ->where(function ($q) {
                // Be tolerant across schema variants (tinyint/enum/string) on server DB.
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

    // Simpan/tambah lowongan
    public function store(Request $request)
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
        ]);
        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('job_banners', 'public');
        }
        $job = JobVacancy::create($data);
        JobVacancyRecruitmentConfig::create(['job_vacancy_id' => $job->id]);
        return response()->json($job);
    }

    // Update lowongan
    public function update(Request $request, $id)
    {
        $job = JobVacancy::findOrFail($id);
        $data = $request->validate([
            'position' => 'required',
            'description' => 'required',
            'requirements' => 'nullable',
            'location' => 'required',
            'job_scope' => 'required|in:outlet,head_office',
            'closing_date' => 'required|date',
            'is_active' => 'required|boolean',
            'banner' => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('banner')) {
            // Hapus banner lama jika ada
            if ($job->banner) Storage::disk('public')->delete($job->banner);
            $data['banner'] = $request->file('banner')->store('job_banners', 'public');
        }
        $job->update($data);
        return response()->json($job);
    }

    // Hapus lowongan
    public function destroy($id)
    {
        $job = JobVacancy::findOrFail($id);
        if ($job->banner) Storage::disk('public')->delete($job->banner);
        $job->delete();
        return response()->json(['success' => true]);
    }

    // Set aktif/nonaktif
    public function setActive($id, Request $request)
    {
        $job = JobVacancy::findOrFail($id);
        $job->is_active = $request->input('is_active', 1);
        $job->save();
        return response()->json($job);
    }

    // Detail
    public function show($id)
    {
        $job = JobVacancy::findOrFail($id);
        return response()->json($job);
    }

    // Public apply endpoint
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
            'recruitment_stage' => 'sourcing',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lamaran berhasil dikirim.',
        ]);
    }

    // Admin list applications
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

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('scope') && in_array($request->scope, ['outlet', 'head_office'], true)) {
            $query->whereHas('jobVacancy', function ($job) use ($request) {
                $job->where('job_scope', $request->scope);
            });
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
                'status' => $request->status ?? '',
                'scope' => $request->scope ?? '',
            ],
            'statusOptions' => ['submitted', 'reviewed', 'interview', 'hired', 'rejected'],
            'recruitmentStages' => RecruitmentStage::STAGES,
            'recruitmentStageLabels' => RecruitmentStage::LABELS,
        ]);
    }

    public function applicationSetRecruitmentStage(Request $request, $id)
    {
        $data = $request->validate([
            'recruitment_stage' => 'required|in:'.implode(',', RecruitmentStage::STAGES),
            'stage_notes' => 'nullable|string',
            'joined_at' => 'nullable|date',
        ]);

        $application = JobVacancyApplication::findOrFail($id);
        $application->recruitment_stage = $data['recruitment_stage'];
        $application->stage_notes = $data['stage_notes'] ?? $application->stage_notes;
        $application->status = RecruitmentStage::legacyStatusForStage($data['recruitment_stage']);

        if ($data['recruitment_stage'] === 'joined') {
            $application->joined_at = $data['joined_at'] ?? now()->toDateString();
        } else {
            $application->joined_at = $data['joined_at'] ?? null;
        }

        $application->save();

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->json([
            'success' => true,
            'message' => 'Progress pelamar berhasil diperbarui.',
            'application' => $application,
        ]);
    }

    public function applicationSetStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:submitted,reviewed,interview,hired,rejected',
        ]);

        $application = JobVacancyApplication::findOrFail($id);
        $application->status = $data['status'];
        $application->save();

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->json([
            'success' => true,
            'message' => 'Status pelamar berhasil diperbarui.',
            'application' => $application,
        ]);
    }

    public function recruitmentDashboard(Request $request)
    {
        $query = JobVacancy::query()->with(['recruitmentConfig']);

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
                    ->orWhereHas('recruitmentConfig', function ($cfg) use ($q) {
                        $cfg->where('pic', 'like', $q)
                            ->orWhere('final_notes', 'like', $q);
                    });
            });
        }

        $vacancies = $query->orderByDesc('created_at')->get();

        $vacancyIds = $vacancies->pluck('id');
        $stageCounts = JobVacancyApplication::query()
            ->whereIn('job_vacancy_id', $vacancyIds)
            ->select('job_vacancy_id', 'recruitment_stage', DB::raw('COUNT(*) as total'))
            ->groupBy('job_vacancy_id', 'recruitment_stage')
            ->get()
            ->groupBy('job_vacancy_id');

        $vacancies->each(function (JobVacancy $vacancy) use ($stageCounts) {
            if (! $vacancy->recruitmentConfig) {
                $vacancy->recruitmentConfig()->create([]);
                $vacancy->load('recruitmentConfig');
            }

            $counts = RecruitmentStage::emptyCounts();
            foreach ($stageCounts->get($vacancy->id, collect()) as $row) {
                $stage = $row->recruitment_stage;
                if (array_key_exists($stage, $counts)) {
                    $counts[$stage] = (int) $row->total;
                }
            }

            $vacancy->stage_counts = $counts;
            $vacancy->join_date = JobVacancyApplication::query()
                ->where('job_vacancy_id', $vacancy->id)
                ->where('recruitment_stage', 'joined')
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
            'pic' => 'nullable|string|max:100',
            'headcount_needed' => 'nullable|integer|min:0|max:999',
            'is_hold' => 'boolean',
            'search_start_date' => 'nullable|date',
            'target_fulfill_date' => 'nullable|date',
            'hr_interview_notes' => 'nullable|string',
            'user_interview_notes' => 'nullable|string',
            'final_notes' => 'nullable|string',
        ]);

        $config = $job->recruitmentConfig()->firstOrCreate([]);
        $config->fill($data);
        $config->save();

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->json([
            'success' => true,
            'message' => 'Config rekrutmen posisi berhasil disimpan.',
            'config' => $config->fresh(),
        ]);
    }
} 