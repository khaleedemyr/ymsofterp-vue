<?php

namespace App\Http\Controllers;

use App\Models\JabatanRequiredTraining;
use App\Models\LmsCourse;
use App\Models\Jabatan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JabatanTrainingController extends Controller
{
    public function index(Request $request)
    {
        $query = JabatanRequiredTraining::with(['jabatan.divisi', 'course.category', 'course.trainers']);

        // Filter by jabatan
        if ($request->jabatan_id) {
            $query->where('jabatan_id', $request->jabatan_id);
        }

        // Filter by course
        if ($request->course_id) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by mandatory/optional
        if ($request->is_mandatory !== null) {
            $query->where('is_mandatory', $request->is_mandatory);
        }

        $requiredTrainings = $query->orderBy('jabatan.nama_jabatan')
            ->orderBy('is_mandatory', 'desc')
            ->orderBy('course.title')
            ->paginate(20);

        // Get filter options
        $jabatans = Jabatan::active()
            ->with(['divisi'])
            ->orderBy('nama_jabatan')
            ->get(['id_jabatan', 'nama_jabatan', 'id_divisi']);

        $courses = LmsCourse::published()
            ->with(['category'])
            ->orderBy('title')
            ->get(['id', 'title', 'category_id']);

        return Inertia::render('Training/JabatanTraining/Index', [
            'requiredTrainings' => $requiredTrainings,
            'jabatans' => $jabatans,
            'courses' => $courses,
            'filters' => $request->only(['jabatan_id', 'course_id', 'is_mandatory']),
        ]);
    }

    public function create()
    {
        $jabatans = Jabatan::active()
            ->with(['divisi'])
            ->orderBy('nama_jabatan')
            ->get(['id_jabatan', 'nama_jabatan', 'id_divisi']);

        $courses = LmsCourse::published()
            ->with(['category'])
            ->orderBy('title')
            ->get(['id', 'title', 'category_id']);

        return Inertia::render('Training/JabatanTraining/Create', [
            'jabatans' => $jabatans,
            'courses' => $courses,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jabatan_id' => 'required|exists:tbl_data_jabatan,id_jabatan',
            'course_id' => 'required|exists:lms_courses,id',
            'is_mandatory' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if combination already exists
        $existing = JabatanRequiredTraining::where('jabatan_id', $request->jabatan_id)
            ->where('course_id', $request->course_id)
            ->first();

        if ($existing) {
            return back()->withErrors(['error' => 'Training sudah terdaftar untuk jabatan ini']);
        }

        try {
            JabatanRequiredTraining::create([
                'jabatan_id' => $request->jabatan_id,
                'course_id' => $request->course_id,
                'is_mandatory' => $request->is_mandatory,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            return redirect()->route('jabatan-training.index')
                ->with('success', 'Training berhasil ditambahkan untuk jabatan');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menambahkan training: ' . $e->getMessage()]);
        }
    }

    public function show(JabatanRequiredTraining $jabatanTraining)
    {
        $jabatanTraining->load(['jabatan.divisi', 'course.category', 'course.trainers.trainer']);

        // Get users with this jabatan
        $users = User::where('id_jabatan', $jabatanTraining->jabatan_id)
            ->where('status', 'A')
            ->with(['jabatan', 'divisi'])
            ->get();

        // Get training hours for this course and jabatan users
        $trainingHours = \App\Models\UserTrainingHours::where('course_id', $jabatanTraining->course_id)
            ->whereIn('user_id', $users->pluck('id'))
            ->with(['user.jabatan', 'user.divisi'])
            ->get();

        return Inertia::render('Training/JabatanTraining/Show', [
            'jabatanTraining' => $jabatanTraining,
            'users' => $users,
            'trainingHours' => $trainingHours,
        ]);
    }

    public function edit(JabatanRequiredTraining $jabatanTraining)
    {
        $jabatanTraining->load(['jabatan.divisi', 'course.category']);

        $jabatans = Jabatan::active()
            ->with(['divisi'])
            ->orderBy('nama_jabatan')
            ->get(['id_jabatan', 'nama_jabatan', 'id_divisi']);

        $courses = LmsCourse::published()
            ->with(['category'])
            ->orderBy('title')
            ->get(['id', 'title', 'category_id']);

        return Inertia::render('Training/JabatanTraining/Edit', [
            'jabatanTraining' => $jabatanTraining,
            'jabatans' => $jabatans,
            'courses' => $courses,
        ]);
    }

    public function update(Request $request, JabatanRequiredTraining $jabatanTraining)
    {
        $request->validate([
            'jabatan_id' => 'required|exists:tbl_data_jabatan,id_jabatan',
            'course_id' => 'required|exists:lms_courses,id',
            'is_mandatory' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if combination already exists (excluding current record)
        $existing = JabatanRequiredTraining::where('jabatan_id', $request->jabatan_id)
            ->where('course_id', $request->course_id)
            ->where('id', '!=', $jabatanTraining->id)
            ->first();

        if ($existing) {
            return back()->withErrors(['error' => 'Training sudah terdaftar untuk jabatan ini']);
        }

        try {
            $jabatanTraining->update([
                'jabatan_id' => $request->jabatan_id,
                'course_id' => $request->course_id,
                'is_mandatory' => $request->is_mandatory,
                'notes' => $request->notes,
                'updated_by' => auth()->id(),
            ]);

            return redirect()->route('jabatan-training.index')
                ->with('success', 'Training berhasil diperbarui');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal memperbarui training: ' . $e->getMessage()]);
        }
    }

    public function destroy(JabatanRequiredTraining $jabatanTraining)
    {
        try {
            $jabatanTraining->delete();

            return redirect()->route('jabatan-training.index')
                ->with('success', 'Training berhasil dihapus dari jabatan');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus training: ' . $e->getMessage()]);
        }
    }

    public function getTrainingsForJabatan($jabatanId)
    {
        $mandatoryTrainings = JabatanRequiredTraining::with(['course.category', 'course.trainers.trainer'])
            ->where('jabatan_id', $jabatanId)
            ->where('is_mandatory', true)
            ->orderBy('course.title')
            ->get();

        $optionalTrainings = JabatanRequiredTraining::with(['course.category', 'course.trainers.trainer'])
            ->where('jabatan_id', $jabatanId)
            ->where('is_mandatory', false)
            ->orderBy('course.title')
            ->get();

        return response()->json([
            'mandatory' => $mandatoryTrainings,
            'optional' => $optionalTrainings,
        ]);
    }

    public function getUsersForJabatan($jabatanId)
    {
        $users = User::where('id_jabatan', $jabatanId)
            ->where('status', 'A')
            ->with(['jabatan', 'divisi'])
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json($users);
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'jabatan_ids' => 'required|array',
            'jabatan_ids.*' => 'exists:tbl_data_jabatan,id_jabatan',
            'course_id' => 'required|exists:lms_courses,id',
            'is_mandatory' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $created = 0;
            $skipped = 0;

            foreach ($request->jabatan_ids as $jabatanId) {
                // Check if combination already exists
                $existing = JabatanRequiredTraining::where('jabatan_id', $jabatanId)
                    ->where('course_id', $request->course_id)
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                JabatanRequiredTraining::create([
                    'jabatan_id' => $jabatanId,
                    'course_id' => $request->course_id,
                    'is_mandatory' => $request->is_mandatory,
                    'notes' => $request->notes,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $created++;
            }

            DB::commit();

            $message = "Berhasil menambahkan training ke {$created} jabatan";
            if ($skipped > 0) {
                $message .= " ({$skipped} jabatan sudah memiliki training ini)";
            }

            return redirect()->route('jabatan-training.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Gagal menambahkan training: ' . $e->getMessage()]);
        }
    }
}
