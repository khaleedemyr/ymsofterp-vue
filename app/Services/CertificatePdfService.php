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
        return [
            'participant_name' => $certificate->user->nama_lengkap ?? 'Unknown',
            'course_title' => $certificate->course->title ?? 'Unknown Course',
            'completion_date' => $certificate->issued_at ? $certificate->issued_at->format('d F Y') : date('d F Y'),
            'certificate_number' => $certificate->certificate_number,
            'instructor_name' => $certificate->course->instructor->nama_lengkap ?? 'Unknown Instructor'
        ];
    }

    private function addTextOverlays(TCPDF $pdf, CertificateTemplate $template, array $data)
    {
        $positions = $template->text_positions ?? [];
        $style = $template->style_settings ?? [
            'font_family' => 'helvetica',
            'text_color' => '#000000',
            'text_align' => 'C'
        ];

        // Convert hex color to RGB
        $textColor = $this->hexToRgb($style['text_color'] ?? '#000000');
        $pdf->SetTextColor($textColor['r'], $textColor['g'], $textColor['b']);

        foreach ($positions as $field => $pos) {
            if (isset($data[$field])) {
                // Set font - prioritize field-specific font over global
                $fontFamily = $this->mapFontFamily($pos['font_family'] ?? $style['font_family'] ?? 'Arial');
                $fontSize = $pos['font_size'] ?? 12;
                $fontStyle = ($pos['font_weight'] ?? 'normal') === 'bold' ? 'B' : '';
                
                $pdf->SetFont($fontFamily, $fontStyle, $fontSize);
                
                // Calculate position (convert from pixels to mm approximately)
                $x = ($pos['x'] ?? 0) * 0.264583; // pixel to mm conversion
                $y = ($pos['y'] ?? 0) * 0.264583;
                
                // Determine alignment
                $align = $style['text_align'] ?? 'C';
                $width = 0; // Auto width
                
                if ($align === 'C') {
                    // For center alignment, we need to specify width
                    $width = $pdf->getPageWidth() - $x;
                }
                
                // Add text
                $pdf->SetXY($x, $y);
                $pdf->Cell($width, 10, $data[$field], 0, 1, $align);
            }
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
