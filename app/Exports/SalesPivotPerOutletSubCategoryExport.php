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

class SalesPivotPerOutletSubCategoryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, Responsable
{
    private $report;
    private $subCategories;
    private $tanggal;
    public $fileName = 'sales_pivot_per_outlet_sub_category.xlsx';

    public function __construct($report, $subCategories, $tanggal = null)
    {
        $this->report = $report;
        $this->subCategories = $subCategories;
        $this->tanggal = $tanggal;
        if ($tanggal) {
            $this->fileName = 'sales_pivot_per_outlet_sub_category_' . $tanggal . '.xlsx';
        }
    }

    public function collection()
    {
        try {
            $data = collect($this->report);
            
            if ($data->isEmpty()) {
                // Return empty collection with headers only
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
        $headings = ['Outlet', 'Total'];
        foreach ($this->subCategories as $subCategory) {
            $headings[] = $subCategory->name;
        }
        return $headings;
    }

    public function map($row): array
    {
        $data = [
            is_object($row) ? $row->customer : $row['customer'],
            is_object($row) ? $row->line_total : $row['line_total'],
        ];
        
        foreach ($this->subCategories as $subCategory) {
            $value = 0;
            if (is_object($row)) {
                $value = $row->{$subCategory->name} ?? 0;
            } else {
                $value = $row[$subCategory->name] ?? 0;
            }
            $data[] = $value;
        }
        
        return $data;
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
        
        // Total row styling (last row)
        if ($highestRow > 1) {
            $sheet->getStyle('A' . $highestRow . ':' . $highestCol . $highestRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'f3f4f6']
                ],
            ]);
            
            // Check if last row is total row
            $lastRowCustomer = $sheet->getCell('A' . $highestRow)->getValue();
            if ($lastRowCustomer === 'TOTAL') {
                $sheet->getStyle('A' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            }
        }
        
        // All borders
        $sheet->getStyle('A1:' . $highestCol . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Number formatting for currency columns
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
        for ($col = 2; $col <= $highestColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle($colLetter . '2:' . $colLetter . $highestRow)->getNumberFormat()->setFormatCode('#,##0');
        }
        
        return [];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 30, // Outlet name
            'B' => 20, // Total
        ];
        
        // Set width for sub category columns
        $colIndex = 3; // Start from column C
        foreach ($this->subCategories as $subCategory) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $widths[$colLetter] = 20;
            $colIndex++;
        }
        
        return $widths;
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