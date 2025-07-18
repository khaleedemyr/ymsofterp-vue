<?php

namespace App\Http\Controllers;

use App\Models\LmsCertificate;
use App\Models\LmsCourse;
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

    public function download(LmsCertificate $certificate)
    {
        // Generate PDF certificate
        // This is a placeholder - you would implement actual PDF generation
        return response()->json([
            'message' => 'Certificate download functionality would be implemented here'
        ]);
    }
} 