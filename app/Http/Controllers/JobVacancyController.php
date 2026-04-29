<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobVacancy;
use App\Models\JobVacancyApplication;
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
            'cover_letter' => 'nullable|string',
            'cv_file' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $cvPath = null;
        if ($request->hasFile('cv_file')) {
            $file = $request->file('cv_file');
            $fileName = time().'_cv_'.$job->id.'_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $cvPath = $file->storeAs('job_applications/cv', $fileName, 'public');
        }

        JobVacancyApplication::create([
            'job_vacancy_id' => $job->id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'cover_letter' => $data['cover_letter'] ?? null,
            'cv_file' => $cvPath,
            'status' => 'submitted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lamaran berhasil dikirim.',
        ]);
    }
} 