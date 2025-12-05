<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WarehouseSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $report;
    private $tanggal;

    public function __construct($report, $tanggal = null)
    {
        $this->report = $report;
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        try {
            $data = collect($this->report);
            
            if ($data->isEmpty()) {
                return collect([]);
            }
            
            // Prepare warehouse data with totals
            $warehouseData = [];
            
            // Add warehouse rows
            foreach ($data as $row) {
                $warehouseData[] = [
                    'customer' => $row->customer,
                    'main_kitchen' => $row->main_kitchen,
                    'main_store' => $row->main_store,
                    'chemical' => $row->chemical,
                    'stationary' => $row->stationary,
                    'marketing' => $row->marketing,
                    'line_total' => $row->line_total,
                    'type' => 'warehouse'
                ];
            }
            
            // Add total row for warehouse
            if ($data->isNotEmpty()) {
                $warehouseData[] = [
                    'customer' => 'TOTAL WAREHOUSE',
                    'main_kitchen' => $data->sum('main_kitchen'),
                    'main_store' => $data->sum('main_store'),
                    'chemical' => $data->sum('chemical'),
                    'stationary' => $data->sum('stationary'),
                    'marketing' => $data->sum('marketing'),
                    'line_total' => $data->sum('line_total'),
                    'type' => 'total'
                ];
            }
            
            return collect($warehouseData);
        } catch (\Exception $e) {
            \Log::error('Warehouse sheet collection error: ' . $e->getMessage());
            return collect([]);
        }
    }

    public function headings(): array
    {
        return [
            'Warehouse',
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
            $row['customer'],
            $row['main_kitchen'],
            $row['main_store'],
            $row['chemical'],
            $row['stationary'],
            $row['marketing'],
            $row['line_total'],
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
        
        // Style total row
        for ($row = 2; $row <= $highestRow; $row++) {
            $customerValue = $sheet->getCell('A' . $row)->getValue();
            
            if ($customerValue === 'TOTAL WAREHOUSE') {
                // Background color for total row
                $sheet->getStyle('A' . $row . ':' . $highestCol . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'dbeafe'] // Light blue background
                    ],
                    'font' => ['bold' => true]
                ]);
            }
        }
        
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // Warehouse name
            'B' => 20, // Main Kitchen
            'C' => 20, // Main Store
            'D' => 20, // Chemical
            'E' => 20, // Stationary
            'F' => 20, // Marketing
            'G' => 20, // Total
        ];
    }
}
