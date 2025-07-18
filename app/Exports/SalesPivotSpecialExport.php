<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;

class SalesPivotSpecialExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, Responsable
{
    private $report;
    private $tanggal;
    public $fileName = 'sales_pivot_special.xlsx';

    public function __construct($report, $tanggal = null)
    {
        $this->report = $report;
        $this->tanggal = $tanggal;
        if ($tanggal) {
            $this->fileName = 'sales_pivot_special_' . $tanggal . '.xlsx';
        }
    }

    public function collection()
    {
        try {
            $data = collect($this->report);
            
            if ($data->isEmpty()) {
                return collect([]);
            }
            
            return $data;
        } catch (\Exception $e) {
            \Log::error('Export collection error: ' . $e->getMessage());
            return collect([]);
        }
    }

    public function headings(): array
    {
        return [
            'Outlet',
            'Main Kitchen',
            'Main Store',
            'Chemical',
            'Stationary',
            'Marketing',
            'Total'
        ];
    }

    public function map($row): array
    {
        return [
            $row->customer,
            $row->main_kitchen,
            $row->main_store,
            $row->chemical,
            $row->stationary,
            $row->marketing,
            $row->line_total,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestCol = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();
        
        // Header styling
        $sheet->getStyle('A1:' . $highestCol . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563eb']
            ],
        ]);
        
        // All borders
        $sheet->getStyle('A1:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Number formatting for currency columns (all columns except first)
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
        for ($col = 2; $col <= $highestColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle($colLetter . '2:' . $colLetter . $highestRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // Outlet name
            'B' => 20, // Main Kitchen
            'C' => 20, // Main Store
            'D' => 20, // Chemical
            'E' => 20, // Stationary
            'F' => 20, // Marketing
            'G' => 20, // Total
        ];
    }

    public function toResponse($request)
    {
        try {
            return Excel::download($this, $this->fileName);
        } catch (\Exception $e) {
            \Log::error('Export toResponse error: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan saat generate file Excel'], 500);
        }
    }
} 