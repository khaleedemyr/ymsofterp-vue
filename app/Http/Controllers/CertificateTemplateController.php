<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class CertificateTemplateController extends Controller
{
    public function index()
    {
        $templates = CertificateTemplate::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Lms/CertificateTemplates/Index', [
            'templates' => $templates
        ]);
    }

    public function create()
    {
        return Inertia::render('Lms/CertificateTemplates/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'background_image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'text_positions' => 'nullable|array',
            'style_settings' => 'nullable|array',
            'status' => 'required|in:active,inactive'
        ]);

        // Upload background image
        $backgroundPath = $request->file('background_image')->store('certificate-templates', 'public');

        $template = CertificateTemplate::create([
            'name' => $request->name,
            'description' => $request->description,
            'background_image' => $backgroundPath,
            'text_positions' => $request->text_positions ?: (new CertificateTemplate())->getDefaultTextPositions(),
            'style_settings' => $request->style_settings ?: [
                'font_family' => 'Arial',
                'text_color' => '#000000',
                'text_align' => 'center'
            ],
            'status' => $request->status,
        ]);

        return redirect()->route('lms.certificate-templates.index')
            ->with('success', 'Template sertifikat berhasil dibuat');
    }

    public function show(CertificateTemplate $template)
    {
        $template->load('creator');
        
        return Inertia::render('Lms/CertificateTemplates/Show', [
            'template' => $template
        ]);
    }

    public function edit(CertificateTemplate $template)
    {
        return Inertia::render('Lms/CertificateTemplates/Edit', [
            'template' => $template
        ]);
    }

    public function update(Request $request, CertificateTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'background_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'text_positions' => 'nullable|array',
            'style_settings' => 'nullable|array',
            'status' => 'required|in:active,inactive'
        ]);

        $updateData = [
            'name' => $request->name,
            'description' => $request->description,
            'text_positions' => $request->text_positions,
            'style_settings' => $request->style_settings,
            'status' => $request->status,
        ];

        // Upload new background image if provided
        if ($request->hasFile('background_image')) {
            // Delete old image
            if ($template->background_image) {
                Storage::disk('public')->delete($template->background_image);
            }
            
            $updateData['background_image'] = $request->file('background_image')->store('certificate-templates', 'public');
        }

        $template->update($updateData);

        return redirect()->route('lms.certificate-templates.index')
            ->with('success', 'Template sertifikat berhasil diperbarui');
    }

    public function destroy(CertificateTemplate $template)
    {
        // Check if template is being used
        if ($template->certificates()->count() > 0) {
            return back()->withErrors(['error' => 'Template tidak dapat dihapus karena sedang digunakan']);
        }

        // Delete background image
        if ($template->background_image) {
            Storage::disk('public')->delete($template->background_image);
        }

        $template->delete();

        return redirect()->route('lms.certificate-templates.index')
            ->with('success', 'Template sertifikat berhasil dihapus');
    }

    public function preview(CertificateTemplate $template)
    {
        // Generate preview with sample data
        $sampleData = [
            'participant_name' => 'John Doe',
            'course_title' => 'Sample Training Course',
            'completion_date' => now()->format('d F Y'),
            'certificate_number' => 'CERT-SAMPLE-001',
            'instructor_name' => 'Jane Smith'
        ];

        return Inertia::render('Lms/CertificateTemplates/Preview', [
            'template' => $template,
            'sampleData' => $sampleData
        ]);
    }
}
