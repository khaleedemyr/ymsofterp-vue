<?php

namespace App\Http\Controllers;

use App\Models\LmsCertificate;
use App\Models\LmsCourse;
use App\Services\CertificatePdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LmsCertificateController extends Controller
{
    public function index()
    {
        $certificates = LmsCertificate::with(['course', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Lms/Certificates/Index', [
            'certificates' => $certificates
        ]);
    }

    public function create()
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Certificates/Create', [
            'courses' => $courses
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'user_id' => 'required|exists:users,id',
            'certificate_number' => 'required|string|unique:lms_certificates',
            'issued_at' => 'required|date',
            'expires_at' => 'nullable|date|after:issued_at',
            'status' => 'required|in:active,expired,revoked'
        ]);

        LmsCertificate::create($validated);

        return redirect()->route('lms.certificates.index')
            ->with('success', 'Sertifikat berhasil dibuat');
    }

    public function show(LmsCertificate $certificate)
    {
        $certificate->load(['course', 'user']);

        return Inertia::render('Lms/Certificates/Show', [
            'certificate' => $certificate
        ]);
    }

    public function edit(LmsCertificate $certificate)
    {
        $courses = LmsCourse::where('status', 'published')
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Lms/Certificates/Edit', [
            'certificate' => $certificate,
            'courses' => $courses
        ]);
    }

    public function update(Request $request, LmsCertificate $certificate)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'user_id' => 'required|exists:users,id',
            'certificate_number' => 'required|string|unique:lms_certificates,certificate_number,' . $certificate->id,
            'issued_at' => 'required|date',
            'expires_at' => 'nullable|date|after:issued_at',
            'status' => 'required|in:active,expired,revoked'
        ]);

        $certificate->update($validated);

        return redirect()->route('lms.certificates.index')
            ->with('success', 'Sertifikat berhasil diperbarui');
    }

    public function destroy(LmsCertificate $certificate)
    {
        $certificate->delete();

        return redirect()->route('lms.certificates.index')
            ->with('success', 'Sertifikat berhasil dihapus');
    }

    public function download(LmsCertificate $certificate, CertificatePdfService $pdfService)
    {
        try {
            $certificate->load(['course', 'user', 'template']);
            
            if (!$certificate->template) {
                return back()->withErrors(['error' => 'Template sertifikat tidak ditemukan']);
            }
            
            return $pdfService->downloadPdf($certificate);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengunduh sertifikat: ' . $e->getMessage()]);
        }
    }

    public function preview(LmsCertificate $certificate)
    {
        try {
            $certificate->load(['course', 'user', 'template']);
            
            if (!$certificate->template) {
                return back()->withErrors(['error' => 'Template sertifikat tidak ditemukan']);
            }
            
            // Get trainer name from training schedule
            $instructorName = 'Instruktur Training'; // Default value
            
            // Try to get trainer and location from training schedule
            $trainingSchedule = \App\Models\TrainingSchedule::where('course_id', $certificate->course_id)
                ->whereDate('scheduled_date', $certificate->issued_at ? $certificate->issued_at->format('Y-m-d') : now()->format('Y-m-d'))
                ->with(['scheduleTrainers.trainer', 'outlet'])
                ->first();
                
            if ($trainingSchedule && $trainingSchedule->scheduleTrainers->isNotEmpty()) {
                $primaryTrainer = $trainingSchedule->scheduleTrainers->where('is_primary_trainer', true)->first();
                if ($primaryTrainer && $primaryTrainer->trainer) {
                    $instructorName = $primaryTrainer->trainer->nama_lengkap;
                } else {
                    // If no primary trainer, get the first trainer
                    $firstTrainer = $trainingSchedule->scheduleTrainers->first();
                    if ($firstTrainer && $firstTrainer->trainer) {
                        $instructorName = $firstTrainer->trainer->nama_lengkap;
                    }
                }
            }
            
            // Get training location from outlet
            $trainingLocation = 'Lokasi Training'; // Default value
            if ($trainingSchedule && $trainingSchedule->outlet) {
                $trainingLocation = $trainingSchedule->outlet->nama_outlet;
            }
            
            // Generate sample data for preview
            $sampleData = [
                'participant_name' => $certificate->user->nama_lengkap,
                'course_title' => $certificate->course->title,
                'completion_date' => $certificate->issued_at ? $certificate->issued_at->format('d F Y') : now()->format('d F Y'),
                'certificate_number' => $certificate->certificate_number,
                'instructor_name' => $instructorName,
                'training_location' => $trainingLocation
            ];
            
            return Inertia::render('Lms/Certificates/Preview', [
                'certificate' => $certificate,
                'sampleData' => $sampleData
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal memuat preview sertifikat: ' . $e->getMessage()]);
        }
    }
} 