<?php

namespace App\Services;

use App\Models\LmsCertificate;
use App\Models\CertificateTemplate;
use TCPDF;
use Illuminate\Support\Facades\Storage;

class CertificatePdfService
{
    public function generatePdf(LmsCertificate $certificate)
    {
        $template = $certificate->template;
        if (!$template) {
            throw new \Exception('Template sertifikat tidak ditemukan');
        }

        // Create new PDF document
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        
        // Set document information
        $pdf->SetCreator('YMSoft ERP');
        $pdf->SetAuthor('YMSoft ERP');
        $pdf->SetTitle('Sertifikat - ' . $certificate->certificate_number);
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        
        // Add a page
        $pdf->AddPage();
        
        // Add background image
        $backgroundPath = storage_path('app/public/' . $template->background_image);
        if (file_exists($backgroundPath)) {
            // Get page dimensions
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            
            // Add background image to cover entire page
            $pdf->Image($backgroundPath, 0, 0, $pageWidth, $pageHeight, '', '', '', false, 300, '', false, false, 0);
        }
        
        // Get certificate data
        $data = $this->getCertificateData($certificate);
        
        // Add text overlays
        $this->addTextOverlays($pdf, $template, $data);
        
        return $pdf;
    }

    private function getCertificateData(LmsCertificate $certificate)
    {
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

        return [
            'participant_name' => $certificate->user->nama_lengkap ?? 'Unknown',
            'course_title' => $certificate->course->title ?? 'Unknown Course',
            'completion_date' => $certificate->issued_at ? $certificate->issued_at->format('d F Y') : date('d F Y'),
            'certificate_number' => $certificate->certificate_number,
            'instructor_name' => $instructorName,
            'training_location' => $trainingLocation
        ];
    }

    private function addTextOverlays(TCPDF $pdf, CertificateTemplate $template, array $data)
    {
        // A4 Landscape dimensions: 297mm x 210mm
        $pageWidth = 297; // mm
        $pageHeight = 210; // mm
        
        // Set text color to black
        $pdf->SetTextColor(0, 0, 0);
        
        // Title: SERTIFIKAT (centered, top)
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetXY(0, 25);
        $pdf->Cell($pageWidth, 15, 'SERTIFIKAT', 0, 1, 'C');
        
        // Participant Name (centered, below title)
        if (isset($data['participant_name'])) {
            $pdf->SetFont('helvetica', 'B', 24);
            $pdf->SetXY(0, 50);
            $pdf->Cell($pageWidth, 15, $data['participant_name'], 0, 1, 'C');
        }
        
        // Course Title (centered)
        if (isset($data['course_title'])) {
            $pdf->SetFont('helvetica', '', 16);
            $pdf->SetXY(0, 75);
            $pdf->Cell($pageWidth, 15, $data['course_title'], 0, 1, 'C');
        }
        
        // Completion Date (centered)
        if (isset($data['completion_date'])) {
            $pdf->SetFont('helvetica', '', 14);
            $pdf->SetXY(0, 95);
            $pdf->Cell($pageWidth, 15, $data['completion_date'], 0, 1, 'C');
        }
        
        // Training Location (centered)
        if (isset($data['training_location'])) {
            $pdf->SetFont('helvetica', '', 12);
            $pdf->SetXY(0, 115);
            $pdf->Cell($pageWidth, 15, $data['training_location'], 0, 1, 'C');
        }
        
        // Certificate Number (bottom left)
        if (isset($data['certificate_number'])) {
            $pdf->SetFont('courier', '', 10);
            $pdf->SetXY(20, $pageHeight - 25);
            $pdf->Cell(0, 10, $data['certificate_number'], 0, 1, 'L');
        }
        
        // Instructor Name (bottom right)
        if (isset($data['instructor_name'])) {
            $pdf->SetFont('helvetica', '', 12);
            $pdf->SetXY(0, $pageHeight - 25);
            $pdf->Cell($pageWidth - 20, 10, $data['instructor_name'], 0, 1, 'R');
        }
    }

    private function mapFontFamily($fontFamily)
    {
        // Map CSS font families to TCPDF supported fonts
        $fontMap = [
            'Arial' => 'helvetica',
            'Helvetica' => 'helvetica',
            'Times New Roman' => 'times',
            'Georgia' => 'times',
            'Palatino' => 'times',
            'Garamond' => 'times',
            'Bookman' => 'times',
            'Verdana' => 'helvetica',
            'Trebuchet MS' => 'helvetica',
            'Impact' => 'helvetica',
            'Comic Sans MS' => 'helvetica',
            'Courier New' => 'courier',
        ];
        
        return $fontMap[$fontFamily] ?? 'helvetica';
    }

    private function hexToRgb($hex)
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }

    public function downloadPdf(LmsCertificate $certificate, $filename = null)
    {
        $pdf = $this->generatePdf($certificate);
        
        if (!$filename) {
            $filename = 'Sertifikat_' . $certificate->certificate_number . '.pdf';
        }
        
        // Output PDF for download
        return $pdf->Output($filename, 'D');
    }

    public function savePdf(LmsCertificate $certificate, $path = null)
    {
        $pdf = $this->generatePdf($certificate);
        
        if (!$path) {
            $path = 'certificates/' . $certificate->certificate_number . '.pdf';
        }
        
        // Save to storage
        $pdfContent = $pdf->Output('', 'S');
        Storage::disk('public')->put($path, $pdfContent);
        
        return $path;
    }
}
