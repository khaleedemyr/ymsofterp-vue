<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RouletteTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function array(): array
    {
        // Sample data untuk template
        return [
            [
                'John Doe',
                'john.doe@example.com',
                '081234567890'
            ],
            [
                'Jane Smith',
                'jane.smith@example.com',
                '081234567891'
            ],
            [
                'Bob Johnson',
                'bob.johnson@example.com',
                '081234567892'
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Nama *',
            'Email (Opsional)',
            'No HP (Opsional)'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style untuk header
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Style untuk semua cell
        $sheet->getStyle('A2:C' . ($sheet->getHighestRow() + 5))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        // Tambahkan instruksi di bawah data
        $lastRow = $sheet->getHighestRow();
        $sheet->setCellValue('A' . ($lastRow + 2), 'INSTRUKSI:');
        $sheet->setCellValue('A' . ($lastRow + 3), '1. Nama adalah field WAJIB (tidak boleh kosong)');
        $sheet->setCellValue('A' . ($lastRow + 4), '2. Email dan No HP adalah OPSIONAL');
        $sheet->setCellValue('A' . ($lastRow + 5), '3. Email harus valid jika diisi');
        $sheet->setCellValue('A' . ($lastRow + 6), '4. No HP maksimal 15 digit jika diisi');
        $sheet->setCellValue('A' . ($lastRow + 7), '5. HAPUS SEMUA BARIS CONTOH DAN INSTRUKSI SEBELUM UPLOAD!');

        // Style untuk instruksi
        $sheet->getStyle('A' . ($lastRow + 2) . ':A' . ($lastRow + 7))->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'DC2626'], // Red color
            ],
        ]);

        return $sheet;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25, // Nama
            'B' => 30, // Email
            'C' => 15, // No HP
        ];
    }
} 